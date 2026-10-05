<?php
/**
 * Integration with Wonom Kampaaniariba (campaign bar).
 *
 * A slide can be *linked* to a campaign: texts, link, coupon and schedule come from the
 * campaign (single source of truth), while images, position and typography stay on the slide.
 * The campaign edit screen gets a "Wonom Slider" box with "Show in slider too"; ticking it
 * creates the linked slide automatically from a template slide.
 *
 * Only post meta is read, so neither plugin depends on the other's functions.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Campaigns {

	const CPT       = 'wonom_banner';
	const META_SHOW = '_wonom_slider_show';
	const META_TPL  = '_wonom_slider_template';

	public static function init() {
		if ( ! self::available() ) {
			return;
		}
		add_action( 'add_meta_boxes_' . self::CPT, array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'on_save' ), 20, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_remove' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_remove' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_untrash' ) );
		// Automatic slides follow whichever campaign is live: any campaign change must purge
		// the page cache and re-plan the schedule tick.
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'on_campaign_change' ), 30 );
		add_action( 'trashed_post', array( __CLASS__, 'on_campaign_change' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_campaign_change' ) );
	}

	/**
	 * Fire the plugin's "saved" hook (cache purge + schedule tick) when a campaign changes and
	 * an automatic campaign slide exists.
	 */
	public static function on_campaign_change( $post_id ) {
		if ( self::CPT !== get_post_type( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		foreach ( (array) get_option( 'wonom_slider_slides', array() ) as $raw ) {
			if ( isset( $raw['source'] ) && 'auto' === $raw['source'] ) {
				do_action( 'wonom_slider_saved' );
				return;
			}
		}
	}

	/**
	 * The campaign an automatic slide should show right now: the live one (latest start wins
	 * when several overlap), otherwise the next upcoming one (so the slide is pre-scheduled and
	 * the cache tick fires at its start). 0 when there is none.
	 */
	public static function current() {
		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);
		$now  = time();
		$live = null;
		$next = null;
		foreach ( $posts as $id ) {
			if ( ! (int) get_post_meta( $id, '_wkr_enabled', true ) ) {
				continue;
			}
			$s = (int) get_post_meta( $id, '_wkr_start_utc', true );
			$e = (int) get_post_meta( $id, '_wkr_end_utc', true );
			if ( ! $s || ! $e || $e < $s ) {
				continue;
			}
			if ( $s <= $now && $now <= $e ) {
				if ( null === $live || $s > $live[1] ) {
					$live = array( $id, $s );
				}
			} elseif ( $s > $now ) {
				if ( null === $next || $s < $next[1] ) {
					$next = array( $id, $s );
				}
			}
		}
		if ( $live ) {
			return (int) $live[0];
		}
		return $next ? (int) $next[0] : 0;
	}

	/**
	 * Fields of the current campaign for the editor (REST /campaign/current).
	 */
	public static function current_fields() {
		$id = self::current();
		if ( ! $id ) {
			return array( 'none' => true );
		}
		$f       = self::fields( $id );
		$f['id'] = $id;
		return $f;
	}

	public static function available() {
		return post_type_exists( self::CPT ) || class_exists( 'WKR_Post_Type' );
	}

	/* ---------- reading campaigns ---------- */

	/**
	 * Campaign status: live | upcoming | ended | off.
	 */
	public static function status( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || ! (int) get_post_meta( $post_id, '_wkr_enabled', true ) ) {
			return 'off';
		}
		$start = (int) get_post_meta( $post_id, '_wkr_start_utc', true );
		$end   = (int) get_post_meta( $post_id, '_wkr_end_utc', true );
		$now   = time();
		if ( ! $start || ! $end ) {
			return 'off';
		}
		if ( $now < $start ) {
			return 'upcoming';
		}
		if ( $now > $end ) {
			return 'ended';
		}
		return 'live';
	}

	/**
	 * List for the editor dropdown.
	 */
	public static function list_campaigns() {
		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
				'posts_per_page' => 100,
				'orderby'        => 'meta_value_num',
				'meta_key'       => '_wkr_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$out[] = array(
				'id'       => $p->ID,
				'title'    => $p->post_title ? $p->post_title : '#' . $p->ID,
				'status'   => self::status( $p->ID ),
				'start'    => self::local( (int) get_post_meta( $p->ID, '_wkr_start_utc', true ) ),
				'end'      => self::local( (int) get_post_meta( $p->ID, '_wkr_end_utc', true ) ),
				'edit_url' => get_edit_post_link( $p->ID, 'raw' ),
			);
		}
		return $out;
	}

	private static function local( $ts ) {
		return $ts ? wp_date( 'Y-m-d\TH:i', $ts ) : '';
	}

	/**
	 * Fields a linked slide takes from its campaign.
	 *
	 * @return array|null null when the campaign does not exist.
	 */
	public static function fields( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return null;
		}
		$langs   = Wonom_Slider_Data::get_languages();
		$default = $langs ? $langs[0]['slug'] : 'et';
		$coupon  = trim( (string) get_post_meta( $post_id, '_wkr_coupon', true ) );
		$apply   = (int) get_post_meta( $post_id, '_wkr_apply_coupon', true );

		$texts = function ( $lang ) use ( $post_id, $coupon, $apply ) {
			$g    = function ( $k ) use ( $post_id, $lang ) {
				return trim( (string) get_post_meta( $post_id, '_wkr_' . $lang . '_' . $k, true ) );
			};
			$link = $g( 'link' );
			if ( $link && $coupon && $apply ) {
				$link = add_query_arg( 'wkr_coupon', rawurlencode( $coupon ), $link );
			}
			$label = $g( 'coupon_label' );
			return array(
				'heading'    => $g( 'l1' ),
				'text'       => $g( 'l2' ),
				'eyebrow'    => $g( 'l3' ),
				'badge'      => $coupon ? ( $label ? $label . ' ' . $coupon : $coupon ) : '',
				'button_url' => $link ? ( preg_match( '#^https?://#i', $link ) ? $link : home_url( $link ) ) : '',
			);
		};

		$base = $texts( $default );
		$i18n = array();
		foreach ( $langs as $l ) {
			if ( $l['slug'] === $default ) {
				continue;
			}
			$t = array_filter( $texts( $l['slug'] ), 'strlen' );
			if ( $t ) {
				$i18n[ $l['slug'] ] = $t;
			}
		}

		return array(
			'base'      => $base,
			'i18n'      => $i18n,
			'start'     => self::local( (int) get_post_meta( $post_id, '_wkr_start_utc', true ) ),
			'end'       => self::local( (int) get_post_meta( $post_id, '_wkr_end_utc', true ) ),
			'enabled'   => 'publish' === $post->post_status && (int) get_post_meta( $post_id, '_wkr_enabled', true ),
			'bg'        => sanitize_hex_color( (string) get_post_meta( $post_id, '_wkr_bg', true ) ),
			'title'     => $post->post_title,
			'status'    => self::status( $post_id ),
			'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * Merge campaign data into a linked slide (idempotent). Unlinked slides pass through.
	 */
	public static function apply( $slide ) {
		if ( ! empty( $slide['source'] ) && 'auto' === $slide['source'] ) {
			$cid = self::current();
			if ( ! $cid ) {
				// Nothing live or upcoming: the slide waits, hidden.
				$slide['enabled']         = false;
				$slide['campaign_none']   = true;
				$slide['campaign_status'] = 'none';
				$slide['start']           = '';
				$slide['end']             = '';
				return $slide;
			}
			$slide['campaign_none']     = false;
			$slide['campaign_resolved'] = $cid;
		} elseif ( ! empty( $slide['source'] ) && 'campaign' === $slide['source'] && ! empty( $slide['campaign_id'] ) ) {
			$cid = (int) $slide['campaign_id'];
		} else {
			return $slide;
		}
		$f = self::fields( $cid );
		if ( ! $f ) {
			$slide['enabled']          = false;
			$slide['campaign_missing'] = true;
			return $slide;
		}
		// The campaign is the only source of content: whatever it does not define is empty, in
		// every language. No button – when the campaign has a link, the whole slide links there.
		$content_keys = array( 'heading', 'text', 'eyebrow', 'badge', 'button_url', 'button_text', 'button2_text', 'button2_url' );
		foreach ( $f['base'] as $k => $v ) {
			$slide[ $k ] = $v;
		}
		$slide['button_text']      = '';
		$slide['button2_text']     = '';
		$slide['button2_url']      = '';
		$slide['link_whole_slide'] = '' !== $slide['button_url'];
		$slide['i18n']             = is_array( $slide['i18n'] ) ? $slide['i18n'] : array();
		foreach ( array_keys( $slide['i18n'] ) as $lang ) {
			foreach ( $content_keys as $k ) {
				unset( $slide['i18n'][ $lang ][ $k ] );
			}
		}
		foreach ( $f['i18n'] as $lang => $t ) {
			$slide['i18n'][ $lang ] = array_merge( isset( $slide['i18n'][ $lang ] ) && is_array( $slide['i18n'][ $lang ] ) ? $slide['i18n'][ $lang ] : array(), $t );
		}
		$slide['start']   = $f['start'];
		$slide['end']     = $f['end'];
		$slide['enabled'] = ! empty( $slide['enabled'] ) && $f['enabled'];
		if ( '' === $slide['name'] ) {
			$slide['name'] = $f['title'];
		}
		$slide['campaign_title']    = $f['title'];
		$slide['campaign_status']   = $f['status'];
		$slide['campaign_edit_url'] = $f['edit_url'];
		return $slide;
	}

	/* ---------- campaign edit screen ---------- */

	public static function meta_box() {
		add_meta_box(
			'wonom_slider_link',
			'Wonom Slider',
			array( __CLASS__, 'render_box' ),
			self::CPT,
			'side',
			'default'
		);
	}

	public static function render_box( $post ) {
		$show = (int) get_post_meta( $post->ID, self::META_SHOW, true );
		$tpl  = (string) get_post_meta( $post->ID, self::META_TPL, true );
		$link = self::find_slide( $post->ID );
		wp_nonce_field( 'wonom_slider_link_' . $post->ID, 'wonom_slider_link_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="wonom_slider_show" value="1" <?php checked( $show, 1 ); ?>>
				<strong><?php esc_html_e( 'Show this campaign in the slider too', 'wonom-slider' ); ?></strong>
			</label>
		</p>
		<p>
			<label for="wonom_slider_template"><?php esc_html_e( 'Template slide', 'wonom-slider' ); ?></label><br>
			<select name="wonom_slider_template" id="wonom_slider_template" style="width:100%">
				<option value=""><?php esc_html_e( 'Default (from slider settings)', 'wonom-slider' ); ?></option>
				<?php foreach ( Wonom_Slider_Data::get_slides() as $s ) : ?>
					<?php if ( ! empty( $s['source'] ) && 'own' !== $s['source'] ) { continue; } ?>
					<option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $tpl, $s['id'] ); ?>><?php echo esc_html( $s['name'] ? $s['name'] : ( $s['heading'] ? $s['heading'] : $s['id'] ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'A linked slide is created automatically: texts, link, coupon and schedule follow this campaign; images, position and typography are edited in Wonom Slider.', 'wonom-slider' ); ?></p>
		<?php if ( $link ) : ?>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wonom-slider' ) ); ?>"><?php esc_html_e( 'Open the slide in Wonom Slider', 'wonom-slider' ); ?></a></p>
		<?php endif; ?>
		<?php
	}

	public static function on_save( $post_id, $post ) {
		if ( ! isset( $_POST['wonom_slider_link_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wonom_slider_link_nonce'] ) ), 'wonom_slider_link_' . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$show = ! empty( $_POST['wonom_slider_show'] ) ? 1 : 0;
		$tpl  = isset( $_POST['wonom_slider_template'] ) ? preg_replace( '/[^a-zA-Z0-9_-]/', '', wp_unslash( $_POST['wonom_slider_template'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_post_meta( $post_id, self::META_SHOW, $show );
		update_post_meta( $post_id, self::META_TPL, $tpl );
		self::sync( $post_id, (bool) $show, $tpl );
	}

	public static function on_remove( $post_id ) {
		if ( self::CPT !== get_post_type( $post_id ) ) {
			return;
		}
		self::sync( $post_id, false, '' );
	}

	public static function on_untrash( $post_id ) {
		if ( self::CPT !== get_post_type( $post_id ) ) {
			return;
		}
		if ( (int) get_post_meta( $post_id, self::META_SHOW, true ) ) {
			self::sync( $post_id, true, (string) get_post_meta( $post_id, self::META_TPL, true ) );
		}
	}

	/* ---------- keeping the linked slide in step ---------- */

	public static function find_slide( $campaign_id ) {
		foreach ( Wonom_Slider_Data::get_slides() as $s ) {
			if ( ! empty( $s['source'] ) && 'campaign' === $s['source'] && (int) $s['campaign_id'] === (int) $campaign_id ) {
				return $s;
			}
		}
		return null;
	}

	/**
	 * Create / enable / disable the linked slide. The slide list is saved through the normal
	 * path, so sanitising and cache purging apply.
	 */
	public static function sync( $campaign_id, $show, $template_id = '' ) {
		$raw   = get_option( Wonom_Slider_Data::OPTION_SLIDES, array() );
		$raw   = is_array( $raw ) ? $raw : array();
		$found = null;
		foreach ( $raw as $i => $s ) {
			if ( is_array( $s ) && ! empty( $s['source'] ) && 'campaign' === $s['source'] && (int) ( isset( $s['campaign_id'] ) ? $s['campaign_id'] : 0 ) === (int) $campaign_id ) {
				$found = $i;
				break;
			}
		}

		if ( ! $show ) {
			if ( null !== $found ) {
				$status = get_post_status( $campaign_id );
				if ( ! $status || 'trash' === $status ) {
					array_splice( $raw, $found, 1 ); // campaign gone → slide gone.
				} else {
					$raw[ $found ]['enabled'] = false; // untick → keep the layout, just hide.
				}
				Wonom_Slider_Data::save_slides( $raw );
			}
			return;
		}

		if ( null !== $found ) {
			$raw[ $found ]['enabled'] = true;
			Wonom_Slider_Data::save_slides( $raw );
			return;
		}

		// New linked slide from the template.
		$settings = Wonom_Slider_Data::get_settings();
		$tpl_id   = $template_id ? $template_id : ( isset( $settings['campaign_template_id'] ) ? $settings['campaign_template_id'] : '' );
		$tpl      = null;
		foreach ( $raw as $s ) {
			if ( is_array( $s ) && isset( $s['id'] ) && $s['id'] === $tpl_id ) {
				$tpl = $s;
				break;
			}
		}
		$slide = $tpl ? $tpl : Wonom_Slider_Data::default_slide();
		foreach ( array( 'id', 'name', 'heading', 'text', 'eyebrow', 'badge', 'button_url', 'button2_text', 'button2_url', 'start', 'end', 'i18n', 'campaign_title', 'campaign_status', 'campaign_edit_url', 'campaign_missing' ) as $k ) {
			unset( $slide[ $k ] );
		}
		$f                    = self::fields( $campaign_id );
		$slide['id']          = 'c' . (int) $campaign_id;
		$slide['name']        = $f ? $f['title'] : '';
		$slide['type']        = 'campaign';
		$slide['source']      = 'campaign';
		$slide['campaign_id'] = (int) $campaign_id;
		$slide['enabled']     = true;
		if ( empty( $slide['button_text'] ) ) {
			$slide['button_text'] = __( 'See the offer', 'wonom-slider' );
		}
		if ( ! $tpl && $f && $f['bg'] ) {
			$slide['bg_color'] = $f['bg'];
		}
		array_unshift( $raw, $slide ); // campaigns go first.
		Wonom_Slider_Data::save_slides( $raw );
	}
}
