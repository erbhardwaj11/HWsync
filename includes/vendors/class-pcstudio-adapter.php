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

		// Split HTML by product block boundary
		$parts = preg_split( '/<li[^>]*class="[^"]*(?:product\s+type-product|type-product\s+post-)[^"]*"/', $html_content );
		if ( count( $parts ) <= 1 ) {
			$parts = preg_split( '/<div[^>]*class="[^"]*(?:product-small|product-item)[^"]*"/', $html_content );
		}

		if ( count( $parts ) > 1 ) {
			// Skip preamble
			array_shift( $parts );

			foreach ( $parts as $p ) {
				// URL
				$url = '';
				if ( preg_match( '/<a\s+href="([^"]+)"\s+class="[^"]*woocommerce-LoopProduct-link/i', $p, $um ) ||
				     preg_match( '/<h[23][^>]*>\s*<a\s+href="([^"]+)"/i', $p, $um ) ) {
					$url = $um[1];
				}

				// Title
				$title = '';
				if ( preg_match( '/<span\s+title="([^"]+)"/i', $p, $tm ) ) {
					$title = $tm[1];
				} elseif ( preg_match( '/alt="&lt;span title=&quot;([^&]+)&quot;/i', $p, $tm ) ) {
					$title = $tm[1];
				} elseif ( preg_match( '/<h[23][^>]*>\s*<a[^>]*>([^<]+)<\/a>/i', $p, $tm ) ) {
					$title = $tm[1];
				}

				// Price
				$price = 0.0;
				if ( preg_match( '/<ins[^>]*>[\s\S]*?<bdi>[\s\S]*?([\d,]+(?:\.\d+)?)<\/bdi>/i', $p, $pm ) ||
				     preg_match( '/<bdi>[\s\S]*?([\d,]+(?:\.\d+)?)<\/bdi>/i', $p, $pm ) ||
				     preg_match( '/Current price is:[\s\S]*?([\d,]+(?:\.\d+)?)/i', $p, $pm ) ) {
					$price = self::clean_price( $pm[1] );
				}

				// SKU
				$sku = '';
				if ( preg_match( '/data-product_sku="([^"]*)"/i', $p, $sm ) ) {
					$sku = trim( $sm[1] );
				}

				if ( ! empty( $url ) && ! empty( $title ) && $price > 0 ) {
					$items[] = array(
						'title'          => html_entity_decode( trim( $title ), ENT_QUOTES, 'UTF-8' ),
						'url'            => $url,
						'price'          => $price,
						'original_price' => null,
						'sku'            => $sku,
						'in_stock'       => true,
						'stock_status'   => 'in_stock',
						'category'       => $category,
						'vendor_slug'    => $this->vendor_slug,
						'raw_data'       => array( 'raw_title' => $title, 'price' => $price, 'sku' => $sku ),
					);
				}
			}
		}

		return $items;
	}
}
