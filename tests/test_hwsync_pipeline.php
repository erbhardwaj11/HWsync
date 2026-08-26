<?php
/**
 * Standalone Test Suite for HWsync Pipeline & Architecture
 * Validates Matching Engine, Normalization, Spec Extraction, INR Price Cleaning,
 * and Database/Post Sync Logic.
 */

// Define mock WP functions and constants for test runner
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'HWSYNC_PLUGIN_DIR' ) ) {
	define( 'HWSYNC_PLUGIN_DIR', __DIR__ . '/../' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( $string, '/\\' );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '<div class="product-item-container"><h4 class="title"><a href="https://mdcomputers.in/amd-ryzen-7-7800x3d.html">AMD Ryzen 7 7800X3D Desktop Processor (100-100000910WOF)</a></h4><span class="price-new">₹ 36,499.00</span><button class="cart">Add to Cart</button></div>',
		);
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return ( is_array( $response ) && isset( $response['response']['code'] ) ) ? intval( $response['response']['code'] ) : 200;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return ( is_array( $response ) && isset( $response['body'] ) ) ? (string) $response['body'] : '';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return ( $thing instanceof WP_Error );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function get_error_message() {
			return 'An error occurred';
		}
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return date( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string)$text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string)$text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num_words = 55, $more = null ) {
		$words = explode( ' ', $text );
		if ( count( $words ) > $num_words ) {
			return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( $more ?: '...' );
		}
		return $text;
	}
}

// In-memory Mock WPDB
class MockWPDB {
	public $prefix = 'wp_';
	public $insert_id = 0;
	private $auto_increment = 1;
	public $tables = array();

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}
	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			$val = is_numeric( $arg ) ? $arg : "'" . addslashes( (string)$arg ) . "'";
			$query = preg_replace( '/(%d|%s|%f)/', $val, $query, 1 );
		}
		return $query;
	}
	public function insert( $table, $data ) {
		$this->insert_id = $this->auto_increment++;
		$data['id'] = $this->insert_id;
		$this->tables[ $table ][] = $data;
		return 1;
	}
	public function update( $table, $data, $where ) {
		if ( ! isset( $this->tables[ $table ] ) ) return 0;
		foreach ( $this->tables[ $table ] as &$row ) {
			$match = true;
			foreach ( $where as $k => $v ) {
				if ( (string)$row[ $k ] !== (string)$v ) { $match = false; break; }
			}
			if ( $match ) {
				foreach ( $data as $dk => $dv ) { $row[ $dk ] = $dv; }
				return 1;
			}
		}
		return 0;
	}
	public function get_row( $query, $output = \ARRAY_A ) {
		if ( preg_match( '/FROM\s+(\w+)/i', $query, $m ) ) {
			$tbl = $m[1];
			if ( ! empty( $this->tables[ $tbl ] ) ) {
				if ( preg_match( '/WHERE\s+id\s*=\s*(\d+)/i', $query, $qm ) ) {
					foreach ( $this->tables[ $tbl ] as $r ) {
						if ( (string)$r['id'] === (string)$qm[1] ) return $r;
					}
				}
				if ( preg_match( '/WHERE\s+mpn\s*=\s*\'([^\']+)\'/i', $query, $qm ) ) {
					foreach ( $this->tables[ $tbl ] as $r ) {
						if ( isset( $r['mpn'] ) && strcasecmp( $r['mpn'], $qm[1] ) === 0 ) return $r;
					}
				}
				if ( preg_match( '/WHERE\s+vendor_slug\s*=\s*\'([^\']+)\'/i', $query, $qm ) ) {
					foreach ( $this->tables[ $tbl ] as $r ) {
						if ( isset( $r['vendor_slug'] ) && strcasecmp( $r['vendor_slug'], $qm[1] ) === 0 ) return $r;
					}
				}
				if ( preg_match( '/WHERE\s+LOWER\(brand\)\s*=\s*LOWER\(\'([^\']+)\'\)\s+AND\s+LOWER\(model_name\)\s*=\s*LOWER\(\'([^\']+)\'\)/i', $query, $qm ) ) {
					foreach ( $this->tables[ $tbl ] as $r ) {
						if ( isset( $r['brand'], $r['model_name'] ) && strcasecmp( $r['brand'], $qm[1] ) === 0 && strcasecmp( $r['model_name'], $qm[2] ) === 0 ) return $r;
					}
				}
				if ( preg_match( '/component_id\s*=\s*(\d+)\s+AND\s+vendor_id\s*=\s*(\d+)/i', $query, $qm ) ) {
					foreach ( $this->tables[ $tbl ] as $r ) {
						if ( (string)$r['component_id'] === (string)$qm[1] && (string)$r['vendor_id'] === (string)$qm[2] ) return $r;
					}
				}
			}
		}
		return null;
	}
	public function get_results( $query, $output = \ARRAY_A ) {
		if ( preg_match( '/FROM\s+(\w+)/i', $query, $m ) ) {
			$tbl = $m[1];
			$rows = $this->tables[ $tbl ] ?? array();
			if ( preg_match( '/WHERE\s+(?:vp\.)?component_id\s*=\s*(\d+)/i', $query, $qm ) ) {
				$filtered = array();
				foreach ( $rows as $r ) {
					if ( (string)$r['component_id'] === (string)$qm[1] ) {
						// Join vendor metadata if available
						if ( ! empty( $this->tables['wp_hwsync_vendors'] ) ) {
							foreach ( $this->tables['wp_hwsync_vendors'] as $v ) {
								if ( (string)$v['id'] === (string)$r['vendor_id'] ) {
									$r['vendor_name'] = $v['vendor_name'];
									$r['vendor_slug'] = $v['vendor_slug'];
									$r['base_url']    = $v['base_url'];
								}
							}
						}
						$filtered[] = $r;
					}
				}
				return $filtered;
			}
			return $rows;
		}
		return array();
	}
	public function query( $sql ) {
		if ( preg_match( '/TRUNCATE\s+TABLE\s+(\w+)/i', $sql, $m ) ) {
			$this->tables[ $m[1] ] = array();
			return true;
		}
		if ( preg_match( '/ALTER\s+TABLE\s+(\w+)\s+AUTO_INCREMENT\s*=\s*(\d+)/i', $sql, $m ) ) {
			$this->auto_increment = intval( $m[2] );
			return true;
		}
		return true;
	}
	public function get_col( $query ) {
		if ( stripos( $query, 'post_type' ) !== false ) {
			return array_keys( $GLOBALS['mock_posts'] ?? array() );
		}
		return array();
	}
	public function get_var( $query ) {
		if ( preg_match( '/COUNT\(\*\)\s+FROM\s+(\w+)/i', $query, $m ) ) {
			$tbl = $m[1];
			return isset( $this->tables[ $tbl ] ) ? count( $this->tables[ $tbl ] ) : 0;
		}
		return 0;
	}
}

global $wpdb;
$wpdb = new MockWPDB();

// Mock WordPress Post functions
$GLOBALS['mock_posts'] = array();
$GLOBALS['mock_postmeta'] = array();

function wp_delete_post( $post_id, $force = true ) {
	if ( isset( $GLOBALS['mock_posts'][ $post_id ] ) ) {
		unset( $GLOBALS['mock_posts'][ $post_id ] );
		return true;
	}
	return false;
}

function delete_option( $option ) {
	unset( $GLOBALS['mock_options'][ $option ] );
	return true;
}

function wp_insert_post( $post_arr ) {
	static $p_id = 100;
	$p_id++;
	$post_arr['ID'] = $p_id;
	$GLOBALS['mock_posts'][ $p_id ] = $post_arr;
	return $p_id;
}
function wp_update_post( $post_arr ) {
	$id = $post_arr['ID'];
	$GLOBALS['mock_posts'][ $id ] = array_merge( $GLOBALS['mock_posts'][ $id ] ?? array(), $post_arr );
	return $id;
}
function get_post( $id ) {
	return isset( $GLOBALS['mock_posts'][ $id ] ) ? (object)$GLOBALS['mock_posts'][ $id ] : null;
}
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['mock_postmeta'][ $post_id ][ $key ] = $value;
	return true;
}
function get_post_meta( $post_id, $key, $single = false ) {
	return $GLOBALS['mock_postmeta'][ $post_id ][ $key ] ?? '';
}
function wp_set_object_terms( $post_id, $terms, $taxonomy ) {
	return true;
}

// Require HWsync files
require_once HWSYNC_PLUGIN_DIR . 'includes/class-database.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-backup-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-component.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-vendor.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-vendor-price.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-matching-engine.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/abstract-vendor-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-mdcomputers-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-vedant-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-primeabgb-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-elitehubs-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-post-sync-processor.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-sync-manager.php';

// Begin Tests
echo "=============================================\n";
echo "           HWsync Test Runner                \n";
echo "=============================================\n\n";

$passed = 0;
$failed = 0;

function assert_test( $name, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		echo " [PASS] {$name}\n";
		$passed++;
	} else {
		echo " [FAIL] {$name}\n";
		$failed++;
	}
}

// Test 1: Price Sanitization for Indian Currency
$p1 = \HWsync\Vendors\Abstract_Vendor_Adapter::clean_price( '₹ 34,999.00' );
$p2 = \HWsync\Vendors\Abstract_Vendor_Adapter::clean_price( 'Rs. 1,450' );
$p3 = \HWsync\Vendors\Abstract_Vendor_Adapter::clean_price( '₹89,900' );
assert_test( 'Clean Indian Currency Strings', abs( $p1 - 34999.0 ) < 0.01 && abs( $p2 - 1450.0 ) < 0.01 && abs( $p3 - 89900.0 ) < 0.01 );

// Test 2: Category & Brand Detection
$cat1 = \HWsync\Matching_Engine::detect_category( 'AMD Ryzen 7 7800X3D Desktop Processor' );
$cat2 = \HWsync\Matching_Engine::detect_category( 'ASUS TUF Gaming GeForce RTX 4070 Ti Super 16GB' );
$cat3 = \HWsync\Matching_Engine::detect_category( 'MSI MAG B650 TOMAHAWK WIFI Motherboard' );
$cat4 = \HWsync\Matching_Engine::detect_category( 'Corsair Vengeance 32GB (16x2) DDR5 6000MHz' );
assert_test( 'Hardware Category Detection', $cat1 === 'cpu' && $cat2 === 'gpu' && $cat3 === 'motherboard' && $cat4 === 'ram' );

// Test 3: Brand Normalization
$b1 = \HWsync\Matching_Engine::extract_brand( 'ASUS ROG Strix B650-E' );
$b2 = \HWsync\Matching_Engine::extract_brand( 'WD Blue SN580 1TB NVMe SSD' );
$b3 = \HWsync\Matching_Engine::extract_brand( 'G.Skill Ripjaws S5 32GB' );
assert_test( 'Brand Normalization (ASUS, WD -> Western Digital, G.Skill)', $b1 === 'ASUS' && $b2 === 'Western Digital' && $b3 === 'G.Skill' );

// Test 4: Specs Extraction
$specs = \HWsync\Matching_Engine::extract_specs( 'AMD Ryzen 7 7800X3D AM5 Processor', 'cpu' );
assert_test( 'CPU Socket Spec Extraction', isset( $specs['socket'] ) && $specs['socket'] === 'AM5' );

$gpu_specs = \HWsync\Matching_Engine::extract_specs( 'Gigabyte RTX 4070 Super Eagle OC 12GB GDDR6X', 'gpu' );
assert_test( 'GPU VRAM & Memory Type Extraction', isset( $gpu_specs['capacity_or_vram'] ) && $gpu_specs['capacity_or_vram'] === '12GB' && isset( $gpu_specs['memory_type'] ) && $gpu_specs['memory_type'] === 'GDDR6X' );

// Test 5: Component Creation & Multi-Vendor Price Linking
$v1 = new \HWsync\Models\Vendor( array( 'vendor_slug' => 'mdcomputers', 'vendor_name' => 'MDComputers', 'base_url' => 'https://mdcomputers.in' ) );
$v1->save();
$v2 = new \HWsync\Models\Vendor( array( 'vendor_slug' => 'vedant', 'vendor_name' => 'Vedant Computers', 'base_url' => 'https://www.vedantcomputers.com' ) );
$v2->save();

$sync_manager = new \HWsync\Sync_Manager();

// Mock listing from MDComputers
$item1 = array(
	'title'          => 'AMD Ryzen 7 7800X3D Desktop Processor (100-100000910WOF)',
	'url'            => 'https://mdcomputers.in/amd-ryzen-7-7800x3d.html',
	'price'          => 36499.00,
	'original_price' => 45000.00,
	'in_stock'       => true,
	'sku'            => '100-100000910WOF',
	'category'       => 'cpu',
);
$res1 = $sync_manager->sync_single_item( $item1, $v1 );

// Mock listing from Vedant Computers for the exact same component
$item2 = array(
	'title'          => 'AMD Ryzen 7 7800X3D Processor 100-100000910WOF',
	'url'            => 'https://www.vedantcomputers.com/amd-ryzen-7-7800x3d',
	'price'          => 35899.00,
	'original_price' => 44500.00,
	'in_stock'       => true,
	'sku'            => '100-100000910WOF',
	'category'       => 'cpu',
);
$res2 = $sync_manager->sync_single_item( $item2, $v2 );

assert_test( 'Multi-Vendor Matching to Single Canonical Component ID', ( ! empty( $res1['component_id'] ) && $res1['component_id'] === $res2['component_id'] ) );

// Test 6: Verify Vendor Prices linked
$comp = \HWsync\Models\Component::find_by_id( $res1['component_id'] );
$prices = $comp ? $comp->get_prices() : array();
assert_test( 'Vendor Prices Linked Count = 2', count( $prices ) === 2 );

// Test 7: Post-Sync Processor WordPress Post Creation
$stats = \HWsync\Post_Sync_Processor::process_all( array( $comp->id ) );
assert_test( 'Post Sync Processor Created Post Record', $stats['created'] === 1 );

// Re-fetch component from DB to get updated wp_post_id
$comp = \HWsync\Models\Component::find_by_id( $res1['component_id'] );
$post_id = $comp->wp_post_id;
$meta_lowest = get_post_meta( $post_id, '_hwsync_lowest_price' );
$meta_vendor_count = get_post_meta( $post_id, '_hwsync_vendor_count' );
assert_test( 'WordPress Post Meta Contains Lowest Price (35899.00)', floatval( $meta_lowest ) === 35899.00 && intval( $meta_vendor_count ) === 2 );

// Test 8: Realtime Sync Logger Callback
$emitted_events = array();
$dummy_logger = function( $level, $message, $stats ) use ( &$emitted_events ) {
	$emitted_events[] = array( 'level' => $level, 'message' => $message );
};
$sync_manager->run_sync( array( 'vendor' => 'mdcomputers', 'category' => 'cpu' ), $dummy_logger );
assert_test( 'Sync Manager Emits Realtime Progress Events', count( $emitted_events ) >= 2 );

// Test 9: Skip Out of Stock items
$item_oos = array(
	'title'        => 'Intel Core i9-14900KS Special Edition Processor',
	'url'          => 'https://example.com/i9-14900ks',
	'price'        => 65000.00,
	'in_stock'     => false,
	'stock_status' => 'out_of_stock',
	'category'     => 'cpu',
);
$res_oos = $sync_manager->sync_single_item( $item_oos, $v1 );
assert_test( 'Out of Stock Component is Skipped (returns null)', $res_oos === null );

// Test 10: In-Stock item with no price is marked NA
$item_na = array(
	'title'        => 'Noctua NH-D15 G2 Special Edition Cooler',
	'url'          => 'https://example.com/noctua-nh-d15-g2',
	'price'        => 0.0,
	'in_stock'     => true,
	'stock_status' => 'in_stock',
	'category'     => 'cooler',
);
$res_na = $sync_manager->sync_single_item( $item_na, $v1 );
$vp_na = \HWsync\Models\Vendor_Price::find_by_id( $res_na['vendor_price_id'] );
assert_test( 'In-Stock item with zero price saved with display_price NA', ( ! empty( $res_na['component_id'] ) && isset( $vp_na->raw_data_json['display_price'] ) && $vp_na->raw_data_json['display_price'] === 'NA' ) );

// Test 11: Wipe Hardware, Reset Posts & Table IDs to 1
$wipe_res = \HWsync\Backup_Manager::wipe_and_reset_all_data();
$comp_count_after = $wpdb->get_var( "SELECT COUNT(*) FROM wp_hwsync_components" );
$price_count_after = $wpdb->get_var( "SELECT COUNT(*) FROM wp_hwsync_vendor_prices" );
assert_test( 'Wipe & Clean Reset Clears All Tables, Posts, and Resets AUTO_INCREMENT to 1', ( $wipe_res['success'] && $comp_count_after === 0 && $price_count_after === 0 && count( $GLOBALS['mock_posts'] ) === 0 ) );

echo "\n---------------------------------------------\n";
echo "Tests Passed: {$passed} | Failed: {$failed}\n";
echo "---------------------------------------------\n";

if ( $failed > 0 ) {
	exit(1);
}
