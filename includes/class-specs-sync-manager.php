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
	 * Run manual specs synchronization for existing canonical components in DB.
	 * Visits product pages on vendor websites, extracts the specifications section,
	 * updates database records, and updates WordPress component posts.
	 *
	 * @param array $options Options array: 'category', 'component_id', 'limit'.
	 * @param callable|null $logger Progress callback logger.
	 * @return array Sync report.
	 */
	public function run_specs_sync( $options = array(), $logger = null ) {
		global $wpdb;
		$comp_table   = Database::get_table_name( 'components' );
		$prices_table = Database::get_table_name( 'vendor_prices' );

		$category     = isset( $options['category'] ) ? sanitize_text_field( $options['category'] ) : 'all';
		$component_id = isset( $options['component_id'] ) ? intval( $options['component_id'] ) : 0;
		$limit        = isset( $options['limit'] ) ? intval( $options['limit'] ) : 100;

		$this->emit( $logger, 'info', "Starting Technical Specifications Sync engine..." );

		$where_clauses = array( "1=1" );
		if ( $component_id > 0 ) {
			$where_clauses[] = $wpdb->prepare( "id = %d", $component_id );
		} elseif ( $category !== 'all' && ! empty( $category ) ) {
			$where_clauses[] = $wpdb->prepare( "category = %s", $category );
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$components_raw = $wpdb->get_results( "SELECT * FROM {$comp_table} WHERE {$where_sql} ORDER BY id ASC LIMIT {$limit}", \ARRAY_A );

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

			// 1. Gather all linked vendor price records for this component
			$prices = $component->get_prices();
			if ( empty( $prices ) ) {
				$this->emit( $logger, 'debug', "Component #{$component->id} has no linked vendor price listings. Skipping." );
				continue;
			}

			$merged_raw_specs = is_array( $component->specs_json ) && isset( $component->specs_json['raw_specs_table'] )
				? (array) $component->specs_json['raw_specs_table']
				: array();

			$collected_text = $component->model_name . ' ' . ( $component->mpn ?: '' ) . ' ' . ( $component->sku ?: '' );
			$successful_vendor = '';

			// 2. Visit linked vendor product pages to locate and extract specs section
			foreach ( $prices as $p ) {
				if ( empty( $p->product_url ) ) {
					continue;
				}

				$vendor_slug = ! empty( $p->vendor_slug ) ? $p->vendor_slug : '';
				$this->emit( $logger, 'debug', "Visiting product page on " . ucfirst( $vendor_slug ) . ": {$p->product_url}..." );

				$page_specs = $this->fetch_specs_from_product_url( $p->product_url, $vendor_slug, $component->category );

				if ( ! empty( $page_specs ) ) {
					$merged_raw_specs = array_merge( $merged_raw_specs, $page_specs );
					$successful_vendor = ucfirst( $vendor_slug );
					$this->emit( $logger, 'match', "Extracted " . count( $page_specs ) . " specs attributes from {$successful_vendor} product specs section." );

					// Aggregate text for domain regex extraction
					foreach ( $page_specs as $sk => $sv ) {
						$collected_text .= " {$sk}: {$sv}";
					}
					break; // Found high-fidelity specs from a primary retailer
				}
			}

			// 3. Extract deep structured technical specifications using domain regex patterns
			$structured_specs = self::extract_detailed_specs( $component->category, $collected_text, $component->specs_json ?: array() );

			if ( ! empty( $merged_raw_specs ) ) {
				$structured_specs['raw_specs_table'] = $merged_raw_specs;
			}

			// 4. Save updated specs
			if ( ! empty( $structured_specs ) ) {
				$component->specs_json = $structured_specs;
				$component->save();
				$report['specs_updated']++;

				$spec_summary = self::format_specs_summary( $structured_specs );
				$this->emit( $logger, 'success', "Specs Saved for #{$component->id} [{$component->model_name}]: {$spec_summary}" );

				// 5. Update linked WordPress post content & meta
				if ( ! empty( $component->wp_post_id ) ) {
					Post_Sync_Processor::sync_component_to_post( $component->id );
					$report['posts_refreshed']++;
				}
			} else {
				$this->emit( $logger, 'debug', "No additional specifications discovered for #{$component->id}." );
			}
		}

		$this->emit( $logger, 'finish', "Specifications Sync Completed! Updated {$report['specs_updated']} components and refreshed {$report['posts_refreshed']} WordPress posts." );

		return $report;
	}

	/**
	 * Fetch and extract specifications section from a vendor's product page URL.
	 *
	 * @param string $url
	 * @param string $vendor_slug
	 * @param string $category
	 * @return array Key-value dictionary of specs.
	 */
	public function fetch_specs_from_product_url( $url, $vendor_slug, $category = '' ) {
		$specs = array();
		if ( empty( $url ) ) {
			return $specs;
		}

		// Handle Shopify stores (EliteHubs) via product JSON or body_html
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

		// Standard cURL fetch for WooCommerce / OpenCart product pages
		$res = $this->make_http_request( $url );
		if ( empty( $res['body'] ) ) {
			return $specs;
		}

		$html = $res['body'];

		// Locate the specifications section in the product DOM
		$specs_html = '';

		// Pattern 1: Specification / Additional Information tab panel
		if ( preg_match( '/<(?:div|section|table)[^>]*(?:id="tab-specification"|id="tab-specs"|class="[^"]*(?:woocommerce-Tabs-panel--specification|shop_attributes|product-info-table|specification)[^"]*"|id="tab-additional_information")[^>]*>[\s\S]*?<\/(?:div|section|table)>/i', $html, $sm ) ) {
			$specs_html = $sm[0];
		}
		// Pattern 2: Tables inside description tab
		elseif ( preg_match( '/<div[^>]*id="tab-description"[^>]*>[\s\S]*?<\/div>/i', $html, $sm ) ) {
			$specs_html = $sm[0];
		}
		// Pattern 3: Full page fallback
		else {
			$specs_html = $html;
		}

		return self::parse_html_specs_section( $specs_html );
	}

	/**
	 * Parse HTML snippet (tables, lists, definition lists) into clean key-value specs dictionary.
	 *
	 * @param string $html_snippet
	 * @return array
	 */
	public static function parse_html_specs_section( $html_snippet ) {
		$specs = array();
		if ( empty( $html_snippet ) ) {
			return $specs;
		}

		// 1. Table rows: <tr><th>Key</th><td>Val</td></tr> or <tr><td>Key</td><td>Val</td></tr>
		if ( preg_match_all( '/<tr[^>]*>[\s\S]*?<\/tr>/i', $html_snippet, $rows ) ) {
			foreach ( $rows[0] as $r ) {
				if ( preg_match_all( '/<(?:th|td)[^>]*>([\s\S]*?)<\/(?:th|td)>/i', $r, $cells ) ) {
					if ( count( $cells[1] ) >= 2 ) {
						$k = trim( strip_tags( $cells[1][0] ) );
						$v = trim( strip_tags( $cells[1][1] ) );
						$k = html_entity_decode( str_replace( ':', '', $k ), ENT_QUOTES, 'UTF-8' );
						$v = html_entity_decode( $v, ENT_QUOTES, 'UTF-8' );
						$k = trim( preg_replace( '/\s+/', ' ', $k ) );
						$v = trim( preg_replace( '/\s+/', ' ', $v ) );

						if ( ! empty( $k ) && ! empty( $v ) && strlen( $k ) < 80 && strlen( $v ) < 300 && strcasecmp( $k, $v ) !== 0 ) {
							$specs[ $k ] = $v;
						}
					}
				}
			}
		}

		// 2. List items: <li><strong>Key:</strong> Val</li>
		if ( preg_match_all( '/<li[^>]*>[\s\S]*?<\/li>/i', $html_snippet, $lis ) ) {
			foreach ( $lis[0] as $li ) {
				if ( preg_match( '/<(?:strong|b|span)[^>]*>([^<:]+)[:]?<\/(?:strong|b|span)>[\s:]*([^<]+)/i', $li, $m ) ) {
					$k = html_entity_decode( trim( strip_tags( $m[1] ) ), ENT_QUOTES, 'UTF-8' );
					$v = html_entity_decode( trim( strip_tags( $m[2] ) ), ENT_QUOTES, 'UTF-8' );
					$k = trim( preg_replace( '/\s+/', ' ', $k ) );
					$v = trim( preg_replace( '/\s+/', ' ', $v ) );

					if ( ! empty( $k ) && ! empty( $v ) && strlen( $k ) < 80 && strlen( $v ) < 300 ) {
						if ( ! isset( $specs[ $k ] ) ) {
							$specs[ $k ] = $v;
						}
					}
				}
			}
		}

		return $specs;
	}

	/**
	 * Extract rich category-specific specifications from title, description & raw specs.
	 *
	 * @param string $category
	 * @param string $text
	 * @param array $existing_specs
	 * @return array
	 */
	public static function extract_detailed_specs( $category, $text, $existing_specs = array() ) {
		$specs = is_array( $existing_specs ) ? $existing_specs : array();

		switch ( strtolower( $category ) ) {
			case 'cpu':
				// Socket
				if ( preg_match( '/\b(AM5|AM4|LGA1700|LGA1851|LGA1200|LGA1151|sTR5|SP5)\b/i', $text, $m ) ) {
					$specs['socket'] = strtoupper( $m[1] );
				}
				// Cores & Threads
				if ( preg_match( '/(\d+)\s*(?:-|\s*)?(?:core|cores)\b/i', $text, $m ) ) {
					$specs['cores'] = intval( $m[1] );
				}
				if ( preg_match( '/(\d+)\s*(?:-|\s*)?(?:thread|threads)\b/i', $text, $m ) ) {
					$specs['threads'] = intval( $m[1] );
				}
				// Boost Clock / Max Clock
				if ( preg_match( '/(?:up\s*to|boost|max\s*clock|freq|turbo)?\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$specs['boost_clock'] = $m[1] . ' GHz';
				}
				// Base Clock
				if ( preg_match( '/(?:base|base\s*clock)\s*(\d+(?:\.\d+)?)\s*(?:GHz)\b/i', $text, $m ) ) {
					$specs['base_clock'] = $m[1] . ' GHz';
				}
				// Cache
				if ( preg_match( '/(\d+)\s*(?:MB|Mb)\s*(?:L3|Cache|3D\s*V-Cache|Smart\s*Cache)/i', $text, $m ) ) {
					$specs['cache'] = $m[1] . 'MB';
				}
				// TDP
				if ( preg_match( '/(\d+)\s*W(?:att)?\b/i', $text, $m ) ) {
					$specs['tdp'] = $m[1] . 'W';
				}
				// Integrated Graphics
				if ( preg_match( '/\b(Radeon Graphics|Intel UHD \d+|Iris Xe|No Graphics|Graphics: [^\n,]+)\b/i', $text, $m ) ) {
					$specs['integrated_gpu'] = $m[1];
				}
				// Memory Support
				if ( preg_match( '/\b(DDR5(?:\s*\+\s*DDR4)?|DDR4|DDR5)\b/i', $text, $m ) ) {
					$specs['memory_support'] = strtoupper( $m[1] );
				}
				break;

			case 'gpu':
				// VRAM
				if ( preg_match( '/(\d+)\s*(?:GB|G)\s*(GDDR6X|GDDR6|GDDR5X|GDDR5|HBM2e|HBM3)/i', $text, $m ) ) {
					$specs['vram_size'] = $m[1] . 'GB';
					$specs['memory_type'] = strtoupper( $m[2] );
				} elseif ( preg_match( '/(\d+)\s*(?:GB|G)\b/i', $text, $m ) ) {
					$specs['vram_size'] = $m[1] . 'GB';
				}
				// Chipset / Architecture
				if ( preg_match( '/\b(RTX\s*4090|RTX\s*4080\s*Super|RTX\s*4080|RTX\s*4070\s*Ti\s*Super|RTX\s*4070\s*Ti|RTX\s*4070\s*Super|RTX\s*4070|RTX\s*4060\s*Ti|RTX\s*4060|RTX\s*3060|RX\s*7900\s*XTX|RX\s*7900\s*XT|RX\s*7800\s*XT|RX\s*7700\s*XT|RX\s*7600\s*XT|RX\s*7600|Arc\s*A770|Arc\s*A750)\b/i', $text, $m ) ) {
					$specs['gpu_chipset'] = strtoupper( $m[1] );
				}
				// Memory Bus
				if ( preg_match( '/(\d+)\s*(?:-|\s*)?bit\b/i', $text, $m ) ) {
					$specs['memory_bus'] = $m[1] . '-bit';
				}
				// Recommended PSU
				if ( preg_match( '/(?:PSU|Power Supply|Recommended PSU|Min PSU)[^\d]*(\d{3,4})\s*W/i', $text, $m ) ) {
					$specs['recommended_psu'] = $m[1] . 'W';
				}
				// Form Factor / Slot width
				if ( preg_match( '/\b(\d+(?:\.\d+)?)\s*(?:slot|slots)\b/i', $text, $m ) ) {
					$specs['slot_width'] = $m[1] . ' Slot';
				}
				break;

			case 'motherboard':
				// Socket
				if ( preg_match( '/\b(AM5|AM4|LGA1700|LGA1851|LGA1200|LGA1151)\b/i', $text, $m ) ) {
					$specs['socket'] = strtoupper( $m[1] );
				}
				// Chipset
				if ( preg_match( '/\b(X870E|X870|X670E|X670|B850|B650E|B650|A620|Z890|Z790|Z690|B760|B660|H610)\b/i', $text, $m ) ) {
					$specs['chipset'] = strtoupper( $m[1] );
				}
				// Form Factor
				if ( preg_match( '/\b(E-ATX|Extended ATX|ATX|Micro-ATX|Micro ATX|mATX|Mini-ITX|Mini ITX|ITX)\b/i', $text, $m ) ) {
					$specs['form_factor'] = strtoupper( str_replace( ' ', '-', $m[1] ) );
				}
				// Memory Type & Slots
				if ( preg_match( '/\b(DDR5|DDR4)\b/i', $text, $m ) ) {
					$specs['memory_type'] = strtoupper( $m[1] );
				}
				if ( preg_match( '/(\d+)\s*(?:x\s*)?(?:DIMM|RAM\s*Slots|Memory\s*Slots)\b/i', $text, $m ) ) {
					$specs['ram_slots'] = intval( $m[1] );
				}
				// Wi-Fi
				if ( preg_match( '/\b(WiFi 7|WiFi 6E|WiFi 6|Wi-Fi 7|Wi-Fi 6E|Wi-Fi 6|WiFi|Wi-Fi|AX|AC)\b/i', $text, $m ) ) {
					$specs['wireless'] = 'Built-in ' . strtoupper( $m[1] );
				}
				// PCIe Version
				if ( preg_match( '/\b(PCIe\s*5\.0|PCIe\s*4\.0)\b/i', $text, $m ) ) {
					$specs['pcie_version'] = strtoupper( $m[1] );
				}
				break;

			case 'ram':
				// Memory Type
				if ( preg_match( '/\b(DDR5|DDR4|DDR3)\b/i', $text, $m ) ) {
					$specs['memory_type'] = strtoupper( $m[1] );
				}
				// Total Capacity & Configuration
				if ( preg_match( '/(\d+)\s*GB\s*\(\s*(\d+)\s*x\s*(\d+)\s*GB\s*\)/i', $text, $m ) ) {
					$specs['capacity'] = $m[1] . 'GB';
					$specs['kit_config'] = $m[2] . 'x' . $m[3] . 'GB';
				} elseif ( preg_match( '/(\d+)\s*GB\s*x\s*(\d+)/i', $text, $m ) ) {
					$specs['capacity'] = ( intval( $m[1] ) * intval( $m[2] ) ) . 'GB';
					$specs['kit_config'] = $m[2] . 'x' . $m[1] . 'GB';
				} elseif ( preg_match( '/(\d+)\s*GB\b/i', $text, $m ) ) {
					$specs['capacity'] = $m[1] . 'GB';
					$specs['kit_config'] = '1x' . $m[1] . 'GB';
				}
				// Speed
				if ( preg_match( '/(\d{4})\s*(?:MHz|MT\/s|MTs)/i', $text, $m ) ) {
					$specs['speed'] = $m[1] . ' MHz';
				}
				// Latency
				if ( preg_match( '/\b(CL\s*\d{2}|C\d{2})\b/i', $text, $m ) ) {
					$specs['latency'] = strtoupper( $m[1] );
				}
				// RGB
				if ( preg_match( '/\b(RGB|ARGB)\b/i', $text ) ) {
					$specs['lighting'] = 'RGB';
				} else {
					$specs['lighting'] = 'Non-RGB';
				}
				break;

			case 'storage':
				// Capacity
				if ( preg_match( '/(\d+)\s*(?:TB|Tb)\b/i', $text, $m ) ) {
					$specs['capacity'] = $m[1] . 'TB';
				} elseif ( preg_match( '/(\d{3,4})\s*(?:GB|Gb)\b/i', $text, $m ) ) {
					$specs['capacity'] = $m[1] . 'GB';
				}
				// Interface
				if ( preg_match( '/\b(PCIe\s*5\.0|Gen5|PCIe\s*Gen\s*5)\b/i', $text ) ) {
					$specs['interface'] = 'PCIe Gen5 x4 NVMe';
				} elseif ( preg_match( '/\b(PCIe\s*4\.0|Gen4|PCIe\s*Gen\s*4)\b/i', $text ) ) {
					$specs['interface'] = 'PCIe Gen4 x4 NVMe';
				} elseif ( preg_match( '/\b(PCIe\s*3\.0|Gen3|PCIe\s*Gen\s*3)\b/i', $text ) ) {
					$specs['interface'] = 'PCIe Gen3 x4 NVMe';
				} elseif ( preg_match( '/\b(SATA\s*III|SATA\s*3|SATA\s*6Gb\/s)\b/i', $text ) ) {
					$specs['interface'] = 'SATA III 6Gb/s';
				}
				// Form Factor
				if ( preg_match( '/\b(M\.2\s*2280|2280|M\.2\s*2242|2\.5\s*inch|2\.5"|3\.5\s*inch|3\.5")\b/i', $text, $m ) ) {
					$specs['form_factor'] = $m[1];
				}
				// Read Speed
				if ( preg_match( '/(?:read|read\s*speed)[^\d]*(\d{3,5})\s*(?:MB\/s|MBps)/i', $text, $m ) ) {
					$specs['read_speed'] = $m[1] . ' MB/s';
				}
				// Write Speed
				if ( preg_match( '/(?:write|write\s*speed)[^\d]*(\d{3,5})\s*(?:MB\/s|MBps)/i', $text, $m ) ) {
					$specs['write_speed'] = $m[1] . ' MB/s';
				}
				break;

			case 'psu':
				// Wattage
				if ( preg_match( '/(\d{3,4})\s*(?:W|Watt|Watts)\b/i', $text, $m ) ) {
					$specs['wattage'] = $m[1] . 'W';
				}
				// 80 Plus Rating
				if ( preg_match( '/80\s*Plus\s*(Titanium|Platinum|Gold|Silver|Bronze|White|Standard)/i', $text, $m ) ) {
					$specs['efficiency_rating'] = '80+ ' . ucfirst( strtolower( $m[1] ) );
				}
				// Modularity
				if ( preg_match( '/\b(Fully\s*Modular|Full\s*Modular)\b/i', $text ) ) {
					$specs['modularity'] = 'Fully Modular';
				} elseif ( preg_match( '/\b(Semi\s*Modular)\b/i', $text ) ) {
					$specs['modularity'] = 'Semi-Modular';
				} elseif ( preg_match( '/\b(Non\s*Modular)\b/i', $text ) ) {
					$specs['modularity'] = 'Non-Modular';
				}
				// PCIe 5.0 / ATX 3.0
				if ( preg_match( '/\b(ATX\s*3\.0|ATX\s*3\.1|PCIe\s*5\.0|12VHPWR)\b/i', $text, $m ) ) {
					$specs['pcie5_ready'] = 'Yes (' . strtoupper( $m[1] ) . ')';
				}
				break;

			case 'cooler':
				// Cooler Type & Radiator
				if ( preg_match( '/(\d{3})\s*mm\s*(?:AIO|Liquid|Liquid\s*Cooler)/i', $text, $m ) ) {
					$specs['cooler_type'] = 'AIO Liquid Cooler';
					$specs['radiator_size'] = $m[1] . 'mm';
				} elseif ( preg_match( '/\b(AIO|Liquid\s*Cooler)\b/i', $text ) ) {
					$specs['cooler_type'] = 'AIO Liquid Cooler';
				} elseif ( preg_match( '/\b(Air\s*Cooler|Dual\s*Tower|Single\s*Tower)\b/i', $text, $m ) ) {
					$specs['cooler_type'] = 'Air Cooler (' . $m[1] . ')';
				}
				// Fan Lighting
				if ( preg_match( '/\b(ARGB|RGB|Auto\s*RGB)\b/i', $text ) ) {
					$specs['lighting'] = strtoupper( $m[1] );
				}
				break;

			case 'cabinet':
				// Form factor
				if ( preg_match( '/\b(Full\s*Tower|Mid\s*Tower|Mini\s*Tower|Mini\s*ITX)\b/i', $text, $m ) ) {
					$specs['case_type'] = ucwords( strtolower( $m[1] ) );
				}
				// Motherboard support
				if ( preg_match( '/\b(E-ATX|ATX|Micro-ATX|mATX|Mini-ITX|ITX)\b/i', $text, $m ) ) {
					$specs['mb_support'] = strtoupper( $m[1] );
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
			if ( $k === 'raw_specs_table' ) {
				continue;
			}
			if ( is_scalar( $v ) ) {
				$label = ucwords( str_replace( '_', ' ', $k ) );
				$parts[] = "{$label}: {$v}";
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
