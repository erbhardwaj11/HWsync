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
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( strip_tags( $str ) ) : $str;
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( trim( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', (string)$title ), '-' ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string)$key ) );
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	function get_edit_post_link( $id ) {
		return 'https://example.com/wp-admin/post.php?post=' . intval( $id ) . '&action=edit';
	}
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
		if ( $type === 'timestamp' || $type === 'U' ) {
			return time();
		}
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

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	function post_type_exists( $post_type ) {
		return in_array( $post_type, array( 'post', 'page', 'hwsync_component', 'pcspecs_component' ) );
	}
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $taxonomy ) {
		return in_array( $taxonomy, array( 'category', 'post_tag', 'hwsync_category', 'hwsync_brand', 'pcspecs_category', 'pcspecs_brand' ) );
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	function wp_set_object_terms( $object_id, $terms, $taxonomy ) {
		return array( 1 );
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		return $content;
	}
}

// In-memory Mock WPDB
class MockWPDB {
	public $prefix = 'wp_';
	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
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
		if ( preg_match( '/SELECT\s+(?:pm\.)?post_id\s+FROM/i', $query ) ) {
			if ( preg_match( '/meta_value\s*=\s*(\d+)/i', $query, $m ) ) {
				$cid = $m[1];
				foreach ( $GLOBALS['mock_postmeta'] ?? array() as $pid => $meta ) {
					if ( isset( $meta['_hwsync_component_id'] ) && (string)$meta['_hwsync_component_id'] === (string)$cid ) {
						return $pid;
					}
				}
			}
		}
		if ( preg_match( '/SELECT\s+ID\s+FROM/i', $query ) ) {
			if ( preg_match( '/post_title\s*=\s*\'([^\']+)\'/i', $query, $m ) ) {
				$title = stripslashes( $m[1] );
				foreach ( $GLOBALS['mock_posts'] ?? array() as $pid => $p ) {
					if ( isset( $p['post_title'] ) && strcasecmp( $p['post_title'], $title ) === 0 ) {
						return $pid;
					}
				}
			}
			if ( preg_match( '/post_name\s*=\s*\'([^\']+)\'/i', $query, $m ) ) {
				$slug = $m[1];
				foreach ( $GLOBALS['mock_posts'] ?? array() as $pid => $p ) {
					if ( isset( $p['post_name'] ) && strcasecmp( $p['post_name'], $slug ) === 0 ) {
						return $pid;
					}
				}
			}
		}
		return null;
	}
}

global $wpdb;
$wpdb = new MockWPDB();

// Mock WordPress Post and Cron functions
$GLOBALS['mock_posts'] = array();
$GLOBALS['mock_postmeta'] = array();
$GLOBALS['mock_options'] = array();
$GLOBALS['mock_cron'] = array();

function get_option( $option, $default = false ) {
	return $GLOBALS['mock_options'][ $option ] ?? $default;
}

function update_option( $option, $value, $autoload = null ) {
	$GLOBALS['mock_options'][ $option ] = $value;
	return true;
}

function wp_next_scheduled( $hook ) {
	return $GLOBALS['mock_cron'][ $hook ] ?? false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
	$GLOBALS['mock_cron'][ $hook ] = $timestamp;
	return true;
}

function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
	unset( $GLOBALS['mock_cron'][ $hook ] );
	return true;
}

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
require_once HWSYNC_PLUGIN_DIR . 'includes/class-specs-sync-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-cron.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-component.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-vendor.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/models/class-vendor-price.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-matching-engine.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/abstract-vendor-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-mdcomputers-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-vedant-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-primeabgb-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-elitehubs-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-pcstudio-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-configurable-vendor-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-post-sync-processor.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-sync-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'public/class-public.php';

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

// Test 12: Manual Deep Specs Extraction
$cpu_raw_text = 'AMD Ryzen 7 7800X3D Desktop Processor 8 Cores 16 Threads Up to 5.0 GHz AM5 Socket 96MB 3D V-Cache 120W TDP';
$cpu_specs = \HWsync\Specs_Sync_Manager::extract_detailed_specs( 'cpu', $cpu_raw_text );
assert_test( 'Deep Specs Extraction for CPU (Socket, Cores, Threads, Boost, Cache, TDP)', (
	isset( $cpu_specs['socket'] ) && $cpu_specs['socket'] === 'AM5' &&
	isset( $cpu_specs['cores'] ) && $cpu_specs['cores'] === 8 &&
	isset( $cpu_specs['threads'] ) && $cpu_specs['threads'] === 16 &&
	isset( $cpu_specs['boost_clock'] ) && $cpu_specs['boost_clock'] === '5.0 GHz' &&
	isset( $cpu_specs['cache'] ) && $cpu_specs['cache'] === '96MB' &&
	isset( $cpu_specs['tdp'] ) && $cpu_specs['tdp'] === '120W'
) );

$gpu_raw_text = 'ZOTAC Gaming GeForce RTX 4080 Super Trinity Black Edition 16GB GDDR6X 256-bit PCIe 4.0 Recommended PSU 750W 3.5 Slot';
$gpu_specs = \HWsync\Specs_Sync_Manager::extract_detailed_specs( 'gpu', $gpu_raw_text );
assert_test( 'Deep Specs Extraction for GPU (VRAM, Type, Chipset, Bus, PSU)', (
	isset( $gpu_specs['vram_size'] ) && $gpu_specs['vram_size'] === '16GB' &&
	isset( $gpu_specs['memory_type'] ) && $gpu_specs['memory_type'] === 'GDDR6X' &&
	isset( $gpu_specs['gpu_chipset'] ) && $gpu_specs['gpu_chipset'] === 'RTX 4080 SUPER' &&
	isset( $gpu_specs['memory_bus'] ) && $gpu_specs['memory_bus'] === '256-bit' &&
	isset( $gpu_specs['recommended_psu'] ) && $gpu_specs['recommended_psu'] === '750W'
) );

// Test 13: Delta Sync Mode (Skip Unchanged vs Update Changed)
$dummy_vendor = new \HWsync\Models\Vendor( array( 'id' => 1, 'vendor_slug' => 'primeabgb', 'vendor_name' => 'PrimeABGB' ) );
$item_initial = array(
	'title'        => 'G.Skill Ripjaws S5 32GB (16GBx2) DDR5 6000MHz CL30 Memory',
	'url'          => 'https://example.com/gskill-32gb',
	'price'        => 9999.00,
	'in_stock'     => true,
	'stock_status' => 'in_stock',
	'category'     => 'ram',
);
$res_init = $sync_manager->sync_single_item( $item_initial, $dummy_vendor, false );
assert_test( 'Initial Sync in Standard Mode Created Record', ! empty( $res_init['component_id'] ) );

// Run again in delta mode with SAME price -> should be unchanged
$res_delta_unchanged = $sync_manager->sync_single_item( $item_initial, $dummy_vendor, true );
assert_test( 'Delta Mode Detects Unchanged Existing Record', ! empty( $res_delta_unchanged['unchanged'] ) );

// Run again in delta mode with CHANGED price -> should update
$item_price_drop = $item_initial;
$item_price_drop['price'] = 9499.00;
$res_delta_changed = $sync_manager->sync_single_item( $item_price_drop, $dummy_vendor, true );
$updated_vp = \HWsync\Models\Vendor_Price::find_by_id( $res_delta_changed['vendor_price_id'] );
assert_test( 'Delta Mode Successfully Updates Changed Price & Resets Unchanged Flag', ( empty( $res_delta_changed['unchanged'] ) && floatval( $updated_vp->price ) === 9499.00 ) );

// Test 14: Scheduled Sync Configuration
\HWsync\Cron::update_schedule( true, 'daily', '03:00' );
$cron_enabled = get_option( 'hwsync_schedule_enabled' );
$cron_time = get_option( 'hwsync_schedule_time' );
$cron_scheduled = wp_next_scheduled( \HWsync\Cron::CRON_HOOK );
assert_test( 'Cron Schedule Configuration Successfully Saved and Event Scheduled', ( $cron_enabled === 1 && $cron_time === '03:00' && ! empty( $cron_scheduled ) ) );

// Test 15: Fast Chunked Page Sync (Single Step)
$page_sync_res = $sync_manager->sync_page( 'mdcomputers', 'cpu', 1, false );
assert_test( 'Chunked Page Sync (Single Step) executes without error and returns logs', ( $page_sync_res['success'] && isset( $page_sync_res['logs'] ) && is_array( $page_sync_res['logs'] ) ) );

// Test 16: Strict Hardware Model Isolation (No False Fuzzy Matches Across Chipsets)
$item_5050 = array(
	'title'    => 'MSI RTX 5050 Shadow 2X OC 8GB GDDR6 Graphics Card',
	'price'    => 47700.00,
	'category' => 'gpu',
	'in_stock' => true,
);
$c_5050 = \HWsync\Matching_Engine::match_or_create_component( $item_5050 );

$item_4070 = array(
	'title'    => 'MSI RTX 4070 Shadow 2X OC 12GB GDDR6X Graphics Card',
	'price'    => 59999.00,
	'category' => 'gpu',
	'in_stock' => true,
);
$c_4070 = \HWsync\Matching_Engine::match_or_create_component( $item_4070 );

assert_test( 'Different GPU Chipsets (RTX 5050 vs RTX 4070) produce distinct canonical component IDs', ( $c_5050 && $c_4070 && $c_5050->id !== $c_4070->id ) );

// Test 17: Dynamic Custom Vendor Creation & Endpoint Configuration
$custom_vendor = new \HWsync\Models\Vendor( array(
	'vendor_name' => 'Clarion Computers',
	'vendor_slug' => 'clarioncomputers',
	'base_url'    => 'https://www.clarioncomputers.in',
	'sync_method' => 'curl_html',
	'is_active'   => 1,
) );
$custom_vendor->set_config( array(
	'endpoints' => array(
		'cpu' => '/product-category/processor/',
		'gpu' => '/product-category/graphics-card/',
	)
) );
$custom_vendor_id = $custom_vendor->save();
$fetched_custom = \HWsync\Models\Vendor::find_by_id( $custom_vendor_id );
$fetched_cfg = $fetched_custom ? $fetched_custom->get_config() : array();

assert_test( 'Custom Vendor Saved with Dynamic Endpoints & Sync Method', (
	$fetched_custom &&
	$fetched_custom->vendor_slug === 'clarioncomputers' &&
	$fetched_custom->sync_method === 'curl_html' &&
	isset( $fetched_cfg['endpoints']['cpu'] ) &&
	$fetched_cfg['endpoints']['cpu'] === '/product-category/processor/'
) );

// Test 18: Configurable Vendor Adapter Generic HTML Parser (1 Sample Extraction)
$sample_html = '<li class="product type-product post-101 status-publish instock">
	<a href="https://example.com/product/intel-core-i5-14600k" class="woocommerce-LoopProduct-link">
		<h2 class="woocommerce-loop-product__title">Intel Core i5-14600K 14-Core Processor</h2>
	</a>
	<span class="price"><del><bdi>₹34,000.00</bdi></del> <ins><bdi>₹27,499.00</bdi></ins></span>
</li>';

$configurable_adapter = new \HWsync\Vendors\Configurable_Vendor_Adapter(
	'clarioncomputers',
	'Clarion Computers',
	'https://www.clarioncomputers.in',
	'curl_html',
	array( 'cpu' => '/product-category/processor/' )
);
$parsed_samples = $configurable_adapter->parse_generic_html( $sample_html, 'cpu' );

assert_test( 'Configurable Vendor Adapter parsed 1 Sample component with accurate sale price', (
	! empty( $parsed_samples ) &&
	count( $parsed_samples ) === 1 &&
	$parsed_samples[0]['title'] === 'Intel Core i5-14600K 14-Core Processor' &&
	floatval( $parsed_samples[0]['price'] ) === 27499.00
) );

// Test 19: Multi-Vendor Aggregation under 1 Deduplicated Theme Post
$comp_7800 = \HWsync\Matching_Engine::match_or_create_component( array(
	'title'    => 'AMD Ryzen 7 7800X3D Desktop Processor (8 Cores 16 Threads 5.0GHz)',
	'price'    => 36499.00,
	'category' => 'cpu',
	'in_stock' => true,
) );

// Add prices from 3 different stores for the same component
$v_md = \HWsync\Models\Vendor::find_by_slug( 'mdcomputers' );
$v_vd = \HWsync\Models\Vendor::find_by_slug( 'vedant' );
$v_pr = \HWsync\Models\Vendor::find_by_slug( 'primeabgb' );

$vp1 = new \HWsync\Models\Vendor_Price( array(
	'component_id'         => $comp_7800->id,
	'vendor_id'            => $v_md ? $v_md->id : 1,
	'vendor_product_title' => 'MDComputers AMD Ryzen 7 7800X3D Processor',
	'price'                => 36499.00,
	'product_url'          => 'https://mdcomputers.in/amd-ryzen-7-7800x3d',
	'is_in_stock'          => 1,
	'stock_status'         => 'in_stock',
) );
$vp1->save();

$vp2 = new \HWsync\Models\Vendor_Price( array(
	'component_id'         => $comp_7800->id,
	'vendor_id'            => $v_vd ? $v_vd->id : 2,
	'vendor_product_title' => 'Vedant Computers AMD Ryzen 7 7800X3D',
	'price'                => 35899.00,
	'product_url'          => 'https://vedantcomputers.com/amd-ryzen-7-7800x3d',
	'is_in_stock'          => 1,
	'stock_status'         => 'in_stock',
) );
$vp2->save();

$vp3 = new \HWsync\Models\Vendor_Price( array(
	'component_id'         => $comp_7800->id,
	'vendor_id'            => $v_pr ? $v_pr->id : 3,
	'vendor_product_title' => 'PrimeABGB AMD Ryzen 7 7800X3D CPU',
	'price'                => 36200.00,
	'product_url'          => 'https://primeabgb.com/amd-ryzen-7-7800x3d',
	'is_in_stock'          => 1,
	'stock_status'         => 'in_stock',
) );
$vp3->save();

// Sync to post
$theme_sync_res = \HWsync\Post_Sync_Processor::sync_component_to_post( $comp_7800 );
$theme_post_id = $theme_sync_res['post_id'];

// Test 20: Verify Theme Metadata & Helper Functions
$post_prices = pcspecs_get_vendor_prices( $theme_post_id );
$post_lowest = pcspecs_get_lowest_price( $theme_post_id );

assert_test( 'Theme Sync creates 1 Single Post with 3 aggregated store prices and lowest price ₹35,899', (
	$theme_post_id > 0 &&
	$theme_sync_res['vendor_count'] === 3 &&
	count( $post_prices ) === 3 &&
	$post_lowest === 35899.00
) );

// Verify re-syncing doesn't duplicate post
$resync_res = \HWsync\Post_Sync_Processor::sync_component_to_post( $comp_7800 );
assert_test( 'Re-syncing same component updates existing post and prevents duplicate posts', (
	$resync_res['post_id'] === $theme_post_id &&
	$resync_res['action'] === 'updated'
) );

echo "\n---------------------------------------------\n";
echo "Tests Passed: {$passed} | Failed: {$failed}\n";
echo "---------------------------------------------\n";

if ( $failed > 0 ) {
	exit(1);
}
