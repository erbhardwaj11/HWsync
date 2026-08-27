<?php
/**
 * Global Helper Functions for HWsync.
 * Defined in root global namespace for theme developers and templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hwsync_get_vendor_prices' ) ) {
	function hwsync_get_vendor_prices( $id = 0 ) {
		global $wpdb;
		if ( empty( $id ) && function_exists( 'get_the_ID' ) ) {
			$id = get_the_ID();
		}

		$hw_table = \HWsync\Database::get_table_name( 'vendor_prices' );
		$v_table  = \HWsync\Database::get_table_name( 'vendors' );

		if ( ! \HWsync\Database::table_exists( $hw_table ) || ! \HWsync\Database::table_exists( $v_table ) ) {
			return array();
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT vp.*, v.vendor_name, v.vendor_slug 
			 FROM {$hw_table} vp 
			 LEFT JOIN {$v_table} v ON v.id = vp.vendor_id 
			 WHERE vp.component_id = %d ORDER BY vp.price ASC",
			$id
		), \ARRAY_A );

		if ( ! empty( $rows ) ) {
			return $rows;
		}

		return get_post_meta( $id, '_hwsync_vendor_prices', true ) ?: array();
	}
}

if ( ! function_exists( 'hwsync_get_lowest_price' ) ) {
	function hwsync_get_lowest_price( $id = 0 ) {
		$prices = hwsync_get_vendor_prices( $id );
		$lowest = null;
		foreach ( $prices as $p ) {
			$val      = isset( $p['price'] ) ? floatval( $p['price'] ) : 0;
			$in_stock = isset( $p['stock_status'] ) ? ( $p['stock_status'] === 'instock' || $p['stock_status'] === 'in_stock' ) : ( ! empty( $p['is_in_stock'] ) );
			if ( $val > 0 && ( null === $lowest || ( $in_stock && $val < $lowest ) ) ) {
				$lowest = $val;
			}
		}
		if ( null !== $lowest ) {
			return $lowest;
		}

		if ( empty( $id ) && function_exists( 'get_the_ID' ) ) {
			$id = get_the_ID();
		}
		return floatval( get_post_meta( $id, '_hwsync_lowest_price', true ) );
	}
}

if ( ! function_exists( 'hwsync_render_price_table' ) ) {
	function hwsync_render_price_table( $id = 0 ) {
		if ( empty( $id ) && function_exists( 'get_the_ID' ) ) {
			$id = get_the_ID();
		}
		$component_id = get_post_meta( $id, '_hwsync_component_id', true );
		if ( ! $component_id ) {
			$component_id = $id;
		}
		if ( $component_id && function_exists( 'do_shortcode' ) ) {
			return do_shortcode( '[hwsync_price_table id="' . intval( $component_id ) . '"]' );
		}
		return '';
	}
}
