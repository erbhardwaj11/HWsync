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
			'cpu'         => array( '/product-category/processor/', '/processor/' ),
			'gpu'         => array( '/product-category/graphics-card/', '/graphics-card/' ),
			'motherboard' => array( '/product-category/motherboard/', '/motherboard/' ),
			'ram'         => array( '/product-category/ram/', '/ram/' ),
			'storage'     => array( '/product-category/ssd/', '/ssd/' ),
			'psu'         => array( '/product-category/smps/', '/smps/' ),
			'cooler'      => array( '/product-category/cpu-cooler/', '/cpu-cooler/' ),
			'cabinet'     => array( '/product-category/cabinet/', '/cabinet/' ),
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$paths = isset( $endpoints[ $category ] ) ? (array) $endpoints[ $category ] : array( '/product-category/processor/' );

		foreach ( $paths as $path ) {
			$url = $this->base_url . $path . ( $page > 1 ? 'page/' . intval( $page ) . '/' : '' );
			$res = $this->make_request( $url );

			if ( $res['success'] && ! empty( $res['body'] ) ) {
				$items = $this->parse_html( $res['body'], $category );
				if ( ! empty( $items ) ) {
					return $items;
				}
			}
		}

		return array();
	}

	public function parse_html( $html, $category ) {
		$items = array();
		if ( empty( $html ) ) {
			return $items;
		}

		// Flatsome / WooCommerce product-small parsing
		if ( preg_match_all( '/<div[^>]*class="[^"]*(?:product-small|product-box|col-inner)[^"]*"[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/i', $html, $cards ) ) {
			foreach ( $cards[0] as $card_html ) {
				$item = $this->extract_card( $card_html, $category );
				if ( $item ) {
					$items[] = $item;
				}
			}
		}

		if ( empty( $items ) ) {
			if ( preg_match_all( '/<p[^>]*class="[^"]*(?:name|product-title)[^"]*"[^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $m ) {
					$prod_url = $m[1];
					$title    = html_entity_decode( trim( $m[2] ) );

					$pos = strpos( $html, $prod_url );
					$price = 0.0;
					$reg_price = null;
					$in_stock = true;

					if ( $pos !== false ) {
						$snippet = substr( $html, max( 0, $pos - 200 ), 1600 );
						if ( preg_match( '/<ins[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $snippet, $pm ) ) {
							$price = self::clean_price( $pm[1] );
						} elseif ( preg_match( '/(?:price|amount)[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $snippet, $pm2 ) ) {
							$price = self::clean_price( $pm2[1] );
						}
						if ( preg_match( '/<del[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $snippet, $rpm ) ) {
							$reg_price = self::clean_price( $rpm[1] );
						}
						if ( stripos( $snippet, 'out-of-stock' ) !== false || stripos( $snippet, 'sold out' ) !== false ) {
							$in_stock = false;
						}
					}

					if ( $price > 0 && ! empty( $title ) ) {
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

		if ( preg_match( '/<p[^>]*class="[^"]*(?:name|product-title)[^"]*"[^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $card_html, $tm ) ||
		     preg_match( '/<h[234][^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $card_html, $tm2 ) ) {
			$url   = isset( $tm[1] ) ? $tm[1] : $tm2[1];
			$title = isset( $tm[2] ) ? html_entity_decode( trim( $tm[2] ) ) : html_entity_decode( trim( $tm2[2] ) );
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
