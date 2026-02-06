<?php
/**
 * Plugin Name: Rewrites
 * Description: Save changes to published posts without immediately publishing them, with editorial review workflow and scheduled publishing.
 * Version: 1.0.0
 * Author: Alan
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: rewrites
 *
 * @package Rewrites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REWRITES_VERSION', '1.0.0' );
define( 'REWRITES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REWRITES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload plugin classes.
 */
spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'Rewrites\\';
		$base_dir = REWRITES_PLUGIN_DIR . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialize the plugin.
 */
function rewrites_init() {
	require_once REWRITES_PLUGIN_DIR . 'includes/class-rewrites.php';
	require_once REWRITES_PLUGIN_DIR . 'includes/class-rewrites-staged-revision.php';
	require_once REWRITES_PLUGIN_DIR . 'includes/class-rewrites-rest-controller.php';
	require_once REWRITES_PLUGIN_DIR . 'includes/class-rewrites-cron-handler.php';
	require_once REWRITES_PLUGIN_DIR . 'admin/class-rewrites-admin.php';
	require_once REWRITES_PLUGIN_DIR . 'admin/class-rewrites-settings.php';

	Rewrites::get_instance();
	Rewrites_Cron_Handler::get_instance();
	Rewrites_Admin::get_instance();
	Rewrites_Settings::get_instance();
}
add_action( 'plugins_loaded', 'rewrites_init' );

/**
 * Plugin activation hook.
 */
function rewrites_activate() {
	// Schedule any cleanup cron if needed.
}
register_activation_hook( __FILE__, 'rewrites_activate' );

/**
 * Plugin deactivation hook.
 */
function rewrites_deactivate() {
	// Clear any scheduled cron events.
	wp_clear_scheduled_hook( 'rewrites_publish_staged' );
}
register_deactivation_hook( __FILE__, 'rewrites_deactivate' );
