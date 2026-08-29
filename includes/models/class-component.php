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
	public $image_url;
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
		$vendor   = isset( $args['vendor'] ) ? trim( $args['vendor'] ) : ( isset( $args['vendor_slug'] ) ? trim( $args['vendor_slug'] ) : '' );
		$search   = isset( $args['search'] ) ? trim( $args['search'] ) : '';
		$limit    = isset( $args['limit'] ) ? intval( $args['limit'] ) : 100;
		$offset   = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;

		$where = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $category ) && $category !== 'all' ) {
			$where .= ' AND category = %s';
			$params[] = $category;
		}

		if ( ! empty( $vendor ) && $vendor !== 'all' ) {
			$prices_table  = Database::get_table_name( 'vendor_prices' );
			$vendors_table = Database::get_table_name( 'vendors' );
			$where .= " AND id IN (SELECT DISTINCT vp.component_id FROM {$prices_table} vp INNER JOIN {$vendors_table} v ON vp.vendor_id = v.id WHERE v.vendor_slug = %s)";
			$params[] = $vendor;
		}

		if ( ! empty( $search ) ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (brand LIKE %s OR model_name LIKE %s OR mpn LIKE %s OR sku LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
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
		$vendor   = isset( $args['vendor'] ) ? trim( $args['vendor'] ) : ( isset( $args['vendor_slug'] ) ? trim( $args['vendor_slug'] ) : '' );
		$search   = isset( $args['search'] ) ? trim( $args['search'] ) : '';

		$where = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $category ) && $category !== 'all' ) {
			$where .= ' AND category = %s';
			$params[] = $category;
		}

		if ( ! empty( $vendor ) && $vendor !== 'all' ) {
			$prices_table  = Database::get_table_name( 'vendor_prices' );
			$vendors_table = Database::get_table_name( 'vendors' );
			$where .= " AND id IN (SELECT DISTINCT vp.component_id FROM {$prices_table} vp INNER JOIN {$vendors_table} v ON vp.vendor_id = v.id WHERE v.vendor_slug = %s)";
			$params[] = $vendor;
		}

		if ( ! empty( $search ) ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (brand LIKE %s OR model_name LIKE %s OR mpn LIKE %s OR sku LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( empty( $params ) ) {
			return intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
		}

		return intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", ...$params ) ) );
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
			'image_url'  => $this->image_url,
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

	public function get_image_url() {
		if ( ! empty( $this->image_url ) ) {
			return $this->image_url;
		}
		$specs = $this->get_specs();
		if ( ! empty( $specs['image_url'] ) ) {
			return $specs['image_url'];
		}
		return '';
	}

	public function delete() {
		global $wpdb;
		if ( ! $this->id ) {
			return false;
		}
		$comp_table    = Database::get_table_name( 'components' );
		$prices_table  = Database::get_table_name( 'vendor_prices' );
		$history_table = Database::get_table_name( 'price_history' );

		// Delete linked WP post if exists
		if ( ! empty( $this->wp_post_id ) && function_exists( 'wp_delete_post' ) ) {
			wp_delete_post( $this->wp_post_id, true );
		}

		$wpdb->delete( $prices_table, array( 'component_id' => $this->id ) );
		$wpdb->delete( $history_table, array( 'component_id' => $this->id ) );
		$wpdb->delete( $comp_table, array( 'id' => $this->id ) );
		return true;
	}

	public static function delete_vendor_records( $vendor_slug_or_id, $component_ids = array() ) {
		global $wpdb;
		$prices_table  = Database::get_table_name( 'vendor_prices' );
		$comp_table    = Database::get_table_name( 'components' );
		$vendors_table = Database::get_table_name( 'vendors' );
		$history_table = Database::get_table_name( 'price_history' );

		$vendor_id = 0;
		if ( is_numeric( $vendor_slug_or_id ) && intval( $vendor_slug_or_id ) > 0 ) {
			$vendor_id = intval( $vendor_slug_or_id );
		} else {
			$vendor = Vendor::find_by_slug( (string) $vendor_slug_or_id );
			if ( $vendor ) {
				$vendor_id = intval( $vendor->id );
			}
		}

		if ( ! $vendor_id ) {
			return array(
				'success'            => false,
				'message'            => __( 'Vendor not found.', 'hwsync' ),
				'prices_deleted'     => 0,
				'components_removed' => 0,
				'components_updated' => 0,
			);
		}

		// Find affected component IDs
		$comp_id_filter = '';
		if ( ! empty( $component_ids ) ) {
			$clean_ids = array_map( 'intval', array_filter( $component_ids ) );
			if ( ! empty( $clean_ids ) ) {
				$comp_id_filter = ' AND component_id IN (' . implode( ',', $clean_ids ) . ')';
			}
		}

		$sql_affected = "SELECT DISTINCT component_id FROM {$prices_table} WHERE vendor_id = %d {$comp_id_filter}";
		$affected_comp_ids = $wpdb->get_col( $wpdb->prepare( $sql_affected, $vendor_id ) );

		if ( empty( $affected_comp_ids ) ) {
			return array(
				'success'            => true,
				'message'            => __( 'No matching vendor price records found.', 'hwsync' ),
				'prices_deleted'     => 0,
				'components_removed' => 0,
				'components_updated' => 0,
			);
		}

		// Delete target vendor price records
		$del_sql = $wpdb->prepare( "DELETE FROM {$prices_table} WHERE vendor_id = %d {$comp_id_filter}", $vendor_id );
		$deleted_prices_count = $wpdb->query( $del_sql );

		// Clean price history for deleted prices
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$history_table} WHERE vendor_id = %d {$comp_id_filter}", $vendor_id ) );

		$components_removed_count = 0;
		$components_updated_count = 0;

		foreach ( $affected_comp_ids as $cid ) {
			$cid = intval( $cid );
			// Check if remaining vendor prices exist for this component
			$remaining_count = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$prices_table} WHERE component_id = %d", $cid ) ) );

			if ( $remaining_count === 0 ) {
				// Component has no remaining vendor prices -> delete orphan component and WP post
				$comp = self::find_by_id( $cid );
				if ( $comp ) {
					if ( ! empty( $comp->wp_post_id ) && function_exists( 'wp_delete_post' ) ) {
						wp_delete_post( $comp->wp_post_id, true );
					}
					$wpdb->delete( $comp_table, array( 'id' => $cid ) );
					$components_removed_count++;
				}
			} else {
				// Component still has prices from other stores -> recalculate lowest price & update postmeta
				$lowest_price_row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT price FROM {$prices_table} WHERE component_id = %d AND is_in_stock = 1 ORDER BY price ASC LIMIT 1",
						$cid
					),
					\ARRAY_A
				);

				$comp = self::find_by_id( $cid );
				if ( $comp ) {
					$lowest_val = $lowest_price_row ? floatval( $lowest_price_row['price'] ) : 0;
					if ( ! empty( $comp->wp_post_id ) && function_exists( 'update_post_meta' ) ) {
						update_post_meta( $comp->wp_post_id, '_hwsync_lowest_price', $lowest_val );
						update_post_meta( $comp->wp_post_id, '_pcspecs_lowest_price', $lowest_val );
					}
					$components_updated_count++;
				}
			}
		}

		return array(
			'success'            => true,
			'prices_deleted'     => intval( $deleted_prices_count ),
			'components_removed' => $components_removed_count,
			'components_updated' => $components_updated_count,
			'message'            => sprintf(
				__( 'Successfully deleted %1$d vendor price listings. %2$d orphan components removed, %3$d components updated.', 'hwsync' ),
				$deleted_prices_count,
				$components_removed_count,
				$components_updated_count
			),
		);
	}
}
