<?php
/**
 * Admin menus and actions.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPQA_Admin
 */
class WPQA_Admin {

	/**
	 * Singleton.
	 *
	 * @var WPQA_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WPQA_Admin
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'demo_notice' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'wp_ajax_wpqa_import_for_post', array( $this, 'ajax_import_for_post' ) );
	}

	/**
	 * Register admin menus.
	 */
	public function register_menu() {
		$capability = 'manage_options';

		add_menu_page(
			__( 'پرسش و پاسخ', 'wp-qa-comments' ),
			__( 'پرسش و پاسخ', 'wp-qa-comments' ),
			$capability,
			'wpqa-comments',
			array( $this, 'render_comments_page' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			'wpqa-comments',
			__( 'همه کامنت‌ها', 'wp-qa-comments' ),
			__( 'همه کامنت‌ها', 'wp-qa-comments' ),
			$capability,
			'wpqa-comments',
			array( $this, 'render_comments_page' )
		);

		add_submenu_page(
			'wpqa-comments',
			__( 'درون‌ریزی JSON', 'wp-qa-comments' ),
			__( 'درون‌ریزی JSON', 'wp-qa-comments' ),
			$capability,
			'wpqa-import',
			array( $this, 'render_import_page' )
		);

		add_submenu_page(
			'wpqa-comments',
			__( 'تنظیمات', 'wp-qa-comments' ),
			__( 'تنظیمات', 'wp-qa-comments' ),
			$capability,
			'wpqa-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$is_plugin_page = ( false !== strpos( $hook, 'wpqa-' ) || false !== strpos( $hook, 'wpqa_comments' ) || 'toplevel_page_wpqa-comments' === $hook );
		$is_post_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_plugin_page && ! $is_post_screen ) {
			return;
		}

		if ( $is_post_screen ) {
			$screen = get_current_screen();
			if ( ! $screen || ! WPQA_Settings::is_enabled_for( $screen->post_type ) ) {
				return;
			}
		}

		wp_enqueue_style(
			'wpqa-admin',
			WPQA_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			WPQA_VERSION
		);

		if ( $is_post_screen ) {
			wp_enqueue_script(
				'wpqa-post-import',
				WPQA_PLUGIN_URL . 'admin/js/post-import.js',
				array(),
				WPQA_VERSION,
				true
			);

			wp_localize_script(
				'wpqa-post-import',
				'wpqaPostImport',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpqa_import_for_post' ),
					'i18n'    => array(
						'importing'       => __( 'در حال درون‌ریزی…', 'wp-qa-comments' ),
						'import'          => __( 'درون‌ریزی برای این نوشته', 'wp-qa-comments' ),
						'empty'           => __( 'لطفاً JSON را وارد کنید یا فایل آپلود کنید.', 'wp-qa-comments' ),
						'error'           => __( 'خطا در درون‌ریزی. دوباره تلاش کنید.', 'wp-qa-comments' ),
						'total'           => __( 'کل', 'wp-qa-comments' ),
						'imported'        => __( 'موفق', 'wp-qa-comments' ),
						'failed'          => __( 'ناموفق', 'wp-qa-comments' ),
						'errors'          => __( 'خطاها:', 'wp-qa-comments' ),
						'done'            => __( 'درون‌ریزی انجام شد', 'wp-qa-comments' ),
						'donePartial'     => __( 'درون‌ریزی با چند خطا انجام شد', 'wp-qa-comments' ),
						'doneEmpty'       => __( 'درون‌ریزی پایان یافت', 'wp-qa-comments' ),
						'countLabel'      => __( 'تعداد پرسش و پاسخ‌های این محتوا: %d', 'wp-qa-comments' ),
					),
				)
			);
		}
	}

	/**
	 * Register import meta box on enabled post types.
	 */
	public function register_meta_boxes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$types = WPQA_Settings::get( 'enabled_post_types', array() );
		if ( empty( $types ) || ! is_array( $types ) ) {
			return;
		}

		foreach ( $types as $post_type ) {
			add_meta_box(
				'wpqa-import-metabox',
				__( 'پرسش و پاسخ — درون‌ریزی JSON', 'wp-qa-comments' ),
				array( $this, 'render_post_import_metabox' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render post/page import meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_post_import_metabox( $post ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		include WPQA_PLUGIN_DIR . 'admin/views/post-import-metabox.php';
	}

	/**
	 * AJAX: import JSON for the current post from the edit screen.
	 */
	public function ajax_import_for_post() {
		check_ajax_referer( 'wpqa_import_for_post', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی مجاز نیست.', 'wp-qa-comments' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'نوشته نامعتبر است.', 'wp-qa-comments' ) ), 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی مجاز نیست.', 'wp-qa-comments' ) ), 403 );
		}

		$post_type = get_post_type( $post_id );
		if ( ! WPQA_Settings::is_enabled_for( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'پرسش و پاسخ برای این نوع محتوا فعال نیست.', 'wp-qa-comments' ) ), 403 );
		}

		$json = isset( $_POST['wpqa_json'] ) ? wp_unslash( $_POST['wpqa_json'] ) : '';

		$options = array(
			'default_status'        => isset( $_POST['default_status'] ) ? sanitize_key( wp_unslash( $_POST['default_status'] ) ) : 'approved',
			'generate_random_dates' => ! empty( $_POST['generate_random_dates'] ),
			'date_range_days'       => isset( $_POST['date_range_days'] ) ? absint( $_POST['date_range_days'] ) : 90,
			'force_post_id'         => $post_id,
		);

		$importer = new WPQA_Importer();
		$report   = $importer->import_json( $json, $options );

		$count = WPQA_Database::query(
			array(
				'post_id'  => $post_id,
				'per_page' => 1,
				'page'     => 1,
			)
		);

		wp_send_json_success(
			array(
				'report'      => $report,
				'total_count' => (int) $count['total'],
			)
		);
	}

	/**
	 * Demo/seed content notice on plugin pages.
	 */
	public function demo_notice() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'wpqa' ) ) {
			return;
		}
		?>
		<div class="notice notice-info wpqa-demo-notice">
			<p>
				<?php
				esc_html_e(
					'پرسش و پاسخ: آیتم‌های درون‌ریزی‌شده و داده‌های تست برای محتوای دمو هستند و نباید به‌عنوان نظر واقعی کاربران معرفی شوند.',
					'wp-qa-comments'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle admin form/actions.
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Settings save.
		if ( isset( $_POST['wpqa_save_settings'] ) ) {
			check_admin_referer( 'wpqa_save_settings' );
			WPQA_Settings::update( wp_unslash( $_POST ) );
			$this->redirect_with_notice( 'settings', 'settings_saved' );
		}

		// Import JSON.
		if ( isset( $_POST['wpqa_import_json'] ) ) {
			check_admin_referer( 'wpqa_import_json' );
			$this->handle_import();
		}

		// Single comment actions via GET with nonce.
		if ( isset( $_GET['wpqa_action'], $_GET['comment_id'], $_GET['_wpnonce'] ) ) {
			$action = sanitize_key( wp_unslash( $_GET['wpqa_action'] ) );
			$id     = absint( $_GET['comment_id'] );

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpqa_comment_' . $id ) ) {
				wp_die( esc_html__( 'بررسی امنیتی ناموفق بود.', 'wp-qa-comments' ) );
			}

			$this->handle_comment_action( $action, $id );
		}

		// Save edit / reply.
		if ( isset( $_POST['wpqa_save_comment'] ) ) {
			check_admin_referer( 'wpqa_save_comment' );
			$this->handle_save_comment();
		}

		// Bulk actions.
		if ( isset( $_POST['wpqa_bulk_action'], $_POST['comment_ids'] ) && is_array( $_POST['comment_ids'] ) ) {
			check_admin_referer( 'wpqa_bulk_action' );
			$this->handle_bulk_action();
		}
	}

	/**
	 * Handle JSON import from textarea or uploaded file.
	 */
	private function handle_import() {
		$json = '';

		if ( ! empty( $_FILES['wpqa_json_file']['tmp_name'] ) && is_uploaded_file( $_FILES['wpqa_json_file']['tmp_name'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$json = file_get_contents( $_FILES['wpqa_json_file']['tmp_name'] );
		} elseif ( isset( $_POST['wpqa_json'] ) ) {
			$json = wp_unslash( $_POST['wpqa_json'] );
		}

		$options = array(
			'default_status'        => isset( $_POST['default_status'] ) ? sanitize_key( wp_unslash( $_POST['default_status'] ) ) : 'approved',
			'generate_random_dates' => ! empty( $_POST['generate_random_dates'] ),
			'date_range_days'       => isset( $_POST['date_range_days'] ) ? absint( $_POST['date_range_days'] ) : 90,
		);

		$importer = new WPQA_Importer();
		$report   = $importer->import_json( $json, $options );

		set_transient(
			'wpqa_import_report_' . get_current_user_id(),
			$report,
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'wpqa-import',
					'wpqa_notice'   => 'import_done',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle single comment action.
	 *
	 * @param string $action Action name.
	 * @param int    $id     Comment ID.
	 */
	private function handle_comment_action( $action, $id ) {
		$comment = WPQA_Database::get( $id );
		if ( ! $comment ) {
			$this->redirect_with_notice( 'comments', 'not_found' );
		}

		switch ( $action ) {
			case 'approve':
				WPQA_Database::update( $id, array( 'status' => 'approved' ) );
				$this->redirect_with_notice( 'comments', 'approved' );
				break;
			case 'reject':
				WPQA_Database::update( $id, array( 'status' => 'rejected' ) );
				$this->redirect_with_notice( 'comments', 'rejected' );
				break;
			case 'pending':
				WPQA_Database::update( $id, array( 'status' => 'pending' ) );
				$this->redirect_with_notice( 'comments', 'pending' );
				break;
			case 'delete':
				WPQA_Database::delete( $id );
				$this->redirect_with_notice( 'comments', 'deleted' );
				break;
			default:
				$this->redirect_with_notice( 'comments', 'invalid' );
		}
	}

	/**
	 * Save edited comment / admin reply.
	 */
	private function handle_save_comment() {
		$id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
		if ( ! $id || ! WPQA_Database::get( $id ) ) {
			$this->redirect_with_notice( 'comments', 'not_found' );
		}

		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$question = isset( $_POST['question'] ) ? wp_kses_post( wp_unslash( $_POST['question'] ) ) : '';
		$answer   = isset( $_POST['answer'] ) ? wp_kses_post( wp_unslash( $_POST['answer'] ) ) : '';
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'pending';
		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
			$status = 'pending';
		}

		$data = array(
			'name'     => $name,
			'question' => $question,
			'status'   => $status,
		);

		if ( $post_id && get_post( $post_id ) ) {
			$data['post_id'] = $post_id;
		}

		$existing = WPQA_Database::get( $id );
		$old_answer = isset( $existing->answer ) ? (string) $existing->answer : '';

		if ( '' === trim( wp_strip_all_tags( $answer ) ) ) {
			$data['answer']     = null;
			$data['replied_at'] = null;
		} else {
			$data['answer'] = $answer;
			if ( $old_answer !== $answer || empty( $existing->replied_at ) ) {
				$data['replied_at'] = current_time( 'mysql' );
			}
		}

		WPQA_Database::update( $id, $data );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'wpqa-comments',
					'action'      => 'edit',
					'comment_id'  => $id,
					'wpqa_notice' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle bulk actions.
	 */
	private function handle_bulk_action() {
		$bulk   = sanitize_key( wp_unslash( $_POST['wpqa_bulk_action'] ) );
		$ids    = array_map( 'absint', wp_unslash( $_POST['comment_ids'] ) );
		$ids    = array_filter( $ids );

		foreach ( $ids as $id ) {
			switch ( $bulk ) {
				case 'approve':
					WPQA_Database::update( $id, array( 'status' => 'approved' ) );
					break;
				case 'reject':
					WPQA_Database::update( $id, array( 'status' => 'rejected' ) );
					break;
				case 'pending':
					WPQA_Database::update( $id, array( 'status' => 'pending' ) );
					break;
				case 'delete':
					WPQA_Database::delete( $id );
					break;
			}
		}

		$this->redirect_with_notice( 'comments', 'bulk_done' );
	}

	/**
	 * Redirect with a notice query arg.
	 *
	 * @param string $page   Page slug key.
	 * @param string $notice Notice code.
	 */
	private function redirect_with_notice( $page, $notice ) {
		$pages = array(
			'comments' => 'wpqa-comments',
			'settings' => 'wpqa-settings',
			'import'   => 'wpqa-import',
		);

		$slug = isset( $pages[ $page ] ) ? $pages[ $page ] : 'wpqa-comments';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => $slug,
					'wpqa_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render comments list / edit page.
	 */
	public function render_comments_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
		$id     = isset( $_GET['comment_id'] ) ? absint( $_GET['comment_id'] ) : 0;

		if ( in_array( $action, array( 'edit', 'reply' ), true ) && $id ) {
			$comment = WPQA_Database::get( $id );
			include WPQA_PLUGIN_DIR . 'admin/views/edit-comment.php';
			return;
		}

		include WPQA_PLUGIN_DIR . 'admin/views/comments.php';
	}

	/**
	 * Render import page.
	 */
	public function render_import_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include WPQA_PLUGIN_DIR . 'admin/views/import.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include WPQA_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Print admin notice from query arg.
	 */
	public static function print_notice() {
		if ( empty( $_GET['wpqa_notice'] ) ) {
			return;
		}

		$code = sanitize_key( wp_unslash( $_GET['wpqa_notice'] ) );
		$map  = array(
			'settings_saved' => array( 'success', __( 'تنظیمات ذخیره شد.', 'wp-qa-comments' ) ),
			'approved'       => array( 'success', __( 'کامنت تأیید شد.', 'wp-qa-comments' ) ),
			'rejected'       => array( 'success', __( 'کامنت رد شد.', 'wp-qa-comments' ) ),
			'pending'        => array( 'success', __( 'کامنت به حالت در انتظار منتقل شد.', 'wp-qa-comments' ) ),
			'deleted'        => array( 'success', __( 'کامنت حذف شد.', 'wp-qa-comments' ) ),
			'saved'          => array( 'success', __( 'کامنت ذخیره شد.', 'wp-qa-comments' ) ),
			'bulk_done'      => array( 'success', __( 'عملیات گروهی انجام شد.', 'wp-qa-comments' ) ),
			'not_found'      => array( 'error', __( 'کامنت یافت نشد.', 'wp-qa-comments' ) ),
			'invalid'        => array( 'error', __( 'عملیات نامعتبر است.', 'wp-qa-comments' ) ),
			'import_done'    => array( 'success', __( 'درون‌ریزی انجام شد.', 'wp-qa-comments' ) ),
		);

		if ( ! isset( $map[ $code ] ) ) {
			return;
		}

		list( $type, $message ) = $map[ $code ];
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Status label helper.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'pending'  => __( 'در انتظار', 'wp-qa-comments' ),
			'approved' => __( 'تأیید شده', 'wp-qa-comments' ),
			'rejected' => __( 'رد شده', 'wp-qa-comments' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Build action URL for a comment.
	 *
	 * @param string $action Action.
	 * @param int    $id     Comment ID.
	 * @return string
	 */
	public static function action_url( $action, $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'        => 'wpqa-comments',
					'wpqa_action' => $action,
					'comment_id'  => $id,
				),
				admin_url( 'admin.php' )
			),
			'wpqa_comment_' . $id
		);
	}
}
