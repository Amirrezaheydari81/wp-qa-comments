<?php
/**
 * Database schema and helpers.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPQA_Database
 */
class WPQA_Database {

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	const TABLE_SLUG = 'wpqa_comments';

	/**
	 * Plugin activation: create/update table and defaults.
	 */
	public static function activate() {
		self::create_table();
		WPQA_Settings::ensure_defaults();
		update_option( 'wpqa_db_version', WPQA_VERSION );
	}

	/**
	 * Get full table name with prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SLUG;
	}

	/**
	 * Create or update the comments table via dbDelta.
	 */
	public static function create_table() {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL DEFAULT '',
			question longtext NOT NULL,
			answer longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			replied_at datetime NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY created_at (created_at),
			KEY post_status (post_id, status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a comment row.
	 *
	 * @param array $data Comment data.
	 * @return int|false Insert ID or false.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$defaults = array(
			'post_id'    => 0,
			'name'       => '',
			'question'   => '',
			'answer'     => null,
			'status'     => 'pending',
			'created_at' => current_time( 'mysql' ),
			'replied_at' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			self::table_name(),
			array(
				'post_id'    => absint( $data['post_id'] ),
				'name'       => sanitize_text_field( $data['name'] ),
				'question'   => wp_kses_post( $data['question'] ),
				'answer'     => ( null !== $data['answer'] && '' !== $data['answer'] ) ? wp_kses_post( $data['answer'] ) : null,
				'status'     => sanitize_key( $data['status'] ),
				'created_at' => sanitize_text_field( $data['created_at'] ),
				'replied_at' => ( null !== $data['replied_at'] && '' !== $data['replied_at'] ) ? sanitize_text_field( $data['replied_at'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a comment by ID.
	 *
	 * @param int   $id   Comment ID.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$id     = absint( $id );
		$fields = array();
		$format = array();

		if ( isset( $data['post_id'] ) ) {
			$fields['post_id'] = absint( $data['post_id'] );
			$format[]          = '%d';
		}
		if ( isset( $data['name'] ) ) {
			$fields['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s';
		}
		if ( isset( $data['question'] ) ) {
			$fields['question'] = wp_kses_post( $data['question'] );
			$format[]           = '%s';
		}
		if ( array_key_exists( 'answer', $data ) ) {
			$fields['answer'] = ( null !== $data['answer'] && '' !== $data['answer'] ) ? wp_kses_post( $data['answer'] ) : null;
			$format[]         = '%s';
		}
		if ( isset( $data['status'] ) ) {
			$fields['status'] = sanitize_key( $data['status'] );
			$format[]         = '%s';
		}
		if ( isset( $data['created_at'] ) ) {
			$fields['created_at'] = sanitize_text_field( $data['created_at'] );
			$format[]             = '%s';
		}
		if ( array_key_exists( 'replied_at', $data ) ) {
			$fields['replied_at'] = ( null !== $data['replied_at'] && '' !== $data['replied_at'] ) ? sanitize_text_field( $data['replied_at'] ) : null;
			$format[]             = '%s';
		}

		if ( empty( $fields ) ) {
			return false;
		}

		$result = $wpdb->update(
			self::table_name(),
			$fields,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a comment by ID.
	 *
	 * @param int $id Comment ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			self::table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get a single comment by ID.
	 *
	 * @param int $id Comment ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) )
		);
	}

	/**
	 * Query comments with filters and pagination.
	 *
	 * @param array $args Query args.
	 * @return array{items: array, total: int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'post_id'  => 0,
			'status'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => 1,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = self::table_name();

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['post_id'] ) ) {
			$where[]  = 'post_id = %d';
			$values[] = absint( $args['post_id'] );
		}

		if ( '' !== $args['status'] && null !== $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(name LIKE %s OR question LIKE %s OR answer LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$allowed_orderby = array( 'id', 'post_id', 'name', 'status', 'created_at', 'replied_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$per_page = max( 1, absint( $args['per_page'] ) );
		$page     = max( 1, absint( $args['page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );
			$items = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $values, array( $per_page, $offset ) ) ) );
		} else {
			$total = (int) $wpdb->get_var( $count_sql );
			$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $per_page, $offset ) );
		}

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Count comments by status.
	 *
	 * @param string $status Optional status filter.
	 * @return int
	 */
	public static function count_by_status( $status = '' ) {
		global $wpdb;

		$table = self::table_name();

		if ( '' === $status ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", sanitize_key( $status ) )
		);
	}

	/**
	 * Drop the plugin table.
	 */
	public static function drop_table() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
