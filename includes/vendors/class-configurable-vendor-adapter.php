<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Configurable_Vendor_Adapter extends Abstract_Vendor_Adapter {

	protected $sync_method;
	protected $endpoints = array();

	public function __construct( $vendor_slug, $vendor_name, $base_url, $sync_method = 'curl_html', $endpoints = array() ) {
		parent::__construct( $vendor_slug, $vendor_name, $base_url );
		$this->sync_method = $sync_method ?: 'curl_html';
		$this->endpoints   = is_array( $endpoints ) ? $endpoints : array();
	}

	public function get_category_endpoints() {
		$defaults = array(
			'cpu'         => '/processors',
			'gpu'         => '/graphics-cards',
			'motherboard' => '/motherboards',
			'ram'         => '/ram',
			'storage'     => '/storage-ssd',
			'psu'         => '/power-supply',
			'cooler'      => '/cooling',
			'cabinet'     => '/cases',
		);

		return wp_parse_args( $this->endpoints, $defaults );
	}

	public function fetch_products( $category = '', $page = 1 ) {
		if ( $this->sync_method === 'shopify_json' ) {
			return $this->fetch_shopify_products( $category, $page );
		}

		return $this->fetch_html_products( $category, $page );
	}

	/**
	 * Scrape Shopify JSON REST endpoint: /collections/{collection}/products.json
	 */
	protected function fetch_shopify_products( $category, $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$collection = isset( $endpoints[ $category ] ) ? trim( $endpoints[ $category ], '/' ) : 'processors';
		// Remove leading 'collections/' if user entered full path
		$collection = preg_replace( '/^collections\//i', '', $collection );

		$url = $this->base_url . '/collections/' . $collection . '/products.json?limit=50&page=' . intval( $page );
		$res = $this->make_request( $url, array( 'Accept' => 'application/json' ) );

		if ( ! $res['success'] || empty( $res['body'] ) ) {
			return array();
		}

		$json = json_decode( $res['body'], true );
		if ( ! is_array( $json ) || empty( $json['products'] ) ) {
			return array();
		}

		$items = array();
		foreach ( $json['products'] as $p ) {
			$title    = isset( $p['title'] ) ? trim( $p['title'] ) : '';
			$handle   = isset( $p['handle'] ) ? $p['handle'] : '';
			$variants = isset( $p['variants'] ) && is_array( $p['variants'] ) ? $p['variants'] : array();
			$variant  = ! empty( $variants ) ? $variants[0] : array();

			$price      = isset( $variant['price'] ) ? self::clean_price( $variant['price'] ) : 0.0;
			$orig_price = isset( $variant['compare_at_price'] ) ? self::clean_price( $variant['compare_at_price'] ) : null;
			$sku        = isset( $variant['sku'] ) ? trim( $variant['sku'] ) : '';
			$in_stock   = isset( $variant['available'] ) ? (bool) $variant['available'] : true;
			$prod_url   = $this->base_url . '/products/' . $handle;

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

		return $items;
	}

	/**
	 * Scrape Standard HTML (WooCommerce / OpenCart / Generic) via cURL
	 */
	protected function fetch_html_products( $category, $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? '/' . ltrim( $endpoints[ $category ], '/' ) : '/';

		// Handle pagination formats
		if ( $page > 1 ) {
			if ( strpos( $path, '?' ) !== false ) {
				$url = $this->base_url . $path . '&page=' . intval( $page );
			} elseif ( strpos( $path, '/page/' ) !== false ) {
				$url = $this->base_url . preg_replace( '/\/page\/\d+/i', '/page/' . intval( $page ), $path );
			} else {
				$url = $this->base_url . rtrim( $path, '/' ) . '/page/' . intval( $page ) . '/';
			}
		} else {
			$url = $this->base_url . $path;
		}

		$res = $this->make_request( $url );
		if ( ! $res['success'] || empty( $res['body'] ) ) {
			return array();
		}

		return $this->parse_generic_html( $res['body'], $category );
	}

	/**
	 * Robust multi-engine HTML card parser
	 */
	public function parse_generic_html( $html_content, $category ) {
		$items = array();
		if ( empty( $html_content ) ) {
			return $items;
		}

		// 1. Split by WooCommerce / OpenCart item cards
		$parts = preg_split( '/<(?:li|div)[^>]*class="[^"]*(?:product\s+type-product|type-product\s+post-|product-small|product-item-container|product-thumb|product-layout|grid-product)[^"]*"/', $html_content );

		if ( count( $parts ) > 1 ) {
			array_shift( $parts ); // Remove preamble

			foreach ( $parts as $p ) {
				// URL
				$url = '';
				if ( preg_match( '/href="([^"]*\/product\/[^"]+|\/[^"]+\.html|\/catalog\/[^"]+)"/i', $p, $um ) ||
				     preg_match( '/<a\s+href="([^"]+)"\s+class="[^"]*woocommerce-LoopProduct-link/i', $p, $um ) ||
				     preg_match( '/<h[234][^>]*>\s*<a\s+href="([^"]+)"/i', $p, $um ) ) {
					$url = $um[1];
					if ( strpos( $url, 'http' ) !== 0 ) {
						$url = $this->base_url . '/' . ltrim( $url, '/' );
					}
				}

				// Title
				$title = '';
				if ( preg_match( '/<h[234][^>]*class="[^"]*(?:product-title|title|name|woocommerce-loop-product__title)[^"]*"[^>]*>\s*(?:<a[^>]*>)?([^<]+)(?:<\/a>)?<\/h[234]>/i', $p, $tm ) ) {
					$title = $tm[1];
				} elseif ( preg_match( '/<h[234][^>]*>\s*<a[^>]*>([^<]+)<\/a>/i', $p, $tm ) ) {
					$title = $tm[1];
				} elseif ( preg_match( '/\btitle="([^"]+)"/i', $p, $tm ) ) {
					$title = $tm[1];
				} elseif ( preg_match( '/\balt="([^"]+)"/i', $p, $tm ) ) {
					$title = $tm[1];
				}

				if ( ! empty( $title ) ) {
					$title = html_entity_decode( trim( strip_tags( $title ) ), ENT_QUOTES, 'UTF-8' );
				}

				// Price extraction - prioritize discounted sale price (<ins>, .price-new, .special-price)
				$price = 0.0;
				if ( preg_match( '/<ins[^>]*>[\s\S]*?<bdi>[\s\S]*?([\d,]+(?:\.\d+)?)<\/bdi>/i', $p, $pm ) ||
				     preg_match( '/<ins[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $p, $pm ) ||
				     preg_match( '/(?:price-new|special-price)[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $p, $pm ) ) {
					$price = self::clean_price( $pm[1] );
				} else {
					// Strip old prices and taxes before extracting
					$clean_block = preg_replace( '/<(?:span|div|del)[^>]*class="[^"]*(?:price-old|del|price-tax)[^"]*"[\s\S]*?<\/(?:span|div|del)>/i', '', $p );
					if ( preg_match( '/<bdi>[\s\S]*?([\d,]+(?:\.\d+)?)<\/bdi>/i', $clean_block, $pm ) ||
					     preg_match( '/(?:price|amount)[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $clean_block, $pm ) ) {
						$price = self::clean_price( $pm[1] );
					}
				}

				// SKU
				$sku = '';
				if ( preg_match( '/data-product_sku="([^"]*)"/i', $p, $sm ) ) {
					$sku = trim( $sm[1] );
				}

				$in_stock = ( stripos( $p, 'out-of-stock' ) === false && stripos( $p, 'out of stock' ) === false && stripos( $p, 'sold out' ) === false );

				if ( ! empty( $url ) && ! empty( $title ) && $price > 0 ) {
					$items[] = array(
						'title'          => $title,
						'url'            => $url,
						'price'          => $price,
						'original_price' => null,
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
