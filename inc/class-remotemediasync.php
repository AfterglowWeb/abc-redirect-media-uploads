<?php
/**
 * Remote Media Sync.
 *
 * @package RemoteMediaSync
 */

namespace Abc\Plugin\RemoteMediaSync;

defined( 'ABSPATH' ) || exit;

use Exception;

/**
 * Main plugin class for Remote Media Sync.
 *
 * Handles admin settings, media URL rewriting, upload request redirects,
 * excluded URLs, debug logging, and optional lazy local media downloads
 * while visitors browse the site.
 */
class RemoteMediaSync {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	public const OPTION_BASE_URL                  = 'abc_remote_media_sync_base_url';
	public const OPTION_EXCLUDED_URLS             = 'abc_remote_media_sync_excluded_urls';
	public const OPTION_ENABLED                   = 'abc_remote_media_sync_enabled';
	public const OPTION_DOWNLOAD_WHILE_NAVIGATING = 'abc_remote_media_sync_download_while_navigating';
	public const OPTION_DEBUG                     = 'abc_remote_media_sync_debug';

	/**
	 * Get the singleton plugin instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Register WordPress hooks.
	 */
	private function __construct() {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_menu', array( self::class, 'register_options_page' ) );
		add_filter( 'plugin_action_links_' . REMOTE_MEDIA_SYNC_PLUGIN_BASENAME, array( self::class, 'add_plugin_action_links' ) );

		add_filter( 'redirect_canonical', array( self::class, 'disable_canonical_for_uploads' ), 10, 1 );
		add_action( 'template_redirect', array( self::class, 'redirect_medias' ), 1 );

		add_filter( 'wp_get_attachment_url', array( self::class, 'replace_media_base_url' ), 10, 2 );
		add_filter( 'wp_calculate_image_srcset', array( self::class, 'replace_media_srcset_urls' ), 10, 5 );
	}

	/**
	 * Register plugin settings.
	 *
	 * Registers all plugin options and their sanitization callbacks
	 * with the WordPress Settings API.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			'remote_media_sync_uploads',
			self::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_boolean' ),
				'default'           => false,
			)
		);

		register_setting(
			'remote_media_sync_uploads',
			self::OPTION_DEBUG,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_boolean' ),
				'default'           => false,
			)
		);

		register_setting(
			'remote_media_sync_uploads',
			self::OPTION_BASE_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_base_url' ),
				'default'           => '',
			)
		);

		register_setting(
			'remote_media_sync_uploads',
			self::OPTION_EXCLUDED_URLS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_excluded_urls' ),
				'default'           => '',
			)
		);

		register_setting(
			'remote_media_sync_uploads',
			self::OPTION_DOWNLOAD_WHILE_NAVIGATING,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_boolean' ),
				'default'           => false,
			)
		);
	}

	/**
	 * Register the plugin settings page.
	 *
	 * Adds a settings page under the WordPress Settings menu.
	 *
	 * @return void
	 */
	public static function register_options_page(): void {
		add_options_page(
			esc_html__( 'Redirect Media Uploads', 'remote-media-sync' ),
			esc_html__( 'Redirect Media Uploads', 'remote-media-sync' ),
			'manage_options',
			'remote-media-sync',
			array( self::class, 'render_options_page' )
		);
	}

	/**
	 * Render the plugin settings page.
	 *
	 * Outputs the administration interface used to configure
	 * media URL rewriting, exclusions and local media caching.
	 *
	 * @return void
	 */
	public static function render_options_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Redirect Media Uploads', 'remote-media-sync' ); ?></h1>

			<p>
				<?php esc_html_e( 'Redirect or rewrite public media URLs to another WordPress uploads base URL. This is useful when several environments share the same database but do not share the same uploads directory.', 'remote-media-sync' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'remote_media_sync_uploads' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable media URL redirect', 'remote-media-sync' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>"
									value="1"
									<?php checked( self::is_enabled(), true ); ?>
								>
								<?php esc_html_e( 'Enable rewriting and redirection of media URLs.', 'remote-media-sync' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Download while navigating', 'remote-media-sync' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_DOWNLOAD_WHILE_NAVIGATING ); ?>"
									value="1"
									<?php checked( self::should_download_while_navigating(), true ); ?>
								>
								<?php esc_html_e( 'Progressively download remote media locally when visitors browse pages using those images.', 'remote-media-sync' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'This uses normal navigation to progressively warm the local uploads folder. It may not work if the source site blocks remote downloads, hotlinking, server-to-server requests, or applies strict rate limiting.', 'remote-media-sync' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( self::OPTION_BASE_URL ); ?>">
								<?php esc_html_e( 'Remote uploads base URL', 'remote-media-sync' ); ?>
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
								<?php esc_html_e( 'Example: https://production.example.com/wp-content/uploads', 'remote-media-sync' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( self::OPTION_EXCLUDED_URLS ); ?>">
								<?php esc_html_e( 'Excluded URLs', 'remote-media-sync' ); ?>
							</label>
						</th>
						<td>
							<textarea
								class="large-text code"
								rows="8"
								id="<?php echo esc_attr( self::OPTION_EXCLUDED_URLS ); ?>"
								name="<?php echo esc_attr( self::OPTION_EXCLUDED_URLS ); ?>"
								placeholder="<?php echo esc_attr( home_url( '/wp-content/uploads/2026/01/example.jpg' ) ); ?>"
							><?php echo esc_textarea( self::get_excluded_urls_raw() ); ?></textarea>

							<p class="description">
								<?php esc_html_e( 'One absolute URL per line. URLs must belong to the current domain.', 'remote-media-sync' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><?php esc_html_e( 'Debug logs', 'remote-media-sync' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_DEBUG ); ?>"
									value="1"
									<?php checked( self::is_debug_enabled(), true ); ?>
								>
								<?php esc_html_e( 'Write plugin debug messages to the PHP error log.', 'remote-media-sync' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<div class="wrap" style="margin-top: 2em; padding: 1em; background: #fff; border: 1px solid #ddd;">
				<h2><?php esc_html_e( 'Environment constants', 'remote-media-sync' ); ?></h2>
				<p><?php esc_html_e( 'You can also define these constants in wp-config.php to override the saved options:', 'remote-media-sync' ); ?></p>
				<code style="display:block;word-break: break-all;margin: 10px 0;">define( 'REMOTE_MEDIA_SYNC_ENABLED', true );</code>
				<code style="display:block;word-break: break-all;margin: 10px 0;">define( 'REMOTE_MEDIA_SYNC_BASE_URL', 'https://production.example.com/wp-content/uploads' );</code>
				<code style="display:block;word-break: break-all;margin: 10px 0;">define( 'REMOTE_MEDIA_SYNC_EXCLUDED_URLS', "https://example.com/wp-content/uploads/file.jpg\nhttps://example.com/wp-content/uploads/other.jpg" );</code>
				<code style="display:block;word-break: break-all;margin: 10px 0;">define( 'REMOTE_MEDIA_SYNC_DEBUG', false );</code>
			</div>
		</div>
		<div class="wrap" style="margin-top: 2em;">
			<p class="description">
				<?php esc_html_e( 'Remote Media Sync is open source, ', 'remote-media-sync' ); ?>
				<a href="https://github.com/AfterglowWeb/abc-redirect-media-uploads"
				target="_blank"
				style="text-decoration: underline;"
				rel="noopener noreferrer"><?php esc_html_e( 'see plugin Github', 'remote-media-sync' ); ?></a><br>
				<?php esc_html_e( 'You can thank me through PayPal or Monero:', 'remote-media-sync' ); ?>
				<br>
				<a href="https://www.paypal.com/donate/?business=HDV38XURDEFEA&no_recurring=0&item_name=Remote+Media+Sync+is+free+and+open+source.%0AIf+the+plugin+saves+you+time%2C+consider+supporting+its+development.&currency_code=EUR"
					target="_blank"
					style="text-decoration: underline;"
					rel="noopener noreferrer">
					<?php esc_html_e( 'PayPal Donate Link', 'remote-media-sync' ); ?>
				</a><br>
				<span><?php esc_html_e( 'Monero Address', 'remote-media-sync' ); ?></span><br>
				<code style="color: var(--wp-admin-theme-color);">87uTq2B99YmNX7Nn9QaEiL6TJugAfCvCHiEEZEVES1xwBQhmrkEzniY8wfegthAYJMZMr8taBqWRSYozRhsXSZbjGxV5LCC</code>
			</p>
		</div>
		<?php
	}

	/**
	 * Add a settings link to the plugin row.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public static function add_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=remote-media-sync' ) ),
			esc_html__( 'Settings', 'remote-media-sync' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Sanitize a boolean option value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return bool Sanitized boolean.
	 */
	public static function sanitize_boolean( mixed $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize a base URL option.
	 *
	 * @param string $url URL to sanitize.
	 * @return string Sanitized URL without trailing slash.
	 */
	public static function sanitize_base_url( string $url ): string {
		$url = trim( $url );

		if ( empty( $url ) ) {
			return '';
		}

		return untrailingslashit( esc_url_raw( $url ) );
	}

	/**
	 * Get the configured remote uploads base URL.
	 *
	 * Constants take precedence over saved plugin options.
	 *
	 * @return string Remote uploads base URL.
	 */
	public static function get_base_url(): string {
		if ( defined( 'REMOTE_MEDIA_SYNC_BASE_URL' ) ) {

			$base_url = constant( 'REMOTE_MEDIA_SYNC_BASE_URL' );

			return untrailingslashit( esc_url_raw( $base_url ) );
		}

		return self::sanitize_base_url( (string) get_option( self::OPTION_BASE_URL, '' ) );
	}

	/**
	 * Determine whether media URL rewriting is enabled.
	 *
	 * Constants take precedence over saved plugin options.
	 *
	 * @return bool True if enabled.
	 */
	public static function is_enabled(): bool {

		if ( defined( 'REMOTE_MEDIA_SYNC_ENABLED' ) ) {

			$enabled = constant( 'REMOTE_MEDIA_SYNC_ENABLED' );

			return (bool) $enabled;
		}

		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Determine whether debug logging is enabled.
	 *
	 * Constants take precedence over saved plugin options.
	 *
	 * @return bool True if debug logging is enabled.
	 */
	public static function is_debug_enabled(): bool {
		if ( defined( 'REMOTE_MEDIA_SYNC_DEBUG' ) ) {

			$debug_enabled = constant( 'REMOTE_MEDIA_SYNC_DEBUG' );

			return (bool) $debug_enabled;
		}

		return (bool) get_option( self::OPTION_DEBUG, false );
	}

	/**
	 * Disable WordPress canonical redirects for upload requests.
	 *
	 * @param mixed $redirect_url Canonical redirect URL.
	 * @return mixed False for upload requests, original value otherwise.
	 */
	public static function disable_canonical_for_uploads( mixed $redirect_url ): mixed {
		if ( self::is_upload_request() ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Redirect upload requests to the remote uploads directory.
	 *
	 * @return array Empty array when no redirect is performed.
	 */
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

		$current_url = home_url( $request_uri );

		if ( self::is_excluded_url( $current_url ) ) {
			self::log( 'excluded url: ' . $current_url );
			return array();
		}
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

	/**
	 * Replace a local media URL with its remote equivalent.
	 *
	 * Optionally triggers background media download when enabled.
	 *
	 * @param string $url           Original media URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string Rewritten media URL.
	 */
	public static function replace_media_base_url( string $url, int $attachment_id = 0 ): string {
		if ( ! self::is_enabled() ) {
			return $url;
		}

		if ( self::is_excluded_url( $url ) ) {
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

		$remote_url = str_replace(
			untrailingslashit( $upload_dir['baseurl'] ),
			untrailingslashit( $base_url ),
			$url
		);

		if ( $attachment_id > 0 ) {
			self::maybe_download_attachment_while_navigating( $attachment_id, $remote_url );
		}

		return $remote_url;
	}

	/**
	 * Replace srcset URLs with remote media URLs.
	 *
	 * @param array $sources Image srcset sources.
	 * @return array Updated srcset sources.
	 */
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

	/**
	 * Determine whether the current request targets the uploads directory.
	 *
	 * @return bool True when the current request is for a media file.
	 */
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

	/**
	 * Write a debug message to the PHP error log.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	protected static function log( string $message ): void {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		error_log( '[Redirect Media Uploads] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Sanitize a list of excluded media URLs.
	 *
	 * Only URLs belonging to the current site are retained.
	 *
	 * @param string $value Raw textarea value.
	 * @return string Sanitized URL list.
	 */
	public static function sanitize_excluded_urls( string $value ): string {
		$lines = preg_split( '/\R/', $value );
		$urls  = array();

		if ( ! is_array( $lines ) ) {
			return '';
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( $lines as $line ) {
			$url = trim( $line );

			if ( empty( $url ) ) {
				continue;
			}

			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			$host   = wp_parse_url( $url, PHP_URL_HOST );

			if (
				! in_array( $scheme, array( 'http', 'https' ), true )
				|| empty( $host )
				|| strtolower( (string) $host ) !== strtolower( (string) $home_host )
			) {
				continue;
			}

			$urls[] = esc_url_raw( $url );
		}

		$urls = array_values( array_unique( $urls ) );

		return implode( "\n", $urls );
	}

	/**
	 * Get the raw excluded URLs configuration.
	 *
	 * Constants take precedence over saved plugin options.
	 *
	 * @return string Raw excluded URLs list.
	 */
	public static function get_excluded_urls_raw(): string {
		if ( defined( 'REMOTE_MEDIA_SYNC_EXCLUDED_URLS' ) ) {
			return self::sanitize_excluded_urls(
				(string) constant( 'REMOTE_MEDIA_SYNC_EXCLUDED_URLS' )
			);
		}

		return self::sanitize_excluded_urls(
			(string) get_option( self::OPTION_EXCLUDED_URLS, '' )
		);
	}

	/**
	 * Get the list of excluded media URLs.
	 *
	 * @return string[] List of excluded URLs.
	 */
	public static function get_excluded_urls(): array {
		$raw = self::get_excluded_urls_raw();

		if ( empty( $raw ) ) {
			return array();
		}

		$excluded_parts = array_map( 'trim', preg_split( '/\R/', $raw ) );

		return array_filter( $excluded_parts );
	}

	/**
	 * Determine whether a URL is excluded from rewriting.
	 *
	 * Comparison is performed using URL paths.
	 *
	 * @param string $url URL to check.
	 * @return bool True when excluded.
	 */
	public static function is_excluded_url( string $url ): bool {
		$url_parts = wp_parse_url( $url );

		if ( empty( $url_parts['path'] ) ) {
			return false;
		}

		$url_path = untrailingslashit( $url_parts['path'] );

		foreach ( self::get_excluded_urls() as $excluded_url ) {
			$excluded_parts = wp_parse_url( $excluded_url );

			if ( empty( $excluded_parts['path'] ) ) {
				continue;
			}

			$excluded_path = untrailingslashit( $excluded_parts['path'] );

			if ( $url_path === $excluded_path ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether media should be downloaded while browsing.
	 *
	 * Constants take precedence over saved plugin options.
	 *
	 * @return bool True if enabled.
	 */
	public static function should_download_while_navigating(): bool {
		if ( defined( 'REMOTE_MEDIA_SYNC_DOWNLOAD_WHILE_NAVIGATING' ) ) {
			return (bool) constant( 'REMOTE_MEDIA_SYNC_DOWNLOAD_WHILE_NAVIGATING' );
		}

		return (bool) get_option( self::OPTION_DOWNLOAD_WHILE_NAVIGATING, false );
	}

	/**
	 * Download a remote media file locally when first encountered.
	 *
	 * Prevents duplicate downloads using attachment metadata
	 * and temporary locking.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $remote_url    Remote media URL.
	 * @return void
	 */
	protected static function maybe_download_attachment_while_navigating( int $attachment_id, string $remote_url ): void {
		if ( ! self::should_download_while_navigating() ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( get_post_meta( $attachment_id, '_remote_media_sync_downloaded', true ) ) {
			return;
		}

		if ( get_transient( 'remote_media_sync_lock_' . $attachment_id ) ) {
			return;
		}

		set_transient( 'remote_media_sync_lock_' . $attachment_id, 1, 10 * MINUTE_IN_SECONDS );

		$local_file = get_attached_file( $attachment_id );

		if ( $local_file && file_exists( $local_file ) ) {
			update_post_meta( $attachment_id, '_remote_media_sync_downloaded', current_time( 'mysql' ) );
			delete_transient( 'remote_media_sync_lock_' . $attachment_id );
			return;
		}

		self::download_remote_file_for_attachment( $attachment_id, $remote_url );
	}

	/**
	 * Download a remote media file into the local attachment file path.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $remote_url    Remote media URL.
	 * @return bool True on success, false on failure.
	 */
	protected static function download_remote_file_for_attachment( int $attachment_id, string $remote_url ): bool {
		$local_file = get_attached_file( $attachment_id );

		if ( empty( $local_file ) ) {
			delete_transient( 'remote_media_sync_lock_' . $attachment_id );
			return false;
		}

		wp_mkdir_p( dirname( $local_file ) );

		$response = wp_remote_get(
			$remote_url,
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'headers'     => array(
					'User-Agent' => 'Mozilla/5.0 WordPress Media Warmup',
					'Accept'     => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
					'Referer'    => home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log( 'download failed: ' . $response->get_error_message() );
			delete_transient( 'remote_media_sync_lock_' . $attachment_id );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			self::log( 'download failed HTTP ' . $code . ': ' . $remote_url );
			delete_transient( 'remote_media_sync_lock_' . $attachment_id );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			delete_transient( 'remote_media_sync_lock_' . $attachment_id );
			return false;
		}

		self::write_file( $local_file, $body );

		update_post_meta( $attachment_id, '_remote_media_sync_downloaded', current_time( 'mysql' ) );
		update_post_meta( $attachment_id, '_remote_media_sync_source_url', esc_url_raw( $remote_url ) );

		delete_transient( 'remote_media_sync_lock_' . $attachment_id );

		return true;
	}

	/**
	 * Get the WordPress filesystem instance.
	 *
	 * @return \WP_Filesystem_Base|null Filesystem instance, or null on failure.
	 */
	public static function wp_filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			try {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return null;
			}
		}

		return $wp_filesystem;
	}

	/**
	 * Write content to a local file using the WordPress filesystem API.
	 *
	 * @param string $file_path Absolute file path.
	 * @param string $content   File content.
	 * @return bool True on success, false on failure.
	 */
	public static function write_file( string $file_path, string $content ): bool {
		$wp_filesystem = self::wp_filesystem();

		if ( ! $wp_filesystem ) {
			return false;
		}

		$dir = dirname( $file_path );

		if ( ! $wp_filesystem->is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
		}

		if ( ! $wp_filesystem->is_writable( $dir ) ) {
			return false;
		}

		return $wp_filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE );
	}
}