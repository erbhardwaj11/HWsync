<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EliteHubs_Adapter extends Abstract_Vendor_Adapter {

	public function __construct() {
		parent::__construct( 'elitehubs', 'EliteHubs', 'https://elitehubs.com' );
	}

	public function get_category_endpoints() {
		return array(
			'cpu'         => 'processor',
			'gpu'         => 'graphic-cards',
			'motherboard' => 'motherboard',
			'ram'         => 'ram',
			'storage'     => 'solid-state-drives',
			'psu'         => 'power-supply',
			'cooler'      => 'pc-coolers',
			'cabinet'     => 'pc-cabinet',
			'case_fan'    => 'case-fans',
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$collection = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : 'processor';
		
		// Use high-reliability Shopify REST collection endpoint
		$url = $this->base_url . '/collections/' . $collection . '/products.json?limit=50&page=' . intval( $page );
		$res = $this->make_request( $url, array( 'Accept' => 'application/json' ) );

		if ( $res['success'] && ! empty( $res['body'] ) ) {
			$json = json_decode( $res['body'], true );
			if ( is_array( $json ) && ! empty( $json['products'] ) ) {
				$items = array();
				foreach ( $json['products'] as $p ) {
					$title = isset( $p['title'] ) ? trim( $p['title'] ) : '';
					$handle = isset( $p['handle'] ) ? $p['handle'] : '';
					$variants = isset( $p['variants'] ) && is_array( $p['variants'] ) ? $p['variants'] : array();
					$variant = ! empty( $variants ) ? $variants[0] : array();
					
					$price = isset( $variant['price'] ) ? self::clean_price( $variant['price'] ) : 0.0;
					$orig_price = isset( $variant['compare_at_price'] ) ? self::clean_price( $variant['compare_at_price'] ) : null;
					$sku = isset( $variant['sku'] ) ? trim( $variant['sku'] ) : '';
					$in_stock = isset( $variant['available'] ) ? (bool) $variant['available'] : true;
					$prod_url = $this->base_url . '/products/' . $handle;

					if ( $price > 0 && ! empty( $title ) ) {
						$items[] = array(
							'title'          => $title,
							'url'            => $prod_url,
							'price'          => $price,
							'original_price' => $orig_price,
							'sku'            => $sku,
							'in_stock'       => $in_stock,
							'stock_status'   => $in_stock ? 'in_stock' : 'out_of_stock',
							'category'       => $category,
							'vendor_slug'    => $this->vendor_slug,
							'raw_data'       => array( 'raw_title' => $title, 'price' => $price, 'sku' => $sku ),
						);
					}
				}
				if ( ! empty( $items ) ) {
					return $items;
				}
			}
		}

		return array();
	}
}
