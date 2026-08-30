<?php
/**
 * Global Theme Helper Functions for pcspecs & Theme Developers.
 * Defined in root global namespace for maximum compatibility.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pcspecs_get_vendor_prices' ) ) {
	function pcspecs_get_vendor_prices( $id = 0 ) {
		global $wpdb;
		if ( empty( $id ) && function_exists( 'get_the_ID' ) ) {
			$id = get_the_ID();
		}

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			$prices = get_post_meta( $id, '_pcspecs_vendor_prices', true );
			if ( ! empty( $prices ) && is_array( $prices ) ) {
				return $prices;
			}
			return get_post_meta( $id, '_hwsync_vendor_prices', true ) ?: array();
		}

		// 1. Direct query from native pcspecs table (wp_pc_vendor_prices)
		$pc_table = $wpdb->prefix . 'pc_vendor_prices';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $pc_table ) ) === $pc_table ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$pc_table} WHERE component_id = %d ORDER BY current_price ASC", $id ), \ARRAY_A );
			if ( ! empty( $rows ) ) {
				return $rows;
			}
		}

		// 2. Direct query from HWsync table (wp_hwsync_vendor_prices)
		$hw_table = \HWsync\Database::get_table_name( 'vendor_prices' );
		$v_table  = \HWsync\Database::get_table_name( 'vendors' );
		$rows     = $wpdb->get_results( $wpdb->prepare(
			"SELECT vp.*, v.vendor_name, v.vendor_slug 
			 FROM {$hw_table} vp 
			 LEFT JOIN {$v_table} v ON v.id = vp.vendor_id 
			 WHERE vp.component_id = %d ORDER BY vp.price ASC",
			$id
		), \ARRAY_A );
		if ( ! empty( $rows ) ) {
			return $rows;
		}

		// 3. Fallback to postmeta
		$prices = get_post_meta( $id, '_pcspecs_vendor_prices', true );
		if ( ! empty( $prices ) && is_array( $prices ) ) {
			return $prices;
		}
		return get_post_meta( $id, '_hwsync_vendor_prices', true ) ?: array();
	}
}

if ( ! function_exists( 'pcspecs_get_lowest_price' ) ) {
	function pcspecs_get_lowest_price( $id = 0 ) {
		$prices = pcspecs_get_vendor_prices( $id );
		$lowest = null;
		foreach ( $prices as $p ) {
			$val = isset( $p['current_price'] ) && floatval( $p['current_price'] ) > 0 
				? floatval( $p['current_price'] ) 
				: ( isset( $p['price'] ) ? floatval( $p['price'] ) : 0 );

			$in_stock = true;
			if ( isset( $p['stock_status'] ) && $p['stock_status'] !== '' && $p['stock_status'] !== null ) {
				$in_stock = in_array( strtolower( (string) $p['stock_status'] ), array( 'instock', 'in_stock', '1', 'true' ), true );
			} elseif ( isset( $p['is_in_stock'] ) ) {
				$in_stock = ! empty( $p['is_in_stock'] );
			}

			if ( $val > 0 && $in_stock ) {
				if ( null === $lowest || $val < $lowest ) {
					$lowest = $val;
				}
			}
		}
		if ( null !== $lowest ) {
			return $lowest;
		}

		if ( empty( $id ) && function_exists( 'get_the_ID' ) ) {
			$id = get_the_ID();
		}
		$meta_lowest = get_post_meta( $id, '_pcspecs_lowest_price', true );
		return ! empty( $meta_lowest ) ? floatval( $meta_lowest ) : floatval( get_post_meta( $id, '_hwsync_lowest_price', true ) );
	}
}

if ( ! function_exists( 'pcspecs_render_price_table' ) ) {
	function pcspecs_render_price_table( $post_id = 0 ) {
		if ( empty( $post_id ) && function_exists( 'get_the_ID' ) ) {
			$post_id = get_the_ID();
		}
		$component_id = get_post_meta( $post_id, '_pcspecs_component_id', true ) ?: get_post_meta( $post_id, '_hwsync_component_id', true );
		if ( ! $component_id ) {
			$component_id = $post_id;
		}
		if ( $component_id && function_exists( 'do_shortcode' ) ) {
			return do_shortcode( '[hwsync_price_table id="' . intval( $component_id ) . '"]' );
		}
		return '';
	}
}

if ( ! function_exists( 'hwsync_get_vendor_prices' ) ) {
	function hwsync_get_vendor_prices( $id = 0 ) {
		return pcspecs_get_vendor_prices( $id );
	}
}

if ( ! function_exists( 'hwsync_get_lowest_price' ) ) {
	function hwsync_get_lowest_price( $id = 0 ) {
		return pcspecs_get_lowest_price( $id );
	}
}

if ( ! function_exists( 'hwsync_render_price_table' ) ) {
	function hwsync_render_price_table( $id = 0 ) {
		return pcspecs_render_price_table( $id );
	}
}
