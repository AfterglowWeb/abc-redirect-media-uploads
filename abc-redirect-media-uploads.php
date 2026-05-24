<?php
/**
 * Plugin Name: Redirect Media Uploads
 * Plugin URI: https://wordpress.org/plugins/redirect-media-uploads/
 * Description: Redirects or rewrites public WordPress media URLs to a remote uploads base URL. Useful for local, staging, pre-production and production environments sharing the same database without duplicating the uploads directory.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Cédric Moris Kelly
 * Author URI: https://example.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: redirect-media-uploads
 * Domain Path: /languages
 *
 * @package RedirectMediaUploads
 */

namespace Abc\Plugin\RedirectMediaUploads;

defined( 'ABSPATH' ) || exit;

class RedirectMediaUploads {

	protected static $instance = null;

	public const OPTION_BASE_URL = 'abc_redirect_media_uploads_base_url';
	public const OPTION_ENABLED  = 'abc_redirect_media_uploads_enabled';
	public const OPTION_DEBUG    = 'abc_redirect_media_uploads_debug';

	public static function get_instance(): self {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_menu', array( self::class, 'register_options_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( self::class, 'add_plugin_action_links' ) );

		add_filter( 'redirect_canonical', array( self::class, 'disable_canonical_for_uploads' ), 10, 2 );
		add_action( 'template_redirect', array( self::class, 'redirect_medias' ), 1 );

		add_filter( 'wp_get_attachment_url', array( self::class, 'replace_media_base_url' ), 10, 2 );
		add_filter( 'wp_calculate_image_srcset', array( self::class, 'replace_media_srcset_urls' ), 10, 5 );
	}

	public static function register_settings(): void {
		register_setting(
			'abc_redirect_media_uploads',
			self::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_boolean' ),
				'default'           => false,
			)
		);

		register_setting(
			'abc_redirect_media_uploads',
			self::OPTION_DEBUG,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_boolean' ),
				'default'           => false,
			)
		);

		register_setting(
			'abc_redirect_media_uploads',
			self::OPTION_BASE_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_base_url' ),
				'default'           => '',
			)
		);
	}

	public static function register_options_page(): void {
		add_options_page(
			esc_html__( 'Redirect Media Uploads', 'redirect-media-uploads' ),
			esc_html__( 'Redirect Media Uploads', 'redirect-media-uploads' ),
			'manage_options',
			'redirect-media-uploads',
			array( self::class, 'render_options_page' )
		);
	}

	public static function render_options_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Redirect Media Uploads', 'redirect-media-uploads' ); ?></h1>

			<p>
				<?php esc_html_e( 'Redirect or rewrite public media URLs to another WordPress uploads base URL. This is useful when several environments share the same database but do not share the same uploads directory.', 'redirect-media-uploads' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'abc_redirect_media_uploads' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable media URL redirect', 'redirect-media-uploads' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>"
									value="1"
									<?php checked( self::is_enabled(), true ); ?>
								>
								<?php esc_html_e( 'Enable rewriting and redirection of media URLs.', 'redirect-media-uploads' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( self::OPTION_BASE_URL ); ?>">
								<?php esc_html_e( 'Remote uploads base URL', 'redirect-media-uploads' ); ?>
							</label>
						</th>
						<td>
							<input
								type="url"
								class="regular-text"
								id="<?php echo esc_attr( self::OPTION_BASE_URL ); ?>"
								name="<?php echo esc_attr( self::OPTION_BASE_URL ); ?>"
								value="<?php echo esc_attr( self::get_base_url() ); ?>"
								placeholder="https://www.example.com/wp-content/uploads"
							>
							<p class="description">
								<?php esc_html_e( 'Example: https://production.example.com/wp-content/uploads', 'redirect-media-uploads' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Debug logs', 'redirect-media-uploads' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_DEBUG ); ?>"
									value="1"
									<?php checked( self::is_debug_enabled(), true ); ?>
								>
								<?php esc_html_e( 'Write plugin debug messages to the PHP error log.', 'redirect-media-uploads' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Environment constants', 'redirect-media-uploads' ); ?></h2>
			<p><?php esc_html_e( 'You can also define these constants in wp-config.php to override the saved options:', 'redirect-media-uploads' ); ?></p>
            <pre><code>define( 'ABC_REDIRECT_MEDIA_UPLOADS_ENABLED', true );</code></pre>
            <pre><code>define( 'ABC_REDIRECT_MEDIA_UPLOADS_BASE_URL', 'https://production.example.com/wp-content/uploads' );</code></pre>
            <pre><code>define( 'ABC_REDIRECT_MEDIA_UPLOADS_DEBUG', false );</code></pre>
        </div>
    <?php }

	public static function add_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=redirect-media-uploads' ) ),
			esc_html__( 'Settings', 'redirect-media-uploads' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	public static function sanitize_boolean( mixed $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	public static function sanitize_base_url( string $url ): string {
		$url = trim( $url );

		if ( empty( $url ) ) {
			return '';
		}

		return untrailingslashit( esc_url_raw( $url ) );
	}

	public static function get_base_url(): string {
		if (defined('ABC_REDIRECT_MEDIA_UPLOADS_BASE_URL')) {

            /** @var string $base_url */
            $base_url = constant('ABC_REDIRECT_MEDIA_UPLOADS_BASE_URL');

            return untrailingslashit(esc_url_raw($base_url));
        }

        return self::sanitize_base_url( (string) get_option( self::OPTION_BASE_URL, '' ) );

	}

	public static function is_enabled(): bool {
		
		if (defined('ABC_REDIRECT_MEDIA_UPLOADS_ENABLED')) {

            /** @var bool $enabled */
            $enabled = constant('ABC_REDIRECT_MEDIA_UPLOADS_ENABLED');

            return (bool) $enabled;
        }

        return (bool) get_option( self::OPTION_ENABLED, false );

	}

	public static function is_debug_enabled(): bool {
        if (defined('ABC_REDIRECT_MEDIA_UPLOADS_DEBUG')) {

            /** @var bool $debug_enabled */
            $debug_enabled = constant('ABC_REDIRECT_MEDIA_UPLOADS_DEBUG');

            return (bool) $debug_enabled;
        }

        return (bool) get_option( self::OPTION_DEBUG, false );
	}

	public static function disable_canonical_for_uploads( mixed $redirect_url, string $requested_url ): mixed {
		if ( self::is_upload_request() ) {
			return false;
		}

		return $redirect_url;
	}

	public static function redirect_medias(): array {
		self::log( 'redirect_medias() called' );

		if ( ! self::is_enabled() ) {
			self::log( 'disabled' );
			return array();
		}

		$base_url = self::get_base_url();
		self::log( 'base_url: ' . $base_url );

		if ( empty( $base_url ) ) {
			self::log( 'empty base_url' );
			return array();
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		self::log( 'request_uri: ' . $request_uri );

		if ( ! self::is_upload_request() ) {
			self::log( 'not an upload request' );
			return array();
		}

		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		self::log( 'request_path: ' . print_r( $request_path, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		if ( ! $request_path ) {
			self::log( 'empty request_path' );
			return array();
		}

		$upload_dir = wp_get_upload_dir();
		self::log( 'upload_dir: ' . print_r( $upload_dir, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		$local_upload_path = ! empty( $upload_dir['baseurl'] ) ? wp_parse_url( $upload_dir['baseurl'], PHP_URL_PATH ) : '';
		self::log( 'local_upload_path before fallback: ' . print_r( $local_upload_path, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		if ( ! $local_upload_path ) {
			$local_upload_path = '/wp-content/uploads';
		}

		self::log( 'local_upload_path final: ' . $local_upload_path );

		$relative_path = preg_replace(
			'#^' . preg_quote( untrailingslashit( $local_upload_path ), '#' ) . '#',
			'',
			$request_path
		);

		$relative_path = is_string( $relative_path ) ? $relative_path : '';
		self::log( 'relative_path: ' . $relative_path );

		$target_url   = $base_url . '/' . ltrim( $relative_path, '/' );
		$query_string = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';

		if ( ! empty( $query_string ) ) {
			$target_url .= '?' . $query_string;
		}

		self::log( 'target_url: ' . $target_url );
		self::log( 'redirecting...' );

		wp_safe_redirect( esc_url_raw( $target_url ), 302 );
		exit;
	}

	public static function replace_media_base_url( string $url ): string {
		if ( ! self::is_enabled() ) {
			return $url;
		}

		$base_url = self::get_base_url();

		if ( empty( $base_url ) ) {
			return $url;
		}

		$upload_dir = wp_get_upload_dir();

		if ( empty( $upload_dir['baseurl'] ) ) {
			return $url;
		}

		return str_replace(
			untrailingslashit( $upload_dir['baseurl'] ),
			untrailingslashit( $base_url ),
			$url
		);
	}

	public static function replace_media_srcset_urls( array $sources ): array {
		if ( ! self::is_enabled() ) {
			return $sources;
		}

		foreach ( $sources as $width => $source ) {
			if ( ! empty( $source['url'] ) ) {
				$sources[ $width ]['url'] = self::replace_media_base_url( $source['url'] );
			}
		}

		return $sources;
	}

	protected static function is_upload_request(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( empty( $request_uri ) ) {
			return false;
		}

		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! $request_path ) {
			return false;
		}

		$upload_dir  = wp_get_upload_dir();
		$upload_path = ! empty( $upload_dir['baseurl'] ) ? wp_parse_url( $upload_dir['baseurl'], PHP_URL_PATH ) : '';

		if ( ! $upload_path ) {
			$upload_path = '/wp-content/uploads';
		}

		return str_starts_with(
			trailingslashit( $request_path ),
			trailingslashit( $upload_path )
		);
	}

	protected static function log( string $message ): void {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		error_log( '[Redirect Media Uploads] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

RedirectMediaUploads::get_instance();
