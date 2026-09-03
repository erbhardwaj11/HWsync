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
			'cpu'         => '/s?k=processor+desktop&i=computers',
			'gpu'         => '/s?k=graphic+card&i=computers',
			'motherboard' => '/s?k=motherboard+desktop&i=computers',
			'ram'         => '/s?k=desktop+ram+ddr4+ddr5&i=computers',
			'storage'     => '/s?k=internal+ssd+nvme+m.2&i=computers',
			'psu'         => '/s?k=smps+power+supply+unit&i=computers',
			'cooler'      => '/s?k=cpu+cooler+liquid+aio&i=computers',
			'cabinet'     => '/s?k=gaming+cabinet+case&i=computers',
			'case_fan'    => '/s?k=pc+case+fan+cabinet+fan+120mm&i=computers',
		);
	}

	/**
	 * Get affiliate tag configured for Amazon India.
	 *
	 * @return string
	 */
	public function get_affiliate_tag() {
		$tag = '';
		if ( function_exists( 'get_option' ) ) {
			$tag = (string) get_option( 'hwsync_amazon_affiliate_tag', '' );
		}
		return trim( $tag );
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : '/s?k=processor+desktop&i=computers';

		if ( $page > 1 ) {
			$connector = ( strpos( $path, '?' ) !== false ) ? '&' : '?';
			$url = $this->base_url . $path . $connector . 'page=' . intval( $page );
		} else {
			$url = $this->base_url . $path;
		}

		$user_agents = array(
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
		);
		$ua = $user_agents[ array_rand( $user_agents ) ];

		$headers = array(
			'User-Agent'                => $ua,
			'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
			'Accept-Language'           => 'en-IN,en-GB;q=0.9,en-US;q=0.8,en;q=0.7',
			'Referer'                   => 'https://www.amazon.in/',
			'Upgrade-Insecure-Requests' => '1',
			'Sec-Fetch-Dest'            => 'document',
			'Sec-Fetch-Mode'            => 'navigate',
			'Sec-Fetch-Site'            => 'none',
			'Sec-Fetch-User'            => '?1',
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

		$affiliate_tag = $this->get_affiliate_tag();

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

			if ( empty( $asin ) ) {
				continue;
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

			// Clean title of promotional junk
			$title = preg_replace( '/^(Sponsored|Limited time deal|Amazon\'s Choice|Best seller)\s*[:-]?\s*/i', '', $title );
			$title = trim( $title );

			// Build canonical URL with affiliate tag
			$url = $this->base_url . '/dp/' . $asin;
			if ( ! empty( $affiliate_tag ) ) {
				$url .= '?tag=' . urlencode( $affiliate_tag );
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

			// Extract High-Res Image URL
			$image_url = '';
			if ( preg_match( '/<img[^>]*class=["\'][^"\']*s-image[^"\']*["\'][^>]*src=["\']([^"\']+)["\']/i', $block, $m_img ) ) {
				$raw_img = trim( $m_img[1] );
				// Convert thumbnail url to clean full-resolution photo
				$image_url = preg_replace( '/\._[A-Z0-9_,]+_\./i', '.', $raw_img );
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

	/**
	 * Fetch single product details directly from Amazon product page URL or ASIN.
	 *
	 * @param string $url_or_asin
	 * @return array|null
	 */
	public function fetch_single_product( $url_or_asin ) {
		$asin = '';
		if ( preg_match( '/(?:dp|gp\/product)\/([A-Z0-9]{10})/i', $url_or_asin, $m ) ) {
			$asin = $m[1];
		} elseif ( preg_match( '/^[A-Z0-9]{10}$/i', trim( $url_or_asin ) ) ) {
			$asin = trim( $url_or_asin );
		}

		if ( empty( $asin ) ) {
			return null;
		}

		$url = $this->base_url . '/dp/' . $asin;
		$headers = array(
			'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
			'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		);

		$res = $this->make_request( $url, $headers );
		if ( ! $res['success'] || empty( $res['body'] ) ) {
			return null;
		}

		$html = $res['body'];

		// Extract Title
		$title = '';
		if ( preg_match( '/<span[^>]*id=["\']productTitle["\'][^>]*>(.*?)<\/span>/is', $html, $m ) ) {
			$title = trim( strip_tags( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) ) );
		}

		// Extract Price
		$price = 0.0;
		if ( preg_match( '/<span[^>]*class=["\'][^"\']*a-price-whole[^"\']*["\'][^>]*>([^<]+)<\/span>/i', $html, $m ) ) {
			$price = floatval( preg_replace( '/[^\d.]/', '', $m[1] ) );
		} elseif ( preg_match( '/<span[^>]*id=["\']priceblock_ourprice["\'][^>]*>₹?\s*([\d,]+(?:\.\d+)?)<\/span>/i', $html, $m ) ) {
			$price = floatval( preg_replace( '/[^\d.]/', '', $m[1] ) );
		}

		// Extract Image
		$image_url = '';
		if ( preg_match( '/<img[^>]*id=["\']landingImage["\'][^>]*data-old-hires=["\']([^"\']+)["\']/i', $html, $m ) && ! empty( $m[1] ) ) {
			$image_url = trim( $m[1] );
		} elseif ( preg_match( '/<img[^>]*id=["\']landingImage["\'][^>]*src=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$image_url = preg_replace( '/\._[A-Z0-9_,]+_\./i', '.', trim( $m[1] ) );
		}

		$in_stock = ( stripos( $html, 'Currently unavailable' ) === false && $price > 0 );

		return array(
			'title'        => $title,
			'url'          => $url,
			'price'        => $price,
			'image_url'    => $image_url,
			'in_stock'     => $in_stock,
			'stock_status' => $in_stock ? 'in_stock' : 'out_of_stock',
			'sku'          => $asin,
		);
	}

	/**
	 * Search Amazon India directly for a specific canonical component title/model.
	 *
	 * @param string $query Product title or model to search on Amazon.
	 * @param string $category Hardware category.
	 * @return array List of parsed product cards.
	 */
	public function search_component_on_amazon( $query, $category = '' ) {
		$query = trim( (string) $query );
		if ( empty( $query ) ) {
			return array();
		}

		$url = $this->base_url . '/s?k=' . urlencode( $query ) . '&i=computers';
		$headers = array(
			'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
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
}
