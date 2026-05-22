<?php
/**
 * Uninstall Redirect Media Uploads.
 *
 * @package RedirectMediaUploads
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'abc_redirect_media_uploads_base_url' );
delete_option( 'abc_redirect_media_uploads_enabled' );
delete_option( 'abc_redirect_media_uploads_debug' );
