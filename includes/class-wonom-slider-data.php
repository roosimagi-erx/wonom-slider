<?php
/**
 * Data layer: option storage, defaults, sanitisation and scheduling logic.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Data {

	const OPTION_SLIDES   = 'wonom_slider_slides';
	const OPTION_SETTINGS = 'wonom_slider_settings';
	const OPTION_ADVANCED = 'wonom_slider_advanced';

	/**
	 * Advanced options (updates, translation, uninstall).
	 *
	 * @return array
	 */
	public static function get_advanced() {
		$saved = get_option( self::OPTION_ADVANCED, array() );
		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'update_repo'         => '',
				'update_token'        => '',
				'deepl_key'           => '',
				'delete_on_uninstall' => false,
				'auto_purge'          => true,
				'cf_zone'             => '',
				'cf_token'            => '',
			)
		);
	}

	/**
	 * Save advanced options. Empty token / key keeps the stored one (so the UI can mask them).
	 *
	 * @param array $in Raw.
	 * @return array
	 */
	public static function save_advanced( $in ) {
		$cur = self::get_advanced();
		$in  = is_array( $in ) ? $in : array();
		$out = $cur;

		if ( isset( $in['update_repo'] ) ) {
			$repo = trim( (string) $in['update_repo'], " \t\n\r/" );
			$repo = preg_replace( '#^https?://github\.com/#i', '', $repo );
			$repo = preg_replace( '#\.git$#i', '', $repo );
			$out['update_repo'] = preg_match( '#^[\w.-]+/[\w.-]+$#', $repo ) ? $repo : '';
		}
		if ( isset( $in['auto_purge'] ) ) {
			$out['auto_purge'] = self::to_bool( $in['auto_purge'] );
		}
		if ( isset( $in['cf_zone'] ) ) {
			$out['cf_zone'] = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $in['cf_zone'] ) );
		}
		foreach ( array( 'update_token', 'deepl_key', 'cf_token' ) as $secret ) {
			if ( isset( $in[ $secret ] ) ) {
				$v = trim( (string) $in[ $secret ] );
				if ( '__clear__' === $v ) {
					$out[ $secret ] = '';
				} elseif ( '' !== $v && false === strpos( $v, '••' ) ) {
					$out[ $secret ] = sanitize_text_field( $v );
				}
			}
		}
		if ( isset( $in['delete_on_uninstall'] ) ) {
			$out['delete_on_uninstall'] = self::to_bool( $in['delete_on_uninstall'] );
		}

		update_option( self::OPTION_ADVANCED, $out, false );
		update_option( 'wonom_slider_delete_on_uninstall', $out['delete_on_uninstall'] ? 1 : 0, false );
		delete_site_transient( 'wonom_slider_github_release' );
		return $out;
	}

	/**
	 * Advanced options safe to send to the browser (secrets masked).
	 */
	public static function get_advanced_public() {
		$a = self::get_advanced();
		return array(
			'update_repo'         => $a['update_repo'] ? $a['update_repo'] : ( WONOM_SLIDER_GITHUB_OWNER . '/' . WONOM_SLIDER_GITHUB_REPO ),
			'update_repo_custom'  => $a['update_repo'],
			'update_token'        => $a['update_token'] ? '••••••••' . substr( $a['update_token'], -4 ) : '',
			'update_token_const'  => defined( 'WONOM_SLIDER_GITHUB_TOKEN' ) && WONOM_SLIDER_GITHUB_TOKEN,
			'deepl_key'           => $a['deepl_key'] ? '••••••••' . substr( $a['deepl_key'], -4 ) : '',
			'delete_on_uninstall' => (bool) $a['delete_on_uninstall'],
			'auto_purge'          => (bool) $a['auto_purge'],
			'cf_zone'             => $a['cf_zone'],
			'cf_token'            => $a['cf_token'] ? '••••••••' . substr( $a['cf_token'], -4 ) : '',
		);
	}

	/**
	 * Default global settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'autoplay'            => true,
			'interval'            => 6000,
			'speed'               => 800,
			'transition'          => 'fade', // fade | slide
			'loop'                => true,
			'pause_on_hover'      => true,
			'show_arrows'         => true,
			'show_dots'           => true,
			'show_progress'       => false,
			'dots_position'       => 'center', // left | center | right
			'dots_mobile'         => 'same',   // same | left | center | right | hidden
			'arrows_mobile'       => 'show',   // show | hide
			'ken_burns'           => false,
			'ratio_desktop'       => 'auto', // auto | "1920x660"
			'ratio_mobile'        => 'auto',
			'max_width'           => 1920, // px, 0 = container width; 1920 = the most common monitor width
			'max_height'          => 40, // cap for the slider height on desktop (0 = none)
			'max_height_unit'     => 'vh', // vh (% of the screen height) | px
			'max_height_mobile'   => 0,
			'max_height_mobile_unit' => 'vh',
			'mobile_breakpoint'   => 768,
			'content_max_width'   => 720,
			'heading_size'        => 56,
			'heading_size_mobile' => 30,
			'font_family'         => 'inherit',
			'button_radius'       => 0,
			'heading_tag'         => 'h2', // h1 | h2 | h3 | p
			'text_size'           => 18,
			'text_size_mobile'    => 15,
			'padding_mobile'      => 20,
			'languages'           => '', // comma list, e.g. "et,en". Empty = auto-detect (Polylang/WPML) or single language.
			'font_heading'        => 'inherit', // inherit | <family from fonts()> | custom:<css value>
			'font_text'           => 'inherit',
			'heading_weight'      => 400,
			'heading_uppercase'   => true,
			'heading_spacing'     => 14, // letter-spacing in 1/100 em
			'campaign_template_id' => '', // slide id used as template for auto-created campaign slides
			'custom_css'          => '',
		);
	}

	/**
	 * Curated web fonts (served from Bunny Fonts – a GDPR-friendly Google Fonts mirror).
	 * key = family name as shown, value = Bunny slug.
	 *
	 * @return array<string,string>
	 */
	public static function fonts() {
		return apply_filters(
			'wonom_slider_fonts',
			array(
				'Lato'               => 'lato',
				'Montserrat'         => 'montserrat',
				'Poppins'            => 'poppins',
				'Inter'              => 'inter',
				'Raleway'            => 'raleway',
				'Playfair Display'   => 'playfair-display',
				'Cormorant Garamond' => 'cormorant-garamond',
				'DM Serif Display'   => 'dm-serif-display',
				'Oswald'             => 'oswald',
				'Josefin Sans'       => 'josefin-sans',
				'Nunito'             => 'nunito',
				'Quicksand'          => 'quicksand',
			)
		);
	}

	/**
	 * Normalise a font setting: 'inherit', a curated family, or 'custom:<css>'.
	 */
	public static function sanitize_font( $v, $default = 'inherit' ) {
		$v = trim( (string) $v );
		if ( '' === $v || 'inherit' === $v ) {
			return 'inherit';
		}
		if ( isset( self::fonts()[ $v ] ) ) {
			return $v;
		}
		if ( 0 === strpos( $v, 'custom:' ) ) {
			$css = sanitize_text_field( substr( $v, 7 ) );
			$css = str_replace( array( ';', '{', '}', '<', '>' ), '', $css );
			return '' !== trim( $css ) ? 'custom:' . trim( $css ) : 'inherit';
		}
		return $default;
	}

	/**
	 * CSS font-family value for a font setting.
	 */
	public static function font_css( $setting ) {
		if ( 'inherit' === $setting || '' === $setting ) {
			return 'inherit';
		}
		if ( 0 === strpos( $setting, 'custom:' ) ) {
			return substr( $setting, 7 );
		}
		$serif = in_array( $setting, array( 'Playfair Display', 'Cormorant Garamond', 'DM Serif Display' ), true );
		return '"' . $setting . '", ' . ( $serif ? 'Georgia, serif' : 'system-ui, sans-serif' );
	}

	/**
	 * Stylesheet URL for the selected web fonts, or '' when only inherit/custom fonts are used.
	 */
	public static function font_stylesheet_url( $settings, $slides = null ) {
		$fams = array();
		$add  = function ( $f, $weights ) use ( &$fams ) {
			if ( isset( self::fonts()[ $f ] ) ) {
				$slug          = self::fonts()[ $f ];
				$fams[ $slug ] = isset( $fams[ $slug ] ) ? '400,500,600,700' : $weights;
			}
		};
		foreach ( array( 'font_heading' => '400,500,600,700', 'font_text' => '400,500,700' ) as $key => $weights ) {
			$add( isset( $settings[ $key ] ) ? $settings[ $key ] : 'inherit', $weights );
		}
		if ( null === $slides ) {
			$slides = self::get_slides();
		}
		foreach ( (array) $slides as $s ) {
			if ( ! empty( $s['typo'] ) && is_array( $s['typo'] ) ) {
				foreach ( array( 'font_heading' => '400,500,600,700', 'font_text' => '400,500,700' ) as $key => $weights ) {
					if ( ! empty( $s['typo'][ $key ] ) ) {
						$add( $s['typo'][ $key ], $weights );
					}
				}
			}
		}
		if ( empty( $fams ) ) {
			return '';
		}
		$parts = array();
		foreach ( $fams as $slug => $w ) {
			$parts[] = $slug . ':' . $w;
		}
		return 'https://fonts.bunny.net/css?family=' . implode( '|', $parts ) . '&display=swap';
	}

	/**
	 * Slide fields that can be translated per language.
	 *
	 * @return string[]
	 */
	public static function translatable_fields() {
		return array( 'eyebrow', 'heading', 'text', 'button_text', 'button_url', 'button2_text', 'button2_url', 'badge', 'alt' );
	}

	/**
	 * Default single slide.
	 *
	 * @return array
	 */
	public static function default_slide() {
		return array(
			'id'                  => '',
			'name'                => '',
			'type'                => 'regular', // regular | campaign
			'enabled'             => true,
			'image_id'            => 0,
			'image_url'           => '',
			'image_width'         => 0,
			'image_height'        => 0,
			'mobile_image_id'     => 0,
			'mobile_image_url'    => '',
			'mobile_image_width'  => 0,
			'mobile_image_height' => 0,
			'alt'                 => '',
			'eyebrow'             => '',
			'heading'             => '',
			'text'                => '',
			'button_text'         => '',
			'button_url'          => '',
			'button_new_tab'      => false,
			'button2_text'        => '',
			'button2_url'         => '',
			'link_whole_slide'    => false,
			'align'               => 'center', // left | center | right
			'valign'              => 'middle', // top | middle | bottom
			'text_color'          => '#ffffff',
			'button_bg'           => '#f28cb1',
			'button_color'        => '#ffffff',
			'overlay'             => 20,
			'overlay_color'       => '#000000',
			'bg_color'            => '#1d2433', // shown behind the image and for image-less slides
			'badge'               => '',
			'start'               => '',
			'end'                 => '',
			'focal_x'             => 50,
			'focal_y'             => 50,
			'mobile_focal_x'      => 50,
			'mobile_focal_y'      => 50,
			'mobile_align'        => '', // '' = same as desktop
			'mobile_valign'       => '',
			'mobile_hide_text'    => false,
			'pos_mode'            => 'grid', // grid (align/valign) | free (pos_x/pos_y/pos_w in %)
			'pos_x'               => 50,
			'pos_y'               => 50,
			'pos_w'               => 60,
			'mobile_pos_mode'     => '', // '' = same as desktop | grid | free
			'mobile_pos_x'        => 50,
			'mobile_pos_y'        => 50,
			'mobile_pos_w'        => 90,
			// Background: a single image (image_* fields) or a collage of 2–4 images built in the browser.
			'bg_mode'             => 'image', // image | collage
			'collage'             => array(), // list of { id, url, width, height, focal_x, focal_y }
			'collage_seam'        => 'fade',  // hard | fade | blur
			'collage_gap'         => 0,       // px, used with the hard seam
			'collage_mobile'      => 'first2', // all | first2 | first1
			// Frame: inset from the slide edge + corner radius; the background colour shows around it.
			'frame_width'         => 0,
			'frame_radius'        => 0,
			// Content source: own texts, or a Wonom Kampaaniariba campaign (texts, link, coupon, schedule follow it).
			'source'              => 'own', // own | campaign
			'campaign_id'         => 0,
			// Per-slide typography overrides. '' / null = use the global setting.
			'typo'                => array(),
			'i18n'                => array(), // lang => array( field => value )
		);
	}

	/**
	 * Per-slide typography keys → [type, min, max].
	 * font_* accept font settings; numbers are px except heading_spacing (1/100 em).
	 */
	public static function typo_fields() {
		return array(
			'font_heading'        => array( 'font' ),
			'font_text'           => array( 'font' ),
			'heading_size'        => array( 'int', 12, 160 ),
			'heading_size_mobile' => array( 'int', 12, 100 ),
			'text_size'           => array( 'int', 10, 60 ),
			'text_size_mobile'    => array( 'int', 10, 40 ),
			'heading_weight'      => array( 'int', 300, 800 ),
			'heading_uppercase'   => array( 'bool' ),
			'heading_spacing'     => array( 'int', -10, 60 ),
			'gap'                 => array( 'int', 0, 80 ),  // space between heading, eyebrow and text
			'gap_button'          => array( 'int', 0, 100 ), // space above the buttons
			'button_radius'       => array( 'int', 0, 100 ),
			'eyebrow_size'        => array( 'int', 8, 40 ),
			'eyebrow_size_mobile' => array( 'int', 8, 30 ),
		);
	}

	/**
	 * Sanitise the per-slide typography array; only explicitly set keys are kept.
	 */
	public static function sanitize_typo( $in ) {
		$out = array();
		if ( ! is_array( $in ) ) {
			return $out;
		}
		foreach ( self::typo_fields() as $k => $def ) {
			if ( ! isset( $in[ $k ] ) || '' === $in[ $k ] || null === $in[ $k ] ) {
				continue;
			}
			$v = $in[ $k ];
			if ( 'font' === $def[0] ) {
				$f = self::sanitize_font( $v, '' );
				if ( '' !== $f && 'inherit' !== $f ) {
					$out[ $k ] = $f;
				} elseif ( 'inherit' === $f && 'inherit' === $v ) {
					$out[ $k ] = 'inherit'; // explicitly "theme font" even if the global uses a web font.
				}
			} elseif ( 'bool' === $def[0] ) {
				$out[ $k ] = self::to_bool( $v );
			} elseif ( is_numeric( $v ) ) {
				$out[ $k ] = max( $def[1], min( $def[2], (int) $v ) );
			}
		}
		return $out;
	}

	/**
	 * Settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::default_settings() );
	}

	/**
	 * All slides merged with defaults, order preserved.
	 *
	 * @return array[]
	 */
	public static function get_slides() {
		$slides = get_option( self::OPTION_SLIDES, array() );
		if ( ! is_array( $slides ) ) {
			$slides = array();
		}
		$out = array();
		foreach ( $slides as $slide ) {
			if ( is_array( $slide ) ) {
				$slide = wp_parse_args( $slide, self::default_slide() );
				if ( class_exists( 'Wonom_Slider_Campaigns' ) ) {
					$slide = Wonom_Slider_Campaigns::apply( $slide );
				}
				$out[] = $slide;
			}
		}
		return $out;
	}

	/**
	 * Persist settings after sanitisation.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public static function save_settings( $settings ) {
		$clean = self::sanitize_settings( $settings );
		update_option( self::OPTION_SETTINGS, $clean, false );
		do_action( 'wonom_slider_saved' );
		return $clean;
	}

	/**
	 * Persist slides after sanitisation.
	 *
	 * @param array $slides Raw slides.
	 * @return array
	 */
	public static function save_slides( $slides ) {
		$clean = array();
		if ( is_array( $slides ) ) {
			foreach ( $slides as $slide ) {
				if ( is_array( $slide ) ) {
					$clean[] = self::sanitize_slide( $slide );
				}
			}
		}
		update_option( self::OPTION_SLIDES, $clean, false );
		do_action( 'wonom_slider_saved' );
		return $clean;
	}

	/**
	 * Sanitise settings.
	 *
	 * @param array $in Raw.
	 * @return array
	 */
	public static function sanitize_settings( $in ) {
		$d   = self::default_settings();
		$in  = is_array( $in ) ? $in : array();
		$out = array();

		foreach ( array( 'autoplay', 'loop', 'pause_on_hover', 'show_arrows', 'show_dots', 'show_progress', 'ken_burns' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? self::to_bool( $in[ $k ] ) : $d[ $k ];
		}

		$out['interval'] = isset( $in['interval'] ) ? max( 1000, min( 60000, absint( $in['interval'] ) ) ) : $d['interval'];
		$out['speed']    = isset( $in['speed'] ) ? max( 100, min( 5000, absint( $in['speed'] ) ) ) : $d['speed'];

		$out['transition'] = ( isset( $in['transition'] ) && in_array( $in['transition'], array( 'fade', 'slide' ), true ) ) ? $in['transition'] : $d['transition'];

		$out['dots_position'] = ( isset( $in['dots_position'] ) && in_array( $in['dots_position'], array( 'left', 'center', 'right' ), true ) ) ? $in['dots_position'] : $d['dots_position'];
		$out['dots_mobile']   = ( isset( $in['dots_mobile'] ) && in_array( $in['dots_mobile'], array( 'same', 'left', 'center', 'right', 'hidden' ), true ) ) ? $in['dots_mobile'] : $d['dots_mobile'];
		$out['arrows_mobile'] = ( isset( $in['arrows_mobile'] ) && 'hide' === $in['arrows_mobile'] ) ? 'hide' : 'show';

		$out['ratio_desktop'] = self::sanitize_ratio( isset( $in['ratio_desktop'] ) ? $in['ratio_desktop'] : $d['ratio_desktop'] );
		$out['ratio_mobile']  = self::sanitize_ratio( isset( $in['ratio_mobile'] ) ? $in['ratio_mobile'] : $d['ratio_mobile'] );

		$out['max_width']           = isset( $in['max_width'] ) ? min( 4000, absint( $in['max_width'] ) ) : $d['max_width'];
		$out['max_height']          = isset( $in['max_height'] ) ? min( 2000, absint( $in['max_height'] ) ) : $d['max_height'];
		$out['max_height_unit']     = ( isset( $in['max_height_unit'] ) && 'px' === $in['max_height_unit'] ) ? 'px' : 'vh';
		$out['max_height_mobile']   = isset( $in['max_height_mobile'] ) ? min( 2000, absint( $in['max_height_mobile'] ) ) : $d['max_height_mobile'];
		$out['max_height_mobile_unit'] = ( isset( $in['max_height_mobile_unit'] ) && 'px' === $in['max_height_mobile_unit'] ) ? 'px' : 'vh';
		if ( 'vh' === $out['max_height_unit'] ) {
			$out['max_height'] = min( 100, $out['max_height'] );
		}
		if ( 'vh' === $out['max_height_mobile_unit'] ) {
			$out['max_height_mobile'] = min( 100, $out['max_height_mobile'] );
		}
		$out['mobile_breakpoint']   = isset( $in['mobile_breakpoint'] ) ? max( 320, min( 1400, absint( $in['mobile_breakpoint'] ) ) ) : $d['mobile_breakpoint'];
		$out['content_max_width']   = isset( $in['content_max_width'] ) ? max( 200, min( 2000, absint( $in['content_max_width'] ) ) ) : $d['content_max_width'];
		$out['heading_size']        = isset( $in['heading_size'] ) ? max( 12, min( 160, absint( $in['heading_size'] ) ) ) : $d['heading_size'];
		$out['heading_size_mobile'] = isset( $in['heading_size_mobile'] ) ? max( 12, min( 100, absint( $in['heading_size_mobile'] ) ) ) : $d['heading_size_mobile'];
		$out['button_radius']       = isset( $in['button_radius'] ) ? min( 100, absint( $in['button_radius'] ) ) : $d['button_radius'];

		$out['heading_tag'] = ( isset( $in['heading_tag'] ) && in_array( $in['heading_tag'], array( 'h1', 'h2', 'h3', 'p' ), true ) ) ? $in['heading_tag'] : $d['heading_tag'];

		$out['text_size']        = isset( $in['text_size'] ) ? max( 10, min( 60, absint( $in['text_size'] ) ) ) : $d['text_size'];
		$out['text_size_mobile'] = isset( $in['text_size_mobile'] ) ? max( 10, min( 40, absint( $in['text_size_mobile'] ) ) ) : $d['text_size_mobile'];
		$out['padding_mobile']   = isset( $in['padding_mobile'] ) ? min( 100, absint( $in['padding_mobile'] ) ) : $d['padding_mobile'];

		$langs = array();
		if ( isset( $in['languages'] ) ) {
			foreach ( explode( ',', (string) $in['languages'] ) as $l ) {
				$l = strtolower( trim( $l ) );
				if ( preg_match( '/^[a-z]{2,3}([_-][a-z]{2,4})?$/', $l ) ) {
					$langs[] = $l;
				}
			}
		}
		$out['languages'] = implode( ',', array_unique( $langs ) );

		$out['font_family'] = isset( $in['font_family'] ) ? sanitize_text_field( $in['font_family'] ) : $d['font_family'];
		if ( '' === $out['font_family'] ) {
			$out['font_family'] = 'inherit';
		}

		$out['font_heading']      = self::sanitize_font( isset( $in['font_heading'] ) ? $in['font_heading'] : $d['font_heading'] );
		$out['font_text']         = self::sanitize_font( isset( $in['font_text'] ) ? $in['font_text'] : $d['font_text'] );
		$out['heading_weight']    = ( isset( $in['heading_weight'] ) && in_array( (int) $in['heading_weight'], array( 300, 400, 500, 600, 700, 800 ), true ) ) ? (int) $in['heading_weight'] : $d['heading_weight'];
		$out['heading_uppercase'] = isset( $in['heading_uppercase'] ) ? self::to_bool( $in['heading_uppercase'] ) : $d['heading_uppercase'];
		$out['heading_spacing']   = isset( $in['heading_spacing'] ) ? max( -10, min( 60, (int) $in['heading_spacing'] ) ) : $d['heading_spacing'];

		$out['custom_css'] = isset( $in['custom_css'] ) ? self::sanitize_css( $in['custom_css'] ) : '';

		$out['campaign_template_id'] = isset( $in['campaign_template_id'] ) ? preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $in['campaign_template_id'] ) : '';

		return $out;
	}

	/**
	 * Sanitise one slide.
	 *
	 * @param array $in Raw slide.
	 * @return array
	 */
	public static function sanitize_slide( $in ) {
		$d   = self::default_slide();
		$out = array();

		$out['id'] = isset( $in['id'] ) ? preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $in['id'] ) : '';
		if ( '' === $out['id'] ) {
			$out['id'] = self::generate_id();
		}

		$out['name']    = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : '';
		$out['type']    = ( isset( $in['type'] ) && 'campaign' === $in['type'] ) ? 'campaign' : 'regular';
		$out['enabled'] = isset( $in['enabled'] ) ? self::to_bool( $in['enabled'] ) : true;

		foreach ( array( 'image_id', 'image_width', 'image_height', 'mobile_image_id', 'mobile_image_width', 'mobile_image_height' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? absint( $in[ $k ] ) : 0;
		}
		$out['image_url']        = isset( $in['image_url'] ) ? esc_url_raw( $in['image_url'] ) : '';
		$out['mobile_image_url'] = isset( $in['mobile_image_url'] ) ? esc_url_raw( $in['mobile_image_url'] ) : '';

		// When an attachment ID is present, trust the attachment for URL and dimensions.
		foreach ( array( 'image', 'mobile_image' ) as $base ) {
			$id = $out[ $base . '_id' ];
			if ( $id ) {
				$src = wp_get_attachment_image_src( $id, 'full' );
				if ( $src ) {
					$out[ $base . '_url' ]    = $src[0];
					$out[ $base . '_width' ]  = (int) $src[1];
					$out[ $base . '_height' ] = (int) $src[2];
				} else {
					// Attachment deleted from the media library.
					$out[ $base . '_id' ] = 0;
				}
			}
		}

		foreach ( array( 'alt', 'eyebrow', 'heading', 'button_text', 'button2_text', 'badge' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		}
		$allowed     = array(
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'span'   => array( 'class' => array() ),
		);
		$out['text'] = isset( $in['text'] ) ? wp_kses( $in['text'], $allowed ) : '';

		$out['button_url']  = isset( $in['button_url'] ) ? esc_url_raw( trim( $in['button_url'] ) ) : '';
		$out['button2_url'] = isset( $in['button2_url'] ) ? esc_url_raw( trim( $in['button2_url'] ) ) : '';

		$out['button_new_tab']   = isset( $in['button_new_tab'] ) ? self::to_bool( $in['button_new_tab'] ) : false;
		$out['link_whole_slide'] = isset( $in['link_whole_slide'] ) ? self::to_bool( $in['link_whole_slide'] ) : false;

		$out['align']  = ( isset( $in['align'] ) && in_array( $in['align'], array( 'left', 'center', 'right' ), true ) ) ? $in['align'] : $d['align'];
		$out['valign'] = ( isset( $in['valign'] ) && in_array( $in['valign'], array( 'top', 'middle', 'bottom' ), true ) ) ? $in['valign'] : $d['valign'];

		foreach ( array( 'text_color', 'button_bg', 'button_color', 'overlay_color', 'bg_color' ) as $k ) {
			$c         = isset( $in[ $k ] ) ? sanitize_hex_color( $in[ $k ] ) : null;
			$out[ $k ] = $c ? $c : $d[ $k ];
		}

		$out['overlay'] = isset( $in['overlay'] ) ? max( 0, min( 100, absint( $in['overlay'] ) ) ) : $d['overlay'];
		$out['focal_x'] = isset( $in['focal_x'] ) ? max( 0, min( 100, absint( $in['focal_x'] ) ) ) : 50;
		$out['focal_y'] = isset( $in['focal_y'] ) ? max( 0, min( 100, absint( $in['focal_y'] ) ) ) : 50;

		$out['mobile_focal_x']   = isset( $in['mobile_focal_x'] ) ? max( 0, min( 100, absint( $in['mobile_focal_x'] ) ) ) : 50;
		$out['mobile_focal_y']   = isset( $in['mobile_focal_y'] ) ? max( 0, min( 100, absint( $in['mobile_focal_y'] ) ) ) : 50;
		$out['mobile_align']     = ( isset( $in['mobile_align'] ) && in_array( $in['mobile_align'], array( 'left', 'center', 'right' ), true ) ) ? $in['mobile_align'] : '';
		$out['mobile_valign']    = ( isset( $in['mobile_valign'] ) && in_array( $in['mobile_valign'], array( 'top', 'middle', 'bottom' ), true ) ) ? $in['mobile_valign'] : '';
		$out['mobile_hide_text'] = isset( $in['mobile_hide_text'] ) ? self::to_bool( $in['mobile_hide_text'] ) : false;

		$pct = function ( $v, $def, $min = 0, $max = 100 ) {
			return is_numeric( $v ) ? max( $min, min( $max, round( (float) $v, 1 ) ) ) : $def;
		};
		$out['pos_mode']        = ( isset( $in['pos_mode'] ) && 'free' === $in['pos_mode'] ) ? 'free' : 'grid';
		$out['pos_x']           = $pct( isset( $in['pos_x'] ) ? $in['pos_x'] : null, $d['pos_x'] );
		$out['pos_y']           = $pct( isset( $in['pos_y'] ) ? $in['pos_y'] : null, $d['pos_y'] );
		$out['pos_w']           = $pct( isset( $in['pos_w'] ) ? $in['pos_w'] : null, $d['pos_w'], 10, 100 );
		$out['mobile_pos_mode'] = ( isset( $in['mobile_pos_mode'] ) && in_array( $in['mobile_pos_mode'], array( 'grid', 'free' ), true ) ) ? $in['mobile_pos_mode'] : '';
		$out['mobile_pos_x']    = $pct( isset( $in['mobile_pos_x'] ) ? $in['mobile_pos_x'] : null, $d['mobile_pos_x'] );
		$out['mobile_pos_y']    = $pct( isset( $in['mobile_pos_y'] ) ? $in['mobile_pos_y'] : null, $d['mobile_pos_y'] );
		$out['mobile_pos_w']    = $pct( isset( $in['mobile_pos_w'] ) ? $in['mobile_pos_w'] : null, $d['mobile_pos_w'], 10, 100 );

		$out['start'] = isset( $in['start'] ) ? self::sanitize_datetime( $in['start'] ) : '';
		$out['end']   = isset( $in['end'] ) ? self::sanitize_datetime( $in['end'] ) : '';

		$out['typo'] = isset( $in['typo'] ) ? self::sanitize_typo( $in['typo'] ) : array();

		$out['source']      = ( isset( $in['source'] ) && 'campaign' === $in['source'] ) ? 'campaign' : 'own';
		$out['campaign_id'] = isset( $in['campaign_id'] ) ? absint( $in['campaign_id'] ) : 0;
		if ( 'campaign' === $out['source'] && ! $out['campaign_id'] ) {
			$out['source'] = 'own';
		}

		// Collage + frame.
		$out['bg_mode']        = ( isset( $in['bg_mode'] ) && 'collage' === $in['bg_mode'] ) ? 'collage' : 'image';
		$out['collage_seam']   = ( isset( $in['collage_seam'] ) && in_array( $in['collage_seam'], array( 'hard', 'fade', 'blur' ), true ) ) ? $in['collage_seam'] : $d['collage_seam'];
		$out['collage_gap']    = isset( $in['collage_gap'] ) ? max( 0, min( 60, absint( $in['collage_gap'] ) ) ) : $d['collage_gap'];
		$out['collage_mobile'] = ( isset( $in['collage_mobile'] ) && in_array( $in['collage_mobile'], array( 'all', 'first3', 'first2', 'first1' ), true ) ) ? $in['collage_mobile'] : $d['collage_mobile'];
		$out['frame_width']    = isset( $in['frame_width'] ) ? max( 0, min( 80, absint( $in['frame_width'] ) ) ) : 0;
		$out['frame_radius']   = isset( $in['frame_radius'] ) ? max( 0, min( 80, absint( $in['frame_radius'] ) ) ) : 0;
		$out['collage']        = array();
		if ( isset( $in['collage'] ) && is_array( $in['collage'] ) ) {
			foreach ( array_slice( array_values( $in['collage'] ), 0, 5 ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
				$slot = array(
					'id'      => $id,
					'url'     => isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '',
					'width'   => isset( $item['width'] ) ? absint( $item['width'] ) : 0,
					'height'  => isset( $item['height'] ) ? absint( $item['height'] ) : 0,
					'focal_x' => isset( $item['focal_x'] ) ? max( 0, min( 100, absint( $item['focal_x'] ) ) ) : 50,
					'focal_y' => isset( $item['focal_y'] ) ? max( 0, min( 100, absint( $item['focal_y'] ) ) ) : 50,
				);
				// Separate focal point for the mobile crop; defaults to the desktop one.
				$slot['mfocal_x'] = isset( $item['mfocal_x'] ) && '' !== $item['mfocal_x'] ? max( 0, min( 100, absint( $item['mfocal_x'] ) ) ) : $slot['focal_x'];
				$slot['mfocal_y'] = isset( $item['mfocal_y'] ) && '' !== $item['mfocal_y'] ? max( 0, min( 100, absint( $item['mfocal_y'] ) ) ) : $slot['focal_y'];
				if ( $id ) {
					$src = wp_get_attachment_image_src( $id, 'full' );
					if ( $src ) {
						$slot['url']    = $src[0];
						$slot['width']  = (int) $src[1];
						$slot['height'] = (int) $src[2];
					} else {
						$slot['id'] = 0;
					}
				}
				if ( '' !== $slot['url'] ) {
					$out['collage'][] = $slot;
				}
			}
		}

		// Translations.
		$out['i18n'] = array();
		if ( isset( $in['i18n'] ) && is_array( $in['i18n'] ) ) {
			foreach ( $in['i18n'] as $lang => $fields ) {
				$lang = strtolower( preg_replace( '/[^a-zA-Z_-]/', '', (string) $lang ) );
				if ( '' === $lang || ! is_array( $fields ) ) {
					continue;
				}
				$clean = array();
				foreach ( self::translatable_fields() as $f ) {
					if ( ! isset( $fields[ $f ] ) ) {
						continue;
					}
					$v = $fields[ $f ];
					if ( 'text' === $f ) {
						$v = wp_kses( $v, $allowed );
					} elseif ( 'button_url' === $f || 'button2_url' === $f ) {
						$v = esc_url_raw( trim( $v ) );
					} else {
						$v = sanitize_text_field( $v );
					}
					if ( '' !== $v ) {
						$clean[ $f ] = $v;
					}
				}
				if ( $clean ) {
					$out['i18n'][ $lang ] = $clean;
				}
			}
		}

		return $out;
	}

	/* ---------- languages ---------- */

	/**
	 * Languages available for slide texts.
	 * Detected from Polylang / WPML; falls back to the "languages" setting; else the site locale only.
	 *
	 * @return array[] { slug, name, default }
	 */
	public static function get_languages() {
		$langs = array();

		if ( function_exists( 'pll_languages_list' ) ) {
			$slugs   = pll_languages_list( array( 'fields' => 'slug' ) );
			$names   = pll_languages_list( array( 'fields' => 'name' ) );
			$default = function_exists( 'pll_default_language' ) ? pll_default_language( 'slug' ) : '';
			foreach ( (array) $slugs as $i => $slug ) {
				$langs[] = array(
					'slug'    => $slug,
					'name'    => isset( $names[ $i ] ) ? $names[ $i ] : strtoupper( $slug ),
					'default' => $slug === $default,
				);
			}
		} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$active  = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
			$default = apply_filters( 'wpml_default_language', null );
			foreach ( (array) $active as $slug => $l ) {
				$langs[] = array(
					'slug'    => $slug,
					'name'    => isset( $l['native_name'] ) ? $l['native_name'] : strtoupper( $slug ),
					'default' => $slug === $default,
				);
			}
		}

		if ( empty( $langs ) ) {
			$settings = self::get_settings();
			$list     = array_filter( array_map( 'trim', explode( ',', $settings['languages'] ) ) );
			if ( empty( $list ) ) {
				$list = array( self::locale_slug( get_locale() ) );
			}
			foreach ( array_values( $list ) as $i => $slug ) {
				$langs[] = array(
					'slug'    => $slug,
					'name'    => strtoupper( $slug ),
					'default' => 0 === $i,
				);
			}
		}

		// Make sure exactly one default; put it first.
		$has_default = false;
		foreach ( $langs as $l ) {
			if ( $l['default'] ) {
				$has_default = true;
			}
		}
		if ( ! $has_default && $langs ) {
			$langs[0]['default'] = true;
		}
		usort(
			$langs,
			function ( $a, $b ) {
				return ( $b['default'] ? 1 : 0 ) - ( $a['default'] ? 1 : 0 );
			}
		);

		return apply_filters( 'wonom_slider_languages', $langs );
	}

	/**
	 * Current front-end language slug.
	 *
	 * @return string
	 */
	public static function current_language() {
		$lang = '';
		if ( function_exists( 'pll_current_language' ) ) {
			$lang = (string) pll_current_language( 'slug' );
		} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$lang = (string) apply_filters( 'wpml_current_language', null );
		}
		if ( '' === $lang ) {
			$lang = self::locale_slug( determine_locale() );
		}
		return apply_filters( 'wonom_slider_current_language', $lang );
	}

	/**
	 * "et_EE" → "et".
	 */
	public static function locale_slug( $locale ) {
		$locale = strtolower( (string) $locale );
		$parts  = preg_split( '/[_-]/', $locale );
		return $parts && '' !== $parts[0] ? $parts[0] : 'en';
	}

	/**
	 * Apply translations of the given language on top of the default texts.
	 *
	 * @param array  $slide Slide.
	 * @param string $lang  Language slug ('' = current).
	 * @return array
	 */
	public static function localize_slide( $slide, $lang = '' ) {
		if ( '' === $lang ) {
			$lang = self::current_language();
		}
		$langs = self::get_languages();
		if ( ! empty( $langs ) && $langs[0]['slug'] === $lang ) {
			return $slide; // default language uses base fields.
		}
		if ( empty( $slide['i18n'][ $lang ] ) || ! is_array( $slide['i18n'][ $lang ] ) ) {
			return $slide;
		}
		foreach ( $slide['i18n'][ $lang ] as $f => $v ) {
			if ( '' !== $v && in_array( $f, self::translatable_fields(), true ) ) {
				$slide[ $f ] = $v;
			}
		}
		return $slide;
	}

	/**
	 * Slides that should be shown right now.
	 *
	 * @return array[]
	 */
	public static function get_active_slides() {
		$now = self::now();
		$out = array();
		foreach ( self::get_slides() as $slide ) {
			if ( self::is_slide_visible( $slide, $now ) ) {
				$out[] = $slide;
			}
		}
		return $out;
	}

	/**
	 * Admin status of a slide: active | scheduled | expired | disabled | noimage.
	 *
	 * @param array $slide Slide.
	 * @return string
	 */
	/**
	 * A slide can be shown when it has an image or at least some text.
	 */
	public static function slide_has_content( $slide ) {
		if ( self::slide_is_collage( $slide ) ) {
			return true;
		}
		foreach ( array( 'image_url', 'heading', 'text', 'eyebrow', 'button_text' ) as $k ) {
			if ( ! empty( $slide[ $k ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Collage mode with at least one image.
	 */
	public static function slide_is_collage( $slide ) {
		return isset( $slide['bg_mode'] ) && 'collage' === $slide['bg_mode'] && ! empty( $slide['collage'] ) && is_array( $slide['collage'] );
	}

	/**
	 * Representative image (for thumbnails / aspect ratio): the single image or the first collage image.
	 */
	public static function slide_main_image( $slide ) {
		if ( self::slide_is_collage( $slide ) ) {
			$c = $slide['collage'][0];
			return array( 'id' => (int) $c['id'], 'url' => $c['url'], 'width' => (int) $c['width'], 'height' => (int) $c['height'] );
		}
		return array( 'id' => (int) $slide['image_id'], 'url' => $slide['image_url'], 'width' => (int) $slide['image_width'], 'height' => (int) $slide['image_height'] );
	}

	public static function slide_status( $slide ) {
		$now = self::now();
		if ( empty( $slide['enabled'] ) ) {
			return 'disabled';
		}
		if ( ! self::slide_has_content( $slide ) ) {
			return 'noimage';
		}
		if ( '' !== $slide['start'] && strtotime( $slide['start'] ) > $now ) {
			return 'scheduled';
		}
		if ( '' !== $slide['end'] && strtotime( $slide['end'] ) < $now ) {
			return 'expired';
		}
		return 'active';
	}

	/**
	 * Whether a slide is visible at a given site-local timestamp.
	 *
	 * @param array $slide Slide.
	 * @param int   $now   Timestamp.
	 * @return bool
	 */
	public static function is_slide_visible( $slide, $now ) {
		if ( empty( $slide['enabled'] ) || ! self::slide_has_content( $slide ) ) {
			return false;
		}
		if ( '' !== $slide['start'] && strtotime( $slide['start'] ) > $now ) {
			return false;
		}
		if ( '' !== $slide['end'] && strtotime( $slide['end'] ) < $now ) {
			return false;
		}
		return true;
	}

	/**
	 * Earliest future moment at which the visible set changes.
	 *
	 * @return int|null Site-local timestamp or null.
	 */
	public static function next_change_timestamp() {
		$now  = self::now();
		$next = null;
		foreach ( self::get_slides() as $slide ) {
			foreach ( array( 'start', 'end' ) as $k ) {
				if ( '' === $slide[ $k ] ) {
					continue;
				}
				$t = strtotime( $slide[ $k ] );
				if ( $t > $now && ( null === $next || $t < $next ) ) {
					$next = $t;
				}
			}
		}
		return $next;
	}

	/* ---------- helpers ---------- */

	/**
	 * Site-local "now" as a timestamp comparable with strtotime() of a local datetime string.
	 *
	 * @return int
	 */
	public static function now() {
		return (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	}

	public static function generate_id() {
		return 's' . substr( md5( uniqid( '', true ) ), 0, 8 );
	}

	public static function to_bool( $v ) {
		return (bool) filter_var( $v, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Accepts "auto" or "WxH" (e.g. 1920x660).
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	public static function sanitize_ratio( $v ) {
		$v = trim( (string) $v );
		if ( 'auto' === $v || '' === $v ) {
			return 'auto';
		}
		if ( preg_match( '/^(\d{1,5})\s*[x:\/]\s*(\d{1,5})$/i', $v, $m ) && (int) $m[1] > 0 && (int) $m[2] > 0 ) {
			return (int) $m[1] . 'x' . (int) $m[2];
		}
		return 'auto';
	}

	/**
	 * Accepts "YYYY-MM-DDTHH:MM" or "YYYY-MM-DD HH:MM" (site timezone). Returns normalised or ''.
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	public static function sanitize_datetime( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return '';
		}
		$v = str_replace( ' ', 'T', $v );
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})(:\d{2})?$/', $v, $m ) ) {
			$ts = strtotime( $m[1] . ' ' . $m[2] );
			return $ts ? $m[1] . 'T' . $m[2] : '';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
			return $v . 'T00:00';
		}
		return '';
	}

	/**
	 * Minimal CSS sanitiser.
	 *
	 * @param string $css Raw CSS.
	 * @return string
	 */
	public static function sanitize_css( $css ) {
		$css = wp_strip_all_tags( (string) $css );
		$css = str_ireplace( array( '</style', '<script', 'expression(', 'javascript:' ), '', $css );
		return trim( $css );
	}
}
