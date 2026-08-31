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
			'case_fan'    => '/product-category/case-fan/',
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
		$parts = preg_split( '/<(?:li|div)[^>]*class="[^"]*(?:product\s+type-product|type-product\s+post-|product-small|product-item)[^"]*"/', $html_content );
		if ( count( $parts ) > 1 ) {
			array_shift( $parts ); // Skip preamble

			foreach ( $parts as $p ) {
				// URL
				$url = '';
				if ( preg_match( '/href="(https:\/\/www\.pcstudio\.in\/product\/[^"]+)"/i', $p, $um ) ||
				     preg_match( '/<a\s+href="([^"]+)"\s+class="[^"]*woocommerce-LoopProduct-link/i', $p, $um ) ||
				     preg_match( '/<h[23][^>]*>\s*<a\s+href="([^"]+)"/i', $p, $um ) ) {
					$url = $um[1];
				}

				// Title
				$title = '';
				if ( preg_match( '/title="([^"]+)"/i', $p, $tm ) ) {
					$title = $tm[1];
				} elseif ( preg_match( '/<h[23][^>]*>\s*<a[^>]*>([^<]+)<\/a>/i', $p, $tm ) ) {
					$title = $tm[1];
				} elseif ( preg_match( '/alt="([^"]+)"/i', $p, $tm ) ) {
					$title = $tm[1];
				}

				if ( ! empty( $title ) ) {
					$title = html_entity_decode( trim( strip_tags( $title ) ), ENT_QUOTES, 'UTF-8' );
				}

				// Price: Must prioritize sale price over MRP
				$price_data = self::extract_clean_prices( $p );
				$price      = $price_data['price'];
				$orig_price = $price_data['original_price'];

				// SKU
				$sku = '';
				if ( preg_match( '/data-product_sku="([^"]*)"/i', $p, $sm ) ) {
					$sku = trim( $sm[1] );
				}

				$in_stock = ( stripos( $p, 'out-of-stock' ) === false && stripos( $p, 'sold out' ) === false );

				if ( ! empty( $url ) && ! empty( $title ) && $price > 0 ) {
					$items[] = array(
						'title'          => $title,
						'url'            => $url,
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
		}

		return $items;
	}
}
