<?php
namespace HWsync;

use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class CLI_Command extends WP_CLI_Command {

	/**
	 * Run hardware components and vendor prices sync.
	 *
	 * ## OPTIONS
	 *
	 * [--vendor=<vendor>]
	 * : Specific vendor slug (e.g. mdcomputers, vedant, primeabgb, elitehubs, pcstudio, or all).
	 * ---
	 * default: all
	 * ---
	 *
	 * [--category=<category>]
	 * : Component category to sync (e.g. cpu, gpu, motherboard, ram, storage, psu, or all).
	 * ---
	 * default: all
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp hwsync run
	 *     wp hwsync run --vendor=mdcomputers --category=cpu
	 */
	public function run( $args, $assoc_args ) {
		$vendor   = isset( $assoc_args['vendor'] ) ? $assoc_args['vendor'] : 'all';
		$category = isset( $assoc_args['category'] ) ? $assoc_args['category'] : 'all';

		WP_CLI::line( "Starting HWsync process for Vendor: [{$vendor}], Category: [{$category}]..." );

		$manager = new Sync_Manager();
		$start_time = microtime( true );
		$report = $manager->run_sync( array( 'vendor' => $vendor, 'category' => $category ) );
		$duration = round( microtime( true ) - $start_time, 2 );

		WP_CLI::success( "Sync completed in {$duration}s!" );
		WP_CLI::line( "----------------------------------------" );
		WP_CLI::line( "Vendors Processed:   " . $report['vendors_processed'] );
		WP_CLI::line( "Items Scraped:       " . $report['total_items_fetched'] );
		WP_CLI::line( "Components Updated:  " . $report['components_processed'] );
		WP_CLI::line( "Vendor Prices Saved: " . $report['prices_updated'] );
		WP_CLI::line( "Posts Created/Synced:" . $report['posts_synced'] );

		if ( ! empty( $report['errors'] ) ) {
			WP_CLI::warning( "Encountered " . count( $report['errors'] ) . " errors during sync:" );
			foreach ( $report['errors'] as $err ) {
				WP_CLI::line( "  - {$err}" );
			}
		}
	}

	/**
	 * Run Multi-Vendor Component Deduplication and Price Merging.
	 *
	 * ## OPTIONS
	 *
	 * [--category=<category>]
	 * : Component category to merge (e.g. cpu, gpu, motherboard, ram, storage, psu, cooler, cabinet, or all).
	 * ---
	 * default: all
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp hwsync merge
	 *     wp hwsync merge --category=cpu
	 */
	public function merge( $args, $assoc_args ) {
		$category = isset( $assoc_args['category'] ) ? $assoc_args['category'] : 'all';
		WP_CLI::line( "Starting Multi-Vendor Component Deduplication & Merge for category: [{$category}]..." );

		$logger = function( $level, $message ) {
			if ( $level === 'error' ) {
				WP_CLI::error( $message, false );
			} elseif ( $level === 'warning' ) {
				WP_CLI::warning( $message );
			} elseif ( $level === 'success' || $level === 'finish' ) {
				WP_CLI::success( $message );
			} else {
				WP_CLI::line( $message );
			}
		};

		$res = Matching_Engine::merge_duplicate_components( $category, $logger );
		WP_CLI::success( "Consolidated {$res['total_merged']} duplicate records! Active canonical hardware components: {$res['canonical_total']}." );
	}

	/**
	 * Run Product Image Synchronization.
	 *
	 * ## OPTIONS
	 *
	 * [--category=<category>]
	 * : Component category to sync images for (e.g. cpu, gpu, motherboard, ram, storage, psu, cooler, cabinet, or all).
	 * ---
	 * default: all
	 * ---
	 *
	 * [--limit=<limit>]
	 * : Number of components to process.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--force]
	 * : Force re-download images even if already present.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hwsync sync-images
	 *     wp hwsync sync-images --category=gpu --limit=50
	 */
	public function sync_images( $args, $assoc_args ) {
		$category = isset( $assoc_args['category'] ) ? $assoc_args['category'] : 'all';
		$limit    = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : 100;
		$force    = isset( $assoc_args['force'] );

		WP_CLI::line( "Starting Product Image Sync for category: [{$category}] (Limit: {$limit}, Force: " . ( $force ? 'Yes' : 'No' ) . ")..." );

		$logger = function( $level, $message ) {
			if ( $level === 'error' ) {
				WP_CLI::error( $message, false );
			} elseif ( $level === 'warning' ) {
				WP_CLI::warning( $message );
			} elseif ( $level === 'success' || $level === 'finish' ) {
				WP_CLI::success( $message );
			} else {
				WP_CLI::line( $message );
			}
		};

		$manager = new Image_Sync_Manager();
		$report = $manager->run_images_sync( array(
			'category' => $category,
			'limit'    => $limit,
			'force'    => $force,
		), $logger );

		WP_CLI::success( "Image sync finished! Processed {$report['total_components']} components: {$report['images_saved']} images downloaded, {$report['skipped']} skipped, {$report['errors']} missing." );
	}

	/**
	 * Display current database stats and vendor status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hwsync status
	 */
	public function status( $args, $assoc_args ) {
		global $wpdb;

		$vendors_table = Database::get_table_name( 'vendors' );
		$components_table = Database::get_table_name( 'components' );
		$prices_table = Database::get_table_name( 'vendor_prices' );

		$vendors = $wpdb->get_results( "SELECT * FROM {$vendors_table}", ARRAY_A );
		$comp_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$components_table}" );
		$price_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prices_table}" );
		$in_stock_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prices_table} WHERE is_in_stock = 1" );

		WP_CLI::line( "=== HWsync System Status ===" );
		WP_CLI::line( "Canonical Components: {$comp_count}" );
		WP_CLI::line( "Vendor Price Records: {$price_count} ({$in_stock_count} In-Stock)" );
		WP_CLI::line( "" );

		WP_CLI::line( "Registered Vendors:" );
		$formatted_vendors = array();
		foreach ( $vendors as $v ) {
			$formatted_vendors[] = array(
				'ID'        => $v['id'],
				'Slug'      => $v['vendor_slug'],
				'Name'      => $v['vendor_name'],
				'Active'    => $v['is_active'] ? 'Yes' : 'No',
				'Last Sync' => $v['last_sync_at'] ?: 'Never',
			);
		}
		WP_CLI\Utils\format_items( 'table', $formatted_vendors, array( 'ID', 'Slug', 'Name', 'Active', 'Last Sync' ) );
	}
}

WP_CLI::add_command( 'hwsync', '\\HWsync\\CLI_Command' );
