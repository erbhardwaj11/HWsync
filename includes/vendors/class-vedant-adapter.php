<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vedant_Adapter extends Abstract_Vendor_Adapter {

	public function __construct() {
		parent::__construct( 'vedant', 'Vedant Computers', 'https://www.vedantcomputers.com' );
	}

	public function get_category_endpoints() {
		return array(
			'cpu'         => '/processors',
			'gpu'         => '/graphics-cards',
			'motherboard' => '/motherboards',
			'ram'         => '/memory-ram',
			'storage'     => '/storage-devices',
			'psu'         => '/power-supply',
			'cooler'      => '/cpu-cooler',
			'cabinet'     => '/cabinet',
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : '/processors';
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

		$nodes = $xpath->query( "//div[contains(@class, 'product-thumb')] | //div[contains(@class, 'product-layout')]" );

		foreach ( $nodes as $node ) {
			$title_node = $xpath->query( ".//div[contains(@class, 'caption')]//h4/a | .//h4/a", $node )->item( 0 );
			if ( ! $title_node ) {
				continue;
			}

			$title = trim( $title_node->textContent );
			$url = $title_node->getAttribute( 'href' );

			$price_node = $xpath->query( ".//span[contains(@class, 'price-new')] | .//p[contains(@class, 'price')]", $node )->item( 0 );
			$price_str = $price_node ? $price_node->textContent : '0';
			$price = self::clean_price( $price_str );

			$old_price_node = $xpath->query( ".//span[contains(@class, 'price-old')]", $node )->item( 0 );
			$old_price = $old_price_node ? self::clean_price( $old_price_node->textContent ) : null;

			$stock_tag = $xpath->query( ".//span[contains(@class, 'stock-status')] | .//div[contains(@class, 'out-of-stock')]", $node )->item( 0 );
			$in_stock = true;
			$stock_status = 'in_stock';
			if ( $stock_tag && stripos( $stock_tag->textContent, 'out of stock' ) !== false ) {
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
