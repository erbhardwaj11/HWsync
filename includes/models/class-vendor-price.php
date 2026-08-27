<?php
namespace HWsync\Models;

use HWsync\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

class Vendor_Price {
	public $id;
	public $component_id;
	public $vendor_id;
	public $vendor_slug;
	public $vendor_name;
	public $vendor_sku;
	public $vendor_product_title;
	public $product_url;
	public $affiliate_url;
	public $price;
	public $original_price;
	public $is_in_stock;
	public $stock_status;
	public $warranty_months;
	public $raw_data_json;
	public $last_checked_at;
	public $updated_at;

	public function __construct( $data = array() ) {
		if ( ! empty( $data ) ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
			if ( ! empty( $this->raw_data_json ) && is_string( $this->raw_data_json ) ) {
				$decoded = json_decode( $this->raw_data_json, true );
				if ( is_array( $decoded ) ) {
					$this->raw_data_json = $decoded;
				}
			}
		}
	}

	public static function find_by_id( $id ) {
		global $wpdb;
		$table = Database::get_table_name( 'vendor_prices' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), \ARRAY_A );
		return $row ? new self( $row ) : null;
	}

	public static function find_by_component_and_vendor( $component_id, $vendor_id ) {
		global $wpdb;
		$table = Database::get_table_name( 'vendor_prices' );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE component_id = %d AND vendor_id = %d",
				$component_id,
				$vendor_id
			),
			\ARRAY_A
		);
		return $row ? new self( $row ) : null;
	}

	public static function find_by_component_id( $component_id ) {
		global $wpdb;
		$table = Database::get_table_name( 'vendor_prices' );
		$vendors_table = Database::get_table_name( 'vendors' );

		$sql = "SELECT vp.*, v.vendor_name, v.vendor_slug, v.base_url 
				FROM {$table} vp
				LEFT JOIN {$vendors_table} v ON vp.vendor_id = v.id
				WHERE vp.component_id = %d
				ORDER BY vp.is_in_stock DESC, vp.price ASC";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $component_id ), \ARRAY_A );
		$prices = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$prices[] = new self( $row );
			}
		}
		return $prices;
	}

	public function save() {
		global $wpdb;
		$table = Database::get_table_name( 'vendor_prices' );
		$history_table = Database::get_table_name( 'price_history' );

		$data = array(
			'component_id'         => $this->component_id,
			'vendor_id'            => $this->vendor_id,
			'vendor_sku'           => $this->vendor_sku,
			'vendor_product_title' => $this->vendor_product_title,
			'product_url'          => $this->product_url,
			'affiliate_url'        => $this->affiliate_url,
			'price'                => floatval( $this->price ),
			'original_price'       => ! empty( $this->original_price ) ? floatval( $this->original_price ) : null,
			'is_in_stock'          => $this->is_in_stock ? 1 : 0,
			'stock_status'         => $this->stock_status ?: 'in_stock',
			'warranty_months'      => ! empty( $this->warranty_months ) ? intval( $this->warranty_months ) : null,
			'raw_data_json'        => is_array( $this->raw_data_json ) ? wp_json_encode( $this->raw_data_json ) : $this->raw_data_json,
			'last_checked_at'      => current_time( 'mysql' ),
		);

		$existing = self::find_by_component_and_vendor( $this->component_id, $this->vendor_id );

		if ( $existing ) {
			$this->id = $existing->id;
			$price_changed = ( floatval( $existing->price ) !== floatval( $this->price ) );

			$wpdb->update( $table, $data, array( 'id' => $this->id ) );

			// Record in price history if price changed
			if ( $price_changed ) {
				$wpdb->insert(
					$history_table,
					array(
						'vendor_price_id' => $this->id,
						'component_id'    => $this->component_id,
						'vendor_id'       => $this->vendor_id,
						'price'           => floatval( $this->price ),
						'is_in_stock'     => $this->is_in_stock ? 1 : 0,
						'recorded_at'     => current_time( 'mysql' ),
					)
				);
			}
		} else {
			$wpdb->insert( $table, $data );
			$this->id = $wpdb->insert_id;

			// Record initial price history entry
			$wpdb->insert(
				$history_table,
				array(
					'vendor_price_id' => $this->id,
					'component_id'    => $this->component_id,
					'vendor_id'       => $this->vendor_id,
					'price'           => floatval( $this->price ),
					'is_in_stock'     => $this->is_in_stock ? 1 : 0,
					'recorded_at'     => current_time( 'mysql' ),
				)
			);
		}

		return $this->id;
	}

	public function get_formatted_price() {
		return '₹' . number_format( floatval( $this->price ), 2 );
	}
}
