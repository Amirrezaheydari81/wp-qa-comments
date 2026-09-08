<?php
/**
 * Plugin settings.
 *
 * @package WP_QA_Comments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPQA_Settings
 */
class WPQA_Settings {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'wpqa_settings';

	/**
	 * Singleton.
	 *
	 * @var WPQA_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WPQA_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled_post_types'       => array( 'post', 'page' ),
			'per_page'                 => 10,
			'require_approval'         => 1,
			'show_form'                => 1,
			'auto_append'              => 1,
			'captcha_enabled'          => 1,
			'captcha_type'             => 'image',
			'turnstile_site_key'       => '',
			'turnstile_secret_key'     => '',
			'delete_data_on_uninstall' => 0,
		);
	}

	/**
	 * Ensure defaults exist in the database.
	 */
	public static function ensure_defaults() {
		$existing = get_option( self::OPTION_KEY, null );
		if ( null === $existing ) {
			add_option( self::OPTION_KEY, self::defaults() );
		}
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_all() {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return wp_parse_args( $settings, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_all();
		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}
		return $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array $data Raw settings input.
	 * @return array Sanitized settings.
	 */
	public static function update( $data ) {
		$defaults = self::defaults();
		$clean    = array();

		$post_types = array();
		if ( ! empty( $data['enabled_post_types'] ) && is_array( $data['enabled_post_types'] ) ) {
			$public_types = get_post_types( array( 'public' => true ), 'names' );
			foreach ( $data['enabled_post_types'] as $pt ) {
				$pt = sanitize_key( $pt );
				if ( isset( $public_types[ $pt ] ) ) {
					$post_types[] = $pt;
				}
			}
		}
		$clean['enabled_post_types'] = array_values( array_unique( $post_types ) );

		$clean['per_page'] = max( 1, min( 100, absint( isset( $data['per_page'] ) ? $data['per_page'] : $defaults['per_page'] ) ) );

		$clean['require_approval'] = empty( $data['require_approval'] ) ? 0 : 1;
		$clean['show_form']        = empty( $data['show_form'] ) ? 0 : 1;
		$clean['auto_append']      = empty( $data['auto_append'] ) ? 0 : 1;
		$clean['captcha_enabled']  = empty( $data['captcha_enabled'] ) ? 0 : 1;

		$type = isset( $data['captcha_type'] ) ? sanitize_key( $data['captcha_type'] ) : 'image';
		$clean['captcha_type'] = in_array( $type, array( 'image', 'math', 'turnstile' ), true ) ? $type : 'image';

		$clean['turnstile_site_key']   = isset( $data['turnstile_site_key'] ) ? sanitize_text_field( $data['turnstile_site_key'] ) : '';
		$clean['turnstile_secret_key'] = isset( $data['turnstile_secret_key'] ) ? sanitize_text_field( $data['turnstile_secret_key'] ) : '';

		$clean['delete_data_on_uninstall'] = empty( $data['delete_data_on_uninstall'] ) ? 0 : 1;

		update_option( self::OPTION_KEY, $clean );

		return $clean;
	}

	/**
	 * Whether Q&A is enabled for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function is_enabled_for( $post_type ) {
		$enabled = self::get( 'enabled_post_types', array() );
		return in_array( $post_type, (array) $enabled, true );
	}

	/**
	 * Get public post types for settings UI.
	 *
	 * @return array
	 */
	public static function get_public_post_types() {
		$types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		unset( $types['attachment'] );

		return $types;
	}
}
