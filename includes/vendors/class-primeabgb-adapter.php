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
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : '/buy-online-price-india/cpu-processor/';
		$url = $this->base_url . $path . ( $page > 1 ? 'page/' . intval( $page ) . '/' : '' );

		$res = $this->make_request( $url );
		if ( ! $res['success'] ) {
			return array();
		}

		return $this->parse_html( $res['body'], $category );
	}

	protected function parse_html( $html, $category ) {
		$items = array();
		if ( empty( $html ) ) {
			return $items;
		}

		$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
		$xpath = new \DOMXPath( $dom );

		$nodes = $xpath->query( "//li[contains(@class, 'product')] | //div[contains(@class, 'product-small')]" );

		foreach ( $nodes as $node ) {
			$title_node = $xpath->query( ".//h2[contains(@class, 'woocommerce-loop-product__title')] | .//p[contains(@class, 'name')]/a | .//h3/a", $node )->item( 0 );
			if ( ! $title_node ) {
				continue;
			}

			$title = trim( $title_node->textContent );
			$url = $title_node->hasAttribute( 'href' ) ? $title_node->getAttribute( 'href' ) : '';
			if ( empty( $url ) ) {
				$parent_link = $xpath->query( ".//a[contains(@class, 'woocommerce-LoopProduct-link')]", $node )->item( 0 );
				if ( $parent_link ) {
					$url = $parent_link->getAttribute( 'href' );
				}
			}

			$ins_node = $xpath->query( ".//span[contains(@class, 'price')]//ins//bdi | .//span[contains(@class, 'price')]//bdi | .//span[contains(@class, 'amount')]", $node )->item( 0 );
			$price_str = $ins_node ? $ins_node->textContent : '0';
			$price = self::clean_price( $price_str );

			$del_node = $xpath->query( ".//span[contains(@class, 'price')]//del//bdi", $node )->item( 0 );
			$old_price = $del_node ? self::clean_price( $del_node->textContent ) : null;

			// Out of stock
			$oos_node = $xpath->query( ".//span[contains(@class, 'out-of-stock')] | .//span[contains(@class, 'badge-out-of-stock')]", $node )->item( 0 );
			$in_stock = $oos_node ? false : true;
			$stock_status = $in_stock ? 'in_stock' : 'out_of_stock';

			if ( $price > 0 && ! empty( $title ) ) {
				$items[] = array(
					'title'          => $title,
					'url'            => $url,
					'price'          => $price,
					'original_price' => $old_price,
					'in_stock'       => $in_stock,
					'stock_status'   => $stock_status,
					'category'       => $category,
					'vendor_slug'    => $this->vendor_slug,
					'raw_data'       => array( 'raw_title' => $title, 'raw_price' => $price_str ),
				);
			}
		}

		return $items;
	}
}
