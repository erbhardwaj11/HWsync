<?php
/**
 * Plugin Name: HWsync - Indian PC Component & Multi-Vendor Price Synchronizer
 * Plugin URI: https://github.com/hwsync/hwsync
 * Description: High-performance hardware component and multi-vendor pricing synchronizer for Indian PC retailers. Tracks canonical components, links vendor prices, and updates WordPress posts.
 * Version: 0.0.3.6
 * Author: HWsync Team
 * Author URI: https://github.com/hwsync
 * License: GPL-2.0+
 * Text Domain: hwsync
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HWSYNC_VERSION', '0.0.3.6' );
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
require_once HWSYNC_PLUGIN_DIR . 'includes/class-image-sync-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-sync-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-matching-engine.php';
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
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-theitdepot-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-amazon-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-configurable-vendor-adapter.php';

if ( is_admin() ) {
	require_once HWSYNC_PLUGIN_DIR . 'admin/class-admin.php';
}

require_once HWSYNC_PLUGIN_DIR . 'public/class-public.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/helpers.php';

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
		register_activation_hook( HWSYNC_PLUGIN_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( HWSYNC_PLUGIN_FILE, array( __CLASS__, 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
	}

	public static function activate() {
		try {
			if ( ! function_exists( 'dbDelta' ) ) {
				if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				}
			}
			\HWsync\Database::create_tables();
			\HWsync\Database::seed_default_vendors();
		} catch ( \Throwable $e ) {
		}

		try {
			\HWsync\Cron::init();
			\HWsync\Cron::schedule_events();
		} catch ( \Throwable $e ) {
		}

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	}

	public static function deactivate() {
		try {
			\HWsync\Cron::clear_events();
		} catch ( \Throwable $e ) {
		}

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	}

	public function init() {
		load_plugin_textdomain( 'hwsync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Check for DB schema updates on upgrade only in admin context with permissions
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			try {
				if ( get_option( 'hwsync_db_version' ) !== \HWsync\Database::DB_VERSION ) {
					\HWsync\Database::create_tables();
					\HWsync\Database::seed_default_vendors();
				}
			} catch ( \Throwable $e ) {
			}
		}

		// Init public shortcodes & styles
		try {
			\HWsync\Public_Handler::init();
		} catch ( \Throwable $e ) {
		}

		// Init admin dashboard
		if ( is_admin() ) {
			try {
				\HWsync\Admin::init();
			} catch ( \Throwable $e ) {
			}
		}

		// Init cron runners
		try {
			\HWsync\Cron::init();
		} catch ( \Throwable $e ) {
		}
	}
}

HWsync_Plugin::get_instance();
