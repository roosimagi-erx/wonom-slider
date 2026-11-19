<?php
/**
 * Front-end rendering: shortcode, template tag, assets, preview endpoint.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Frontend {

	/** @var bool Whether assets have been requested on this page. */
	private static $assets_needed = false;

	/** @var array|null Slides being rendered (so the font stylesheet covers per-slide fonts). */
	private static $fonts_slides = null;

	public static function init() {
		add_shortcode( 'wonom_slider', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_print_assets' ), 1 );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_preview' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'script_tag' ), 10, 2 );
	}

	/**
	 * Ask speed plugins (FlyingPress, WP Rocket, Cloudflare Rocket Loader…) not to delay or
	 * rewrite the slider script, otherwise autoplay waits for the first user interaction.
	 */
	public static function script_tag( $tag, $handle ) {
		if ( 'wonom-slider' === $handle && false === strpos( $tag, 'data-no-delay' ) ) {
			$tag = str_replace( '<script ', '<script data-no-optimize="1" data-no-defer="1" data-no-delay="1" data-cfasync="false" data-no-minify="1" ', $tag );
		}
		return $tag;
	}

	/**
	 * Register (but do not yet enqueue) front-end assets.
	 */
	public static function register_assets() {
		wp_register_style( 'wonom-slider', WONOM_SLIDER_URL . 'assets/public/slider.css', array(), WONOM_SLIDER_VERSION );
		wp_register_script( 'wonom-slider', WONOM_SLIDER_URL . 'assets/public/slider.js', array(), WONOM_SLIDER_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}

	/**
	 * Enqueue assets on demand (works also when the shortcode is rendered late, e.g. in widgets).
	 */
	public static function enqueue_assets() {
		self::$assets_needed = true;
		if ( ! wp_style_is( 'wonom-slider', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'wonom-slider' );
		wp_enqueue_script( 'wonom-slider' );

		$settings = Wonom_Slider_Data::get_settings();
		if ( ! empty( $settings['custom_css'] ) ) {
			wp_add_inline_style( 'wonom-slider', $settings['custom_css'] );
		}
		$fonts = Wonom_Slider_Data::font_stylesheet_url( $settings, self::$fonts_slides );
		if ( $fonts ) {
			wp_enqueue_style( 'wonom-slider-fonts', $fonts, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}

	public static function maybe_print_assets() {
		if ( self::$assets_needed && ! wp_script_is( 'wonom-slider', 'done' ) ) {
			wp_print_styles( 'wonom-slider' );
			wp_print_scripts( 'wonom-slider' );
		}
	}

	/**
	 * Shortcode handler: [wonom_slider]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'class' => '',
			),
			$atts,
			'wonom_slider'
		);
		return self::render( $atts );
	}

	/**
	 * Gutenberg block (dynamic, renders the shortcode output).
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type(
			'wonom/slider',
			array(
				'api_version'     => 2,
				'title'           => __( 'Wonom Slider', 'wonom-slider' ),
				'description'     => __( 'Displays the Wonom hero slider.', 'wonom-slider' ),
				'category'        => 'media',
				'icon'            => 'images-alt2',
				'render_callback' => array( __CLASS__, 'render' ),
				'editor_script'   => 'wonom-slider-block',
				'supports'        => array( 'align' => array( 'wide', 'full' ) ),
			)
		);
		wp_register_script(
			'wonom-slider-block',
			WONOM_SLIDER_URL . 'assets/admin/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-server-side-render' ),
			WONOM_SLIDER_VERSION,
			true
		);
	}

	/**
	 * Build the slider HTML.
	 *
	 * @param array $args   { class, slides, settings } – slides/settings override stored data (used by preview).
	 * @return string
	 */
	public static function render( $args = array() ) {
		$args     = is_array( $args ) ? $args : array();
		$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : Wonom_Slider_Data::get_settings();
		// A real page gets the visible slides plus the ones that start soon (hidden, with their start
		// and end): the browser switches them at the exact moment, also on a cached page. Editor
		// previews pass their own slides and stay as they are.
		$timed    = ! ( isset( $args['slides'] ) && is_array( $args['slides'] ) );
		$slides   = $timed ? Wonom_Slider_Data::get_window_slides() : $args['slides'];

		/**
		 * Filter the slides about to be rendered.
		 *
		 * @param array $slides
		 * @param array $settings
		 */
		$slides = apply_filters( 'wonom_slider_slides', $slides, $settings );

		if ( empty( $slides ) ) {
			return '';
		}

		// A real shop page shows the slider: remember it, so saving purges exactly the slider pages.
		if ( ! isset( $args['slides'] ) && ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && did_action( 'wp' ) && ! is_preview() && is_singular() ) {
			Wonom_Slider_Cache::remember_page( get_queried_object_id() );
		}

		$lang = isset( $args['lang'] ) ? sanitize_key( $args['lang'] ) : Wonom_Slider_Data::current_language();
		foreach ( $slides as $k => $slide ) {
			if ( class_exists( 'Wonom_Slider_Campaigns' ) ) {
				$slide = Wonom_Slider_Campaigns::apply( $slide ); // unsaved slides from the editor stage.
			}
			$slides[ $k ] = Wonom_Slider_Data::localize_slide( $slide, $lang );
		}

		self::$fonts_slides = $slides;
		self::enqueue_assets();

		// WoodMart swaps every attachment image for a lazy placeholder (lazy.svg + data-src) that only its
		// own JavaScript resolves. The hero image is the LCP element and the editor preview has no theme JS,
		// so lazy loading is switched off for the slider markup (native loading="lazy" is kept).
		$wd_lazy = function_exists( 'woodmart_lazy_loading_deinit' ) && function_exists( 'woodmart_lazy_loading_init' );
		if ( $wd_lazy ) {
			woodmart_lazy_loading_deinit( true );
		}

		$slides = array_values( $slides );
		$total  = count( $slides );   // slides in the markup
		$live   = array();            // the ones visible right now
		$timing = false;              // does any slide start or end at a set time?
		foreach ( $slides as $slide ) {
			if ( ! isset( $slide['_live'] ) || $slide['_live'] ) {
				$live[] = $slide;
			}
			if ( $timed && ( ! empty( $slide['_start'] ) || ! empty( $slide['_end'] ) ) ) {
				$timing = true;
			}
		}
		$count   = count( $live );
		$uid     = 'wonom-slider-' . wp_unique_id();
		$classes = array( 'wonom-slider', 'is-' . $settings['transition'] );
		if ( ! empty( $args['class'] ) ) {
			$classes[] = sanitize_html_class( $args['class'] );
		}
		if ( ! empty( $args['className'] ) ) { // block editor.
			$classes[] = esc_attr( $args['className'] );
		}
		if ( ! empty( $args['align'] ) ) {
			$classes[] = 'align' . sanitize_html_class( $args['align'] );
		}
		if ( ! empty( $settings['ken_burns'] ) ) {
			$classes[] = 'has-ken-burns';
		}
		$classes[] = 'dots-' . $settings['dots_position'];
		$classes[] = 'm-dots-' . ( 'same' === $settings['dots_mobile'] ? $settings['dots_position'] : $settings['dots_mobile'] );
		if ( 'hide' === $settings['arrows_mobile'] ) {
			$classes[] = 'm-arrows-hide';
		}
		if ( $count < 2 ) {
			$classes[] = 'is-single';
		}

		$config = array(
			'autoplay'     => (bool) $settings['autoplay'] && $total > 1,
			'interval'     => (int) $settings['interval'],
			'speed'        => (int) $settings['speed'],
			'transition'   => $settings['transition'],
			'loop'         => (bool) $settings['loop'],
			'pauseOnHover' => (bool) $settings['pause_on_hover'],
			'progress'     => (bool) $settings['show_progress'],
		);

		// The slider ratio follows the first slide that actually has an image (text-only slides carry no dimensions).
		// Editor previews pass 'ratio_slides' = the slides that are live in the shop, so a single slide or a
		// preview that includes scheduled/expired slides still gets exactly the shop's slider shape.
		$ratio_pool  = ! empty( $args['ratio_slides'] ) && is_array( $args['ratio_slides'] ) ? array_values( $args['ratio_slides'] ) : ( $live ? $live : $slides );
		$ratio_slide = $live ? $live[0] : $slides[0];
		foreach ( $ratio_pool as $candidate ) {
			$main = Wonom_Slider_Data::slide_main_image( $candidate );
			if ( $main['width'] && $main['height'] ) {
				$ratio_slide = $candidate;
				break;
			}
		}
		$style = self::inline_style( $settings, $ratio_slide );

		ob_start();
		// The stylesheet's mobile rules use 767px; a custom breakpoint needs its own media block.
		if ( 768 !== (int) $settings['mobile_breakpoint'] ) {
			echo '<style>' . self::mobile_css( '#' . $uid, (int) $settings['mobile_breakpoint'] ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<section id="<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>" data-wonom-slider='<?php echo esc_attr( wp_json_encode( $config ) ); ?>'<?php echo $timing ? ' data-ws-now="' . (int) time() . '" data-ws-clock="' . esc_url( WONOM_SLIDER_URL . 'assets/public/slider.css?ver=' . WONOM_SLIDER_VERSION ) . '"' : ''; ?><?php echo $count ? '' : ' hidden'; ?> aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Featured', 'wonom-slider' ); ?>">
			<div class="wonom-slider__track">
				<?php
				$pos = 0; // position among the slides visible now; a hidden (upcoming) slide is never "first".
				foreach ( $slides as $slide ) {
					$is_live = ! isset( $slide['_live'] ) || $slide['_live'];
					$main = Wonom_Slider_Data::slide_main_image( $slide );
					if ( $timing && $main['width'] && $main['height'] ) {
						// The slider takes its shape from the first slide with an image; when the
						// visible set changes the script needs the shape of the new first one.
						$slide['_ratio'] = implode( '|', self::ratio_pair( $settings, $slide ) );
					}
					echo self::render_slide( $slide, $is_live ? $pos : -1, $count, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$pos += $is_live ? 1 : 0;
				}
				?>
			</div>
			<?php if ( $total > 1 ) : ?>
				<?php if ( ! empty( $settings['show_arrows'] ) ) : ?>
					<button type="button" class="wonom-slider__arrow wonom-slider__arrow--prev" data-dir="-1" aria-label="<?php esc_attr_e( 'Previous slide', 'wonom-slider' ); ?>">
						<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
					<button type="button" class="wonom-slider__arrow wonom-slider__arrow--next" data-dir="1" aria-label="<?php esc_attr_e( 'Next slide', 'wonom-slider' ); ?>">
						<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
				<?php endif; ?>
				<?php if ( ! empty( $settings['show_dots'] ) ) : ?>
					<div class="wonom-slider__dots" role="tablist" data-label="<?php /* translators: %d slide number */ echo esc_attr( __( 'Go to slide %d', 'wonom-slider' ) ); ?>">
						<?php for ( $i = 0; $i < $count; $i++ ) : ?>
							<button type="button" class="wonom-slider__dot<?php echo 0 === $i ? ' is-active' : ''; ?>" data-index="<?php echo (int) $i; ?>" role="tab" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d slide number */ __( 'Go to slide %d', 'wonom-slider' ), $i + 1 ) ); ?>"></button>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $settings['show_progress'] ) ) : ?>
					<div class="wonom-slider__progress" aria-hidden="true"><span></span></div>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
		if ( $timing ) {
			echo self::timing_script( $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$html = ob_get_clean();

		if ( $wd_lazy ) {
			woodmart_lazy_loading_init();
		}

		/**
		 * Filter final slider markup.
		 */
		return apply_filters( 'wonom_slider_html', $html, $slides, $settings );
	}

	/**
	 * Single slide markup.
	 */
	private static function render_slide( $slide, $index, $count, $settings ) {
		$is_first = 0 === $index;
		$upcoming = $index < 0; // rendered ahead of its start: hidden until the browser's clock reaches it.
		// Timing for the script: start / end (UTC seconds, end exclusive), group (one campaign slide
		// at a time) and the slider shape this slide gives when it is the first one.
		$timing = '';
		foreach ( array( '_start' => 'start', '_end' => 'end', '_group' => 'group', '_ratio' => 'ratio' ) as $key => $name ) {
			if ( ! empty( $slide[ $key ] ) ) {
				$timing .= ' data-ws-' . $name . '="' . esc_attr( $slide[ $key ] ) . '"';
			}
		}
		$timing .= $upcoming ? ' hidden' : '';
		$has_link = ! empty( $slide['link_whole_slide'] ) && ! empty( $slide['button_url'] );
		$target   = ! empty( $slide['button_new_tab'] ) ? ' target="_blank" rel="noopener"' : '';
		$bp       = (int) $settings['mobile_breakpoint'] - 1;
		$tag      = in_array( $settings['heading_tag'], array( 'h1', 'h2', 'h3', 'p' ), true ) ? $settings['heading_tag'] : 'h2';
		if ( 'h1' === $tag && ! $is_first ) {
			$tag = 'h2'; // Never output more than one H1.
		}

		// Text block position: grid (align/valign) or free (% coordinates); mobile may inherit or override.
		$m_mode = $slide['mobile_pos_mode'] ? $slide['mobile_pos_mode'] : $slide['pos_mode'];
		$m_x    = $slide['mobile_pos_mode'] ? $slide['mobile_pos_x'] : $slide['pos_x'];
		$m_y    = $slide['mobile_pos_mode'] ? $slide['mobile_pos_y'] : $slide['pos_y'];
		$m_w    = $slide['mobile_pos_mode'] ? $slide['mobile_pos_w'] : $slide['pos_w'];

		$slide_style = sprintf(
			'--ws-text:%1$s;--ws-btn-bg:%2$s;--ws-btn-color:%3$s;--ws-overlay:%4$s;--ws-overlay-color:%5$s;--ws-focal:%6$d%% %7$d%%;--ws-focal-m:%8$d%% %9$d%%;--ws-x:%10$s%%;--ws-y:%11$s%%;--ws-w:%12$s%%;--ws-mx:%13$s%%;--ws-my:%14$s%%;--ws-mw:%15$s%%;--ws-bg:%16$s;',
			esc_attr( $slide['text_color'] ),
			esc_attr( $slide['button_bg'] ),
			esc_attr( $slide['button_color'] ),
			esc_attr( $slide['overlay'] / 100 ),
			esc_attr( $slide['overlay_color'] ),
			(int) $slide['focal_x'],
			(int) $slide['focal_y'],
			(int) $slide['mobile_focal_x'],
			(int) $slide['mobile_focal_y'],
			(float) $slide['pos_x'],
			(float) $slide['pos_y'],
			(float) $slide['pos_w'],
			(float) $m_x,
			(float) $m_y,
			(float) $m_w,
			esc_attr( $slide['bg_color'] ? $slide['bg_color'] : 'transparent' )
		);
		$slide_style .= self::bg_gradient_style( $slide );
		$slide_style .= self::typo_style( isset( $slide['typo'] ) ? $slide['typo'] : array() );

		$classes = array(
			'wonom-slide',
			'align-' . $slide['align'],
			'valign-' . $slide['valign'],
			'm-align-' . ( $slide['mobile_align'] ? $slide['mobile_align'] : $slide['align'] ),
			'm-valign-' . ( $slide['mobile_valign'] ? $slide['mobile_valign'] : $slide['valign'] ),
			'pos-' . $slide['pos_mode'],
			'm-pos-' . $m_mode,
			'type-' . $slide['type'],
		);
		if ( ! empty( $slide['mobile_hide_text'] ) ) {
			$classes[] = 'm-hide-text';
		}
		if ( (int) $slide['frame_width'] > 0 || (int) $slide['frame_radius'] > 0 ) {
			$classes[]    = 'has-frame';
			$slide_style .= '--ws-frame-d:' . (int) $slide['frame_width'] . 'px;--ws-frame-r:' . (int) $slide['frame_radius'] . 'px;';
		}
		$is_collage = Wonom_Slider_Data::slide_is_collage( $slide );
		if ( $is_collage ) {
			$classes[] = 'is-collage';
		}
		if ( $is_first ) {
			$classes[] = 'is-active';
		}

		$has_content = '' !== $slide['heading'] || '' !== $slide['text'] || '' !== $slide['eyebrow'] || ( '' !== $slide['button_text'] && '' !== $slide['button_url'] ) || '' !== $slide['badge'];

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo $slide_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" data-id="<?php echo esc_attr( $slide['id'] ); ?>"<?php echo $timing; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( $upcoming ? '' : sprintf( '%d / %d', $index + 1, $count ) ); ?>"<?php echo $is_first ? '' : ' aria-hidden="true"'; ?>>
			<?php if ( $has_link ) : ?>
				<a class="wonom-slide__link" href="<?php echo esc_url( $slide['button_url'] ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php echo esc_attr( $slide['heading'] ? $slide['heading'] : $slide['name'] ); ?>"></a>
			<?php endif; ?>
			<?php if ( $is_collage ) : ?>
				<?php
				$items   = array_values( $slide['collage'] );
				$n       = count( $items );
				$contain = isset( $slide['collage_fit'] ) && 'contain' === $slide['collage_fit'];
				$unit    = self::collage_unit( $items );
				$vis_d   = self::collage_visible( $slide, false );
				$vis_m   = self::collage_visible( $slide, true );
				$nb_d    = self::collage_neighbours( $vis_d );
				$nb_m    = self::collage_neighbours( $vis_m );
				$tot_d   = self::collage_weight_sum( $vis_d );
				$tot_m   = self::collage_weight_sum( $vis_m );
				$cstyle  = '--ws-cgap:' . (int) $slide['collage_gap'] . 'px;--ws-cfade:' . (int) ( isset( $slide['collage_fade'] ) ? $slide['collage_fade'] : 20 ) . '%';
				if ( $contain ) {
					// Whole photos: the row is as tall as the slide (or narrower than it) – CSS needs the
					// sum of the slot shapes and the total gap, for desktop and for the mobile subset.
					$gap    = (int) $slide['collage_gap'];   // whole photos never overlap, the gap applies with every seam
					$cstyle .= sprintf(
						';--ws-cr:%1$s;--ws-cr-m:%2$s;--ws-gaps:%3$dpx;--ws-gaps-m:%4$dpx',
						self::num( self::collage_ratio_sum( $vis_d, $unit, true ) ),
						self::num( self::collage_ratio_sum( $vis_m, $unit, true ) ),
						$gap * max( 0, count( $vis_d ) - 1 ),
						$gap * max( 0, count( $vis_m ) - 1 )
					);
				}
				$photo_no = 0;
				?>
			<div class="wonom-slide__media wonom-collage cols-<?php echo (int) $n; ?> seam-<?php echo esc_attr( $slide['collage_seam'] ); ?> m-<?php echo esc_attr( $slide['collage_mobile'] ); ?> fit-<?php echo $contain ? 'contain' : 'cover'; ?>" style="<?php echo esc_attr( $cstyle ); ?>">
				<?php if ( $contain ) : ?><div class="wonom-collage__row"><?php endif; ?>
				<?php foreach ( $items as $ci => $c ) : ?>
					<?php
					$is_color = Wonom_Slider_Data::is_color_slot( $c );
					$w_d      = self::slot_weight( $c );
					$w_m      = isset( $vis_m[ $ci ] ) ? $vis_m[ $ci ]['_w'] : 0;
					// Per-slot layout vars: width weight (desktop/mobile), shape for "whole photos",
					// and which edges fade (p = next to a photo, c = next to a colour field).
					$istyle = '--ws-cw:' . self::num( $w_d ) . ( $w_m && $w_m !== $w_d ? ';--ws-cwm:' . self::num( $w_m ) : '' );
					if ( $contain ) {
						$istyle .= ';--ws-ir:' . self::num( self::slot_ratio( $c, $unit, $w_d ) );
						if ( $is_color && $w_m && $w_m !== $w_d ) {
							$istyle .= ';--ws-irm:' . self::num( self::slot_ratio( $c, $unit, $w_m ) );
						}
					}
					foreach ( array( 'd' => $nb_d, 'm' => $nb_m ) as $dev => $nb ) {
						if ( ! $is_color && isset( $nb[ $ci ] ) ) {
							foreach ( $nb[ $ci ] as $flag => $on ) {
								$istyle .= $on ? ';--ws-' . $dev . $flag . ':1' : '';
							}
						}
					}
					$iclass = 'wonom-collage__item ' . ( $is_color ? 'is-color' : 'is-photo' ) . ( isset( $vis_m[ $ci ] ) ? '' : ' is-mhide' );
					if ( $is_color ) :
						?>
					<div class="<?php echo esc_attr( $iclass ); ?>" style="<?php echo esc_attr( $istyle ); ?>"></div>
						<?php
						continue;
					endif;
					$istyle .= ';--ws-cf:' . (int) $c['focal_x'] . '% ' . (int) $c['focal_y'] . '%;--ws-cfm:' . (int) ( isset( $c['mfocal_x'] ) ? $c['mfocal_x'] : $c['focal_x'] ) . '% ' . (int) ( isset( $c['mfocal_y'] ) ? $c['mfocal_y'] : $c['focal_y'] ) . '%';
					$eager  = $is_first && $photo_no < 2;
					$vw_d   = max( 10, (int) round( 100 * $w_d / max( 0.5, $tot_d ) ) ) . 'vw';
					$vw_m   = $w_m ? max( 10, (int) round( 100 * $w_m / max( 0.5, $tot_m ) ) ) . 'vw' : '1px';
					$attr   = array(
						'class'    => 'wonom-collage__img',
						'alt'      => 0 === $photo_no ? ( $slide['alt'] ? $slide['alt'] : $slide['heading'] ) : '',
						'loading'  => $eager ? 'eager' : 'lazy',
						'decoding' => 'async',
						'sizes'    => '(max-width: ' . (int) $bp . 'px) ' . $vw_m . ', ' . $vw_d,
					);
					if ( $is_first && 0 === $photo_no ) {
						$attr['fetchpriority'] = 'high';
					}
					++$photo_no;
					if ( ! empty( $c['id'] ) ) {
						$img_html = wp_get_attachment_image( $c['id'], 'large', false, $attr );
					} else {
						$img_html = sprintf(
							'<img src="%s" alt="%s" class="wonom-collage__img" loading="%s" decoding="async">',
							esc_url( $c['url'] ),
							esc_attr( $attr['alt'] ),
							esc_attr( $attr['loading'] )
						);
					}
					?>
					<div class="<?php echo esc_attr( $iclass ); ?>" style="<?php echo esc_attr( $istyle ); ?>">
						<?php echo $is_first ? $img_html : self::lazy_markup( $img_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endforeach; ?>
				<?php if ( $contain ) : ?></div><?php endif; ?>
			</div>
			<div class="wonom-slide__overlay" aria-hidden="true"></div>
			<?php elseif ( ! empty( $slide['image_url'] ) ) : ?>
			<?php ob_start(); ?>
			<picture class="wonom-slide__media">
				<?php if ( ! empty( $slide['mobile_image_url'] ) ) : ?>
					<source media="(max-width: <?php echo (int) $bp; ?>px)" srcset="<?php echo esc_url( $slide['mobile_image_url'] ); ?>"<?php echo $slide['mobile_image_width'] ? ' width="' . (int) $slide['mobile_image_width'] . '" height="' . (int) $slide['mobile_image_height'] . '"' : ''; ?>>
				<?php endif; ?>
				<?php
				$img_attr = array(
					'class'    => 'wonom-slide__img',
					'alt'      => $slide['alt'] ? $slide['alt'] : $slide['heading'],
					'loading'  => $is_first ? 'eager' : 'lazy',
					'decoding' => 'async',
				);
				if ( $is_first ) {
					$img_attr['fetchpriority'] = 'high';
				}
				if ( $slide['image_id'] ) {
					echo wp_get_attachment_image( $slide['image_id'], 'full', false, $img_attr );
				} else {
					printf(
						'<img src="%s" alt="%s" class="wonom-slide__img" loading="%s" decoding="async"%s%s>',
						esc_url( $slide['image_url'] ),
						esc_attr( $img_attr['alt'] ),
						esc_attr( $img_attr['loading'] ),
						$is_first ? ' fetchpriority="high"' : '',
						$slide['image_width'] ? ' width="' . (int) $slide['image_width'] . '" height="' . (int) $slide['image_height'] . '"' : ''
					);
				}
				?>
			</picture>
			<?php
			$picture = ob_get_clean();
			// Only the first slide loads its image right away. The others sit in the viewport
			// (stacked, transparent), so loading="lazy" would not stop the browser: their
			// sources are held in data-* attributes and resolved by the script just in time.
			echo $is_first ? $picture : self::lazy_markup( $picture ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<div class="wonom-slide__overlay" aria-hidden="true"></div>
			<?php endif; ?>
			<?php if ( $has_content ) : ?>
				<div class="wonom-slide__content">
					<div class="wonom-slide__inner">
						<?php if ( '' !== $slide['badge'] ) : ?>
							<span class="wonom-slide__badge"><?php echo esc_html( $slide['badge'] ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $slide['eyebrow'] ) : ?>
							<p class="wonom-slide__eyebrow"><?php echo esc_html( $slide['eyebrow'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $slide['heading'] ) : ?>
							<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="wonom-slide__heading"><?php echo esc_html( $slide['heading'] ); ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php endif; ?>
						<?php if ( '' !== $slide['text'] ) : ?>
							<p class="wonom-slide__text"><?php echo wp_kses_post( $slide['text'] ); ?></p>
						<?php endif; ?>
						<?php if ( ( '' !== $slide['button_text'] && '' !== $slide['button_url'] ) || ( '' !== $slide['button2_text'] && '' !== $slide['button2_url'] ) ) : ?>
							<div class="wonom-slide__actions">
								<?php if ( '' !== $slide['button_text'] && '' !== $slide['button_url'] ) : ?>
									<a class="wonom-slide__button" href="<?php echo esc_url( $slide['button_url'] ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $slide['button_text'] ); ?></a>
								<?php endif; ?>
								<?php if ( '' !== $slide['button2_text'] && '' !== $slide['button2_url'] ) : ?>
									<a class="wonom-slide__button wonom-slide__button--secondary" href="<?php echo esc_url( $slide['button2_url'] ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $slide['button2_text'] ); ?></a>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Per-slide typography overrides as CSS custom properties (only the keys that are set).
	 */
	public static function typo_style( $typo ) {
		if ( empty( $typo ) || ! is_array( $typo ) ) {
			return '';
		}
		$map = array(
			'font_heading'        => array( '--ws-font-h', 'font' ),
			'font_text'           => array( '--ws-font-t', 'font' ),
			'heading_size'        => array( '--ws-h-size', 'px' ),
			'heading_size_mobile' => array( '--ws-h-size-mobile', 'px' ),
			'text_size'           => array( '--ws-t-size', 'px' ),
			'text_size_mobile'    => array( '--ws-t-size-mobile', 'px' ),
			'heading_weight'      => array( '--ws-h-weight', 'int' ),
			'heading_uppercase'   => array( '--ws-h-transform', 'bool' ),
			'heading_spacing'     => array( '--ws-h-spacing', 'em100' ),
			'gap'                 => array( '--ws-gap', 'px' ),
			'gap_button'          => array( '--ws-gap-btn', 'px' ),
			'button_radius'       => array( '--ws-btn-radius', 'px' ),
			'eyebrow_size'        => array( '--ws-e-size', 'px' ),
			'eyebrow_size_mobile' => array( '--ws-e-size-mobile', 'px' ),
			'badge_font'          => array( '--ws-font-b', 'font' ),
			'badge_size'          => array( '--ws-b-size', 'px' ),
			'badge_size_mobile'   => array( '--ws-b-size-mobile', 'px' ),
			'badge_weight'        => array( '--ws-b-weight', 'int' ),
			'badge_uppercase'     => array( '--ws-b-transform', 'bool' ),
			'badge_spacing'       => array( '--ws-b-spacing', 'em100' ),
			'badge_bg'            => array( '--ws-b-bg', 'color' ),
			'badge_color'         => array( '--ws-b-color', 'color' ),
			'badge_pad'           => array( '--ws-b-pad', 'px' ),
			'badge_radius'        => array( '--ws-b-radius', 'px' ),
		);
		$out = '';
		foreach ( $map as $k => $def ) {
			if ( ! isset( $typo[ $k ] ) || '' === $typo[ $k ] ) {
				continue;
			}
			$v = $typo[ $k ];
			switch ( $def[1] ) {
				case 'font':
					$v = Wonom_Slider_Data::font_css( $v );
					break;
				case 'px':
					$v = (int) $v . 'px';
					break;
				case 'int':
					$v = (int) $v;
					break;
				case 'bool':
					$v = $v ? 'uppercase' : 'none';
					break;
				case 'em100':
					$v = ( (int) $v / 100 ) . 'em';
					break;
				case 'color':
					$v = sanitize_hex_color( $v );
					if ( ! $v ) {
						continue 2;
					}
					break;
			}
			$out .= $def[0] . ':' . esc_attr( $v ) . ';';
		}
		return $out;
	}

	/**
	 * CSS custom properties for the slider wrapper.
	 */
	/** Width/height of one collage photo (portrait 3:4 when its size is unknown). */
	public static function photo_ratio( $c ) {
		return ( ! empty( $c['width'] ) && ! empty( $c['height'] ) ) ? $c['width'] / $c['height'] : 0.75;
	}

	/** Gradient background (two colours) as --ws-bgi; empty for a plain colour. */
	public static function bg_gradient_style( $slide ) {
		$dirs = array( 'right' => '90deg', 'bottom' => '180deg', 'br' => '135deg', 'tr' => '45deg' );
		$dir  = isset( $slide['bg_grad'] ) ? $slide['bg_grad'] : '';
		$c1   = ! empty( $slide['bg_color'] ) ? sanitize_hex_color( $slide['bg_color'] ) : '';
		$c2   = ! empty( $slide['bg_color2'] ) ? sanitize_hex_color( $slide['bg_color2'] ) : '';
		if ( ! isset( $dirs[ $dir ] ) || ! $c1 || ! $c2 ) {
			return '';
		}
		return '--ws-bgi:linear-gradient(' . $dirs[ $dir ] . ',' . $c1 . ',' . $c2 . ');';
	}

	/** Width of a collage slot in "photos" (1 = one photo's share). */
	public static function slot_weight( $c ) {
		return isset( $c['w'] ) && (float) $c['w'] > 0 ? (float) $c['w'] : 1.0;
	}

	/** Shape of one "photo" for colour fields: the average shape of the collage photos. */
	public static function collage_unit( $items ) {
		$sum = 0;
		$n   = 0;
		foreach ( $items as $c ) {
			if ( ! Wonom_Slider_Data::is_color_slot( $c ) ) {
				$sum += self::photo_ratio( $c );
				++$n;
			}
		}
		return $n ? $sum / $n : 0.75;
	}

	/** Shape (width/height) of a slot: a photo keeps its own shape, a colour field is $w "photos" wide. */
	public static function slot_ratio( $c, $unit, $w ) {
		return Wonom_Slider_Data::is_color_slot( $c ) ? $w * $unit : self::photo_ratio( $c );
	}

	/**
	 * Slots shown on desktop or on mobile, keyed by their position, each with its width '_w'.
	 * Mobile follows collage_mobile: first N slots, all, or per slot (custom: wm 0 = hidden).
	 */
	public static function collage_visible( $slide, $mobile ) {
		$items = array_values( isset( $slide['collage'] ) && is_array( $slide['collage'] ) ? $slide['collage'] : array() );
		$mode  = isset( $slide['collage_mobile'] ) ? $slide['collage_mobile'] : 'all';
		$limit = array( 'first3' => 3, 'first2' => 2, 'first1' => 1 );
		$out   = array();
		foreach ( $items as $i => $c ) {
			$w = self::slot_weight( $c );
			if ( $mobile ) {
				if ( 'custom' === $mode ) {
					$wm = isset( $c['wm'] ) ? (float) $c['wm'] : -1.0;
					if ( 0.0 === $wm ) {
						continue;
					}
					if ( $wm > 0 ) {
						$w = $wm;
					}
				} elseif ( isset( $limit[ $mode ] ) && $i >= $limit[ $mode ] ) {
					continue;
				}
			}
			$c['_w']   = $w;
			$out[ $i ] = $c;
		}
		if ( $mobile && ! $out ) {
			return self::collage_visible( $slide, false ); // everything hidden on mobile: show the desktop row
		}
		return $out;
	}

	/** For each visible photo: is its left neighbour a photo (pl) or a colour field (cl), is its right one a colour field (cr). */
	public static function collage_neighbours( $visible ) {
		$keys = array_keys( $visible );
		$out  = array();
		foreach ( $keys as $p => $k ) {
			$prev      = $p > 0 ? $visible[ $keys[ $p - 1 ] ] : null;
			$next      = $p < count( $keys ) - 1 ? $visible[ $keys[ $p + 1 ] ] : null;
			$out[ $k ] = array(
				'pl' => $prev && ! Wonom_Slider_Data::is_color_slot( $prev ),
				'cl' => $prev && Wonom_Slider_Data::is_color_slot( $prev ),
				'cr' => $next && Wonom_Slider_Data::is_color_slot( $next ),
			);
		}
		return $out;
	}

	public static function collage_weight_sum( $visible ) {
		$sum = 0;
		foreach ( $visible as $c ) {
			$sum += $c['_w'];
		}
		return $sum;
	}

	/**
	 * Shape of the row the visible slots form side by side. Photos count with their own shape
	 * (× their width when cropped to fill), colour fields as w × the average photo shape.
	 */
	public static function collage_ratio_sum( $visible, $unit, $contain ) {
		$sum = 0;
		foreach ( $visible as $c ) {
			$sum += Wonom_Slider_Data::is_color_slot( $c ) ? $c['_w'] * $unit : self::photo_ratio( $c ) * ( $contain ? 1 : $c['_w'] );
		}
		return $sum > 0 ? $sum : 0.75;
	}

	/** Locale-independent number for CSS. */
	private static function num( $v ) {
		return rtrim( rtrim( number_format( (float) $v, 4, '.', '' ), '0' ), '.' );
	}

	/**
	 * Tiny inline script right after a slider that has timed slides. A cached page may be older
	 * than a start or end: before anything is painted it hides what is over, shows what has
	 * started and loads the image of the slide that is first now. slider.js does the same and
	 * keeps watching the clock; this only removes the flash of an outdated slide.
	 */
	private static function timing_script( $uid ) {
		$js = '(function(){try{var r=document.getElementById(' . wp_json_encode( $uid ) . ');if(!r)return;'
			. 'var n=Math.max(Date.now()/1000,+r.getAttribute("data-ws-now")||0),a=[].slice.call(r.querySelectorAll(".wonom-slide")),b={},v=[];'
			. 'function g(e,k){return +e.getAttribute("data-ws-"+k)||0}'
			. 'a.forEach(function(e){var s=g(e,"start"),x=g(e,"end"),q=e.getAttribute("data-ws-group");e._on=(!s||n>=s)&&(!x||n<x);if(e._on&&q&&(!b[q]||s>g(b[q],"start")))b[q]=e});'
			. 'a.forEach(function(e){var q=e.getAttribute("data-ws-group");if(e._on&&q&&b[q]!==e)e._on=false;if(e._on){v.push(e);e.removeAttribute("hidden")}else{e.setAttribute("hidden","");e.classList.remove("is-active");e.setAttribute("aria-hidden","true")}});'
			. 'if(!v.length){r.setAttribute("hidden","");return}r.removeAttribute("hidden");'
			. 'var f=v[0],o="";v.some(function(e){o=e.getAttribute("data-ws-ratio");return !!o});'
			. 'if(o){o=o.split("|");r.style.setProperty("--ws-ratio",o[0]);r.style.setProperty("--ws-ratio-mobile",o[1]||o[0])}'
			. 'if(f.classList.contains("is-active"))return;'
			. 'v.forEach(function(e){e.classList.remove("is-active")});f.classList.add("is-active");f.removeAttribute("aria-hidden");'
			. '[].forEach.call(f.querySelectorAll("source[data-wonom-srcset]"),function(s){s.setAttribute("srcset",s.getAttribute("data-wonom-srcset"));s.removeAttribute("data-wonom-srcset")});'
			. '[].forEach.call(f.querySelectorAll("img[data-wonom-src]"),function(i,k){i.loading="eager";if(!k)i.setAttribute("fetchpriority","high");var t=i.getAttribute("data-wonom-srcset");if(t){i.setAttribute("srcset",t);i.removeAttribute("data-wonom-srcset")}i.setAttribute("src",i.getAttribute("data-wonom-src"));i.removeAttribute("data-wonom-src")});'
			. '}catch(e){}})();';
		// "wonom-slider" in the tag keeps speed plugins (delay / defer JS) from postponing it.
		return '<script data-no-optimize="1" data-no-defer="1" data-no-delay="1" data-cfasync="false" data-no-minify="1">/* wonom-slider timing */' . $js . '</script>';
	}

	/**
	 * Slider shape a slide gives when it is the first one: [ desktop, mobile ] aspect ratios.
	 */
	public static function ratio_pair( $settings, $first ) {
		if ( Wonom_Slider_Data::slide_is_collage( $first ) ) {
			$unit    = self::collage_unit( $first['collage'] );
			$contain = isset( $first['collage_fit'] ) && 'contain' === $first['collage_fit'];
			$ratio_d = self::ratio_value( $settings['ratio_desktop'], (int) round( self::collage_ratio_sum( self::collage_visible( $first, false ), $unit, $contain ) * 1000 ), 1000, '1920 / 660' );
			$ratio_m = self::ratio_value( $settings['ratio_mobile'], (int) round( self::collage_ratio_sum( self::collage_visible( $first, true ), $unit, $contain ) * 1000 ), 1000, $ratio_d );
		} else {
			$ratio_d = self::ratio_value( $settings['ratio_desktop'], $first['image_width'], $first['image_height'], '1920 / 660' );
			$ratio_m = self::ratio_value(
				$settings['ratio_mobile'],
				$first['mobile_image_width'] ? $first['mobile_image_width'] : $first['image_width'],
				$first['mobile_image_height'] ? $first['mobile_image_height'] : $first['image_height'],
				$first['image_width'] ? $ratio_d : '4 / 3'
			);
		}
		return array( $ratio_d, $ratio_m );
	}

	private static function inline_style( $settings, $first ) {
		list( $ratio_d, $ratio_m ) = self::ratio_pair( $settings, $first );

		$vars = array(
			'--ws-ratio'          => $ratio_d,
			'--ws-ratio-mobile'   => $ratio_m,
			'--ws-speed'          => (int) $settings['speed'] . 'ms',
			'--ws-interval'       => (int) $settings['interval'] . 'ms',
			'--ws-max-width'      => $settings['max_width'] ? (int) $settings['max_width'] . 'px' : 'none',
			'--ws-cap'            => $settings['max_height'] ? (int) $settings['max_height'] . $settings['max_height_unit'] : 'none',
			'--ws-cap-m'          => $settings['max_height_mobile'] ? (int) $settings['max_height_mobile'] . $settings['max_height_mobile_unit'] : ( $settings['max_height'] ? (int) $settings['max_height'] . $settings['max_height_unit'] : 'none' ),
			'--ws-content-width'  => (int) $settings['content_max_width'] . 'px',
			'--ws-h-size'         => (int) $settings['heading_size'] . 'px',
			'--ws-h-size-mobile'  => (int) $settings['heading_size_mobile'] . 'px',
			'--ws-t-size'         => (int) $settings['text_size'] . 'px',
			'--ws-t-size-mobile'  => (int) $settings['text_size_mobile'] . 'px',
			'--ws-pad-mobile'     => (int) $settings['padding_mobile'] . 'px',
			'--ws-font'           => $settings['font_family'],
			'--ws-font-h'         => Wonom_Slider_Data::font_css( $settings['font_heading'] ),
			'--ws-font-t'         => Wonom_Slider_Data::font_css( $settings['font_text'] ),
			'--ws-h-weight'       => (int) $settings['heading_weight'],
			'--ws-h-transform'    => $settings['heading_uppercase'] ? 'uppercase' : 'none',
			'--ws-h-spacing'      => ( (int) $settings['heading_spacing'] / 100 ) . 'em',
			'--ws-btn-radius'     => (int) $settings['button_radius'] . 'px',
			'--ws-bp'             => (int) $settings['mobile_breakpoint'] . 'px',
		);
		$out  = '';
		foreach ( $vars as $k => $v ) {
			$out .= $k . ':' . $v . ';';
		}
		return $out;
	}

	/**
	 * Mobile rules for a custom breakpoint (mirrors the @media block in slider.css).
	 *
	 * @param string $sel Scope selector, e.g. "#wonom-slider-3".
	 * @param int    $bp  Breakpoint in px.
	 * @return string
	 */
	public static function mobile_css( $sel, $bp ) {
		$max = (int) $bp - 1;
		$s   = $sel;
		$css = "@media (max-width:{$max}px){"
			. "{$s} .wonom-slider__track{aspect-ratio:var(--ws-ratio-mobile);max-height:var(--ws-max-height,var(--ws-cap-m,none))}"
			. "{$s} .wonom-slide__img,{$s} .wonom-slide__media img{object-position:var(--ws-focal-m)}"
			. "{$s} .wonom-slide__content{padding:var(--ws-pad-mobile)}"
			. "{$s} .wonom-slide__heading{font-size:var(--ws-h-size-mobile);letter-spacing:calc(var(--ws-h-spacing,.14em)*.75)}"
			. "{$s} .wonom-slide__text{font-size:var(--ws-t-size-mobile)}"
			. "{$s} .wonom-slide__eyebrow{font-size:var(--ws-e-size-mobile,12px)}"
			. "{$s} .wonom-slide__badge{font-size:var(--ws-b-size-mobile,var(--ws-b-size,12px))}"
			. "{$s} .wonom-slide__button{padding:10px 20px;font-size:12px}"
			. "{$s} .wonom-slide.m-align-left .wonom-slide__content{justify-content:flex-start;text-align:left}"
			. "{$s} .wonom-slide.m-align-center .wonom-slide__content{justify-content:center;text-align:center}"
			. "{$s} .wonom-slide.m-align-right .wonom-slide__content{justify-content:flex-end;text-align:right}"
			. "{$s} .wonom-slide.m-valign-top .wonom-slide__content{align-items:flex-start}"
			. "{$s} .wonom-slide.m-valign-middle .wonom-slide__content{align-items:center}"
			. "{$s} .wonom-slide.m-valign-bottom .wonom-slide__content{align-items:flex-end}"
			. "{$s} .wonom-slide.m-align-left .wonom-slide__actions{justify-content:flex-start}"
			. "{$s} .wonom-slide.m-align-center .wonom-slide__actions{justify-content:center}"
			. "{$s} .wonom-slide.m-align-right .wonom-slide__actions{justify-content:flex-end}"
			. "{$s} .wonom-slide.m-hide-text .wonom-slide__content{display:none}"
			. "{$s} .wonom-slide.m-pos-free .wonom-slide__content{display:block;padding:0}"
			. "{$s} .wonom-slide.m-pos-free .wonom-slide__inner{position:absolute;left:var(--ws-mx);top:var(--ws-my);width:var(--ws-mw);max-width:none;transform:translate(-50%,-50%)}"
			. "{$s} .wonom-slide.m-pos-grid .wonom-slide__content{display:flex;padding:var(--ws-pad-mobile)}"
			. "{$s} .wonom-slide.m-pos-grid .wonom-slide__inner{position:static;width:auto;max-width:var(--ws-content-width);transform:none}"
			. "{$s} .wonom-slide.m-hide-text.m-pos-free .wonom-slide__content{display:none}"
			. "{$s} .wonom-collage{--ws-seam:40px}"
			. "{$s} .wonom-collage__item img{object-position:var(--ws-cfm,var(--ws-cf,50% 50%))}"
			. "{$s} .wonom-collage__item{--ws-pl:var(--ws-mpl,0);--ws-cl:var(--ws-mcl,0);--ws-cr:var(--ws-mcr,0)}"
			. "{$s} .wonom-collage:not(.fit-contain) .wonom-collage__item{flex-grow:var(--ws-cwm,var(--ws-cw,1))}"
			. "{$s} .wonom-collage__item.is-mhide{display:none}"
			. "{$s} .wonom-collage.fit-contain .wonom-collage__item{aspect-ratio:var(--ws-irm,var(--ws-ir,.75))}"
			. "{$s} .wonom-collage.fit-contain .wonom-collage__row{height:min(100cqh,calc((100cqw - var(--ws-gaps-m,0px)) / var(--ws-cr-m,var(--ws-cr,1))))}"
			. "{$s} .wonom-slide.has-frame{--ws-frame:calc(var(--ws-frame-d,0px) * .5)}"
			. "{$s} .wonom-slider__arrow{width:38px!important;height:38px!important;opacity:1;background:rgba(255,255,255,.7)!important}"
			. "{$s} .wonom-slider__arrow--prev{left:8px!important}{$s} .wonom-slider__arrow--next{right:8px!important}"
			. "{$s} .wonom-slider__dots{bottom:10px!important}"
			. "{$s}.m-dots-left .wonom-slider__dots{justify-content:flex-start;padding-left:14px}"
			. "{$s}.m-dots-right .wonom-slider__dots{justify-content:flex-end;padding-right:14px}"
			. "{$s}.m-dots-center .wonom-slider__dots{justify-content:center;padding:0}"
			. "{$s}.m-dots-hidden .wonom-slider__dots{display:none!important}"
			. "{$s}.m-arrows-hide .wonom-slider__arrow{display:none!important}"
			. '}';
		// Neutralise the stylesheet's default 767px block when the custom breakpoint is larger/smaller.
		if ( $bp > 768 ) {
			return $css;
		}
		$css .= "@media (min-width:{$bp}px) and (max-width:767px){"
			. "{$s} .wonom-slider__track{aspect-ratio:var(--ws-ratio)}"
			. "{$s} .wonom-slide__img,{$s} .wonom-slide__media img{object-position:var(--ws-focal)}"
			. "{$s} .wonom-collage__item img{object-position:var(--ws-cf,50% 50%)}"
			. "{$s} .wonom-collage{--ws-seam:90px}"
			. "{$s} .wonom-collage__item{--ws-pl:var(--ws-dpl,0);--ws-cl:var(--ws-dcl,0);--ws-cr:var(--ws-dcr,0)}"
			. "{$s} .wonom-collage:not(.fit-contain) .wonom-collage__item{flex-grow:var(--ws-cw,1)}"
			. "{$s} .wonom-collage__item.is-mhide{display:block}"
			. "{$s} .wonom-collage.fit-contain .wonom-collage__item{aspect-ratio:var(--ws-ir,.75)}"
			. "{$s} .wonom-collage.fit-contain .wonom-collage__row{height:min(100cqh,calc((100cqw - var(--ws-gaps,0px)) / var(--ws-cr,1)))}"
			. "{$s} .wonom-slide__content{padding:clamp(24px,5vw,72px)}"
			. "{$s} .wonom-slide__heading{font-size:var(--ws-h-size);letter-spacing:var(--ws-h-spacing,.14em)}"
			. "{$s} .wonom-slide__text{font-size:var(--ws-t-size)}"
			. "{$s} .wonom-slide.m-hide-text .wonom-slide__content{display:flex}"
			. "{$s} .wonom-slide.pos-free .wonom-slide__content{display:block;padding:0}"
			. "{$s} .wonom-slide.pos-free .wonom-slide__inner{position:absolute;left:var(--ws-x);top:var(--ws-y);width:var(--ws-w);max-width:none;transform:translate(-50%,-50%)}"
			. "{$s} .wonom-slide.pos-grid .wonom-slide__content{display:flex;padding:clamp(24px,5vw,72px)}"
			. "{$s} .wonom-slide.pos-grid .wonom-slide__inner{position:static;width:auto;max-width:var(--ws-content-width);transform:none}"
			. '}';
		return $css;
	}

	/**
	 * Defer the images of a non-active slide: src/srcset become data-src/data-srcset with a
	 * transparent placeholder, and <source srcset> becomes <source data-srcset>. slider.js
	 * (hydrate) restores them for the active slide and the next one.
	 *
	 * @param string $html Image or <picture> markup.
	 * @return string
	 */
	public static function lazy_markup( $html ) {
		$placeholder = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
		// Attribute names are prefixed on purpose: theme/optimiser lazy-loaders (WoodMart,
		// FlyingPress…) grab plain data-src and would load every slide at once.
		$html        = preg_replace( '/<source\b([^>]*?)\ssrcset=/i', '<source$1 data-wonom-srcset=', $html );
		$html        = preg_replace( '/<img\b([^>]*?)\ssrcset=/i', '<img$1 data-wonom-srcset=', $html );
		$html        = preg_replace( '/<img\b([^>]*?)\ssrc="([^"]*)"/i', '<img$1 src="' . $placeholder . '" data-wonom-src="$2"', $html );
		$html        = preg_replace( '/<img\b([^>]*?)\sfetchpriority="[^"]*"/i', '<img$1', $html );
		$html        = preg_replace( '/<img\b/i', '<img data-wonom-lazy="1"', $html );
		return $html;
	}

	private static function ratio_value( $setting, $w, $h, $fallback ) {
		if ( 'auto' !== $setting && preg_match( '/^(\d+)x(\d+)$/', $setting, $m ) ) {
			return $m[1] . ' / ' . $m[2];
		}
		if ( $w && $h ) {
			return (int) $w . ' / ' . (int) $h;
		}
		return $fallback;
	}

	/**
	 * Preview endpoint for the admin editor: /?wonom_slider_preview=1&_wpnonce=…
	 * Renders a minimal HTML document containing only the slider.
	 */
	public static function maybe_render_preview() {
		if ( empty( $_GET['wonom_slider_preview'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wonom_slider_preview' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'wonom-slider' ), 403 );
		}

		nocache_headers();

		$args = array();
		// Preview may include scheduled/disabled slides when ?all=1.
		if ( ! empty( $_GET['all'] ) ) {
			$slides = array_filter(
				Wonom_Slider_Data::get_slides(),
				function ( $s ) {
					return Wonom_Slider_Data::slide_has_content( $s );
				}
			);
			$args['slides'] = array_values( $slides );
		}
		if ( ! empty( $_GET['lang'] ) ) {
			$args['lang'] = sanitize_key( wp_unslash( $_GET['lang'] ) );
		}
		if ( ! empty( $_GET['slide'] ) ) {
			$id     = sanitize_text_field( wp_unslash( $_GET['slide'] ) );
			$slides = array_filter(
				Wonom_Slider_Data::get_slides(),
				function ( $s ) use ( $id ) {
					return $s['id'] === $id && Wonom_Slider_Data::slide_has_content( $s );
				}
			);
			$args['slides'] = array_values( $slides );
		}

		$html = self::render( $args );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php esc_html_e( 'Slider preview', 'wonom-slider' ); ?></title>
<?php
wp_print_styles( 'wonom-slider' );
?>
<style>html,body{margin:0;padding:0;background:#f3f4f6}.wonom-preview-empty{font:14px/1.5 system-ui,sans-serif;color:#555;padding:40px;text-align:center}</style>
</head>
<body class="wonom-slider-preview">
<?php
if ( $html ) {
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
	echo '<p class="wonom-preview-empty">' . esc_html__( 'No visible slides. Add an image to a slide and save.', 'wonom-slider' ) . '</p>';
}
wp_print_scripts( 'wonom-slider' );
?>
</body>
</html>
		<?php
		exit;
	}
}
