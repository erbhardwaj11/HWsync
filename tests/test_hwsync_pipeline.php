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

function is_wp_error( $thing ) {
	return ( $thing instanceof WP_Error );
}

class WP_Error {
	public function get_error_message() {
		return 'An error occurred';
	}
}

function untrailingslashit( $val ) {
	return rtrim( $val, '/\\' );
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function current_time( $type ) {
	return date( 'Y-m-d H:i:s' );
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function esc_html( $text ) {
	return htmlspecialchars( (string)$text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string)$text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}
function wp_trim_words( $text, $num_words = 55, $more = null ) {
	$words = explode( ' ', $text );
	if ( count( $words ) > $num_words ) {
		return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( $more ?: '...' );
	}
	return $text;
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
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-component.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-vendor.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-vendor-price.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-matching-engine.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/abstract-vendor-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-mdcomputers-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-vedant-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-primeabgb-adapter.php';
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

echo "\n---------------------------------------------\n";
echo "Tests Passed: {$passed} | Failed: {$failed}\n";
echo "---------------------------------------------\n";

if ( $failed > 0 ) {
	exit(1);
}
