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
			'cpu'         => array( '/pc-components/processor', '/processors', '/processor' ),
			'gpu'         => array( '/pc-components/graphics-card', '/graphics-cards', '/graphics-card' ),
			'motherboard' => array( '/pc-components/motherboard', '/motherboards', '/motherboard' ),
			'ram'         => array( '/pc-components/memory', '/memory-ram', '/ram' ),
			'storage'     => array( '/pc-components/storage', '/storage-devices', '/solid-state-drive' ),
			'psu'         => array( '/pc-components/power-supply', '/power-supply', '/smps' ),
			'cooler'      => array( '/cooling-system', '/cpu-cooler' ),
			'cabinet'     => array( '/pc-components/cabinet', '/cabinet' ),
		);
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$paths = isset( $endpoints[ $category ] ) ? (array) $endpoints[ $category ] : array( '/pc-components/processor' );

		foreach ( $paths as $path ) {
			$url = $this->base_url . $path . ( $page > 1 ? '?page=' . intval( $page ) : '' );
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

		// Pattern 1: OpenCart standard and Journal3 product cards
		if ( preg_match_all( '/<div[^>]*class="[^"]*(?:product-thumb|product-layout|product-grid-item)[^"]*"[\s\S]*?<\/h[34]>[\s\S]*?(?:<\/div>\s*<\/div>|<\/div>\s*<\/div>\s*<\/div>)/i', $html, $cards ) ) {
			foreach ( $cards[0] as $card_html ) {
				$item = $this->extract_card( $card_html, $category );
				if ( $item ) {
					$items[] = $item;
				}
			}
		}

		// Pattern 2: Global link and price chunk parsing
		if ( empty( $items ) ) {
			if ( preg_match_all( '/<h[34][^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $m ) {
					$prod_url = $m[1];
					$title    = html_entity_decode( trim( $m[2] ) );

					if ( empty( $title ) || stripos( $prod_url, 'javascript:' ) === 0 ) {
						continue;
					}

					$pos = strpos( $html, $prod_url );
					$price = 0.0;
					$reg_price = null;
					$in_stock = true;

					if ( $pos !== false ) {
						$snippet = substr( $html, max( 0, $pos - 200 ), 1600 );
						$price_data = self::extract_clean_prices( $snippet );
						$price = $price_data['price'];
						$reg_price = $price_data['original_price'];

						if ( stripos( $snippet, 'out of stock' ) !== false || stripos( $snippet, 'sold out' ) !== false ) {
							$in_stock = false;
						}
					}

					if ( $price > 0 ) {
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

		if ( preg_match( '/<h[34][^>]*class="[^"]*caption[^"]*"[^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $card_html, $tm ) ||
		     preg_match( '/<h[34][^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $card_html, $tm ) ) {
			$url   = $tm[1];
			$title = html_entity_decode( trim( $tm[2] ) );
		}

		if ( empty( $title ) || empty( $url ) ) {
			return null;
		}

		$price_data = self::extract_clean_prices( $card_html );
		$price      = $price_data['price'];
		$orig_price = $price_data['original_price'];

		$in_stock = ( stripos( $card_html, 'out of stock' ) === false && stripos( $card_html, 'sold out' ) === false );

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
