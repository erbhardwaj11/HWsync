<?php
namespace HWsync;

use HWsync\Models\Component;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matching_Engine {

	private static $known_brands = array(
		'AMD', 'Intel', 'NVIDIA', 'ASUS', 'MSI', 'Gigabyte', 'Zotac', 'Inno3D', 'Galax', 'Colorful',
		'Sapphire', 'PowerColor', 'ASRock', 'Corsair', 'G.Skill', 'Kingston', 'Crucial', 'XPG', 'Adata',
		'TeamGroup', 'Western Digital', 'WD', 'Samsung', 'Seagate', 'Kioxia',
		'Deepcool', 'Cooler Master', 'Lian Li', 'NZXT', 'Ant Esports', 'Thermaltake', 'Antec', 'Montech',
		'Seasonic', 'SilverStone', 'MSI MAG', 'ROG', 'TUF Gaming'
	);

	/**
	 * Matches a raw vendor product item to a canonical Component or creates a new one.
	 *
	 * @param array $raw_item Normalized raw item from vendor adapter
	 * @return Component
	 */
	public static function match_or_create_component( $raw_item ) {
		$raw_title = isset( $raw_item['title'] ) ? trim( $raw_item['title'] ) : '';
		$category  = isset( $raw_item['category'] ) ? trim( $raw_item['category'] ) : '';
		$vendor_sku = isset( $raw_item['sku'] ) ? trim( $raw_item['sku'] ) : '';

		if ( empty( $raw_title ) ) {
			return null;
		}

		$brand = self::extract_brand( $raw_title );
		$mpn = self::extract_mpn( $raw_title, $vendor_sku );
		$category = ! empty( $category ) ? $category : self::detect_category( $raw_title );
		$normalized_model = self::normalize_model_name( $raw_title, $brand, $category );
		$specs = self::extract_specs( $raw_title, $category );
		$core_hw_id = self::extract_core_hardware_id( $raw_title, $category );

		// 1. Try matching by MPN if available
		if ( ! empty( $mpn ) ) {
			$component = Component::find_by_mpn( $mpn );
			if ( $component ) {
				return $component;
			}
		}

		// 2. Try matching by Brand and Normalized Model Name
		if ( ! empty( $brand ) && ! empty( $normalized_model ) ) {
			$component = Component::find_by_brand_and_model( $brand, $normalized_model );
			if ( $component ) {
				if ( empty( $component->mpn ) && ! empty( $mpn ) ) {
					$component->mpn = $mpn;
					$component->save();
				}
				return $component;
			}
		}

		// 3. Strict Fuzzy search: MUST match category, brand, and Core Hardware ID exactly
		$candidates = Component::get_all( array( 'category' => $category, 'limit' => 300 ) );
		$best_match = null;
		$highest_sim = 0.0;

		foreach ( $candidates as $candidate ) {
			if ( strcasecmp( $candidate->brand, $brand ) !== 0 ) {
				continue;
			}

			// Validate that Core Hardware IDs match strictly
			if ( ! empty( $core_hw_id ) ) {
				$candidate_core_id = self::extract_core_hardware_id( $candidate->model_name, $category );
				if ( ! empty( $candidate_core_id ) && strcasecmp( $core_hw_id, $candidate_core_id ) !== 0 ) {
					// Different hardware model (e.g. RTX 5050 vs RTX 4070 or RX 9060 XT vs RX 7900 XTX) -> DO NOT MATCH!
					continue;
				}
			}

			// Validate RAM capacity / Storage capacity / PSU wattage
			if ( $category === 'ram' || $category === 'storage' || $category === 'psu' ) {
				$cand_specs = $candidate->get_specs();
				if ( ! empty( $specs['capacity_or_vram'] ) && ! empty( $cand_specs['capacity_or_vram'] ) && $specs['capacity_or_vram'] !== $cand_specs['capacity_or_vram'] ) {
					continue;
				}
				if ( ! empty( $specs['storage_capacity'] ) && ! empty( $cand_specs['storage_capacity'] ) && $specs['storage_capacity'] !== $cand_specs['storage_capacity'] ) {
					continue;
				}
				if ( ! empty( $specs['wattage'] ) && ! empty( $cand_specs['wattage'] ) && $specs['wattage'] !== $cand_specs['wattage'] ) {
					continue;
				}
			}

			similar_text( strtolower( $candidate->model_name ), strtolower( $normalized_model ), $percent );
			if ( $percent > $highest_sim && $percent >= 90.0 ) {
				$highest_sim = $percent;
				$best_match = $candidate;
			}
		}

		if ( $best_match ) {
			return $best_match;
		}

		// 4. Create new Canonical Component
		$new_component = new Component( array(
			'category'   => $category ?: 'other',
			'brand'      => $brand ?: 'Generic',
			'model_name' => $normalized_model ?: $raw_title,
			'mpn'        => $mpn,
			'sku'        => $vendor_sku,
			'specs_json' => $specs,
			'sync_hash'  => md5( ( $brand ?: '' ) . ( $normalized_model ?: $raw_title ) . $category ),
		) );

		$new_component->save();
		return $new_component;
	}

	/**
	 * Extract critical hardware model identifier (e.g. RTX 5050, RX 9060 XT, Ryzen 7 7800X3D, i7-14700K)
	 * to prevent cross-model collision during fuzzy matching.
	 */
	public static function extract_core_hardware_id( $title, $category ) {
		$t = strtoupper( $title );
		$cat = strtolower( $category );

		if ( $cat === 'cpu' ) {
			if ( preg_match( '/\b(RYZEN\s*[3579]\s*PRO\s*\d{4}[A-Z0-9]*|RYZEN\s*[3579]\s*\d{4}[A-Z0-9]*|THREADRIPPER\s*PRO\s*\d{4}[A-Z0-9]*|THREADRIPPER\s*\d{4}[A-Z0-9]*|I[3579]-\d{4,5}[A-Z0-9]*|CORE\s*ULTRA\s*[3579]\s*\d{3}[A-Z0-9]*)\b/i', $t, $m ) ) {
				return preg_replace( '/\s+/', ' ', $m[1] );
			}
		} elseif ( $cat === 'gpu' ) {
			if ( preg_match( '/\b(RTX\s*\d{4}\s*TI\s*SUPER|RTX\s*\d{4}\s*SUPER|RTX\s*\d{4}\s*TI|RTX\s*\d{4}|GTX\s*\d{4}\s*TI|GTX\s*\d{4}|RX\s*\d{4}\s*XTX|RX\s*\d{4}\s*XT|RX\s*\d{4}|ARC\s*A\d{3}[A-Z]?)\b/i', $t, $m ) ) {
				return preg_replace( '/\s+/', ' ', $m[1] );
			}
		} elseif ( $cat === 'motherboard' ) {
			if ( preg_match( '/\b(X870E|X870|X670E|X670|B850|B650E|B650|A620|Z890|Z790|Z690|B760|B660|H610)\b/i', $t, $m ) ) {
				return $m[1];
			}
		} elseif ( $cat === 'ram' ) {
			$cap = preg_match( '/\b(\d+)\s*GB\b/i', $t, $cm ) ? $cm[1] . 'GB' : '';
			$spd = preg_match( '/\b(\d{4})\s*(?:MHZ|MT\/S)\b/i', $t, $sm ) ? $sm[1] . 'MHZ' : '';
			$gen = preg_match( '/\b(DDR[45])\b/i', $t, $gm ) ? $gm[1] : '';
			return trim( "{$cap}-{$spd}-{$gen}", '-' );
		} elseif ( $cat === 'storage' ) {
			if ( preg_match( '/\b(\d+)\s*(?:TB|GB)\b/i', $t, $m ) ) {
				return $m[0];
			}
		} elseif ( $cat === 'psu' ) {
			if ( preg_match( '/\b(\d{3,4})\s*W\b/i', $t, $m ) ) {
				return $m[1] . 'W';
			}
		}

		return null;
	}

	public static function extract_brand( $title ) {
		foreach ( self::$known_brands as $brand ) {
			if ( preg_match( '/\b' . preg_quote( $brand, '/' ) . '\b/i', $title ) ) {
				if ( strcasecmp( $brand, 'WD' ) === 0 ) {
					return 'Western Digital';
				}
				if ( strcasecmp( $brand, 'ROG' ) === 0 || strcasecmp( $brand, 'TUF Gaming' ) === 0 ) {
					return 'ASUS';
				}
				return $brand;
			}
		}
		return 'Generic';
	}

	public static function detect_category( $title ) {
		$title_lower = strtolower( $title );

		if ( preg_match( '/\b(ryzen|intel core|processor|cpu|i3|i5|i7|i9|threadripper)\b/i', $title_lower ) ) {
			return 'cpu';
		}
		if ( preg_match( '/\b(rtx|gtx|radeon|rx\s*\d+|geforce|graphics card|gpu)\b/i', $title_lower ) ) {
			return 'gpu';
		}
		if ( preg_match( '/\b(motherboard|b650|b760|z790|x670|x870|a620|b550|h610)\b/i', $title_lower ) ) {
			return 'motherboard';
		}
		if ( preg_match( '/\b(ddr4|ddr5|ram|memory|desktop memory|3200mhz|6000mhz|5600mhz)\b/i', $title_lower ) ) {
			return 'ram';
		}
		if ( preg_match( '/\b(nvme|ssd|solid state drive|m\.2|sata ssd|hard disk|hdd)\b/i', $title_lower ) ) {
			return 'storage';
		}
		if ( preg_match( '/\b(smps|power supply|psu|80 plus|80\+|bronze|gold|750w|850w|650w|1000w)\b/i', $title_lower ) ) {
			return 'psu';
		}
		if ( preg_match( '/\b(cooler|aio|liquid cooler|air cooler|radiator|heatsink)\b/i', $title_lower ) ) {
			return 'cooler';
		}
		if ( preg_match( '/\b(cabinet|chassis|pc case|mid tower|mini itx case)\b/i', $title_lower ) ) {
			return 'cabinet';
		}

		return 'other';
	}

	public static function extract_mpn( $title, $sku = '' ) {
		if ( ! empty( $sku ) && strlen( $sku ) >= 5 && ! is_numeric( $sku ) ) {
			return $sku;
		}

		if ( preg_match( '/\b([0-9]{3}-[0-9]{9}[A-Z0-9]{3})\b/i', $title, $m ) ) {
			return strtoupper( $m[1] );
		}
		if ( preg_match( '/\b(BX80[0-9]{3}[A-Z0-9]+)\b/i', $title, $m ) ) {
			return strtoupper( $m[1] );
		}

		return null;
	}

	public static function normalize_model_name( $title, $brand, $category ) {
		$clean = $title;

		if ( ! empty( $brand ) ) {
			$clean = preg_replace( '/^' . preg_quote( $brand, '/' ) . '\s+/i', '', $clean );
		}

		$buzzwords = array(
			'Desktop Processor', 'Processor with Radeon Graphics', 'Processor', 'Unlocked', 'Box Pack',
			'Gaming Graphics Card', 'Graphics Card', 'Video Card', 'Desktop Memory',
			'Internal Solid State Drive', 'Solid State Drive', 'PCIe NVMe M.2 SSD', 'M.2 NVMe SSD',
			'Power Supply Unit', 'Power Supply SMPS', 'SMPS', 'Liquid CPU Cooler', 'CPU Air Cooler',
			'Cabinet with Tempered Glass', 'Gaming Cabinet', 'Brand New', 'Special Offer'
		);

		foreach ( $buzzwords as $word ) {
			$clean = preg_replace( '/\b' . preg_quote( $word, '/' ) . '\b/i', '', $clean );
		}

		$clean = trim( preg_replace( '/\s+/', ' ', $clean ) );
		return $clean ?: $title;
	}

	public static function extract_specs( $title, $category ) {
		$specs = array();

		if ( preg_match( '/\b(\d+)\s*GB\b/i', $title, $m ) ) {
			$specs['capacity_or_vram'] = $m[1] . 'GB';
		}

		if ( preg_match( '/\b(\d{4})\s*MHz\b/i', $title, $m ) ) {
			$specs['speed'] = $m[1] . 'MHz';
		}

		if ( preg_match( '/\b(GDDR[56]X?|DDR[45])\b/i', $title, $m ) ) {
			$specs['memory_type'] = strtoupper( $m[1] );
		}

		if ( preg_match( '/\b(ATX|Micro-ATX|M-ATX|Mini-ITX|E-ATX)\b/i', $title, $m ) ) {
			$specs['form_factor'] = strtoupper( $m[1] );
		}

		if ( preg_match( '/\b(AM4|AM5|LGA\s*1700|LGA\s*1851|LGA\s*1200)\b/i', $title, $m ) ) {
			$specs['socket'] = strtoupper( str_replace( ' ', '', $m[1] ) );
		}

		if ( preg_match( '/\b(\d+)\s*(TB|GB)\b/i', $title, $m ) ) {
			$specs['storage_capacity'] = $m[1] . strtoupper( $m[2] );
		}

		if ( preg_match( '/\b(\d{3,4})\s*W\b/i', $title, $m ) ) {
			$specs['wattage'] = $m[1] . 'W';
		}

		return $specs;
	}
}
