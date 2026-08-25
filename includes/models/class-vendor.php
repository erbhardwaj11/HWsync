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
	public $is_active;
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

	public function save() {
		global $wpdb;
		$table = Database::get_table_name( 'vendors' );

		$data = array(
			'vendor_slug'   => $this->vendor_slug,
			'vendor_name'   => $this->vendor_name,
			'base_url'      => $this->base_url,
			'adapter_class' => $this->adapter_class,
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
}
