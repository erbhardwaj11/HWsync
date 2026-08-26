<?php
namespace HWsync;

use HWsync\Models\Component;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PCSpecs Theme & Part-Picker Database Synchronization Engine
 * Directly maps canonical components, normalized specifications, and multi-vendor pricing matrices
 * into the PCSpecs Theme database tables (wp_pc_components and wp_pc_vendor_prices)
 * powering the headless React PC Builder SPA and REST API (/wp-json/pc-builder/v1/components).
 */
class Post_Sync_Processor {

	/**
	 * Empty stub preserved for backwards compatibility (no CPT menu is registered)
	 */
	public static function register_post_type() {
		// Custom Post Type registration removed.
		// HWsync manages components directly through its dedicated dashboard and theme database tables.
	}

	/**
	 * Normalize category string to PCSpecs Theme canonical slugs
	 */
	public static function normalize_pcspecs_category( $cat ) {
		$name = strtolower( trim( (string) $cat ) );
		if ( strpos( $name, 'proc' ) !== false || $name === 'cpu' ) return 'cpu';
		if ( strpos( $name, 'graph' ) !== false || strpos( $name, 'video' ) !== false || $name === 'gpu' ) return 'gpu';
		if ( strpos( $name, 'mother' ) !== false || strpos( $name, 'mainboard' ) !== false ) return 'motherboard';
		if ( strpos( $name, 'ram' ) !== false || strpos( $name, 'memory' ) !== false ) return 'memory';
		if ( strpos( $name, 'storage' ) !== false || strpos( $name, 'ssd' ) !== false || strpos( $name, 'drive' ) !== false ) return 'storage';
		if ( strpos( $name, 'power' ) !== false || strpos( $name, 'psu' ) !== false ) return 'psu';
		if ( strpos( $name, 'case' ) !== false || strpos( $name, 'cabin' ) !== false || strpos( $name, 'chassis' ) !== false ) return 'case';
		if ( strpos( $name, 'cool' ) !== false ) return 'cooler';
		return sanitize_title( $cat ) ?: 'cpu';
	}

	/**
	 * Synchronize a batch chunk of canonical components directly into PCSpecs Theme tables.
	 *
	 * @param array $options Filter & pagination options
	 * @return array Result summary with processed counts and item details
	 */
	public static function sync_theme_chunk( $options = array() ) {
		$category = isset( $options['category'] ) ? $options['category'] : 'all';
		$offset   = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;
		$limit    = isset( $options['limit'] ) ? intval( $options['limit'] ) : 10;

		$args = array(
			'limit'  => $limit,
			'offset' => $offset,
		);
		if ( $category !== 'all' ) {
			$args['category'] = $category;
		}

		$total_count = Component::count( $category !== 'all' ? array( 'category' => $category ) : array() );
		$components  = Component::get_all( $args );

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$logs    = array();

		foreach ( $components as $component ) {
			$res = self::sync_component_to_theme( $component );
			$action       = $res['action'];
			$target_id    = $res['component_id'];
			$prices_count = $res['vendor_count'];
			$lowest       = $res['lowest_price'];

			if ( $action === 'created' ) {
				$created++;
				$logs[] = sprintf(
					__( '[NEW COMPONENT #%d] Synced "%s" with %d vendor prices into pcspecs Part-Picker (Lowest: %s)', 'hwsync' ),
					$target_id,
					$component->brand . ' ' . $component->model_name,
					$prices_count,
					$lowest > 0 ? '₹' . number_format( $lowest, 2 ) : 'NA'
				);
			} elseif ( $action === 'updated' ) {
				$updated++;
				$logs[] = sprintf(
					__( '[UPDATED #%d] "%s" mapped to %d vendor prices in pcspecs Part-Picker (Lowest: %s)', 'hwsync' ),
					$target_id,
					$component->brand . ' ' . $component->model_name,
					$prices_count,
					$lowest > 0 ? '₹' . number_format( $lowest, 2 ) : 'NA'
				);
			} else {
				$skipped++;
				$logs[] = sprintf(
					__( '[SKIPPED] "%s" (No active vendor pricing found)', 'hwsync' ),
					$component->brand . ' ' . $component->model_name
				);
			}
		}

		$next_offset = $offset + count( $components );
		$is_done = ( $next_offset >= $total_count || empty( $components ) );

		return array(
			'success'     => true,
			'total'       => $total_count,
			'processed'   => count( $components ),
			'offset'      => $next_offset,
			'created'     => $created,
			'updated'     => $updated,
			'skipped'     => $skipped,
			'is_done'     => $is_done,
			'logs'        => $logs,
		);
	}

	/**
	 * Synchronize all canonical components into PCSpecs Theme tables.
	 *
	 * @param array $component_ids Optional specific component IDs to process
	 * @return array Stats of processed, created, and updated components
	 */
	public static function process_all( $component_ids = array() ) {
		$stats = array(
			'total'   => 0,
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
		);

		if ( ! empty( $component_ids ) ) {
			$components = array();
			foreach ( $component_ids as $cid ) {
				$c = Component::find_by_id( $cid );
				if ( $c ) {
					$components[] = $c;
				}
			}
		} else {
			$components = Component::get_all( array( 'limit' => 2000 ) );
		}

		$stats['total'] = count( $components );

		foreach ( $components as $component ) {
			$res = self::sync_component_to_theme( $component );
			if ( $res['action'] === 'created' ) {
				$stats['created']++;
			} elseif ( $res['action'] === 'updated' ) {
				$stats['updated']++;
			} else {
				$stats['skipped']++;
			}
		}

		return $stats;
	}

	/**
	 * Map canonical category slug to theme category table name
	 */
	public static function get_category_table( $cat_slug ) {
		global $wpdb;
		$cat = self::normalize_pcspecs_category( $cat_slug );
		$map = array(
			'cpu'         => $wpdb->prefix . 'pcha_processors',
			'gpu'         => $wpdb->prefix . 'pcha_graphics_cards',
			'motherboard' => $wpdb->prefix . 'pcha_motherboards',
			'memory'      => $wpdb->prefix . 'pcha_rams',
			'storage'     => $wpdb->prefix . 'pcha_storages',
			'psu'         => $wpdb->prefix . 'pcha_power_supplies',
			'cooler'      => $wpdb->prefix . 'pcha_cpu_coolers',
			'case'        => $wpdb->prefix . 'pcha_cabinets',
		);
		return isset( $map[ $cat ] ) ? $map[ $cat ] : null;
	}

	/**
	 * Synchronize a single canonical Component and all linked vendor prices
	 * directly into wp_pc_components, wp_pc_vendor_prices, and category tables.
	 *
	 * @param Component $component
	 * @return array
	 */
	public static function sync_component_to_theme( Component $component ) {
		global $wpdb;

		$pc_comp_table   = $wpdb->prefix . 'pc_components';
		$pc_prices_table = $wpdb->prefix . 'pc_vendor_prices';

		$prices = $component->get_prices();
		if ( empty( $prices ) ) {
			return array(
				'action'       => 'skipped',
				'post_id'      => 0,
				'component_id' => 0,
				'vendor_count' => 0,
				'lowest_price' => 0.0,
			);
		}

		$name       = trim( $component->brand . ' ' . $component->model_name );
		$slug       = function_exists( 'sanitize_title' ) ? \sanitize_title( $name ) : strtolower( trim( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $name ), '-' ) );
		$cat_slug   = self::normalize_pcspecs_category( $component->category );
		$specs_arr  = $component->get_specs();
		$specs_json = ! empty( $specs_arr ) ? ( is_array( $specs_arr ) ? wp_json_encode( $specs_arr ) : $specs_arr ) : null;

		// 1. Locate existing entry in wp_pc_components
		$existing_id = null;
		if ( ! empty( $component->mpn ) ) {
			$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pc_comp_table} WHERE mpn = %s LIMIT 1", $component->mpn ) );
		}
		if ( ! $existing_id ) {
			$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pc_comp_table} WHERE slug = %s LIMIT 1", $slug ) );
		}
		if ( ! $existing_id ) {
			$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pc_comp_table} WHERE LOWER(brand) = LOWER(%s) AND LOWER(name) = LOWER(%s) LIMIT 1", $component->brand, $name ) );
		}

		$action = 'updated';
		$target_id = 0;

		$comp_data = array(
			'name'           => $name,
			'slug'           => $slug,
			'brand'          => $component->brand,
			'category'       => $cat_slug,
			'mpn'            => $component->mpn,
			'normalized_sku' => $component->sku,
			'specs'          => $specs_json,
			'updated_at'     => current_time( 'mysql' ),
		);

		if ( $existing_id ) {
			$target_id = intval( $existing_id );
			$wpdb->update( $pc_comp_table, $comp_data, array( 'id' => $target_id ) );
		} else {
			$comp_data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $pc_comp_table, $comp_data );
			$target_id = intval( $wpdb->insert_id );
			$action = 'created';
		}

		// Also synchronize into specific category table (e.g. wp_pcha_processors)
		$cat_table = self::get_category_table( $cat_slug );
		if ( $cat_table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $cat_table ) );
			if ( $table_exists === $cat_table ) {
				$cat_comp_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$cat_table} WHERE id = %d OR mpn = %s OR slug = %s LIMIT 1", $target_id, $component->mpn, $slug ) );
				if ( $cat_comp_id ) {
					$wpdb->update( $cat_table, $comp_data, array( 'id' => intval( $cat_comp_id ) ) );
				} else {
					$cat_record = $comp_data;
					$cat_record['id'] = $target_id;
					$cat_record['created_at'] = current_time( 'mysql' );
					$wpdb->insert( $cat_table, $cat_record );
				}
			}
		}

		// 2. Synchronize all merchant pricing offers into wp_pc_vendor_prices
		$lowest_price   = 0.0;
		$highest_price  = 0.0;
		$in_stock_count = 0;
		$vendor_count   = 0;

		foreach ( $prices as $p ) {
			$v_name = ! empty( $p->vendor_name ) ? $p->vendor_name : '';
			if ( empty( $v_name ) && ! empty( $p->vendor_id ) ) {
				$v_obj = \HWsync\Models\Vendor::find_by_id( $p->vendor_id );
				if ( $v_obj ) {
					$v_name = $v_obj->vendor_name;
				}
			}
			if ( empty( $v_name ) ) {
				$v_name = ! empty( $p->vendor_slug ) ? ucfirst( $p->vendor_slug ) : 'Retailer';
			}

			$cur_price    = floatval( $p->price );
			$is_stock     = (bool) $p->is_in_stock;
			$stock_status = $is_stock ? 'instock' : 'outofstock';

			if ( $is_stock ) {
				$in_stock_count++;
				if ( $cur_price > 0 && ( $lowest_price === 0.0 || $cur_price < $lowest_price ) ) {
					$lowest_price = $cur_price;
				}
			}
			if ( $cur_price > $highest_price ) {
				$highest_price = $cur_price;
			}

			$vp_record = array(
				'component_id'  => $target_id,
				'vendor_name'   => $v_name,
				'current_price' => $cur_price,
				'regular_price' => ! empty( $p->original_price ) ? floatval( $p->original_price ) : null,
				'sale_price'    => $cur_price,
				'stock_status'  => $stock_status,
				'product_url'   => ! empty( $p->affiliate_url ) ? $p->affiliate_url : $p->product_url,
				'vendor_sku'    => $p->vendor_sku,
				'last_checked'  => current_time( 'mysql' ),
			);

			$existing_vp = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$pc_prices_table} WHERE component_id = %d AND vendor_name = %s LIMIT 1",
				$target_id,
				$v_name
			) );

			if ( $existing_vp ) {
				$wpdb->update( $pc_prices_table, $vp_record, array( 'id' => intval( $existing_vp ) ) );
			} else {
				$wpdb->insert( $pc_prices_table, $vp_record );
			}
			$vendor_count++;
		}

		if ( $lowest_price === 0.0 && $highest_price > 0.0 ) {
			$lowest_price = $highest_price;
		}

		return array(
			'action'       => $action,
			'post_id'      => $target_id,
			'component_id' => $target_id,
			'vendor_count' => $vendor_count,
			'lowest_price' => $lowest_price,
		);
	}

	/**
	 * Backwards compatibility alias for sync_component_to_theme
	 */
	public static function sync_component_to_post( Component $component, $target_post_type = '' ) {
		return self::sync_component_to_theme( $component );
	}
}
