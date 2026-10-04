<?php
/**
 * REST API used by the admin editor.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Rest {

	const NS = 'wonom-slider/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/state',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_state' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/slides',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_slides' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/attachment/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'attachment' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'export' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/advanced',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_advanced' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/update-status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'update_status' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/translate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'translate' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/purge',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'purge' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/render',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'render_slide' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'import' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Full editor state.
	 */
	public static function get_state() {
		return rest_ensure_response( self::state() );
	}

	public static function state() {
		$slides = Wonom_Slider_Data::get_slides();
		foreach ( $slides as $k => $slide ) {
			$slides[ $k ]['status'] = Wonom_Slider_Data::slide_status( $slide );
			$slides[ $k ]['thumb']  = $slide['image_id'] ? wp_get_attachment_image_url( $slide['image_id'], 'medium' ) : $slide['image_url'];
			$slides[ $k ]['mobile_thumb'] = $slide['mobile_image_id'] ? wp_get_attachment_image_url( $slide['mobile_image_id'], 'medium' ) : $slide['mobile_image_url'];
		}
		return array(
			'slides'    => $slides,
			'settings'  => Wonom_Slider_Data::get_settings(),
			'advanced'  => Wonom_Slider_Data::get_advanced_public(),
			'cache'     => Wonom_Slider_Cache::status(),
			'fonts'     => array_keys( Wonom_Slider_Data::fonts() ),
			'languages' => Wonom_Slider_Data::get_languages(),
			'now'       => wp_date( 'Y-m-d\TH:i' ),
			'timezone'  => wp_timezone_string(),
		);
	}

	/**
	 * Render one (unsaved) slide with (unsaved) settings for the editor stage.
	 * Returns the slider HTML plus the stylesheet URLs the stage iframe needs.
	 */
	public static function render_slide( WP_REST_Request $req ) {
		$slide    = $req->get_param( 'slide' );
		$settings = $req->get_param( 'settings' );
		$lang     = sanitize_key( (string) $req->get_param( 'lang' ) );
		if ( ! is_array( $slide ) ) {
			return new WP_Error( 'wonom_invalid', __( 'Invalid payload.', 'wonom-slider' ), array( 'status' => 400 ) );
		}
		$slide    = wp_parse_args( Wonom_Slider_Data::sanitize_slide( $slide ), Wonom_Slider_Data::default_slide() );
		$settings = is_array( $settings ) ? Wonom_Slider_Data::sanitize_settings( $settings ) : Wonom_Slider_Data::get_settings();

		$html = '';
		if ( Wonom_Slider_Data::slide_has_content( $slide ) ) {
			$html = Wonom_Slider_Frontend::render(
				array(
					'slides'   => array( $slide ),
					'settings' => $settings,
					'lang'     => $lang ? $lang : null,
				)
			);
		}
		return rest_ensure_response(
			array(
				'html'  => $html,
				'css'   => add_query_arg( 'ver', WONOM_SLIDER_VERSION, WONOM_SLIDER_URL . 'assets/public/slider.css' ),
				'fonts' => Wonom_Slider_Data::font_stylesheet_url( $settings ),
				'custom_css' => $settings['custom_css'],
			)
		);
	}

	public static function purge() {
		$done = Wonom_Slider_Cache::purge( true );
		return rest_ensure_response(
			array(
				'done'   => $done,
				'errors' => Wonom_Slider_Cache::last_errors(),
			)
		);
	}

	public static function save_advanced( WP_REST_Request $req ) {
		$adv = $req->get_param( 'advanced' );
		if ( ! is_array( $adv ) ) {
			return new WP_Error( 'wonom_invalid', __( 'Invalid payload.', 'wonom-slider' ), array( 'status' => 400 ) );
		}
		Wonom_Slider_Data::save_advanced( $adv );
		return rest_ensure_response( self::state() );
	}

	public static function update_status( WP_REST_Request $req ) {
		$force = Wonom_Slider_Data::to_bool( $req->get_param( 'force' ) );
		return rest_ensure_response( Wonom_Slider_Updater::status( $force ) );
	}

	/**
	 * Machine translation of slide texts via DeepL (needs an API key in settings)
	 * or a custom service hooked to the 'wonom_slider_translate' filter.
	 */
	public static function translate( WP_REST_Request $req ) {
		$fields = $req->get_param( 'fields' );
		$source = sanitize_key( $req->get_param( 'source' ) );
		$target = sanitize_key( $req->get_param( 'target' ) );
		if ( ! is_array( $fields ) || '' === $target ) {
			return new WP_Error( 'wonom_invalid', __( 'Invalid payload.', 'wonom-slider' ), array( 'status' => 400 ) );
		}

		$clean = array();
		foreach ( Wonom_Slider_Data::translatable_fields() as $f ) {
			if ( isset( $fields[ $f ] ) && '' !== trim( (string) $fields[ $f ] ) && 'button_url' !== $f && 'button2_url' !== $f ) {
				$clean[ $f ] = sanitize_text_field( $fields[ $f ] );
			}
		}

		/**
		 * Plug in a custom translation service. Return array( field => translated ) or null.
		 */
		$custom = apply_filters( 'wonom_slider_translate', null, $clean, $source, $target );
		if ( is_array( $custom ) ) {
			return rest_ensure_response( array( 'fields' => $custom, 'engine' => 'custom' ) );
		}

		$adv = Wonom_Slider_Data::get_advanced();
		$key = trim( (string) $adv['deepl_key'] );
		if ( '' === $key ) {
			return new WP_Error( 'wonom_no_key', __( 'Add a DeepL API key under Settings → Translation to translate automatically.', 'wonom-slider' ), array( 'status' => 400 ) );
		}
		if ( empty( $clean ) ) {
			return rest_ensure_response( array( 'fields' => array(), 'engine' => 'DeepL' ) );
		}

		$endpoint = ( false !== strpos( $key, ':fx' ) ) ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';
		$body     = array(
			'text'        => array_values( $clean ),
			'target_lang' => strtoupper( substr( $target, 0, 2 ) ),
		);
		if ( $source ) {
			$body['source_lang'] = strtoupper( substr( $source, 0, 2 ) );
		}
		$res = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'wonom_deepl', $res->get_error_message(), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || empty( $json['translations'] ) ) {
			$msg = isset( $json['message'] ) ? $json['message'] : sprintf( 'DeepL HTTP %d', $code );
			return new WP_Error( 'wonom_deepl', $msg, array( 'status' => 502 ) );
		}
		$out  = array();
		$keys = array_keys( $clean );
		foreach ( $json['translations'] as $i => $t ) {
			if ( isset( $keys[ $i ], $t['text'] ) ) {
				$out[ $keys[ $i ] ] = sanitize_text_field( $t['text'] );
			}
		}
		return rest_ensure_response( array( 'fields' => $out, 'engine' => 'DeepL' ) );
	}

	public static function save_slides( WP_REST_Request $req ) {
		$slides = $req->get_param( 'slides' );
		if ( ! is_array( $slides ) ) {
			return new WP_Error( 'wonom_invalid', __( 'Invalid payload.', 'wonom-slider' ), array( 'status' => 400 ) );
		}
		Wonom_Slider_Data::save_slides( $slides );
		return rest_ensure_response( self::state() );
	}

	public static function save_settings( WP_REST_Request $req ) {
		$settings = $req->get_param( 'settings' );
		if ( ! is_array( $settings ) ) {
			return new WP_Error( 'wonom_invalid', __( 'Invalid payload.', 'wonom-slider' ), array( 'status' => 400 ) );
		}
		Wonom_Slider_Data::save_settings( $settings );
		return rest_ensure_response( self::state() );
	}

	public static function attachment( WP_REST_Request $req ) {
		$id  = (int) $req['id'];
		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( ! $src ) {
			return new WP_Error( 'wonom_not_found', __( 'Attachment not found.', 'wonom-slider' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'id'     => $id,
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
				'thumb'  => wp_get_attachment_image_url( $id, 'medium' ),
				'alt'    => get_post_meta( $id, '_wp_attachment_image_alt', true ),
			)
		);
	}

	public static function export() {
		return rest_ensure_response(
			array(
				'plugin'   => 'wonom-slider',
				'version'  => WONOM_SLIDER_VERSION,
				'exported' => wp_date( 'c' ),
				'slides'   => Wonom_Slider_Data::get_slides(),
				'settings' => Wonom_Slider_Data::get_settings(),
			)
		);
	}

	public static function import( WP_REST_Request $req ) {
		$data = $req->get_param( 'data' );
		if ( ! is_array( $data ) || empty( $data['slides'] ) || ! is_array( $data['slides'] ) ) {
			return new WP_Error( 'wonom_invalid', __( 'Invalid import file.', 'wonom-slider' ), array( 'status' => 400 ) );
		}
		Wonom_Slider_Data::save_slides( $data['slides'] );
		if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
			Wonom_Slider_Data::save_settings( $data['settings'] );
		}
		return rest_ensure_response( self::state() );
	}
}
