<?php
namespace HWsync;

use HWsync\Models\Component;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Public_Handler {

	public static function init() {
		add_shortcode( 'hwsync_price_table', array( __CLASS__, 'render_price_table_shortcode' ) );
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
			font-weight: 600;
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
			font-size: 1.1rem;
			font-weight: 700;
			color: #0f172a;
		}
		.hwsync-original-price {
			color: #94a3b8;
			font-size: 0.85rem;
			margin-left: 6px;
		}
		.hwsync-best-tag {
			display: inline-block;
			margin-left: 6px;
			background: #16a34a;
			color: #fff;
			font-size: 0.7rem;
			padding: 2px 6px;
			border-radius: 4px;
			text-transform: uppercase;
			font-weight: bold;
		}
		.hwsync-buy-btn {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			background: #2563eb;
			color: #ffffff !important;
			padding: 6px 14px;
			border-radius: 6px;
			text-decoration: none !important;
			font-weight: 600;
			font-size: 0.85rem;
			transition: background 0.2s;
		}
		.hwsync-buy-btn:hover {
			background: #1d4ed8;
		}
		.hwsync-btn-disabled {
			background: #94a3b8;
		}
		.hwsync-disclaimer {
			margin-top: 12px;
			color: #94a3b8;
		}
		";
	}
}
