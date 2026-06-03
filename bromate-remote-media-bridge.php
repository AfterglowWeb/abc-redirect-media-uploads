<?php
/**
 * Plugin Name: Bromate Remote Media Bridge
 * Plugin URI: https://wordpress.org/plugins/bromate-remote-media-bridge/
 * Description: Use a remote WordPress uploads directory without copying your entire media library.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 7.0 
 * Requires PHP: 7.4
 * Author: bromate
 * Author URI: https://www.moriskelly.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bromate-remote-media-bridge
 * Domain Path: /languages
 *
 * @package BromateRemoteMediaBridge
 */

defined( 'ABSPATH' ) || exit;
define('BROMATE_REMOTE_MEDIA_BRIDGE_PLUGIN_BASENAME', plugin_basename( __FILE__ ));
include_once plugin_dir_path( __FILE__ ) . 'inc/class-remotemediabridge.php';
Bromate\Plugin\RemoteMediaBridge\RemoteMediaBridge::get_instance();
