<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amazon_Adapter extends Abstract_Vendor_Adapter {

	public function __construct() {
		parent::__construct( 'amazon-in', 'Amazon India', 'https://www.amazon.in' );
	}

	public function get_category_endpoints() {
		return array(
			'cpu'         => '/s?k=processor+cpu+desktop',
			'gpu'         => '/s?k=graphic+card+gpu',
			'motherboard' => '/s?k=motherboard+desktop',
			'ram'         => '/s?k=desktop+ram+ddr4+ddr5',
			'storage'     => '/s?k=internal+ssd+nvme+m2',
			'psu'         => '/s?k=power+supply+psu+smps',
			'cooler'      => '/s?k=cpu+cooler+aio+liquid',
			'cabinet'     => '/s?k=gaming+pc+cabinet+case',
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : '/s?k=processor+cpu+desktop';

		if ( $page > 1 ) {
			$connector = ( strpos( $path, '?' ) !== false ) ? '&' : '?';
			$url = $this->base_url . $path . $connector . 'page=' . intval( $page );
		} else {
			$url = $this->base_url . $path;
		}

		$headers = array(
			'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
			'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
			'Accept-Language' => 'en-IN,en-GB;q=0.9,en-US;q=0.8,en;q=0.7',
			'Referer'         => 'https://www.amazon.in/',
		);

		$res = $this->make_request( $url, $headers );
		if ( ! $res['success'] || empty( $res['body'] ) ) {
			return array();
		}

		return $this->parse_html( $res['body'], $category );
	}

	public function parse_html( $html, $category = '' ) {
		$items = array();
		if ( empty( $html ) ) {
			return $items;
		}

		// Split by search result components or data-asin cards
		$blocks = array();
		if ( preg_match_all( '/<div[^>]*data-component-type=["\']s-search-result["\'][^>]*>[\s\S]*?(?=<div[^>]*data-component-type=["\']s-search-result["\']|\Z)/i', $html, $matches ) ) {
			$blocks = $matches[0];
		} elseif ( preg_match_all( '/<div[^>]*data-asin=["\']([A-Z0-9]{10})["\'][^>]*>[\s\S]*?(?=<div[^>]*data-asin=|\Z)/i', $html, $matches ) ) {
			$blocks = $matches[0];
		}

		foreach ( $blocks as $block ) {
			// Extract ASIN
			$asin = '';
			if ( preg_match( '/data-asin=["\']([A-Z0-9]{10})["\']/i', $block, $m_asin ) ) {
				$asin = trim( $m_asin[1] );
			}

			// Extract Title
			$title = '';
			if ( preg_match( '/<h2[^>]*>[\s\S]*?<span[^>]*>(.*?)<\/span>/i', $block, $m_title ) ) {
				$title = trim( strip_tags( html_entity_decode( $m_title[1], ENT_QUOTES, 'UTF-8' ) ) );
			} elseif ( preg_match( '/<span[^>]*class=["\'][^"\']*\ba-text-normal\b[^"\']*["\'][^>]*>(.*?)<\/span>/i', $block, $m_title ) ) {
				$title = trim( strip_tags( html_entity_decode( $m_title[1], ENT_QUOTES, 'UTF-8' ) ) );
			}

			if ( empty( $title ) ) {
				continue;
			}

			// Build canonical URL
			if ( ! empty( $asin ) ) {
				$url = $this->base_url . '/dp/' . $asin;
			} elseif ( preg_match( '/<a[^>]*class=["\'][^"\']*a-link-normal[^"\']*["\'][^>]*href=["\']([^"\']+)["\']/i', $block, $m_url ) ) {
				$raw_href = $m_url[1];
				$clean_path = explode( '?', $raw_href )[0];
				$url = ( strpos( $clean_path, 'http' ) === 0 ) ? $clean_path : $this->base_url . '/' . ltrim( $clean_path, '/' );
			} else {
				continue;
			}

			// Extract Price
			$price = 0.0;
			if ( preg_match( '/class=["\']a-price-whole["\'][^>]*>([^<]+)</i', $block, $m_price ) ) {
				$price_str = preg_replace( '/[^\d.]/', '', $m_price[1] );
				$price = floatval( $price_str );
			} elseif ( preg_match( '/<span[^>]*class=["\']a-offscreen["\'][^>]*>₹?\s*([\d,]+(?:\.\d+)?)<\/span>/i', $block, $m_price ) ) {
				$price_str = preg_replace( '/[^\d.]/', '', $m_price[1] );
				$price = floatval( $price_str );
			}

			if ( $price <= 0 ) {
				continue;
			}

			// Extract Original Price / MRP
			$original_price = 0.0;
			if ( preg_match( '/<span[^>]*class=["\'][^"\']*a-text-price[^"\']*["\'][^>]*>[\s\S]*?<span[^>]*class=["\']a-offscreen["\'][^>]*>₹?\s*([\d,]+(?:\.\d+)?)<\/span>/i', $block, $m_orig ) ) {
				$orig_str = preg_replace( '/[^\d.]/', '', $m_orig[1] );
				$original_price = floatval( $orig_str );
			}

			// Extract Image URL
			$image_url = '';
			if ( preg_match( '/<img[^>]*class=["\'][^"\']*s-image[^"\']*["\'][^>]*src=["\']([^"\']+)["\']/i', $block, $m_img ) ) {
				$image_url = trim( $m_img[1] );
			}

			// Check Availability
			$is_unavailable = ( stripos( $block, 'Currently unavailable' ) !== false || stripos( $block, 'Out of Stock' ) !== false );
			$in_stock = ( ! $is_unavailable && $price > 0 );

			$items[] = array(
				'title'          => $title,
				'url'            => $url,
				'price'          => $price,
				'original_price' => ( $original_price > $price ) ? $original_price : 0.0,
				'image_url'      => $image_url,
				'in_stock'       => $in_stock,
				'stock_status'   => $in_stock ? 'in_stock' : 'out_of_stock',
				'sku'            => $asin,
				'category'       => $category,
				'brand'          => '',
				'raw_data'       => array(
					'asin'   => $asin,
					'vendor' => 'amazon-in',
				),
			);
		}

		return $items;
	}
}
