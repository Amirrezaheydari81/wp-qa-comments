<?php
/**
 * Frontend comments rendering and shortcode.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPQA_Comments
 */
class WPQA_Comments {

	/**
	 * Singleton.
	 *
	 * @var WPQA_Comments|null
	 */
	private static $instance = null;

	/**
	 * Whether assets were enqueued.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Get instance.
	 *
	 * @return WPQA_Comments
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
		add_shortcode( 'wpqa_comments', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'append_to_content' ), 25 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register frontend assets (load on demand).
	 */
	public function register_assets() {
		wp_register_style(
			'wpqa-frontend',
			WPQA_PLUGIN_URL . 'public/css/frontend.css',
			array(),
			WPQA_VERSION
		);

		wp_register_script(
			'wpqa-frontend',
			WPQA_PLUGIN_URL . 'public/js/frontend.js',
			array(),
			WPQA_VERSION,
			true
		);

		wp_register_script(
			'wpqa-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			array(),
			WPQA_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend assets once.
	 */
	public function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}

		wp_enqueue_style( 'wpqa-frontend' );
		wp_enqueue_script( 'wpqa-frontend' );

		$captcha_type = WPQA_Captcha::is_enabled() ? WPQA_Captcha::get_type() : 'off';

		if ( 'turnstile' === $captcha_type ) {
			wp_enqueue_script( 'wpqa-turnstile' );
		}

		wp_localize_script(
			'wpqa-frontend',
			'wpqaData',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'wpqa_frontend' ),
				'captchaType' => $captcha_type,
				'i18n'        => array(
					'sending'          => __( 'در حال ارسال…', 'wp-qa-comments' ),
					'submit'           => __( 'ارسال سؤال', 'wp-qa-comments' ),
					'error'            => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'wp-qa-comments' ),
					'loadMore'         => __( 'مشاهده سوالات بیشتر', 'wp-qa-comments' ),
					'loading'          => __( 'در حال بارگذاری…', 'wp-qa-comments' ),
					'nameRequired'     => __( 'لطفاً نام خود را وارد کنید.', 'wp-qa-comments' ),
					'questionRequired' => __( 'لطفاً سؤال یا نظر خود را بنویسید.', 'wp-qa-comments' ),
					'captchaRequired'  => __( 'لطفاً کد تصویر کپچا را وارد کنید.', 'wp-qa-comments' ),
				),
			)
		);

		$this->assets_enqueued = true;
	}

	/**
	 * Auto-append Q&A box to post/page content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append_to_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( ! WPQA_Settings::get( 'auto_append', 1 ) ) {
			return $content;
		}

		$post_id   = get_the_ID();
		$post_type = get_post_type( $post_id );

		if ( ! WPQA_Settings::is_enabled_for( $post_type ) ) {
			return $content;
		}

		$content .= $this->render( $post_id );
		return $content;
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
			),
			$atts,
			'wpqa_comments'
		);

		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return '';
		}

		return $this->render( $post_id );
	}

	/**
	 * Render the full Q&A block for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function render( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! get_post( $post_id ) ) {
			return '';
		}

		$per_page = (int) WPQA_Settings::get( 'per_page', 10 );
		$result   = WPQA_Database::query(
			array(
				'post_id'  => $post_id,
				'status'   => 'approved',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => $per_page,
				'page'     => 1,
			)
		);

		// Do not render the Q&A box when there are no approved items.
		if ( (int) $result['total'] < 1 ) {
			return '';
		}

		$this->enqueue_assets();

		$show_form = (bool) WPQA_Settings::get( 'show_form', 1 );
		$has_more  = $result['total'] > $per_page;

		ob_start();
		?>
		<div class="wpqa-wrap" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-page="1" data-per-page="<?php echo esc_attr( $per_page ); ?>" data-total="<?php echo esc_attr( $result['total'] ); ?>" dir="rtl">
			<div class="wpqa-header">
				<h3 class="wpqa-title"><?php esc_html_e( 'سؤالات و پاسخ‌ها', 'wp-qa-comments' ); ?></h3>
				<p class="wpqa-count">
					<?php
					printf(
						/* translators: %d: number of questions */
						esc_html( _n( '%d سؤال', '%d سؤال', $result['total'], 'wp-qa-comments' ) ),
						(int) $result['total']
					);
					?>
				</p>
			</div>

			<div class="wpqa-list" id="wpqa-list-<?php echo esc_attr( $post_id ); ?>">
				<?php
				foreach ( $result['items'] as $item ) {
					echo $this->render_item( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>

			<?php if ( $has_more ) : ?>
				<div class="wpqa-load-more-wrap">
					<button type="button" class="wpqa-load-more" data-post-id="<?php echo esc_attr( $post_id ); ?>">
						<?php esc_html_e( 'مشاهده سوالات بیشتر', 'wp-qa-comments' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( $show_form ) : ?>
				<div class="wpqa-form-wrap">
					<h4 class="wpqa-form-title"><?php esc_html_e( 'سؤال خود را بپرسید', 'wp-qa-comments' ); ?></h4>
					<form class="wpqa-form" method="post" novalidate>
						<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>" />
						<div class="wpqa-field">
							<label for="wpqa-name-<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'نام شما', 'wp-qa-comments' ); ?></label>
							<input type="text" id="wpqa-name-<?php echo esc_attr( $post_id ); ?>" name="name" maxlength="100" required autocomplete="name" />
						</div>
						<div class="wpqa-field">
							<label for="wpqa-question-<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'سؤال یا نظر شما', 'wp-qa-comments' ); ?></label>
							<textarea id="wpqa-question-<?php echo esc_attr( $post_id ); ?>" name="question" rows="4" required maxlength="5000"></textarea>
						</div>
						<?php echo WPQA_Captcha::render_fields( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div class="wpqa-form-actions">
							<button type="submit" class="wpqa-submit"><?php esc_html_e( 'ارسال سؤال', 'wp-qa-comments' ); ?></button>
						</div>
						<div class="wpqa-message" role="status" aria-live="polite" hidden></div>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single Q&A item.
	 *
	 * @param object $item Comment row.
	 * @return string
	 */
	public function render_item( $item ) {
		ob_start();
		?>
		<article class="wpqa-item" data-id="<?php echo esc_attr( $item->id ); ?>">
			<header class="wpqa-item-header">
				<span class="wpqa-item-name"><?php echo esc_html( $item->name ); ?></span>
				<?php if ( ! empty( $item->created_at ) && '0000-00-00 00:00:00' !== $item->created_at ) : ?>
					<time class="wpqa-item-date" datetime="<?php echo esc_attr( $item->created_at ); ?>">
						<?php echo esc_html( WPQA_Jalali::format( $item->created_at ) ); ?>
					</time>
				<?php endif; ?>
			</header>
			<div class="wpqa-item-question">
				<?php echo wp_kses_post( wpautop( $item->question ) ); ?>
			</div>
			<?php if ( ! empty( $item->answer ) ) : ?>
				<div class="wpqa-item-answer">
					<div class="wpqa-answer-label"><?php esc_html_e( 'پاسخ مدیر سایت', 'wp-qa-comments' ); ?></div>
					<div class="wpqa-answer-body">
						<?php echo wp_kses_post( wpautop( $item->answer ) ); ?>
					</div>
				</div>
			<?php endif; ?>
		</article>
		<?php
		return ob_get_clean();
	}
}
