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

		foreach ( $vendors as $vendor ) {
			$adapter = $this->get_adapter_instance( $vendor );
			if ( ! $adapter ) {
				$msg = "No adapter found for vendor: {$vendor->vendor_slug}";
				$report['errors'][] = $msg;
				$this->emit( $logger, 'warning', $msg, $report );
				continue;
			}

			$this->emit( $logger, 'info', "Connecting to {$vendor->vendor_name} ({$vendor->base_url})...", $report );

			foreach ( $categories_to_sync as $cat ) {
				try {
					$page = 1;
					$max_pages = 2; // Limit per category per batch

					while ( $page <= $max_pages ) {
						$this->emit( $logger, 'debug', "Scraping {$vendor->vendor_name} » category: " . strtoupper( $cat ) . " (Page {$page})...", $report );

						$raw_items = $adapter->fetch_products( $cat, $page );
						if ( empty( $raw_items ) ) {
							$this->emit( $logger, 'debug', "No further items found for {$vendor->vendor_name} » {$cat} (Page {$page}).", $report );
							break;
						}

						$report['total_items_fetched'] += count( $raw_items );
						$this->emit( $logger, 'info', "Fetched " . count( $raw_items ) . " products from {$vendor->vendor_name} [{$cat}]", $report );

						foreach ( $raw_items as $item ) {
							$sync_res = $this->sync_single_item( $item, $vendor );
							if ( $sync_res && ! empty( $sync_res['component_id'] ) ) {
								$report['touched_component_ids'][ $sync_res['component_id'] ] = true;
								$report['prices_updated']++;
								$report['components_processed'] = count( $report['touched_component_ids'] );

								$stock_label = ! empty( $item['in_stock'] ) ? 'In Stock' : 'Out of Stock';
								$this->emit( $logger, 'match', "Matched: \"{$item['title']}\" -> ₹" . number_format( $item['price'], 2 ) . " ({$stock_label})", $report );
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
			$this->emit( $logger, 'success', "Completed scrape for {$vendor->vendor_name}.", $report );
		}

		$touched_ids = array_keys( $report['touched_component_ids'] );
		$report['components_processed'] = count( $touched_ids );

		// Step 3: Trigger Post Sync Processor once component & price syncing is complete
		if ( ! empty( $touched_ids ) ) {
			$this->emit( $logger, 'info', "Processing post-sync: Publishing and updating WordPress component posts...", $report );
			$post_stats = Post_Sync_Processor::process_all( $touched_ids );
			$report['posts_synced'] = $post_stats['created'] + $post_stats['updated'];
			$report['post_stats']   = $post_stats;
			$this->emit( $logger, 'success', "Post sync complete! Created: {$post_stats['created']}, Updated: {$post_stats['updated']}, Skipped: {$post_stats['skipped']}", $report );
		} else {
			$this->emit( $logger, 'info', "No new components to sync to WordPress posts.", $report );
		}

		$report['completed_at'] = current_time( 'mysql' );
		$this->emit( $logger, 'finish', "HWsync process finished successfully! Total scraped: {$report['total_items_fetched']}, Prices saved: {$report['prices_updated']}, Posts synced: {$report['posts_synced']}.", $report );

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
	 * @return array
	 */
	public function sync_single_item( $item, Vendor $vendor ) {
		// 1. Match or Create Canonical Component
		$component = Matching_Engine::match_or_create_component( $item );
		if ( ! $component || empty( $component->id ) ) {
			return null;
		}

		// 2. Insert or Update Vendor Price Record
		$vendor_price = Vendor_Price::find_by_component_and_vendor( $component->id, $vendor->id );
		if ( ! $vendor_price ) {
			$vendor_price = new Vendor_Price( array(
				'component_id' => $component->id,
				'vendor_id'    => $vendor->id,
			) );
		}

		$vendor_price->vendor_product_title = isset( $item['title'] ) ? $item['title'] : '';
		$vendor_price->product_url          = isset( $item['url'] ) ? $item['url'] : '';
		$vendor_price->price                = isset( $item['price'] ) ? floatval( $item['price'] ) : 0.0;
		$vendor_price->original_price       = isset( $item['original_price'] ) ? floatval( $item['original_price'] ) : null;
		$vendor_price->is_in_stock          = ! empty( $item['in_stock'] ) ? 1 : 0;
		$vendor_price->stock_status         = isset( $item['stock_status'] ) ? $item['stock_status'] : 'in_stock';
		$vendor_price->vendor_sku           = isset( $item['sku'] ) ? $item['sku'] : null;
		$vendor_price->raw_data_json        = isset( $item['raw_data'] ) ? $item['raw_data'] : null;

		$vendor_price->save();

		return array(
			'component_id'    => $component->id,
			'vendor_price_id' => $vendor_price->id,
		);
	}

	protected function get_adapter_instance( Vendor $vendor ) {
		$class = ! empty( $vendor->adapter_class ) && class_exists( $vendor->adapter_class )
			? $vendor->adapter_class
			: ( isset( $this->adapter_map[ $vendor->vendor_slug ] ) ? $this->adapter_map[ $vendor->vendor_slug ] : null );

		if ( $class && class_exists( $class ) ) {
			return new $class();
		}
		return null;
	}
}
