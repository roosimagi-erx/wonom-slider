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
		$slides   = isset( $args['slides'] ) && is_array( $args['slides'] ) ? $args['slides'] : Wonom_Slider_Data::get_active_slides();

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

		$count   = count( $slides );
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
		if ( 1 === $count ) {
			$classes[] = 'is-single';
		}

		$config = array(
			'autoplay'     => (bool) $settings['autoplay'] && $count > 1,
			'interval'     => (int) $settings['interval'],
			'speed'        => (int) $settings['speed'],
			'transition'   => $settings['transition'],
			'loop'         => (bool) $settings['loop'],
			'pauseOnHover' => (bool) $settings['pause_on_hover'],
			'progress'     => (bool) $settings['show_progress'],
		);

		// The slider ratio follows the first slide that actually has an image (text-only slides carry no dimensions).
		$ratio_slide = $slides[0];
		foreach ( $slides as $candidate ) {
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
		<section id="<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>" data-wonom-slider='<?php echo esc_attr( wp_json_encode( $config ) ); ?>' aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Featured', 'wonom-slider' ); ?>">
			<div class="wonom-slider__track">
				<?php foreach ( $slides as $i => $slide ) : ?>
					<?php echo self::render_slide( $slide, $i, $count, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
			<?php if ( $count > 1 ) : ?>
				<?php if ( ! empty( $settings['show_arrows'] ) ) : ?>
					<button type="button" class="wonom-slider__arrow wonom-slider__arrow--prev" data-dir="-1" aria-label="<?php esc_attr_e( 'Previous slide', 'wonom-slider' ); ?>">
						<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
					<button type="button" class="wonom-slider__arrow wonom-slider__arrow--next" data-dir="1" aria-label="<?php esc_attr_e( 'Next slide', 'wonom-slider' ); ?>">
						<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
				<?php endif; ?>
				<?php if ( ! empty( $settings['show_dots'] ) ) : ?>
					<div class="wonom-slider__dots" role="tablist">
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
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo $slide_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" data-id="<?php echo esc_attr( $slide['id'] ); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( '%d / %d', $index + 1, $count ) ); ?>"<?php echo $is_first ? '' : ' aria-hidden="true"'; ?>>
			<?php if ( $has_link ) : ?>
				<a class="wonom-slide__link" href="<?php echo esc_url( $slide['button_url'] ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php echo esc_attr( $slide['heading'] ? $slide['heading'] : $slide['name'] ); ?>"></a>
			<?php endif; ?>
			<?php if ( $is_collage ) : ?>
				<?php
				$items = array_values( $slide['collage'] );
				$n     = count( $items );
				$sizes = (int) round( 100 / max( 1, $n ) ) . 'vw';
				?>
			<div class="wonom-slide__media wonom-collage cols-<?php echo (int) $n; ?> seam-<?php echo esc_attr( $slide['collage_seam'] ); ?> m-<?php echo esc_attr( $slide['collage_mobile'] ); ?>" style="--ws-cgap:<?php echo (int) $slide['collage_gap']; ?>px">
				<?php foreach ( $items as $ci => $c ) : ?>
					<?php
					$eager = $is_first && $ci < 2;
					$attr  = array(
						'class'    => 'wonom-collage__img',
						'alt'      => 0 === $ci ? ( $slide['alt'] ? $slide['alt'] : $slide['heading'] ) : '',
						'loading'  => $eager ? 'eager' : 'lazy',
						'decoding' => 'async',
						'sizes'    => '(max-width: ' . (int) $bp . 'px) ' . ( 'first1' === $slide['collage_mobile'] ? '100vw' : ( 'first2' === $slide['collage_mobile'] ? '50vw' : $sizes ) ) . ', ' . $sizes,
					);
					if ( $is_first && 0 === $ci ) {
						$attr['fetchpriority'] = 'high';
					}
					?>
					<div class="wonom-collage__item" style="--ws-cf:<?php echo (int) $c['focal_x']; ?>% <?php echo (int) $c['focal_y']; ?>%">
						<?php
						if ( ! empty( $c['id'] ) ) {
							echo wp_get_attachment_image( $c['id'], 'large', false, $attr );
						} else {
							printf(
								'<img src="%s" alt="%s" class="wonom-collage__img" loading="%s" decoding="async">',
								esc_url( $c['url'] ),
								esc_attr( $attr['alt'] ),
								esc_attr( $attr['loading'] )
							);
						}
						?>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="wonom-slide__overlay" aria-hidden="true"></div>
			<?php elseif ( ! empty( $slide['image_url'] ) ) : ?>
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
			}
			$out .= $def[0] . ':' . esc_attr( $v ) . ';';
		}
		return $out;
	}

	/**
	 * CSS custom properties for the slider wrapper.
	 */
	private static function inline_style( $settings, $first ) {
		if ( Wonom_Slider_Data::slide_is_collage( $first ) ) {
			// Collage: N same-height images side by side → ratio = (N × w) / h.
			$main    = Wonom_Slider_Data::slide_main_image( $first );
			$n       = count( $first['collage'] );
			$n_m     = 'all' === $first['collage_mobile'] ? $n : ( 'first2' === $first['collage_mobile'] ? min( 2, $n ) : 1 );
			$ratio_d = self::ratio_value( $settings['ratio_desktop'], $main['width'] * $n, $main['height'], '1920 / 660' );
			$ratio_m = self::ratio_value( $settings['ratio_mobile'], $main['width'] * $n_m, $main['height'], $ratio_d );
		} else {
			$ratio_d = self::ratio_value( $settings['ratio_desktop'], $first['image_width'], $first['image_height'], '1920 / 660' );
			$ratio_m = self::ratio_value(
				$settings['ratio_mobile'],
				$first['mobile_image_width'] ? $first['mobile_image_width'] : $first['image_width'],
				$first['mobile_image_height'] ? $first['mobile_image_height'] : $first['image_height'],
				// No image anywhere (text-only slider): 4:3 gives the texts room on phones.
				$first['image_width'] ? $ratio_d : '4 / 3'
			);
		}

		$vars = array(
			'--ws-ratio'          => $ratio_d,
			'--ws-ratio-mobile'   => $ratio_m,
			'--ws-speed'          => (int) $settings['speed'] . 'ms',
			'--ws-interval'       => (int) $settings['interval'] . 'ms',
			'--ws-max-width'      => $settings['max_width'] ? (int) $settings['max_width'] . 'px' : 'none',
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
			. "{$s} .wonom-slider__track{aspect-ratio:var(--ws-ratio-mobile)}"
			. "{$s} .wonom-slide__img,{$s} .wonom-slide__media img{object-position:var(--ws-focal-m)}"
			. "{$s} .wonom-slide__content{padding:var(--ws-pad-mobile)}"
			. "{$s} .wonom-slide__heading{font-size:var(--ws-h-size-mobile);letter-spacing:calc(var(--ws-h-spacing,.14em)*.75)}"
			. "{$s} .wonom-slide__text{font-size:var(--ws-t-size-mobile)}"
			. "{$s} .wonom-slide__eyebrow{font-size:var(--ws-e-size-mobile,12px)}"
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
			. "{$s} .wonom-collage.m-first2 .wonom-collage__item:nth-child(n+3){display:none}"
			. "{$s} .wonom-collage.m-first1 .wonom-collage__item:nth-child(n+2){display:none}"
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
