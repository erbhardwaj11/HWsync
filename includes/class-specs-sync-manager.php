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
	 *
	 * @param array $options Options array: 'category', 'component_id', 'limit'.
	 * @param callable|null $logger Progress callback logger.
	 * @return array Sync report.
	 */
	public function run_specs_sync( $options = array(), $logger = null ) {
		global $wpdb;
		$comp_table = Database::get_table_name( 'components' );
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

		$this->emit( $logger, 'info', "Found " . count( $components_raw ) . " canonical components in DB. Extracting detailed specifications..." );

		foreach ( $components_raw as $c_row ) {
			$component = new Component( $c_row );
			$this->emit( $logger, 'debug', "Inspecting Component #{$component->id}: [{$component->brand} {$component->model_name}] ({$component->category})..." );

			// 1. Gather all linked vendor price records for this component
			$prices = $component->get_prices();
			$collected_text = $component->model_name . ' ' . ( $component->mpn ?: '' ) . ' ' . ( $component->sku ?: '' );

			foreach ( $prices as $p ) {
				$collected_text .= ' ' . $p->vendor_product_title;
				if ( ! empty( $p->raw_data_json['description'] ) ) {
					$collected_text .= ' ' . $p->raw_data_json['description'];
				}
				if ( ! empty( $p->raw_data_json['specs'] ) && is_array( $p->raw_data_json['specs'] ) ) {
					$collected_text .= ' ' . json_encode( $p->raw_data_json['specs'] );
				}
			}

			// 2. Extract deep technical specifications using domain regex patterns
			$extracted_specs = self::extract_detailed_specs( $component->category, $collected_text, $component->specs_json ?: array() );

			// 3. Save updated specs if new data found
			if ( ! empty( $extracted_specs ) ) {
				$component->specs_json = $extracted_specs;
				$component->save();
				$report['specs_updated']++;

				$spec_summary = self::format_specs_summary( $extracted_specs );
				$this->emit( $logger, 'match', "Specs Updated for #{$component->id} [{$component->model_name}]: {$spec_summary}" );

				// 4. Update linked WordPress post content & meta
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
				if ( preg_match( '/\b(AM5|AM4|LGA1700|LGA1200|LGA1151|sTR5|SP5)\b/i', $text, $m ) ) {
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
				if ( preg_match( '/\b(ARGB|RGB|Auto\s*RGB)\b/i', $text, $m ) ) {
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
			if ( is_scalar( $v ) ) {
				$label = ucwords( str_replace( '_', ' ', $k ) );
				$parts[] = "{$label}: {$v}";
			}
		}
		return implode( ' | ', array_slice( $parts, 0, 5 ) );
	}

	protected function emit( $logger, $level, $message ) {
		if ( is_callable( $logger ) ) {
			call_user_func( $logger, $level, $message, array(
				'timestamp' => current_time( 'H:i:s' ),
			) );
		}
	}
}
