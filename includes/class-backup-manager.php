<?php
namespace HWsync;

use HWsync\Models\Component;
use HWsync\Models\Vendor;
use HWsync\Models\Vendor_Price;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Backup_Manager {

	/**
	 * Export components and vendor prices as a structured CSV stream/file.
	 */
	public static function export_csv() {
		global $wpdb;
		$comp_table   = Database::get_table_name( 'components' );
		$prices_table = Database::get_table_name( 'vendor_prices' );
		$vendor_table = Database::get_table_name( 'vendors' );

		$sql = "SELECT 
					c.id AS component_id,
					c.category,
					c.brand,
					c.model_name,
					c.mpn,
					c.sku AS component_sku,
					c.ean_upc,
					c.specs_json,
					c.wp_post_id,
					v.vendor_slug,
					v.vendor_name,
					vp.id AS vendor_price_id,
					vp.vendor_sku,
					vp.vendor_product_title,
					vp.product_url,
					vp.price,
					vp.original_price,
					vp.is_in_stock,
					vp.stock_status,
					vp.last_checked_at
				FROM {$comp_table} c
				LEFT JOIN {$prices_table} vp ON c.id = vp.component_id
				LEFT JOIN {$vendor_table} v ON vp.vendor_id = v.id
				ORDER BY c.id ASC, vp.price ASC";

		$rows = $wpdb->get_results( $sql, \ARRAY_A );

		// Prepare HTTP headers for CSV download
		$filename = 'hwsync-hardware-export-' . date( 'Y-m-d-His' ) . '.csv';

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		$output = fopen( 'php://output', 'w' );
		// Write UTF-8 BOM for Excel compatibility
		fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

		// Header row
		fputcsv( $output, array(
			'Component ID',
			'Category',
			'Brand',
			'Model Name',
			'MPN',
			'Component SKU',
			'EAN/UPC',
			'Specs JSON',
			'WP Post ID',
			'Vendor Slug',
			'Vendor Name',
			'Vendor SKU',
			'Vendor Product Title',
			'Product URL',
			'Price (INR)',
			'Original Price (INR)',
			'In Stock',
			'Stock Status',
			'Last Checked At',
		) );

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				fputcsv( $output, array(
					$row['component_id'],
					$row['category'],
					$row['brand'],
					$row['model_name'],
					$row['mpn'],
					$row['component_sku'],
					$row['ean_upc'],
					$row['specs_json'],
					$row['wp_post_id'],
					$row['vendor_slug'],
					$row['vendor_name'],
					$row['vendor_sku'],
					$row['vendor_product_title'],
					$row['product_url'],
					$row['price'],
					$row['original_price'],
					$row['is_in_stock'],
					$row['stock_status'],
					$row['last_checked_at'],
				) );
			}
		}

		fclose( $output );
		exit;
	}

	/**
	 * Restore database from an uploaded CSV file.
	 *
	 * @param string $file_path Absolute path to the CSV file.
	 * @return array Report array with statistics.
	 */
	public static function restore_csv( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success' => false,
				'message' => \__( 'CSV file not found or unreadable.', 'hwsync' ),
			);
		}

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return array(
				'success' => false,
				'message' => \__( 'Could not open CSV file.', 'hwsync' ),
			);
		}

		// Read header
		$header = fgetcsv( $handle );
		if ( ! $header || count( $header ) < 5 ) {
			fclose( $handle );
			return array(
				'success' => false,
				'message' => \__( 'Invalid CSV format or missing columns.', 'hwsync' ),
			);
		}

		$header_map = array_flip( array_map( 'trim', $header ) );

		$imported_components = 0;
		$imported_prices     = 0;
		$touched_comp_ids    = array();
		$errors              = array();

		$vendors_cache = array();
		foreach ( Vendor::get_all() as $v ) {
			$vendors_cache[ $v->vendor_slug ] = $v;
		}

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			if ( empty( $data ) || count( $data ) < 4 ) {
				continue;
			}

			$category   = isset( $header_map['Category'] ) ? trim( $data[ $header_map['Category'] ] ) : '';
			$brand      = isset( $header_map['Brand'] ) ? trim( $data[ $header_map['Brand'] ] ) : '';
			$model_name = isset( $header_map['Model Name'] ) ? trim( $data[ $header_map['Model Name'] ] ) : '';
			$mpn        = isset( $header_map['MPN'] ) ? trim( $data[ $header_map['MPN'] ] ) : '';
			$sku        = isset( $header_map['Component SKU'] ) ? trim( $data[ $header_map['Component SKU'] ] ) : '';
			$specs_json = isset( $header_map['Specs JSON'] ) ? trim( $data[ $header_map['Specs JSON'] ] ) : '';
			$post_id    = isset( $header_map['WP Post ID'] ) ? intval( $data[ $header_map['WP Post ID'] ] ) : 0;

			if ( empty( $model_name ) ) {
				continue;
			}

			// 1. Find or create canonical component
			$component = null;
			if ( ! empty( $mpn ) ) {
				$component = Component::find_by_mpn( $mpn );
			}
			if ( ! $component && ! empty( $sku ) ) {
				$component = Component::find_by_sku( $sku );
			}
			if ( ! $component ) {
				$component = new Component( array(
					'category'   => $category ?: 'cpu',
					'brand'      => $brand,
					'model_name' => $model_name,
					'mpn'        => $mpn ?: null,
					'sku'        => $sku ?: null,
					'specs_json' => ! empty( $specs_json ) ? json_decode( $specs_json, true ) : null,
					'wp_post_id' => $post_id ?: null,
				) );
				$component->save();
				$imported_components++;
			}

			if ( ! $component || empty( $component->id ) ) {
				continue;
			}

			$touched_comp_ids[ $component->id ] = true;

			// 2. Vendor Price import if vendor info exists in row
			$vendor_slug = isset( $header_map['Vendor Slug'] ) ? trim( $data[ $header_map['Vendor Slug'] ] ) : '';
			$price_val   = isset( $header_map['Price (INR)'] ) ? floatval( $data[ $header_map['Price (INR)'] ] ) : 0.0;

			if ( ! empty( $vendor_slug ) && isset( $vendors_cache[ $vendor_slug ] ) ) {
				$vendor = $vendors_cache[ $vendor_slug ];
				$vp = Vendor_Price::find_by_component_and_vendor( $component->id, $vendor->id );
				if ( ! $vp ) {
					$vp = new Vendor_Price( array(
						'component_id' => $component->id,
						'vendor_id'    => $vendor->id,
					) );
				}

				$vp->vendor_product_title = isset( $header_map['Vendor Product Title'] ) ? trim( $data[ $header_map['Vendor Product Title'] ] ) : $model_name;
				$vp->product_url          = isset( $header_map['Product URL'] ) ? trim( $data[ $header_map['Product URL'] ] ) : '';
				$vp->vendor_sku           = isset( $header_map['Vendor SKU'] ) ? trim( $data[ $header_map['Vendor SKU'] ] ) : $sku;
				$vp->price                = $price_val;
				$vp->original_price       = isset( $header_map['Original Price (INR)'] ) && is_numeric( $data[ $header_map['Original Price (INR)'] ] ) ? floatval( $data[ $header_map['Original Price (INR)'] ] ) : null;
				$vp->is_in_stock          = isset( $header_map['In Stock'] ) ? intval( $data[ $header_map['In Stock'] ] ) : 1;
				$vp->stock_status         = isset( $header_map['Stock Status'] ) ? trim( $data[ $header_map['Stock Status'] ] ) : 'in_stock';
				$vp->save();
				$imported_prices++;
			}
		}

		fclose( $handle );

		// Re-sync WordPress posts
		$post_stats = array( 'created' => 0, 'updated' => 0 );
		if ( ! empty( $touched_comp_ids ) ) {
			$post_stats = Post_Sync_Processor::process_all( array_keys( $touched_comp_ids ) );
		}

		return array(
			'success'             => true,
			'components_imported' => $imported_components,
			'prices_imported'     => $imported_prices,
			'posts_synced'        => ( $post_stats['created'] + $post_stats['updated'] ),
			'message'             => sprintf(
				\__( 'CSV Restore Completed: %d components processed, %d vendor prices imported, %d WordPress posts synced.', 'hwsync' ),
				count( $touched_comp_ids ),
				$imported_prices,
				( $post_stats['created'] + $post_stats['updated'] )
			),
		);
	}

	/**
	 * Complete Wipe and Clean Reset:
	 * Deletes all WordPress component posts, deletes postmeta,
	 * truncates all plugin tables, and resets auto_increment IDs to 1.
	 *
	 * @return array Result report.
	 */
	public static function wipe_and_reset_all_data() {
		global $wpdb;

		$comp_table    = Database::get_table_name( 'components' );
		$prices_table  = Database::get_table_name( 'vendor_prices' );
		$history_table = Database::get_table_name( 'price_history' );
		$vendors_table = Database::get_table_name( 'vendors' );

		// 1. Delete all WordPress `hwsync_component` custom posts and their postmeta
		$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'hwsync_component'" );
		$deleted_posts_count = 0;

		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $p_id ) {
				// Force delete bypassing trash
				\wp_delete_post( $p_id, true );
				$deleted_posts_count++;
			}
		}

		// Also clean up any lingering orphaned postmeta
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_hwsync_%'" );

		// 2. Truncate custom hardware database tables
		$wpdb->query( "TRUNCATE TABLE {$history_table}" );
		$wpdb->query( "TRUNCATE TABLE {$prices_table}" );
		$wpdb->query( "TRUNCATE TABLE {$comp_table}" );

		// 3. Reset MySQL AUTO_INCREMENT counter to 1 for clean ID numbering
		$wpdb->query( "ALTER TABLE {$comp_table} AUTO_INCREMENT = 1" );
		$wpdb->query( "ALTER TABLE {$prices_table} AUTO_INCREMENT = 1" );
		$wpdb->query( "ALTER TABLE {$history_table} AUTO_INCREMENT = 1" );

		// 4. Reset Vendor sync timestamps
		$wpdb->query( "UPDATE {$vendors_table} SET last_sync_at = NULL" );

		// 5. Clear plugin sync reports
		delete_option( 'hwsync_last_sync_report' );

		return array(
			'success'             => true,
			'deleted_posts_count' => $deleted_posts_count,
			'tables_truncated'    => array( 'components', 'vendor_prices', 'price_history' ),
			'auto_increment_reset'=> true,
			'message'             => sprintf(
				\__( 'Complete Wipe Successful: Deleted %d WordPress component posts, truncated all hardware tables, and reset all table IDs to 1.', 'hwsync' ),
				$deleted_posts_count
			),
		);
	}
}
