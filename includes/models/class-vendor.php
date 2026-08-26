<?php
namespace HWsync\Models;

use HWsync\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

class Vendor {
	public $id;
	public $vendor_slug;
	public $vendor_name;
	public $base_url;
	public $adapter_class;
	public $sync_method = 'curl_html';
	public $config_json;
	public $is_active = 1;
	public $last_sync_at;
	public $created_at;

	public function __construct( $data = array() ) {
		if ( ! empty( $data ) ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}

	public static function find_by_id( $id ) {
		global $wpdb;
		$table = Database::get_table_name( 'vendors' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), \ARRAY_A );
		return $row ? new self( $row ) : null;
	}

	public static function find_by_slug( $slug ) {
		global $wpdb;
		$table = Database::get_table_name( 'vendors' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE vendor_slug = %s", $slug ), \ARRAY_A );
		return $row ? new self( $row ) : null;
	}

	public static function get_all( $active_only = false ) {
		global $wpdb;
		$table = Database::get_table_name( 'vendors' );
		$sql = "SELECT * FROM {$table}";
		if ( $active_only ) {
			$sql .= " WHERE is_active = 1";
		}
		$sql .= " ORDER BY vendor_name ASC";

		$results = $wpdb->get_results( $sql, \ARRAY_A );
		$vendors = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$vendors[] = new self( $row );
			}
		}
		return $vendors;
	}

	public function get_config() {
		if ( empty( $this->config_json ) ) {
			return array( 'endpoints' => array() );
		}
		if ( is_array( $this->config_json ) ) {
			return $this->config_json;
		}
		$decoded = json_decode( $this->config_json, true );
		return is_array( $decoded ) ? $decoded : array( 'endpoints' => array() );
	}

	public function set_config( array $config ) {
		$this->config_json = wp_json_encode( $config );
	}

	public function get_sync_method_label() {
		switch ( $this->sync_method ) {
			case 'shopify_json':
				return __( 'cURL (Shopify REST JSON)', 'hwsync' );
			case 'browser_headless':
				return __( 'In-Browser Headless (Client-Side DOM)', 'hwsync' );
			case 'curl_html':
			default:
				return ! empty( $this->adapter_class ) 
					? __( 'cURL (Native PHP Adapter)', 'hwsync' ) 
					: __( 'cURL (Standard HTML / WooCommerce)', 'hwsync' );
		}
	}

	public function save() {
		global $wpdb;
		$table = Database::get_table_name( 'vendors' );

		$data = array(
			'vendor_slug'   => $this->vendor_slug,
			'vendor_name'   => $this->vendor_name,
			'base_url'      => $this->base_url,
			'adapter_class' => $this->adapter_class,
			'sync_method'   => $this->sync_method ?: 'curl_html',
			'config_json'   => is_array( $this->config_json ) ? wp_json_encode( $this->config_json ) : $this->config_json,
			'is_active'     => $this->is_active ? 1 : 0,
			'last_sync_at'  => $this->last_sync_at,
		);

		if ( $this->id ) {
			$wpdb->update( $table, $data, array( 'id' => $this->id ) );
		} else {
			$wpdb->insert( $table, $data );
			$this->id = $wpdb->insert_id;
		}

		return $this->id;
	}

	public function update_last_sync() {
		global $wpdb;
		$table = Database::get_table_name( 'vendors' );
		$now = current_time( 'mysql' );
		$this->last_sync_at = $now;
		$wpdb->update( $table, array( 'last_sync_at' => $now ), array( 'id' => $this->id ) );
	}

	public function delete() {
		global $wpdb;
		if ( ! $this->id ) return false;

		$vendors_table = Database::get_table_name( 'vendors' );
		$prices_table  = Database::get_table_name( 'vendor_prices' );
		$history_table = Database::get_table_name( 'price_history' );

		$wpdb->delete( $prices_table, array( 'vendor_id' => $this->id ) );
		$wpdb->delete( $history_table, array( 'vendor_id' => $this->id ) );
		$wpdb->delete( $vendors_table, array( 'id' => $this->id ) );

		return true;
	}
}
