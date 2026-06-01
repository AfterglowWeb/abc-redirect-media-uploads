<?php
/**
 * Plugin Name: Remote Media Sync
 * Plugin URI: https://wordpress.org/plugins/remote-media-sync/
 * Description: Rewrite media URLs to a remote WordPress uploads directory and optionally download files locally while visitors browse your site.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: bromate
 * Author URI: https://www.moriskelly.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: remote-media-sync
 * Domain Path: /languages
 *
 * @package RemoteMediaSync
 */

defined( 'ABSPATH' ) || exit;
define('REMOTE_MEDIA_SYNC_PLUGIN_BASENAME', plugin_basename( __FILE__ ));
include_once plugin_dir_path( __FILE__ ) . 'inc/class-remotemediasync.php';
Abc\Plugin\RemoteMediaSync\RemoteMediaSync::get_instance();
