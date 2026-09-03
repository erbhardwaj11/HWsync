<?php
namespace HWsync;

use HWsync\Models\Vendor;
use HWsync\Models\Vendor_Price;
use HWsync\Models\Component;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sync_Manager {

	private $adapter_map = array(
		'mdcomputers' => 'HWsync\\Vendors\\MDComputers_Adapter',
		'vedant'      => 'HWsync\\Vendors\\Vedant_Adapter',
		'primeabgb'   => 'HWsync\\Vendors\\PrimeABGB_Adapter',
		'elitehubs'   => 'HWsync\\Vendors\\EliteHubs_Adapter',
		'pcstudio'    => 'HWsync\\Vendors\\PCStudio_Adapter',
		'theitdepot'  => 'HWsync\\Vendors\\TheITDepot_Adapter',
		'amazon-in'   => 'HWsync\\Vendors\\Amazon_Adapter',
		'amazon'      => 'HWsync\\Vendors\\Amazon_Adapter',
	);

	/**
	 * Run full synchronization cycle across all active vendors and categories.
	 *
	 * @param array $options Filter options: 'vendor' => 'mdcomputers', 'category' => 'cpu'
	 * @param callable|null $logger Realtime streaming callback ($level, $message, $stats)
	 * @return array Sync report
	 */
	public function run_sync( $options = array(), $logger = null ) {
		$target_vendor_slug = isset( $options['vendor'] ) ? $options['vendor'] : 'all';
		$target_category    = isset( $options['category'] ) ? $options['category'] : 'all';
		$categories_to_sync = ( $target_category === 'all' )
			? array( 'cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet', 'case_fan' )
			: array( $target_category );

		$vendors = ( $target_vendor_slug === 'all' )
			? Vendor::get_all( true )
			: array_filter( array( Vendor::find_by_slug( $target_vendor_slug ) ) );

		$report = array(
			'started_at'            => current_time( 'mysql' ),
			'vendors_processed'     => count( $vendors ),
			'total_items_fetched'   => 0,
			'components_processed'  => 0,
			'prices_updated'        => 0,
			'posts_synced'          => 0,
			'touched_component_ids' => array(),
			'errors'                => array(),
		);

		$this->emit( $logger, 'info', "Starting sync cycle across " . count( $vendors ) . " active retailers...", $report );

		$delta_only = ! empty( $options['delta_only'] );

		foreach ( $vendors as $vendor ) {
			$adapter = $this->get_adapter_instance( $vendor );
			if ( ! $adapter ) {
				$msg = "No adapter found for vendor: {$vendor->vendor_slug}";
				$report['errors'][] = $msg;
				$this->emit( $logger, 'warning', $msg, $report );
				continue;
			}

			$this->emit( $logger, 'info', "=== Connecting to {$vendor->vendor_name} (" . ( $delta_only ? "Delta / Incremental Mode" : "Full Catalog Mode" ) . ") ===", $report );

			// Targeted search for Amazon India across existing DB components
			if ( $vendor->vendor_slug === 'amazon-in' || $vendor->vendor_slug === 'amazon' ) {
				foreach ( $categories_to_sync as $cat ) {
					try {
						$amz_page = 1;
						$amz_has_more = true;
						while ( $amz_has_more && $amz_page <= 500 ) {
							$res = $this->sync_amazon_page( $vendor, $cat, $amz_page, $delta_only );
							if ( ! empty( $res['logs'] ) ) {
								foreach ( $res['logs'] as $l ) {
									$this->emit( $logger, $l['level'], $l['message'], $report );
								}
							}
							$report['total_items_fetched']  += ( $res['items_count'] ?? 0 );
							$report['prices_updated']       += ( $res['prices_saved'] ?? 0 );
							$report['components_processed'] += ( $res['components'] ?? 0 );
							$amz_has_more = ! empty( $res['has_more'] );
							$amz_page++;
						}
					} catch ( \Exception $e ) {
						$err = "Error syncing Amazon India / {$cat}: " . $e->getMessage();
						$report['errors'][] = $err;
						$this->emit( $logger, 'error', $err, $report );
					}
				}
				$vendor->update_last_sync();
				$this->emit( $logger, 'success', "Completed Amazon India targeted search across existing database components.", $report );
				continue;
			}

			foreach ( $categories_to_sync as $cat ) {
				try {
					$page = 1;
					$max_pages = 50; // Comprehensive pagination across all vendor catalog pages
					$seen_page_hashes = array();

					while ( $page <= $max_pages ) {
						$this->emit( $logger, 'debug', "Scraping {$vendor->vendor_name} » category: " . strtoupper( $cat ) . " (Page {$page})...", $report );

						$raw_items = $adapter->fetch_products( $cat, $page );
						if ( empty( $raw_items ) ) {
							$this->emit( $logger, 'debug', "No further items found for {$vendor->vendor_name} » {$cat} (Page {$page}).", $report );
							break;
						}

						// Detect page loop repetition
						$page_urls = array_map( function( $it ) { return $it['url'] ?? ( $it['title'] ?? '' ); }, $raw_items );
						$page_hash = md5( implode( '|', $page_urls ) );
						if ( isset( $seen_page_hashes[ $page_hash ] ) ) {
							$this->emit( $logger, 'debug', "Duplicate page detected at Page {$page}. End of catalog reached for {$vendor->vendor_name} [{$cat}].", $report );
							break;
						}
						$seen_page_hashes[ $page_hash ] = true;

						$report['total_items_fetched'] += count( $raw_items );
						$this->emit( $logger, 'info', "Fetched " . count( $raw_items ) . " product listings from {$vendor->vendor_name} [{$cat}] (Page {$page}). Processing matches & prices...", $report );

						foreach ( $raw_items as $item ) {
							// Skip Out of Stock items
							if ( empty( $item['in_stock'] ) || ( isset( $item['stock_status'] ) && $item['stock_status'] === 'out_of_stock' ) ) {
								$this->emit( $logger, 'debug', "[{$vendor->vendor_name}] Skipped Out-of-Stock: \"{$item['title']}\"", $report );
								continue;
							}

							$sync_res = $this->sync_single_item( $item, $vendor, $delta_only );
							if ( $sync_res && ! empty( $sync_res['component_id'] ) ) {
								if ( empty( $sync_res['unchanged'] ) ) {
									$report['touched_component_ids'][ $sync_res['component_id'] ] = true;
									$report['prices_updated']++;
									$report['components_processed'] = count( $report['touched_component_ids'] );

									$stock_label   = 'In Stock';
									$price_val     = isset( $item['price'] ) ? floatval( $item['price'] ) : 0.0;
									$price_display = ( $price_val > 0 ) ? '₹' . number_format( $price_val, 2 ) : 'NA';
									$sku_display   = ! empty( $item['sku'] ) ? " [SKU: {$item['sku']}]" : '';
									
									$this->emit( $logger, 'match', "[{$vendor->vendor_name}] Matched & Saved: \"{$item['title']}\"{$sku_display} @ {$price_display} ({$stock_label})", $report );
								} else {
									$this->emit( $logger, 'debug', "[{$vendor->vendor_name}] Unchanged: \"{$item['title']}\"", $report );
								}
							}
						}

						$page++;
					}
				} catch ( \Exception $e ) {
					$err = "Error syncing {$vendor->vendor_slug} / {$cat}: " . $e->getMessage();
					$report['errors'][] = $err;
					$this->emit( $logger, 'error', $err, $report );
				}
			}

			$vendor->update_last_sync();
			$this->emit( $logger, 'success', "Completed scrape cycle for {$vendor->vendor_name}.", $report );
		}

		$touched_ids = array_keys( $report['touched_component_ids'] );
		$report['components_processed'] = count( $touched_ids );

		$report['completed_at'] = current_time( 'mysql' );
		$this->emit( $logger, 'finish', "HWsync process finished successfully! Total scraped: {$report['total_items_fetched']}, Prices saved/updated: {$report['prices_updated']}, Components in DB: {$report['components_processed']}.", $report );

		return $report;
	}

	protected function emit( $logger, $level, $message, $report = array() ) {
		if ( is_callable( $logger ) ) {
			call_user_func( $logger, $level, $message, array(
				'total_items' => $report['total_items_fetched'] ?? 0,
				'components'  => $report['components_processed'] ?? 0,
				'prices'      => $report['prices_updated'] ?? 0,
				'posts'       => $report['posts_synced'] ?? 0,
				'timestamp'   => current_time( 'H:i:s' ),
			) );
		}
	}

	/**
	 * Process a single raw item: match/create component, then upsert vendor price.
	 *
	 * @param array $item
	 * @param Vendor $vendor
	 * @param bool $delta_only
	 * @return array
	 */
	public function sync_single_item( $item, Vendor $vendor, $delta_only = false, $explicit_component_id = null ) {
		// Skip Out of Stock items
		if ( empty( $item['in_stock'] ) || ( isset( $item['stock_status'] ) && $item['stock_status'] === 'out_of_stock' ) ) {
			return null;
		}

		// 1. Match or Create Canonical Component (Amazon sync only matches to existing DB components)
		if ( ! empty( $explicit_component_id ) ) {
			$component = Component::find_by_id( $explicit_component_id );
		} else {
			$is_amazon = (
				$vendor->vendor_slug === 'amazon-in' ||
				$vendor->vendor_slug === 'amazon' ||
				( isset( $item['raw_data']['vendor'] ) && strpos( $item['raw_data']['vendor'], 'amazon' ) !== false )
			);
			$create_if_missing = ! $is_amazon;

			$component = Matching_Engine::match_or_create_component( $item, $create_if_missing );
		}

		if ( ! $component || empty( $component->id ) ) {
			return null;
		}

		// 2. Insert or Update Vendor Price Record
		$vendor_price = Vendor_Price::find_by_component_and_vendor( $component->id, $vendor->id );
		$is_new = empty( $vendor_price );
		$price_changed = false;
		$price_val = isset( $item['price'] ) && is_numeric( $item['price'] ) ? floatval( $item['price'] ) : 0.0;

		if ( ! $is_new ) {
			$old_price = floatval( $vendor_price->price );
			$old_stock = (bool) $vendor_price->is_in_stock;
			$new_stock = ! empty( $item['in_stock'] );

			if ( abs( $old_price - $price_val ) > 0.01 || $old_stock !== $new_stock ) {
				$price_changed = true;
			}
		}

		if ( $delta_only && ! $is_new && ! $price_changed ) {
			$vendor_price->last_checked_at = current_time( 'mysql' );
			$vendor_price->save();
			return array(
				'component_id'    => $component->id,
				'vendor_price_id' => $vendor_price->id,
				'unchanged'       => true,
			);
		}

		if ( ! $vendor_price ) {
			$vendor_price = new Vendor_Price( array(
				'component_id' => $component->id,
				'vendor_id'    => $vendor->id,
			) );
		}

		$vendor_price->vendor_product_title = isset( $item['title'] ) ? $item['title'] : '';
		$vendor_price->product_url          = isset( $item['url'] ) ? $item['url'] : '';
		$vendor_price->price                = $price_val;
		$vendor_price->original_price       = isset( $item['original_price'] ) && is_numeric( $item['original_price'] ) ? floatval( $item['original_price'] ) : null;
		$vendor_price->is_in_stock          = 1;
		$vendor_price->stock_status         = 'in_stock';
		$vendor_price->vendor_sku           = isset( $item['sku'] ) ? $item['sku'] : null;
		
		// If price is missing/zero for an in-stock component, mark formatted price as 'NA'
		if ( $price_val <= 0 ) {
			$raw_data = isset( $item['raw_data'] ) && is_array( $item['raw_data'] ) ? $item['raw_data'] : array();
			$raw_data['display_price'] = 'NA';
			$vendor_price->raw_data_json = $raw_data;
		} else {
			$vendor_price->raw_data_json = isset( $item['raw_data'] ) ? $item['raw_data'] : null;
		}

		$vendor_price->save();

		return array(
			'component_id'    => $component->id,
			'vendor_price_id' => $vendor_price->id,
			'unchanged'       => false,
		);
	}

	public function sync_page( $vendor_slug, $category, $page = 1, $delta_only = false ) {
		$vendor = Vendor::find_by_slug( $vendor_slug );
		if ( ! $vendor ) {
			return array(
				'success'  => false,
				'message'  => "Vendor not found: {$vendor_slug}",
				'has_more' => false,
				'logs'     => array( array( 'level' => 'error', 'message' => "Vendor not found: {$vendor_slug}" ) ),
			);
		}

		// Dedicated Component-Driven Targeted Search for Amazon India
		if ( $vendor_slug === 'amazon-in' || $vendor_slug === 'amazon' ) {
			return $this->sync_amazon_page( $vendor, $category, $page, $delta_only );
		}

		$adapter = $this->get_adapter_instance( $vendor );
		if ( ! $adapter ) {
			return array(
				'success'  => false,
				'message'  => "Adapter not found for {$vendor_slug}",
				'has_more' => false,
				'logs'     => array( array( 'level' => 'error', 'message' => "Adapter not found for {$vendor_slug}" ) ),
			);
		}

		$logs = array();
		$logs[] = array( 'level' => 'debug', 'message' => "Scraping {$vendor->vendor_name} » " . strtoupper( $category ) . " (Page {$page})..." );

		try {
			$raw_items = $adapter->fetch_products( $category, $page );
		} catch ( \Exception $e ) {
			$logs[] = array( 'level' => 'error', 'message' => "Scraping error on {$vendor->vendor_name} [{$category} Page {$page}]: " . $e->getMessage() );
			return array(
				'success'      => false,
				'has_more'     => false,
				'items_count'  => 0,
				'prices_saved' => 0,
				'components'   => 0,
				'posts_synced' => 0,
				'logs'         => $logs,
			);
		}

		if ( empty( $raw_items ) ) {
			$logs[] = array( 'level' => 'info', 'message' => "End of catalog reached for {$vendor->vendor_name} » {$category} (Page {$page})." );
			return array(
				'success'      => true,
				'has_more'     => false,
				'items_count'  => 0,
				'prices_saved' => 0,
				'components'   => 0,
				'posts_synced' => 0,
				'logs'         => $logs,
			);
		}

		$logs[] = array( 'level' => 'info', 'message' => "Fetched " . count( $raw_items ) . " product listings from {$vendor->vendor_name} [{$category}] (Page {$page})." );

		$touched_ids = array();
		$prices_saved = 0;
		$skipped_oos = 0;

		foreach ( $raw_items as $item ) {
			if ( empty( $item['in_stock'] ) || ( isset( $item['stock_status'] ) && $item['stock_status'] === 'out_of_stock' ) ) {
				$skipped_oos++;
				$logs[] = array( 'level' => 'debug', 'message' => "[{$vendor->vendor_name}] Skipped Out-of-Stock: \"{$item['title']}\"" );
				continue;
			}

			$sync_res = $this->sync_single_item( $item, $vendor, $delta_only );
			if ( $sync_res && ! empty( $sync_res['component_id'] ) ) {
				if ( empty( $sync_res['unchanged'] ) ) {
					$touched_ids[ $sync_res['component_id'] ] = true;
					$prices_saved++;

					$price_val     = isset( $item['price'] ) ? floatval( $item['price'] ) : 0.0;
					$price_display = ( $price_val > 0 ) ? '₹' . number_format( $price_val, 2 ) : 'NA';
					$sku_display   = ! empty( $item['sku'] ) ? " [SKU: {$item['sku']}]" : '';
					
					$logs[] = array( 'level' => 'match', 'message' => "[{$vendor->vendor_name}] Matched & Saved: \"{$item['title']}\"{$sku_display} @ {$price_display}" );
				} else {
					$logs[] = array( 'level' => 'debug', 'message' => "[{$vendor->vendor_name}] Unchanged: \"{$item['title']}\"" );
				}
			}
		}

		$vendor->update_last_sync();

		// Has more pages if we received full batch and page < 50
		$has_more = ( count( $raw_items ) >= 10 && $page < 50 );

		return array(
			'success'      => true,
			'has_more'     => $has_more,
			'items_count'  => count( $raw_items ),
			'prices_saved' => $prices_saved,
			'components'   => count( $touched_ids ),
			'posts_synced' => 0,
			'logs'         => $logs,
		);
	}

	/**
	 * Targeted component-driven Amazon India sync across existing database records.
	 *
	 * @param Vendor $vendor
	 * @param string $category
	 * @param int $page
	 * @param bool $delta_only
	 * @return array
	 */
	public function sync_amazon_page( Vendor $vendor, $category, $page = 1, $delta_only = false ) {
		$adapter = $this->get_adapter_instance( $vendor );
		$per_page = 5;
		$offset = ( $page - 1 ) * $per_page;

		$args = array(
			'limit'  => $per_page,
			'offset' => $offset,
		);
		if ( ! empty( $category ) && $category !== 'all' ) {
			$args['category'] = $category;
		}

		$components = Component::get_all( $args );
		$total_count = Component::count( ! empty( $category ) && $category !== 'all' ? array( 'category' => $category ) : array() );

		$logs = array();
		if ( empty( $components ) ) {
			$logs[] = array( 'level' => 'info', 'message' => "Completed Amazon India search across all existing components in category [" . strtoupper( $category ) . "]." );
			return array(
				'success'      => true,
				'has_more'     => false,
				'items_count'  => 0,
				'prices_saved' => 0,
				'components'   => 0,
				'posts_synced' => 0,
				'logs'         => $logs,
			);
		}

		$logs[] = array( 'level' => 'debug', 'message' => "Targeted Amazon Search » " . strtoupper( $category ) . " (Components " . ( $offset + 1 ) . " to " . ( $offset + count( $components ) ) . " of {$total_count})..." );

		$prices_saved = 0;
		$touched_ids = array();

		foreach ( $components as $component ) {
			$search_term = trim( ( ! empty( $component->brand ) && stripos( $component->model_name, $component->brand ) === false ? $component->brand . ' ' : '' ) . $component->model_name );
			$search_query = ( ! empty( $component->mpn ) && strlen( $component->mpn ) >= 5 ) ? $component->mpn : $search_term;

			$cards = array();
			if ( method_exists( $adapter, 'search_component_on_amazon' ) ) {
				$cards = $adapter->search_component_on_amazon( $search_query, $component->category );
				// If no cards found with MPN, fallback to Search Term
				if ( empty( $cards ) && $search_query !== $search_term ) {
					$cards = $adapter->search_component_on_amazon( $search_term, $component->category );
				}
				// If still empty, search model name alone
				if ( empty( $cards ) && $component->model_name !== $search_term ) {
					$cards = $adapter->search_component_on_amazon( $component->model_name, $component->category );
				}
			}

			$matched_item = null;
			if ( ! empty( $cards ) && is_array( $cards ) ) {
				foreach ( $cards as $card ) {
					if ( empty( $card['in_stock'] ) || empty( $card['price'] ) || floatval( $card['price'] ) <= 0 ) {
						continue;
					}
					// 1. Direct hardware comparison via Matching Engine
					$cand_comp = Matching_Engine::match_or_create_component( $card, false );
					if ( $cand_comp && $cand_comp->id === $component->id ) {
						$matched_item = $card;
						break;
					}

					// 2. Direct MPN matching
					$card_title = $card['title'];
					if ( ! empty( $component->mpn ) && strlen( $component->mpn ) >= 5 && stripos( $card_title, $component->mpn ) !== false ) {
						$matched_item = $card;
						break;
					}

					// 3. Core hardware identity matching (e.g. RYZEN 5 5500GT, CORE I3-12100F, RTX 4070 SUPER)
					$comp_core = Matching_Engine::extract_core_hardware_id( $component->model_name, $component->category );
					$card_core = Matching_Engine::extract_core_hardware_id( $card_title, $component->category );
					if ( ! empty( $comp_core ) && ! empty( $card_core ) && strcasecmp( $comp_core, $card_core ) === 0 ) {
						if ( empty( $component->brand ) || stripos( $card_title, $component->brand ) !== false || ( ! empty( $card['brand'] ) && strcasecmp( $component->brand, $card['brand'] ) === 0 ) ) {
							$matched_item = $card;
							break;
						}
					}

					// 4. Clean model name direct match
					$norm_model = preg_replace( '/[^\w\d]/', '', strtolower( $component->model_name ) );
					$norm_card  = preg_replace( '/[^\w\d]/', '', strtolower( $card_title ) );
					if ( strlen( $norm_model ) >= 6 && strpos( $norm_card, $norm_model ) !== false ) {
						$matched_item = $card;
						break;
					}
				}
			}

			if ( $matched_item ) {
				$sync_res = $this->sync_single_item( $matched_item, $vendor, $delta_only, $component->id );
				if ( $sync_res && ! empty( $sync_res['component_id'] ) ) {
					if ( empty( $sync_res['unchanged'] ) ) {
						$touched_ids[ $sync_res['component_id'] ] = true;
						$prices_saved++;

						$price_val     = floatval( $matched_item['price'] );
						$price_display = '₹' . number_format( $price_val, 2 );
						$asin_display  = ! empty( $matched_item['sku'] ) ? " [ASIN: {$matched_item['sku']}]" : '';

						$logs[] = array( 'level' => 'match', 'message' => "[Amazon India] Matched & Saved: #{$component->id} \"{$component->brand} {$component->model_name}\"{$asin_display} @ {$price_display}" );
					} else {
						$logs[] = array( 'level' => 'debug', 'message' => "[Amazon India] Unchanged: #{$component->id} \"{$component->brand} {$component->model_name}\"" );
					}
				}
			} else {
				$logs[] = array( 'level' => 'debug', 'message' => "[Amazon India] No matching listing found on Amazon for #{$component->id} \"{$component->brand} {$component->model_name}\"" );
			}
		}

		$vendor->update_last_sync();

		$has_more = ( $offset + count( $components ) ) < $total_count;

		return array(
			'success'      => true,
			'has_more'     => $has_more,
			'items_count'  => count( $components ),
			'prices_saved' => $prices_saved,
			'components'   => count( $touched_ids ),
			'posts_synced' => 0,
			'logs'         => $logs,
		);
	}

	public function get_adapter_instance( Vendor $vendor ) {
		$sync_method = $vendor->sync_method ?: 'curl_html';
		$cfg = $vendor->get_config();
		$endpoints = isset( $cfg['endpoints'] ) ? $cfg['endpoints'] : array();
		$has_custom_endpoints = ! empty( array_filter( $endpoints ) );

		// If vendor uses standard native class and method is curl_html and no custom endpoints override
		$native_class = ! empty( $vendor->adapter_class ) && class_exists( $vendor->adapter_class )
			? $vendor->adapter_class
			: ( isset( $this->adapter_map[ $vendor->vendor_slug ] ) ? $this->adapter_map[ $vendor->vendor_slug ] : null );

		if ( $native_class && class_exists( $native_class ) && $sync_method === 'curl_html' && ! $has_custom_endpoints ) {
			return new $native_class();
		}

		// Dynamically instantiate Configurable_Vendor_Adapter for all custom sync methods & endpoints
		return new \HWsync\Vendors\Configurable_Vendor_Adapter(
			$vendor->vendor_slug,
			$vendor->vendor_name,
			$vendor->base_url,
			$sync_method,
			$endpoints
		);
	}
}
