<?php
namespace HWsync;

use HWsync\Models\Component;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Public_Handler {

	public static function init() {
		add_shortcode( 'hwsync_price_table', array( __CLASS__, 'render_price_table_shortcode' ) );
		add_shortcode( 'pcspecs_prices', array( __CLASS__, 'render_price_table_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
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
		.hwsync-vendor-cell {
			font-weight: 600;
			color: #1e293b;
		}
		.hwsync-product-cell {
			color: #475569;
		}
		.hwsync-product-link {
			color: #2563eb;
			text-decoration: none;
			font-weight: 500;
		}
		.hwsync-product-link:hover {
			text-decoration: underline;
		}
		.hwsync-price-cell {
			font-weight: 700;
			font-size: 1.05rem;
			color: #0f172a;
		}
		.hwsync-lowest-price {
			color: #16a34a !important;
		}
		.hwsync-badge-lowest {
			display: inline-block;
			background: #dcfce7;
			color: #15803d;
			font-size: 0.75rem;
			font-weight: 700;
			padding: 2px 6px;
			border-radius: 4px;
			margin-left: 6px;
		}
		.hwsync-original-price {
			font-size: 0.8rem;
			color: #94a3b8;
			text-decoration: line-through;
			margin-left: 6px;
			font-weight: 400;
		}
		.hwsync-stock-in {
			color: #16a34a;
			font-weight: 600;
			font-size: 0.85rem;
		}
		.hwsync-stock-out {
			color: #dc2626;
			font-weight: 600;
			font-size: 0.85rem;
		}
		.hwsync-action-cell {
			text-align: right;
		}
		.hwsync-buy-btn {
			display: inline-block;
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
