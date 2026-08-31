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
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $val ) {
		return is_string( $val ) ? stripslashes( $val ) : $val;
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

	public function esc_like( $text ) {
		return addcslashes( (string)$text, '_%\\' );
	}

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}
	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) && count( $args ) === 1 ) {
			$args = $args[0];
		}
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
			if ( preg_match( '/\bid\s*=\s*(\d+)/i', $query, $qm ) ) {
				$filtered = array();
				foreach ( $rows as $r ) {
					if ( isset( $r['id'] ) && (string)$r['id'] === (string)$qm[1] ) {
						$filtered[] = $r;
					}
				}
				return $filtered;
			}
			if ( preg_match( '/category\s*=\s*\'([^\']+)\'/i', $query, $qm ) ) {
				$filtered = array();
				foreach ( $rows as $r ) {
					if ( isset( $r['category'] ) && strcasecmp( $r['category'], $qm[1] ) === 0 ) {
						$filtered[] = $r;
					}
				}
				return $filtered;
			}
			return $rows;
		}
		return array();
	}
	public function delete( $table, $where ) {
		if ( ! isset( $this->tables[ $table ] ) ) return 0;
		$deleted = 0;
		foreach ( $this->tables[ $table ] as $idx => $row ) {
			$match = true;
			foreach ( $where as $k => $v ) {
				if ( (string)$row[ $k ] !== (string)$v ) { $match = false; break; }
			}
			if ( $match ) {
				unset( $this->tables[ $table ][ $idx ] );
				$deleted++;
			}
		}
		$this->tables[ $table ] = array_values( $this->tables[ $table ] );
		return $deleted;
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
		if ( preg_match( '/DELETE\s+FROM\s+(\w+)\s+WHERE\s+(.+)/i', $sql, $m ) ) {
			$tbl = $m[1];
			$cond = $m[2];
			if ( ! empty( $this->tables[ $tbl ] ) ) {
				preg_match( '/vendor_id\s*=\s*(\d+)/i', $cond, $vm );
				$vid = $vm ? $vm[1] : null;
				preg_match( '/component_id\s+IN\s*\(([^)]+)\)/i', $cond, $cm );
				$cids = $cm ? array_map( 'trim', explode( ',', $cm[1] ) ) : null;
				preg_match( '/id\s*=\s*(\d+)/i', $cond, $idm );
				$idval = $idm ? $idm[1] : null;

				$deleted = 0;
				foreach ( $this->tables[ $tbl ] as $idx => $r ) {
					if ( $idval && (string)$r['id'] === (string)$idval ) {
						unset( $this->tables[ $tbl ][ $idx ] );
						$deleted++;
					} elseif ( $vid && (string)$r['vendor_id'] === (string)$vid ) {
						if ( ! $cids || in_array( (string)$r['component_id'], $cids ) ) {
							unset( $this->tables[ $tbl ][ $idx ] );
							$deleted++;
						}
					}
				}
				$this->tables[ $tbl ] = array_values( $this->tables[ $tbl ] );
				return $deleted;
			}
			return true;
		}
		return true;
	}
	public function get_col( $query ) {
		if ( stripos( $query, 'post_type' ) !== false ) {
			return array_keys( $GLOBALS['mock_posts'] ?? array() );
		}
		if ( preg_match( '/SELECT\s+DISTINCT\s+component_id\s+FROM\s+(\w+)\s+WHERE\s+(.+)/i', $query, $m ) ) {
			$tbl = $m[1];
			$cond = $m[2];
			$cols = array();
			if ( ! empty( $this->tables[ $tbl ] ) ) {
				preg_match( '/vendor_id\s*=\s*(\d+)/i', $cond, $vm );
				$vid = $vm ? $vm[1] : null;
				preg_match( '/component_id\s+IN\s*\(([^)]+)\)/i', $cond, $cm );
				$cids = $cm ? array_map( 'trim', explode( ',', $cm[1] ) ) : null;
				foreach ( $this->tables[ $tbl ] as $r ) {
					if ( $vid && (string)$r['vendor_id'] !== (string)$vid ) continue;
					if ( $cids && ! in_array( (string)$r['component_id'], $cids ) ) continue;
					if ( isset( $r['component_id'] ) && ! in_array( $r['component_id'], $cols ) ) {
						$cols[] = $r['component_id'];
					}
				}
			}
			return $cols;
		}
		return array();
	}
	public function get_var( $query ) {
		if ( preg_match( '/SHOW\s+TABLES\s+LIKE\s+\'([^\']+)\'/i', $query, $m ) ) {
			return isset( $this->tables[ $m[1] ] ) ? $m[1] : null;
		}
		if ( preg_match( '/COUNT\(\*\)\s+FROM\s+(\w+)(?:\s+WHERE\s+(.+))?/i', $query, $m ) ) {
			$tbl = $m[1];
			if ( empty( $m[2] ) ) {
				return isset( $this->tables[ $tbl ] ) ? count( $this->tables[ $tbl ] ) : 0;
			}
			$cond = $m[2];
			$count = 0;
			if ( ! empty( $this->tables[ $tbl ] ) ) {
				preg_match( '/component_id\s*=\s*(\d+)/i', $cond, $cm );
				$cid = $cm ? $cm[1] : null;
				foreach ( $this->tables[ $tbl ] as $r ) {
					if ( $cid && (string)$r['component_id'] === (string)$cid ) $count++;
				}
			}
			return $count;
		}
		if ( preg_match( '/SELECT\s+id\s+FROM\s+(\w+)\s+WHERE\s+(.+)/i', $query, $m ) ) {
			$tbl = $m[1];
			$cond = $m[2];
			if ( ! empty( $this->tables[ $tbl ] ) ) {
				if ( preg_match( '/mpn\s*=\s*\'([^\']+)\'/i', $cond, $qm ) ) {
					foreach ( $this->tables[ $tbl ] as $r ) {
						if ( isset( $r['mpn'] ) && strcasecmp( $r['mpn'], $qm[1] ) === 0 ) return $r['id'];
					}
				}
				if ( preg_match( '/slug\s*=\s*\'([^\']+)\'/i', $cond, $qm ) ) {
					foreach ( $this->tables[ $tbl ] as $r ) {
						if ( isset( $r['slug'] ) && strcasecmp( $r['slug'], $qm[1] ) === 0 ) return $r['id'];
					}
				}
				if ( preg_match( '/component_id\s*=\s*(\d+)\s+AND\s+vendor_name\s*=\s*\'([^\']+)\'/i', $cond, $qm ) ) {
					foreach ( $this->tables[ $tbl ] as $r ) {
						if ( isset( $r['component_id'], $r['vendor_name'] ) && (string)$r['component_id'] === (string)$qm[1] && strcasecmp( $r['vendor_name'], $qm[2] ) === 0 ) return $r['id'];
					}
				}
			}
		}
		if ( preg_match( '/SELECT\s+image_url\s+FROM\s+(\w+)\s+WHERE\s+(.+)/i', $query, $m ) ) {
			$tbl = $m[1];
			$cond = $m[2];
			if ( ! empty( $this->tables[ $tbl ] ) ) {
				preg_match( '/id\s*!=\s*(\d+)/i', $cond, $excl_m );
				$excl_id = $excl_m ? $excl_m[1] : null;

				preg_match( '/mpn\s*=\s*\'([^\']+)\'/i', $cond, $mpn_m );
				$mpn_val = $mpn_m ? $mpn_m[1] : null;

				preg_match( '/brand\s*=\s*\'([^\']+)\'/i', $cond, $brand_m );
				$brand_val = $brand_m ? $brand_m[1] : null;

				preg_match( '/model_name\s*=\s*\'([^\']+)\'/i', $cond, $model_m );
				$model_val = $model_m ? $model_m[1] : null;

				foreach ( $this->tables[ $tbl ] as $r ) {
					if ( $excl_id && (string)$r['id'] === (string)$excl_id ) {
						continue;
					}
					if ( empty( $r['image_url'] ) || strpos( $r['image_url'], '/uploads/' ) === false ) {
						continue;
					}
					if ( $mpn_val && ! empty( $r['mpn'] ) && strcasecmp( $r['mpn'], $mpn_val ) === 0 ) {
						return $r['image_url'];
					}
					if ( $brand_val && $model_val && isset( $r['brand'], $r['model_name'] ) && strcasecmp( $r['brand'], $brand_val ) === 0 && strcasecmp( $r['model_name'], $model_val ) === 0 ) {
						return $r['image_url'];
					}
				}
			}
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

// Require HWsync files
require_once HWSYNC_PLUGIN_DIR . 'includes/class-database.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-backup-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-specs-sync-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-image-sync-manager.php';
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
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-theitdepot-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-amazon-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/vendors/class-configurable-vendor-adapter.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/class-sync-manager.php';
require_once HWSYNC_PLUGIN_DIR . 'public/class-public.php';
require_once HWSYNC_PLUGIN_DIR . 'includes/helpers.php';

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

// Test 7: Multi-Vendor Pricing Aggregation & Lowest Price Calculation
$lowest = hwsync_get_lowest_price( $comp->id );
$all_prices = hwsync_get_vendor_prices( $comp->id );
assert_test( 'Multi-Vendor Pricing Table Contains Lowest Price (35899.00) across 2 stores', floatval( $lowest ) === 35899.00 && count( $all_prices ) === 2 );

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

// Test 20: 3-Store Multi-Vendor Aggregation & Lowest Price Detection
$all_3_prices = hwsync_get_vendor_prices( $comp_7800->id );
$lowest_3 = hwsync_get_lowest_price( $comp_7800->id );

assert_test( 'Multi-Vendor Engine aggregates 3 store prices and accurately detects lowest price INR 35899', (
	count( $all_3_prices ) === 3 &&
	$lowest_3 === 35899.00
) );

// Test 21: Current Sale/Offer Price Prioritization over MRP (MDComputers & OpenCart/WooCommerce)
$md_markup = '<span class="price"><span class="del"><span class=" amount"><span class="">&#8377;4,99,999</span></span></span><span class="ins"><span class=" amount"> &#8377;360,000<span class=""></span></span></span></span>';
$extracted_md = \HWsync\Vendors\Abstract_Vendor_Adapter::extract_clean_prices( $md_markup );

assert_test( 'Price Extractor accurately extracts MDComputers Current Offer Price (360000.00) instead of MRP (499999.00)', (
	$extracted_md['price'] === 360000.00 &&
	$extracted_md['original_price'] === 499999.00
) );

// Test 22: Component Deduplication & Merge Engine
// Create 2 separate component records for Cooler Master Shark X from 2 vendors
$comp_shark_1 = new \HWsync\Models\Component( array(
	'brand'      => 'Cooler Master',
	'model_name' => 'Shark X Mini ITX Case with Cooler Master Fan',
	'category'   => 'cabinet',
) );
$comp_shark_1->save();

$vp_shark_1 = new \HWsync\Models\Vendor_Price( array(
	'component_id'         => $comp_shark_1->id,
	'vendor_id'            => 1,
	'vendor_product_title' => 'Cooler Master Shark X Mini ITX Case',
	'price'                => 365000.00,
	'product_url'          => 'https://example.com/shark-x-1',
	'is_in_stock'          => 1,
) );
$vp_shark_1->save();

$comp_shark_2 = new \HWsync\Models\Component( array(
	'brand'      => 'Cooler Master',
	'model_name' => 'Shark X Mini ITX Gaming Cabinet',
	'category'   => 'cabinet',
) );
$comp_shark_2->save();

$vp_shark_2 = new \HWsync\Models\Vendor_Price( array(
	'component_id'         => $comp_shark_2->id,
	'vendor_id'            => 2,
	'vendor_product_title' => 'Cooler Master Shark X Mini ITX Case (Offer Price)',
	'price'                => 360000.00,
	'product_url'          => 'https://example.com/shark-x-2',
	'is_in_stock'          => 1,
) );
$vp_shark_2->save();

// Run component merge for cabinets
$merge_res = \HWsync\Matching_Engine::merge_duplicate_components( 'cabinet' );
$shark_merged_prices = hwsync_get_vendor_prices( $comp_shark_1->id );
$shark_lowest = hwsync_get_lowest_price( $comp_shark_1->id );

assert_test( 'Merge Engine consolidates duplicate component listings and sets lowest price (INR 360000.00)', (
	$merge_res['total_merged'] >= 1 &&
	count( $shark_merged_prices ) === 2 &&
	$shark_lowest === 360000.00
) );

// Test 23: Category Isolation during Component Merge
$comp_cpu_dummy = new \HWsync\Models\Component( array(
	'brand'      => 'AMD',
	'model_name' => 'Shark X Custom AMD CPU',
	'category'   => 'cpu',
) );
$comp_cpu_dummy->save();

$comp_mobo_dummy = new \HWsync\Models\Component( array(
	'brand'      => 'AMD',
	'model_name' => 'Shark X Custom AMD Motherboard',
	'category'   => 'motherboard',
) );
$comp_mobo_dummy->save();

$is_same_cat = \HWsync\Matching_Engine::is_same_hardware_component( $comp_cpu_dummy, $comp_mobo_dummy );
assert_test( 'Category Isolation prevents different hardware categories from ever merging', $is_same_cat === false );

// Test 24: Add New Custom Vendor and Update Sync Method
$custom_vendor = new \HWsync\Models\Vendor( array(
	'vendor_name' => 'Kalyan Computers',
	'vendor_slug' => 'kalyancomputers',
	'base_url'    => 'https://kalyancomputers.com',
	'sync_method' => 'shopify_json',
	'is_active'   => 1,
) );
$custom_vendor->set_config( array( 'endpoints' => array( 'cpu' => 'processors-amd' ) ) );
$v_id = $custom_vendor->save();

$retrieved_vendor = \HWsync\Models\Vendor::find_by_slug( 'kalyancomputers' );
assert_test( 'Vendor Model properly adds new retailer and persists custom sync_method and config_json', (
	$v_id > 0 &&
	$retrieved_vendor !== null &&
	$retrieved_vendor->sync_method === 'shopify_json' &&
	( $retrieved_vendor->get_config()['endpoints']['cpu'] ?? '' ) === 'processors-amd'
) );

// Test 25: Sync_Manager Dynamic Adapter Instance Selection
$manager = new \HWsync\Sync_Manager();
$adapter_instance = $manager->get_adapter_instance( $retrieved_vendor );

assert_test( 'Sync_Manager dynamically generates Configurable_Vendor_Adapter obeying updated sync_method', (
	$adapter_instance instanceof \HWsync\Vendors\Configurable_Vendor_Adapter
) );

// Test 26: Manual Component Merge
$comp_primary = new \HWsync\Models\Component( array(
	'brand'      => 'Gigabyte',
	'model_name' => 'GeForce RTX 4070 Windforce OC 12GB',
	'category'   => 'gpu',
) );
$comp_primary->save();

$vp_prim = new \HWsync\Models\Vendor_Price( array(
	'component_id'         => $comp_primary->id,
	'vendor_id'            => 1,
	'vendor_product_title' => 'Gigabyte RTX 4070 Windforce OC 12GB',
	'price'                => 55999.00,
	'product_url'          => 'https://example.com/gpu1',
	'is_in_stock'          => 1,
) );
$vp_prim->save();

$comp_secondary = new \HWsync\Models\Component( array(
	'brand'      => 'Gigabyte',
	'model_name' => 'RTX 4070 12GB Windforce Edition',
	'category'   => 'gpu',
) );
$comp_secondary->save();

$vp_sec = new \HWsync\Models\Vendor_Price( array(
	'component_id'         => $comp_secondary->id,
	'vendor_id'            => 2,
	'vendor_product_title' => 'Gigabyte RTX 4070 Windforce 12G Graphics Card',
	'price'                => 54500.00,
	'product_url'          => 'https://example.com/gpu2',
	'is_in_stock'          => 1,
) );
$vp_sec->save();

$manual_merge_res = \HWsync\Matching_Engine::manual_merge_components( $comp_primary->id, $comp_secondary->id );
$merged_prices = hwsync_get_vendor_prices( $comp_primary->id );
$lowest_merged_gpu = hwsync_get_lowest_price( $comp_primary->id );
$deleted_secondary = \HWsync\Models\Component::find_by_id( $comp_secondary->id );

assert_test( 'Manual Merge reassigns store prices, eliminates secondary component, and updates lowest price (INR 54500.00)', (
	! empty( $manual_merge_res['success'] ) &&
	count( $merged_prices ) === 2 &&
	$lowest_merged_gpu === 54500.00 &&
	$deleted_secondary === null
) );

// Test 27: Unmerge / Split Vendor Price into Standalone Component
$unmerge_res = \HWsync\Matching_Engine::unmerge_vendor_price( $vp_sec->id, 'Gigabyte RTX 4070 Custom Split Component' );
$old_comp_prices_after = hwsync_get_vendor_prices( $comp_primary->id );
$new_comp_created = \HWsync\Models\Component::find_by_id( $unmerge_res['new_component_id'] ?? 0 );
$new_comp_prices = $new_comp_created ? hwsync_get_vendor_prices( $new_comp_created->id ) : array();

assert_test( 'Unmerge / Split detaches store price, creates clean standalone component, and updates pairing matrices', (
	! empty( $unmerge_res['success'] ) &&
	count( $old_comp_prices_after ) === 1 &&
	$new_comp_created !== null &&
	$new_comp_created->model_name === 'Gigabyte RTX 4070 Custom Split Component' &&
	count( $new_comp_prices ) === 1
) );

// Test 28: Extract Product Image from HTML (OpenGraph, Schema, WooCommerce)
$mock_html_og = '<html><head><meta property="og:image" content="https://example.com/images/products/rtx-4070-windforce.jpg" /><title>Gigabyte RTX 4070</title></head><body><h1>Gigabyte RTX 4070</h1></body></html>';
$extracted_img_og = \HWsync\Image_Sync_Manager::extract_product_image_from_html( $mock_html_og, 'https://example.com/product/1' );

$mock_html_woo = '<html><body><div class="woocommerce-product-gallery__image"><a href="https://vendor.in/wp-content/uploads/2024/01/ryzen-7-7800x3d.png"><img src="thumb.jpg"/></a></div></body></html>';
$extracted_img_woo = \HWsync\Image_Sync_Manager::extract_product_image_from_html( $mock_html_woo, 'https://vendor.in/product/cpu' );

$mock_html_shopify = '<html><body><script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":"Corsair RM850e","image":["https://cdn.shopify.com/s/files/1/0000/products/rm850e_large.webp"]}</script></body></html>';
$extracted_img_shopify = \HWsync\Image_Sync_Manager::extract_product_image_from_html( $mock_html_shopify, 'https://store.in/products/rm850e' );

assert_test( 'Multi-Selector Product Image Extraction (OpenGraph, WooCommerce, Shopify CDN)', (
	$extracted_img_og === 'https://example.com/images/products/rtx-4070-windforce.jpg' &&
	$extracted_img_woo === 'https://vendor.in/wp-content/uploads/2024/01/ryzen-7-7800x3d.png' &&
	$extracted_img_shopify === 'https://cdn.shopify.com/s/files/1/0000/products/rm850e_large.webp'
) );

// Test 29: Product Image Association with Component Model
$comp_for_img = new \HWsync\Models\Component( array(
	'brand'      => 'AMD',
	'model_name' => 'Ryzen 7 7800X3D',
	'category'   => 'cpu',
	'image_url'  => 'https://example.com/wp-content/uploads/hwsync/amd-ryzen-7-7800x3d.jpg',
) );
$comp_for_img->save();

$saved_comp_with_img = \HWsync\Models\Component::find_by_id( $comp_for_img->id );
assert_test( 'Component Image Model persistence & get_image_url() resolution', (
	$saved_comp_with_img !== null &&
	$saved_comp_with_img->image_url === 'https://example.com/wp-content/uploads/hwsync/amd-ryzen-7-7800x3d.jpg' &&
	$saved_comp_with_img->get_image_url() === 'https://example.com/wp-content/uploads/hwsync/amd-ryzen-7-7800x3d.jpg'
) );

// Test 30: Local Image URL Detection
$is_local = \HWsync\Image_Sync_Manager::is_local_image_url( 'https://example.com/wp-content/uploads/hwsync/amd-ryzen-7-7800x3d.jpg' );
$is_external = \HWsync\Image_Sync_Manager::is_local_image_url( 'https://www.theitdepot.com/image/cache/catalog/asus3050.jpg' );
assert_test( 'Local Image Detection identifies WordPress uploaded images vs remote web URLs', (
	$is_local === true &&
	$is_external === false
) );

// Test 31: The IT Depot HTML Parsing & Offer Price Extraction
$mock_itdepot_html = '<div class="product-layout product-grid"><div class="image"><a href="https://www.theitdepot.com/Graphic_Card/asus_dual_geforce_rtx_3050"><img data-src="https://www.theitdepot.com/image/cache/catalog/asus3050.jpg" /></a></div><div class="caption"><div class="name"><a href="https://www.theitdepot.com/Graphic_Card/asus_dual_geforce_rtx_3050">Asus Dual GeForce RTX 3050 OC Edition 6GB GDDR6 (DUAL-RTX3050-O6G)</a></div><div class="price"><span class="price-new">₹60,530.00</span> <span class="price-old">₹42,999.00</span></div></div><div class="cart-group"></div></div>';
$itdepot_adapter = new \HWsync\Vendors\TheITDepot_Adapter();
$parsed_itdepot = $itdepot_adapter->parse_html( $mock_itdepot_html, 'gpu' );

assert_test( 'The IT Depot (Journal 3 / OpenCart) Adapter Parsing & Price Extraction', (
	count( $parsed_itdepot ) === 1 &&
	$parsed_itdepot[0]['title'] === 'Asus Dual GeForce RTX 3050 OC Edition 6GB GDDR6 (DUAL-RTX3050-O6G)' &&
	$parsed_itdepot[0]['price'] === 60530.00 &&
	$parsed_itdepot[0]['original_price'] === 42999.00 &&
	$parsed_itdepot[0]['sku'] === 'DUAL-RTX3050-O6G' &&
	$parsed_itdepot[0]['in_stock'] === 1 &&
	$parsed_itdepot[0]['image_url'] === 'https://www.theitdepot.com/image/cache/catalog/asus3050.jpg'
) );

// Test 32: Technical Specifications HTML Extraction & Noise/Disclaimer Filtering
$mock_specs_html = '<div id="tab-specification"><table>
<tr><td>CPU Cores</td><td>4</td></tr>
<tr><td>Thread Count</td><td>8</td></tr>
<tr><td>Sockets Supported</td><td>LGA1700</td></tr>
<tr><td>Processor Base Frequency</td><td>3.5 Ghz</td></tr>
<tr><td>Max Turbo Frequency</td><td>4.7 GHz</td></tr>
<tr><td>Note**</td><td>**Prices, Specifications & Features are subject to change without notice</td></tr>
<tr><td>Standard Shipping</td><td>Delivery typically takes *3-15 business days*, depending on the delivery location.</td></tr>
<tr><td>Wishlist</td><td>(</td></tr>
<tr><td>Compare</td><td>(</td></tr>
</table></div>';

$extracted_clean_specs = \HWsync\Specs_Sync_Manager::parse_html_specs_section( $mock_specs_html, 'cpu' );

assert_test( 'Technical Specifications HTML Parser extracts genuine attributes & filters out shipping, notes, and UI noise', (
	isset( $extracted_clean_specs['Socket'] ) && $extracted_clean_specs['Socket'] === 'LGA1700' &&
	isset( $extracted_clean_specs['Number of Cores'] ) && $extracted_clean_specs['Number of Cores'] === '4' &&
	isset( $extracted_clean_specs['Number of Threads'] ) && $extracted_clean_specs['Number of Threads'] === '8' &&
	isset( $extracted_clean_specs['Frequency'] ) && $extracted_clean_specs['Frequency'] === '3.5 Ghz' &&
	isset( $extracted_clean_specs['Turbo Clock'] ) && $extracted_clean_specs['Turbo Clock'] === '4.7 GHz' &&
	! isset( $extracted_clean_specs['Note**'] ) &&
	! isset( $extracted_clean_specs['Standard Shipping'] ) &&
	! isset( $extracted_clean_specs['Wishlist'] ) &&
	! isset( $extracted_clean_specs['Compare'] )
) );

// Test 33: Specifications Key Normalization & Merge Engine conforming to exact Category Schema
$normalized_specs = \HWsync\Specs_Sync_Manager::merge_and_clean_specs( 'cpu', $extracted_clean_specs, array(), 'Intel Core i3 14100F LGA1700 4 Cores 8 Threads 58W TDP' );

assert_test( 'Key Normalization & Clean Dictionary Merge Engine conforms strictly to category schema', (
	isset( $normalized_specs['Socket'] ) && $normalized_specs['Socket'] === 'LGA1700' &&
	isset( $normalized_specs['Number of Cores'] ) && $normalized_specs['Number of Cores'] === '4' &&
	isset( $normalized_specs['Number of Threads'] ) && $normalized_specs['Number of Threads'] === '8' &&
	isset( $normalized_specs['TDP'] ) && $normalized_specs['TDP'] === '58W' &&
	! isset( $normalized_specs['raw_specs_table'] ) &&
	! isset( $normalized_specs['CPU Socket Type'] ) &&
	! isset( $normalized_specs['Total Cores'] )
) );

// Test 34: Clear Component Specifications Persistence
$comp_for_specs = new \HWsync\Models\Component( array(
	'brand'      => 'Intel',
	'model_name' => 'Core i3-14100F',
	'category'   => 'cpu',
	'specs_json' => $normalized_specs,
) );
$comp_for_specs->save();

// Clear specs for this component
$comp_for_specs->specs_json = array();
$comp_for_specs->save();

$refreshed_comp = \HWsync\Models\Component::find_by_id( $comp_for_specs->id );
assert_test( 'Component Specification Clearing sets specs_json to empty/null without modifying component identity', (
	$refreshed_comp !== null &&
	( empty( $refreshed_comp->specs_json ) || $refreshed_comp->specs_json === array() ) &&
	$refreshed_comp->brand === 'Intel' &&
	$refreshed_comp->model_name === 'Core i3-14100F'
) );

// Test 35: Component Specifications Manual Edit & Key Normalization Save
$comp_for_edit = new \HWsync\Models\Component( array(
	'brand'      => 'AMD',
	'model_name' => 'Ryzen 5 7600',
	'category'   => 'cpu',
	'specs_json' => array( 'Note**' => 'Fake note', 'Socket' => 'AM5' ),
) );
$comp_for_edit->save();

// Simulate manual edit: user removes Note**, edits Socket, and adds Number of Cores and TDP
$manual_keys = array( 'cpu socket type', 'Processor Cores', 'TDP', '' );
$manual_vals = array( 'AM5', '6', '65 W', '' );

$edited_specs = array();
for ( $i = 0; $i < count( $manual_keys ); $i++ ) {
	$k = trim( $manual_keys[ $i ] );
	$v = trim( $manual_vals[ $i ] );
	if ( ! empty( $k ) && ! empty( $v ) ) {
		$norm_k = \HWsync\Specs_Sync_Manager::normalize_spec_key( $k, 'cpu' );
		$edited_specs[ $norm_k ] = $v;
	}
}

\HWsync\Specs_Sync_Manager::sync_post_specs( $comp_for_edit, $edited_specs );

$refreshed_edited = \HWsync\Models\Component::find_by_id( $comp_for_edit->id );
$refreshed_specs = $refreshed_edited ? $refreshed_edited->get_specs() : array();
assert_test( 'Component Specifications Manual Edit persists customized attributes and removes unwanted specs', (
	$refreshed_edited !== null &&
	count( $refreshed_specs ) === 3 &&
	isset( $refreshed_specs['Socket'] ) && $refreshed_specs['Socket'] === 'AM5' &&
	isset( $refreshed_specs['Number of Cores'] ) && $refreshed_specs['Number of Cores'] === '6' &&
	isset( $refreshed_specs['TDP'] ) && $refreshed_specs['TDP'] === '65 W' &&
	! isset( $refreshed_specs['Note**'] )
) );

// Test 36: Cloudflare Bot Challenge Interstitial Detection & Script Stripping
$mock_cf_challenge = '<!DOCTYPE html><html><head><title>Just a moment...</title><script>window._cf_chl_opt = { cs: { title: "Vaše připojení se ověřuje...", "content-title": "Než budete moct pokračovat" }, da: { title: "Omdirigerer..." } };</script></head><body><div id="challenge-running">Checking your browser before accessing retailer.com</div></body></html>';

$is_cf_detected = \HWsync\Specs_Sync_Manager::is_bot_challenge_html( $mock_cf_challenge );
$cf_extracted_specs = \HWsync\Specs_Sync_Manager::parse_html_specs_section( $mock_cf_challenge, 'cpu' );

assert_test( 'Cloudflare / Anti-Bot Interstitial Challenge is detected and rejected without saving multilingual script noise', (
	$is_cf_detected === true &&
	empty( $cf_extracted_specs )
) );

// Test 37: Category-Specific Synonyms & Strict Whitelist Filter across GPU, RAM, PSU, Cabinet, Storage
$raw_gpu_specs = array(
	'CUDA Cores' => '7680',
	'Stream Processors' => '7680',
	'Graphics Processor' => 'GeForce RTX 4070 Ti SUPER',
	'VRAM' => '16 GB',
	'Memory Interface Type' => 'GDDR6X',
	'Bus Width' => '256-bit',
	'Recommended Power Supply' => '750 W',
	'Random Non-Spec Extra' => 'Invalid Data',
);

$cleaned_gpu_specs = \HWsync\Specs_Sync_Manager::merge_and_clean_specs( 'gpu', $raw_gpu_specs );

assert_test( 'Category-Specific Synonyms & Strict Whitelisting (GPU Name, Shading Units, Memory Size, Suggested PSU, discarding random keys)', (
	isset( $cleaned_gpu_specs['GPU Name'] ) && $cleaned_gpu_specs['GPU Name'] === 'GeForce RTX 4070 Ti SUPER' &&
	isset( $cleaned_gpu_specs['Shading Units'] ) && $cleaned_gpu_specs['Shading Units'] === '7680' &&
	isset( $cleaned_gpu_specs['Memory Size'] ) && $cleaned_gpu_specs['Memory Size'] === '16 GB' &&
	isset( $cleaned_gpu_specs['Memory Type'] ) && $cleaned_gpu_specs['Memory Type'] === 'GDDR6X' &&
	isset( $cleaned_gpu_specs['Memory Bus'] ) && $cleaned_gpu_specs['Memory Bus'] === '256-bit' &&
	isset( $cleaned_gpu_specs['Suggested PSU'] ) && $cleaned_gpu_specs['Suggested PSU'] === '750 W' &&
	! isset( $cleaned_gpu_specs['Random Non-Spec Extra'] ) &&
	! isset( $cleaned_gpu_specs['CUDA Cores'] )
) );

// Test 38: Amazon India Adapter Scraping, ASIN & High-Res Photo Extraction, and Specs Sync
$mock_amazon_search_html = '<div data-asin="B0CQMQF95K" data-component-type="s-search-result" class="s-result-item">
    <div class="s-product-image-container">
        <img class="s-image" src="https://m.media-amazon.com/images/I/61N7S1f4s+L._AC_UY218_.jpg" alt="AMD Ryzen 5 7600X">
    </div>
    <h2><a class="a-link-normal" href="/dp/B0CQMQF95K"><span class="a-text-normal">AMD Ryzen 5 7600X Desktop Processor (6 Cores, 12 Threads, Up to 5.3 GHz, AM5)</span></a></h2>
    <span class="a-price-whole">19,799</span>
    <span class="a-price a-text-price" data-a-strike="true"><span class="a-offscreen">₹32,000</span></span>
</div>';

$amazon_adapter = new \HWsync\Vendors\Amazon_Adapter();
$parsed_amazon = $amazon_adapter->parse_html( $mock_amazon_search_html, 'cpu' );

assert_test( 'Amazon India Adapter parses search cards, extracts ASIN, canonical URL, pricing, and converts to high-res photo', (
	count( $parsed_amazon ) === 1 &&
	$parsed_amazon[0]['sku'] === 'B0CQMQF95K' &&
	$parsed_amazon[0]['url'] === 'https://www.amazon.in/dp/B0CQMQF95K' &&
	$parsed_amazon[0]['price'] === 19799.0 &&
	$parsed_amazon[0]['original_price'] === 32000.0 &&
	$parsed_amazon[0]['image_url'] === 'https://m.media-amazon.com/images/I/61N7S1f4s+L.jpg' &&
	$parsed_amazon[0]['in_stock'] === true
) );

// Test 39: Amazon Products CSV Export & Bulk Affiliate Links Updater Pipeline
$comp_amazon = new \HWsync\Models\Component( array(
	'brand'      => 'AMD',
	'model_name' => 'Ryzen 7 7800X3D',
	'category'   => 'cpu',
) );
$comp_amazon->save();

$amazon_vendor_rec = \HWsync\Models\Vendor::find_by_slug( 'amazon-in' );
$vendor_amazon_id = $amazon_vendor_rec ? $amazon_vendor_rec->id : 99;

$price_amazon = new \HWsync\Models\Vendor_Price( array(
	'component_id' => $comp_amazon->id,
	'vendor_id'    => $vendor_amazon_id,
	'product_url'  => 'https://www.amazon.in/dp/B0BTZB7F88',
	'price'        => 36999.00,
	'vendor_sku'   => 'B0BTZB7F88',
	'stock_status' => 'in_stock',
) );
$price_amazon->save();

// Simulate CSV Import with updated custom affiliate link
$mock_csv_line = array(
	'price_id'               => $price_amazon->id,
	'component_id'           => $comp_amazon->id,
	'asin___sku'             => 'B0BTZB7F88',
	'affiliate___custom_url' => 'https://www.amazon.in/dp/B0BTZB7F88?tag=mycustomtag-21',
);

$sanitized_affiliate_url = esc_url_raw( $mock_csv_line['affiliate___custom_url'] );
$price_amazon->product_url = $sanitized_affiliate_url;
$price_amazon->save();

$refreshed_price = \HWsync\Models\Vendor_Price::find_by_id( $price_amazon->id );

assert_test( 'Amazon Products CSV Bulk Link Updater updates stored product_url with affiliate tracking link', (
	$refreshed_price !== null &&
	$refreshed_price->product_url === 'https://www.amazon.in/dp/B0BTZB7F88?tag=mycustomtag-21' &&
	$refreshed_price->vendor_sku === 'B0BTZB7F88' &&
	$refreshed_price->price == 36999.00
) );

// Test 40: Component Catalog Vendor Filtering & Vendor Record Deletion (Selected & All Cascade)
$comp_multi1 = new \HWsync\Models\Component( array(
	'brand'      => 'Gigabyte',
	'model_name' => 'B650 AORUS ELITE AX',
	'category'   => 'motherboard',
) );
$comp_multi1->save();

$comp_multi2 = new \HWsync\Models\Component( array(
	'brand'      => 'MSI',
	'model_name' => 'MAG B650 TOMAHAWK WIFI',
	'category'   => 'motherboard',
) );
$comp_multi2->save();

// comp_multi1 has Amazon & MDComputers prices
$vp_amz1 = new \HWsync\Models\Vendor_Price( array(
	'component_id' => $comp_multi1->id,
	'vendor_id'    => $vendor_amazon_id,
	'product_url'  => 'https://www.amazon.in/dp/B0BHDV51JS',
	'price'        => 22499.00,
	'vendor_sku'   => 'B0BHDV51JS',
	'is_in_stock'  => 1,
) );
$vp_amz1->save();

$vp_md1 = new \HWsync\Models\Vendor_Price( array(
	'component_id' => $comp_multi1->id,
	'vendor_id'    => 1, // MDComputers
	'product_url'  => 'https://mdcomputers.in/gigabyte-b650.html',
	'price'        => 22999.00,
	'vendor_sku'   => 'GIGA-B650-ELITE',
	'is_in_stock'  => 1,
) );
$vp_md1->save();

// comp_multi2 ONLY has Amazon price
$vp_amz2 = new \HWsync\Models\Vendor_Price( array(
	'component_id' => $comp_multi2->id,
	'vendor_id'    => $vendor_amazon_id,
	'product_url'  => 'https://www.amazon.in/dp/B0BHC3V1DF',
	'price'        => 21999.00,
	'vendor_sku'   => 'B0BHC3V1DF',
	'is_in_stock'  => 1,
) );
$vp_amz2->save();

// Execute delete_vendor_records for Amazon India across these 2 components
$del_res = \HWsync\Models\Component::delete_vendor_records( 'amazon-in', array( $comp_multi1->id, $comp_multi2->id ) );

$c1_after = \HWsync\Models\Component::find_by_id( $comp_multi1->id );
$c2_after = \HWsync\Models\Component::find_by_id( $comp_multi2->id );
$c1_prices = \HWsync\Models\Vendor_Price::find_by_component_id( $comp_multi1->id );

assert_test( 'Component Catalog Vendor Deletion removes targeted vendor prices, recalculates remaining store lowest prices, and purges orphan components', (
	$del_res['success'] === true &&
	$del_res['prices_deleted'] === 2 &&
	$del_res['components_removed'] === 1 && // comp_multi2 had only Amazon, so removed
	$del_res['components_updated'] === 1 && // comp_multi1 still has MDComputers, so updated
	$c1_after !== null &&
	$c2_after === null &&
	count( $c1_prices ) === 1 &&
	$c1_prices[0]->price == 22999.00
) );

// Test 41: Bulk Component Deletion Pipeline ($comp->delete() and cascade cleanup)
$comp_to_delete = new \HWsync\Models\Component( array(
	'brand'      => 'Corsair',
	'model_name' => 'RM850x 850W Gold Power Supply',
	'category'   => 'psu',
) );
$comp_to_delete->save();

$vp_to_delete = new \HWsync\Models\Vendor_Price( array(
	'component_id' => $comp_to_delete->id,
	'vendor_id'    => 1,
	'product_url'  => 'https://mdcomputers.in/corsair-rm850x.html',
	'price'        => 11500.00,
	'vendor_sku'   => 'CORSAIR-RM850X',
	'is_in_stock'  => 1,
) );
$vp_to_delete->save();

$delete_success = $comp_to_delete->delete();
$comp_deleted_check = \HWsync\Models\Component::find_by_id( $comp_to_delete->id );
$prices_deleted_check = \HWsync\Models\Vendor_Price::find_by_component_id( $comp_to_delete->id );

assert_test( 'Bulk Component Deletion permanently removes component record and all associated vendor prices', (
	$delete_success === true &&
	$comp_deleted_check === null &&
	empty( $prices_deleted_check )
) );

// Test 42: Automated Scheduling for Price, Specs, and Image Sync Events
\HWsync\Cron::update_schedule( true, 'every_six_hours', '02:30' );
\HWsync\Cron::update_specs_schedule( true, 'daily', '04:15' );
\HWsync\Cron::update_image_schedule( true, 'twicedaily', '06:00' );

$price_cron_scheduled = wp_next_scheduled( \HWsync\Cron::CRON_HOOK );
$specs_cron_scheduled = wp_next_scheduled( \HWsync\Cron::CRON_SPECS_HOOK );
$image_cron_scheduled = wp_next_scheduled( \HWsync\Cron::CRON_IMAGE_HOOK );

$price_opt = get_option( 'hwsync_schedule_frequency' );
$specs_opt = get_option( 'hwsync_schedule_specs_frequency' );
$image_opt = get_option( 'hwsync_schedule_image_frequency' );

assert_test( 'Automated Scheduling configures and registers Cron events for Price, Specs, and Image sync runners independently', (
	$price_cron_scheduled !== false &&
	$specs_cron_scheduled !== false &&
	$image_cron_scheduled !== false &&
	$price_opt === 'every_six_hours' &&
	$specs_opt === 'daily' &&
	$image_opt === 'twicedaily'
) );

// Test 43: Strict Cabinet & Hardware Series Matching Isolation (Epoch XL vs Meshify 3 XL)
$raw_epoch = array(
	'title'    => 'FRACTAL DESIGN EPOCH XL BLACK SOLID PC CASE (FD-C-EPO1X-01)',
	'category' => 'cabinet',
	'price'    => 18325.0,
	'sku'      => 'FD-C-EPO1X-01',
);
$raw_meshify = array(
	'title'    => 'Fractal Design Meshify 3 XL Black Solid Mid Tower Case (FD-C-MES3X-01)',
	'category' => 'cabinet',
	'price'    => 19499.0,
	'sku'      => 'FD-C-MES3X-01',
);

$comp_epoch   = \HWsync\Matching_Engine::match_or_create_component( $raw_epoch );
$comp_meshify = \HWsync\Matching_Engine::match_or_create_component( $raw_meshify );

$is_same_check = \HWsync\Matching_Engine::is_same_hardware_component( $comp_epoch, $comp_meshify );

assert_test( 'Strict Cabinet Matching isolates Fractal Design Epoch XL from Meshify 3 XL without false-positive pairing', (
	$comp_epoch !== null &&
	$comp_meshify !== null &&
	$comp_epoch->id !== $comp_meshify->id &&
	$comp_epoch->brand === 'Fractal Design' &&
	$comp_meshify->brand === 'Fractal Design' &&
	$comp_epoch->mpn === 'FD-C-EPO1X-01' &&
	$comp_meshify->mpn === 'FD-C-MES3X-01' &&
	$is_same_check === false
) );

// Test 44: Fixed Category Specs Schema & Cascading Missing Specs Extraction
$allowed_gpu = \HWsync\Specs_Sync_Manager::$allowed_specs_by_category['gpu'];
$allowed_cpu = \HWsync\Specs_Sync_Manager::$allowed_specs_by_category['cpu'];
$allowed_mobo = \HWsync\Specs_Sync_Manager::$allowed_specs_by_category['motherboard'];
$allowed_cooler = \HWsync\Specs_Sync_Manager::$allowed_specs_by_category['cooler'];
$allowed_ram = \HWsync\Specs_Sync_Manager::$allowed_specs_by_category['ram'];
$allowed_psu = \HWsync\Specs_Sync_Manager::$allowed_specs_by_category['psu'];
$allowed_case = \HWsync\Specs_Sync_Manager::$allowed_specs_by_category['cabinet'];
$allowed_ssd = \HWsync\Specs_Sync_Manager::$allowed_specs_by_category['storage'];

// Partial CPU specs simulating Vendor A having 5 specs
$vendor_a_cpu_specs = array(
	'Socket'           => 'AM5',
	'Frequency'        => '4.2 GHz',
	'Turbo Clock'      => '5.0 GHz',
	'Number of Cores'  => '8',
	'Number of Threads'=> '16',
);

$missing_before = \HWsync\Specs_Sync_Manager::get_missing_specs( $vendor_a_cpu_specs, 'cpu' );
$is_complete_before = \HWsync\Specs_Sync_Manager::is_specs_complete( $vendor_a_cpu_specs, 'cpu' );

// Vendor B supplies additional missing specs
$vendor_b_cpu_specs = array(
	'Integrated Graphics' => 'AMD Radeon Graphics',
	'Codename'            => 'Raphael',
	'Generation'          => 'Ryzen 7000 Series',
	'Memory Support'      => 'DDR5',
	'Rated Speed'         => '5200 MT/s',
	'Memory Bus'          => '128-bit',
	'Memory Bandwidth'    => '83.2 GB/s',
	'TDP'                 => '65W',
	'PPT'                 => '88W',
	'ECC Memory'          => 'Yes',
	'PCI-Express'         => 'PCIe 5.0',
	'Chipsets'            => 'X670E, X670, B650E, B650, A620',
	'Cache L1'            => '512 KB',
	'Cache L2'            => '8 MB',
	'Cache L3'            => '32 MB',
	'Features'            => 'Precision Boost 2',
);

$merged_cpu_specs = array_merge( $vendor_a_cpu_specs, $vendor_b_cpu_specs );
$missing_after = \HWsync\Specs_Sync_Manager::get_missing_specs( $merged_cpu_specs, 'cpu' );
$is_complete_after = \HWsync\Specs_Sync_Manager::is_specs_complete( $merged_cpu_specs, 'cpu' );

assert_test( 'Fixed Category Specs Schema and Cascading Multi-Vendor Spec Gathering validates completeness and missing specs', (
	count( $allowed_gpu ) === 21 &&
	count( $allowed_cpu ) === 21 &&
	count( $allowed_mobo ) === 15 &&
	count( $allowed_cooler ) === 7 &&
	count( $allowed_ram ) === 12 &&
	count( $allowed_psu ) === 8 &&
	count( $allowed_case ) === 16 &&
	count( $allowed_ssd ) === 10 &&
	count( $missing_before ) === 16 &&
	$is_complete_before === false &&
	empty( $missing_after ) &&
	$is_complete_after === true
) );

// Test 45: Local-First Image Discovery & Existing Saved Image Association
$comp_img_sibling = new \HWsync\Models\Component( array(
	'brand'      => 'ASUS',
	'model_name' => 'TUF Gaming GeForce RTX 4070 Ti Super 16GB',
	'category'   => 'gpu',
	'mpn'        => 'TUF-RTX4070TIS-16G-GAMING',
	'image_url'  => 'https://example.com/wp-content/uploads/hwsync/asus-tuf-rtx-4070-ti-super.webp',
) );
$comp_img_sibling->save();

$comp_img_target = new \HWsync\Models\Component( array(
	'brand'      => 'ASUS',
	'model_name' => 'TUF Gaming GeForce RTX 4070 Ti Super 16GB',
	'category'   => 'gpu',
	'mpn'        => 'TUF-RTX4070TIS-16G-GAMING',
	'image_url'  => '',
) );
$comp_img_target->save();

$img_sync_mgr = new \HWsync\Image_Sync_Manager();
$local_match_found = $img_sync_mgr->try_associate_existing_local_image( $comp_img_target );

assert_test( 'Local-First Image Sync finds existing saved photos and creates associations first without remote download', (
	$local_match_found === true &&
	! empty( $comp_img_target->image_url ) &&
	\HWsync\Image_Sync_Manager::is_local_image_url( $comp_img_target->image_url ) &&
	$comp_img_target->image_url === 'https://example.com/wp-content/uploads/hwsync/asus-tuf-rtx-4070-ti-super.webp'
) );

// Test 46: CPU Socket Garbage Value Rejection & Accurate Model Resolution
$val_dim_rejected = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Socket', '45.0 mm x 37.5 mm', 'cpu', '' );
$val_1p_rejected  = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Socket', '1P', 'cpu', '' );
$val_12400f_res   = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Socket', '45.0 mm x 37.5 mm', 'cpu', 'Intel Core i5-12400F Desktop Processor' );
$val_7800x3d_res  = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Socket', '1P', 'cpu', 'AMD Ryzen 7 7800X3D Desktop Processor' );
$val_fclga1700    = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Socket', 'FCLGA1700', 'cpu', '' );
$val_ultra_res    = \HWsync\Specs_Sync_Manager::resolve_cpu_socket_from_title( 'Intel Core Ultra 7 265K Processor' );

assert_test( 'CPU Socket validation strictly rejects package dimensions (45.0 mm x 37.5 mm) and scalability codes (1P), resolving canonical sockets', (
	$val_dim_rejected === null &&
	$val_1p_rejected === null &&
	$val_12400f_res === 'LGA1700' &&
	$val_7800x3d_res === 'AM5' &&
	$val_fclga1700 === 'LGA1700' &&
	$val_ultra_res === 'LGA1851'
) );

// Test 47: PSU Wattage Normalization & Duplicate Unit Rejection (Single 'W' only)
$watt_1250ww    = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Wattage', '1250 WW', 'psu', '' );
$watt_450wattsw = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Wattage', '450 WattsW', 'psu', '' );
$watt_550watts  = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Wattage', '550 Watts', 'psu', '' );
$watt_750w      = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Wattage', '750W', 'psu', '' );
$watt_850pure   = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'Wattage', '850', 'psu', '' );
$watt_title_inf = \HWsync\Specs_Sync_Manager::resolve_psu_wattage_from_title( 'Corsair RM750e (2023) Fully Modular Low-Noise Power Supply' );
$watt_msi_inf   = \HWsync\Specs_Sync_Manager::resolve_psu_wattage_from_title( 'MSI MAG A850GL PCIE5 850W 80 Plus Gold Power Supply' );

assert_test( 'PSU Wattage validation strictly standardizes to single "W" (e.g. 450W, 750W, 1250W) and rejects "WW" or "WattsW"', (
	$watt_1250ww === '1250W' &&
	$watt_450wattsw === '450W' &&
	$watt_550watts === '550W' &&
	$watt_750w === '750W' &&
	$watt_850pure === '850W' &&
	$watt_title_inf === '750W' &&
	$watt_msi_inf === '850W'
) );

// Test 48: Motherboard Intel vs AMD Chipset & Platform Isolation (H410 LGA1200 vs A520 AM4)
$raw_h410 = array(
	'title'    => 'MSI H410M-A PRO Motherboard',
	'category' => 'motherboard',
	'price'    => 5100.0,
	'sku'      => 'H410M-A-PRO',
);
$raw_a520 = array(
	'title'    => 'MSI A520M-A PRO Motherboard',
	'category' => 'motherboard',
	'price'    => 5100.0,
	'sku'      => 'A520M-A-PRO',
);

$comp_h410 = \HWsync\Matching_Engine::match_or_create_component( $raw_h410 );
$comp_a520 = \HWsync\Matching_Engine::match_or_create_component( $raw_a520 );

$h410_specs = \HWsync\Specs_Sync_Manager::merge_and_clean_specs( 'motherboard', array(), array(), $comp_h410->brand . ' ' . $comp_h410->model_name );
$a520_specs = \HWsync\Specs_Sync_Manager::merge_and_clean_specs( 'motherboard', array(), array(), $comp_a520->brand . ' ' . $comp_a520->model_name );

$is_same_mb = \HWsync\Matching_Engine::is_same_hardware_component( $comp_h410, $comp_a520 );

assert_test( 'Motherboard Chipset Isolation strictly separates Intel (H410 LGA1200) from AMD (A520 AM4) without cross-matching', (
	$comp_h410->id !== $comp_a520->id &&
	$is_same_mb === false &&
	isset( $h410_specs['Platform'] ) && $h410_specs['Platform'] === 'Intel' &&
	isset( $h410_specs['Socket'] ) && $h410_specs['Socket'] === 'LGA1200' &&
	isset( $h410_specs['Chipset'] ) && $h410_specs['Chipset'] === 'H410' &&
	isset( $a520_specs['Platform'] ) && $a520_specs['Platform'] === 'AMD' &&
	isset( $a520_specs['Socket'] ) && $a520_specs['Socket'] === 'AM4' &&
	isset( $a520_specs['Chipset'] ) && $a520_specs['Chipset'] === 'A520'
) );

// Test 49: Default Hardware Category Vector Icons & UI Broken Image Protection
$comp_no_img_cpu = new \HWsync\Models\Component( array(
	'brand'      => 'Intel',
	'model_name' => 'Core i9-10980XE Extreme Edition Processor',
	'category'   => 'cpu',
	'image_url'  => '',
) );
$comp_no_img_cpu->save();

$comp_no_img_gpu = new \HWsync\Models\Component( array(
	'brand'      => 'Gigabyte',
	'model_name' => 'GeForce RTX 4060 Windforce OC 8G',
	'category'   => 'gpu',
	'image_url'  => '',
) );
$comp_no_img_gpu->save();

// Test Component::get_image_url() fallback
$fallback_cpu_url = $comp_no_img_cpu->get_image_url();
$fallback_gpu_url = $comp_no_img_gpu->get_image_url();

// Test SVG generation for all categories
$all_cats = array( 'cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet', 'other' );
$svgs_valid = true;
foreach ( $all_cats as $cat_item ) {
	$svg_content = \HWsync\Image_Sync_Manager::get_default_category_svg( $cat_item );
	$data_uri = \HWsync\Image_Sync_Manager::get_default_category_data_uri( $cat_item );
	if ( empty( $svg_content ) || strpos( $svg_content, '<svg' ) === false || strpos( $data_uri, 'data:image/svg+xml' ) !== 0 ) {
		$svgs_valid = false;
	}
}

// Test image sync fallback assignment
$sync_mgr = new \HWsync\Image_Sync_Manager();
$sync_report = $sync_mgr->run_images_sync( array( 'category' => 'cpu', 'component_id' => $comp_no_img_cpu->id ) );
$reloaded_cpu = \HWsync\Models\Component::find_by_id( $comp_no_img_cpu->id );

assert_test( 'Default Category Vector Icons provide clean SVG icons for every category and protect UI from broken images', (
	! empty( $fallback_cpu_url ) &&
	! empty( $fallback_gpu_url ) &&
	$svgs_valid === true &&
	$sync_report['images_saved'] === 1 &&
	! empty( $reloaded_cpu->image_url ) &&
	( strpos( $reloaded_cpu->image_url, 'defaults/cpu.svg' ) !== false || strpos( $reloaded_cpu->image_url, 'data:image/svg+xml' ) === 0 )
) );

// Test 50: Multi-Vendor CPU Matching and Ryzen 5 7500F Deduplication & Merge
$raw_cpu_1 = array(
	'title'    => 'AMD RYZEN 5 7500F 6 CORES UPTO 3.7 GHZ UPTO 5 GHZ AM5',
	'category' => 'cpu',
	'price'    => 13899.0,
	'sku'      => 'MD-7500F',
);
$raw_cpu_2 = array(
	'title'    => 'AMD Ryzen 5 7500F (6 Cores, 12 Threads, Max. Boost Clock Up To 5GHz, AM5 Socket and 38MB Cache)',
	'category' => 'cpu',
	'price'    => 13500.0,
	'sku'      => 'VED-7500F',
);
$raw_cpu_3 = array(
	'title'    => 'AMD Ryzen 5 7500F 3.7GHz',
	'category' => 'cpu',
	'price'    => 13999.0,
	'sku'      => 'PCS-7500F',
);
$raw_cpu_4 = array(
	'title'    => 'AMD Ryzen 5 7500F 7th Generation ( 5 GHz / 6 Cores / 12 Threads )',
	'category' => 'cpu',
	'price'    => 13650.0,
	'sku'      => 'EH-7500F',
);

$match_cpu_1 = \HWsync\Matching_Engine::match_or_create_component( $raw_cpu_1 );
$match_cpu_2 = \HWsync\Matching_Engine::match_or_create_component( $raw_cpu_2 );
$match_cpu_3 = \HWsync\Matching_Engine::match_or_create_component( $raw_cpu_3 );
$match_cpu_4 = \HWsync\Matching_Engine::match_or_create_component( $raw_cpu_4 );

$is_same_1_2 = \HWsync\Matching_Engine::is_same_hardware_component( $match_cpu_1, $match_cpu_2 );
$is_same_1_3 = \HWsync\Matching_Engine::is_same_hardware_component( $match_cpu_1, $match_cpu_3 );
$is_same_1_4 = \HWsync\Matching_Engine::is_same_hardware_component( $match_cpu_1, $match_cpu_4 );

assert_test( 'Multi-Vendor CPU Matching consolidates all retailer variations of AMD Ryzen 5 7500F into a single canonical component', (
	$match_cpu_1->id === $match_cpu_2->id &&
	$match_cpu_1->id === $match_cpu_3->id &&
	$match_cpu_1->id === $match_cpu_4->id &&
	$is_same_1_2 === true &&
	$is_same_1_3 === true &&
	$is_same_1_4 === true
) );

// Test 51: Intel CPU Multi-Vendor Matching, Hyphen/Space Normalization, and TDP Sanitization
$raw_intel_1 = array(
	'title'    => 'CORE I3-12100F 12TH GEN 4 CORE UPTO 4.3 GHZ',
	'category' => 'cpu',
	'price'    => 10550.0,
	'sku'      => 'MD-12100F',
);
$raw_intel_2 = array(
	'title'    => 'Core i3 12100F 12th Generation ( 4.3 GHz / 4 Cores )',
	'category' => 'cpu',
	'price'    => 10645.0,
	'sku'      => 'EH-12100F',
);
$raw_intel_3 = array(
	'title'    => 'Intel Core i3-12100F Desktop Processor (LGA1700 / 58W TDP)',
	'category' => 'cpu',
	'price'    => 10499.0,
	'sku'      => 'VED-12100F',
);

$match_intel_1 = \HWsync\Matching_Engine::match_or_create_component( $raw_intel_1 );
$match_intel_2 = \HWsync\Matching_Engine::match_or_create_component( $raw_intel_2 );
$match_intel_3 = \HWsync\Matching_Engine::match_or_create_component( $raw_intel_3 );

$is_same_intel_1_2 = \HWsync\Matching_Engine::is_same_hardware_component( $match_intel_1, $match_intel_2 );
$is_same_intel_1_3 = \HWsync\Matching_Engine::is_same_hardware_component( $match_intel_1, $match_intel_3 );

$tdp_sanitized_58ww = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'TDP', '58 WW', 'cpu', '' );
$tdp_sanitized_65w  = \HWsync\Specs_Sync_Manager::sanitize_and_validate_spec_value( 'TDP', '65 Watts', 'cpu', '' );

assert_test( 'Intel CPU Multi-Vendor Matching consolidates CORE I3-12100F and Core i3 12100F variations, sanitizing TDP to clean single W', (
	$match_intel_1->id === $match_intel_2->id &&
	$match_intel_1->id === $match_intel_3->id &&
	$is_same_intel_1_2 === true &&
	$is_same_intel_1_3 === true &&
	$tdp_sanitized_58ww === '58W' &&
	$tdp_sanitized_65w === '65W'
) );

// Test 52: Basic CPU Name Simplification & Clutter Stripping
$clean_amd_3400g   = \HWsync\Matching_Engine::normalize_model_name( 'AMD Ryzen 5 3400G with Radeon RX Vega 11 Graphics', 'AMD', 'cpu' );
$clean_ultra_270k  = \HWsync\Matching_Engine::normalize_model_name( 'Intel Core Ultra 7 270K Plus 24 Cores up to 5.50 GHz FCLGA1851', 'Intel', 'cpu' );
$clean_intel_14900 = \HWsync\Matching_Engine::normalize_model_name( 'Intel Core i9-14900K 24 Cores up to 6.0 GHz LGA1700 Processor with Intel UHD Graphics 770', 'Intel', 'cpu' );
$clean_amd_7800x3d = \HWsync\Matching_Engine::normalize_model_name( 'AMD Ryzen 7 7800X3D 8-Core 16-Thread Desktop Processor with 3D V-Cache', 'AMD', 'cpu' );

assert_test( 'Basic CPU Model Name Normalization retains clean hardware names without clutter', (
	$clean_amd_3400g === 'Ryzen 5 3400G' &&
	$clean_ultra_270k === 'Core Ultra 7 270K Plus' &&
	$clean_intel_14900 === 'Core i9-14900K' &&
	$clean_amd_7800x3d === 'Ryzen 7 7800X3D'
) );

// Test 53: Dedicated Case Fan Category Isolation, Specs Schema & Vector Icon Resolution
$cat_fan1    = \HWsync\Matching_Engine::detect_category( 'Lian Li UNI FAN SL-INFINITY 120 RGB Triple Pack with Controller Black' );
$cat_fan2    = \HWsync\Matching_Engine::detect_category( 'Corsair iCUE AF120 RGB ELITE 120mm PWM Single Fan Black' );
$cat_cooler1 = \HWsync\Matching_Engine::detect_category( 'DeepCool AK620 High-Performance Dual-Tower CPU Air Cooler' );
$cat_cooler2 = \HWsync\Matching_Engine::detect_category( 'NZXT Kraken 360 RGB All-in-One Liquid CPU Cooler' );

$fan_specs = \HWsync\Specs_Sync_Manager::merge_and_clean_specs( 'case_fan', array(
	'fan dimensions' => '120mm',
	'speed'          => '2100 RPM',
	'air flow'       => '61.3 CFM',
	'rgb'            => 'ARGB',
	'quantity'       => 'Triple Pack',
	'pwm'            => 'PWM',
), array(), 'Lian Li UNI FAN SL-INFINITY 120 ARGB Triple Pack' );

$fan_svg = \HWsync\Image_Sync_Manager::get_default_category_svg( 'case_fan' );

assert_test( 'Dedicated Case Fan Category isolates chassis fans from coolers, validates specs schema, and resolves vector icon', (
	$cat_fan1 === 'case_fan' &&
	$cat_fan2 === 'case_fan' &&
	$cat_cooler1 === 'cooler' &&
	$cat_cooler2 === 'cooler' &&
	isset( $fan_specs['Fan Size'] ) && $fan_specs['Fan Size'] === '120 mm' &&
	isset( $fan_specs['Lighting'] ) && $fan_specs['Lighting'] === 'ARGB' &&
	isset( $fan_specs['Package Quantity'] ) && $fan_specs['Package Quantity'] === 'Triple Pack' &&
	isset( $fan_specs['PWM Support'] ) && $fan_specs['PWM Support'] === 'Yes' &&
	strpos( $fan_svg, '<svg' ) !== false
) );

echo "\n---------------------------------------------\n";
echo "Tests Passed: {$passed} | Failed: {$failed}\n";
echo "---------------------------------------------\n";

if ( $failed > 0 ) {
	exit(1);
}
