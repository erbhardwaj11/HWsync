<?php
/**
 * Plugin Name: HWsync - Indian PC Component & Multi-Vendor Price Synchronizer
 * Plugin URI: https://github.com/hwsync/hwsync
 * Description: High-performance hardware component and multi-vendor pricing synchronizer for Indian PC retailers. Tracks canonical components, links vendor prices, and updates WordPress posts.
 * Version: 0.0.0.1
 * Author: HWsync Team
 * Author URI: https://github.com/hwsync
 * License: GPL-2.0+
 * Text Domain: hwsync
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HWSYNC_VERSION', '0.0.0.1' );
define( 'HWSYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HWSYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HWSYNC_PLUGIN_FILE', __FILE__ );

/**
 * Autoloader for HWsync classes.
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'HWsync\\';
	$base_dir = HWSYNC_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$parts = explode( '\\', $relative_class );
	$file_name = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';
	$subdir = ! empty( $parts ) ? strtolower( implode( '/', $parts ) ) . '/' : '';

	$file = $base_dir . $subdir . $file_name;

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Include core procedural files and admin if needed
require_once HWSYNC_PLUGIN_DIR . 'includes/class-database.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-backup-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-specs-sync-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-sync-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-matching-engine.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-post-sync-processor.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-cron.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-component.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-vendor.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-vendor-price.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/abstract-vendor-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-mdcomputers-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-vedant-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-primeabgb-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-elitehubs-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-pcstudio-adapter.php';

if ( is_admin() ) {
	require_once HWSYNC_PLUGIN_DIR . 'admin/class-admin.php';
}

require_once HWSYNC_PLUGIN_DIR . 'public/class-public.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once HWSYNC_PLUGIN_DIR . 'includes/class-cli.php';
}

/**
 * Main Plugin Bootstrap
 */
class HWsync_Plugin {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( HWSYNC_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( HWSYNC_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
	}

	public function activate() {
		\HWsync\Database::create_tables();
		\HWsync\Database::seed_default_vendors();
		\HWsync\Cron::schedule_events();
		\HWsync\Post_Sync_Processor::register_post_type();
		flush_rewrite_rules();
	}

	public function deactivate() {
		\HWsync\Cron::clear_events();
		flush_rewrite_rules();
	}

	public function init() {
		load_plugin_textdomain( 'hwsync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Register custom post type & taxonomies on init
		\HWsync\Post_Sync_Processor::register_post_type();

		// Init public shortcodes & styles
		\HWsync\Public_Handler::init();

		// Init admin dashboard
		if ( is_admin() ) {
			\HWsync\Admin::init();
		}

		// Init cron runners
		\HWsync\Cron::init();
	}
}

HWsync_Plugin::get_instance();
