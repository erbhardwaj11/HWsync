<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCStudio_Adapter extends Abstract_Vendor_Adapter {

	public function __construct() {
		parent::__construct( 'pcstudio', 'PCStudio', 'https://www.pcstudio.in' );
	}

	public function get_category_endpoints() {
		return array(
			'cpu'         => '/product-category/processor/',
			'gpu'         => '/product-category/graphics-card/',
			'motherboard' => '/product-category/motherboard/',
			'ram'         => '/product-category/ram/',
			'storage'     => '/product-category/ssd/',
			'psu'         => '/product-category/smps/',
			'cooler'      => '/product-category/cpu-cooler/',
			'cabinet'     => '/product-category/cabinet/',
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : '/product-category/processor/';
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

		$nodes = $xpath->query( "//div[contains(@class, 'product-small')] | //li[contains(@class, 'product')]" );

		foreach ( $nodes as $node ) {
			$title_node = $xpath->query( ".//p[contains(@class, 'name')]/a | .//h2[contains(@class, 'woocommerce-loop-product__title')]/a | .//h2[contains(@class, 'woocommerce-loop-product__title')]", $node )->item( 0 );
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

			$price_node = $xpath->query( ".//span[contains(@class, 'price')]//ins//bdi | .//span[contains(@class, 'price')]//bdi | .//span[contains(@class, 'amount')]", $node )->item( 0 );
			$price_str = $price_node ? $price_node->textContent : '0';
			$price = self::clean_price( $price_str );

			$del_node = $xpath->query( ".//span[contains(@class, 'price')]//del//bdi", $node )->item( 0 );
			$old_price = $del_node ? self::clean_price( $del_node->textContent ) : null;

			$oos_node = $xpath->query( ".//span[contains(@class, 'out-of-stock')] | .//p[contains(@class, 'stock') and contains(@class, 'out-of-stock')]", $node )->item( 0 );
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
