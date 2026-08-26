<?php
namespace HWsync\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Abstract_Vendor_Adapter {
	protected $vendor_slug;
	protected $vendor_name;
	protected $base_url;

	public function __construct( $vendor_slug, $vendor_name, $base_url ) {
		$this->vendor_slug = $vendor_slug;
		$this->vendor_name = $vendor_name;
		$this->base_url    = rtrim( (string) $base_url, '/\\' );
	}

	public function get_slug() {
		return $this->vendor_slug;
	}

	public function get_name() {
		return $this->vendor_name;
	}

	public function get_base_url() {
		return $this->base_url;
	}

	/**
	 * Fetch listing of products for a category or feed.
	 * Must return an array of normalized raw items:
	 * [
	 *   [
	 *     'title' => 'AMD Ryzen 7 7800X3D Processor',
	 *     'url' => 'https://...',
	 *     'price' => 35999.00,
	 *     'original_price' => 45000.00,
	 *     'in_stock' => true,
	 *     'stock_status' => 'in_stock',
	 *     'sku' => '100-100000910WOF',
	 *     'category' => 'cpu',
	 *     'brand' => 'AMD',
	 *     'raw_data' => [...]
	 *   ]
	 * ]
	 *
	 * @param string $category Hardware category slug (e.g. 'cpu', 'gpu', 'motherboard', 'ram', 'psu', 'storage')
	 * @param int $page Page number
	 * @return array
	 */
	abstract public function fetch_products( $category = '', $page = 1 );

	/**
	 * Perform an authentic HTTP GET request using native cURL with full browser headers and decompression.
	 */
	protected function make_request( $url, $headers = array() ) {
		$default_ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

		$default_headers = array(
			'User-Agent: ' . $default_ua,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
			'Accept-Language: en-US,en;q=0.9',
			'Referer: https://www.google.com/',
			'Sec-Ch-Ua: "Not/A)Brand";v="8", "Chromium";v="126", "Google Chrome";v="126"',
			'Sec-Ch-Ua-Mobile: ?0',
			'Sec-Ch-Ua-Platform: "Windows"',
			'Sec-Fetch-Dest: document',
			'Sec-Fetch-Mode: navigate',
			'Sec-Fetch-Site: cross-site',
			'Sec-Fetch-User: ?1',
			'Upgrade-Insecure-Requests: 1',
			'Cache-Control: max-age=0',
		);

		// If custom headers provided as key-value pairs
		$formatted_headers = $default_headers;
		if ( ! empty( $headers ) ) {
			foreach ( $headers as $k => $v ) {
				if ( is_string( $k ) ) {
					$formatted_headers[] = "{$k}: {$v}";
				} else {
					$formatted_headers[] = $v;
				}
			}
		}

		// Try native cURL first (bypasses bot signatures and handles decompression properly)
		if ( function_exists( 'curl_init' ) ) {
			$cookie_file = sys_get_temp_dir() . '/hwsync_cookies_' . md5( parse_url( $url, PHP_URL_HOST ) ) . '.txt';

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch, CURLOPT_MAXREDIRS, 5 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $formatted_headers );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
			curl_setopt( $ch, CURLOPT_ENCODING, '' ); // Decompress gzip, deflate, and br
			curl_setopt( $ch, CURLOPT_USERAGENT, $default_ua );
			curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie_file );
			curl_setopt( $ch, CURLOPT_COOKIEFILE, $cookie_file );
			curl_setopt( $ch, CURLOPT_AUTOREFERER, true );

			$body = curl_exec( $ch );
			$status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$error = curl_error( $ch );
			curl_close( $ch );

			if ( $body !== false && $status_code >= 200 && $status_code < 400 ) {
				return array(
					'success'     => true,
					'status_code' => $status_code,
					'body'        => $body,
				);
			}

			if ( ! empty( $error ) ) {
				// Log or continue to wp_remote_get fallback
			}
		}

		// WordPress HTTP API Fallback
		if ( function_exists( 'wp_remote_get' ) ) {
			$wp_args = array(
				'timeout'     => 25,
				'redirection' => 5,
				'user-agent'  => $default_ua,
				'sslverify'   => false,
			);
			$response = \wp_remote_get( $url, $wp_args );

			if ( ! ( function_exists( 'is_wp_error' ) && \is_wp_error( $response ) ) ) {
				$status_code = function_exists( 'wp_remote_retrieve_response_code' ) ? \wp_remote_retrieve_response_code( $response ) : 200;
				$body        = function_exists( 'wp_remote_retrieve_body' ) ? \wp_remote_retrieve_body( $response ) : '';

				return array(
					'success'     => ( $status_code >= 200 && $status_code < 400 ),
					'status_code' => $status_code,
					'body'        => $body,
				);
			}
		}

		return array(
			'success'     => false,
			'status_code' => 0,
			'body'        => '',
		);
	}

	/**
	 * Sanitize and parse Indian Rupee strings (e.g., "₹ 34,999.00", "Rs. 2,450", "34999") into float.
	 */
	public static function clean_price( $price_str ) {
		if ( is_numeric( $price_str ) ) {
			return (float) $price_str;
		}

		$price_str = (string) $price_str;
		// Remove commas, spaces, and non-breaking spaces
		$normalized = str_replace( array( ',', ' ', "\xc2\xa0" ), '', $price_str );

		// Extract first valid decimal number pattern
		if ( preg_match( '/\d+(?:\.\d+)?/', $normalized, $matches ) ) {
			return (float) $matches[0];
		}

		return 0.0;
	}

	/**
	 * Accurately extracts the current (sale/offer) price and original (MRP/regular) price from HTML snippets.
	 * Guarantees that current sale price is prioritized over strikethrough MRP prices.
	 *
	 * @param string $html HTML snippet or product card.
	 * @return array array( 'price' => float, 'original_price' => float|null )
	 */
	public static function extract_clean_prices( $html ) {
		if ( empty( $html ) ) {
			return array( 'price' => 0.0, 'original_price' => null );
		}

		$current_price  = 0.0;
		$original_price = null;

		// 1. Look for explicit sale / offer / discounted price tags FIRST
		$sale_patterns = array(
			// WooCommerce <ins> tag (contains sale price)
			'/<ins[^>]*>[\s\S]*?<bdi>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)<\/bdi>/i',
			'/<ins[^>]*>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)<\/ins>/i',
			// OpenCart / Journal / Custom sale price classes
			'/<(?:span|div|p|ins)[^>]*class="[^"]*(?:price-new|special-price|offer-price|sales-price|sale-price|current-price|price-normal)[^"]*"[^>]*>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)/i',
			'/<(?:span|div|p|ins)[^>]*class="[^"]*(?:price-new|special-price|offer-price|sales-price|sale-price|current-price)[^"]*"[^>]*>[\s\S]*?<bdi>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)<\/bdi>/i',
		);

		foreach ( $sale_patterns as $pattern ) {
			if ( preg_match( $pattern, $html, $m ) ) {
				$val = self::clean_price( $m[1] );
				if ( $val > 0 ) {
					$current_price = $val;
					break;
				}
			}
		}

		// 2. Look for explicit regular / MRP / old price tags
		$old_patterns = array(
			// WooCommerce <del> tag (contains regular MRP price)
			'/<del[^>]*>[\s\S]*?<bdi>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)<\/bdi>/i',
			'/<del[^>]*>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)<\/del>/i',
			// OpenCart / Custom old price classes
			'/<(?:span|div|p|del)[^>]*class="[^"]*(?:price-old|old-price|regular-price|mrp|strike)[^"]*"[^>]*>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)/i',
			'/<(?:span|div|p|del)[^>]*class="[^"]*(?:price-old|old-price|regular-price|mrp|strike)[^"]*"[^>]*>[\s\S]*?<bdi>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)<\/bdi>/i',
		);

		foreach ( $old_patterns as $pattern ) {
			if ( preg_match( $pattern, $html, $m ) ) {
				$val = self::clean_price( $m[1] );
				if ( $val > 0 ) {
					$original_price = $val;
					break;
				}
			}
		}

		// 3. Fallback: If no explicit sale price was detected, strip old prices/del/taxes first, then extract standard price
		if ( $current_price <= 0 ) {
			$clean_html = preg_replace( '/<(?:del|span|div|p)[^>]*class="[^"]*(?:price-old|old-price|regular-price|mrp|strike|price-tax)[^"]*"[\s\S]*?<\/(?:del|span|div|p)>/i', '', $html );
			$clean_html = preg_replace( '/<del[^>]*>[\s\S]*?<\/del>/i', '', $clean_html );

			$fallback_patterns = array(
				'/<bdi>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)<\/bdi>/i',
				'/<bdi>[\s\S]*?([\d,]+(?:\.\d+)?)<\/bdi>/i',
				'/(?:price|amount)[^>]*>[\s\S]*?(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)/i',
				'/(?:(?:&#8377;|₹|Rs\.?)\s*)?([\d,]+(?:\.\d+)?)/i',
			);
			foreach ( $fallback_patterns as $fpat ) {
				if ( preg_match( $fpat, $clean_html, $fm ) ) {
					$cval = self::clean_price( $fm[1] );
					if ( $cval > 0 ) {
						$current_price = $cval;
						break;
					}
				}
			}
		}

		// 4. Sanity check: Ensure current_price is the lowest (discounted offer price)
		if ( $current_price > 0 && $original_price !== null && $original_price > 0 ) {
			if ( $current_price > $original_price ) {
				$temp           = $current_price;
				$current_price  = $original_price;
				$original_price = $temp;
			}
		}

		return array(
			'price'          => $current_price,
			'original_price' => $original_price,
		);
	}
}
