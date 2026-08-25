<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MDComputers_Adapter extends Abstract_Vendor_Adapter {

	public function __construct() {
		parent::__construct( 'mdcomputers', 'MDComputers', 'https://mdcomputers.in' );
	}

	public function get_category_endpoints() {
		return array(
			'cpu'         => '/catalog/processor',
			'gpu'         => '/catalog/graphics-card',
			'motherboard' => '/catalog/motherboard',
			'ram'         => '/catalog/ram/desktop-ram',
			'storage'     => '/catalog/storage',
			'psu'         => '/catalog/smps',
			'cooler'      => '/cooling-system.html',
			'cabinet'     => '/catalog/cabinet',
		);
	}

	/**
	 * Path to persistent session cookie jar for headless session warm-up.
	 */
	protected function get_cookie_file() {
		$upload_dir = function_exists( 'wp_upload_dir' ) ? \wp_upload_dir() : array( 'basedir' => sys_get_temp_dir() );
		$dir = trailingslashit( $upload_dir['basedir'] ) . 'hwsync-sessions';
		if ( ! file_exists( $dir ) && function_exists( 'wp_mkdir_p' ) ) {
			\wp_mkdir_p( $dir );
		}
		if ( is_dir( $dir ) && is_writable( $dir ) ) {
			return trailingslashit( $dir ) . 'mdcomputers_cookies.txt';
		}
		return sys_get_temp_dir() . '/hwsync_mdcomputers_cookies.txt';
	}

	/**
	 * Perform a headless session warm-up handshake to acquire Cloudflare clearance and session cookies.
	 */
	public function warm_up_session() {
		$cookie_file = $this->get_cookie_file();
		if ( file_exists( $cookie_file ) && ( time() - filemtime( $cookie_file ) < 10800 ) && filesize( $cookie_file ) > 10 ) {
			return true;
		}

		$home_url = 'https://mdcomputers.in/';
		$this->fetch_headless_http( $home_url, $cookie_file, true );
		return true;
	}

	public function fetch_products( $category = '', $page = 1 ) {
		$endpoints = $this->get_category_endpoints();
		$path = isset( $endpoints[ $category ] ) ? $endpoints[ $category ] : '/catalog/processor';
		$url = $this->base_url . $path . ( $page > 1 ? '?page=' . intval( $page ) : '' );

		$cookie_file = $this->get_cookie_file();
		if ( ! file_exists( $cookie_file ) || ( time() - filemtime( $cookie_file ) > 10800 ) ) {
			$this->warm_up_session();
		}

		$res = $this->fetch_headless_http( $url, $cookie_file, false );
		if ( ! $res['success'] ) {
			return array();
		}

		return $this->parse_html( $res['body'], $category );
	}

	/**
	 * Low-level headless transport with browser fingerprint and persistent cookies.
	 */
	protected function fetch_headless_http( $url, $cookie_file, $is_warmup = false ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return $this->make_request( $url );
		}

		$ch = curl_init();
		$headers = array(
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
			'Accept-Language: en-US,en;q=0.9',
			'Sec-Ch-Ua: "Not/A)Brand";v="8", "Chromium";v="126", "Google Chrome";v="126"',
			'Sec-Ch-Ua-Mobile: ?0',
			'Sec-Ch-Ua-Platform: "Windows"',
			'Sec-Fetch-Dest: document',
			'Sec-Fetch-Mode: navigate',
			'Sec-Fetch-Site: ' . ( $is_warmup ? 'none' : 'same-origin' ),
			'Sec-Fetch-User: ?1',
			'Upgrade-Insecure-Requests: 1',
			'Referer: https://mdcomputers.in/',
			'Cache-Control: max-age=0',
		);

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 5 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 35 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_ENCODING, '' ); // Decompress gzip/deflate/br
		curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie_file );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, $cookie_file );

		$body = curl_exec( $ch );
		$status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		return array(
			'success'     => ( $body !== false && $status_code >= 200 && $status_code < 400 ),
			'status_code' => $status_code,
			'body'        => $body ?: '',
		);
	}

	public function parse_html( $html, $category ) {
		$items = array();
		if ( empty( $html ) ) {
			return $items;
		}

		// Strategy 1: OpenCart / Journal Theme Grid Items
		if ( preg_match_all( '/<div[^>]*class="[^"]*(?:product-grid-item|product-item-container|product-thumb|product-layout)[^"]*"[\s\S]*?<\/h[34]>[\s\S]*?(?:<\/div>\s*<\/div>|<\/div>\s*<\/div>\s*<\/div>)/i', $html, $cards ) ) {
			foreach ( $cards[0] as $card_html ) {
				$item = $this->extract_card( $card_html, $category );
				if ( $item ) {
					$items[] = $item;
				}
			}
		}

		// Strategy 2: Direct link-to-title extraction if card wrapper differs
		if ( empty( $items ) ) {
			if ( preg_match_all( '/<h[34][^>]*class="[^"]*(?:product-entities-title|title|name)[^"]*"[^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $html, $title_matches, PREG_SET_ORDER ) ) {
				foreach ( $title_matches as $tm ) {
					$prod_url = $tm[1];
					$title    = html_entity_decode( trim( $tm[2] ) );

					$pos = strpos( $html, $prod_url );
					$price = 0.0;
					$reg_price = null;
					$in_stock = true;

					if ( $pos !== false ) {
						$snippet = substr( $html, max( 0, $pos - 200 ), 1600 );
						if ( preg_match( '/(?:price-new|price|amount)[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $snippet, $pm ) ) {
							$price = self::clean_price( $pm[1] );
						}
						if ( preg_match( '/(?:price-old|del)[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $snippet, $rpm ) ) {
							$reg_price = self::clean_price( $rpm[1] );
						}
						if ( stripos( $snippet, 'out of stock' ) !== false || stripos( $snippet, 'sold out' ) !== false ) {
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

		if ( preg_match( '/<h[34][^>]*>\s*<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $card_html, $tm ) ) {
			$url   = $tm[1];
			$title = html_entity_decode( trim( $tm[2] ) );
		}

		if ( empty( $title ) || empty( $url ) ) {
			return null;
		}

		$price = 0.0;
		$orig_price = null;
		if ( preg_match( '/(?:price-new|price|amount)[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $card_html, $pm ) ) {
			$price = self::clean_price( $pm[1] );
		}
		if ( preg_match( '/(?:price-old|del)[^>]*>[\s\S]*?(?:&#8377;|₹|Rs\.?)\s*([\d,]+(?:\.\d+)?)/i', $card_html, $rpm ) ) {
			$orig_price = self::clean_price( $rpm[1] );
		}

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
