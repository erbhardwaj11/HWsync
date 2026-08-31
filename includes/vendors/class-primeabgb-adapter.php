<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PrimeABGB_Adapter extends Abstract_Vendor_Adapter {

	public function __construct() {
		parent::__construct( 'primeabgb', 'PrimeABGB', 'https://www.primeabgb.com' );
	}

	public function get_category_endpoints() {
		return array(
			'cpu'         => '/buy-online-price-india/cpu-processor/',
			'gpu'         => '/buy-online-price-india/graphic-cards-gpu/',
			'motherboard' => '/buy-online-price-india/motherboards/',
			'ram'         => '/buy-online-price-india/ram-memory/',
			'storage'     => '/buy-online-price-india/solid-state-drives-ssd/',
			'psu'         => '/buy-online-price-india/power-supplies-smps/',
			'cooler'      => '/buy-online-price-india/cpu-cooler-fan-heatsink/',
			'cabinet'     => '/buy-online-price-india/pc-cases-cabinets/',
			'case_fan'    => '/buy-online-price-india/chassis-fan/',
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : '/buy-online-price-india/cpu-processor/';
		$url = $this->base_url . $path . ( $page > 1 ? 'page/' . intval( $page ) . '/' : '' );

		$res = $this->make_request( $url );
		if ( ! $res['success'] || empty( $res['body'] ) ) {
			return array();
		}

		return $this->parse_html( $res['body'], $category );
	}

	public function parse_html( $html_content, $category ) {
		$items = array();
		if ( empty( $html_content ) ) {
			return $items;
		}

		// Strategy 1: GTM Tag Data Extraction (Highest fidelity)
		if ( preg_match_all( '/data-gtm4wp_product_data="([^"]+)"/', $html_content, $gtm_matches ) ) {
			foreach ( $gtm_matches[1] as $gtm_raw ) {
				$decoded = html_entity_decode( $gtm_raw, ENT_QUOTES, 'UTF-8' );
				$data = json_decode( $decoded, true );
				if ( is_array( $data ) ) {
					$title = isset( $data['item_name'] ) ? trim( $data['item_name'] ) : '';
					$prod_url = isset( $data['productlink'] ) ? trim( $data['productlink'] ) : '';
					$price = isset( $data['price'] ) ? floatval( $data['price'] ) : 0.0;
					$sku = isset( $data['sku'] ) ? trim( $data['sku'] ) : '';
					$in_stock = ( ! isset( $data['stockstatus'] ) || $data['stockstatus'] === 'instock' );

					if ( $price > 0 && ! empty( $title ) ) {
						$items[] = array(
							'title'          => $title,
							'url'            => $prod_url,
							'price'          => $price,
							'original_price' => null,
							'sku'            => $sku,
							'in_stock'       => $in_stock,
							'stock_status'   => $in_stock ? 'in_stock' : 'out_of_stock',
							'category'       => $category,
							'vendor_slug'    => $this->vendor_slug,
							'raw_data'       => array( 'raw_title' => $title, 'price' => $price, 'sku' => $sku ),
						);
					}
				}
			}
			if ( ! empty( $items ) ) {
				return $items;
			}
		}

		// Strategy 2: HTML Card parsing
		if ( preg_match_all( '/<div[^>]*class="[^"]*product-item[^"]*"[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/i', $html_content, $cards ) ) {
			foreach ( $cards[0] as $card_html ) {
				$title_m = preg_match( '/<h3[^>]*class="product-title"[^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $card_html, $tm );
				$price_data = self::extract_clean_prices( $card_html );
				$price = $price_data['price'];
				$orig_price = $price_data['original_price'];

				if ( $title_m && $price > 0 ) {
					$title = html_entity_decode( trim( $tm[2] ) );
					$url = $tm[1];
					$in_stock = ( stripos( $card_html, 'out-of-stock' ) === false );

					if ( ! empty( $title ) ) {
						$items[] = array(
							'title'          => $title,
							'url'            => $url,
							'price'          => $price,
							'original_price' => $orig_price,
							'sku'            => '',
							'in_stock'       => $in_stock,
							'stock_status'   => $in_stock ? 'in_stock' : 'out_of_stock',
							'category'       => $category,
							'vendor_slug'    => $this->vendor_slug,
							'raw_data'       => array( 'raw_title' => $title, 'price' => $price ),
						);
					}
				}
			}
		}

		return $items;
	}
}
