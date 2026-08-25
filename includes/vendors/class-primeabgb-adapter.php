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
		if ( ! $res['success'] || empty( $res['body'] ) ) {
			return array();
		}

		return $this->parse_html( $res['body'], $category );
	}

	public function parse_html( $html, $category ) {
		$items = array();
		if ( empty( $html ) ) {
			return $items;
		}

		// WooCommerce card parsing
		if ( preg_match_all( '/<li[^>]*class="[^"]*product[^"]*"[\s\S]*?<\/li>/i', $html, $cards ) ) {
			foreach ( $cards[0] as $card_html ) {
				$item = $this->extract_card( $card_html, $category );
				if ( $item ) {
					$items[] = $item;
				}
			}
		}

		// Global WooCommerce title & link fallback
		if ( empty( $items ) ) {
			if ( preg_match_all( '/<h2[^>]*class="[^"]*woocommerce-loop-product__title[^"]*"[^>]*>([^<]+)<\/h2>/i', $html, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $m ) {
					$title = html_entity_decode( trim( $m[1] ) );
					$pos = strpos( $html, $m[0] );
					$snippet = ( $pos !== false ) ? substr( $html, max( 0, $pos - 300 ), 1500 ) : '';

					$prod_url = '';
					if ( preg_match( '/<a[^>]*href="([^"]+)"[^>]*class="[^"]*woocommerce-LoopProduct-link/i', $snippet, $um ) ||
					     preg_match( '/<a[^>]*href="([^"]+)"/i', $snippet, $um ) ) {
						$prod_url = $um[1];
					}

					$price = 0.0;
					$reg_price = null;
					if ( preg_match( '/<ins[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $snippet, $pm ) ) {
						$price = self::clean_price( $pm[1] );
					} elseif ( preg_match( '/(?:price|amount)[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $snippet, $pm2 ) ) {
						$price = self::clean_price( $pm2[1] );
					}

					if ( preg_match( '/<del[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $snippet, $rpm ) ) {
						$reg_price = self::clean_price( $rpm[1] );
					}

					$in_stock = ( stripos( $snippet, 'out-of-stock' ) === false );

					if ( $price > 0 && ! empty( $title ) && ! empty( $prod_url ) ) {
						$items[] = array(
							'title'          => $title,
							'url'            => $prod_url,
							'price'          => $price,
							'original_price' => $reg_price,
							'in_stock'       => $in_stock,
							'stock_status'   => $in_stock ? 'in_stock' : 'out_of_stock',
							'category'       => $category,
							'vendor_slug'    => $this->vendor_slug,
							'raw_data'       => array( 'raw_title' => $title, 'price' => $price ),
						);
					}
				}
			}
		}

		return $items;
	}

	private function extract_card( $card_html, $category ) {
		$title = '';
		$url   = '';

		if ( preg_match( '/<h2[^>]*class="[^"]*woocommerce-loop-product__title[^"]*"[^>]*>([^<]+)<\/h2>/i', $card_html, $tm ) ||
		     preg_match( '/<h[234][^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $card_html, $tm2 ) ) {
			$title = isset( $tm[1] ) ? html_entity_decode( trim( $tm[1] ) ) : html_entity_decode( trim( $tm2[2] ) );
		}

		if ( preg_match( '/<a[^>]*class="[^"]*woocommerce-LoopProduct-link[^"]*"[^>]*href="([^"]+)"/i', $card_html, $um ) ||
		     preg_match( '/<a[^>]*href="([^"]+)"/i', $card_html, $um2 ) ) {
			$url = isset( $um[1] ) ? $um[1] : $um2[1];
		}

		if ( empty( $title ) || empty( $url ) ) {
			return null;
		}

		$price = 0.0;
		$orig_price = null;
		if ( preg_match( '/<ins[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $card_html, $pm ) ) {
			$price = self::clean_price( $pm[1] );
		} elseif ( preg_match( '/(?:price|amount)[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $card_html, $pm2 ) ) {
			$price = self::clean_price( $pm2[1] );
		}

		if ( preg_match( '/<del[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $card_html, $rpm ) ) {
			$orig_price = self::clean_price( $rpm[1] );
		}

		$in_stock = ( stripos( $card_html, 'out-of-stock' ) === false && stripos( $card_html, 'out of stock' ) === false );

		if ( $price > 0 ) {
			return array(
				'title'          => $title,
				'url'            => $url,
				'price'          => $price,
				'original_price' => $orig_price,
				'in_stock'       => $in_stock,
				'stock_status'   => $in_stock ? 'in_stock' : 'out_of_stock',
				'category'       => $category,
				'vendor_slug'    => $this->vendor_slug,
				'raw_data'       => array( 'raw_title' => $title, 'price' => $price ),
			);
		}

		return null;
	}
}
