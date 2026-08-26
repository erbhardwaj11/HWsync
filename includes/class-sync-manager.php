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
			? array( 'cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet' )
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
	public function sync_single_item( $item, Vendor $vendor, $delta_only = false ) {
		// Skip Out of Stock items
		if ( empty( $item['in_stock'] ) || ( isset( $item['stock_status'] ) && $item['stock_status'] === 'out_of_stock' ) ) {
			return null;
		}

		// 1. Match or Create Canonical Component
		$component = Matching_Engine::match_or_create_component( $item );
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

		// Real-time automatic mirror to PCSpecs Theme tables & category tables
		Post_Sync_Processor::sync_component_to_theme( $component );

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

	public function get_adapter_instance( Vendor $vendor ) {
		$class = ! empty( $vendor->adapter_class ) && class_exists( $vendor->adapter_class )
			? $vendor->adapter_class
			: ( isset( $this->adapter_map[ $vendor->vendor_slug ] ) ? $this->adapter_map[ $vendor->vendor_slug ] : null );

		if ( $class && class_exists( $class ) ) {
			return new $class();
		}

		// Fallback to dynamic Configurable_Vendor_Adapter
		$cfg = $vendor->get_config();
		$endpoints = isset( $cfg['endpoints'] ) ? $cfg['endpoints'] : array();

		return new \HWsync\Vendors\Configurable_Vendor_Adapter(
			$vendor->vendor_slug,
			$vendor->vendor_name,
			$vendor->base_url,
			$vendor->sync_method ?: 'curl_html',
			$endpoints
		);
	}
}
