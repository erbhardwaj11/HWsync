<?php
namespace HWsync;

use HWsync\Models\Component;
use HWsync\Models\Vendor_Price;
use HWsync\Models\Vendor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Specs_Sync_Manager {

	/**
	 * Canonical dictionary map for normalizing inconsistent hardware specification labels.
	 */
	protected static $key_normalization_map = array(
		'cpusockettype'                        => 'CPU Socket Type',
		'cpu socket type'                      => 'CPU Socket Type',
		'socket'                               => 'CPU Socket Type',
		'sockets supported'                    => 'CPU Socket Type',
		'socket type'                          => 'CPU Socket Type',

		'total cores'                          => 'Total Cores',
		'cores'                                => 'Total Cores',
		'cpu cores'                            => 'Total Cores',
		'# of performance-cores'               => '# of Performance-cores',
		'performance-cores'                    => '# of Performance-cores',
		'performance cores'                    => '# of Performance-cores',
		'# of efficient-cores'                 => '# of Efficient-cores',
		'efficient-cores'                      => '# of Efficient-cores',
		'efficient cores'                      => '# of Efficient-cores',

		'total threads'                        => 'Total Threads',
		'threads'                              => 'Total Threads',
		'thread count'                         => 'Total Threads',

		'processor base frequency'             => 'Processor Base Frequency',
		'base frequency'                       => 'Processor Base Frequency',
		'base clock'                           => 'Processor Base Frequency',
		'performance-core base frequency'      => 'Performance-core Base Frequency',

		'processor max frequency'              => 'Max Turbo Frequency',
		'max turbo frequency'                  => 'Max Turbo Frequency',
		'boost clock'                          => 'Max Turbo Frequency',
		'max boost clock speed'                => 'Max Turbo Frequency',
		'turbo frequency'                      => 'Max Turbo Frequency',
		'performance-core max turbo frequency' => 'Performance-core Max Turbo Frequency',

		'cache'                                => 'Cache',
		'l3 cache'                             => 'L3 Cache',
		'total l2 cache'                       => 'Total L2 Cache',
		'l2 cache'                             => 'L2 Cache',
		'l1 cache'                             => 'L1 Cache',
		'intel smart cache'                    => 'Cache',

		'processor base power'                 => 'Processor Base Power',
		'tdp'                                  => 'TDP (Base Power)',
		'maximum turbo power'                  => 'Maximum Turbo Power',
		'power consumption'                    => 'Power Consumption',

		'memory types'                         => 'Memory Types',
		'memory support'                       => 'Memory Types',
		'max memory size'                      => 'Max Memory Size',
		'max memory size (dependent on memory type)' => 'Max Memory Size',
		'max # of memory channels'             => 'Max # of Memory Channels',
		'max memory bandwidth'                 => 'Max Memory Bandwidth',
		'ecc memory supported'                 => 'ECC Memory Supported',

		'integrated graphics'                  => 'Integrated Graphics',
		'graphics'                             => 'Integrated Graphics',
		'processor graphics'                   => 'Integrated Graphics',

		'gpu chipset'                          => 'GPU Chipset',
		'graphics processor'                   => 'GPU Chipset',
		'vram'                                 => 'VRAM Size',
		'vram size'                            => 'VRAM Size',
		'memory bus'                           => 'Memory Bus',
		'memory interface'                     => 'Memory Interface',
		'recommended psu'                      => 'Recommended PSU',

		'form factor'                          => 'Form Factor',
		'chipset'                              => 'Chipset',
		'warranty'                             => 'Warranty',
		'manufacturer warranty period'         => 'Warranty',
		'lithography'                          => 'Lithography',
		'product collection'                   => 'Product Collection',
		'code name'                            => 'Code Name',
		'vertical segment'                     => 'Vertical Segment',
		'use conditions'                       => 'Use Conditions',
		'package size'                         => 'Package Size',
		'tjunction'                            => 'TJUNCTION (Max Temp)',
		'direct media interface (dmi) revision'=> 'DMI Revision',
		'max # of dmi lanes'                   => 'Max DMI Lanes',
		'pci express revision'                 => 'PCI Express Revision',
		'pci express configurations'           => 'PCI Express Configurations',
		'max # of pci express lanes'           => 'Max PCI Express Lanes',
	);

	/**
	 * Validate whether a key-value pair is a genuine hardware specification,
	 * rejecting footer disclaimers, shipping text, wishlist, notes, and paragraphs.
	 *
	 * @param string $key
	 * @param string $val
	 * @return bool
	 */
	public static function is_valid_spec_pair( $key, $val ) {
		if ( empty( $key ) || empty( $val ) || ! is_scalar( $key ) || ! is_scalar( $val ) ) {
			return false;
		}

		$k = trim( (string) $key );
		$v = trim( (string) $val );

		$k = rtrim( $k, ':' );
		$k = trim( $k, '*' );

		if ( strlen( $k ) < 2 || strlen( $k ) > 60 ) {
			return false;
		}
		if ( strlen( $v ) < 1 || strlen( $v ) > 220 ) {
			return false;
		}

		$k_lower = strtolower( $k );
		$v_lower = strtolower( $v );

		// Blacklisted keys (disclaimers, shipping, UI buttons, store policies)
		$blacklisted_keys = array(
			'note', 'notes', 'notice', 'disclaimer', 'terms', 'condition', 'conditions',
			'shipping', 'delivery', 'courier', 'dispatch', 'estimated',
			'wishlist', 'compare', 'review', 'reviews', 'rating', 'ratings', 'cart', 'buy now', 'add to',
			'description', 'overview', 'features', 'key features', 'highlights', 'quick overview',
			'tags', 'tax', 'gst', 'emi', 'cod', 'payment', 'in stock', 'out of stock',
			'return', 'policy', 'cancellation', 'refund', 'replacement',
			'standard shipping', 'fast delivery',
		);

		foreach ( $blacklisted_keys as $b ) {
			if ( $k_lower === $b || strpos( $k_lower, $b ) !== false ) {
				return false;
			}
		}

		// Blacklisted symbol-only or boilerplate values
		$symbol_values = array( '(', ')', '-', '*', '**', '***', ':', ';', 'n/a', 'na', 'null', 'none', 'undefined', 'no' );
		if ( in_array( $v_lower, $symbol_values, true ) ) {
			return false;
		}

		// Blacklisted phrases inside values
		$blacklisted_phrases = array(
			'subject to change without notice',
			'delivery typically takes',
			'business days',
			'prices, specifications',
			'all rights reserved',
			'add to cart',
			'add to wishlist',
		);

		foreach ( $blacklisted_phrases as $phrase ) {
			if ( strpos( $v_lower, $phrase ) !== false ) {
				return false;
			}
		}

		// Reject long descriptive paragraphs
		if ( substr_count( $v, '.' ) > 3 && strlen( $v ) > 100 ) {
			return false;
		}

		// Reject identical key-value
		if ( strcasecmp( $k, $v ) === 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize a raw specification label into standardized Title Case.
	 *
	 * @param string $key
	 * @return string
	 */
	public static function normalize_spec_key( $key ) {
		$k = trim( (string) $key );
		$k = rtrim( $k, ':' );
		$k = trim( $k, '*' );
		$clean = preg_replace( '/[\s_\-]+/', ' ', strtolower( $k ) );

		if ( isset( self::$key_normalization_map[ $clean ] ) ) {
			return self::$key_normalization_map[ $clean ];
		}

		// Clean fallback to proper title case
		return ucwords( preg_replace( '/\s+/', ' ', $k ) );
	}

	/**
	 * Run manual specs synchronization for existing canonical components in DB.
	 * Visits product pages on vendor websites, extracts the specifications section,
	 * updates database records, and updates WordPress component posts.
	 *
	 * @param array $options Options array: 'category', 'component_id', 'limit', 'offset'.
	 * @param callable|null $logger Progress callback logger.
	 * @return array Sync report.
	 */
	public function run_specs_sync( $options = array(), $logger = null ) {
		global $wpdb;
		$comp_table = Database::get_table_name( 'components' );

		$category     = isset( $options['category'] ) ? sanitize_text_field( $options['category'] ) : 'all';
		$component_id = isset( $options['component_id'] ) ? intval( $options['component_id'] ) : 0;
		$limit        = isset( $options['limit'] ) ? intval( $options['limit'] ) : 100;
		$offset       = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;

		$this->emit( $logger, 'info', "Starting Technical Specifications Sync engine..." );

		$where_clauses = array( "1=1" );
		if ( $component_id > 0 ) {
			$where_clauses[] = $wpdb->prepare( "id = %d", $component_id );
		} elseif ( $category !== 'all' && ! empty( $category ) ) {
			$where_clauses[] = $wpdb->prepare( "category = %s", $category );
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$components_raw = $wpdb->get_results( "SELECT * FROM {$comp_table} WHERE {$where_sql} ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}", \ARRAY_A );

		if ( empty( $components_raw ) ) {
			$this->emit( $logger, 'warning', "No components found in database matching criteria." );
			return array( 'total' => 0, 'updated' => 0 );
		}

		$report = array(
			'total_components' => count( $components_raw ),
			'specs_updated'    => 0,
			'posts_refreshed'  => 0,
			'errors'           => array(),
		);

		$this->emit( $logger, 'info', "Found " . count( $components_raw ) . " canonical components in DB. Visiting retailer product pages..." );

		foreach ( $components_raw as $c_row ) {
			$component = new Component( $c_row );
			$this->emit( $logger, 'info', "Syncing specs for Component #{$component->id}: [{$component->brand} {$component->model_name}] ({$component->category})..." );

			$prices = $component->get_prices();
			if ( empty( $prices ) ) {
				$this->emit( $logger, 'debug', "Component #{$component->id} has no linked vendor price listings. Skipping." );
				continue;
			}

			$clean_specs = array();
			$collected_text = $component->brand . ' ' . $component->model_name . ' ' . ( $component->mpn ?: '' ) . ' ' . ( $component->sku ?: '' );

			foreach ( $prices as $p ) {
				if ( empty( $p->product_url ) ) {
					continue;
				}

				$vendor_slug = ! empty( $p->vendor_slug ) ? $p->vendor_slug : '';
				$this->emit( $logger, 'debug', "Visiting product page on " . ucfirst( $vendor_slug ) . ": {$p->product_url}..." );

				$page_specs = $this->fetch_specs_from_product_url( $p->product_url, $vendor_slug, $component->category );

				if ( ! empty( $page_specs ) ) {
					$clean_specs = array_merge( $clean_specs, $page_specs );
					$this->emit( $logger, 'match', "Extracted " . count( $page_specs ) . " clean specification attributes from " . ucfirst( $vendor_slug ) . " product page." );

					foreach ( $page_specs as $sk => $sv ) {
						$collected_text .= " {$sk}: {$sv}";
					}
					break; // Pick clean specifications from primary vendor
				}
			}

			// Fill in any missing category standard attributes via deep domain regex
			$final_specs = self::merge_and_clean_specs( $component->category, $clean_specs, $component->specs_json ?: array(), $collected_text );

			if ( ! empty( $final_specs ) ) {
				$component->specs_json = $final_specs;
				$component->save();
				$report['specs_updated']++;

				// Update postmeta if linked
				if ( ! empty( $component->wp_post_id ) && function_exists( 'update_post_meta' ) ) {
					update_post_meta( $component->wp_post_id, '_pcspecs_specs', $final_specs );
					update_post_meta( $component->wp_post_id, '_hwsync_specs', $final_specs );
					$report['posts_refreshed']++;
				}

				$spec_summary = self::format_specs_summary( $final_specs );
				$this->emit( $logger, 'success', "Specs Saved for #{$component->id} [{$component->model_name}]: {$spec_summary}" );
			} else {
				$this->emit( $logger, 'debug', "No technical specifications discovered for #{$component->id}." );
			}
		}

		$this->emit( $logger, 'finish', "Specifications Sync Completed! Updated {$report['specs_updated']} components in database." );

		return $report;
	}

	/**
	 * Synchronize specifications for a small chunk of components via AJAX.
	 *
	 * @param array $options Options array: 'category', 'offset', 'limit'.
	 * @return array Chunk sync report including logs.
	 */
	public function sync_specs_chunk( $options = array() ) {
		global $wpdb;
		$comp_table   = Database::get_table_name( 'components' );
		$category     = isset( $options['category'] ) ? sanitize_text_field( $options['category'] ) : 'all';
		$offset       = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;
		$limit        = isset( $options['limit'] ) ? intval( $options['limit'] ) : 5;

		$where_clauses = array( "1=1" );
		if ( $category !== 'all' && ! empty( $category ) ) {
			$where_clauses[] = $wpdb->prepare( "category = %s", $category );
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$total_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$comp_table} WHERE {$where_sql}" ) );
		$components_raw = $wpdb->get_results( "SELECT * FROM {$comp_table} WHERE {$where_sql} ORDER BY id ASC LIMIT {$offset}, {$limit}", \ARRAY_A );

		$logs = array();
		$updated = 0;

		if ( empty( $components_raw ) ) {
			return array(
				'success'          => true,
				'has_more'         => false,
				'processed'        => 0,
				'total_components' => $total_count,
				'next_offset'      => $offset,
				'logs'             => array( array( 'level' => 'info', 'message' => "All components in DB have been analyzed." ) ),
			);
		}

		foreach ( $components_raw as $c_row ) {
			try {
				$component = new Component( $c_row );
				$prices = $component->get_prices();

				if ( empty( $prices ) ) {
					$logs[] = array( 'level' => 'debug', 'message' => "Component #{$component->id} has no linked price listings. Skipping." );
					continue;
				}

				$clean_specs = array();
				$collected_text = $component->brand . ' ' . $component->model_name . ' ' . ( $component->mpn ?: '' ) . ' ' . ( $component->sku ?: '' );

				foreach ( $prices as $p ) {
					if ( empty( $p->product_url ) ) continue;
					$vendor_slug = ! empty( $p->vendor_slug ) ? $p->vendor_slug : '';
					$page_specs = $this->fetch_specs_from_product_url( $p->product_url, $vendor_slug, $component->category );

					if ( ! empty( $page_specs ) ) {
						$clean_specs = array_merge( $clean_specs, $page_specs );
						$logs[] = array( 'level' => 'match', 'message' => "[{$component->model_name}] Extracted " . count( $page_specs ) . " clean specs from " . ucfirst( $vendor_slug ) . " product page." );
						foreach ( $page_specs as $sk => $sv ) {
							$collected_text .= " {$sk}: {$sv}";
						}
						break;
					}
				}

				$final_specs = self::merge_and_clean_specs( $component->category, $clean_specs, $component->specs_json ?: array(), $collected_text );

				if ( ! empty( $final_specs ) ) {
					$component->specs_json = $final_specs;
					$component->save();
					$updated++;

					// Update postmeta if linked
					if ( ! empty( $component->wp_post_id ) && function_exists( 'update_post_meta' ) ) {
						update_post_meta( $component->wp_post_id, '_pcspecs_specs', $final_specs );
						update_post_meta( $component->wp_post_id, '_hwsync_specs', $final_specs );
					}

					$summary = self::format_specs_summary( $final_specs );
					$logs[] = array( 'level' => 'success', 'message' => "Specs Saved for #{$component->id} [{$component->model_name}]: {$summary}" );
				}
			} catch ( \Throwable $e ) {
				$logs[] = array( 'level' => 'warning', 'message' => "Error syncing specs for component #{$c_row['id']}: " . $e->getMessage() );
			}
		}

		$next_offset = $offset + count( $components_raw );
		$has_more = ( $next_offset < $total_count );

		return array(
			'success'          => true,
			'has_more'         => $has_more,
			'processed'        => count( $components_raw ),
			'updated'          => $updated,
			'total_components' => $total_count,
			'next_offset'      => $next_offset,
			'logs'             => $logs,
		);
	}

	/**
	 * Fetch and extract specifications section from a vendor's product page URL.
	 *
	 * @param string $url
	 * @param string $vendor_slug
	 * @param string $category
	 * @return array Key-value dictionary of clean specs.
	 */
	public function fetch_specs_from_product_url( $url, $vendor_slug, $category = '' ) {
		$specs = array();
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $specs;
		}

		// Handle Shopify stores (EliteHubs) via product JSON
		if ( $vendor_slug === 'elitehubs' || strpos( $url, 'elitehubs.com' ) !== false ) {
			$path = parse_url( $url, PHP_URL_PATH );
			if ( preg_match( '#/products/([^/?]+)#', $path, $m ) ) {
				$json_url = 'https://elitehubs.com/products/' . $m[1] . '.json';
				$json_res = $this->make_http_request( $json_url, array( 'Accept' => 'application/json' ) );
				if ( ! empty( $json_res['body'] ) ) {
					$data = json_decode( $json_res['body'], true );
					if ( isset( $data['product']['body_html'] ) ) {
						$specs = self::parse_html_specs_section( $data['product']['body_html'] );
						if ( ! empty( $specs ) ) {
							return $specs;
						}
					}
				}
			}
		}

		// Standard cURL fetch for WooCommerce, OpenCart, Journal 3, Magento pages
		$res = $this->make_http_request( $url );
		if ( empty( $res['body'] ) ) {
			return $specs;
		}

		$html = $res['body'];
		$specs_html = '';

		// Targeted Pattern 1: Dedicated specification tab container
		if ( preg_match( '/<(?:div|section|table)[^>]*(?:id=["\']tab-specification["\']|id=["\']tab-specs["\']|class=["\'][^"\']*(?:woocommerce-Tabs-panel--specification|shop_attributes|product-attribute-specs-table|specification)[^"\']*)[^>]*>[\s\S]*?<\/(?:div|section|table)>/i', $html, $sm ) ) {
			$specs_html = $sm[0];
		}
		// Targeted Pattern 2: Attributes table within product container
		elseif ( preg_match( '/<table[^>]*(?:class=["\'][^"\']*(?:shop_attributes|table-bordered|table-striped|data-table|table_specifications)[^"\']*|id=["\']product-attribute-specs-table["\'])[^>]*>[\s\S]*?<\/table>/i', $html, $sm ) ) {
			$specs_html = $sm[0];
		}
		// Targeted Pattern 3: Description tab containing definition lists
		elseif ( preg_match( '/<div[^>]*id=["\']tab-description["\'][^>]*>[\s\S]*?<\/div>/i', $html, $sm ) ) {
			$specs_html = $sm[0];
		} else {
			$specs_html = $html;
		}

		return self::parse_html_specs_section( $specs_html );
	}

	/**
	 * Parse HTML snippet (tables, lists, definition lists, structured text) into clean key-value specs dictionary.
	 *
	 * @param string $html_snippet
	 * @return array
	 */
	public static function parse_html_specs_section( $html_snippet ) {
		$specs = array();
		if ( empty( $html_snippet ) ) {
			return $specs;
		}

		// 1. Table rows: <tr><td>Key</td><td>Val</td></tr> or <tr><th>Key</th><td>Val</td></tr>
		if ( preg_match_all( '/<tr[^>]*>[\s\S]*?<\/tr>/i', $html_snippet, $rows ) ) {
			foreach ( $rows[0] as $r ) {
				if ( preg_match_all( '/<(?:th|td)[^>]*>([\s\S]*?)<\/(?:th|td)>/i', $r, $cells ) ) {
					if ( count( $cells[1] ) >= 2 ) {
						$k = trim( strip_tags( $cells[1][0] ) );
						$v = trim( strip_tags( $cells[1][1] ) );
						$k = html_entity_decode( $k, ENT_QUOTES, 'UTF-8' );
						$v = html_entity_decode( $v, ENT_QUOTES, 'UTF-8' );
						$k = trim( preg_replace( '/\s+/', ' ', $k ) );
						$v = trim( preg_replace( '/\s+/', ' ', $v ) );

						if ( self::is_valid_spec_pair( $k, $v ) ) {
							$norm_k = self::normalize_spec_key( $k );
							$specs[ $norm_k ] = $v;
						}
					}
				}
			}
		}

		// 2. Definition Lists: <dt>Key</dt><dd>Val</dd>
		if ( preg_match_all( '/<dt[^>]*>([\s\S]*?)<\/dt>\s*<dd[^>]*>([\s\S]*?)<\/dd>/i', $html_snippet, $dls, PREG_SET_ORDER ) ) {
			foreach ( $dls as $dl ) {
				$k = trim( strip_tags( $dl[1] ) );
				$v = trim( strip_tags( $dl[2] ) );
				$k = html_entity_decode( $k, ENT_QUOTES, 'UTF-8' );
				$v = html_entity_decode( $v, ENT_QUOTES, 'UTF-8' );
				$k = trim( preg_replace( '/\s+/', ' ', $k ) );
				$v = trim( preg_replace( '/\s+/', ' ', $v ) );

				if ( self::is_valid_spec_pair( $k, $v ) ) {
					$norm_k = self::normalize_spec_key( $k );
					if ( ! isset( $specs[ $norm_k ] ) ) {
						$specs[ $norm_k ] = $v;
					}
				}
			}
		}

		// 3. List items: <li><strong>Key:</strong> Val</li>
		if ( preg_match_all( '/<li[^>]*>[\s\S]*?<\/li>/i', $html_snippet, $lis ) ) {
			foreach ( $lis[0] as $li ) {
				if ( preg_match( '/<(?:strong|b|span)[^>]*>([^<:]+)[:]?<\/(?:strong|b|span)>[\s:]*([^<]+)/i', $li, $m ) ) {
					$k = html_entity_decode( trim( strip_tags( $m[1] ) ), ENT_QUOTES, 'UTF-8' );
					$v = html_entity_decode( trim( strip_tags( $m[2] ) ), ENT_QUOTES, 'UTF-8' );
					$k = trim( preg_replace( '/\s+/', ' ', $k ) );
					$v = trim( preg_replace( '/\s+/', ' ', $v ) );

					if ( self::is_valid_spec_pair( $k, $v ) ) {
						$norm_k = self::normalize_spec_key( $k );
						if ( ! isset( $specs[ $norm_k ] ) ) {
							$specs[ $norm_k ] = $v;
						}
					}
				}
			}
		}

		// 4. Clean text key-value lines: "Key : Value"
		if ( empty( $specs ) ) {
			$clean_text = strip_tags( $html_snippet );
			$lines = explode( "\n", $clean_text );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( strpos( $line, ':' ) !== false ) {
					$parts = explode( ':', $line, 2 );
					$k = trim( $parts[0] );
					$v = trim( $parts[1] );
					if ( self::is_valid_spec_pair( $k, $v ) ) {
						$norm_k = self::normalize_spec_key( $k );
						if ( ! isset( $specs[ $norm_k ] ) ) {
							$specs[ $norm_k ] = $v;
						}
					}
				}
			}
		}

		return $specs;
	}

	/**
	 * Merge vendor extracted specs with domain regex extractions and return a clean, deduplicated dictionary.
	 *
	 * @param string $category
	 * @param array $raw_specs
	 * @param array $existing_specs
	 * @param string $text_context
	 * @return array
	 */
	public static function merge_and_clean_specs( $category, $raw_specs = array(), $existing_specs = array(), $text_context = '' ) {
		$merged = array();

		// 1. Sanitize and normalize existing specs
		if ( is_array( $existing_specs ) ) {
			foreach ( $existing_specs as $k => $v ) {
				if ( $k === 'raw_specs_table' || ! is_scalar( $v ) ) {
					continue;
				}
				if ( self::is_valid_spec_pair( $k, $v ) ) {
					$norm_k = self::normalize_spec_key( $k );
					$merged[ $norm_k ] = (string) $v;
				}
			}
		}

		// 2. Add newly extracted vendor specs
		if ( is_array( $raw_specs ) ) {
			foreach ( $raw_specs as $k => $v ) {
				if ( self::is_valid_spec_pair( $k, $v ) ) {
					$norm_k = self::normalize_spec_key( $k );
					$merged[ $norm_k ] = (string) $v;
				}
			}
		}

		// 3. Fill in missing category-specific core hardware attributes
		$cat = strtolower( $category );
		$text = $text_context;

		switch ( $cat ) {
			case 'cpu':
				if ( empty( $merged['CPU Socket Type'] ) && preg_match( '/\b(AM5|AM4|LGA1700|LGA1851|LGA1200|LGA1151|sTR5|SP5)\b/i', $text, $m ) ) {
					$merged['CPU Socket Type'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Total Cores'] ) && preg_match( '/\b(\d+)\s*(?:-|\s*)?(?:core|cores)\b/i', $text, $m ) ) {
					$merged['Total Cores'] = $m[1];
				}
				if ( empty( $merged['Total Threads'] ) && preg_match( '/\b(\d+)\s*(?:-|\s*)?(?:thread|threads)\b/i', $text, $m ) ) {
					$merged['Total Threads'] = $m[1];
				}
				if ( empty( $merged['Max Turbo Frequency'] ) && preg_match( '/(?:up\s*to|boost|max\s*clock|turbo)?\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$merged['Max Turbo Frequency'] = $m[1] . ' GHz';
				}
				if ( empty( $merged['Processor Base Frequency'] ) && preg_match( '/(?:base|base\s*clock)\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$merged['Processor Base Frequency'] = $m[1] . ' GHz';
				}
				if ( empty( $merged['Cache'] ) && preg_match( '/(\d+)\s*(?:MB|Mb)\s*(?:L3|Cache|3D\s*V-Cache|Smart\s*Cache)/i', $text, $m ) ) {
					$merged['Cache'] = $m[1] . ' MB';
				}
				if ( empty( $merged['TDP (Base Power)'] ) && empty( $merged['Processor Base Power'] ) && preg_match( '/(\d+)\s*W(?:att)?\b/i', $text, $m ) ) {
					$merged['Processor Base Power'] = $m[1] . ' W';
				}
				if ( empty( $merged['Memory Types'] ) && preg_match( '/\b(DDR5(?:\s*\+\s*DDR4)?|DDR4|DDR5)\b/i', $text, $m ) ) {
					$merged['Memory Types'] = strtoupper( $m[1] );
				}
				break;

			case 'gpu':
				if ( empty( $merged['VRAM Size'] ) && preg_match( '/(\d+)\s*(?:GB|G)\s*(GDDR6X|GDDR6|GDDR5X|GDDR5|HBM2e|HBM3)/i', $text, $m ) ) {
					$merged['VRAM Size'] = $m[1] . ' GB ' . strtoupper( $m[2] );
				} elseif ( empty( $merged['VRAM Size'] ) && preg_match( '/(\d+)\s*(?:GB|G)\b/i', $text, $m ) ) {
					$merged['VRAM Size'] = $m[1] . ' GB';
				}
				if ( empty( $merged['GPU Chipset'] ) && preg_match( '/\b(RTX\s*4090|RTX\s*4080\s*Super|RTX\s*4080|RTX\s*4070\s*Ti\s*Super|RTX\s*4070\s*Ti|RTX\s*4070\s*Super|RTX\s*4070|RTX\s*4060\s*Ti|RTX\s*4060|RTX\s*3060|RX\s*7900\s*XTX|RX\s*7900\s*XT|RX\s*7800\s*XT|RX\s*7700\s*XT|RX\s*7600\s*XT|RX\s*7600|Arc\s*A770|Arc\s*A750)\b/i', $text, $m ) ) {
					$merged['GPU Chipset'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Memory Bus'] ) && preg_match( '/(\d+)\s*(?:-|\s*)?bit\b/i', $text, $m ) ) {
					$merged['Memory Bus'] = $m[1] . '-bit';
				}
				if ( empty( $merged['Recommended PSU'] ) && preg_match( '/(?:PSU|Power Supply|Recommended PSU|Min PSU)[^\d]*(\d{3,4})\s*W/i', $text, $m ) ) {
					$merged['Recommended PSU'] = $m[1] . ' W';
				}
				break;

			case 'motherboard':
				if ( empty( $merged['CPU Socket Type'] ) && preg_match( '/\b(AM5|AM4|LGA1700|LGA1851|LGA1200|LGA1151)\b/i', $text, $m ) ) {
					$merged['CPU Socket Type'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Chipset'] ) && preg_match( '/\b(X870E|X870|X670E|X670|B850|B650E|B650|A620|Z890|Z790|Z690|B760|B660|H610)\b/i', $text, $m ) ) {
					$merged['Chipset'] = strtoupper( $m[1] );
				}
				if ( empty( $merged['Form Factor'] ) && preg_match( '/\b(E-ATX|Extended ATX|ATX|Micro-ATX|Micro ATX|mATX|Mini-ITX|Mini ITX|ITX)\b/i', $text, $m ) ) {
					$merged['Form Factor'] = strtoupper( str_replace( ' ', '-', $m[1] ) );
				}
				if ( empty( $merged['Memory Types'] ) && preg_match( '/\b(DDR5|DDR4)\b/i', $text, $m ) ) {
					$merged['Memory Types'] = strtoupper( $m[1] );
				}
				break;
		}

		return $merged;
	}

	/**
	 * Backwards-compatible domain regex extraction helper for structured specs.
	 *
	 * @param string $category
	 * @param string $text
	 * @param array $existing_specs
	 * @return array
	 */
	public static function extract_detailed_specs( $category, $text, $existing_specs = array() ) {
		$specs = is_array( $existing_specs ) ? $existing_specs : array();
		$cat = strtolower( $category );

		switch ( $cat ) {
			case 'cpu':
				if ( preg_match( '/\b(AM5|AM4|LGA1700|LGA1851|LGA1200|LGA1151|sTR5|SP5)\b/i', $text, $m ) ) {
					$specs['socket'] = strtoupper( $m[1] );
				}
				if ( preg_match( '/(\d+)\s*(?:-|\s*)?(?:core|cores)\b/i', $text, $m ) ) {
					$specs['cores'] = intval( $m[1] );
				}
				if ( preg_match( '/(\d+)\s*(?:-|\s*)?(?:thread|threads)\b/i', $text, $m ) ) {
					$specs['threads'] = intval( $m[1] );
				}
				if ( preg_match( '/(?:up\s*to|boost|max\s*clock|turbo)?\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$specs['boost_clock'] = $m[1] . ' GHz';
				}
				if ( preg_match( '/(?:base|base\s*clock)\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$specs['base_clock'] = $m[1] . ' GHz';
				}
				if ( preg_match( '/(\d+)\s*(?:MB|Mb)\s*(?:L3|Cache|3D\s*V-Cache|Smart\s*Cache)/i', $text, $m ) ) {
					$specs['cache'] = $m[1] . 'MB';
				}
				if ( preg_match( '/(\d+)\s*W(?:att)?\b/i', $text, $m ) ) {
					$specs['tdp'] = $m[1] . 'W';
				}
				break;

			case 'gpu':
				if ( preg_match( '/(\d+)\s*(?:GB|G)\s*(GDDR6X|GDDR6|GDDR5X|GDDR5|HBM2e|HBM3)/i', $text, $m ) ) {
					$specs['vram_size'] = $m[1] . 'GB';
					$specs['memory_type'] = strtoupper( $m[2] );
				} elseif ( preg_match( '/(\d+)\s*(?:GB|G)\b/i', $text, $m ) ) {
					$specs['vram_size'] = $m[1] . 'GB';
				}
				if ( preg_match( '/\b(RTX\s*4090|RTX\s*4080\s*Super|RTX\s*4080|RTX\s*4070\s*Ti\s*Super|RTX\s*4070\s*Ti|RTX\s*4070\s*Super|RTX\s*4070|RTX\s*4060\s*Ti|RTX\s*4060|RTX\s*3060|RX\s*7900\s*XTX|RX\s*7900\s*XT|RX\s*7800\s*XT|RX\s*7700\s*XT|RX\s*7600\s*XT|RX\s*7600|Arc\s*A770|Arc\s*A750)\b/i', $text, $m ) ) {
					$specs['gpu_chipset'] = strtoupper( $m[1] );
				}
				if ( preg_match( '/(\d+)\s*(?:-|\s*)?bit\b/i', $text, $m ) ) {
					$specs['memory_bus'] = $m[1] . '-bit';
				}
				if ( preg_match( '/(?:PSU|Power Supply|Recommended PSU|Min PSU)[^\d]*(\d{3,4})\s*W/i', $text, $m ) ) {
					$specs['recommended_psu'] = $m[1] . 'W';
				}
				break;
		}

		return $specs;
	}

	protected static function format_specs_summary( $specs ) {
		if ( empty( $specs ) || ! is_array( $specs ) ) {
			return 'None';
		}
		$parts = array();
		foreach ( $specs as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$parts[] = "{$k}: {$v}";
			}
		}
		return ! empty( $parts ) ? implode( ' | ', array_slice( $parts, 0, 5 ) ) : 'Specs recorded';
	}

	protected function make_http_request( $url, $headers = array() ) {
		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init();
			$default_headers = array(
				'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.9',
			);

			foreach ( $headers as $k => $v ) {
				$default_headers[] = "{$k}: {$v}";
			}

			curl_setopt_array( $ch, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_TIMEOUT        => 20,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_ENCODING       => '',
				CURLOPT_HTTPHEADER     => $default_headers,
			) );

			$body = curl_exec( $ch );
			$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			return array(
				'success' => ( $code >= 200 && $code < 400 ),
				'code'    => $code,
				'body'    => $body,
			);
		}

		$response = \wp_remote_get( $url, array(
			'timeout' => 20,
			'headers' => array_merge( array(
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
			), $headers ),
		) );

		if ( \is_wp_error( $response ) ) {
			return array( 'success' => false, 'code' => 500, 'body' => '' );
		}

		$code = \wp_remote_retrieve_response_code( $response );
		$body = \wp_remote_retrieve_body( $response );

		return array(
			'success' => ( $code >= 200 && $code < 400 ),
			'code'    => $code,
			'body'    => $body,
		);
	}

	protected function emit( $logger, $level, $message ) {
		if ( is_callable( $logger ) ) {
			call_user_func( $logger, $level, $message, array(
				'timestamp' => current_time( 'H:i:s' ),
			) );
		}
	}
}
