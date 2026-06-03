<?php
/**
 * Uninstall Bromate Remote Media Bridge plugin.
 *
 * @package RemoteMediaBridge
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'bromate_remote_media_bridge_base_url' );
delete_option( 'bromate_remote_media_bridge_enabled' );
delete_option( 'bromate_remote_media_bridge_download_while_navigating' );
delete_option( 'bromate_remote_media_bridge_debug' );