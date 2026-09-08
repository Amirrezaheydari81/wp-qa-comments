<?php
/**
 * Frontend captcha helpers (noisy image + honeypot + optional Turnstile).
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPQA_Captcha
 */
class WPQA_Captcha {

	/**
	 * Transient prefix for challenges.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'wpqa_captcha_';

	/**
	 * Whether captcha checks are enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) WPQA_Settings::get( 'captcha_enabled', 1 );
	}

	/**
	 * Whether GD image functions are available.
	 *
	 * @return bool
	 */
	public static function supports_image() {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagejpeg' );
	}

	/**
	 * Active captcha type: image|math|turnstile.
	 *
	 * @return string
	 */
	public static function get_type() {
		$type = sanitize_key( (string) WPQA_Settings::get( 'captcha_type', 'image' ) );

		if ( ! in_array( $type, array( 'image', 'math', 'turnstile' ), true ) ) {
			$type = 'image';
		}

		if ( 'turnstile' === $type ) {
			$site   = trim( (string) WPQA_Settings::get( 'turnstile_site_key', '' ) );
			$secret = trim( (string) WPQA_Settings::get( 'turnstile_secret_key', '' ) );
			if ( '' === $site || '' === $secret ) {
				$type = 'image';
			}
		}

		if ( 'image' === $type && ! self::supports_image() ) {
			return 'math';
		}

		return $type;
	}

	/**
	 * Create a noisy image captcha challenge.
	 *
	 * @return array{token:string,image_url:string,type:string}
	 */
	public static function create_image_challenge() {
		$code  = self::random_code( 4 );
		$token = wp_generate_password( 24, false, false );

		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array(
				'answer'  => $code,
				'type'    => 'image',
				'created' => time(),
			),
			20 * MINUTE_IN_SECONDS
		);

		return array(
			'token'     => $token,
			'image_url' => self::image_url( $token ),
			'type'      => 'image',
		);
	}

	/**
	 * Create a math challenge (fallback when GD is unavailable).
	 *
	 * @return array{token:string,question:string,type:string}
	 */
	public static function create_math_challenge() {
		$a     = wp_rand( 1, 9 );
		$b     = wp_rand( 1, 9 );
		$token = wp_generate_password( 20, false, false );

		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array(
				'answer'  => (string) ( $a + $b ),
				'type'    => 'math',
				'created' => time(),
			),
			30 * MINUTE_IN_SECONDS
		);

		$question = sprintf(
			/* translators: 1: first number, 2: second number */
			__( 'حاصل جمع %1$s و %2$s چند می‌شود؟', 'wp-qa-comments' ),
			WPQA_Jalali::to_persian_digits( (string) $a ),
			WPQA_Jalali::to_persian_digits( (string) $b )
		);

		return array(
			'token'    => $token,
			'question' => $question,
			'type'     => 'math',
		);
	}

	/**
	 * Create challenge for the active captcha type.
	 *
	 * @return array
	 */
	public static function create_challenge() {
		$type = self::get_type();
		if ( 'image' === $type ) {
			return self::create_image_challenge();
		}
		if ( 'math' === $type ) {
			return self::create_math_challenge();
		}
		return array( 'type' => 'turnstile' );
	}

	/**
	 * Build captcha image URL.
	 *
	 * @param string $token Challenge token.
	 * @return string
	 */
	public static function image_url( $token ) {
		return add_query_arg(
			array(
				'action' => 'wpqa_captcha_image',
				'token'  => rawurlencode( $token ),
				'r'      => wp_rand( 1000, 999999 ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Generate a random captcha code (ambiguous chars excluded).
	 *
	 * @param int $length Code length.
	 * @return string
	 */
	private static function random_code( $length = 4 ) {
		$chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$max    = strlen( $chars ) - 1;
		$code   = '';
		$length = max( 4, min( 6, absint( $length ) ) );

		for ( $i = 0; $i < $length; $i++ ) {
			$code .= $chars[ wp_rand( 0, $max ) ];
		}

		return $code;
	}

	/**
	 * Verify submitted captcha + honeypot.
	 *
	 * @param array $request Request data (usually $_POST).
	 * @return true|WP_Error
	 */
	public static function verify( $request ) {
		$honey = isset( $request['wpqa_website'] ) ? trim( (string) wp_unslash( $request['wpqa_website'] ) ) : '';
		if ( '' !== $honey ) {
			return new WP_Error( 'wpqa_spam', __( 'ارسال نامعتبر تشخیص داده شد.', 'wp-qa-comments' ) );
		}

		if ( ! self::is_enabled() ) {
			return true;
		}

		$type = self::get_type();

		if ( 'turnstile' === $type ) {
			return self::verify_turnstile( $request );
		}

		return self::verify_code( $request );
	}

	/**
	 * Verify image/math code answer.
	 *
	 * @param array $request Request data.
	 * @return true|WP_Error
	 */
	private static function verify_code( $request ) {
		$token  = isset( $request['wpqa_captcha_token'] ) ? sanitize_text_field( wp_unslash( $request['wpqa_captcha_token'] ) ) : '';
		$answer = isset( $request['wpqa_captcha_answer'] ) ? sanitize_text_field( wp_unslash( $request['wpqa_captcha_answer'] ) ) : '';

		$answer = strtr(
			$answer,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
		$answer = preg_replace( '/\s+/', '', $answer );
		$answer = strtoupper( $answer );

		if ( '' === $token || '' === $answer ) {
			return new WP_Error( 'wpqa_captcha', __( 'لطفاً کد تصویر کپچا را وارد کنید.', 'wp-qa-comments' ) );
		}

		$key  = self::TRANSIENT_PREFIX . $token;
		$data = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $data ) || empty( $data['answer'] ) ) {
			return new WP_Error( 'wpqa_captcha', __( 'کپچا منقضی شده است. تصویر جدید بگیرید.', 'wp-qa-comments' ) );
		}

		$expected = strtoupper( (string) $data['answer'] );
		if ( ! hash_equals( $expected, $answer ) ) {
			return new WP_Error( 'wpqa_captcha', __( 'کد کپچا نادرست است.', 'wp-qa-comments' ) );
		}

		return true;
	}

	/**
	 * Verify Cloudflare Turnstile token.
	 *
	 * @param array $request Request data.
	 * @return true|WP_Error
	 */
	private static function verify_turnstile( $request ) {
		$token = isset( $request['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $request['cf-turnstile-response'] ) ) : '';
		if ( '' === $token && isset( $request['wpqa_turnstile_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $request['wpqa_turnstile_token'] ) );
		}

		if ( '' === $token ) {
			return new WP_Error( 'wpqa_captcha', __( 'لطفاً کپچا را تکمیل کنید.', 'wp-qa-comments' ) );
		}

		$secret = trim( (string) WPQA_Settings::get( 'turnstile_secret_key', '' ) );
		if ( '' === $secret ) {
			return new WP_Error( 'wpqa_captcha', __( 'تنظیمات کپچا ناقص است.', 'wp-qa-comments' ) );
		}

		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wpqa_captcha', __( 'تأیید کپچا با خطا مواجه شد. دوباره تلاش کنید.', 'wp-qa-comments' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'wpqa_captcha', __( 'تأیید کپچا ناموفق بود.', 'wp-qa-comments' ) );
		}

		return true;
	}

	/**
	 * Output lightweight noisy captcha image for a token.
	 *
	 * @param string $token Challenge token.
	 */
	public static function output_image( $token ) {
		$token = sanitize_text_field( $token );
		$data  = get_transient( self::TRANSIENT_PREFIX . $token );

		if ( ! is_array( $data ) || empty( $data['answer'] ) || ! self::supports_image() ) {
			status_header( 404 );
			exit;
		}

		$code   = (string) $data['answer'];
		$width  = 150;
		$height = 48;
		$image  = imagecreatetruecolor( $width, $height );

		if ( false === $image ) {
			status_header( 500 );
			exit;
		}

		// Fixed small palette (avoid allocating hundreds of colors).
		$bg    = imagecolorallocate( $image, 236, 239, 241 );
		$ink   = imagecolorallocate( $image, 35, 45, 55 );
		$ink2  = imagecolorallocate( $image, 70, 85, 100 );
		$noise = imagecolorallocate( $image, 150, 160, 170 );
		$line  = imagecolorallocate( $image, 120, 135, 150 );

		imagefilledrectangle( $image, 0, 0, $width, $height, $bg );

		// Light pixel noise.
		for ( $i = 0; $i < 120; $i++ ) {
			imagesetpixel( $image, wp_rand( 0, $width - 1 ), wp_rand( 0, $height - 1 ), $noise );
		}

		// Few interference lines.
		for ( $i = 0; $i < 3; $i++ ) {
			imageline(
				$image,
				wp_rand( 0, $width ),
				wp_rand( 0, $height ),
				wp_rand( 0, $width ),
				wp_rand( 0, $height ),
				$line
			);
		}

		// Built-in GD font only (faster than TTF for this use-case).
		$len  = strlen( $code );
		$step = (int) ( ( $width - 16 ) / max( 1, $len ) );

		for ( $i = 0; $i < $len; $i++ ) {
			$x = 8 + ( $i * $step ) + wp_rand( -1, 1 );
			$y = wp_rand( 12, 22 );
			imagestring( $image, 5, $x, $y, $code[ $i ], ( 0 === $i % 2 ) ? $ink : $ink2 );
		}

		// Tiny speckles over glyphs.
		for ( $i = 0; $i < 40; $i++ ) {
			imagesetpixel( $image, wp_rand( 0, $width - 1 ), wp_rand( 0, $height - 1 ), $noise );
		}

		nocache_headers();
		header( 'Content-Type: image/jpeg' );
		header( 'X-Content-Type-Options: nosniff' );
		imagejpeg( $image, null, 72 );
		imagedestroy( $image );
		exit;
	}

	/**
	 * Render captcha fields markup for the form.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function render_fields( $post_id ) {
		$post_id = absint( $post_id );
		ob_start();
		?>
		<div class="wpqa-hp" aria-hidden="true">
			<label for="wpqa-website-<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'وب‌سایت', 'wp-qa-comments' ); ?></label>
			<input type="text" id="wpqa-website-<?php echo esc_attr( $post_id ); ?>" name="wpqa_website" value="" tabindex="-1" autocomplete="off" />
		</div>
		<?php

		if ( ! self::is_enabled() ) {
			return ob_get_clean();
		}

		if ( 'turnstile' === self::get_type() ) {
			$site_key = trim( (string) WPQA_Settings::get( 'turnstile_site_key', '' ) );
			?>
			<div class="wpqa-field wpqa-captcha wpqa-captcha--turnstile">
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-theme="light"></div>
			</div>
			<?php
			return ob_get_clean();
		}

		if ( 'math' === self::get_type() ) {
			$challenge = self::create_math_challenge();
			?>
			<div class="wpqa-field wpqa-captcha wpqa-captcha--math">
				<label for="wpqa-captcha-<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'کپچا', 'wp-qa-comments' ); ?></label>
				<p class="wpqa-captcha-question" data-role="question"><?php echo esc_html( $challenge['question'] ); ?></p>
				<input type="hidden" name="wpqa_captcha_token" value="<?php echo esc_attr( $challenge['token'] ); ?>" data-role="token" />
				<input type="text" id="wpqa-captcha-<?php echo esc_attr( $post_id ); ?>" name="wpqa_captcha_answer" inputmode="numeric" autocomplete="off" required placeholder="<?php esc_attr_e( 'پاسخ را وارد کنید', 'wp-qa-comments' ); ?>" />
				<button type="button" class="wpqa-captcha-refresh" aria-label="<?php esc_attr_e( 'تولید کپچای جدید', 'wp-qa-comments' ); ?>" title="<?php esc_attr_e( 'کپچای جدید', 'wp-qa-comments' ); ?>">
					<svg class="wpqa-captcha-refresh-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path fill="currentColor" d="M17.65 6.35A7.95 7.95 0 0 0 12 4a8 8 0 1 0 7.75 10h-2.1A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
					</svg>
				</button>
			</div>
			<?php
			return ob_get_clean();
		}

		$challenge = self::create_image_challenge();
		?>
		<div class="wpqa-field wpqa-captcha wpqa-captcha--image">
			<label for="wpqa-captcha-<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'کد امنیتی تصویر', 'wp-qa-comments' ); ?></label>
			<div class="wpqa-captcha-image-row">
				<img
					class="wpqa-captcha-image"
					data-role="image"
					src="<?php echo esc_url( $challenge['image_url'] ); ?>"
					alt="<?php esc_attr_e( 'کپچای تصویری', 'wp-qa-comments' ); ?>"
					width="150"
					height="48"
					loading="lazy"
					decoding="async"
				/>
				<button type="button" class="wpqa-captcha-refresh" aria-label="<?php esc_attr_e( 'تصویر جدید', 'wp-qa-comments' ); ?>" title="<?php esc_attr_e( 'تصویر جدید', 'wp-qa-comments' ); ?>">
					<svg class="wpqa-captcha-refresh-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path fill="currentColor" d="M17.65 6.35A7.95 7.95 0 0 0 12 4a8 8 0 1 0 7.75 10h-2.1A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
					</svg>
				</button>
			</div>
			<input type="hidden" name="wpqa_captcha_token" value="<?php echo esc_attr( $challenge['token'] ); ?>" data-role="token" />
			<input
				type="text"
				id="wpqa-captcha-<?php echo esc_attr( $post_id ); ?>"
				name="wpqa_captcha_answer"
				autocomplete="off"
				autocapitalize="characters"
				spellcheck="false"
				required
				maxlength="8"
				placeholder="<?php esc_attr_e( 'کد داخل تصویر را وارد کنید', 'wp-qa-comments' ); ?>"
			/>
			<p class="wpqa-captcha-hint"><?php esc_html_e( 'حروف بزرگ/کوچک مهم نیست. کاراکترهای مبهم حذف شده‌اند.', 'wp-qa-comments' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
