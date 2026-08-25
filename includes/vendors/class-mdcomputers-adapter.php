<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MDComputers_Adapter extends Abstract_Vendor_Adapter {

	public function __construct() {
		parent::__construct( 'mdcomputers', 'MDComputers', 'https://mdcomputers.in' );
	}

	public function get_category_endpoints() {
		return array(
			'cpu'         => '/processor',
			'gpu'         => '/graphics-card',
			'motherboard' => '/motherboard',
			'ram'         => '/memory',
			'storage'     => '/storage',
			'psu'         => '/power-supply',
			'cooler'      => '/cpu-cooler',
			'cabinet'     => '/cabinet',
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : '/processor';
		$url = $this->base_url . $path . '?page=' . intval( $page ) . '&limit=50';

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

		$nodes = $xpath->query( "//div[contains(@class, 'product-item-container') or contains(@class, 'product-grid-item') or contains(@class, 'product-thumb')]" );

		foreach ( $nodes as $node ) {
			$title_node = $xpath->query( ".//h4/a | .//h4[contains(@class, 'title')]/a | .//a[contains(@class, 'product-item-link')]", $node )->item( 0 );
			if ( ! $title_node ) {
				continue;
			}

			$title = trim( $title_node->textContent );
			$url = $title_node->getAttribute( 'href' );

			$price_node = $xpath->query( ".//span[contains(@class, 'price-new')] | .//span[contains(@class, 'price')]", $node )->item( 0 );
			$price_str = $price_node ? $price_node->textContent : '0';
			$price = self::clean_price( $price_str );

			$old_price_node = $xpath->query( ".//span[contains(@class, 'price-old')]", $node )->item( 0 );
			$old_price = $old_price_node ? self::clean_price( $old_price_node->textContent ) : null;

			// Out of stock detection
			$stock_btn = $xpath->query( ".//button[contains(@class, 'cart')] | .//span[contains(@class, 'out-of-stock')]", $node )->item( 0 );
			$in_stock = true;
			$stock_status = 'in_stock';
			if ( $stock_btn && stripos( $stock_btn->textContent, 'out of stock' ) !== false ) {
				$in_stock = false;
				$stock_status = 'out_of_stock';
			}

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
