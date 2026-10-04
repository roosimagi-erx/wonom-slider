<?php
/**
 * Admin screen: menu, assets, app shell. The editor itself is a small vanilla-JS app
 * (assets/admin/admin.js) talking to the REST API.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Admin {

	const PAGE = 'wonom-slider';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'plugin_action_links_' . WONOM_SLIDER_BASENAME, array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Wonom Slider', 'wonom-slider' ),
			__( 'Wonom Slider', 'wonom-slider' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' ),
			'dashicons-images-alt2',
			58
		);
	}

	public static function is_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'toplevel_page_' . self::PAGE === $screen->id;
	}

	public static function body_class( $classes ) {
		return self::is_screen() ? $classes . ' wonom-app' : $classes;
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Slides', 'wonom-slider' ) . '</a>' );
		return $links;
	}

	public static function notices() {
		$msg = get_transient( 'wonom_slider_update_notice' );
		if ( $msg ) {
			delete_transient( 'wonom_slider_update_notice' );
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
		if ( get_transient( 'wonom_slider_activated' ) && ! self::is_screen() ) {
			delete_transient( 'wonom_slider_activated' );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Wonom Slider is active.', 'wonom-slider' ),
				esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
				esc_html__( 'Add your first slide →', 'wonom-slider' )
			);
		}
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'wonom-slider-admin', WONOM_SLIDER_URL . 'assets/admin/admin.css', array(), WONOM_SLIDER_VERSION );
		wp_enqueue_script( 'wonom-slider-admin', WONOM_SLIDER_URL . 'assets/admin/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-api-fetch' ), WONOM_SLIDER_VERSION, true );

		$preview = add_query_arg(
			array(
				'wonom_slider_preview' => 1,
				'_wpnonce'             => wp_create_nonce( 'wonom_slider_preview' ),
			),
			home_url( '/' )
		);

		wp_localize_script(
			'wonom-slider-admin',
			'WONOM_SLIDER',
			array(
				'rest'       => esc_url_raw( rest_url( Wonom_Slider_Rest::NS ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'state'      => Wonom_Slider_Rest::state(),
				'previewUrl' => $preview,
				'version'    => WONOM_SLIDER_VERSION,
				'homeUrl'    => home_url( '/' ),
				'pluginsUrl' => admin_url( 'plugins.php' ),
				'langPlugin' => function_exists( 'pll_languages_list' ) ? 'Polylang' : ( defined( 'ICL_SITEPRESS_VERSION' ) ? 'WPML' : '' ),
				'i18n'       => self::strings(),
			)
		);
	}

	/**
	 * All editor strings (translatable through the plugin text domain).
	 */
	public static function strings() {
		return array(
			// Tabs & bar.
			'tabSlides'        => __( 'Slides', 'wonom-slider' ),
			'tabCalendar'      => __( 'Calendar', 'wonom-slider' ),
			'tabSettings'      => __( 'Settings', 'wonom-slider' ),
			'addSlide'         => __( 'Add slide', 'wonom-slider' ),
			'addCampaign'      => __( 'Add campaign slide', 'wonom-slider' ),
			'noSlides'         => __( 'No slides yet. Add your first slide – a desktop image is all that is required.', 'wonom-slider' ),
			'save'             => __( 'Save changes', 'wonom-slider' ),
			'saved'            => __( 'Saved', 'wonom-slider' ),
			'saving'           => __( 'Saving…', 'wonom-slider' ),
			'unsaved'          => __( 'Unsaved changes', 'wonom-slider' ),
			'allSaved'         => __( 'All changes saved', 'wonom-slider' ),
			'saveFailed'       => __( 'Saving failed', 'wonom-slider' ),
			'leaveConfirm'     => __( 'You have unsaved changes.', 'wonom-slider' ),
			'preview'          => __( 'Preview', 'wonom-slider' ),
			'previewHint'      => __( 'Preview shows the saved version. Save to refresh.', 'wonom-slider' ),
			'previewAll'       => __( 'Include scheduled and disabled slides', 'wonom-slider' ),
			'desktop'          => __( 'Desktop', 'wonom-slider' ),
			'tablet'           => __( 'Tablet', 'wonom-slider' ),
			'mobile'           => __( 'Mobile', 'wonom-slider' ),
			'refresh'          => __( 'Refresh', 'wonom-slider' ),
			'openSite'         => __( 'Open site', 'wonom-slider' ),

			// Status pills.
			'stActive'         => __( 'Live', 'wonom-slider' ),
			'stScheduled'      => __( 'Scheduled', 'wonom-slider' ),
			'stExpired'        => __( 'Ended', 'wonom-slider' ),
			'stDisabled'       => __( 'Off', 'wonom-slider' ),
			'stNoimage'        => __( 'Empty', 'wonom-slider' ),

			// Stage (live preview with draggable text block).
			'stageTitle'       => __( 'Preview – drag the text block to position it', 'wonom-slider' ),
			'stageHint'        => __( 'Drag the text block anywhere. Arrow keys nudge by 1 % (Shift = 5 %). Drag the blue handle to change its width. Switch to Mobile to position it separately for phones.', 'wonom-slider' ),
			'stageEmpty'       => __( 'Add an image or some text to see the preview.', 'wonom-slider' ),
			'stageLoading'     => __( 'Rendering…', 'wonom-slider' ),
			'posMode'          => __( 'Text block position', 'wonom-slider' ),
			'posGrid'          => __( 'Grid', 'wonom-slider' ),
			'posFree'          => __( 'Free', 'wonom-slider' ),
			'posHint'          => __( 'Grid = the alignment buttons above. Free = exact position; set it by dragging in the preview or with the numbers.', 'wonom-slider' ),
			'posX'             => __( 'X (%)', 'wonom-slider' ),
			'posY'             => __( 'Y (%)', 'wonom-slider' ),
			'posW'             => __( 'Width (%)', 'wonom-slider' ),
			'posCenter'        => __( 'Center', 'wonom-slider' ),
			'bgColor'          => __( 'Background colour', 'wonom-slider' ),
			'bgHint'           => __( 'Shown behind the image and on its own when the slide has no image – a slide can be text only.', 'wonom-slider' ),

			// Per-slide typography.
			'secTypo'          => __( 'Typography (this slide)', 'wonom-slider' ),
			'typoIntro'        => __( 'Empty = as in Settings → Typography. Set only what this slide should do differently; the preview updates immediately.', 'wonom-slider' ),
			'asSettings'       => __( 'As in settings', 'wonom-slider' ),
			'gap'              => __( 'Space between lines (px)', 'wonom-slider' ),
			'gapButton'        => __( 'Space above buttons (px)', 'wonom-slider' ),
			'yes'              => __( 'Yes', 'wonom-slider' ),
			'no'               => __( 'No', 'wonom-slider' ),
			'typoReset'        => __( 'Reset to settings', 'wonom-slider' ),

			// Fonts.
			'fontHeading'      => __( 'Heading font', 'wonom-slider' ),
			'fontText'         => __( 'Text & button font', 'wonom-slider' ),
			'fontInherit'      => __( 'Site default font (theme)', 'wonom-slider' ),
			'fontCustom'       => __( 'Custom CSS value…', 'wonom-slider' ),
			'fontCustomPh'     => __( 'e.g. "Helvetica Neue", Arial, sans-serif', 'wonom-slider' ),
			'headingWeight'    => __( 'Heading weight', 'wonom-slider' ),
			'headingUppercase' => __( 'Heading in uppercase', 'wonom-slider' ),
			'headingSpacing'   => __( 'Heading letter spacing (1/100 em)', 'wonom-slider' ),
			'fontsHint'        => __( 'Web fonts are loaded from Bunny Fonts (EU servers, GDPR-friendly) and only when one is selected.', 'wonom-slider' ),

			// Cache.
			'gCache'           => __( 'Page cache', 'wonom-slider' ),
			'autoPurge'        => __( 'Purge the page cache automatically when slides change and at scheduled start/end times', 'wonom-slider' ),
			'cacheDetected'    => __( 'Detected on this site: %s', 'wonom-slider' ),
			'cacheNone'        => __( 'No cache plugin detected – nothing to purge.', 'wonom-slider' ),
			'cfZone'           => __( 'Cloudflare Zone ID', 'wonom-slider' ),
			'cfToken'          => __( 'Cloudflare API token (Cache Purge permission)', 'wonom-slider' ),
			'cfHint'           => __( 'Only needed when Cloudflare caches HTML (APO or “Cache Everything”).', 'wonom-slider' ),
			'purgeNow'         => __( 'Purge cache now', 'wonom-slider' ),
			'purging'          => __( 'Purging…', 'wonom-slider' ),
			'purged'           => __( 'Purged: %s', 'wonom-slider' ),
			'purgedNone'       => __( 'Nothing to purge – no cache plugin active.', 'wonom-slider' ),
			'nextTick'         => __( 'Next scheduled purge: %s', 'wonom-slider' ),
			'cronOff'          => __( 'WP-Cron is disabled (DISABLE_WP_CRON). Make sure a server cron runs wp-cron.php, otherwise scheduled slides switch only on the next save.', 'wonom-slider' ),
			'typeRegular'      => __( 'Regular', 'wonom-slider' ),
			'typeCampaign'     => __( 'Campaign', 'wonom-slider' ),

			// Card actions.
			'edit'             => __( 'Edit', 'wonom-slider' ),
			'close'            => __( 'Close', 'wonom-slider' ),
			'duplicate'        => __( 'Duplicate', 'wonom-slider' ),
			'delete'           => __( 'Delete', 'wonom-slider' ),
			'confirmDelete'    => __( 'Delete this slide? This cannot be undone after saving.', 'wonom-slider' ),
			'enabled'          => __( 'Enabled', 'wonom-slider' ),
			'dragToReorder'    => __( 'Drag to reorder', 'wonom-slider' ),
			'untitled'         => __( 'Untitled slide', 'wonom-slider' ),
			'copySuffix'       => __( ' (copy)', 'wonom-slider' ),
			'moveUp'           => __( 'Move up', 'wonom-slider' ),
			'moveDown'         => __( 'Move down', 'wonom-slider' ),

			// Sections.
			'secImages'        => __( 'Images', 'wonom-slider' ),
			'secContent'       => __( 'Texts & button', 'wonom-slider' ),
			'secDesign'        => __( 'Design', 'wonom-slider' ),
			'secMobile'        => __( 'Mobile', 'wonom-slider' ),
			'secSchedule'      => __( 'Schedule', 'wonom-slider' ),
			'secAdvanced'      => __( 'Advanced', 'wonom-slider' ),

			// Images.
			'desktopImage'     => __( 'Desktop image', 'wonom-slider' ),
			'mobileImage'      => __( 'Mobile image (optional)', 'wonom-slider' ),
			'chooseImage'      => __( 'Choose image', 'wonom-slider' ),
			'replace'          => __( 'Replace', 'wonom-slider' ),
			'remove'           => __( 'Remove', 'wonom-slider' ),
			'focalHint'        => __( 'Click the image to set the focal point – the spot that stays visible when the image is cropped on other screen sizes.', 'wonom-slider' ),
			'recommended'      => __( 'Recommended: desktop 1920×660 px (or your ratio), mobile 800×1000 px. WebP/JPG under 300 kB.', 'wonom-slider' ),
			'mobileHint'       => __( 'Used below the mobile breakpoint. If empty, the desktop image is used with the mobile focal point.', 'wonom-slider' ),
			'alt'              => __( 'Alt text (accessibility & SEO)', 'wonom-slider' ),
			'imageSize'        => __( '%1$d × %2$d px', 'wonom-slider' ),

			// Content.
			'name'             => __( 'Internal name', 'wonom-slider' ),
			'nameHint'         => __( 'Only shown in the admin, e.g. “Autumn campaign”.', 'wonom-slider' ),
			'eyebrow'          => __( 'Small line above heading', 'wonom-slider' ),
			'heading'          => __( 'Heading', 'wonom-slider' ),
			'text'             => __( 'Text', 'wonom-slider' ),
			'buttonText'       => __( 'Button text', 'wonom-slider' ),
			'buttonUrl'        => __( 'Button link', 'wonom-slider' ),
			'button2Text'      => __( 'Second button text', 'wonom-slider' ),
			'button2Url'       => __( 'Second button link', 'wonom-slider' ),
			'badge'            => __( 'Badge (e.g. −20 %)', 'wonom-slider' ),
			'translateFrom'    => __( 'Translate from %s', 'wonom-slider' ),
			'translating'      => __( 'Translating…', 'wonom-slider' ),
			'translated'       => __( 'Translated with %s – please review.', 'wonom-slider' ),
			'translateFailed'  => __( 'Translation failed', 'wonom-slider' ),
			'fallbackHint'     => __( 'Empty fields fall back to the default language, so a slide never shows blank text.', 'wonom-slider' ),
			'defaultLang'      => __( 'default', 'wonom-slider' ),

			// Design.
			'align'            => __( 'Text alignment', 'wonom-slider' ),
			'left'             => __( 'Left', 'wonom-slider' ),
			'center'           => __( 'Center', 'wonom-slider' ),
			'right'            => __( 'Right', 'wonom-slider' ),
			'valign'           => __( 'Vertical position', 'wonom-slider' ),
			'top'              => __( 'Top', 'wonom-slider' ),
			'middle'           => __( 'Middle', 'wonom-slider' ),
			'bottom'           => __( 'Bottom', 'wonom-slider' ),
			'textColor'        => __( 'Text colour', 'wonom-slider' ),
			'buttonBg'         => __( 'Button background', 'wonom-slider' ),
			'buttonColor'      => __( 'Button text colour', 'wonom-slider' ),
			'overlay'          => __( 'Overlay darkness', 'wonom-slider' ),
			'overlayColor'     => __( 'Overlay colour', 'wonom-slider' ),
			'overlayHint'      => __( 'A light overlay keeps white text readable on bright photos.', 'wonom-slider' ),

			// Mobile.
			'mobileIntro'      => __( 'Everything here applies only below the mobile breakpoint (Settings → Layout). Leave “Same as desktop” unless the mobile view needs something different.', 'wonom-slider' ),
			'sameAsDesktop'    => __( 'Same as desktop', 'wonom-slider' ),
			'hideTextMobile'   => __( 'Hide texts and buttons on mobile (image only)', 'wonom-slider' ),
			'mobileFocal'      => __( 'Mobile focal point', 'wonom-slider' ),

			// Schedule.
			'start'            => __( 'Starts', 'wonom-slider' ),
			'end'              => __( 'Ends', 'wonom-slider' ),
			'scheduleIntro'    => __( 'Leave both empty for a slide that is always shown. Set a start and/or end for campaigns – the slide appears and disappears automatically.', 'wonom-slider' ),
			'scheduleTz'       => __( 'Times follow the site timezone: %s', 'wonom-slider' ),
			'presetNow'        => __( 'Now', 'wonom-slider' ),
			'presetTomorrow'   => __( 'Tomorrow 00:00', 'wonom-slider' ),
			'presetMonday'     => __( 'Next Monday', 'wonom-slider' ),
			'preset7'          => __( '+7 days', 'wonom-slider' ),
			'preset14'         => __( '+14 days', 'wonom-slider' ),
			'presetEom'        => __( 'End of month', 'wonom-slider' ),
			'clear'            => __( 'Clear', 'wonom-slider' ),
			'openCalendar'     => __( 'Open calendar', 'wonom-slider' ),
			'prevMonth'        => __( 'Previous month', 'wonom-slider' ),
			'nextMonth'        => __( 'Next month', 'wonom-slider' ),
			'hour'             => __( 'Hour', 'wonom-slider' ),
			'minute'           => __( 'Minute', 'wonom-slider' ),
			'today'            => __( 'Today', 'wonom-slider' ),
			'done'             => __( 'Done', 'wonom-slider' ),
			'liveNow'          => __( 'Live now', 'wonom-slider' ),
			'liveUntil'        => __( 'Live now, ends %s', 'wonom-slider' ),
			'startsAt'         => __( 'Starts %s', 'wonom-slider' ),
			'startsEnds'       => __( 'Starts %1$s, ends %2$s', 'wonom-slider' ),
			'endedAt'          => __( 'Ended %s', 'wonom-slider' ),
			'always'           => __( 'Always visible', 'wonom-slider' ),
			'scheduleInvalid'  => __( 'End is before start – the slide will never show.', 'wonom-slider' ),
			'duration'         => __( 'Duration: %s', 'wonom-slider' ),
			'days'             => __( '%d days', 'wonom-slider' ),
			'hours'            => __( '%d hours', 'wonom-slider' ),

			// Advanced.
			'linkWhole'        => __( 'Make the whole slide clickable (uses the button link)', 'wonom-slider' ),
			'newTab'           => __( 'Open links in a new tab', 'wonom-slider' ),
			'typeLabel'        => __( 'Slide type', 'wonom-slider' ),
			'typeHint'         => __( 'Only a label for you – campaign slides are highlighted in the list and the calendar.', 'wonom-slider' ),

			// Settings groups.
			'gPlayback'        => __( 'Playback', 'wonom-slider' ),
			'gLayout'          => __( 'Layout & sizes', 'wonom-slider' ),
			'gTypography'      => __( 'Typography', 'wonom-slider' ),
			'gNavigation'      => __( 'Navigation', 'wonom-slider' ),
			'gLanguages'       => __( 'Languages & translation', 'wonom-slider' ),
			'gUpdates'         => __( 'Automatic updates', 'wonom-slider' ),
			'gData'            => __( 'Data', 'wonom-slider' ),
			'gCss'             => __( 'Custom CSS', 'wonom-slider' ),
			'gEmbed'           => __( 'How to show the slider', 'wonom-slider' ),

			'autoplay'         => __( 'Autoplay', 'wonom-slider' ),
			'interval'         => __( 'Time per slide (ms)', 'wonom-slider' ),
			'speed'            => __( 'Transition speed (ms)', 'wonom-slider' ),
			'transition'       => __( 'Transition', 'wonom-slider' ),
			'fade'             => __( 'Fade', 'wonom-slider' ),
			'slide'            => __( 'Slide', 'wonom-slider' ),
			'loop'             => __( 'Loop', 'wonom-slider' ),
			'pauseOnHover'     => __( 'Pause on hover', 'wonom-slider' ),
			'kenBurns'         => __( 'Slow zoom (Ken Burns)', 'wonom-slider' ),
			'showArrows'       => __( 'Show arrows', 'wonom-slider' ),
			'showDots'         => __( 'Show dots', 'wonom-slider' ),
			'showProgress'     => __( 'Show progress bar', 'wonom-slider' ),
			'ratioDesktop'     => __( 'Desktop aspect ratio', 'wonom-slider' ),
			'ratioMobile'      => __( 'Mobile aspect ratio', 'wonom-slider' ),
			'ratioAuto'        => __( 'From first image', 'wonom-slider' ),
			'ratioHint'        => __( 'Width × height, e.g. 1920x660. Match your images so nothing gets cropped. “From first image” uses the first slide’s dimensions.', 'wonom-slider' ),
			'maxWidth'         => __( 'Max width (px, 0 = container width)', 'wonom-slider' ),
			'breakpoint'       => __( 'Mobile breakpoint (px)', 'wonom-slider' ),
			'breakpointHint'   => __( 'Below this width the mobile image, ratio, sizes and alignment are used.', 'wonom-slider' ),
			'contentWidth'     => __( 'Text block max width (px)', 'wonom-slider' ),
			'headingSize'      => __( 'Heading size – desktop (px)', 'wonom-slider' ),
			'headingSizeMobile' => __( 'Heading size – mobile (px)', 'wonom-slider' ),
			'textSize'         => __( 'Text size – desktop (px)', 'wonom-slider' ),
			'textSizeMobile'   => __( 'Text size – mobile (px)', 'wonom-slider' ),
			'paddingMobile'    => __( 'Side padding – mobile (px)', 'wonom-slider' ),
			'fontFamily'       => __( 'Font family (CSS value)', 'wonom-slider' ),
			'fontHint'         => __( '“inherit” uses the theme font.', 'wonom-slider' ),
			'buttonRadius'     => __( 'Button corner radius (px)', 'wonom-slider' ),
			'headingTag'       => __( 'Heading HTML tag', 'wonom-slider' ),
			'headingTagHint'   => __( 'Keep H2 unless the slider heading should be the page’s only H1. Multiple H1s hurt SEO.', 'wonom-slider' ),
			'languagesList'    => __( 'Languages (comma separated, default first)', 'wonom-slider' ),
			'languagesDetected' => __( 'Languages are managed by %s – each slide gets a tab per language automatically.', 'wonom-slider' ),
			'languagesHint'    => __( 'Example: et,en. Used when no Polylang/WPML is active; the slider then picks the language from the page locale.', 'wonom-slider' ),
			'deeplKey'         => __( 'DeepL API key', 'wonom-slider' ),
			'deeplHint'        => __( 'Optional. Enables the “Translate” button in slide texts. Free keys end with “:fx”. Saved keys are shown masked.', 'wonom-slider' ),
			'repo'             => __( 'GitHub repository (owner/name)', 'wonom-slider' ),
			'repoHint'         => __( 'Published releases of this repository are offered as plugin updates in WordPress.', 'wonom-slider' ),
			'token'            => __( 'Access token (private repository only)', 'wonom-slider' ),
			'tokenHint'        => __( 'Fine-grained token with “Contents: read” on this repository. Leave empty for a public repository.', 'wonom-slider' ),
			'tokenConst'       => __( 'A token is defined in wp-config.php and takes precedence.', 'wonom-slider' ),
			'checkUpdates'     => __( 'Check for updates now', 'wonom-slider' ),
			'checking'         => __( 'Checking…', 'wonom-slider' ),
			'installed'        => __( 'Installed: %s', 'wonom-slider' ),
			'upToDate'         => __( 'Up to date – latest release is %s.', 'wonom-slider' ),
			'updateAvailable'  => __( 'Version %s is available.', 'wonom-slider' ),
			'updateNow'        => __( 'Update now', 'wonom-slider' ),
			'updateFailed'     => __( 'Could not reach GitHub or no release has been published yet.', 'wonom-slider' ),
			'deleteOnUninstall' => __( 'Delete slides and settings when the plugin is deleted', 'wonom-slider' ),
			'export'           => __( 'Export JSON', 'wonom-slider' ),
			'import'           => __( 'Import JSON', 'wonom-slider' ),
			'importConfirm'    => __( 'Importing replaces all current slides and settings. Continue?', 'wonom-slider' ),
			'importFailed'     => __( 'Import failed – not a Wonom Slider export.', 'wonom-slider' ),
			'imported'         => __( 'Imported', 'wonom-slider' ),
			'customCssHint'    => __( 'Loaded only on pages that show the slider. Example: .wonom-slide__heading { letter-spacing: .12em; }', 'wonom-slider' ),
			'embedShortcode'   => __( 'Shortcode – paste into any page, widget or theme slider area:', 'wonom-slider' ),
			'embedElementor'   => __( 'Elementor – drag the “Wonom Slider” widget (category Wonom) onto the page.', 'wonom-slider' ),
			'embedBlock'       => __( 'Block editor – add the “Wonom Slider” block.', 'wonom-slider' ),
			'embedPhp'         => __( 'Theme template (PHP):', 'wonom-slider' ),
			'copy'             => __( 'Copy', 'wonom-slider' ),
			'copied'           => __( 'Copied', 'wonom-slider' ),

			// Calendar.
			'calendarIntro'    => __( 'Scheduled slides on a monthly grid. Slides without a schedule are always visible and are not drawn.', 'wonom-slider' ),
			'calendarList'     => __( 'Schedule', 'wonom-slider' ),
			'noScheduled'      => __( 'No scheduled slides yet. Open a slide → Schedule to set a start and end.', 'wonom-slider' ),
			'colSlide'         => __( 'Slide', 'wonom-slider' ),
			'colStart'         => __( 'Starts', 'wonom-slider' ),
			'colEnd'           => __( 'Ends', 'wonom-slider' ),
			'colStatus'        => __( 'Status', 'wonom-slider' ),
			'noEnd'            => __( 'no end', 'wonom-slider' ),
			'noStart'          => __( 'immediately', 'wonom-slider' ),
			'months'           => array(
				__( 'January', 'wonom-slider' ),
				__( 'February', 'wonom-slider' ),
				__( 'March', 'wonom-slider' ),
				__( 'April', 'wonom-slider' ),
				__( 'May', 'wonom-slider' ),
				__( 'June', 'wonom-slider' ),
				__( 'July', 'wonom-slider' ),
				__( 'August', 'wonom-slider' ),
				__( 'September', 'wonom-slider' ),
				__( 'October', 'wonom-slider' ),
				__( 'November', 'wonom-slider' ),
				__( 'December', 'wonom-slider' ),
			),
			'dow'              => array(
				_x( 'Mo', 'weekday short', 'wonom-slider' ),
				_x( 'Tu', 'weekday short', 'wonom-slider' ),
				_x( 'We', 'weekday short', 'wonom-slider' ),
				_x( 'Th', 'weekday short', 'wonom-slider' ),
				_x( 'Fr', 'weekday short', 'wonom-slider' ),
				_x( 'Sa', 'weekday short', 'wonom-slider' ),
				_x( 'Su', 'weekday short', 'wonom-slider' ),
			),
		);
	}

	/**
	 * App shell. Everything inside #wonom-slider-app is rendered by JS.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wonom-appbar">
			<a class="wonom-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>">
				<span class="wonom-logo" aria-hidden="true"><span class="dashicons dashicons-images-alt2"></span></span>
				<span class="wonom-brand-name">Wonom Slider</span>
				<span class="wonom-brand-version"><?php echo esc_html( WONOM_SLIDER_VERSION ); ?></span>
			</a>
			<nav class="wonom-nav" id="wonom-nav" aria-label="Wonom Slider">
				<a href="#slides" data-tab="slides" class="is-active"><?php esc_html_e( 'Slides', 'wonom-slider' ); ?></a>
				<a href="#calendar" data-tab="calendar"><?php esc_html_e( 'Calendar', 'wonom-slider' ); ?></a>
				<a href="#settings" data-tab="settings"><?php esc_html_e( 'Settings', 'wonom-slider' ); ?></a>
			</nav>
			<div class="wonom-appbar-actions">
				<span class="wonom-dirty" id="wonom-dirty" hidden><?php esc_html_e( 'Unsaved changes', 'wonom-slider' ); ?></span>
				<button type="button" class="button button-primary wonom-save" id="wonom-save" disabled><?php esc_html_e( 'Save changes', 'wonom-slider' ); ?></button>
			</div>
		</div>
		<div class="wrap wonom-wrap">
			<noscript><p><?php esc_html_e( 'The slider editor needs JavaScript.', 'wonom-slider' ); ?></p></noscript>
			<div id="wonom-slider-app" class="wonom-app-root" aria-live="polite"></div>
		</div>
		<div class="wonom-toasts" id="wonom-toasts" aria-live="polite"></div>
		<?php
	}
}
