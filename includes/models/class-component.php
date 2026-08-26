<?php
namespace HWsync\Models;

use HWsync\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

class Component {
	public $id;
	public $category;
	public $brand;
	public $model_name;
	public $mpn;
	public $sku;
	public $ean_upc;
	public $specs_json;
	public $wp_post_id;
	public $sync_hash;
	public $created_at;
	public $updated_at;

	public function __construct( $data = array() ) {
		if ( ! empty( $data ) ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
			if ( ! empty( $this->specs_json ) && is_string( $this->specs_json ) ) {
				$decoded = json_decode( $this->specs_json, true );
				if ( is_array( $decoded ) ) {
					$this->specs_json = $decoded;
				}
			}
		}
	}

	public static function find_by_id( $id ) {
		global $wpdb;
		$table = Database::get_table_name( 'components' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), \ARRAY_A );
		return $row ? new self( $row ) : null;
	}

	public static function find_by_mpn( $mpn ) {
		if ( empty( $mpn ) ) {
			return null;
		}
		global $wpdb;
		$table = Database::get_table_name( 'components' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE mpn = %s", $mpn ), \ARRAY_A );
		return $row ? new self( $row ) : null;
	}

	public static function find_by_brand_and_model( $brand, $model_name ) {
		global $wpdb;
		$table = Database::get_table_name( 'components' );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE LOWER(brand) = LOWER(%s) AND LOWER(model_name) = LOWER(%s)",
				$brand,
				$model_name
			),
			\ARRAY_A
		);
		return $row ? new self( $row ) : null;
	}

	public static function get_all( $args = array() ) {
		global $wpdb;
		$table = Database::get_table_name( 'components' );
		$category = isset( $args['category'] ) ? $args['category'] : '';
		$limit = isset( $args['limit'] ) ? intval( $args['limit'] ) : 100;
		$offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;

		$where = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $category ) ) {
			$where .= ' AND category = %s';
			$params[] = $category;
		}

		$sql = "SELECT * FROM {$table} {$where} ORDER BY id ASC LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), \ARRAY_A );
		$components = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$components[] = new self( $row );
			}
		}
		return $components;
	}

	public static function count( $args = array() ) {
		global $wpdb;
		$table = Database::get_table_name( 'components' );
		$category = isset( $args['category'] ) ? $args['category'] : '';

		if ( ! empty( $category ) && $category !== 'all' ) {
			return intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE category = %s", $category ) ) );
		}

		return intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
	}

	public function save() {
		global $wpdb;
		$table = Database::get_table_name( 'components' );

		$data = array(
			'category'   => $this->category,
			'brand'      => $this->brand,
			'model_name' => $this->model_name,
			'mpn'        => $this->mpn,
			'sku'        => $this->sku,
			'ean_upc'    => $this->ean_upc,
			'specs_json' => is_array( $this->specs_json ) ? wp_json_encode( $this->specs_json ) : $this->specs_json,
			'wp_post_id' => $this->wp_post_id,
			'sync_hash'  => $this->sync_hash,
		);

		if ( $this->id ) {
			$wpdb->update( $table, $data, array( 'id' => $this->id ) );
		} else {
			$wpdb->insert( $table, $data );
			$this->id = $wpdb->insert_id;
		}

		return $this->id;
	}

	public function get_prices() {
		return Vendor_Price::find_by_component_id( $this->id );
	}

	public function get_lowest_price() {
		$prices = $this->get_prices();
		$lowest = null;
		foreach ( $prices as $vp ) {
			if ( $vp->is_in_stock && ( null === $lowest || $vp->price < $lowest->price ) ) {
				$lowest = $vp;
			}
		}
		return $lowest;
	}

	public function get_specs() {
		if ( empty( $this->specs_json ) ) {
			return array();
		}
		return is_array( $this->specs_json ) ? $this->specs_json : json_decode( $this->specs_json, true );
	}
}
