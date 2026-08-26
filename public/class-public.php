<?php
namespace HWsync {

use HWsync\Models\Component;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Public_Handler {

	public static function init() {
		add_shortcode( 'hwsync_price_table', array( __CLASS__, 'render_price_table_shortcode' ) );
		add_shortcode( 'pcspecs_prices', array( __CLASS__, 'render_price_table_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ), 5 );
	}

	public static function register_rest_routes() {
		$namespaces = array( 'pc-builder/v1', 'hwsync/v1' );

		foreach ( $namespaces as $ns ) {
			register_rest_route( $ns, '/components', array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_components' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'category'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'socket'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'ram_type'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'form_factor' => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'brand'       => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'search'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'min_price'   => array( 'sanitize_callback' => 'floatval' ),
					'max_price'   => array( 'sanitize_callback' => 'floatval' ),
					'sort_by'     => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'rating' ),
					'limit'       => array( 'sanitize_callback' => 'absint', 'default' => 50 ),
					'page'        => array( 'sanitize_callback' => 'absint', 'default' => 1 ),
				),
			) );

			register_rest_route( $ns, '/components/(?P<id>\d+)', array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_component_by_id' ),
				'permission_callback' => '__return_true',
			) );

			register_rest_route( $ns, '/pricing/lookup', array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_pricing_offers' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'part_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			) );
		}
	}

	/**
	 * Serve components to the React PC Part-Picker SPA
	 */
	public static function rest_get_components( \WP_REST_Request $request ) {
		global $wpdb;

		$category    = $request->get_param( 'category' );
		$socket      = $request->get_param( 'socket' );
		$ram_type    = $request->get_param( 'ram_type' );
		$form_factor = $request->get_param( 'form_factor' );
		$brand       = $request->get_param( 'brand' );
		$search      = $request->get_param( 'search' );
		$limit       = min( 200, max( 1, intval( $request->get_param( 'limit' ) ?: 50 ) ) );
		$page        = max( 1, intval( $request->get_param( 'page' ) ?: 1 ) );
		$offset      = ( $page - 1 ) * $limit;

		$comp_table = \HWsync\Database::get_table_name( 'components' );
		$pc_table   = $wpdb->prefix . 'pc_components';

		// Determine target table
		$tbl = ( $wpdb->get_var( "SHOW TABLES LIKE '{$pc_table}'" ) === $pc_table && intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$pc_table}" ) ) > 0 )
			? $pc_table
			: $comp_table;

		$where = array( "1=1" );

		if ( ! empty( $category ) && $category !== 'all' ) {
			$norm_cat = \HWsync\Post_Sync_Processor::normalize_pcspecs_category( $category );
			$where[] = $wpdb->prepare( "(category = %s OR LOWER(category) LIKE %s)", $norm_cat, '%' . $wpdb->esc_like( $norm_cat ) . '%' );
		}

		if ( ! empty( $brand ) && $brand !== 'all' ) {
			$where[] = $wpdb->prepare( "brand = %s", $brand );
		}

		if ( ! empty( $search ) ) {
			$wild = '%' . $wpdb->esc_like( $search ) . '%';
			$name_col = ( $tbl === $pc_table ) ? 'name' : 'model_name';
			$where[] = $wpdb->prepare( "({$name_col} LIKE %s OR mpn LIKE %s OR brand LIKE %s)", $wild, $wild, $wild );
		}

		$where_sql = implode( ' AND ', $where );
		$total = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE {$where_sql}" ) );

		$rows = $wpdb->get_results( "SELECT * FROM {$tbl} WHERE {$where_sql} ORDER BY id ASC LIMIT {$offset}, {$limit}", \ARRAY_A );

		$items = array();
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				$cid = intval( $r['id'] );
				$c_obj = Component::find_by_id( $cid );
				if ( ! $c_obj ) {
					$c_obj = new Component( $r );
				}

				$prices = $c_obj->get_prices();
				$merchant_offers = array();
				$lowest = 0.0;

				foreach ( $prices as $p ) {
					$cur = floatval( $p->price );
					$is_stock = (bool) $p->is_in_stock;
					if ( $is_stock && $cur > 0 && ( $lowest === 0.0 || $cur < $lowest ) ) {
						$lowest = $cur;
					}
					$v_name = ! empty( $p->vendor_name ) ? $p->vendor_name : 'Retailer';
					$merchant_offers[] = array(
						'merchant'      => $v_name,
						'merchant_slug' => sanitize_title( $v_name ),
						'price'         => $cur,
						'regular_price' => ! empty( $p->original_price ) ? floatval( $p->original_price ) : $cur,
						'currency'      => 'INR',
						'in_stock'      => $is_stock,
						'url'           => ! empty( $p->affiliate_url ) ? $p->affiliate_url : $p->product_url,
						'last_updated'  => $p->last_checked_at,
					);
				}

				$specs_raw = $c_obj->get_specs() ?: array();
				$formatted_specs = array();
				foreach ( $specs_raw as $sk => $sv ) {
					$formatted_specs[ 'spec_' . sanitize_key( $sk ) ] = $sv;
				}
				$formatted_specs['spec_base_price'] = $lowest;

				$title = ! empty( $r['name'] ) ? $r['name'] : ( $c_obj->brand . ' ' . $c_obj->model_name );

				$items[] = array(
					'id'              => $cid,
					'title'           => $title,
					'slug'            => sanitize_title( $title ),
					'category'        => \HWsync\Post_Sync_Processor::normalize_pcspecs_category( $c_obj->category ),
					'brand'           => $c_obj->brand,
					'price'           => $lowest,
					'image'           => null,
					'specs'           => $formatted_specs,
					'raw_specs'       => $specs_raw,
					'taxonomies'      => array(
						'component_type' => array( array( 'slug' => $c_obj->category, 'name' => ucfirst( $c_obj->category ) ) ),
						'brand'          => array( array( 'slug' => sanitize_title( $c_obj->brand ), 'name' => $c_obj->brand ) ),
					),
					'merchant_offers' => $merchant_offers,
					'updated_at'      => current_time( 'mysql' ),
				);
			}
		}

		return new \WP_REST_Response( array(
			'items' => $items,
			'total' => $total,
			'pages' => ceil( $total / $limit ),
		), 200 );
	}

	public static function rest_get_component_by_id( \WP_REST_Request $request ) {
		$id = intval( $request->get_param( 'id' ) );
		$c = Component::find_by_id( $id );
		if ( ! $c ) {
			return new \WP_Error( 'not_found', 'Component not found', array( 'status' => 404 ) );
		}

		$prices = $c->get_prices();
		$merchant_offers = array();
		$lowest = 0.0;

		foreach ( $prices as $p ) {
			$cur = floatval( $p->price );
			$is_stock = (bool) $p->is_in_stock;
			if ( $is_stock && $cur > 0 && ( $lowest === 0.0 || $cur < $lowest ) ) {
				$lowest = $cur;
			}
			$v_name = ! empty( $p->vendor_name ) ? $p->vendor_name : 'Retailer';
			$merchant_offers[] = array(
				'merchant'      => $v_name,
				'merchant_slug' => sanitize_title( $v_name ),
				'price'         => $cur,
				'regular_price' => ! empty( $p->original_price ) ? floatval( $p->original_price ) : $cur,
				'currency'      => 'INR',
				'in_stock'      => $is_stock,
				'url'           => ! empty( $p->affiliate_url ) ? $p->affiliate_url : $p->product_url,
				'last_updated'  => $p->last_checked_at,
			);
		}

		$specs_raw = $c->get_specs() ?: array();
		$formatted_specs = array();
		foreach ( $specs_raw as $sk => $sv ) {
			$formatted_specs[ 'spec_' . sanitize_key( $sk ) ] = $sv;
		}
		$formatted_specs['spec_base_price'] = $lowest;

		$title = $c->brand . ' ' . $c->model_name;

		return new \WP_REST_Response( array(
			'id'              => $c->id,
			'title'           => $title,
			'slug'            => sanitize_title( $title ),
			'category'        => \HWsync\Post_Sync_Processor::normalize_pcspecs_category( $c->category ),
			'brand'           => $c->brand,
			'price'           => $lowest,
			'image'           => null,
			'specs'           => $formatted_specs,
			'raw_specs'       => $specs_raw,
			'taxonomies'      => array(
				'component_type' => array( array( 'slug' => $c->category, 'name' => ucfirst( $c->category ) ) ),
				'brand'          => array( array( 'slug' => sanitize_title( $c->brand ), 'name' => $c->brand ) ),
			),
			'merchant_offers' => $merchant_offers,
			'updated_at'      => current_time( 'mysql' ),
		), 200 );
	}

	public static function rest_get_pricing_offers( \WP_REST_Request $request ) {
		$part_id = intval( $request->get_param( 'part_id' ) );
		$c = Component::find_by_id( $part_id );
		$offers = array();

		if ( $c ) {
			$prices = $c->get_prices();
			foreach ( $prices as $p ) {
				$v_name = ! empty( $p->vendor_name ) ? $p->vendor_name : 'Retailer';
				$slug = sanitize_title( $v_name );
				$offers[ $slug ] = array(
					'merchant'      => $v_name,
					'merchant_slug' => $slug,
					'price'         => floatval( $p->price ),
					'regular_price' => ! empty( $p->original_price ) ? floatval( $p->original_price ) : floatval( $p->price ),
					'sale_price'    => floatval( $p->price ),
					'currency'      => 'INR',
					'in_stock'      => (bool) $p->is_in_stock,
					'url'           => ! empty( $p->affiliate_url ) ? $p->affiliate_url : $p->product_url,
					'sku'           => $p->vendor_sku,
					'last_updated'  => $p->last_checked_at,
				);
			}
		}

		return new \WP_REST_Response( array(
			'part_id' => $part_id,
			'offers'  => $offers,
		), 200 );
	}

	public static function enqueue_styles() {
		wp_add_inline_style( 'wp-block-library', self::get_inline_css() );
	}

	public static function render_price_table_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'hwsync_price_table' );

		$component_id = intval( $atts['id'] );

		if ( empty( $component_id ) ) {
			// Try getting component ID from current post meta
			$post_id = get_the_ID();
			$component_id = intval( get_post_meta( $post_id, '_hwsync_component_id', true ) );
			if ( empty( $component_id ) ) {
				$component_id = intval( get_post_meta( $post_id, '_pcspecs_component_id', true ) );
			}
		}

		if ( empty( $component_id ) ) {
			return '';
		}

		$component = Component::find_by_id( $component_id );
		if ( ! $component ) {
			return '';
		}

		$prices = $component->get_prices();

		ob_start();
		include HWSYNC_PLUGIN_DIR . 'templates/price-comparison-table.php';
		return ob_get_clean();
	}

	public static function get_inline_css() {
		return "
		.hwsync-price-comparison-widget {
			border: 1px solid #e2e8f0;
			border-radius: 8px;
			padding: 20px;
			margin: 24px 0;
			background: #ffffff;
			box-shadow: 0 2px 4px rgba(0,0,0,0.04);
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.hwsync-widget-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 16px;
			border-bottom: 2px solid #f1f5f9;
			padding-bottom: 10px;
		}
		.hwsync-widget-title {
			margin: 0;
			font-size: 1.25rem;
			color: #1e293b;
		}
		.hwsync-retailers-count {
			font-size: 0.85rem;
			color: #64748b;
			background: #f1f5f9;
			padding: 4px 8px;
			border-radius: 12px;
		}
		.hwsync-table-responsive {
			overflow-x: auto;
		}
		.hwsync-table {
			width: 100%;
			border-collapse: collapse;
			text-align: left;
			font-size: 0.95rem;
		}
		.hwsync-table th {
			background: #f8fafc;
			padding: 10px 14px;
			color: #475569;
			font-weight: 600;
			border-bottom: 1px solid #e2e8f0;
		}
		.hwsync-table td {
			padding: 12px 14px;
			border-bottom: 1px solid #f1f5f9;
			vertical-align: middle;
		}
		.hwsync-lowest-deal {
			background: #f0fdf4;
		}
		.hwsync-badge {
			display: inline-block;
			padding: 2px 8px;
			font-size: 0.75rem;
			font-weight: 700;
			border-radius: 4px;
		}
		.hwsync-badge-instock {
			background: #dcfce7;
			color: #15803d;
		}
		.hwsync-badge-outofstock {
			background: #fee2e2;
			color: #b91c1c;
		}
		.hwsync-price-value {
			font-weight: 700;
			color: #0f172a;
			font-size: 1.05rem;
		}
		.hwsync-original-price {
			color: #94a3b8;
			margin-left: 6px;
			font-size: 0.85rem;
		}
		.hwsync-best-tag {
			display: block;
			font-size: 0.7rem;
			color: #15803d;
			font-weight: 700;
			text-transform: uppercase;
		}
		.hwsync-buy-btn {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			background: #2563eb;
			color: #ffffff !important;
			padding: 6px 14px;
			border-radius: 6px;
			text-decoration: none;
			font-size: 0.85rem;
			font-weight: 600;
			transition: background 0.15s ease-in-out;
		}
		.hwsync-buy-btn:hover {
			background: #1d4ed8;
		}
		.hwsync-btn-disabled {
			background: #94a3b8 !important;
		}
		.hwsync-disclaimer {
			margin-top: 14px;
			margin-bottom: 0;
			color: #94a3b8;
		}
		";
	}
}
}

namespace {

	/**
	 * Global Theme Helper Functions for pcspecs & Theme Developers
	 */
	if ( ! function_exists( 'pcspecs_get_vendor_prices' ) ) {
		function pcspecs_get_vendor_prices( $id = 0 ) {
			global $wpdb;
			if ( empty( $id ) && function_exists( 'get_the_ID' ) ) {
				$id = get_the_ID();
			}

			// 1. Direct query from native pcspecs table (wp_pc_vendor_prices)
			$pc_table = $wpdb->prefix . 'pc_vendor_prices';
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $pc_table ) ) === $pc_table ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$pc_table} WHERE component_id = %d ORDER BY current_price ASC", $id ), \ARRAY_A );
				if ( ! empty( $rows ) ) {
					return $rows;
				}
			}

			// 2. Direct query from HWsync table (wp_hwsync_vendor_prices)
			$hw_table = \HWsync\Database::get_table_name( 'vendor_prices' );
			$v_table = \HWsync\Database::get_table_name( 'vendors' );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT vp.*, v.vendor_name, v.vendor_slug 
				 FROM {$hw_table} vp 
				 LEFT JOIN {$v_table} v ON v.id = vp.vendor_id 
				 WHERE vp.component_id = %d ORDER BY vp.price ASC",
				$id
			), \ARRAY_A );
			if ( ! empty( $rows ) ) {
				return $rows;
			}

			// 3. Fallback to postmeta
			$prices = get_post_meta( $id, '_pcspecs_vendor_prices', true );
			if ( ! empty( $prices ) && is_array( $prices ) ) {
				return $prices;
			}
			return get_post_meta( $id, '_hwsync_vendor_prices', true ) ?: array();
		}
	}

	if ( ! function_exists( 'pcspecs_get_lowest_price' ) ) {
		function pcspecs_get_lowest_price( $id = 0 ) {
			$prices = pcspecs_get_vendor_prices( $id );
			$lowest = null;
			foreach ( $prices as $p ) {
				$val = isset( $p['current_price'] ) ? floatval( $p['current_price'] ) : ( isset( $p['price'] ) ? floatval( $p['price'] ) : 0 );
				$in_stock = isset( $p['stock_status'] ) ? ( $p['stock_status'] === 'instock' || $p['stock_status'] === 'in_stock' ) : ( ! empty( $p['is_in_stock'] ) );
				if ( $val > 0 && ( null === $lowest || ( $in_stock && $val < $lowest ) ) ) {
					$lowest = $val;
				}
			}
			if ( null !== $lowest ) {
				return $lowest;
			}

			if ( empty( $id ) && function_exists( 'get_the_ID' ) ) {
				$id = get_the_ID();
			}
			$meta_lowest = get_post_meta( $id, '_pcspecs_lowest_price', true );
			return ! empty( $meta_lowest ) ? floatval( $meta_lowest ) : floatval( get_post_meta( $id, '_hwsync_lowest_price', true ) );
		}
	}

	if ( ! function_exists( 'pcspecs_render_price_table' ) ) {
		function pcspecs_render_price_table( $post_id = 0 ) {
			if ( empty( $post_id ) && function_exists( 'get_the_ID' ) ) {
				$post_id = get_the_ID();
			}
			$component_id = get_post_meta( $post_id, '_pcspecs_component_id', true ) ?: get_post_meta( $post_id, '_hwsync_component_id', true );
			if ( ! $component_id ) {
				$component_id = $post_id;
			}
			if ( $component_id && function_exists( 'do_shortcode' ) ) {
				return do_shortcode( '[hwsync_price_table id="' . intval( $component_id ) . '"]' );
			}
			return '';
		}
	}

	if ( ! function_exists( 'hwsync_get_vendor_prices' ) ) {
		function hwsync_get_vendor_prices( $id = 0 ) {
			return pcspecs_get_vendor_prices( $id );
		}
	}

	if ( ! function_exists( 'hwsync_get_lowest_price' ) ) {
		function hwsync_get_lowest_price( $id = 0 ) {
			return pcspecs_get_lowest_price( $id );
		}
	}

	if ( ! function_exists( 'hwsync_render_price_table' ) ) {
		function hwsync_render_price_table( $id = 0 ) {
			return pcspecs_render_price_table( $id );
		}
	}
}
