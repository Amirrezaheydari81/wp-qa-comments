<?php
/**
 * JSON importer for demo/seed Q&A data.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPQA_Importer
 */
class WPQA_Importer {

	/**
	 * Allowed statuses.
	 *
	 * @var array
	 */
	const STATUSES = array( 'pending', 'approved', 'rejected' );

	/**
	 * Import items from a decoded JSON array.
	 *
	 * @param array $items   List of items.
	 * @param array $options Import options.
	 * @return array Report.
	 */
	public function import( $items, $options = array() ) {
		$defaults = array(
			'default_status'        => 'approved',
			'generate_random_dates' => false,
			'date_range_days'       => 90,
			'force_post_id'         => 0,
		);

		$options = wp_parse_args( $options, $defaults );

		$force_post_id = absint( $options['force_post_id'] );
		if ( $force_post_id ) {
			$forced_post = get_post( $force_post_id );
			if ( ! $forced_post || 'trash' === $forced_post->post_status ) {
				return array(
					'total'    => 0,
					'imported' => 0,
					'failed'   => 1,
					'errors'   => array( __( 'شناسه نوشته نامعتبر است.', 'wp-qa-comments' ) ),
				);
			}
		}

		$status = sanitize_key( $options['default_status'] );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			$status = 'approved';
		}

		$report = array(
			'total'    => 0,
			'imported' => 0,
			'failed'   => 0,
			'errors'   => array(),
		);

		if ( ! is_array( $items ) ) {
			$report['errors'][] = __( 'JSON باید یک آرایه از اشیاء باشد.', 'wp-qa-comments' );
			$report['failed']   = 1;
			return $report;
		}

		$report['total'] = count( $items );
		$index           = 0;

		foreach ( $items as $item ) {
			++$index;

			if ( ! is_array( $item ) && ! is_object( $item ) ) {
				$report['failed']++;
				$report['errors'][] = sprintf(
					/* translators: %d: item index */
					__( '#%d - فرمت آیتم نامعتبر است', 'wp-qa-comments' ),
					$index
				);
				continue;
			}

			$item = (array) $item;

			if ( $force_post_id ) {
				$item['post_id'] = $force_post_id;
			}

			$error = $this->validate_item( $item, $index, $force_post_id > 0 );

			if ( $error ) {
				$report['failed']++;
				$report['errors'][] = $error;
				continue;
			}

			$created_at = current_time( 'mysql' );
			if ( ! empty( $options['generate_random_dates'] ) ) {
				$created_at = $this->random_datetime( (int) $options['date_range_days'], absint( $item['post_id'] ) );
			} elseif ( ! empty( $item['created_at'] ) ) {
				$normalized = $this->normalize_datetime( (string) $item['created_at'] );
				if ( $normalized ) {
					$created_at = $normalized;
				}
			}

			$answer     = isset( $item['answer'] ) ? trim( (string) $item['answer'] ) : '';
			$replied_at = null;

			if ( '' !== $answer ) {
				$replied_at = $created_at;
				if ( ! empty( $options['generate_random_dates'] ) ) {
					$created_gmt = get_gmt_from_date( $created_at );
					$created_ts  = strtotime( $created_gmt . ' UTC' );
					if ( false !== $created_ts ) {
						$reply_ts = $created_ts + wp_rand( HOUR_IN_SECONDS, 5 * DAY_IN_SECONDS );
						$now_ts   = time();
						if ( $reply_ts > $now_ts ) {
							$reply_ts = $now_ts;
						}
						if ( $reply_ts < $created_ts ) {
							$reply_ts = $created_ts;
						}
						$replied_at = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $reply_ts ), 'Y-m-d H:i:s' );
					}
				} elseif ( ! empty( $item['replied_at'] ) ) {
					$normalized = $this->normalize_datetime( (string) $item['replied_at'] );
					if ( $normalized ) {
						$replied_at = $normalized;
					}
				}
			}

			$item_status = $status;
			if ( ! empty( $item['status'] ) ) {
				$candidate = sanitize_key( $item['status'] );
				if ( in_array( $candidate, self::STATUSES, true ) ) {
					$item_status = $candidate;
				}
			}

			$id = WPQA_Database::insert(
				array(
					'post_id'    => absint( $item['post_id'] ),
					'name'       => sanitize_text_field( $item['name'] ),
					'question'   => wp_kses_post( $item['question'] ),
					'answer'     => '' !== $answer ? wp_kses_post( $answer ) : null,
					'status'     => $item_status,
					'created_at' => $created_at,
					'replied_at' => $replied_at,
				)
			);

			if ( ! $id ) {
				$report['failed']++;
				$report['errors'][] = sprintf(
					/* translators: %d: item index */
					__( '#%d - ذخیره در دیتابیس ناموفق بود', 'wp-qa-comments' ),
					$index
				);
				continue;
			}

			$report['imported']++;
		}

		return $report;
	}

	/**
	 * Parse JSON string and import.
	 *
	 * @param string $json    JSON string.
	 * @param array  $options Import options.
	 * @return array Report.
	 */
	public function import_json( $json, $options = array() ) {
		$json = trim( (string) $json );

		if ( '' === $json ) {
			return array(
				'total'    => 0,
				'imported' => 0,
				'failed'   => 0,
				'errors'   => array( __( 'JSON خالی است.', 'wp-qa-comments' ) ),
			);
		}

		$data = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return array(
				'total'    => 0,
				'imported' => 0,
				'failed'   => 0,
				'errors'   => array(
					sprintf(
						/* translators: %s: JSON error message */
						__( 'JSON نامعتبر: %s', 'wp-qa-comments' ),
						json_last_error_msg()
					),
				),
			);
		}

		return $this->import( $data, $options );
	}

	/**
	 * Validate a single import item.
	 *
	 * @param array $item              Item data.
	 * @param int   $index             1-based index.
	 * @param bool  $skip_post_check   Skip post existence check (already forced).
	 * @return string|null Error message or null.
	 */
	private function validate_item( $item, $index, $skip_post_check = false ) {
		if ( empty( $item['post_id'] ) || ! absint( $item['post_id'] ) ) {
			return sprintf(
				/* translators: %d: item index */
				__( '#%d - شناسه نوشته نامعتبر است', 'wp-qa-comments' ),
				$index
			);
		}

		if ( ! $skip_post_check ) {
			$post_id = absint( $item['post_id'] );
			$post    = get_post( $post_id );

			if ( ! $post || 'trash' === $post->post_status ) {
				return sprintf(
					/* translators: %d: item index */
					__( '#%d - شناسه نوشته نامعتبر است', 'wp-qa-comments' ),
					$index
				);
			}
		}

		if ( empty( $item['name'] ) || '' === trim( (string) $item['name'] ) ) {
			return sprintf(
				/* translators: %d: item index */
				__( '#%d - نام وارد نشده است', 'wp-qa-comments' ),
				$index
			);
		}

		if ( empty( $item['question'] ) || '' === trim( (string) $item['question'] ) ) {
			return sprintf(
				/* translators: %d: item index */
				__( '#%d - سؤال وارد نشده است', 'wp-qa-comments' ),
				$index
			);
		}

		return null;
	}

	/**
	 * Generate a random datetime within the past N days (site local time).
	 * Never earlier than the related post's publish date.
	 *
	 * @param int $days    Range in days.
	 * @param int $post_id Related post ID.
	 * @return string MySQL datetime.
	 */
	private function random_datetime( $days = 90, $post_id = 0 ) {
		$days = max( 1, absint( $days ) );
		$now  = time();
		$past = $now - ( $days * DAY_IN_SECONDS );
		$min  = $past;

		$post_id = absint( $post_id );
		if ( $post_id ) {
			$publish_ts = get_post_time( 'U', true, $post_id );
			if ( $publish_ts ) {
				$min = max( $min, (int) $publish_ts );
			}
		}

		if ( $min > $now ) {
			$min = $now;
		}

		$ts = ( $min === $now ) ? $now : wp_rand( $min, $now );

		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ), 'Y-m-d H:i:s' );
	}

	/**
	 * Normalize a datetime string to MySQL format.
	 *
	 * @param string $value Raw datetime.
	 * @return string|null
	 */
	private function normalize_datetime( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2})?$/', $value ) ) {
			if ( 10 === strlen( $value ) ) {
				return $value . ' 00:00:00';
			}
			return str_replace( 'T', ' ', $value );
		}

		$parsed = strtotime( $value );
		if ( false === $parsed ) {
			return null;
		}

		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $parsed ), 'Y-m-d H:i:s' );
	}
}
