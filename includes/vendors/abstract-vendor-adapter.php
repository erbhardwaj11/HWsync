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
		$this->base_url    = untrailingslashit( $base_url );
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
	 * Perform a safe HTTP GET request with realistic browser headers.
	 */
	protected function make_request( $url, $headers = array() ) {
		$default_headers = array(
			'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 HWsync/1.0',
			'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,application/json,*/*;q=0.8',
			'Accept-Language' => 'en-US,en;q=0.9',
		);

		$args = array(
			'timeout'     => 25,
			'redirection' => 5,
			'headers'     => wp_parse_args( $headers, $default_headers ),
			'sslverify'   => false,
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
				'body'    => '',
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		return array(
			'success'     => ( $status_code >= 200 && $status_code < 300 ),
			'status_code' => $status_code,
			'body'        => $body,
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
}
