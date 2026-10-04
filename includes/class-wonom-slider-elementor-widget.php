<?php
/**
 * Elementor widget.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'wonom_slider';
	}

	public function get_title() {
		return __( 'Wonom Slider', 'wonom-slider' );
	}

	public function get_icon() {
		return 'eicon-slider-full-screen';
	}

	public function get_categories() {
		return array( 'wonom', 'general' );
	}

	public function get_keywords() {
		return array( 'slider', 'hero', 'banner', 'carousel', 'wonom' );
	}

	public function get_style_depends() {
		return array( 'wonom-slider' );
	}

	public function get_script_depends() {
		return array( 'wonom-slider' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Wonom Slider', 'wonom-slider' ) )
		);

		$this->add_control(
			'info',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: admin URL */
					__( 'Slides, texts, scheduling and design are managed in <a href="%s" target="_blank">Wonom Slider</a>. This widget only places the slider.', 'wonom-slider' ),
					esc_url( admin_url( 'admin.php?page=wonom-slider' ) )
				),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'extra_class',
			array(
				'label'       => __( 'Extra CSS class', 'wonom-slider' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			)
		);

		$this->add_responsive_control(
			'min_height',
			array(
				'label'      => __( 'Minimum height', 'wonom-slider' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 1200 ),
					'vh' => array( 'min' => 0, 'max' => 100 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .wonom-slider' => '--ws-min-height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'max_height',
			array(
				'label'      => __( 'Maximum height', 'wonom-slider' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 1600 ),
					'vh' => array( 'min' => 0, 'max' => 100 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .wonom-slider' => '--ws-max-height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$args     = array( 'class' => isset( $settings['extra_class'] ) ? $settings['extra_class'] : '' );

		$is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode();
		if ( $is_editor ) {
			// Inside the editor show every slide that has an image, so campaign slides can be checked too.
			$slides = array_values(
				array_filter(
					Wonom_Slider_Data::get_slides(),
					function ( $s ) {
						return ! empty( $s['image_url'] );
					}
				)
			);
			if ( empty( $slides ) ) {
				echo '<div style="padding:40px;text-align:center;background:#f3f4f6;border:1px dashed #cbd5e1;font:14px/1.5 system-ui,sans-serif;color:#475569">'
					. esc_html__( 'Wonom Slider: no slides yet. Add slides under Wonom Slider in the WordPress admin.', 'wonom-slider' ) . '</div>';
				return;
			}
			$args['slides'] = $slides;
		}

		echo Wonom_Slider_Frontend::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
