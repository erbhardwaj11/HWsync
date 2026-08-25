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
		'TeamGroup', 'Western Digital', 'WD', 'Samsung', 'Crucial', 'Seagate', 'Kingston', 'Kioxia',
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
				// Update MPN if missing
				if ( empty( $component->mpn ) && ! empty( $mpn ) ) {
					$component->mpn = $mpn;
					$component->save();
				}
				return $component;
			}
		}

		// 3. Fuzzy search across same category and brand
		$candidates = Component::get_all( array( 'category' => $category, 'limit' => 200 ) );
		$best_match = null;
		$highest_sim = 0.0;

		foreach ( $candidates as $candidate ) {
			if ( strtolower( $candidate->brand ) === strtolower( $brand ) ) {
				similar_text( strtolower( $candidate->model_name ), strtolower( $normalized_model ), $percent );
				if ( $percent > $highest_sim && $percent >= 88.0 ) {
					$highest_sim = $percent;
					$best_match = $candidate;
				}
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

	public static function extract_brand( $title ) {
		foreach ( self::$known_brands as $brand ) {
			if ( preg_match( '/\b' . preg_quote( $brand, '/' ) . '\b/i', $title ) ) {
				// Standardize aliases
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

		// Common MPN patterns like 100-100000910WOF, BX8071514700K, etc.
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

		// Remove brand prefix if duplicated
		if ( ! empty( $brand ) ) {
			$clean = preg_replace( '/^' . preg_quote( $brand, '/' ) . '\s+/i', '', $clean );
		}

		// Remove generic buzzwords
		$buzzwords = array(
			'Desktop Processor', 'Processor with Radeon Graphics', 'Processor', 'Unlocked', 'Box Pack',
			'Gaming Graphics Card', 'Graphics Card', 'Video Card', 'Edition', 'Desktop Memory',
			'Internal Solid State Drive', 'Solid State Drive', 'PCIe NVMe M.2 SSD', 'M.2 NVMe SSD',
			'Power Supply Unit', 'Power Supply SMPS', 'SMPS', 'Liquid CPU Cooler', 'CPU Air Cooler',
			'Cabinet with Tempered Glass', 'Gaming Cabinet', 'Brand New', 'Special Offer'
		);

		foreach ( $buzzwords as $word ) {
			$clean = preg_replace( '/\b' . preg_quote( $word, '/' ) . '\b/i', '', $clean );
		}

		// Normalize multiple spaces and trimming
		$clean = trim( preg_replace( '/\s+/', ' ', $clean ) );
		return $clean ?: $title;
	}

	public static function extract_specs( $title, $category ) {
		$specs = array();

		// RAM / VRAM detection (e.g. 16GB, 32GB, 8GB, 24GB)
		if ( preg_match( '/\b(\d+)\s*GB\b/i', $title, $m ) ) {
			$specs['capacity_or_vram'] = $m[1] . 'GB';
		}

		// Frequency (e.g. 3200MHz, 6000MHz)
		if ( preg_match( '/\b(\d{4})\s*MHz\b/i', $title, $m ) ) {
			$specs['speed'] = $m[1] . 'MHz';
		}

		// DDR / GDDR generation
		if ( preg_match( '/\b(GDDR[56]X?|DDR[45])\b/i', $title, $m ) ) {
			$specs['memory_type'] = strtoupper( $m[1] );
		}

		// Form factor
		if ( preg_match( '/\b(ATX|Micro-ATX|M-ATX|Mini-ITX|E-ATX)\b/i', $title, $m ) ) {
			$specs['form_factor'] = strtoupper( $m[1] );
		}

		// Socket
		if ( preg_match( '/\b(AM4|AM5|LGA\s*1700|LGA\s*1851|LGA\s*1200)\b/i', $title, $m ) ) {
			$specs['socket'] = strtoupper( str_replace( ' ', '', $m[1] ) );
		}

		// Storage capacity (e.g. 1TB, 2TB, 500GB)
		if ( preg_match( '/\b(\d+)\s*(TB|GB)\b/i', $title, $m ) ) {
			$specs['storage_capacity'] = $m[1] . strtoupper( $m[2] );
		}

		// Wattage for PSU
		if ( preg_match( '/\b(\d{3,4})\s*W\b/i', $title, $m ) ) {
			$specs['wattage'] = $m[1] . 'W';
		}

		return $specs;
	}
}
