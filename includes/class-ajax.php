<?php
/**
 * AJAX handlers.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPQA_Ajax
 */
class WPQA_Ajax {

	/**
	 * Singleton.
	 *
	 * @var WPQA_Ajax|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WPQA_Ajax
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_wpqa_submit_question', array( $this, 'submit_question' ) );
		add_action( 'wp_ajax_nopriv_wpqa_submit_question', array( $this, 'submit_question' ) );
		add_action( 'wp_ajax_wpqa_load_more', array( $this, 'load_more' ) );
		add_action( 'wp_ajax_nopriv_wpqa_load_more', array( $this, 'load_more' ) );
		add_action( 'wp_ajax_wpqa_refresh_captcha', array( $this, 'refresh_captcha' ) );
		add_action( 'wp_ajax_nopriv_wpqa_refresh_captcha', array( $this, 'refresh_captcha' ) );
		add_action( 'wp_ajax_wpqa_captcha_image', array( $this, 'captcha_image' ) );
		add_action( 'wp_ajax_nopriv_wpqa_captcha_image', array( $this, 'captcha_image' ) );
	}

	/**
	 * Submit a new question from the frontend form.
	 */
	public function submit_question() {
		check_ajax_referer( 'wpqa_frontend', 'nonce' );

		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$question = isset( $_POST['question'] ) ? wp_kses_post( wp_unslash( $_POST['question'] ) ) : '';

		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'نوشته نامعتبر است.', 'wp-qa-comments' ) ),
				400
			);
		}

		$post_type = get_post_type( $post_id );
		if ( ! WPQA_Settings::is_enabled_for( $post_type ) ) {
			wp_send_json_error(
				array( 'message' => __( 'سیستم پرسش و پاسخ برای این محتوا فعال نیست.', 'wp-qa-comments' ) ),
				403
			);
		}

		if ( ! WPQA_Settings::get( 'show_form', 1 ) ) {
			wp_send_json_error(
				array( 'message' => __( 'ارسال سؤال در حال حاضر غیرفعال است.', 'wp-qa-comments' ) ),
				403
			);
		}

		$captcha = WPQA_Captcha::verify( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( is_wp_error( $captcha ) ) {
			$challenge = null;
			$type      = WPQA_Captcha::get_type();
			if ( WPQA_Captcha::is_enabled() && in_array( $type, array( 'image', 'math' ), true ) ) {
				$challenge = WPQA_Captcha::create_challenge();
			}
			wp_send_json_error(
				array(
					'message' => $captcha->get_error_message(),
					'captcha' => $challenge,
				),
				400
			);
		}

		if ( '' === $name ) {
			wp_send_json_error(
				array( 'message' => __( 'لطفاً نام خود را وارد کنید.', 'wp-qa-comments' ) ),
				400
			);
		}

		if ( '' === trim( wp_strip_all_tags( $question ) ) ) {
			wp_send_json_error(
				array( 'message' => __( 'لطفاً سؤال یا نظر خود را بنویسید.', 'wp-qa-comments' ) ),
				400
			);
		}

		if ( mb_strlen( $name ) > 100 ) {
			wp_send_json_error(
				array( 'message' => __( 'نام نباید بیشتر از ۱۰۰ کاراکتر باشد.', 'wp-qa-comments' ) ),
				400
			);
		}

		$require_approval = (bool) WPQA_Settings::get( 'require_approval', 1 );
		$status           = $require_approval ? 'pending' : 'approved';

		$id = WPQA_Database::insert(
			array(
				'post_id'    => $post_id,
				'name'       => $name,
				'question'   => $question,
				'answer'     => null,
				'status'     => $status,
				'created_at' => current_time( 'mysql' ),
				'replied_at' => null,
			)
		);

		if ( ! $id ) {
			wp_send_json_error(
				array( 'message' => __( 'ثبت سؤال با خطا مواجه شد.', 'wp-qa-comments' ) ),
				500
			);
		}

		if ( $require_approval ) {
			$message = __( 'سؤال شما ثبت شد و پس از بررسی منتشر خواهد شد.', 'wp-qa-comments' );
		} else {
			$message = __( 'سؤال شما با موفقیت ثبت شد.', 'wp-qa-comments' );
		}

		$response = array(
			'message'  => $message,
			'approved' => ! $require_approval,
			'id'       => $id,
		);

		if ( ! $require_approval ) {
			$item = WPQA_Database::get( $id );
			if ( $item ) {
				$response['html'] = WPQA_Comments::instance()->render_item( $item );
			}
		}

		if ( WPQA_Captcha::is_enabled() && in_array( WPQA_Captcha::get_type(), array( 'image', 'math' ), true ) ) {
			$response['captcha'] = WPQA_Captcha::create_challenge();
		}

		wp_send_json_success( $response );
	}

	/**
	 * Refresh captcha challenge.
	 */
	public function refresh_captcha() {
		check_ajax_referer( 'wpqa_frontend', 'nonce' );

		if ( ! WPQA_Captcha::is_enabled() || ! in_array( WPQA_Captcha::get_type(), array( 'image', 'math' ), true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'کپچا در دسترس نیست.', 'wp-qa-comments' ) ),
				400
			);
		}

		wp_send_json_success(
			array(
				'captcha' => WPQA_Captcha::create_challenge(),
			)
		);
	}

	/**
	 * Stream captcha PNG image.
	 */
	public function captcha_image() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		WPQA_Captcha::output_image( $token );
	}

	/**
	 * Load more approved comments.
	 */
	public function load_more() {
		check_ajax_referer( 'wpqa_frontend', 'nonce' );

		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = (int) WPQA_Settings::get( 'per_page', 10 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'نوشته نامعتبر است.', 'wp-qa-comments' ) ),
				400
			);
		}

		$page = max( 1, $page );

		$result = WPQA_Database::query(
			array(
				'post_id'  => $post_id,
				'status'   => 'approved',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => $per_page,
				'page'     => $page,
			)
		);

		$html = '';
		foreach ( $result['items'] as $item ) {
			$html .= WPQA_Comments::instance()->render_item( $item );
		}

		$loaded   = $page * $per_page;
		$has_more = $loaded < $result['total'];

		wp_send_json_success(
			array(
				'html'     => $html,
				'page'     => $page,
				'has_more' => $has_more,
				'total'    => $result['total'],
			)
		);
	}
}
