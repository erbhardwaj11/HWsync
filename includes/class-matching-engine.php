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

	/**
	 * Strictly evaluates if two Component models represent the exact same hardware product.
	 *
	 * @param Component $a
	 * @param Component $b
	 * @return bool
	 */
	public static function is_same_hardware_component( Component $a, Component $b ) {
		// 1. Category must match strictly
		if ( empty( $a->category ) || empty( $b->category ) || $a->category !== $b->category ) {
			return false;
		}

		// 2. MPN Match (Manufacturer Part Number)
		if ( ! empty( $a->mpn ) && ! empty( $b->mpn ) && strcasecmp( $a->mpn, $b->mpn ) === 0 ) {
			return true;
		}

		// 3. Normalized SKU Match
		if ( ! empty( $a->sku ) && ! empty( $b->sku ) && strcasecmp( $a->sku, $b->sku ) === 0 ) {
			return true;
		}

		// 4. Brand must match (case-insensitive)
		if ( ! empty( $a->brand ) && ! empty( $b->brand ) && strcasecmp( $a->brand, $b->brand ) !== 0 ) {
			return false;
		}

		// 5. Core Hardware ID Check
		$core_a = self::extract_core_hardware_id( $a->model_name, $a->category );
		$core_b = self::extract_core_hardware_id( $b->model_name, $b->category );

		if ( ! empty( $core_a ) && ! empty( $core_b ) ) {
			if ( strcasecmp( $core_a, $core_b ) !== 0 ) {
				// Different hardware chipsets (e.g. RTX 5050 vs RTX 4070) -> CANNOT MERGE!
				return false;
			}
			return true;
		}

		// 6. Check critical hardware specs: Socket / RAM Capacity / SSD Capacity / PSU Wattage
		$specs_a = $a->get_specs() ?: array();
		$specs_b = $b->get_specs() ?: array();

		// CPU Socket
		if ( ! empty( $specs_a['socket'] ) && ! empty( $specs_b['socket'] ) && strcasecmp( $specs_a['socket'], $specs_b['socket'] ) !== 0 ) {
			return false;
		}

		// RAM Capacity
		if ( ! empty( $specs_a['capacity_or_vram'] ) && ! empty( $specs_b['capacity_or_vram'] ) && strcasecmp( $specs_a['capacity_or_vram'], $specs_b['capacity_or_vram'] ) !== 0 ) {
			return false;
		}

		// Storage Capacity
		if ( ! empty( $specs_a['storage_capacity'] ) && ! empty( $specs_b['storage_capacity'] ) && strcasecmp( $specs_a['storage_capacity'], $specs_b['storage_capacity'] ) !== 0 ) {
			return false;
		}

		// PSU Wattage
		if ( ! empty( $specs_a['wattage'] ) && ! empty( $specs_b['wattage'] ) && strcasecmp( $specs_a['wattage'], $specs_b['wattage'] ) !== 0 ) {
			return false;
		}

		// 7. Normalized Name Similarity
		$norm_a = self::normalize_model_name( $a->model_name, $a->brand, $a->category );
		$norm_b = self::normalize_model_name( $b->model_name, $b->brand, $b->category );

		if ( strcasecmp( $norm_a, $norm_b ) === 0 ) {
			return true;
		}

		similar_text( strtolower( $norm_a ), strtolower( $norm_b ), $percent );
		if ( $percent >= 85.0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Scans and merges duplicate component records representing the same hardware product.
	 * Enforces strict category isolation and strict same-component matching.
	 *
	 * @param string $target_category Specific category or 'all'
	 * @param callable|null $logger Optional callback for live console logging: fn(string $level, string $message)
	 * @return array Merge report with counts and logs
	 */
	public static function merge_duplicate_components( $target_category = 'all', $logger = null ) {
		global $wpdb;
		$comp_table   = Database::get_table_name( 'components' );
		$prices_table = Database::get_table_name( 'vendor_prices' );

		$all_cats = array( 'cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet' );
		$categories = ( ! empty( $target_category ) && $target_category !== 'all' ) ? array( $target_category ) : $all_cats;

		$total_merged_records = 0;
		$logs = array();

		$emit = function( $level, $message ) use ( $logger, &$logs ) {
			$logs[] = array( 'level' => $level, 'message' => $message );
			if ( is_callable( $logger ) ) {
				call_user_func( $logger, $level, $message );
			}
		};

		$emit( 'info', "Starting Multi-Vendor Component Deduplication & Price Merging..." );

		foreach ( $categories as $cat ) {
			$cat_components = Component::get_all( array( 'category' => $cat, 'limit' => 2000 ) );
			if ( empty( $cat_components ) ) {
				continue;
			}

			$merged_in_cat = 0;
			$handled_ids = array();

			for ( $i = 0; $i < count( $cat_components ); $i++ ) {
				$primary = $cat_components[ $i ];
				if ( isset( $handled_ids[ $primary->id ] ) ) {
					continue;
				}

				$duplicates_to_merge = array();

				for ( $j = $i + 1; $j < count( $cat_components ); $j++ ) {
					$candidate = $cat_components[ $j ];
					if ( isset( $handled_ids[ $candidate->id ] ) ) {
						continue;
					}

					// Strict Category check: NEVER merge across different categories
					if ( $primary->category !== $candidate->category ) {
						continue;
					}

					// Check if $primary and $candidate represent the EXACT same component
					if ( self::is_same_hardware_component( $primary, $candidate ) ) {
						$duplicates_to_merge[] = $candidate;
						$handled_ids[ $candidate->id ] = true;
					}
				}

				if ( ! empty( $duplicates_to_merge ) ) {
					$handled_ids[ $primary->id ] = true;

					// Reassign all vendor prices from duplicate components to the primary component
					$merged_vendor_names = array();
					$primary_prices = $primary->get_prices();
					$existing_vendors = array();
					foreach ( $primary_prices as $pp ) {
						$existing_vendors[ $pp->vendor_id ] = $pp;
						if ( ! empty( $pp->vendor_name ) ) {
							$merged_vendor_names[] = $pp->vendor_name;
						}
					}

					foreach ( $duplicates_to_merge as $dup ) {
						$dup_prices = $dup->get_prices();
						foreach ( $dup_prices as $dp ) {
							if ( isset( $existing_vendors[ $dp->vendor_id ] ) ) {
								$existing_vp = $existing_vendors[ $dp->vendor_id ];
								// If dup price is lower, update the primary's price record
								if ( floatval( $dp->price ) > 0 && ( floatval( $existing_vp->price ) <= 0 || floatval( $dp->price ) < floatval( $existing_vp->price ) ) ) {
									$existing_vp->price = $dp->price;
									$existing_vp->original_price = $dp->original_price ?: $existing_vp->original_price;
									$existing_vp->product_url = $dp->product_url ?: $existing_vp->product_url;
									$existing_vp->is_in_stock = $dp->is_in_stock;
									$existing_vp->save();
								}
								// Delete redundant duplicate vendor price record
								$wpdb->query( $wpdb->prepare( "DELETE FROM {$prices_table} WHERE id = %d", $dp->id ) );
							} else {
								// Move vendor price to primary component
								$dp->component_id = $primary->id;
								$dp->save();
								$existing_vendors[ $dp->vendor_id ] = $dp;
								if ( ! empty( $dp->vendor_name ) ) {
									$merged_vendor_names[] = $dp->vendor_name;
								}
							}
						}

						// Merge specs if primary is missing any
						$primary_specs = $primary->get_specs() ?: array();
						$dup_specs     = $dup->get_specs() ?: array();
						if ( ! empty( $dup_specs ) ) {
							$merged_specs = array_merge( $dup_specs, $primary_specs );
							$primary->specs_json = $merged_specs;
						}

						// Copy MPN / SKU if primary was missing it
						if ( empty( $primary->mpn ) && ! empty( $dup->mpn ) ) {
							$primary->mpn = $dup->mpn;
						}
						if ( empty( $primary->sku ) && ! empty( $dup->sku ) ) {
							$primary->sku = $dup->sku;
						}

						// Delete duplicate component record from components table
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$comp_table} WHERE id = %d", $dup->id ) );

						$merged_in_cat++;
						$total_merged_records++;
					}

					$primary->save();

					// Fetch updated lowest price across all retailers
					$updated_prices = $primary->get_prices();
					$lowest_price = 0.0;
					$vendor_labels = array();
					foreach ( $updated_prices as $up ) {
						$p_val = floatval( $up->price );
						$is_stk = (bool) $up->is_in_stock;
						if ( $is_stk && $p_val > 0 && ( $lowest_price === 0.0 || $p_val < $lowest_price ) ) {
							$lowest_price = $p_val;
						}
						if ( ! empty( $up->vendor_name ) ) {
							$vendor_labels[] = $up->vendor_name;
						}
					}
					$vendor_str = implode( ', ', array_unique( $vendor_labels ) );
					$lowest_fmt = ( $lowest_price > 0 ) ? '₹' . number_format( $lowest_price, 2 ) : 'NA';

					$comp_name = trim( $primary->brand . ' ' . $primary->model_name );
					$emit( 'match', "[Category: " . strtoupper( $cat ) . "] Merged " . ( count( $duplicates_to_merge ) + 1 ) . " listings for \"{$comp_name}\" across [{$vendor_str}] -> Live Lowest: {$lowest_fmt}" );
				}
			}

			if ( $merged_in_cat > 0 ) {
				$emit( 'success', "Category [" . strtoupper( $cat ) . "]: Eliminated {$merged_in_cat} duplicate records." );
			}
		}

		$total_canonical_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$comp_table}" ) );

		$emit( 'finish', "Multi-Vendor Merge Completed! Consolidated {$total_merged_records} duplicate records. Active canonical hardware components: {$total_canonical_count}." );

		return array(
			'success'          => true,
			'total_merged'     => $total_merged_records,
			'canonical_total'  => $total_canonical_count,
			'logs'             => $logs,
		);
	}
}
