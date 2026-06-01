<?php
/**
 * Uninstall Remote Media Sync plugin.
 *
 * @package RemoteMediaSync
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'abc_remote_media_sync_base_url' );
delete_option( 'abc_remote_media_sync_enabled' );
delete_option( 'abc_remote_media_sync_download_while_navigating' );
delete_option( 'abc_remote_media_sync_debug' );