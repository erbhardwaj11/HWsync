<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheITDepot_Adapter extends Abstract_Vendor_Adapter {

	public function __construct() {
		parent::__construct( 'theitdepot', 'The IT Depot', 'https://www.theitdepot.com' );
	}

	public function get_category_endpoints() {
		return array(
			'cpu'         => '/Processor?fq=1',
			'gpu'         => '/Graphic_Card?fq=1',
			'motherboard' => '/Motherboard?fq=1',
			'ram'         => '/Memory?fq=1',
			'storage'     => '/Solid_State_Drive_(SSD)?fq=1',
			'psu'         => '/Power_Supply%20(PSU)?fq=1',
			'cooler'      => '/Cooling_system?fq=1',
			'cabinet'     => '/Cabinet%20(Case)?fq=1',
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : '/Graphic_Card?fq=1';

		if ( $page > 1 ) {
			$connector = ( strpos( $path, '?' ) !== false ) ? '&' : '?';
			$url = $this->base_url . $path . $connector . 'page=' . intval( $page );
		} else {
			$url = $this->base_url . $path;
		}

		$headers = array(
			'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
			'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Referer'    => 'https://www.theitdepot.com/',
		);

		$res = $this->make_request( $url, $headers );
		if ( ! $res['success'] ) {
			return array();
		}

		return $this->parse_html( $res['body'], $category );
	}

	public function parse_html( $html, $category = '' ) {
		$items = array();
		if ( empty( $html ) ) {
			return $items;
		}

		// Match Journal 3 / OpenCart product cards
		$cards = array();
		if ( preg_match_all( '/<div[^>]*class=["\'][^"\']*product-layout[^"\']*["\'][^>]*>(.*?)<div class="cart-group">/is', $html, $matches ) ) {
			$cards = $matches[1];
		} else {
			$raw_blocks = explode( 'class="product-layout', $html );
			array_shift( $raw_blocks );
			$cards = $raw_blocks;
		}

		foreach ( $cards as $block ) {
			// Product Title & URL
			if ( ! preg_match( '/<div[^>]*class=["\']name["\'][^>]*>\s*<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $block, $m_link ) ) {
				if ( ! preg_match( '/<h4[^>]*>\s*<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $block, $m_link ) ) {
					continue;
				}
			}

			$url   = trim( $m_link[1] );
			$title = trim( strip_tags( html_entity_decode( $m_link[2], ENT_QUOTES, 'UTF-8' ) ) );

			if ( empty( $title ) || empty( $url ) ) {
				continue;
			}

			// Resolving absolute URL if needed
			if ( strpos( $url, 'http' ) !== 0 ) {
				$url = $this->base_url . '/' . ltrim( $url, '/' );
			}

			// Extract Sale Price (.price-new > .price-normal > .price)
			$raw_price = '';
			if ( preg_match( '/<span[^>]*class=["\'][^"\']*\bprice-new\b[^"\']*["\'][^>]*>(.*?)<\/span>/is', $block, $m_price ) ) {
				$raw_price = $m_price[1];
			} elseif ( preg_match( '/<span[^>]*class=["\'][^"\']*\bprice-normal\b[^"\']*["\'][^>]*>(.*?)<\/span>/is', $block, $m_price ) ) {
				$raw_price = $m_price[1];
			} elseif ( preg_match( '/<span[^>]*class=["\'][^"\']*\bprice\b[^"\']*["\'][^>]*>(.*?)<\/span>/is', $block, $m_price ) ) {
				$raw_price = $m_price[1];
			}

			// Extract Old MRP Price (.price-old)
			$raw_old_price = '';
			if ( preg_match( '/<span[^>]*class=["\'][^"\']*\bprice-old\b[^"\']*["\'][^>]*>(.*?)<\/span>/is', $block, $m_old_price ) ) {
				$raw_old_price = $m_old_price[1];
			}

			$clean_price = ! empty( $raw_price ) ? self::clean_price( strip_tags( $raw_price ) ) : 0.0;
			$clean_old_price = ! empty( $raw_old_price ) ? self::clean_price( strip_tags( $raw_old_price ) ) : null;

			if ( $clean_price <= 0 ) {
				$extracted_prices = self::extract_clean_prices( $block );
				$clean_price = $extracted_prices['price'];
				if ( $clean_old_price === null ) {
					$clean_old_price = $extracted_prices['original_price'];
				}
			}

			// Image URL
			$image_url = '';
			if ( preg_match( '/data-src=["\']([^"\']+)["\']/i', $block, $m_img ) ) {
				$image_url = trim( $m_img[1] );
			} elseif ( preg_match( '/src=["\'](https?:\/\/[^"\']+)["\']/i', $block, $m_img ) ) {
				$image_url = trim( $m_img[1] );
			}

			// Stock Status
			$is_in_stock = 1;
			if ( preg_match( '/out[-_\s]*of[-_\s]*stock|stock-out|btn-disabled/i', $block ) ) {
				$is_in_stock = 0;
			}

			$sku = self::extract_sku_from_title( $title );

			$items[] = array(
				'title'          => $title,
				'url'            => $url,
				'price'          => $clean_price,
				'original_price' => $clean_old_price,
				'sku'            => $sku,
				'in_stock'       => $is_in_stock,
				'stock_status'   => $is_in_stock ? 'in_stock' : 'out_of_stock',
				'category'       => $category,
				'image_url'      => $image_url,
				'raw_data'       => array(
					'vendor'        => $this->vendor_slug,
					'raw_price'     => $raw_price,
					'raw_old_price' => $raw_old_price,
					'image_url'     => $image_url,
				),
			);
		}

		return $items;
	}
}
