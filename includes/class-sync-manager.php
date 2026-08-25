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
	 * @return array Sync report
	 */
	public function run_sync( $options = array() ) {
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

		foreach ( $vendors as $vendor ) {
			$adapter = $this->get_adapter_instance( $vendor );
			if ( ! $adapter ) {
				$report['errors'][] = "No adapter found for vendor: {$vendor->vendor_slug}";
				continue;
			}

			foreach ( $categories_to_sync as $cat ) {
				try {
					$page = 1;
					$max_pages = 2; // Default limit per category per batch

					while ( $page <= $max_pages ) {
						$raw_items = $adapter->fetch_products( $cat, $page );
						if ( empty( $raw_items ) ) {
							break;
						}

						$report['total_items_fetched'] += count( $raw_items );

						foreach ( $raw_items as $item ) {
							$sync_res = $this->sync_single_item( $item, $vendor );
							if ( $sync_res && ! empty( $sync_res['component_id'] ) ) {
								$report['touched_component_ids'][ $sync_res['component_id'] ] = true;
								$report['prices_updated']++;
							}
						}

						$page++;
					}
				} catch ( \Exception $e ) {
					$report['errors'][] = "Error syncing {$vendor->vendor_slug} / {$cat}: " . $e->getMessage();
				}
			}

			$vendor->update_last_sync();
		}

		$touched_ids = array_keys( $report['touched_component_ids'] );
		$report['components_processed'] = count( $touched_ids );

		// Step 3: Trigger Post Sync Processor once component & price syncing is complete
		if ( ! empty( $touched_ids ) ) {
			$post_stats = Post_Sync_Processor::process_all( $touched_ids );
			$report['posts_synced'] = $post_stats['created'] + $post_stats['updated'];
			$report['post_stats']   = $post_stats;
		}

		$report['completed_at'] = current_time( 'mysql' );
		return $report;
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
