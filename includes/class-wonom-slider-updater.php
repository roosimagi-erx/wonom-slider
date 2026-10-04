<?php
/**
 * Self-contained GitHub Releases updater.
 *
 * How it works:
 *  - Every 6 hours (or when "Check for updates" is clicked) the latest release of
 *    WONOM_SLIDER_GITHUB_OWNER/WONOM_SLIDER_GITHUB_REPO is fetched from the GitHub API.
 *  - If the release tag (e.g. "v1.2.0" or "1.2.0") is newer than the installed version,
 *    WordPress shows the normal update notice and installs it like any other plugin.
 *  - The download is the release asset named "wonom-slider.zip" when present
 *    (built by .github/workflows/release.yml), otherwise GitHub's automatic zipball.
 *  - For private repositories define WONOM_SLIDER_GITHUB_TOKEN in wp-config.php.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Updater {

	const TRANSIENT = 'wonom_slider_github_release';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	private static $slug;

	public static function init() {
		self::$slug = dirname( WONOM_SLIDER_BASENAME );
		if ( '.' === self::$slug ) {
			self::$slug = 'wonom-slider';
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_folder' ), 10, 4 );
		add_filter( 'http_request_args', array( __CLASS__, 'auth_header' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_force_check' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_update' ), 10, 2 );
	}

	/* ---------- release data ---------- */

	/**
	 * "owner/repo" – from settings if set, else from constants.
	 */
	public static function repo() {
		$adv = Wonom_Slider_Data::get_advanced();
		if ( ! empty( $adv['update_repo'] ) ) {
			return $adv['update_repo'];
		}
		return WONOM_SLIDER_GITHUB_OWNER . '/' . WONOM_SLIDER_GITHUB_REPO;
	}

	/**
	 * Access token for private repositories (constant wins over setting).
	 */
	public static function token() {
		if ( defined( 'WONOM_SLIDER_GITHUB_TOKEN' ) && WONOM_SLIDER_GITHUB_TOKEN ) {
			return (string) WONOM_SLIDER_GITHUB_TOKEN;
		}
		$adv = Wonom_Slider_Data::get_advanced();
		return isset( $adv['update_token'] ) ? (string) $adv['update_token'] : '';
	}

	public static function repo_url() {
		return 'https://github.com/' . self::repo();
	}

	/**
	 * Fetch (cached) latest release info.
	 *
	 * @param bool $force Bypass cache.
	 * @return array|null { version, zip, url, body, published, requires, requires_php }
	 */
	public static function get_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return isset( $cached['version'] ) ? $cached : null;
			}
		}

		$api = 'https://api.github.com/repos/' . self::repo() . '/releases/latest';
		$res = wp_remote_get(
			$api,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'wonom-slider/' . WONOM_SLIDER_VERSION . '; ' . home_url(),
				),
			)
		);

		$release = array();
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$json = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( is_array( $json ) && ! empty( $json['tag_name'] ) && empty( $json['draft'] ) && empty( $json['prerelease'] ) ) {
				$zip   = '';
				$token = self::token();
				if ( ! empty( $json['assets'] ) && is_array( $json['assets'] ) ) {
					foreach ( $json['assets'] as $asset ) {
						if ( ! empty( $asset['name'] ) && preg_match( '/\.zip$/i', $asset['name'] ) ) {
							// Private repos must download assets through the API URL with a token.
							$zip = ( $token && ! empty( $asset['url'] ) ) ? $asset['url'] : $asset['browser_download_url'];
							if ( 'wonom-slider.zip' === $asset['name'] ) {
								break;
							}
						}
					}
				}
				if ( '' === $zip ) {
					$zip = $json['zipball_url'];
				}
				$body    = isset( $json['body'] ) ? (string) $json['body'] : '';
				$release = array(
					'version'      => ltrim( $json['tag_name'], 'vV' ),
					'zip'          => $zip,
					'url'          => $json['html_url'],
					'body'         => $body,
					'published'    => isset( $json['published_at'] ) ? $json['published_at'] : '',
					'requires'     => self::meta_from_body( $body, 'Requires at least' ),
					'requires_php' => self::meta_from_body( $body, 'Requires PHP' ),
				);
			}
		}

		// Cache even failures (empty array) so a GitHub outage does not slow every admin page.
		set_site_transient( self::TRANSIENT, $release, $release ? self::CACHE_TTL : HOUR_IN_SECONDS );

		return $release ? $release : null;
	}

	private static function meta_from_body( $body, $label ) {
		if ( preg_match( '/' . preg_quote( $label, '/' ) . '\s*:\s*([0-9.]+)/i', $body, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/* ---------- WordPress hooks ---------- */

	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$release = self::get_release();
		if ( ! $release ) {
			return $transient;
		}

		$item = (object) array(
			'id'            => self::repo_url(),
			'slug'          => self::$slug,
			'plugin'        => WONOM_SLIDER_BASENAME,
			'new_version'   => $release['version'],
			'url'           => self::repo_url(),
			'package'       => $release['zip'],
			'icons'         => array(),
			'banners'       => array(),
			'tested'        => '',
			'requires'      => $release['requires'],
			'requires_php'  => $release['requires_php'],
			'compatibility' => new stdClass(),
		);

		if ( version_compare( $release['version'], WONOM_SLIDER_VERSION, '>' ) ) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ WONOM_SLIDER_BASENAME ] = $item;
			if ( isset( $transient->no_update[ WONOM_SLIDER_BASENAME ] ) ) {
				unset( $transient->no_update[ WONOM_SLIDER_BASENAME ] );
			}
		} else {
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ WONOM_SLIDER_BASENAME ] = $item;
			if ( isset( $transient->response[ WONOM_SLIDER_BASENAME ] ) ) {
				unset( $transient->response[ WONOM_SLIDER_BASENAME ] );
			}
		}
		return $transient;
	}

	/**
	 * "View details" popup.
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== self::$slug ) {
			return $result;
		}
		$release = self::get_release();
		if ( ! $release ) {
			return $result;
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin = get_plugin_data( WONOM_SLIDER_FILE, false, false );

		return (object) array(
			'name'          => $plugin['Name'],
			'slug'          => self::$slug,
			'version'       => $release['version'],
			'author'        => $plugin['Author'],
			'author_profile' => $plugin['AuthorURI'],
			'homepage'      => self::repo_url(),
			'requires'      => $release['requires'] ? $release['requires'] : $plugin['RequiresWP'],
			'requires_php'  => $release['requires_php'] ? $release['requires_php'] : $plugin['RequiresPHP'],
			'last_updated'  => $release['published'],
			'download_link' => $release['zip'],
			'trunk'         => $release['zip'],
			'sections'      => array(
				'description' => wpautop( esc_html( $plugin['Description'] ) ),
				'changelog'   => self::markdown_lite( $release['body'] ),
			),
			'banners'       => array(),
		);
	}

	/**
	 * GitHub zipballs extract into "owner-repo-hash/". Rename to the plugin slug so the update replaces the right folder.
	 */
	public static function fix_source_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( empty( $hook_extra['plugin'] ) || WONOM_SLIDER_BASENAME !== $hook_extra['plugin'] ) {
			return $source;
		}
		$desired = trailingslashit( $remote_source ) . self::$slug . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}
		return $source;
	}

	/**
	 * Auth header for private repos.
	 */
	public static function auth_header( $args, $url ) {
		$token = self::token();
		if ( ! $token ) {
			return $args;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( in_array( $host, array( 'api.github.com', 'codeload.github.com' ), true ) ) {
			if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
				$args['headers'] = array();
			}
			$args['headers']['Authorization'] = 'Bearer ' . $token;
			// Private release assets need this Accept header to get the binary.
			if ( 'api.github.com' === $host && false !== strpos( $url, '/releases/assets/' ) ) {
				$args['headers']['Accept'] = 'application/octet-stream';
			}
		}
		return $args;
	}

	public static function row_meta( $links, $file ) {
		if ( WONOM_SLIDER_BASENAME !== $file ) {
			return $links;
		}
		$url     = wp_nonce_url( add_query_arg( array( 'wonom_slider_check_update' => 1 ), self_admin_url( 'plugins.php' ) ), 'wonom_slider_check_update' );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'wonom-slider' ) . '</a>';
		$links[] = '<a href="' . esc_url( self::repo_url() ) . '" target="_blank" rel="noopener">GitHub</a>';
		return $links;
	}

	public static function handle_force_check() {
		if ( empty( $_GET['wonom_slider_check_update'] ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		check_admin_referer( 'wonom_slider_check_update' );
		delete_site_transient( self::TRANSIENT );
		$release = self::get_release( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		$msg = $release
			? sprintf( /* translators: 1: latest version 2: installed version */ __( 'Latest release on GitHub: %1$s (installed: %2$s).', 'wonom-slider' ), $release['version'], WONOM_SLIDER_VERSION )
			: __( 'Could not reach GitHub or no release published yet.', 'wonom-slider' );
		set_transient( 'wonom_slider_update_notice', $msg, 60 );
		wp_safe_redirect( self_admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * Status array for the settings screen / REST.
	 *
	 * @param bool $force Re-check now.
	 * @return array
	 */
	public static function status( $force = false ) {
		if ( $force ) {
			delete_site_transient( self::TRANSIENT );
			delete_site_transient( 'update_plugins' );
		}
		$release = self::get_release( $force );
		$status  = array(
			'installed' => WONOM_SLIDER_VERSION,
			'repo'      => self::repo(),
			'repo_url'  => self::repo_url(),
			'latest'    => $release ? $release['version'] : '',
			'release_url' => $release ? $release['url'] : '',
			'published' => $release ? $release['published'] : '',
			'has_update' => $release ? version_compare( $release['version'], WONOM_SLIDER_VERSION, '>' ) : false,
			'ok'        => (bool) $release,
			// Raw URL (no &amp; entities) – it is used from JavaScript.
			'update_url' => add_query_arg(
				array(
					'action'   => 'upgrade-plugin',
					'plugin'   => rawurlencode( WONOM_SLIDER_BASENAME ),
					'_wpnonce' => wp_create_nonce( 'upgrade-plugin_' . WONOM_SLIDER_BASENAME ),
				),
				self_admin_url( 'update.php' )
			),
		);
		if ( $force && $release ) {
			wp_update_plugins();
		}
		return $status;
	}

	public static function after_update( $upgrader, $hook_extra ) {
		if ( isset( $hook_extra['type'] ) && 'plugin' === $hook_extra['type'] ) {
			delete_site_transient( self::TRANSIENT );
		}
	}

	/**
	 * Tiny markdown → HTML for release notes (headings, lists, bold, code, links).
	 */
	public static function markdown_lite( $md ) {
		$md = esc_html( (string) $md );
		if ( '' === trim( $md ) ) {
			return '<p>' . esc_html__( 'No release notes.', 'wonom-slider' ) . '</p>';
		}
		$lines = preg_split( '/\r\n|\r|\n/', $md );
		$html  = '';
		$in_ul = false;
		foreach ( $lines as $line ) {
			$t = trim( $line );
			if ( preg_match( '/^[-*]\s+(.*)$/', $t, $m ) ) {
				if ( ! $in_ul ) {
					$html .= '<ul>';
					$in_ul = true;
				}
				$html .= '<li>' . self::inline_md( $m[1] ) . '</li>';
				continue;
			}
			if ( $in_ul ) {
				$html .= '</ul>';
				$in_ul = false;
			}
			if ( preg_match( '/^(#{1,6})\s+(.*)$/', $t, $m ) ) {
				$lvl   = min( 4, strlen( $m[1] ) + 2 );
				$html .= '<h' . $lvl . '>' . self::inline_md( $m[2] ) . '</h' . $lvl . '>';
			} elseif ( '' !== $t ) {
				$html .= '<p>' . self::inline_md( $t ) . '</p>';
			}
		}
		if ( $in_ul ) {
			$html .= '</ul>';
		}
		return $html;
	}

	private static function inline_md( $s ) {
		$s = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s );
		$s = preg_replace( '/`(.+?)`/', '<code>$1</code>', $s );
		$s = preg_replace( '/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $s );
		return $s;
	}
}
