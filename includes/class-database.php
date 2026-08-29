<?php
namespace HWsync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {
	const DB_VERSION = '1.0.2';

	public static function get_table_name( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'hwsync_' . $name;
	}

	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		if ( ! function_exists( 'dbDelta' ) ) {
			if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			} elseif ( defined( 'ABSPATH' ) && file_exists( rtrim( ABSPATH, '/\\' ) . '/wp-admin/includes/upgrade.php' ) ) {
				require_once rtrim( ABSPATH, '/\\' ) . '/wp-admin/includes/upgrade.php';
			}
		}

		$vendors_table = self::get_table_name( 'vendors' );
		$components_table = self::get_table_name( 'components' );
		$prices_table = self::get_table_name( 'vendor_prices' );
		$history_table = self::get_table_name( 'price_history' );

		// 1. Vendors Table
		$sql_vendors = "CREATE TABLE {$vendors_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vendor_slug varchar(64) NOT NULL,
			vendor_name varchar(128) NOT NULL,
			base_url varchar(255) NOT NULL,
			adapter_class varchar(128) DEFAULT NULL,
			sync_method varchar(64) NOT NULL DEFAULT 'curl_html',
			config_json longtext DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			last_sync_at datetime DEFAULT NULL,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY vendor_slug (vendor_slug)
		) {$charset_collate};";

		// 2. Canonical Components Table
		$sql_components = "CREATE TABLE {$components_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			category varchar(64) NOT NULL,
			brand varchar(64) NOT NULL,
			model_name varchar(255) NOT NULL,
			mpn varchar(128) DEFAULT NULL,
			sku varchar(128) DEFAULT NULL,
			ean_upc varchar(64) DEFAULT NULL,
			specs_json longtext DEFAULT NULL,
			image_url varchar(500) DEFAULT NULL,
			wp_post_id bigint(20) unsigned DEFAULT NULL,
			sync_hash varchar(64) DEFAULT NULL,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY category (category),
			KEY brand (brand),
			KEY model_name (model_name(191)),
			KEY mpn (mpn),
			KEY sku (sku)
		) {$charset_collate};";

		// 3. Vendor Prices Table
		$sql_prices = "CREATE TABLE {$prices_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			component_id bigint(20) unsigned NOT NULL,
			vendor_id bigint(20) unsigned NOT NULL,
			vendor_product_title varchar(255) NOT NULL,
			product_url varchar(500) NOT NULL,
			vendor_sku varchar(128) DEFAULT NULL,
			price decimal(10,2) NOT NULL,
			original_price decimal(10,2) DEFAULT NULL,
			is_in_stock tinyint(1) NOT NULL DEFAULT 1,
			stock_status varchar(32) NOT NULL DEFAULT 'in_stock',
			raw_data_json longtext DEFAULT NULL,
			last_checked_at datetime DEFAULT NULL,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY component_id (component_id),
			KEY vendor_id (vendor_id),
			KEY is_in_stock (is_in_stock),
			KEY price (price),
			UNIQUE KEY comp_vendor_uniq (component_id, vendor_id)
		) {$charset_collate};";

		// 4. Price History Table
		$sql_history = "CREATE TABLE {$history_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vendor_price_id bigint(20) unsigned NOT NULL,
			component_id bigint(20) unsigned NOT NULL,
			vendor_id bigint(20) unsigned NOT NULL,
			price decimal(10,2) NOT NULL,
			is_in_stock tinyint(1) NOT NULL DEFAULT 1,
			recorded_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY vendor_price_id (vendor_price_id),
			KEY component_id (component_id),
			KEY recorded_at (recorded_at)
		) {$charset_collate};";

		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql_vendors );
			dbDelta( $sql_components );
			dbDelta( $sql_prices );
			dbDelta( $sql_history );
		} else {
			$wpdb->query( $sql_vendors );
			$wpdb->query( $sql_components );
			$wpdb->query( $sql_prices );
			$wpdb->query( $sql_history );
		}

		// Ensure columns exist on existing databases and modify column constraints
		$existing_cols = $wpdb->get_col( "DESC {$vendors_table}" );
		if ( ! empty( $existing_cols ) ) {
			// Ensure adapter_class is nullable with default empty string
			$wpdb->query( "ALTER TABLE {$vendors_table} MODIFY COLUMN adapter_class varchar(128) NULL DEFAULT ''" );
			if ( ! in_array( 'sync_method', $existing_cols ) ) {
				$wpdb->query( "ALTER TABLE {$vendors_table} ADD COLUMN sync_method varchar(64) NOT NULL DEFAULT 'curl_html' AFTER adapter_class" );
			}
			if ( ! in_array( 'config_json', $existing_cols ) ) {
				$wpdb->query( "ALTER TABLE {$vendors_table} ADD COLUMN config_json longtext DEFAULT NULL AFTER sync_method" );
			}
		}

		$existing_comp_cols = $wpdb->get_col( "DESC {$components_table}" );
		if ( ! empty( $existing_comp_cols ) ) {
			if ( ! in_array( 'image_url', $existing_comp_cols ) ) {
				$wpdb->query( "ALTER TABLE {$components_table} ADD COLUMN image_url varchar(500) DEFAULT NULL AFTER specs_json" );
			}
		}

		update_option( 'hwsync_db_version', self::DB_VERSION );
	}

	public static function seed_default_vendors() {
		global $wpdb;
		$table = self::get_table_name( 'vendors' );

		$default_vendors = array(
			array(
				'vendor_slug'   => 'mdcomputers',
				'vendor_name'   => 'MDComputers',
				'base_url'      => 'https://mdcomputers.in',
				'adapter_class' => 'HWsync\\Vendors\\MDComputers_Adapter',
				'sync_method'   => 'browser_headless',
				'is_active'     => 1,
			),
			array(
				'vendor_slug'   => 'vedant',
				'vendor_name'   => 'Vedant Computers',
				'base_url'      => 'https://www.vedantcomputers.com',
				'adapter_class' => 'HWsync\\Vendors\\Vedant_Adapter',
				'sync_method'   => 'curl_html',
				'is_active'     => 1,
			),
			array(
				'vendor_slug'   => 'primeabgb',
				'vendor_name'   => 'PrimeABGB',
				'base_url'      => 'https://www.primeabgb.com',
				'adapter_class' => 'HWsync\\Vendors\\PrimeABGB_Adapter',
				'sync_method'   => 'curl_html',
				'is_active'     => 1,
			),
			array(
				'vendor_slug'   => 'elitehubs',
				'vendor_name'   => 'EliteHubs',
				'base_url'      => 'https://elitehubs.com',
				'adapter_class' => 'HWsync\\Vendors\\EliteHubs_Adapter',
				'sync_method'   => 'shopify_json',
				'is_active'     => 1,
			),
			array(
				'vendor_slug'   => 'pcstudio',
				'vendor_name'   => 'PCStudio (Ankit Infotech)',
				'base_url'      => 'https://www.pcstudio.in',
				'adapter_class' => 'HWsync\\Vendors\\PCStudio_Adapter',
				'sync_method'   => 'curl_html',
				'is_active'     => 1,
			),
			array(
				'vendor_slug'   => 'theitdepot',
				'vendor_name'   => 'The IT Depot',
				'base_url'      => 'https://www.theitdepot.com',
				'adapter_class' => 'HWsync\\Vendors\\TheITDepot_Adapter',
				'sync_method'   => 'curl_html',
				'is_active'     => 1,
			),
			array(
				'vendor_slug'   => 'amazon-in',
				'vendor_name'   => 'Amazon India',
				'base_url'      => 'https://www.amazon.in',
				'adapter_class' => 'HWsync\\Vendors\\Amazon_Adapter',
				'sync_method'   => 'curl_html',
				'is_active'     => 1,
			),
		);

		foreach ( $default_vendors as $vendor ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE vendor_slug = %s", $vendor['vendor_slug'] ) );
			if ( ! $exists ) {
				$wpdb->insert( $table, $vendor );
			}
		}
	}
}
