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
	 * Sync components and vendor prices.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hwsync sync_posts
	 */
	public function sync_posts( $args, $assoc_args ) {
		WP_CLI::line( "HWsync sync is direct to hardware tables." );
		WP_CLI::success( "Sync completed!" );
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
