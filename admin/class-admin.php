<?php
namespace HWsync;

use HWsync\Models\Vendor;
use HWsync\Models\Component;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_post_hwsync_manual_sync', array( __CLASS__, 'handle_manual_sync' ) );
		add_action( 'admin_post_hwsync_toggle_vendor', array( __CLASS__, 'handle_toggle_vendor' ) );
		add_action( 'admin_post_hwsync_export_csv', array( __CLASS__, 'handle_export_csv' ) );
		add_action( 'admin_post_hwsync_restore_csv', array( __CLASS__, 'handle_restore_csv' ) );
		add_action( 'admin_post_hwsync_wipe_reset', array( __CLASS__, 'handle_wipe_reset' ) );
		add_action( 'admin_post_hwsync_restore_default_vendors', array( __CLASS__, 'handle_restore_default_vendors' ) );
		add_action( 'admin_post_hwsync_save_schedule', array( __CLASS__, 'handle_save_schedule_settings' ) );
		add_action( 'wp_ajax_hwsync_sync_batch', array( __CLASS__, 'handle_sync_batch' ) );
		add_action( 'wp_ajax_hwsync_sync_specs_chunk', array( __CLASS__, 'handle_sync_specs_chunk' ) );
		add_action( 'wp_ajax_hwsync_stream_sync', array( __CLASS__, 'handle_stream_sync' ) );
		add_action( 'wp_ajax_hwsync_stream_specs_sync', array( __CLASS__, 'handle_stream_specs_sync' ) );
		add_action( 'wp_ajax_hwsync_process_browser_batch', array( __CLASS__, 'handle_browser_batch' ) );
		add_action( 'wp_ajax_hwsync_save_vendor', array( __CLASS__, 'handle_save_vendor' ) );
		add_action( 'wp_ajax_hwsync_delete_vendor', array( __CLASS__, 'handle_delete_vendor' ) );
		add_action( 'wp_ajax_hwsync_test_vendor_sync', array( __CLASS__, 'handle_test_vendor_sync' ) );
		add_action( 'wp_ajax_hwsync_merge_components', array( __CLASS__, 'handle_merge_components' ) );
		add_action( 'wp_ajax_hwsync_get_component_prices', array( __CLASS__, 'handle_get_component_prices' ) );
		add_action( 'wp_ajax_hwsync_manual_merge_components', array( __CLASS__, 'handle_manual_merge_components' ) );
		add_action( 'wp_ajax_hwsync_unmerge_vendor_price', array( __CLASS__, 'handle_unmerge_vendor_price' ) );
		add_action( 'wp_ajax_hwsync_stream_image_sync', array( __CLASS__, 'handle_stream_image_sync' ) );
		add_action( 'wp_ajax_hwsync_sync_image_chunk', array( __CLASS__, 'handle_sync_image_chunk' ) );
		add_action( 'wp_ajax_hwsync_clear_component_specs', array( __CLASS__, 'handle_clear_component_specs' ) );
		add_action( 'wp_ajax_hwsync_get_component_specs', array( __CLASS__, 'handle_get_component_specs' ) );
		add_action( 'wp_ajax_hwsync_save_component_specs', array( __CLASS__, 'handle_save_component_specs' ) );
		add_action( 'admin_post_hwsync_export_amazon_csv', array( __CLASS__, 'handle_export_amazon_csv' ) );
		add_action( 'wp_ajax_hwsync_export_amazon_csv', array( __CLASS__, 'handle_export_amazon_csv' ) );
		add_action( 'wp_ajax_hwsync_import_amazon_csv', array( __CLASS__, 'handle_import_amazon_csv' ) );
		add_action( 'wp_ajax_hwsync_delete_vendor_records', array( __CLASS__, 'handle_delete_vendor_records' ) );
		add_action( 'wp_ajax_hwsync_delete_components', array( __CLASS__, 'handle_delete_components' ) );
	}

	public static function register_admin_menu() {
		add_menu_page(
			__( 'HWsync Dashboard', 'hwsync' ),
			__( 'HWsync', 'hwsync' ),
			'manage_options',
			'hwsync-dashboard',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-randomize',
			26
		);

		add_submenu_page(
			'hwsync-dashboard',
			__( 'Dashboard & Sync Control', 'hwsync' ),
			__( 'Dashboard', 'hwsync' ),
			'manage_options',
			'hwsync-dashboard',
			array( __CLASS__, 'render_dashboard_page' )
		);

		add_submenu_page(
			'hwsync-dashboard',
			__( 'Vendor Management', 'hwsync' ),
			__( 'Vendors', 'hwsync' ),
			'manage_options',
			'hwsync-vendors',
			array( __CLASS__, 'render_vendors_page' )
		);

		add_submenu_page(
			'hwsync-dashboard',
			__( 'Component Catalog', 'hwsync' ),
			__( 'Component Catalog', 'hwsync' ),
			'manage_options',
			'hwsync-components',
			array( __CLASS__, 'render_components_page' )
		);

		add_submenu_page(
			'hwsync-dashboard',
			__( 'Backup & Maintenance', 'hwsync' ),
			__( 'Maintenance & Reset', 'hwsync' ),
			'manage_options',
			'hwsync-maintenance',
			array( __CLASS__, 'render_maintenance_page' )
		);
	}

	public static function render_dashboard_page() {
		global $wpdb;
		$vendors_table = Database::get_table_name( 'vendors' );
		$components_table = Database::get_table_name( 'components' );
		$prices_table = Database::get_table_name( 'vendor_prices' );

		$total_vendors = $wpdb->get_var( "SELECT COUNT(*) FROM {$vendors_table} WHERE is_active = 1" );
		$total_components = $wpdb->get_var( "SELECT COUNT(*) FROM {$components_table}" );
		$total_prices = $wpdb->get_var( "SELECT COUNT(*) FROM {$prices_table}" );
		$in_stock_prices = $wpdb->get_var( "SELECT COUNT(*) FROM {$prices_table} WHERE is_in_stock = 1" );
		$last_report = get_option( 'hwsync_last_sync_report', array() );
		?>
		<div class="wrap">
			<h1><span class="dashicons dashicons-randomize" style="font-size: 32px; width: 32px; height: 32px;"></span> <?php esc_html_e( 'HWsync - PC Hardware & Price Synchronizer', 'hwsync' ); ?></h1>
			<hr/>

			<?php if ( isset( $_GET['sync_status'] ) && $_GET['sync_status'] === 'success' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Manual sync completed successfully!', 'hwsync' ); ?></p></div>
			<?php endif; ?>

			<!-- Top Summary Cards -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 20px 0;">
				<div style="background: #fff; border-left: 4px solid #2563eb; padding: 16px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
					<h3 style="margin:0 0 8px 0; color: #64748b; font-size: 13px; text-transform: uppercase;"><?php esc_html_e( 'Active Vendors', 'hwsync' ); ?></h3>
					<div id="stat-active-vendors" style="font-size: 28px; font-weight: bold; color: #1e293b;"><?php echo intval( $total_vendors ); ?></div>
				</div>
				<div style="background: #fff; border-left: 4px solid #16a34a; padding: 16px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
					<h3 style="margin:0 0 8px 0; color: #64748b; font-size: 13px; text-transform: uppercase;"><?php esc_html_e( 'Canonical Components', 'hwsync' ); ?></h3>
					<div id="stat-canonical-components" style="font-size: 28px; font-weight: bold; color: #1e293b;"><?php echo intval( $total_components ); ?></div>
				</div>
				<div style="background: #fff; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
					<h3 style="margin:0 0 8px 0; color: #64748b; font-size: 13px; text-transform: uppercase;"><?php esc_html_e( 'Live Vendor Prices', 'hwsync' ); ?></h3>
					<div id="stat-vendor-prices" style="font-size: 28px; font-weight: bold; color: #1e293b;"><?php echo intval( $total_prices ); ?> <small id="stat-instock-prices" style="font-size: 14px; color: #16a34a;">(<?php echo intval( $in_stock_prices ); ?> in stock)</small></div>
				</div>
			</div>

			<!-- Main 2-Column Section: Control Form on Left, Live Console on Right -->
			<div style="display: grid; grid-template-columns: minmax(320px, 1fr) minmax(460px, 1.4fr); gap: 20px; margin-bottom: 24px; align-items: stretch;">
				
				<!-- Left Column: Sync Controls -->
				<div style="background: #fff; padding: 22px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between;">
					<div>
						<h2 style="margin-top:0; font-size: 18px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-controls-play" style="color: #2563eb;"></span>
							<?php esc_html_e( 'Trigger Manual Sync', 'hwsync' ); ?>
						</h2>
						<p style="color: #64748b; font-size: 13px; margin-bottom: 18px;">
							<?php esc_html_e( 'Initiate an on-demand scrape and match cycle across Indian PC retail vendors, updating component tables and creating/updating WordPress posts.', 'hwsync' ); ?>
						</p>

						<form id="hwsync-sync-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'hwsync_manual_sync_action', 'hwsync_nonce' ); ?>
							<input type="hidden" name="action" value="hwsync_manual_sync" />

							<div style="margin-bottom: 16px;">
								<label for="target_vendor" style="display: block; font-weight: 600; margin-bottom: 6px; color: #334155;"><?php esc_html_e( 'Target Vendor', 'hwsync' ); ?></label>
								<select name="target_vendor" id="target_vendor" style="width: 100%; max-width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1;">
									<option value="all"><?php esc_html_e( 'All Active Retailers', 'hwsync' ); ?></option>
									<?php foreach ( Vendor::get_all() as $v ) : ?>
										<option value="<?php echo esc_attr( $v->vendor_slug ); ?>"><?php echo esc_html( $v->vendor_name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div style="margin-bottom: 16px;">
								<label for="target_category" style="display: block; font-weight: 600; margin-bottom: 6px; color: #334155;"><?php esc_html_e( 'Category', 'hwsync' ); ?></label>
								<select name="target_category" id="target_category" style="width: 100%; max-width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1;">
									<option value="all"><?php esc_html_e( 'All Hardware Categories', 'hwsync' ); ?></option>
									<option value="cpu"><?php esc_html_e( 'Processors (CPU)', 'hwsync' ); ?></option>
									<option value="gpu"><?php esc_html_e( 'Graphics Cards (GPU)', 'hwsync' ); ?></option>
									<option value="motherboard"><?php esc_html_e( 'Motherboards', 'hwsync' ); ?></option>
									<option value="ram"><?php esc_html_e( 'Memory (RAM)', 'hwsync' ); ?></option>
									<option value="storage"><?php esc_html_e( 'Storage (SSDs/HDDs)', 'hwsync' ); ?></option>
									<option value="psu"><?php esc_html_e( 'Power Supply Units (PSU)', 'hwsync' ); ?></option>
									<option value="cooler"><?php esc_html_e( 'Coolers', 'hwsync' ); ?></option>
									<option value="cabinet"><?php esc_html_e( 'Cabinets', 'hwsync' ); ?></option>
								</select>
							</div>

							<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 15px;">
								<button type="button" id="btn-start-live-sync" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; padding: 6px 14px; font-weight: 600; border-radius: 6px; height: 38px; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-update" style="line-height: 1;"></span>
									<span><?php esc_html_e( 'Live Scrape', 'hwsync' ); ?></span>
								</button>
								<button type="button" id="btn-sync-specs" class="button" style="background: #0284c7; border-color: #0369a1; color: #fff; padding: 6px 12px; font-weight: 600; border-radius: 6px; height: 38px; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-admin-generic" style="line-height: 1;"></span>
									<span><?php esc_html_e( 'Sync Specs', 'hwsync' ); ?></span>
								</button>
								<button type="button" id="btn-sync-images" class="button" style="background: #8b5cf6; border-color: #7c3aed; color: #fff; padding: 6px 12px; font-weight: 600; border-radius: 6px; height: 38px; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-format-image" style="line-height: 1;"></span>
									<span><?php esc_html_e( 'Sync Images', 'hwsync' ); ?></span>
								</button>
								<button type="button" id="btn-merge-components" class="button" style="background: #f59e0b; border-color: #d97706; color: #fff; padding: 6px 12px; font-weight: 600; border-radius: 6px; height: 38px; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-randomize" style="line-height: 1;"></span>
									<span><?php esc_html_e( 'Merge', 'hwsync' ); ?></span>
								</button>
								<button type="button" id="btn-stop-sync" class="button" style="display: none; border-color: #ef4444; color: #ef4444; height: 38px; border-radius: 6px;">
									<?php esc_html_e( 'Stop', 'hwsync' ); ?>
								</button>
							</div>

							<div style="margin-top: 10px;">
								<label style="font-size: 11.5px; color: #64748b; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
									<input type="checkbox" id="chk-force-images" value="1" /> <?php esc_html_e( 'Force re-download photos for products that already have images', 'hwsync' ); ?>
								</label>
							</div>
						</form>
					</div>
				</div>

				<!-- Right Column: Live Sync Console -->
				<div style="background: #0f172a; border-radius: 8px; border: 1px solid #1e293b; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.2); display: flex; flex-direction: column; overflow: hidden; min-height: 380px;">
					
					<!-- Console Top Bar -->
					<div style="background: #1e293b; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155;">
						<div style="display: flex; align-items: center; gap: 10px;">
							<div id="console-status-dot" style="width: 10px; height: 10px; border-radius: 50%; background: #64748b; box-shadow: 0 0 6px rgba(100,116,139,0.6);"></div>
							<strong style="color: #f8fafc; font-size: 13px; letter-spacing: 0.5px;"><?php esc_html_e( 'LIVE SYNC CONSOLE', 'hwsync' ); ?></strong>
							<span id="console-status-badge" style="background: #334155; color: #94a3b8; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: bold; text-transform: uppercase;">IDLE</span>
						</div>
						<div style="display: flex; align-items: center; gap: 12px;">
							<label style="color: #94a3b8; font-size: 11px; display: flex; align-items: center; gap: 4px; cursor: pointer;">
								<input type="checkbox" id="chk-autoscroll" checked style="margin: 0;" /> <?php esc_html_e( 'Auto-scroll', 'hwsync' ); ?>
							</label>
							<button type="button" id="btn-clear-console" style="background: transparent; border: 1px solid #475569; color: #cbd5e1; font-size: 11px; padding: 2px 8px; border-radius: 4px; cursor: pointer;">
								<?php esc_html_e( 'Clear', 'hwsync' ); ?>
							</button>
						</div>
					</div>

					<!-- Console Live Metrics Counter Pills -->
					<div style="background: #131d31; padding: 8px 16px; border-bottom: 1px solid #1e293b; display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; font-size: 11px; text-align: center;">
						<div style="background: #1e293b; padding: 4px 6px; border-radius: 4px; color: #94a3b8;">
							Scraped: <strong id="m-scraped" style="color: #38bdf8;">0</strong>
						</div>
						<div style="background: #1e293b; padding: 4px 6px; border-radius: 4px; color: #94a3b8;">
							Matched: <strong id="m-matched" style="color: #a855f7;">0</strong>
						</div>
						<div style="background: #1e293b; padding: 4px 6px; border-radius: 4px; color: #94a3b8;">
							Prices: <strong id="m-prices" style="color: #facc15;">0</strong>
						</div>
						<div style="background: #1e293b; padding: 4px 6px; border-radius: 4px; color: #94a3b8;">
							Specs: <strong id="m-specs" style="color: #4ade80;">0</strong>
						</div>
						<div style="background: #1e293b; padding: 4px 6px; border-radius: 4px; color: #94a3b8;">
							Images: <strong id="m-images" style="color: #c084fc;">0</strong>
						</div>
					</div>

					<!-- Terminal Stream Area -->
					<div id="hwsync-terminal" style="flex: 1; padding: 14px 16px; overflow-y: auto; max-height: 320px; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 12px; line-height: 1.6; color: #e2e8f0; background: #0b0f19;">
						<div class="log-line log-muted" style="color: #64748b;">
							<span style="color: #475569;">[--:--:--]</span> HWsync Live Engine ready. Select options and click "Live Scrape", "Sync Specs", or "Sync Images".
						</div>
					</div>

				</div>
			</div>

			<!-- Live Streaming JS Controller -->
			<script>
			window.hwsyncVendorsRegistry = <?php echo wp_json_encode( array_map( function( $v ) {
				return array(
					'id'          => intval( $v->id ),
					'name'        => $v->vendor_name,
					'slug'        => $v->vendor_slug,
					'base_url'    => $v->base_url,
					'sync_method' => $v->sync_method ?: 'curl_html',
					'endpoints'   => $v->get_config()['endpoints'] ?? array(),
					'is_active'   => intval( $v->is_active ),
				);
			}, Vendor::get_all() ) ); ?>;

			document.addEventListener('DOMContentLoaded', function() {
				var startBtn = document.getElementById('btn-start-live-sync');
				var syncSpecsBtn = document.getElementById('btn-sync-specs');
				var mergeBtn = document.getElementById('btn-merge-components');
				var stopBtn = document.getElementById('btn-stop-sync');
				var clearBtn = document.getElementById('btn-clear-console');
				var terminal = document.getElementById('hwsync-terminal');
				var statusDot = document.getElementById('console-status-dot');
				var statusBadge = document.getElementById('console-status-badge');
				var chkAutoScroll = document.getElementById('chk-autoscroll');

				var mScraped = document.getElementById('m-scraped');
				var mMatched = document.getElementById('m-matched');
				var mPrices = document.getElementById('m-prices');
				var mSpecs = document.getElementById('m-specs');

				var abortController = null;

				function appendLog(level, message, timestamp) {
					var timeStr = timestamp || new Date().toTimeString().split(' ')[0];
					var line = document.createElement('div');
					line.style.marginBottom = '3px';
					line.style.wordBreak = 'break-word';

					var levelColors = {
						'info': '#38bdf8',
						'match': '#c084fc',
						'price': '#facc15',
						'success': '#4ade80',
						'finish': '#4ade80',
						'warning': '#fbbf24',
						'error': '#f87171',
						'debug': '#64748b'
					};
					var color = levelColors[level] || '#e2e8f0';

					line.innerHTML = '<span style="color:#64748b; user-select:none;">[' + timeStr + ']</span> ' +
						'<span style="color:' + color + '; font-weight:bold; text-transform:uppercase;">[' + level + ']</span> ' +
						'<span style="color:#f1f5f9;">' + escapeHtml(message) + '</span>';

					terminal.appendChild(line);

					if (chkAutoScroll.checked) {
						terminal.scrollTop = terminal.scrollHeight;
					}
				}


				function escapeHtml(text) {
					if (typeof text !== 'string') return text;
					var map = {
						'&': '&amp;',
						'<': '&lt;',
						'>': '&gt;',
						'"': '&quot;',
						"'": '&#039;'
					};
					return text.replace(/[&<>"']/g, function(m) { return map[m]; });
				}

				clearBtn.addEventListener('click', function() {
					terminal.innerHTML = '';
				});

				startBtn.addEventListener('click', function() {
					var vendor = document.getElementById('target_vendor').value;
					var category = document.getElementById('target_category').value;
					var nonce = document.querySelector('input[name="hwsync_nonce"]').value;

					startBtn.disabled = true;
					syncSpecsBtn.disabled = true;
					if (mergeBtn) mergeBtn.disabled = true;
					startBtn.innerHTML = '<span class="dashicons dashicons-update spin" style="animation: rotation 1s infinite linear;"></span> Syncing...';
					stopBtn.style.display = 'inline-block';

					statusDot.style.background = '#22c55e';
					statusDot.style.boxShadow = '0 0 10px #22c55e';
					statusBadge.textContent = 'RUNNING';
					statusBadge.style.background = '#15803d';
					statusBadge.style.color = '#fff';

					appendLog('info', 'Starting live scrape sync for Vendor: [' + vendor + '], Category: [' + category + ']...');

					abortController = new AbortController();

					runChunkedMainSync(vendor, category, nonce);
				});

				syncSpecsBtn.addEventListener('click', function() {
					var category = document.getElementById('target_category').value;
					var nonce = document.querySelector('input[name="hwsync_nonce"]').value;

					startBtn.disabled = true;
					syncSpecsBtn.disabled = true;
					if (mergeBtn) mergeBtn.disabled = true;
					syncSpecsBtn.innerHTML = '<span class="dashicons dashicons-update spin" style="animation: rotation 1s infinite linear;"></span> Syncing Specs...';
					stopBtn.style.display = 'inline-block';

					statusDot.style.background = '#0284c7';
					statusDot.style.boxShadow = '0 0 10px #0284c7';
					statusBadge.textContent = 'SPECS SYNC';
					statusBadge.style.background = '#0369a1';
					statusBadge.style.color = '#fff';

					appendLog('info', 'Initiating Technical Specifications extraction for category [' + category + ']...');

					abortController = new AbortController();

					runChunkedSpecsSync(category, nonce);
				});

				var syncImagesBtn = document.getElementById('btn-sync-images');
				var mImages = document.getElementById('m-images');

				if (syncImagesBtn) {
					syncImagesBtn.addEventListener('click', function() {
						var category = document.getElementById('target_category').value;
						var nonce = document.querySelector('input[name="hwsync_nonce"]').value;
						var force = document.getElementById('chk-force-images') && document.getElementById('chk-force-images').checked ? 1 : 0;

						startBtn.disabled = true;
						syncSpecsBtn.disabled = true;
						syncImagesBtn.disabled = true;
						if (mergeBtn) mergeBtn.disabled = true;
						syncImagesBtn.innerHTML = '<span class="dashicons dashicons-update spin" style="animation: rotation 1s infinite linear;"></span> Syncing Images...';
						stopBtn.style.display = 'inline-block';

						statusDot.style.background = '#8b5cf6';
						statusDot.style.boxShadow = '0 0 10px #8b5cf6';
						statusBadge.textContent = 'IMAGE SYNC';
						statusBadge.style.background = '#7c3aed';
						statusBadge.style.color = '#fff';

						appendLog('info', 'Initiating Product Image Sync for category [' + category + '] (Force: ' + (force ? 'Yes' : 'No') + ')...');

						abortController = new AbortController();

						runChunkedImageSync(category, nonce, force);
					});
				}

				if (mergeBtn) {
					mergeBtn.addEventListener('click', function() {
						var category = document.getElementById('target_category').value;
						var nonce = document.querySelector('input[name="hwsync_nonce"]').value;

						startBtn.disabled = true;
						syncSpecsBtn.disabled = true;
						if (syncImagesBtn) syncImagesBtn.disabled = true;
						mergeBtn.disabled = true;
						mergeBtn.innerHTML = '<span class="dashicons dashicons-update spin" style="animation: rotation 1s infinite linear;"></span> Merging...';
						stopBtn.style.display = 'inline-block';

						abortController = new AbortController();

						runChunkedMerge(category, nonce, function() {
							finishSync();
						});
					});
				}

				function runChunkedMainSync(vendorChoice, categoryChoice, nonce) {
					var allVendors = window.hwsyncVendorsRegistry || [];
					var activeVendors = allVendors.filter(function(v) { return v.is_active === 1; });

					var targetVendors = [];
					if (vendorChoice === 'all') {
						targetVendors = activeVendors;
					} else {
						targetVendors = allVendors.filter(function(v) { return v.slug === vendorChoice; });
						if (targetVendors.length === 0) {
							targetVendors = [{
								name: vendorChoice,
								slug: vendorChoice,
								sync_method: 'curl_html',
								base_url: '',
								endpoints: {}
							}];
						}
					}

					var allCategories = (categoryChoice === 'all') 
						? ['cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet'] 
						: [categoryChoice];

					var currentVendorIdx = 0;

					function processNextVendor() {
						if (abortController && abortController.signal.aborted) {
							appendLog('warning', 'Live Sync aborted by user.');
							finishSync();
							return;
						}

						if (currentVendorIdx >= targetVendors.length) {
							appendLog('info', '=== Automatically Running Multi-Vendor Deduplication & Merge Phase ===');
							runChunkedMerge(categoryChoice, nonce, function() {
								appendLog('success', 'Full sync & multi-vendor merge completed successfully!');
								finishSync();
							});
							return;
						}

						var curVendorObj = targetVendors[currentVendorIdx++];
						var curVendorSlug = curVendorObj.slug;
						var curVendorName = curVendorObj.name || curVendorSlug;
						var syncMethod = curVendorObj.sync_method || 'curl_html';

						appendLog('info', '=== Connecting to ' + curVendorName.toUpperCase() + ' (Method: ' + syncMethod + ') ===');

						if (syncMethod === 'browser_headless') {
							runBrowserHeadlessSyncForVendor(curVendorObj, categoryChoice, nonce, function() {
								processNextVendor();
							});
						} else {
							var curCatIdx = 0;

							function processNextCategory() {
								if (abortController && abortController.signal.aborted) {
									appendLog('warning', 'Live Sync aborted by user.');
									finishSync();
									return;
								}

								if (curCatIdx >= allCategories.length) {
									processNextVendor();
									return;
								}

								var curCat = allCategories[curCatIdx++];
								var curPage = 1;

								function fetchPageStep() {
									if (abortController && abortController.signal.aborted) {
										finishSync();
										return;
									}

									var postData = new URLSearchParams();
									postData.append('action', 'hwsync_sync_batch');
									postData.append('target_vendor', curVendorSlug);
									postData.append('target_category', curCat);
									postData.append('page', curPage);
									postData.append('hwsync_nonce', nonce);

									fetch(ajaxurl, {
										method: 'POST',
										headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
										body: postData.toString(),
										signal: abortController ? abortController.signal : null
									}).then(function(res) {
										return res.json();
									}).then(function(json) {
										if (json.success && json.data) {
											var d = json.data;
											if (d.logs && Array.isArray(d.logs)) {
												d.logs.forEach(function(l) {
													appendLog(l.level, l.message);
												});
											}

											var curScraped = parseInt(mScraped.textContent) || 0;
											var curMatched = parseInt(mMatched.textContent) || 0;
											var curPrices = parseInt(mPrices.textContent) || 0;

											mScraped.textContent = curScraped + (d.items_count || 0);
											mMatched.textContent = curMatched + (d.components || 0);
											mPrices.textContent = curPrices + (d.prices_saved || 0);

											if (d.has_more && curPage < 50) {
												curPage++;
												fetchPageStep();
											} else {
												processNextCategory();
											}
										} else {
											appendLog('warning', '[' + curVendorName + '] Category ' + curCat + ' ended on Page ' + curPage);
											processNextCategory();
										}
									}).catch(function(err) {
										appendLog('error', '[' + curVendorName + '] Category ' + curCat + ' error: ' + err.message);
										processNextCategory();
									});
								}

								fetchPageStep();
							}

							processNextCategory();
						}
					}

					processNextVendor();
				}

				function runChunkedSpecsSync(categoryChoice, nonce) {
					var offset = 0;
					var limit = 2;
					var totalProcessed = 0;

					function fetchSpecsStep() {
						if (abortController && abortController.signal.aborted) {
							appendLog('warning', 'Specs Sync aborted by user.');
							finishSync();
							return;
						}

						var postData = new URLSearchParams();
						postData.append('action', 'hwsync_sync_specs_chunk');
						postData.append('target_category', categoryChoice);
						postData.append('offset', offset);
						postData.append('limit', limit);
						postData.append('hwsync_nonce', nonce);

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: postData.toString(),
							signal: abortController ? abortController.signal : null
						}).then(function(res) {
							return res.text();
						}).then(function(rawText) {
							var json;
							try {
								json = JSON.parse(rawText);
							} catch (e) {
								appendLog('warning', 'Skipped component specs item due to server timeout. Continuing next...');
								offset += limit;
								fetchSpecsStep();
								return;
							}

							if (json.success && json.data) {
								var d = json.data;
								if (d.logs && Array.isArray(d.logs)) {
									d.logs.forEach(function(l) {
										appendLog(l.level, l.message);
									});
								}

								var curSpecs = parseInt(mSpecs.textContent) || 0;
								mSpecs.textContent = curSpecs + (d.updated || d.specs_updated || 0);
								totalProcessed += (d.processed || 0);

								if (d.has_more) {
									offset = (d.next_offset !== undefined) ? d.next_offset : (offset + limit);
									fetchSpecsStep();
								} else {
									appendLog('success', 'Specifications Extraction completed! Updated ' + mSpecs.textContent + ' components.');
									finishSync();
								}
							} else {
								appendLog('info', 'Specs sync step completed for selected items.');
								finishSync();
							}
						}).catch(function(err) {
							if (abortController && abortController.signal.aborted) return;
							appendLog('warning', 'Network interruption (' + err.message + '). Continuing next item...');
							offset += limit;
							fetchSpecsStep();
						});
					}

					fetchSpecsStep();
				}

				function runChunkedImageSync(categoryChoice, nonce, force) {
					var offset = 0;
					var limit = 2;

					function fetchImageStep() {
						if (abortController && abortController.signal.aborted) {
							appendLog('warning', 'Image Sync aborted by user.');
							finishSync();
							return;
						}

						var postData = new URLSearchParams();
						postData.append('action', 'hwsync_sync_image_chunk');
						postData.append('target_category', categoryChoice);
						postData.append('offset', offset);
						postData.append('limit', limit);
						postData.append('force_images', force);
						postData.append('hwsync_nonce', nonce);

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: postData.toString(),
							signal: abortController ? abortController.signal : null
						}).then(function(res) {
							return res.text();
						}).then(function(rawText) {
							var json;
							try {
								json = JSON.parse(rawText);
							} catch (e) {
								appendLog('warning', 'Skipped component item due to server timeout. Continuing next...');
								offset += limit;
								fetchImageStep();
								return;
							}

							if (json.success && json.data) {
								var d = json.data;
								if (d.logs && Array.isArray(d.logs)) {
									d.logs.forEach(function(l) {
										appendLog(l.level, l.message);
									});
								}

								var curImages = parseInt(mImages.textContent) || 0;
								mImages.textContent = curImages + (d.images_saved || 0);

								if (d.has_more) {
									offset += limit;
									fetchImageStep();
								} else {
									appendLog('success', 'Product Image Synchronization completed! Saved ' + mImages.textContent + ' photos.');
									finishSync();
								}
							} else {
								appendLog('info', 'Image sync completed for selected items.');
								finishSync();
							}
						}).catch(function(err) {
							if (abortController && abortController.signal.aborted) return;
							appendLog('warning', 'Network interruption (' + err.message + '). Continuing next item...');
							offset += limit;
							fetchImageStep();
						});
					}

					fetchImageStep();
				}

				function runBrowserHeadlessSyncForVendor(vendorObj, categoryChoice, nonce, nextCallback) {
					var endpoints = vendorObj.endpoints || {};
					var baseUrl = (vendorObj.base_url || '').replace(/\/+$/, '');

					var defaultPaths = {
						'cpu': '/catalog/processor',
						'gpu': '/catalog/graphics-card',
						'motherboard': '/catalog/motherboard',
						'ram': '/catalog/ram/desktop-ram',
						'storage': '/catalog/storage',
						'psu': '/catalog/smps',
						'cooler': '/cooling-system.html',
						'cabinet': '/catalog/cabinet'
					};

					var allCats = ['cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet'];
					var catsToSync = (categoryChoice === 'all') ? allCats : [categoryChoice];
					var currentCatIndex = 0;

					function processNextCategory() {
						if (currentCatIndex >= catsToSync.length) {
							appendLog('success', 'In-Browser headless sync completed for ' + vendorObj.name + '.');
							if (typeof nextCallback === 'function') {
								nextCallback();
							} else {
								finishSync();
							}
							return;
						}

						var currentCat = catsToSync[currentCatIndex++];
						var endpointPath = endpoints[currentCat] || defaultPaths[currentCat] || ('/product-category/' + currentCat);
						var baseEndpoint = baseUrl ? (baseUrl + (endpointPath.startsWith('/') ? '' : '/') + endpointPath) : endpointPath;
						var currentPage = 1;
						var maxPages = 25;

						function fetchCategoryPage(page) {
							if (page > maxPages) {
								processNextCategory();
								return;
							}

							var pageUrl = baseEndpoint + (baseEndpoint.indexOf('?') !== -1 ? '&' : '?') + 'page=' + page;
							appendLog('info', '[' + vendorObj.name + '] In-Browser Headless Request [' + currentCat + '] Page ' + page + ' (' + pageUrl + ')...');

							fetch(pageUrl, {
								method: 'GET',
								credentials: 'omit',
								signal: abortController ? abortController.signal : null
							}).then(function(resp) {
								return resp.text();
							}).then(function(htmlText) {
								var parser = new DOMParser();
								var doc = parser.parseFromString(htmlText, 'text/html');
								var productElements = doc.querySelectorAll('.product-grid-item, .product-item-container, .product-thumb, .product-layout, .product-small, li.product');

								if (!productElements || productElements.length === 0) {
									appendLog('debug', '[' + vendorObj.name + '] No more items found on Page ' + page + ' for [' + currentCat + '].');
									processNextCategory();
									return;
								}

								appendLog('info', '[' + vendorObj.name + '] Detected ' + productElements.length + ' raw cards on Page ' + page + '.');

								var parsedItems = [];
								productElements.forEach(function(el) {
									var titleEl = el.querySelector('h3 a, h4 a, h2 a, .product-entities-title a, .name a, a.woocommerce-LoopProduct-link');
									if (!titleEl) return;
									var title = titleEl.textContent.trim();
									var link = titleEl.getAttribute('href');
									if (link && !link.startsWith('http')) link = baseUrl + '/' + link.replace(/^\//, '');

									// Stock status check
									var cardText = el.textContent.toLowerCase();
									var isOutOfStock = cardText.indexOf('out of stock') !== -1 || cardText.indexOf('sold out') !== -1;
									if (isOutOfStock) {
										appendLog('debug', '[' + vendorObj.name + '] Skipped Out-of-Stock: "' + title + '"');
										return;
									}

									// Price extraction: prioritize discounted price-new and .ins first
									var price = 0;
									var origPrice = null;

									var priceNewEl = el.querySelector('.ins, ins, .price-new, .special-price, .offer-price, .sales-price, .sale-price, .current-price, .price-normal');
									var priceOldEl = el.querySelector('.del, del, .price-old, .old-price, .regular-price, .mrp, .strike');

									if (priceOldEl) {
										var opMatch = priceOldEl.textContent.replace(/,/g, '').match(/[\d]+(?:\.\d+)?/);
										if (opMatch) origPrice = parseFloat(opMatch[0]);
									}

									if (priceNewEl) {
										var pMatch = priceNewEl.textContent.replace(/,/g, '').match(/[\d]+(?:\.\d+)?/);
										if (pMatch) price = parseFloat(pMatch[0]);
									} else {
										var priceEl = el.querySelector('.price, .amount');
										if (priceEl) {
											var clone = priceEl.cloneNode(true);
											var oldEls = clone.querySelectorAll('.del, del, .price-old, .old-price, .regular-price, .price-tax, .mrp, .strike');
											oldEls.forEach(function(o) { o.remove(); });
											var pMatch = clone.textContent.replace(/,/g, '').match(/[\d]+(?:\.\d+)?/);
											if (pMatch) price = parseFloat(pMatch[0]);
										}
									}

									if (price > 0 && origPrice && price > origPrice) {
										var temp = price;
										price = origPrice;
										origPrice = temp;
									}

									var priceDisplay = (price > 0) ? '₹' + price.toFixed(2) : 'NA';

									parsedItems.push({
										title: title,
										url: link,
										price: price,
										original_price: origPrice,
										in_stock: true,
										stock_status: 'in_stock',
										category: currentCat,
										vendor_slug: vendorObj.slug,
										raw_data: { raw_title: title, display_price: priceDisplay }
									});
								});

								if (parsedItems.length === 0) {
									fetchCategoryPage(page + 1);
									return;
								}

								appendLog('info', '[' + vendorObj.name + '] Sending ' + parsedItems.length + ' in-stock items (Page ' + page + ') to database...');

								var batchData = new URLSearchParams();
								batchData.append('action', 'hwsync_process_browser_batch');
								batchData.append('vendor_slug', vendorObj.slug);
								batchData.append('items', JSON.stringify(parsedItems));
								batchData.append('hwsync_nonce', nonce);

								fetch(ajaxurl, {
									method: 'POST',
									headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
									body: batchData.toString(),
									signal: abortController ? abortController.signal : null
								}).then(function(res) {
									return res.json();
								}).then(function(json) {
									if (json.success && json.data) {
										var d = json.data;
										var curScraped = parseInt(mScraped.textContent) || 0;
										var curMatched = parseInt(mMatched.textContent) || 0;
										var curPrices = parseInt(mPrices.textContent) || 0;

										mScraped.textContent = curScraped + (d.processed || 0);
										mMatched.textContent = curMatched + (d.components || 0);
										mPrices.textContent = curPrices + (d.prices_saved || 0);

										appendLog('match', '[' + vendorObj.name + '] Page ' + page + ': Synced ' + (d.prices_saved || 0) + ' prices into component catalog.');
									}
									fetchCategoryPage(page + 1);
								}).catch(function(err) {
									appendLog('warning', '[' + vendorObj.name + '] Batch save warning: ' + err.message);
									fetchCategoryPage(page + 1);
								});
							}).catch(function(err) {
								appendLog('warning', '[' + vendorObj.name + '] In-browser request ended on Page ' + page + ': ' + err.message);
								processNextCategory();
							});
						}

						fetchCategoryPage(1);
					}

					processNextCategory();
				}

				function runChunkedMerge(categoryChoice, nonce, onDone) {
					statusDot.style.background = '#f59e0b';
					statusDot.style.boxShadow = '0 0 10px #f59e0b';
					statusBadge.textContent = 'MERGING';
					statusBadge.style.background = '#d97706';
					statusBadge.style.color = '#fff';

					appendLog('info', 'Initiating Component Merge & Deduplication for category: [' + categoryChoice + ']...');

					var postData = new URLSearchParams();
					postData.append('action', 'hwsync_merge_components');
					postData.append('target_category', categoryChoice);
					postData.append('hwsync_nonce', nonce);

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: postData.toString(),
						signal: abortController ? abortController.signal : null
					}).then(function(res) {
						return res.json();
					}).then(function(json) {
						if (json.success && json.data) {
							var d = json.data;
							if (d.logs && Array.isArray(d.logs)) {
								d.logs.forEach(function(l) {
									appendLog(l.level, l.message);
								});
							}
							if (d.total_merged > 0) {
								appendLog('success', 'Consolidated ' + d.total_merged + ' duplicate records! Active canonical hardware components: ' + d.canonical_total);
							} else {
								appendLog('info', 'All records already fully canonical. Active components: ' + d.canonical_total);
							}
						} else {
							appendLog('warning', 'Merge step returned no changes or encountered an error.');
						}
						if (typeof onDone === 'function') {
							onDone();
						}
					}).catch(function(err) {
						appendLog('error', 'Merge error: ' + err.message);
						if (typeof onDone === 'function') {
							onDone();
						}
					});
				}

				stopBtn.addEventListener('click', function() {
					if (abortController) {
						abortController.abort();
					}
					finishSync();
				});

				function finishSync() {
					startBtn.disabled = false;
					startBtn.innerHTML = '<span class="dashicons dashicons-update"></span> <?php esc_html_e( "Live Scrape", "hwsync" ); ?>';
					syncSpecsBtn.disabled = false;
					syncSpecsBtn.innerHTML = '<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( "Sync Specs", "hwsync" ); ?>';
					if (syncImagesBtn) {
						syncImagesBtn.disabled = false;
						syncImagesBtn.innerHTML = '<span class="dashicons dashicons-format-image"></span> <?php esc_html_e( "Sync Images", "hwsync" ); ?>';
					}
					if (mergeBtn) {
						mergeBtn.disabled = false;
						mergeBtn.innerHTML = '<span class="dashicons dashicons-randomize"></span> <?php esc_html_e( "Merge", "hwsync" ); ?>';
					}
					stopBtn.style.display = 'none';

					statusDot.style.background = '#64748b';
					statusDot.style.boxShadow = 'none';
					statusBadge.textContent = 'COMPLETED';
					statusBadge.style.background = '#334155';
					statusBadge.style.color = '#94a3b8';
				}
			});
			</script>
			<style>
			@keyframes rotation {
				from { transform: rotate(0deg); }
				to { transform: rotate(359deg); }
			}
			</style>

			<?php if ( ! empty( $last_report ) ) : ?>
				<div style="background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 6px;">
					<h2><?php esc_html_e( 'Last Sync Report', 'hwsync' ); ?></h2>
					<p><strong><?php esc_html_e( 'Completed At:', 'hwsync' ); ?></strong> <?php echo esc_html( $last_report['completed_at'] ?? 'N/A' ); ?></p>
					<ul>
						<li><strong><?php esc_html_e( 'Items Scraped:', 'hwsync' ); ?></strong> <?php echo intval( $last_report['total_items_fetched'] ?? 0 ); ?></li>
						<li><strong><?php esc_html_e( 'Components Synced:', 'hwsync' ); ?></strong> <?php echo intval( $last_report['components_processed'] ?? 0 ); ?></li>
						<li><strong><?php esc_html_e( 'Prices Updated:', 'hwsync' ); ?></strong> <?php echo intval( $last_report['prices_updated'] ?? 0 ); ?></li>
						<li><strong><?php esc_html_e( 'WP Posts Created/Updated:', 'hwsync' ); ?></strong> <?php echo intval( $last_report['posts_synced'] ?? 0 ); ?></li>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_vendors_page() {
		$vendors = Vendor::get_all();
		$nonce   = wp_create_nonce( 'hwsync_manual_sync_action' );
		?>
		<div class="wrap hwsync-vendors-wrap">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
				<div>
					<h1 style="margin: 0 0 4px 0; font-size: 24px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Registered PC Hardware Retailers', 'hwsync' ); ?></h1>
					<p style="margin: 0; color: #64748b; font-size: 13px;"><?php esc_html_e( 'Manage multi-vendor scrapers, add custom retailers, and test 1-component sample extraction across all hardware categories.', 'hwsync' ); ?></p>
				</div>
				<div style="display: flex; gap: 8px; align-items: center;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
						<?php wp_nonce_field( 'hwsync_restore_default_vendors_action', 'hwsync_nonce' ); ?>
						<input type="hidden" name="action" value="hwsync_restore_default_vendors" />
						<button type="submit" class="button" style="height: 38px; padding: 0 14px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; border-color: #cbd5e1; color: #475569;">
							<span class="dashicons dashicons-update" style="color: #64748b;"></span> <?php esc_html_e( 'Sync / Restore Core Retailers', 'hwsync' ); ?>
						</button>
					</form>
					<button type="button" id="btn-open-add-vendor" class="button button-primary" style="height: 38px; padding: 0 16px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: #2563eb; border-color: #1d4ed8;">
						<span class="dashicons dashicons-plus-alt2" style="margin-top: 1px;"></span> <?php esc_html_e( 'Add New Retailer', 'hwsync' ); ?>
					</button>
				</div>
			</div>

			<!-- Retailers Table -->
			<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<table class="wp-list-table widefat fixed striped" style="border: none;">
					<thead>
						<tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
							<th style="padding: 12px 16px; font-weight: 600; color: #475569;"><?php esc_html_e( 'Retailer Name', 'hwsync' ); ?></th>
							<th style="padding: 12px 16px; font-weight: 600; color: #475569; width: 140px;"><?php esc_html_e( 'Slug', 'hwsync' ); ?></th>
							<th style="padding: 12px 16px; font-weight: 600; color: #475569;"><?php esc_html_e( 'Base Store URL', 'hwsync' ); ?></th>
							<th style="padding: 12px 16px; font-weight: 600; color: #475569; width: 160px;"><?php esc_html_e( 'Sync Method', 'hwsync' ); ?></th>
							<th style="padding: 12px 16px; font-weight: 600; color: #475569; width: 90px; text-align: center;"><?php esc_html_e( 'Status', 'hwsync' ); ?></th>
							<th style="padding: 12px 16px; font-weight: 600; color: #475569; width: 150px;"><?php esc_html_e( 'Last Sync', 'hwsync' ); ?></th>
							<th style="padding: 12px 16px; font-weight: 600; color: #475569; width: 230px; text-align: right;"><?php esc_html_e( 'Actions', 'hwsync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $vendors ) ) : ?>
							<tr><td colspan="7" style="padding: 24px; text-align: center; color: #64748b;"><?php esc_html_e( 'No vendors found. Click "Add New Retailer" to register one.', 'hwsync' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $vendors as $vendor ) : 
								$cfg = $vendor->get_config();
								$endpoints_json = esc_attr( wp_json_encode( $cfg['endpoints'] ?? array() ) );
								$method_label = $vendor->get_sync_method_label();
								$method_badge_bg = '#f1f5f9';
								$method_badge_fg = '#475569';
								if ( $vendor->sync_method === 'shopify_json' ) {
									$method_badge_bg = '#f0fdf4';
									$method_badge_fg = '#16a34a';
								} elseif ( $vendor->sync_method === 'browser_headless' ) {
									$method_badge_bg = '#f5f3ff';
									$method_badge_fg = '#7c3aed';
								}
							?>
								<tr id="vendor-row-<?php echo esc_attr( $vendor->id ); ?>">
									<td style="padding: 12px 16px; vertical-align: middle;">
										<strong style="font-size: 14px; color: #0f172a;"><?php echo esc_html( $vendor->vendor_name ); ?></strong>
										<?php if ( ! empty( $vendor->adapter_class ) ) : ?>
											<span style="font-size: 10px; background: #e0f2fe; color: #0284c7; padding: 2px 6px; border-radius: 4px; font-weight: 600; margin-left: 6px;">BUILTIN</span>
										<?php else : ?>
											<span style="font-size: 10px; background: #fef3c7; color: #d97706; padding: 2px 6px; border-radius: 4px; font-weight: 600; margin-left: 6px;">CUSTOM</span>
										<?php endif; ?>
									</td>
									<td style="padding: 12px 16px; vertical-align: middle;">
										<code style="font-size: 12px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #334155;"><?php echo esc_html( $vendor->vendor_slug ); ?></code>
									</td>
									<td style="padding: 12px 16px; vertical-align: middle;">
										<a href="<?php echo esc_url( $vendor->base_url ); ?>" target="_blank" style="color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
											<?php echo esc_html( $vendor->base_url ); ?> <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px;"></span>
										</a>
									</td>
									<td style="padding: 12px 16px; vertical-align: middle;">
										<span style="font-size: 11px; font-weight: 600; background: <?php echo esc_attr( $method_badge_bg ); ?>; color: <?php echo esc_attr( $method_badge_fg ); ?>; padding: 3px 8px; border-radius: 12px; display: inline-block;">
											<?php echo esc_html( $method_label ); ?>
										</span>
									</td>
									<td style="padding: 12px 16px; vertical-align: middle; text-align: center;">
										<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=hwsync_toggle_vendor&vendor_id=' . $vendor->id ) ); ?>" style="text-decoration: none;">
											<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 700; border-radius: 12px; background: <?php echo $vendor->is_active ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $vendor->is_active ? '#15803d' : '#64748b'; ?>;">
												<?php echo $vendor->is_active ? esc_html__( 'Active', 'hwsync' ) : esc_html__( 'Disabled', 'hwsync' ); ?>
											</span>
										</a>
									</td>
									<td style="padding: 12px 16px; vertical-align: middle; color: #64748b; font-size: 12px;">
										<?php echo esc_html( $vendor->last_sync_at ?: __( 'Never', 'hwsync' ) ); ?>
									</td>
									<td style="padding: 12px 16px; vertical-align: middle; text-align: right;">
										<button type="button" class="button btn-test-vendor" 
											data-id="<?php echo esc_attr( $vendor->id ); ?>"
											data-name="<?php echo esc_attr( $vendor->vendor_name ); ?>"
											data-slug="<?php echo esc_attr( $vendor->vendor_slug ); ?>"
											data-url="<?php echo esc_attr( $vendor->base_url ); ?>"
											data-method="<?php echo esc_attr( $vendor->sync_method ?: 'curl_html' ); ?>"
											data-endpoints="<?php echo $endpoints_json; ?>"
											style="background: #f0fdf4; border-color: #bbf7d0; color: #16a34a; font-weight: 600; font-size: 12px; margin-right: 4px;">
											<span class="dashicons dashicons-dashboard" style="margin-top: 2px; font-size: 15px; width: 15px; height: 15px;"></span> <?php esc_html_e( 'Test Sync', 'hwsync' ); ?>
										</button>
										<button type="button" class="button btn-edit-vendor"
											data-id="<?php echo esc_attr( $vendor->id ); ?>"
											data-name="<?php echo esc_attr( $vendor->vendor_name ); ?>"
											data-slug="<?php echo esc_attr( $vendor->vendor_slug ); ?>"
											data-url="<?php echo esc_attr( $vendor->base_url ); ?>"
											data-method="<?php echo esc_attr( $vendor->sync_method ?: 'curl_html' ); ?>"
											data-active="<?php echo esc_attr( $vendor->is_active ); ?>"
											data-endpoints="<?php echo $endpoints_json; ?>"
											style="font-size: 12px; margin-right: 4px;">
											<?php esc_html_e( 'Edit', 'hwsync' ); ?>
										</button>
										<?php if ( empty( $vendor->adapter_class ) ) : ?>
											<button type="button" class="button btn-delete-vendor" data-id="<?php echo esc_attr( $vendor->id ); ?>" data-name="<?php echo esc_attr( $vendor->vendor_name ); ?>" style="color: #dc2626; border-color: #fecaca; font-size: 12px;">
												<?php esc_html_e( 'Delete', 'hwsync' ); ?>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Add / Edit Vendor Modal -->
			<div id="modal-vendor-form" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 100000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
				<div style="background: #fff; width: 680px; max-width: 95%; max-height: 90vh; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); overflow-y: auto; display: flex; flex-direction: column;">
					<div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
						<h2 id="modal-vendor-title" style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Add New Hardware Retailer', 'hwsync' ); ?></h2>
						<button type="button" id="btn-close-vendor-modal" style="background: none; border: none; font-size: 20px; color: #64748b; cursor: pointer;">&times;</button>
					</div>
					<form id="form-vendor-save" style="padding: 24px; flex: 1;">
						<input type="hidden" id="v-id" name="vendor_id" value="0" />
						<input type="hidden" name="action" value="hwsync_save_vendor" />
						<input type="hidden" name="hwsync_nonce" value="<?php echo esc_attr( $nonce ); ?>" />

						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
							<div>
								<label style="display: block; font-weight: 600; font-size: 12px; color: #334155; margin-bottom: 4px;"><?php esc_html_e( 'Retailer Name *', 'hwsync' ); ?></label>
								<input type="text" id="v-name" name="vendor_name" placeholder="e.g. Vedant Computers" class="regular-text" style="width: 100%;" required />
							</div>
							<div>
								<label style="display: block; font-weight: 600; font-size: 12px; color: #334155; margin-bottom: 4px;"><?php esc_html_e( 'Slug (Identifier) *', 'hwsync' ); ?></label>
								<input type="text" id="v-slug" name="vendor_slug" placeholder="e.g. vedantcomputers" class="regular-text" style="width: 100%;" required />
							</div>
						</div>

						<div style="margin-bottom: 16px;">
							<label style="display: block; font-weight: 600; font-size: 12px; color: #334155; margin-bottom: 4px;"><?php esc_html_e( 'Base Store URL *', 'hwsync' ); ?></label>
							<input type="url" id="v-url" name="base_url" placeholder="https://www.vedantcomputers.com" class="regular-text" style="width: 100%;" required />
						</div>

						<div style="margin-bottom: 20px;">
							<label style="display: block; font-weight: 600; font-size: 12px; color: #334155; margin-bottom: 4px;"><?php esc_html_e( 'Sync Scraper Method *', 'hwsync' ); ?></label>
							<select id="v-method" name="sync_method" style="width: 100%; height: 36px;">
								<option value="curl_html"><?php esc_html_e( 'cURL HTML (Standard / WooCommerce / OpenCart / HTML Scraper)', 'hwsync' ); ?></option>
								<option value="shopify_json"><?php esc_html_e( 'cURL Shopify REST JSON (/collections/{slug}/products.json)', 'hwsync' ); ?></option>
								<option value="browser_headless"><?php esc_html_e( 'In-Browser Headless (Client-Side DOM Fetch & Parser)', 'hwsync' ); ?></option>
							</select>
							<p style="margin: 4px 0 0; font-size: 11px; color: #64748b;"><?php esc_html_e( 'Choose how this retailer will be scraped: Native server-side cURL HTML, Shopify REST API endpoint, or In-Browser Headless browser request.', 'hwsync' ); ?></p>
						</div>

						<!-- Category Endpoints / Paths -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
								<strong style="font-size: 13px; color: #0f172a;"><?php esc_html_e( 'Category URL Paths & Endpoints', 'hwsync' ); ?></strong>
								<div style="display: flex; gap: 6px;">
									<button type="button" class="button button-small btn-preset" data-preset="woo"><?php esc_html_e( 'WooCommerce Preset', 'hwsync' ); ?></button>
									<button type="button" class="button button-small btn-preset" data-preset="shopify"><?php esc_html_e( 'Shopify Preset', 'hwsync' ); ?></button>
									<button type="button" class="button button-small btn-preset" data-preset="opencart"><?php esc_html_e( 'OpenCart Preset', 'hwsync' ); ?></button>
								</div>
							</div>
							<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569;">CPU (Processor):</label>
									<input type="text" id="ep-cpu" name="endpoints[cpu]" placeholder="/product-category/processor/" style="width: 100%; font-size: 12px;" />
								</div>
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569;">GPU (Graphics Card):</label>
									<input type="text" id="ep-gpu" name="endpoints[gpu]" placeholder="/product-category/graphics-card/" style="width: 100%; font-size: 12px;" />
								</div>
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569;">Motherboard:</label>
									<input type="text" id="ep-motherboard" name="endpoints[motherboard]" placeholder="/product-category/motherboard/" style="width: 100%; font-size: 12px;" />
								</div>
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569;">RAM (Memory):</label>
									<input type="text" id="ep-ram" name="endpoints[ram]" placeholder="/product-category/ram/" style="width: 100%; font-size: 12px;" />
								</div>
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569;">Storage (SSD / HDD):</label>
									<input type="text" id="ep-storage" name="endpoints[storage]" placeholder="/product-category/ssd/" style="width: 100%; font-size: 12px;" />
								</div>
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569;">PSU (Power Supply):</label>
									<input type="text" id="ep-psu" name="endpoints[psu]" placeholder="/product-category/smps/" style="width: 100%; font-size: 12px;" />
								</div>
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569;">Cooler (AIO / Air):</label>
									<input type="text" id="ep-cooler" name="endpoints[cooler]" placeholder="/product-category/cpu-cooler/" style="width: 100%; font-size: 12px;" />
								</div>
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569;">Cabinet (Chassis):</label>
									<input type="text" id="ep-cabinet" name="endpoints[cabinet]" placeholder="/product-category/cabinet/" style="width: 100%; font-size: 12px;" />
								</div>
							</div>
						</div>

						<div style="margin-bottom: 24px;">
							<label style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: #334155; font-size: 13px; cursor: pointer;">
								<input type="checkbox" id="v-active" name="is_active" value="1" checked /> <?php esc_html_e( 'Enable Retailer for Catalog Sync', 'hwsync' ); ?>
							</label>
						</div>

						<div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
							<button type="button" id="btn-cancel-vendor" class="button"><?php esc_html_e( 'Cancel', 'hwsync' ); ?></button>
							<button type="submit" id="btn-save-vendor" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; padding: 0 20px; font-weight: 600;">
								<?php esc_html_e( 'Save Retailer', 'hwsync' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<!-- Live Scraper & Test Sync Modal -->
			<div id="modal-test-sync" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.7); z-index: 100000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
				<div style="background: #fff; width: 880px; max-width: 95%; max-height: 90vh; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow-y: auto; display: flex; flex-direction: column;">
					
					<div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #0f172a; color: #fff;">
						<div style="display: flex; align-items: center; gap: 10px;">
							<span class="dashicons dashicons-dashboard" style="color: #38bdf8; font-size: 22px; width: 22px; height: 22px;"></span>
							<div>
								<h2 style="margin: 0; font-size: 16px; font-weight: 700; color: #f8fafc; display: flex; align-items: center; gap: 8px;">
									<?php esc_html_e( 'Live Scraper & Sync Test', 'hwsync' ); ?>
									<span id="test-vendor-badge" style="font-size: 12px; background: #1e293b; color: #38bdf8; padding: 2px 8px; border-radius: 10px; font-weight: normal;">Vendor</span>
								</h2>
								<div id="test-vendor-sub" style="font-size: 11px; color: #94a3b8;">https://...</div>
							</div>
						</div>
						<button type="button" id="btn-close-test-modal" style="background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer;">&times;</button>
					</div>

					<div style="padding: 20px 24px; flex: 1;">
						
						<!-- Controls bar -->
						<div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
							<div style="display: flex; align-items: center; gap: 12px;">
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 2px;"><?php esc_html_e( 'Testing Sync Method:', 'hwsync' ); ?></label>
									<select id="test-select-method" style="font-size: 12px; height: 32px;">
										<option value="curl_html"><?php esc_html_e( 'cURL (Standard HTML / WooCommerce / OpenCart)', 'hwsync' ); ?></option>
										<option value="shopify_json"><?php esc_html_e( 'cURL (Shopify REST JSON)', 'hwsync' ); ?></option>
										<option value="browser_headless"><?php esc_html_e( 'In-Browser Headless (Client-Side DOM)', 'hwsync' ); ?></option>
									</select>
								</div>
								<div>
									<label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 2px;"><?php esc_html_e( 'Scope:', 'hwsync' ); ?></label>
									<select id="test-select-scope" style="font-size: 12px; height: 32px;">
										<option value="all"><?php esc_html_e( 'All 8 Hardware Categories (1 sample each)', 'hwsync' ); ?></option>
										<option value="cpu"><?php esc_html_e( 'CPU only', 'hwsync' ); ?></option>
										<option value="gpu"><?php esc_html_e( 'GPU only', 'hwsync' ); ?></option>
										<option value="motherboard"><?php esc_html_e( 'Motherboard only', 'hwsync' ); ?></option>
										<option value="ram"><?php esc_html_e( 'RAM only', 'hwsync' ); ?></option>
										<option value="storage"><?php esc_html_e( 'Storage only', 'hwsync' ); ?></option>
										<option value="psu"><?php esc_html_e( 'PSU only', 'hwsync' ); ?></option>
										<option value="cooler"><?php esc_html_e( 'Cooler only', 'hwsync' ); ?></option>
										<option value="cabinet"><?php esc_html_e( 'Cabinet only', 'hwsync' ); ?></option>
									</select>
								</div>
							</div>
							<div>
								<button type="button" id="btn-run-live-test" class="button button-primary" style="height: 36px; padding: 0 18px; font-weight: 600; font-size: 13px; background: #16a34a; border-color: #15803d; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-controls-play" style="margin-top: 1px;"></span> <?php esc_html_e( 'Run Test Sync', 'hwsync' ); ?>
								</button>
							</div>
						</div>

						<!-- Test Output Table -->
						<div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
							<table class="wp-list-table widefat fixed striped" style="margin: 0; border: none;">
								<thead>
									<tr style="background: #f1f5f9;">
										<th style="width: 120px; font-weight: 600; color: #475569;"><?php esc_html_e( 'Category', 'hwsync' ); ?></th>
										<th style="width: 100px; font-weight: 600; color: #475569;"><?php esc_html_e( 'Status', 'hwsync' ); ?></th>
										<th style="font-weight: 600; color: #475569;"><?php esc_html_e( 'Sample Extracted Component', 'hwsync' ); ?></th>
										<th style="width: 120px; font-weight: 600; color: #475569;"><?php esc_html_e( 'Offer Price', 'hwsync' ); ?></th>
										<th style="width: 100px; font-weight: 600; color: #475569;"><?php esc_html_e( 'Stock / SKU', 'hwsync' ); ?></th>
										<th style="width: 80px; font-weight: 600; color: #475569; text-align: right;"><?php esc_html_e( 'Time', 'hwsync' ); ?></th>
									</tr>
								</thead>
								<tbody id="test-results-body">
									<?php 
									$cats = array(
										'cpu' => 'CPU (Processor)',
										'gpu' => 'GPU (Graphics Card)',
										'motherboard' => 'Motherboard',
										'ram' => 'RAM (Memory)',
										'storage' => 'Storage (SSD/HDD)',
										'psu' => 'PSU (Power Supply)',
										'cooler' => 'CPU Cooler',
										'cabinet' => 'Cabinet Chassis',
									);
									foreach ( $cats as $ckey => $clabel ) : ?>
										<tr id="test-row-<?php echo esc_attr( $ckey ); ?>">
											<td><strong><?php echo esc_html( $clabel ); ?></strong></td>
											<td class="col-status"><span style="color: #94a3b8; font-size: 11px;">READY</span></td>
											<td class="col-title" style="color: #64748b;">-</td>
											<td class="col-price" style="color: #64748b;">-</td>
											<td class="col-stock" style="color: #64748b;">-</td>
											<td class="col-time" style="color: #94a3b8; text-align: right;">-</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

					</div>
				</div>
			</div>

			<!-- Scripts for Vendor Management and Testing -->
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				var nonce = '<?php echo esc_js( $nonce ); ?>';
				var modalVendor = document.getElementById('modal-vendor-form');
				var modalTest = document.getElementById('modal-test-sync');
				var formVendor = document.getElementById('form-vendor-save');

				// Preset endpoints map
				var presets = {
					woo: {
						cpu: '/product-category/processor/',
						gpu: '/product-category/graphics-card/',
						motherboard: '/product-category/motherboard/',
						ram: '/product-category/ram/',
						storage: '/product-category/ssd/',
						psu: '/product-category/smps/',
						cooler: '/product-category/cpu-cooler/',
						cabinet: '/product-category/cabinet/'
					},
					shopify: {
						cpu: 'processor',
						gpu: 'graphic-cards',
						motherboard: 'motherboard',
						ram: 'ram',
						storage: 'solid-state-drives',
						psu: 'power-supply',
						cooler: 'pc-coolers',
						cabinet: 'pc-cabinet'
					},
					opencart: {
						cpu: '/catalog/processor',
						gpu: '/catalog/graphics-card',
						motherboard: '/catalog/motherboard',
						ram: '/catalog/ram/desktop-ram',
						storage: '/catalog/storage',
						psu: '/catalog/smps',
						cooler: '/cooling-system.html',
						cabinet: '/catalog/cabinet'
					}
				};

				// Presets click
				document.querySelectorAll('.btn-preset').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var type = this.getAttribute('data-preset');
						var p = presets[type];
						if (p) {
							Object.keys(p).forEach(function(cat) {
								var inp = document.getElementById('ep-' + cat);
								if (inp) inp.value = p[cat];
							});
						}
					});
				});

				// Auto-slugify on name typing
				document.getElementById('v-name').addEventListener('input', function() {
					if (!document.getElementById('v-id').value || document.getElementById('v-id').value === '0') {
						document.getElementById('v-slug').value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
					}
				});

				// Open Add Modal
				document.getElementById('btn-open-add-vendor').addEventListener('click', function() {
					document.getElementById('modal-vendor-title').textContent = '<?php esc_html_e( "Add New Hardware Retailer", "hwsync" ); ?>';
					document.getElementById('v-id').value = '0';
					document.getElementById('v-name').value = '';
					document.getElementById('v-slug').value = '';
					document.getElementById('v-url').value = '';
					document.getElementById('v-method').value = 'curl_html';
					document.getElementById('v-active').checked = true;

					var defaultEndpoints = presets.woo;
					Object.keys(defaultEndpoints).forEach(function(cat) {
						var inp = document.getElementById('ep-' + cat);
						if (inp) inp.value = defaultEndpoints[cat];
					});

					modalVendor.style.display = 'flex';
				});

				// Close Vendor Modal
				document.getElementById('btn-close-vendor-modal').addEventListener('click', function() {
					modalVendor.style.display = 'none';
				});
				document.getElementById('btn-cancel-vendor').addEventListener('click', function() {
					modalVendor.style.display = 'none';
				});

				// Edit Vendor click
				document.querySelectorAll('.btn-edit-vendor').forEach(function(btn) {
					btn.addEventListener('click', function() {
						document.getElementById('modal-vendor-title').textContent = '<?php esc_html_e( "Edit Retailer Settings", "hwsync" ); ?>';
						document.getElementById('v-id').value = this.getAttribute('data-id');
						document.getElementById('v-name').value = this.getAttribute('data-name');
						document.getElementById('v-slug').value = this.getAttribute('data-slug');
						document.getElementById('v-url').value = this.getAttribute('data-url');
						document.getElementById('v-method').value = this.getAttribute('data-method') || 'curl_html';
						document.getElementById('v-active').checked = (this.getAttribute('data-active') === '1');

						var eps = {};
						try {
							eps = JSON.parse(this.getAttribute('data-endpoints') || '{}');
						} catch (e) {}

						var cats = ['cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet'];
						cats.forEach(function(cat) {
							var inp = document.getElementById('ep-' + cat);
							if (inp) inp.value = eps[cat] || '';
						});

						modalVendor.style.display = 'flex';
					});
				});

				// Save Vendor Form Submit
				formVendor.addEventListener('submit', function(e) {
					e.preventDefault();
					var saveBtn = document.getElementById('btn-save-vendor');
					saveBtn.disabled = true;
					saveBtn.textContent = 'Saving...';

					var formData = new FormData(formVendor);

					fetch(ajaxurl, {
						method: 'POST',
						body: new URLSearchParams(formData)
					}).then(function(res) {
						return res.json();
					}).then(function(json) {
						saveBtn.disabled = false;
						saveBtn.textContent = '<?php esc_html_e( "Save Retailer", "hwsync" ); ?>';
						if (json.success) {
							window.location.reload();
						} else {
							alert(json.data && json.data.message ? json.data.message : 'Error saving vendor.');
						}
					}).catch(function(err) {
						saveBtn.disabled = false;
						saveBtn.textContent = '<?php esc_html_e( "Save Retailer", "hwsync" ); ?>';
						alert('Save error: ' + err.message);
					});
				});

				// Delete Vendor
				document.querySelectorAll('.btn-delete-vendor').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var id = this.getAttribute('data-id');
						var name = this.getAttribute('data-name');
						if (confirm('Are you sure you want to delete retailer "' + name + '" and all its linked price listings?')) {
							var postData = new URLSearchParams();
							postData.append('action', 'hwsync_delete_vendor');
							postData.append('vendor_id', id);
							postData.append('hwsync_nonce', nonce);

							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: postData.toString()
							}).then(function(res) {
								return res.json();
							}).then(function(json) {
								if (json.success) {
									var row = document.getElementById('vendor-row-' + id);
									if (row) row.remove();
								} else {
									alert(json.data && json.data.message ? json.data.message : 'Error deleting vendor.');
								}
							});
						}
					});
				});

				// Test Sync Modal Variables
				var currentTestVendor = null;

				document.querySelectorAll('.btn-test-vendor').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var eps = {};
						try {
							eps = JSON.parse(this.getAttribute('data-endpoints') || '{}');
						} catch (e) {}

						currentTestVendor = {
							id: this.getAttribute('data-id'),
							name: this.getAttribute('data-name'),
							slug: this.getAttribute('data-slug'),
							url: this.getAttribute('data-url'),
							method: this.getAttribute('data-method') || 'curl_html',
							endpoints: eps
						};

						document.getElementById('test-vendor-badge').textContent = currentTestVendor.name;
						document.getElementById('test-vendor-sub').textContent = currentTestVendor.url + ' (' + currentTestVendor.slug + ')';
						document.getElementById('test-select-method').value = currentTestVendor.method;

						// Reset table rows
						resetTestResultsTable();

						modalTest.style.display = 'flex';
					});
				});

				document.getElementById('btn-close-test-modal').addEventListener('click', function() {
					modalTest.style.display = 'none';
				});

				function resetTestResultsTable() {
					var cats = ['cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet'];
					cats.forEach(function(cat) {
						var row = document.getElementById('test-row-' + cat);
						if (row) {
							row.querySelector('.col-status').innerHTML = '<span style="color: #94a3b8; font-size: 11px;">READY</span>';
							row.querySelector('.col-title').textContent = '-';
							row.querySelector('.col-price').textContent = '-';
							row.querySelector('.col-stock').textContent = '-';
							row.querySelector('.col-time').textContent = '-';
						}
					});
				}

				// Run Live Test
				document.getElementById('btn-run-live-test').addEventListener('click', function() {
					if (!currentTestVendor) return;

					var method = document.getElementById('test-select-method').value;
					var scope = document.getElementById('test-select-scope').value;
					var testBtn = document.getElementById('btn-run-live-test');

					testBtn.disabled = true;
					testBtn.innerHTML = '<span class="dashicons dashicons-update spin" style="animation: rotation 1s infinite linear;"></span> Testing...';

					var catsToTest = (scope === 'all') 
						? ['cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet'] 
						: [scope];

					resetTestResultsTable();

					var catIndex = 0;

					function runNextCategoryTest() {
						if (catIndex >= catsToTest.length) {
							testBtn.disabled = false;
							testBtn.innerHTML = '<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( "Run Test Sync", "hwsync" ); ?>';
							return;
						}

						var currentCat = catsToTest[catIndex++];
						var row = document.getElementById('test-row-' + currentCat);
						if (!row) {
							runNextCategoryTest();
							return;
						}

						row.querySelector('.col-status').innerHTML = '<span style="color: #0284c7; font-size: 11px; font-weight: bold;"><span class="dashicons dashicons-update spin" style="font-size: 14px; width: 14px; height: 14px; animation: rotation 1s infinite linear;"></span> TESTING</span>';

						if (method === 'browser_headless') {
							// Client-Side In-Browser Headless Test
							var endpointPath = (currentTestVendor.endpoints && currentTestVendor.endpoints[currentCat]) 
								? currentTestVendor.endpoints[currentCat] 
								: (presets.opencart[currentCat] || '/');
							
							var targetUrl = currentTestVendor.url + (endpointPath.startsWith('/') ? '' : '/') + endpointPath;
							var startTime = performance.now();

							fetch(targetUrl, { method: 'GET', credentials: 'omit' })
								.then(function(resp) { return resp.text(); })
								.then(function(html) {
									var duration = Math.round(performance.now() - startTime);
									var parser = new DOMParser();
									var doc = parser.parseFromString(html, 'text/html');
									var cards = doc.querySelectorAll('.product-grid-item, .product-item-container, .product-thumb, .product-layout, .product-small, li.product');

									if (cards && cards.length > 0) {
										var card = cards[0];
										var titleEl = card.querySelector('h3 a, h4 a, h2 a, .product-entities-title a, .name a, a.woocommerce-LoopProduct-link');
										var title = titleEl ? titleEl.textContent.trim() : 'Sample Product';
										var link = titleEl ? titleEl.getAttribute('href') : targetUrl;
										if (link && !link.startsWith('http')) link = currentTestVendor.url + '/' + link.replace(/^\//, '');

										var priceNew = card.querySelector('.price-new, .special-price, ins .amount');
										var price = 0;
										if (priceNew) {
											var m = priceNew.textContent.replace(/,/g, '').match(/[\d]+(?:\.\d+)?/);
											if (m) price = parseFloat(m[0]);
										} else {
											var pEl = card.querySelector('.price, .amount');
											if (pEl) {
												var cl = pEl.cloneNode(true);
												cl.querySelectorAll('.price-old, del, .price-tax').forEach(function(e) { e.remove(); });
												var m = cl.textContent.replace(/,/g, '').match(/[\d]+(?:\.\d+)?/);
												if (m) price = parseFloat(m[0]);
											}
										}

										row.querySelector('.col-status').innerHTML = '<span style="color: #16a34a; font-size: 11px; font-weight: bold; background: #dcfce7; padding: 2px 6px; border-radius: 4px;">✓ SUCCESS</span>';
										row.querySelector('.col-title').innerHTML = '<a href="' + link + '" target="_blank" style="color: #0f172a; text-decoration: none; font-weight: 600;">' + escapeHtml(title) + '</a>';
										row.querySelector('.col-price').innerHTML = '<strong style="color: #16a34a;">' + (price > 0 ? '₹' + price.toLocaleString('en-IN', {minimumFractionDigits: 2}) : 'NA') + '</strong>';
										row.querySelector('.col-stock').innerHTML = '<span style="color: #16a34a; font-size: 11px;">In Stock</span>';
										row.querySelector('.col-time').textContent = duration + 'ms';
									} else {
										row.querySelector('.col-status').innerHTML = '<span style="color: #d97706; font-size: 11px; font-weight: bold; background: #fef3c7; padding: 2px 6px; border-radius: 4px;">NO ITEMS</span>';
										row.querySelector('.col-title').textContent = 'No product cards detected on endpoint (' + targetUrl + ')';
										row.querySelector('.col-time').textContent = duration + 'ms';
									}
									runNextCategoryTest();
								}).catch(function(err) {
									var duration = Math.round(performance.now() - startTime);
									row.querySelector('.col-status').innerHTML = '<span style="color: #dc2626; font-size: 11px; font-weight: bold; background: #fee2e2; padding: 2px 6px; border-radius: 4px;">ERROR</span>';
									row.querySelector('.col-title').textContent = err.message;
									row.querySelector('.col-time').textContent = duration + 'ms';
									runNextCategoryTest();
								});
						} else {
							// Server-Side cURL / Shopify Test
							var postData = new URLSearchParams();
							postData.append('action', 'hwsync_test_vendor_sync');
							postData.append('vendor_slug', currentTestVendor.slug);
							postData.append('sync_method', method);
							postData.append('category', currentCat);
							postData.append('base_url', currentTestVendor.url);
							postData.append('endpoint', (currentTestVendor.endpoints && currentTestVendor.endpoints[currentCat]) ? currentTestVendor.endpoints[currentCat] : '');
							postData.append('hwsync_nonce', nonce);

							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: postData.toString()
							}).then(function(res) {
								return res.json();
							}).then(function(json) {
								if (json.success && json.data) {
									var d = json.data;
									if (d.success && d.sample_item) {
										var it = d.sample_item;
										row.querySelector('.col-status').innerHTML = '<span style="color: #16a34a; font-size: 11px; font-weight: bold; background: #dcfce7; padding: 2px 6px; border-radius: 4px;">✓ SUCCESS</span>';
										row.querySelector('.col-title').innerHTML = '<a href="' + it.url + '" target="_blank" style="color: #0f172a; text-decoration: none; font-weight: 600;">' + escapeHtml(it.title) + '</a>';
										row.querySelector('.col-price').innerHTML = '<strong style="color: #16a34a;">' + it.display_price + '</strong>';
										row.querySelector('.col-stock').innerHTML = '<span style="color: ' + (it.in_stock ? '#16a34a' : '#dc2626') + '; font-size: 11px;">' + (it.in_stock ? 'In Stock' : 'Out of Stock') + (it.sku ? '<br/>' + it.sku : '') + '</span>';
										row.querySelector('.col-time').textContent = (d.duration_ms || 0) + 'ms';
									} else {
										row.querySelector('.col-status').innerHTML = '<span style="color: #d97706; font-size: 11px; font-weight: bold; background: #fef3c7; padding: 2px 6px; border-radius: 4px;">NO ITEMS</span>';
										row.querySelector('.col-title').textContent = d.message || 'No products returned by adapter.';
										row.querySelector('.col-time').textContent = (d.duration_ms || 0) + 'ms';
									}
								} else {
									row.querySelector('.col-status').innerHTML = '<span style="color: #dc2626; font-size: 11px; font-weight: bold; background: #fee2e2; padding: 2px 6px; border-radius: 4px;">ERROR</span>';
									row.querySelector('.col-title').textContent = (json.data && json.data.message) ? json.data.message : 'Server test error';
								}
								runNextCategoryTest();
							}).catch(function(err) {
								row.querySelector('.col-status').innerHTML = '<span style="color: #dc2626; font-size: 11px; font-weight: bold; background: #fee2e2; padding: 2px 6px; border-radius: 4px;">ERROR</span>';
								row.querySelector('.col-title').textContent = err.message;
								runNextCategoryTest();
							});
						}
					}

					runNextCategoryTest();
				});

				function escapeHtml(text) {
					if (typeof text !== 'string') return text;
					var map = {
						'&': '&amp;',
						'<': '&lt;',
						'>': '&gt;',
						'"': '&quot;',
						"'": '&#039;'
					};
					return text.replace(/[&<>"']/g, function(m) { return map[m]; });
				}
			});
			</script>
		</div>
		<?php
	}

	public static function render_components_page() {
		$cat_filter    = isset( $_GET['cat'] ) ? sanitize_text_field( $_GET['cat'] ) : 'all';
		$vendor_filter = isset( $_GET['vendor'] ) ? sanitize_text_field( $_GET['vendor'] ) : 'all';
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$paged         = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$limit         = 50;
		$offset        = ( $paged - 1 ) * $limit;

		$query_args = array(
			'category' => ( $cat_filter !== 'all' ) ? $cat_filter : '',
			'vendor'   => ( $vendor_filter !== 'all' ) ? $vendor_filter : '',
			'search'   => $search,
			'limit'    => $limit,
			'offset'   => $offset,
		);

		$components = Component::get_all( $query_args );
		$all_vendors = Vendor::get_all();
		$filtered_vendor_obj = ( $vendor_filter !== 'all' ) ? Vendor::find_by_slug( $vendor_filter ) : null;
		$total_count = Component::count( $query_args );
		$total_pages = ceil( $total_count / $limit );
		$nonce = wp_create_nonce( 'hwsync_manual_sync_action' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><span class="dashicons dashicons-products" style="font-size: 30px; width: 30px; height: 30px;"></span> <?php esc_html_e( 'Canonical Component Catalog', 'hwsync' ); ?></h1>
			<p style="color: #64748b; font-size: 13px; margin: 4px 0 16px 0;">
				<?php esc_html_e( 'Aggregated multi-vendor hardware components. Review store pairings, merge duplicate records, or unmerge incorrectly grouped store prices.', 'hwsync' ); ?>
			</p>

			<!-- Action & Filter Bar -->
			<div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; background: #fff; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
				
				<!-- Left: Filters & Search Form -->
				<form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
					<input type="hidden" name="page" value="hwsync-components" />

					<select name="cat" onchange="this.form.submit()" style="height: 36px; border-radius: 6px; font-size: 13px;">
						<option value="all" <?php selected( $cat_filter, 'all' ); ?>><?php esc_html_e( 'All Categories', 'hwsync' ); ?></option>
						<option value="cpu" <?php selected( $cat_filter, 'cpu' ); ?>><?php esc_html_e( 'Processors (CPU)', 'hwsync' ); ?></option>
						<option value="gpu" <?php selected( $cat_filter, 'gpu' ); ?>><?php esc_html_e( 'Graphics Cards (GPU)', 'hwsync' ); ?></option>
						<option value="motherboard" <?php selected( $cat_filter, 'motherboard' ); ?>><?php esc_html_e( 'Motherboards', 'hwsync' ); ?></option>
						<option value="ram" <?php selected( $cat_filter, 'ram' ); ?>><?php esc_html_e( 'Memory (RAM)', 'hwsync' ); ?></option>
						<option value="storage" <?php selected( $cat_filter, 'storage' ); ?>><?php esc_html_e( 'Storage (SSDs/HDDs)', 'hwsync' ); ?></option>
						<option value="psu" <?php selected( $cat_filter, 'psu' ); ?>><?php esc_html_e( 'Power Supply Units', 'hwsync' ); ?></option>
						<option value="cooler" <?php selected( $cat_filter, 'cooler' ); ?>><?php esc_html_e( 'Coolers (AIO/Air)', 'hwsync' ); ?></option>
						<option value="cabinet" <?php selected( $cat_filter, 'cabinet' ); ?>><?php esc_html_e( 'Cabinets / Cases', 'hwsync' ); ?></option>
					</select>

					<select name="vendor" onchange="this.form.submit()" style="height: 36px; border-radius: 6px; font-size: 13px; max-width: 190px;">
						<option value="all" <?php selected( $vendor_filter, 'all' ); ?>><?php esc_html_e( 'All Retailers', 'hwsync' ); ?></option>
						<?php if ( ! empty( $all_vendors ) ) : ?>
							<?php foreach ( $all_vendors as $v ) : ?>
								<option value="<?php echo esc_attr( $v->vendor_slug ); ?>" <?php selected( $vendor_filter, $v->vendor_slug ); ?>>
									<?php echo esc_html( $v->vendor_name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>

					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search brand, model, SKU...', 'hwsync' ); ?>" style="height: 36px; width: 200px; border-radius: 6px; font-size: 13px;" />

					<button type="submit" class="button" style="height: 36px; border-radius: 6px; font-weight: 600;"><?php esc_html_e( 'Filter', 'hwsync' ); ?></button>
					<?php if ( $cat_filter !== 'all' || $vendor_filter !== 'all' || ! empty( $search ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=hwsync-components' ) ); ?>" class="button" style="height: 36px; border-radius: 6px;"><?php esc_html_e( 'Reset', 'hwsync' ); ?></a>
					<?php endif; ?>
				</form>

				<!-- Right: Action Buttons -->
				<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
					<?php if ( $vendor_filter !== 'all' && $filtered_vendor_obj ) : ?>
						<button type="button" id="btn-delete-selected-vendor" class="button" disabled style="height: 36px; border-radius: 6px; font-weight: 600; background: #fef2f2; border-color: #fca5a5; color: #b91c1c; display: inline-flex; align-items: center; gap: 4px;" data-vendor-slug="<?php echo esc_attr( $vendor_filter ); ?>" data-vendor-name="<?php echo esc_attr( $filtered_vendor_obj->vendor_name ); ?>">
							<span class="dashicons dashicons-trash"></span> <?php printf( esc_html__( 'Delete Selected from %s', 'hwsync' ), esc_html( $filtered_vendor_obj->vendor_name ) ); ?> (<span id="selected-vendor-count">0</span>)
						</button>
						<button type="button" id="btn-delete-all-vendor" class="button" style="height: 36px; border-radius: 6px; font-weight: 600; background: #fee2e2; border-color: #f87171; color: #991b1b; display: inline-flex; align-items: center; gap: 4px;" data-vendor-slug="<?php echo esc_attr( $vendor_filter ); ?>" data-vendor-name="<?php echo esc_attr( $filtered_vendor_obj->vendor_name ); ?>">
							<span class="dashicons dashicons-warning"></span> <?php printf( esc_html__( 'Delete All from %s', 'hwsync' ), esc_html( $filtered_vendor_obj->vendor_name ) ); ?>
						</button>
					<?php endif; ?>
					<button type="button" id="btn-bulk-delete-selected" class="button" disabled style="height: 36px; border-radius: 6px; font-weight: 600; background: #fef2f2; border-color: #fca5a5; color: #b91c1c; display: inline-flex; align-items: center; gap: 4px;">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete Selected', 'hwsync' ); ?> (<span id="selected-delete-count">0</span>)
					</button>
					<button type="button" id="btn-bulk-clear-specs" class="button" disabled style="height: 36px; border-radius: 6px; font-weight: 600; background: #fef2f2; border-color: #fca5a5; color: #b91c1c; display: inline-flex; align-items: center; gap: 4px;">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear Specs', 'hwsync' ); ?> (<span id="selected-specs-count">0</span>)
					</button>
					<button type="button" id="btn-open-wipe-specs" class="button" style="height: 36px; border-radius: 6px; font-weight: 600; background: #fff; border-color: #cbd5e1; color: #475569; display: inline-flex; align-items: center; gap: 4px;">
						<span class="dashicons dashicons-eraser"></span> <?php esc_html_e( 'Wipe Specs...', 'hwsync' ); ?>
					</button>
					<button type="button" id="btn-bulk-merge-selected" class="button" disabled style="height: 36px; border-radius: 6px; font-weight: 600; background: #f8fafc; border-color: #cbd5e1; color: #64748b;">
						<span class="dashicons dashicons-randomize" style="line-height: 1.4;"></span> <?php esc_html_e( 'Merge Selected', 'hwsync' ); ?> (<span id="selected-comp-count">0</span>)
					</button>
					<button type="button" id="btn-open-amazon-csv" class="button" style="height: 36px; border-radius: 6px; font-weight: 600; background: #fffbeb; border-color: #fcd34d; color: #b45309; display: inline-flex; align-items: center; gap: 4px;">
						<span class="dashicons dashicons-media-spreadsheet" style="color: #d97706;"></span> <?php esc_html_e( 'Amazon CSV Tools', 'hwsync' ); ?>
					</button>
					<button type="button" id="btn-open-manual-merge" class="button button-primary" style="height: 36px; border-radius: 6px; font-weight: 600; background: #2563eb; border-color: #1d4ed8; display: inline-flex; align-items: center; gap: 4px;">
						<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Manual Merge Tool', 'hwsync' ); ?>
					</button>
				</div>
			</div>

			<!-- Component Table -->
			<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td style="width: 40px; text-align: center; vertical-align: middle;">
								<input type="checkbox" id="cb-select-all-comps" />
							</td>
							<th style="font-weight: 600; color: #334155; width: 60px;">ID</th>
							<th style="font-weight: 600; color: #334155;"><?php esc_html_e( 'Brand & Hardware Model', 'hwsync' ); ?></th>
							<th style="font-weight: 600; color: #334155; width: 130px;"><?php esc_html_e( 'Category', 'hwsync' ); ?></th>
							<th style="font-weight: 600; color: #334155; width: 140px;"><?php esc_html_e( 'MPN / SKU', 'hwsync' ); ?></th>
							<th style="font-weight: 600; color: #334155; width: 160px;"><?php esc_html_e( 'Lowest Live Price', 'hwsync' ); ?></th>
							<th style="font-weight: 600; color: #334155; width: 280px; text-align: right;"><?php esc_html_e( 'Linked Stores & Actions', 'hwsync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $components ) ) : ?>
							<tr><td colspan="7" style="padding: 30px; text-align: center; color: #64748b; font-size: 14px;"><?php esc_html_e( 'No components found matching your query.', 'hwsync' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $components as $comp ) :
								$prices = $comp->get_prices();
								$lowest = $comp->get_lowest_price();
								$price_count = count( $prices );
								$has_specs = ! empty( $comp->specs_json );
							?>
								<tr id="comp-row-<?php echo esc_attr( $comp->id ); ?>">
									<td style="text-align: center; vertical-align: middle;">
										<input type="checkbox" class="cb-comp-item" value="<?php echo esc_attr( $comp->id ); ?>" data-name="<?php echo esc_attr( $comp->brand . ' ' . $comp->model_name ); ?>" data-category="<?php echo esc_attr( $comp->category ); ?>" />
									</td>
									<td style="vertical-align: middle; color: #64748b; font-size: 12px; font-weight: 600;">
										#<?php echo intval( $comp->id ); ?>
									</td>
									<td style="vertical-align: middle;">
										<div style="display: flex; align-items: center; gap: 10px;">
											<?php if ( ! empty( $comp->get_image_url() ) ) : ?>
												<img src="<?php echo esc_url( $comp->get_image_url() ); ?>" alt="" style="width: 38px; height: 38px; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; flex-shrink: 0; padding: 2px;" />
											<?php else : ?>
												<div style="width: 38px; height: 38px; border-radius: 6px; border: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: center; color: #cbd5e1; flex-shrink: 0;">
													<span class="dashicons dashicons-format-image" style="font-size: 18px; width: 18px; height: 18px; line-height: 18px;"></span>
												</div>
											<?php endif; ?>
											<div>
												<strong style="font-size: 13.5px; color: #0f172a;"><?php echo esc_html( $comp->brand . ' ' . $comp->model_name ); ?></strong>
												<?php if ( $comp->wp_post_id ) : ?>
													<span style="font-size: 11px; margin-left: 6px; color: #64748b;">(Linked Post #<?php echo intval( $comp->wp_post_id ); ?>)</span>
												<?php endif; ?>
											</div>
										</div>
									</td>
									<td style="vertical-align: middle;">
										<span style="display: inline-block; font-size: 11px; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 12px; text-transform: uppercase;">
											<?php echo esc_html( $comp->category ); ?>
										</span>
									</td>
									<td style="vertical-align: middle; font-size: 12px; color: #475569;">
										<code><?php echo esc_html( $comp->mpn ?: ( $comp->sku ?: '-' ) ); ?></code>
									</td>
									<td style="vertical-align: middle;">
										<?php if ( $lowest && floatval( $lowest->price ) > 0 ) : ?>
											<strong style="color: #16a34a; font-size: 14px;">₹<?php echo esc_html( number_format( $lowest->price, 2 ) ); ?></strong>
											<br/><small style="color: #64748b; font-size: 11px;"><?php echo esc_html( $lowest->vendor_name ); ?></small>
										<?php else : ?>
											<span style="color: #94a3b8; font-size: 12px;">NA (Out of Stock)</span>
										<?php endif; ?>
									</td>
									<td style="vertical-align: middle; text-align: right;">
										<div style="display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end;">
											<button type="button" class="button btn-edit-comp-specs"
												data-id="<?php echo esc_attr( $comp->id ); ?>"
												data-name="<?php echo esc_attr( $comp->brand . ' ' . $comp->model_name ); ?>"
												data-category="<?php echo esc_attr( $comp->category ); ?>"
												style="background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; font-size: 11.5px; padding: 0 8px; border-radius: 4px; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;"
												title="<?php esc_attr_e( 'Edit specifications (add, modify, or remove attributes)', 'hwsync' ); ?>">
												<span class="dashicons dashicons-edit" style="font-size: 13px; line-height: 22px; width: 13px; height: 13px;"></span> <?php esc_html_e( 'Specs', 'hwsync' ); ?>
											</button>
											<?php if ( $has_specs ) : ?>
												<button type="button" class="button btn-clear-comp-specs"
													data-id="<?php echo esc_attr( $comp->id ); ?>"
													data-name="<?php echo esc_attr( $comp->brand . ' ' . $comp->model_name ); ?>"
													style="background: #fff1f2; border-color: #fecdd3; color: #be123c; font-size: 11px; padding: 0 6px; border-radius: 4px; font-weight: 600;"
													title="<?php esc_attr_e( 'Remove all specifications for this component', 'hwsync' ); ?>">
													<span class="dashicons dashicons-trash" style="font-size: 13px; line-height: 22px; width: 13px; height: 13px;"></span>
												</button>
											<?php endif; ?>
											<button type="button" class="button btn-view-comp-prices"
												data-id="<?php echo esc_attr( $comp->id ); ?>"
												data-name="<?php echo esc_attr( $comp->brand . ' ' . $comp->model_name ); ?>"
												data-category="<?php echo esc_attr( $comp->category ); ?>"
												style="background: #f0fdf4; border-color: #bbf7d0; color: #15803d; font-weight: 600; font-size: 12px; border-radius: 6px;">
												<span class="dashicons dashicons-tag" style="font-size: 14px; width: 14px; height: 14px; margin-top: 2px;"></span>
												<?php echo sprintf( esc_html__( '%d Stores Linked', 'hwsync' ), $price_count ); ?>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center; color: #64748b; font-size: 13px;">
					<div>
						<?php echo sprintf( esc_html__( 'Showing page %d of %d (%d total items)', 'hwsync' ), $paged, $total_pages, $total_count ); ?>
					</div>
					<div style="display: flex; gap: 4px;">
						<?php for ( $i = 1; $i <= $total_pages; $i++ ) : 
							if ( $i == 1 || $i == $total_pages || abs( $i - $paged ) <= 2 ) :
								$url = add_query_arg( array( 'paged' => $i, 'cat' => $cat_filter, 'vendor' => $vendor_filter, 's' => $search ) );
								$is_curr = ( $i == $paged );
						?>
							<a href="<?php echo esc_url( $url ); ?>" class="button <?php echo $is_curr ? 'button-primary' : ''; ?>" style="min-width: 32px; text-align: center;">
								<?php echo intval( $i ); ?>
							</a>
						<?php elseif ( abs( $i - $paged ) == 3 ) : ?>
							<span style="padding: 0 6px; line-height: 30px;">...</span>
						<?php endif; endfor; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Modal 1: Linked Stores & Price Management / Unmerge Modal -->
			<div id="modal-manage-prices" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 100000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
				<div style="background: #fff; width: 780px; max-width: 95%; max-height: 90vh; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow-y: auto; display: flex; flex-direction: column;">
					
					<div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
						<div>
							<h2 id="modal-prices-comp-title" style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a;">Component Linked Stores</h2>
							<span id="modal-prices-comp-cat" style="font-size: 11px; font-weight: 600; color: #0369a1; text-transform: uppercase;">CATEGORY</span>
						</div>
						<button type="button" id="btn-close-prices-modal" style="background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer;">&times;</button>
					</div>

					<div style="padding: 20px 24px; flex: 1;">
						<p style="color: #64748b; font-size: 12px; margin-top: 0; line-height: 1.5;">
							<?php esc_html_e( 'Review each retailer price listing paired to this canonical hardware item. If any store product was paired incorrectly, click "Unmerge / Split" to detach it into its own separate standalone component record.', 'hwsync' ); ?>
						</p>

						<div id="prices-loading-spinner" style="text-align: center; padding: 30px; color: #64748b; font-size: 13px;">
							<span class="dashicons dashicons-update spin" style="font-size: 24px; width: 24px; height: 24px; animation: rotation 1s infinite linear;"></span>
							<p><?php esc_html_e( 'Loading linked store listings...', 'hwsync' ); ?></p>
						</div>

						<div id="prices-table-wrapper" style="display: none;">
							<table class="widefat striped" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
								<thead>
									<tr style="background: #f8fafc;">
										<th style="font-weight: 600; color: #334155;"><?php esc_html_e( 'Retailer Store', 'hwsync' ); ?></th>
										<th style="font-weight: 600; color: #334155;"><?php esc_html_e( 'Product Listing at Store', 'hwsync' ); ?></th>
										<th style="font-weight: 600; color: #334155; width: 140px;"><?php esc_html_e( 'Live Price', 'hwsync' ); ?></th>
										<th style="font-weight: 600; color: #334155; width: 160px; text-align: right;"><?php esc_html_e( 'Pairing Action', 'hwsync' ); ?></th>
									</tr>
								</thead>
								<tbody id="prices-modal-tbody">
									<!-- Dynamic Rows Injected by JS -->
								</tbody>
							</table>
						</div>
					</div>

					<div style="display: flex; justify-content: flex-end; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
						<button type="button" id="btn-done-prices-modal" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; padding: 0 20px; font-weight: 600;">
							<?php esc_html_e( 'Done', 'hwsync' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Modal 2: Manual Component Merge Tool Modal -->
			<div id="modal-manual-merge" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 100000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
				<div style="background: #fff; width: 620px; max-width: 95%; max-height: 90vh; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow-y: auto; display: flex; flex-direction: column;">
					
					<div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
						<h2 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-randomize" style="color: #2563eb;"></span>
							<?php esc_html_e( 'Merge Components Tool', 'hwsync' ); ?>
						</h2>
						<button type="button" id="btn-close-merge-modal" style="background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer;">&times;</button>
					</div>

					<form id="form-manual-merge" style="padding: 20px 24px; flex: 1;">
						<p style="color: #64748b; font-size: 12.5px; margin-top: 0; line-height: 1.5;">
							<?php esc_html_e( 'Consolidate multiple separate component records into a single canonical hardware item. All vendor price listings will be reassigned to the Primary Component, technical specifications merged, and the redundant component deleted.', 'hwsync' ); ?>
						</p>

						<div style="margin-bottom: 16px;">
							<label style="display: block; font-weight: 600; font-size: 12px; color: #334155; margin-bottom: 6px;">
								<span class="dashicons dashicons-yes" style="color: #16a34a; font-size: 16px;"></span>
								<?php esc_html_e( 'Primary Component (Target to Retain):', 'hwsync' ); ?>
							</label>
							<select id="merge-target-select" style="width: 100%; height: 38px; border-radius: 6px;" required>
								<option value=""><?php esc_html_e( '-- Select Primary Component --', 'hwsync' ); ?></option>
								<?php foreach ( $components as $comp ) : ?>
									<option value="<?php echo esc_attr( $comp->id ); ?>">
										#<?php echo intval( $comp->id ); ?> - [<?php echo esc_html( strtoupper( $comp->category ) ); ?>] <?php echo esc_html( $comp->brand . ' ' . $comp->model_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div style="margin-bottom: 20px;">
							<label style="display: block; font-weight: 600; font-size: 12px; color: #334155; margin-bottom: 6px;">
								<span class="dashicons dashicons-no" style="color: #dc2626; font-size: 16px;"></span>
								<?php esc_html_e( 'Secondary Component (Source to Merge & Remove):', 'hwsync' ); ?>
							</label>
							<select id="merge-source-select" style="width: 100%; height: 38px; border-radius: 6px;" required>
								<option value=""><?php esc_html_e( '-- Select Component to Merge --', 'hwsync' ); ?></option>
								<?php foreach ( $components as $comp ) : ?>
									<option value="<?php echo esc_attr( $comp->id ); ?>">
										#<?php echo intval( $comp->id ); ?> - [<?php echo esc_html( strtoupper( $comp->category ) ); ?>] <?php echo esc_html( $comp->brand . ' ' . $comp->model_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div id="merge-alert-box" style="display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 12px; font-weight: 600;"></div>

						<div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
							<button type="button" id="btn-cancel-merge-modal" class="button"><?php esc_html_e( 'Cancel', 'hwsync' ); ?></button>
							<button type="submit" id="btn-submit-manual-merge" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; padding: 0 20px; font-weight: 600;">
								<?php esc_html_e( 'Merge Components Now', 'hwsync' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<!-- Modal 3: Wipe Specifications Modal -->
			<div id="modal-wipe-specs" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 100000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
				<div style="background: #fff; width: 500px; max-width: 95%; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden;">
					<div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fef2f2;">
						<div style="display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-trash" style="color: #dc2626; font-size: 20px;"></span>
							<h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #991b1b;"><?php esc_html_e( 'Wipe Technical Specifications', 'hwsync' ); ?></h3>
						</div>
						<button type="button" id="btn-close-wipe-specs-modal" style="background: none; border: none; font-size: 20px; color: #64748b; cursor: pointer;">&times;</button>
					</div>
					<div style="padding: 20px;">
						<p style="color: #475569; font-size: 13px; margin-top: 0; line-height: 1.5;">
							<?php esc_html_e( 'Select the scope of specifications to remove. This will ONLY clear the technical specs dictionary without removing components, vendor prices, or product photos.', 'hwsync' ); ?>
						</p>
						<div style="background: #f8fafc; padding: 14px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px;">
							<label style="display: block; margin-bottom: 10px; font-weight: 600; font-size: 13px; color: #1e293b; cursor: pointer;">
								<input type="radio" name="wipe_specs_scope" value="category" checked />
								<?php echo sprintf( esc_html__( 'Wipe for current filtered category only (%s)', 'hwsync' ), '<strong>' . esc_html( ucfirst( $cat_filter ) ) . '</strong>' ); ?>
							</label>
							<label style="display: block; font-weight: 600; font-size: 13px; color: #1e293b; cursor: pointer;">
								<input type="radio" name="wipe_specs_scope" value="all" />
								<?php esc_html_e( 'Wipe specifications for ALL components across entire database', 'hwsync' ); ?>
							</label>
						</div>
						<div style="display: flex; justify-content: flex-end; gap: 8px;">
							<button type="button" id="btn-cancel-wipe-specs" class="button" style="height: 36px; border-radius: 6px;"><?php esc_html_e( 'Cancel', 'hwsync' ); ?></button>
							<button type="button" id="btn-confirm-wipe-specs" class="button button-primary" style="height: 36px; border-radius: 6px; background: #dc2626; border-color: #b91c1c; font-weight: 600;"><?php esc_html_e( 'Confirm Wipe Specs', 'hwsync' ); ?></button>
						</div>
					</div>
				</div>
			</div>

			<!-- Modal 4: Edit Specifications Modal -->
			<div id="modal-edit-specs" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 100000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
				<div style="background: #fff; width: 740px; max-width: 95%; max-height: 90vh; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column;">
					
					<div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
						<div style="display: flex; align-items: center; gap: 10px;">
							<div style="background: #eff6ff; color: #2563eb; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
								<span class="dashicons dashicons-edit" style="font-size: 20px; width: 20px; height: 20px;"></span>
							</div>
							<div>
								<h2 id="modal-edit-specs-title" style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;">Edit Specifications</h2>
								<div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
									<span id="modal-edit-specs-cat" style="display: inline-block; font-size: 10.5px; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 1px 6px; border-radius: 10px; text-transform: uppercase;"></span>
									<span id="modal-edit-specs-id" style="font-size: 11px; color: #64748b;"></span>
								</div>
							</div>
						</div>
						<button type="button" id="btn-close-edit-specs-modal" style="background: none; border: none; font-size: 24px; line-height: 1; color: #64748b; cursor: pointer; padding: 0 4px;">&times;</button>
					</div>

					<div style="padding: 20px 24px; flex: 1; overflow-y: auto;">
						<div id="edit-specs-loading" style="display: none; padding: 40px; text-align: center;">
							<span class="spinner is-active" style="float: none; margin: 0 auto 10px; display: inline-block;"></span>
							<p style="color: #64748b; margin: 0; font-size: 13px; font-weight: 500;">Loading specifications...</p>
						</div>

						<div id="edit-specs-content" style="display: none;">
							<div style="margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
								<p style="margin: 0; font-size: 13px; color: #475569; line-height: 1.4;">
									<?php esc_html_e( 'Review and edit technical specifications. Delete incorrect entries, edit values, or add new specification attributes.', 'hwsync' ); ?>
								</p>
								<button type="button" id="btn-add-spec-row" class="button" style="border-color: #2563eb; color: #2563eb; font-size: 12px; font-weight: 600; background: #eff6ff; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px;">
									<span class="dashicons dashicons-plus-alt2" style="font-size: 14px; width: 14px; height: 14px; margin-top: 1px;"></span> <?php esc_html_e( 'Add Attribute', 'hwsync' ); ?>
								</button>
							</div>

							<div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 16px; background: #fff;">
								<table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
									<thead>
										<tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #334155; font-weight: 600;">
											<th style="padding: 10px 12px; width: 42%;"><?php esc_html_e( 'Specification Attribute (Key)', 'hwsync' ); ?></th>
											<th style="padding: 10px 12px; width: 48%;"><?php esc_html_e( 'Specification Value', 'hwsync' ); ?></th>
											<th style="padding: 10px 12px; width: 10%; text-align: center;"><?php esc_html_e( 'Remove', 'hwsync' ); ?></th>
										</tr>
									</thead>
									<tbody id="edit-specs-tbody">
										<!-- Dynamic Rows -->
									</tbody>
								</table>
							</div>

							<div id="edit-specs-suggestions-box" style="margin-bottom: 16px; background: #f8fafc; padding: 12px 14px; border-radius: 8px; border: 1px dashed #cbd5e1;">
								<small style="font-weight: 700; color: #475569; display: block; margin-bottom: 8px;"><?php esc_html_e( 'Suggested attributes for this category (click to add):', 'hwsync' ); ?></small>
								<div id="edit-specs-pills" style="display: flex; gap: 6px; flex-wrap: wrap;">
									<!-- Dynamic Category Pills -->
								</div>
							</div>

							<div id="edit-specs-alert-box" style="display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; font-weight: 600;"></div>
						</div>
					</div>

					<div style="padding: 14px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
						<button type="button" id="btn-cancel-edit-specs" class="button" style="height: 36px; border-radius: 6px;"><?php esc_html_e( 'Cancel', 'hwsync' ); ?></button>
						<button type="button" id="btn-save-comp-specs" class="button button-primary" style="height: 36px; border-radius: 6px; background: #2563eb; border-color: #1d4ed8; font-weight: 600; padding: 0 20px; display: inline-flex; align-items: center; gap: 6px;">
							<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Specifications', 'hwsync' ); ?>
						</button>
					</div>

				</div>
			</div>

			<!-- Modal 5: Amazon Synced Products CSV Export & Bulk Affiliate Links Updater -->
			<div id="modal-amazon-csv" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.65); z-index: 100050; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
				<div style="background: #fff; border-radius: 12px; max-width: 680px; width: 92%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1); border: 1px solid #cbd5e1;">
					
					<!-- Modal Header -->
					<div style="padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-top-left-radius: 12px; border-top-right-radius: 12px;">
						<div style="display: flex; align-items: center; gap: 10px;">
							<div style="width: 38px; height: 38px; border-radius: 8px; background: #fef3c7; display: flex; align-items: center; justify-content: center; color: #b45309;">
								<span class="dashicons dashicons-media-spreadsheet" style="font-size: 22px; width: 22px; height: 22px;"></span>
							</div>
							<div>
								<h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Amazon India CSV & Affiliate Links Manager', 'hwsync' ); ?></h3>
								<p style="margin: 2px 0 0 0; font-size: 12px; color: #64748b;"><?php esc_html_e( 'Export all synced Amazon products to CSV and bulk-update custom affiliate links.', 'hwsync' ); ?></p>
							</div>
						</div>
						<button type="button" id="btn-close-amazon-csv-modal" style="background: none; border: none; font-size: 24px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 4px;">&times;</button>
					</div>

					<!-- Modal Body -->
					<div style="padding: 24px;">
						
						<!-- Action Card 1: Export Amazon CSV -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 20px;">
							<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
								<div>
									<h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
										<span class="dashicons dashicons-download" style="color: #2563eb;"></span> <?php esc_html_e( '1. Download Amazon Synced Products CSV', 'hwsync' ); ?>
									</h4>
									<p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; max-width: 440px;">
										<?php esc_html_e( 'Downloads a CSV spreadsheet containing all canonical components currently linked to Amazon India, including ASINs, prices, and editable affiliate URL columns.', 'hwsync' ); ?>
									</p>
								</div>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hwsync_export_amazon_csv' ), 'hwsync_export_amazon_csv_action', 'hwsync_nonce' ) ); ?>" class="button button-primary" style="height: 38px; padding: 0 16px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; background: #0284c7; border-color: #0369a1;">
									<span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e( 'Export Amazon CSV', 'hwsync' ); ?>
								</a>
							</div>
						</div>

						<!-- Action Card 2: Import & Update Affiliate Links -->
						<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 18px;">
							<h4 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
								<span class="dashicons dashicons-upload" style="color: #16a34a;"></span> <?php esc_html_e( '2. Upload & Bulk Update Amazon Links', 'hwsync' ); ?>
							</h4>
							<p style="margin: 0 0 14px 0; font-size: 12px; color: #64748b; line-height: 1.5;">
								<?php esc_html_e( 'Open the downloaded CSV, edit or replace the links in the "Affiliate / Custom URL" column with your Amazon Associates affiliate tracking links, and upload it here.', 'hwsync' ); ?>
							</p>

							<form id="form-import-amazon-csv" enctype="multipart/form-data">
								<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 14px; flex-wrap: wrap;">
									<input type="file" id="amazon-csv-file-input" name="csv_file" accept=".csv,text/csv" required style="font-size: 13px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; flex: 1; min-width: 240px;" />
									<button type="submit" id="btn-submit-amazon-csv" class="button button-primary" style="height: 38px; padding: 0 20px; font-weight: 600; border-radius: 6px; background: #16a34a; border-color: #15803d; display: inline-flex; align-items: center; gap: 6px;">
										<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Upload & Update Links', 'hwsync' ); ?>
									</button>
								</div>
							</form>

							<div id="amazon-csv-alert-box" style="display: none; padding: 12px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; line-height: 1.5;"></div>
						</div>

					</div>

					<!-- Modal Footer -->
					<div style="padding: 14px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; background: #f8fafc; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
						<button type="button" id="btn-done-amazon-csv" class="button" style="height: 36px; border-radius: 6px; font-weight: 600;"><?php esc_html_e( 'Done / Close', 'hwsync' ); ?></button>
					</div>

				</div>
			</div>

			<!-- JavaScript Controller for Merge, Unmerge & Clear Specs Actions -->
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				var nonce = '<?php echo esc_js( $nonce ); ?>';
				var modalPrices = document.getElementById('modal-manage-prices');
				var modalMerge = document.getElementById('modal-manual-merge');
				var modalWipeSpecs = document.getElementById('modal-wipe-specs');
				var currentCategory = '<?php echo esc_js( $cat_filter ); ?>';
				var selectedComps = [];

				// Checkbox Multi-Select logic
				var cbAll = document.getElementById('cb-select-all-comps');
				var itemCbs = document.querySelectorAll('.cb-comp-item');
				var bulkMergeBtn = document.getElementById('btn-bulk-merge-selected');
				var bulkDeleteSelectedBtn = document.getElementById('btn-bulk-delete-selected');
				var bulkClearSpecsBtn = document.getElementById('btn-bulk-clear-specs');
				var btnDeleteSelectedVendor = document.getElementById('btn-delete-selected-vendor');
				var btnDeleteAllVendor = document.getElementById('btn-delete-all-vendor');
				var selCountLabel = document.getElementById('selected-comp-count');
				var selDeleteCountLabel = document.getElementById('selected-delete-count');
				var selSpecsCountLabel = document.getElementById('selected-specs-count');
				var selVendorCountLabel = document.getElementById('selected-vendor-count');
				var currentVendorFilter = '<?php echo esc_js( $vendor_filter ); ?>';

				function updateSelectedState() {
					selectedComps = [];
					itemCbs.forEach(function(cb) {
						if (cb.checked) {
							selectedComps.push({
								id: cb.value,
								name: cb.getAttribute('data-name'),
								category: cb.getAttribute('data-category')
							});
						}
					});

					selCountLabel.textContent = selectedComps.length;
					if (selDeleteCountLabel) selDeleteCountLabel.textContent = selectedComps.length;
					if (selSpecsCountLabel) selSpecsCountLabel.textContent = selectedComps.length;

					if (bulkDeleteSelectedBtn) {
						if (selectedComps.length >= 1) {
							bulkDeleteSelectedBtn.disabled = false;
							bulkDeleteSelectedBtn.style.background = '#dc2626';
							bulkDeleteSelectedBtn.style.borderColor = '#b91c1c';
							bulkDeleteSelectedBtn.style.color = '#fff';
						} else {
							bulkDeleteSelectedBtn.disabled = true;
							bulkDeleteSelectedBtn.style.background = '#fef2f2';
							bulkDeleteSelectedBtn.style.borderColor = '#fca5a5';
							bulkDeleteSelectedBtn.style.color = '#b91c1c';
						}
					}
					if (selVendorCountLabel) selVendorCountLabel.textContent = selectedComps.length;

					if (btnDeleteSelectedVendor) {
						if (selectedComps.length >= 1) {
							btnDeleteSelectedVendor.disabled = false;
							btnDeleteSelectedVendor.style.background = '#dc2626';
							btnDeleteSelectedVendor.style.borderColor = '#b91c1c';
							btnDeleteSelectedVendor.style.color = '#fff';
						} else {
							btnDeleteSelectedVendor.disabled = true;
							btnDeleteSelectedVendor.style.background = '#fef2f2';
							btnDeleteSelectedVendor.style.borderColor = '#fca5a5';
							btnDeleteSelectedVendor.style.color = '#b91c1c';
						}
					}

					if (selectedComps.length >= 2) {
						bulkMergeBtn.disabled = false;
						bulkMergeBtn.style.background = '#2563eb';
						bulkMergeBtn.style.borderColor = '#1d4ed8';
						bulkMergeBtn.style.color = '#fff';
					} else {
						bulkMergeBtn.disabled = true;
						bulkMergeBtn.style.background = '#f8fafc';
						bulkMergeBtn.style.borderColor = '#cbd5e1';
						bulkMergeBtn.style.color = '#64748b';
					}

					if (bulkClearSpecsBtn) {
						if (selectedComps.length >= 1) {
							bulkClearSpecsBtn.disabled = false;
							bulkClearSpecsBtn.style.background = '#dc2626';
							bulkClearSpecsBtn.style.borderColor = '#b91c1c';
							bulkClearSpecsBtn.style.color = '#fff';
						} else {
							bulkClearSpecsBtn.disabled = true;
							bulkClearSpecsBtn.style.background = '#fef2f2';
							bulkClearSpecsBtn.style.borderColor = '#fca5a5';
							bulkClearSpecsBtn.style.color = '#b91c1c';
						}
					}
				}

				if (cbAll) {
					cbAll.addEventListener('change', function() {
						itemCbs.forEach(function(cb) { cb.checked = cbAll.checked; });
						updateSelectedState();
					});
				}

				itemCbs.forEach(function(cb) {
					cb.addEventListener('change', function() {
						updateSelectedState();
					});
				});

				// Open Manual Merge Modal
				document.getElementById('btn-open-manual-merge').addEventListener('click', function() {
					modalMerge.style.display = 'flex';
				});

				document.getElementById('btn-close-merge-modal').addEventListener('click', function() {
					modalMerge.style.display = 'none';
				});
				document.getElementById('btn-cancel-merge-modal').addEventListener('click', function() {
					modalMerge.style.display = 'none';
				});

				// Bulk Merge Selected
				bulkMergeBtn.addEventListener('click', function() {
					if (selectedComps.length < 2) return;

					var target = selectedComps[0];
					var sources = selectedComps.slice(1);

					var sourceNames = sources.map(function(s) { return '"' + s.name + '"'; }).join(', ');
					if (confirm('Merge ' + sources.length + ' component(s) (' + sourceNames + ') into primary component "' + target.name + '"?')) {
						var mergeIdx = 0;
						bulkMergeBtn.disabled = true;
						bulkMergeBtn.textContent = 'Merging...';

						function mergeNextStep() {
							if (mergeIdx >= sources.length) {
								alert('Successfully merged selected components!');
								window.location.reload();
								return;
							}

							var curSource = sources[mergeIdx++];
							var postData = new URLSearchParams();
							postData.append('action', 'hwsync_manual_merge_components');
							postData.append('target_id', target.id);
							postData.append('source_id', curSource.id);
							postData.append('hwsync_nonce', nonce);

							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: postData.toString()
							}).then(function(res) {
								return res.json();
							}).then(function(json) {
								if (json.success) {
									mergeNextStep();
								} else {
									alert('Error merging: ' + (json.data && json.data.message ? json.data.message : 'Unknown error'));
									window.location.reload();
								}
							}).catch(function(err) {
								alert('Network error: ' + err.message);
								window.location.reload();
							});
						}

						mergeNextStep();
					}
				});

				// Submit Manual Merge Form
				document.getElementById('form-manual-merge').addEventListener('submit', function(e) {
					e.preventDefault();
					var targetId = document.getElementById('merge-target-select').value;
					var sourceId = document.getElementById('merge-source-select').value;
					var submitBtn = document.getElementById('btn-submit-manual-merge');
					var alertBox = document.getElementById('merge-alert-box');

					if (targetId === sourceId) {
						alertBox.style.display = 'block';
						alertBox.style.background = '#fee2e2';
						alertBox.style.color = '#dc2626';
						alertBox.textContent = 'Primary and secondary components cannot be the same.';
						return;
					}

					submitBtn.disabled = true;
					submitBtn.textContent = 'Merging...';

					var postData = new URLSearchParams();
					postData.append('action', 'hwsync_manual_merge_components');
					postData.append('target_id', targetId);
					postData.append('source_id', sourceId);
					postData.append('hwsync_nonce', nonce);

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: postData.toString()
					}).then(function(res) {
						return res.json();
					}).then(function(json) {
						submitBtn.disabled = false;
						submitBtn.textContent = '<?php esc_html_e( "Merge Components Now", "hwsync" ); ?>';
						if (json.success) {
							alert(json.data && json.data.message ? json.data.message : 'Components merged successfully!');
							window.location.reload();
						} else {
							alertBox.style.display = 'block';
							alertBox.style.background = '#fee2e2';
							alertBox.style.color = '#dc2626';
							alertBox.textContent = (json.data && json.data.message) ? json.data.message : 'Error merging components.';
						}
					}).catch(function(err) {
						submitBtn.disabled = false;
						submitBtn.textContent = '<?php esc_html_e( "Merge Components Now", "hwsync" ); ?>';
						alertBox.style.display = 'block';
						alertBox.style.background = '#fee2e2';
						alertBox.style.color = '#dc2626';
						alertBox.textContent = 'Network error: ' + err.message;
					});
				});

				// View & Unmerge Prices Modal
				var currentActiveCompId = null;

				document.querySelectorAll('.btn-view-comp-prices').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var compId = this.getAttribute('data-id');
						var compName = this.getAttribute('data-name');
						var compCat = this.getAttribute('data-category');

						currentActiveCompId = compId;
						document.getElementById('modal-prices-comp-title').textContent = compName;
						document.getElementById('modal-prices-comp-cat').textContent = compCat;

						document.getElementById('prices-loading-spinner').style.display = 'block';
						document.getElementById('prices-table-wrapper').style.display = 'none';
						modalPrices.style.display = 'flex';

						loadComponentPrices(compId);
					});
				});

				document.getElementById('btn-close-prices-modal').addEventListener('click', function() {
					modalPrices.style.display = 'none';
				});
				document.getElementById('btn-done-prices-modal').addEventListener('click', function() {
					modalPrices.style.display = 'none';
				});

				function loadComponentPrices(compId) {
					var postData = new URLSearchParams();
					postData.append('action', 'hwsync_get_component_prices');
					postData.append('component_id', compId);
					postData.append('hwsync_nonce', nonce);

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: postData.toString()
					}).then(function(res) {
						return res.text();
					}).then(function(text) {
						var json;
						try {
							json = JSON.parse(text);
						} catch(e) {
							throw new Error('Server returned invalid response: ' + text.substring(0, 100));
						}
						document.getElementById('prices-loading-spinner').style.display = 'none';
						document.getElementById('prices-table-wrapper').style.display = 'block';

						var tbody = document.getElementById('prices-modal-tbody');
						tbody.innerHTML = '';

						if (json.success && json.data && json.data.prices && json.data.prices.length > 0) {
							json.data.prices.forEach(function(p) {
								var tr = document.createElement('tr');
								tr.id = 'vp-row-' + p.id;

								var stockBadge = p.is_in_stock 
									? '<span style="color: #16a34a; font-weight: 600; font-size: 11px; background: #dcfce7; padding: 2px 6px; border-radius: 4px;">In Stock</span>'
									: '<span style="color: #dc2626; font-weight: 600; font-size: 11px; background: #fee2e2; padding: 2px 6px; border-radius: 4px;">Out of Stock</span>';

								var originalPriceHtml = p.display_original ? ' <del style="color: #94a3b8; font-size: 11px; margin-left: 4px;">' + p.display_original + '</del>' : '';

								tr.innerHTML = 
									'<td style="vertical-align: middle;">' +
										'<strong style="font-size: 13px; color: #0f172a;">' + escapeHtml(p.vendor_name) + '</strong>' +
									'</td>' +
									'<td style="vertical-align: middle;">' +
										'<a href="' + escapeHtml(p.product_url) + '" target="_blank" style="color: #2563eb; text-decoration: none; font-size: 12.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">' +
											escapeHtml(p.vendor_product_title) + ' <span class="dashicons dashicons-external" style="font-size: 13px; width: 13px; height: 13px;"></span>' +
										'</a>' +
									'</td>' +
									'<td style="vertical-align: middle;">' +
										'<strong style="color: #16a34a; font-size: 13.5px;">' + escapeHtml(p.display_price) + '</strong>' + originalPriceHtml + '<br/>' + stockBadge +
									'</td>' +
									'<td style="vertical-align: middle; text-align: right;">' +
										'<button type="button" class="button btn-unmerge-price" data-id="' + p.id + '" data-title="' + escapeHtml(p.vendor_product_title) + '" style="background: #fff; border-color: #fca5a5; color: #dc2626; font-size: 11.5px; font-weight: 600; border-radius: 6px;">' +
											'<span class="dashicons dashicons-editor-unlink" style="font-size: 14px; width: 14px; height: 14px; margin-top: 2px;"></span> <?php esc_html_e( "Unmerge / Split", "hwsync" ); ?>' +
										'</button>' +
									'</td>';

								tbody.appendChild(tr);
							});

							// Attach click handlers to unmerge buttons
							document.querySelectorAll('.btn-unmerge-price').forEach(function(unmergeBtn) {
								unmergeBtn.addEventListener('click', function() {
									var vpId = this.getAttribute('data-id');
									var vpTitle = this.getAttribute('data-title');

									var customName = prompt('Detach this listing into a new standalone component.\nEnter component name:', vpTitle);
									if (customName !== null) {
										unmergeBtn.disabled = true;
										unmergeBtn.textContent = 'Unmerging...';

										var uData = new URLSearchParams();
										uData.append('action', 'hwsync_unmerge_vendor_price');
										uData.append('vendor_price_id', vpId);
										uData.append('custom_model_name', customName);
										uData.append('hwsync_nonce', nonce);

										fetch(ajaxurl, {
											method: 'POST',
											headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
											body: uData.toString()
										}).then(function(res) {
											return res.json();
										}).then(function(json) {
											if (json.success) {
												var row = document.getElementById('vp-row-' + vpId);
												if (row) row.remove();
												alert(json.data && json.data.message ? json.data.message : 'Successfully separated into a new component!');
												window.location.reload();
											} else {
												alert('Error unmerging: ' + (json.data && json.data.message ? json.data.message : 'Unknown error'));
												unmergeBtn.disabled = false;
												unmergeBtn.textContent = 'Unmerge / Split';
											}
										}).catch(function(err) {
											alert('Network error: ' + err.message);
											unmergeBtn.disabled = false;
											unmergeBtn.textContent = 'Unmerge / Split';
										});
									}
								});
							});

						} else {
							tbody.innerHTML = '<tr><td colspan="4" style="padding: 20px; text-align: center; color: #64748b;">No active store prices currently linked to this component.</td></tr>';
						}
					}).catch(function(err) {
						document.getElementById('prices-loading-spinner').style.display = 'none';
						alert('Error loading prices: ' + err.message);
					});
				}

				// Clear Specs - Single Component Row
				document.querySelectorAll('.btn-clear-comp-specs').forEach(function(clearBtn) {
					clearBtn.addEventListener('click', function(e) {
						e.stopPropagation();
						var compId = this.getAttribute('data-id');
						var compName = this.getAttribute('data-name');

						if (confirm('Remove all technical specifications for "' + compName + '"?')) {
							clearBtn.disabled = true;
							clearBtn.textContent = 'Clearing...';

							var cData = new URLSearchParams();
							cData.append('action', 'hwsync_clear_component_specs');
							cData.append('component_id', compId);
							cData.append('hwsync_nonce', nonce);

							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: cData.toString()
							}).then(function(res) {
								return res.json();
							}).then(function(json) {
								if (json.success) {
									clearBtn.remove();
								} else {
									alert('Error clearing specs: ' + (json.data && json.data.message ? json.data.message : 'Unknown error'));
									clearBtn.disabled = false;
									clearBtn.innerHTML = '<span class="dashicons dashicons-trash" style="font-size: 13px; line-height: 22px; width: 13px; height: 13px;"></span> Specs';
								}
							}).catch(function(err) {
								alert('Network error: ' + err.message);
								clearBtn.disabled = false;
								clearBtn.innerHTML = '<span class="dashicons dashicons-trash" style="font-size: 13px; line-height: 22px; width: 13px; height: 13px;"></span> Specs';
							});
						}
					});
				});

				// Bulk Clear Specs for Selected Components
				if (bulkClearSpecsBtn) {
					bulkClearSpecsBtn.addEventListener('click', function() {
						if (selectedComps.length < 1) return;

						var compIds = selectedComps.map(function(c) { return c.id; });
						if (confirm('Remove specifications for ' + selectedComps.length + ' selected component(s)?')) {
							bulkClearSpecsBtn.disabled = true;
							bulkClearSpecsBtn.textContent = 'Clearing Specs...';

							var bData = new URLSearchParams();
							bData.append('action', 'hwsync_clear_component_specs');
							compIds.forEach(function(id) { bData.append('component_ids[]', id); });
							bData.append('hwsync_nonce', nonce);

							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: bData.toString()
							}).then(function(res) {
								return res.json();
							}).then(function(json) {
								if (json.success) {
									alert(json.data && json.data.message ? json.data.message : 'Specifications cleared successfully!');
									window.location.reload();
								} else {
									alert('Error clearing specs: ' + (json.data && json.data.message ? json.data.message : 'Unknown error'));
									bulkClearSpecsBtn.disabled = false;
									updateSelectedState();
								}
							}).catch(function(err) {
								alert('Network error: ' + err.message);
								bulkClearSpecsBtn.disabled = false;
								updateSelectedState();
							});
						}
					});
				}

				// Wipe Specs Modal Dialog Handlers
				var openWipeSpecsBtn = document.getElementById('btn-open-wipe-specs');
				if (openWipeSpecsBtn) {
					openWipeSpecsBtn.addEventListener('click', function() {
						modalWipeSpecs.style.display = 'flex';
					});
				}
				document.getElementById('btn-close-wipe-specs-modal').addEventListener('click', function() {
					modalWipeSpecs.style.display = 'none';
				});
				document.getElementById('btn-cancel-wipe-specs').addEventListener('click', function() {
					modalWipeSpecs.style.display = 'none';
				});

				// Confirm Wipe Specifications
				document.getElementById('btn-confirm-wipe-specs').addEventListener('click', function() {
					var scopeRadio = document.querySelector('input[name="wipe_specs_scope"]:checked');
					var scope = scopeRadio ? scopeRadio.value : 'category';
					var targetCat = (scope === 'all') ? 'all' : currentCategory;
					var confirmText = (scope === 'all') 
						? 'Wipe specifications for ALL hardware components across the database?' 
						: 'Wipe specifications for all components in category "' + currentCategory.toUpperCase() + '"?';

					if (confirm(confirmText)) {
						var confirmBtn = document.getElementById('btn-confirm-wipe-specs');
						confirmBtn.disabled = true;
						confirmBtn.textContent = 'Wiping...';

						var wData = new URLSearchParams();
						wData.append('action', 'hwsync_clear_component_specs');
						wData.append('category', targetCat);
						wData.append('hwsync_nonce', nonce);

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: wData.toString()
						}).then(function(res) {
							return res.json();
						}).then(function(json) {
							if (json.success) {
								modalWipeSpecs.style.display = 'none';
								alert(json.data && json.data.message ? json.data.message : 'Specifications wiped successfully!');
								window.location.reload();
							} else {
								alert('Error wiping specs: ' + (json.data && json.data.message ? json.data.message : 'Unknown error'));
								confirmBtn.disabled = false;
								confirmBtn.textContent = 'Confirm Wipe Specs';
							}
						}).catch(function(err) {
							alert('Network error: ' + err.message);
							confirmBtn.disabled = false;
							confirmBtn.textContent = 'Confirm Wipe Specs';
						});
					}
				});

				// Modal 4: Edit Specifications Controller
				var modalEditSpecs = document.getElementById('modal-edit-specs');
				var editSpecsTbody = document.getElementById('edit-specs-tbody');
				var editSpecsAlert = document.getElementById('edit-specs-alert-box');
				var editSpecsPills = document.getElementById('edit-specs-pills');
				var editSpecsLoading = document.getElementById('edit-specs-loading');
				var editSpecsContent = document.getElementById('edit-specs-content');
				var currentEditCompId = null;
				var currentEditCompCat = '';

				var catSpecSuggestions = {
					'gpu': ['GPU Name', 'Architecture', 'Shading Units', 'TMUs', 'ROPs', 'Compute Units', 'Matrix Cores', 'RT Cores', 'Base Clock', 'Game Clock', 'Boost Clock', 'Memory Clock', 'Memory Size', 'Memory Type', 'Memory Bus', 'Bandwidth', 'Slot Width', 'TDP', 'Suggested PSU', 'Outputs', 'Power Connectors'],
					'cpu': ['Socket', 'Frequency', 'Turbo Clock', 'Number of Cores', 'Number of Threads', 'Integrated Graphics', 'Codename', 'Generation', 'Memory Support', 'Rated Speed', 'Memory Bus', 'Memory Bandwidth', 'TDP', 'PPT', 'ECC Memory', 'PCI-Express', 'Chipsets', 'Cache L1', 'Cache L2', 'Cache L3', 'Features'],
					'motherboard': ['Platform', 'Socket', 'Cpu Type', 'Chipset', 'Memory Speed', 'Max Memory Support', 'Supported Memory Type', 'Channel Supported', 'Memory Feature', 'Graphics Port', 'Expansion Slots', 'Back Panel I/O Ports', 'Internal I/O Connector', 'Form Factor', 'Warranty'],
					'cooler': ['Cooling Type', 'Socket Support', 'Fan Size', 'PWM Controller', 'Radiator Size', 'Lighting', 'Warranty'],
					'ram': ['Model', 'Product Series', 'Memory Type', 'Capacity', 'Lighting', 'Kit Type', 'Speed', 'Tested Latency', 'Tested Voltage', 'Dimm Type', 'Profile Type', 'Warranty'],
					'psu': ['Wattage', 'Series', 'Certification', 'Modular', 'PCIe Connector (6+2)', 'SATA Connector', 'Peripheral (4-Pin)', 'Warranty'],
					'cabinet': ['Cabinet Size', 'Color', 'Material', 'Expansion Slots', 'Motherboard Size', 'Max CPU Cooler Height', 'Max PSU Length', 'Max Gpu Length', 'Max 3.5" HDD', 'Max 2.5" SSD', 'Dust Filters', 'Pre Installed Fans', 'Max Fan Support', 'Radiator Support', 'I/O Panel', 'Warranty'],
					'storage': ['Category', 'Series', 'Capacity', 'Form Factor', 'NVMe', 'Interface', 'Write Speed', 'Read Speed', 'TBW', 'Warranty']
				};

				function createSpecRow(key, val) {
					var tr = document.createElement('tr');
					tr.style.borderBottom = '1px solid #f1f5f9';

					tr.innerHTML = 
						'<td style="padding: 6px 10px;">' +
							'<input type="text" class="spec-input-key" value="' + escapeHtml(key || '') + '" placeholder="e.g. CPU Socket Type" style="width: 100%; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 8px; font-size: 13px;" />' +
						'</td>' +
						'<td style="padding: 6px 10px;">' +
							'<input type="text" class="spec-input-val" value="' + escapeHtml(val || '') + '" placeholder="e.g. LGA1700" style="width: 100%; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 8px; font-size: 13px;" />' +
						'</td>' +
						'<td style="padding: 6px 10px; text-align: center;">' +
							'<button type="button" class="button btn-remove-spec-row" style="color: #dc2626; border-color: #fecaca; background: #fff; padding: 0 6px; height: 30px; border-radius: 4px;" title="Remove this attribute">' +
								'<span class="dashicons dashicons-trash" style="margin-top: 4px; font-size: 15px; width: 15px; height: 15px;"></span>' +
							'</button>' +
						'</td>';

					tr.querySelector('.btn-remove-spec-row').addEventListener('click', function() {
						tr.remove();
						if (editSpecsTbody.children.length === 0) {
							createSpecRow('', '');
						}
					});

					editSpecsTbody.appendChild(tr);
					return tr;
				}

				// Click Edit Specs on Component Row
				document.querySelectorAll('.btn-edit-comp-specs').forEach(function(editBtn) {
					editBtn.addEventListener('click', function() {
						var compId = this.getAttribute('data-id');
						var compName = this.getAttribute('data-name');
						var compCat = this.getAttribute('data-category') || '';

						currentEditCompId = compId;
						currentEditCompCat = compCat.toLowerCase();

						document.getElementById('modal-edit-specs-title').textContent = compName;
						document.getElementById('modal-edit-specs-cat').textContent = compCat;
						document.getElementById('modal-edit-specs-id').textContent = '(ID #' + compId + ')';

						editSpecsAlert.style.display = 'none';
						editSpecsLoading.style.display = 'block';
						editSpecsContent.style.display = 'none';
						editSpecsTbody.innerHTML = '';
						editSpecsPills.innerHTML = '';
						modalEditSpecs.style.display = 'flex';

						// Render suggested pills
						var suggestions = catSpecSuggestions[currentEditCompCat] || ['CPU Socket Type', 'Chipset', 'VRAM Size', 'Memory Types', 'Total Cores', 'Warranty'];
						suggestions.forEach(function(sKey) {
							var pill = document.createElement('button');
							pill.type = 'button';
							pill.className = 'button';
							pill.style.fontSize = '11px';
							pill.style.padding = '1px 8px';
							pill.style.height = '24px';
							pill.style.borderRadius = '12px';
							pill.style.background = '#fff';
							pill.style.borderColor = '#cbd5e1';
							pill.textContent = '+ ' + sKey;

							pill.addEventListener('click', function() {
								var existingInputs = editSpecsTbody.querySelectorAll('.spec-input-key');
								var found = false;
								existingInputs.forEach(function(inp) {
									if (inp.value.trim().toLowerCase() === sKey.toLowerCase()) {
										found = true;
										inp.focus();
									}
								});
								if (!found) {
									var newRow = createSpecRow(sKey, '');
									var valInp = newRow.querySelector('.spec-input-val');
									if (valInp) valInp.focus();
								}
							});
							editSpecsPills.appendChild(pill);
						});

						// Fetch specs via AJAX
						var postData = new URLSearchParams();
						postData.append('action', 'hwsync_get_component_specs');
						postData.append('component_id', compId);
						postData.append('hwsync_nonce', nonce);

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: postData.toString()
						}).then(function(res) {
							return res.json();
						}).then(function(json) {
							editSpecsLoading.style.display = 'none';
							editSpecsContent.style.display = 'block';

							if (json.success && json.data && json.data.specs && json.data.specs.length > 0) {
								json.data.specs.forEach(function(item) {
									createSpecRow(item.key, item.value);
								});
							} else {
								createSpecRow('', '');
								createSpecRow('', '');
							}
						}).catch(function(err) {
							editSpecsLoading.style.display = 'none';
							editSpecsContent.style.display = 'block';
							createSpecRow('', '');
						});
					});
				});

				// Add Spec Row Button
				document.getElementById('btn-add-spec-row').addEventListener('click', function() {
					var row = createSpecRow('', '');
					var keyInp = row.querySelector('.spec-input-key');
					if (keyInp) keyInp.focus();
				});

				// Close / Cancel Edit Specs Modal
				document.getElementById('btn-close-edit-specs-modal').addEventListener('click', function() {
					modalEditSpecs.style.display = 'none';
				});
				document.getElementById('btn-cancel-edit-specs').addEventListener('click', function() {
					modalEditSpecs.style.display = 'none';
				});

				// Save Specifications Button
				document.getElementById('btn-save-comp-specs').addEventListener('click', function() {
					if (!currentEditCompId) return;

					var saveBtn = document.getElementById('btn-save-comp-specs');
					saveBtn.disabled = true;
					saveBtn.textContent = 'Saving...';

					var keyInputs = editSpecsTbody.querySelectorAll('.spec-input-key');
					var valInputs = editSpecsTbody.querySelectorAll('.spec-input-val');

					var postData = new URLSearchParams();
					postData.append('action', 'hwsync_save_component_specs');
					postData.append('component_id', currentEditCompId);
					postData.append('hwsync_nonce', nonce);

					for (var i = 0; i < keyInputs.length; i++) {
						var k = keyInputs[i].value.trim();
						var v = valInputs[i].value.trim();
						if (k && v) {
							postData.append('keys[]', k);
							postData.append('values[]', v);
						}
					}

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: postData.toString()
					}).then(function(res) {
						return res.json();
					}).then(function(json) {
						saveBtn.disabled = false;
						saveBtn.innerHTML = '<span class="dashicons dashicons-saved"></span> <?php esc_html_e( "Save Specifications", "hwsync" ); ?>';

						if (json.success) {
							editSpecsAlert.style.display = 'block';
							editSpecsAlert.style.background = '#dcfce7';
							editSpecsAlert.style.color = '#15803d';
							editSpecsAlert.textContent = (json.data && json.data.message) ? json.data.message : 'Specifications saved successfully!';
							setTimeout(function() {
								modalEditSpecs.style.display = 'none';
								window.location.reload();
							}, 800);
						} else {
							editSpecsAlert.style.display = 'block';
							editSpecsAlert.style.background = '#fee2e2';
							editSpecsAlert.style.color = '#dc2626';
							editSpecsAlert.textContent = (json.data && json.data.message) ? json.data.message : 'Error saving specifications.';
						}
					}).catch(function(err) {
						saveBtn.disabled = false;
						saveBtn.innerHTML = '<span class="dashicons dashicons-saved"></span> <?php esc_html_e( "Save Specifications", "hwsync" ); ?>';
						editSpecsAlert.style.display = 'block';
						editSpecsAlert.style.background = '#fee2e2';
						editSpecsAlert.style.color = '#dc2626';
						editSpecsAlert.textContent = 'Network error: ' + err.message;
					});
				});

				// Amazon CSV Modal Controller
				var modalAmazonCsv = document.getElementById('modal-amazon-csv');
				var btnOpenAmazonCsv = document.getElementById('btn-open-amazon-csv');
				var btnCloseAmazonCsv = document.getElementById('btn-close-amazon-csv-modal');
				var btnDoneAmazonCsv = document.getElementById('btn-done-amazon-csv');
				var formImportAmazonCsv = document.getElementById('form-import-amazon-csv');
				var btnSubmitAmazonCsv = document.getElementById('btn-submit-amazon-csv');
				var amazonCsvAlert = document.getElementById('amazon-csv-alert-box');

				if (btnOpenAmazonCsv && modalAmazonCsv) {
					btnOpenAmazonCsv.addEventListener('click', function() {
						if (amazonCsvAlert) amazonCsvAlert.style.display = 'none';
						var fileInp = document.getElementById('amazon-csv-file-input');
						if (fileInp) fileInp.value = '';
						modalAmazonCsv.style.display = 'flex';
					});

					function closeAmazonCsvModal() {
						modalAmazonCsv.style.display = 'none';
					}

					if (btnCloseAmazonCsv) btnCloseAmazonCsv.addEventListener('click', closeAmazonCsvModal);
					if (btnDoneAmazonCsv) btnDoneAmazonCsv.addEventListener('click', function() {
						closeAmazonCsvModal();
						window.location.reload();
					});

					if (formImportAmazonCsv) {
						formImportAmazonCsv.addEventListener('submit', function(e) {
							e.preventDefault();
							var fileInput = document.getElementById('amazon-csv-file-input');
							if (!fileInput.files || fileInput.files.length === 0) {
								alert('Please select a CSV file to upload.');
								return;
							}

							btnSubmitAmazonCsv.disabled = true;
							btnSubmitAmazonCsv.innerHTML = '<span class="dashicons dashicons-update spin"></span> Updating...';
							if (amazonCsvAlert) {
								amazonCsvAlert.style.display = 'block';
								amazonCsvAlert.style.background = '#eff6ff';
								amazonCsvAlert.style.color = '#1e40af';
								amazonCsvAlert.style.border = '1px solid #bfdbfe';
								amazonCsvAlert.innerHTML = 'Uploading and processing Amazon product links...';
							}

							var formData = new FormData();
							formData.append('action', 'hwsync_import_amazon_csv');
							formData.append('hwsync_nonce', nonce);
							formData.append('csv_file', fileInput.files[0]);

							fetch(ajaxurl, {
								method: 'POST',
								body: formData
							})
							.then(function(res) { return res.json(); })
							.then(function(data) {
								btnSubmitAmazonCsv.disabled = false;
								btnSubmitAmazonCsv.innerHTML = '<span class="dashicons dashicons-saved"></span> Upload & Update Links';

								if (data.success) {
									amazonCsvAlert.style.background = '#f0fdf4';
									amazonCsvAlert.style.color = '#15803d';
									amazonCsvAlert.style.border = '1px solid #bbf7d0';
									amazonCsvAlert.innerHTML = '<strong>Success!</strong> ' + (data.data.message || 'Updated Amazon product links successfully.');
								} else {
									amazonCsvAlert.style.background = '#fef2f2';
									amazonCsvAlert.style.color = '#b91c1c';
									amazonCsvAlert.style.border = '1px solid #fecaca';
									amazonCsvAlert.innerHTML = '<strong>Error:</strong> ' + (data.data ? data.data.message : 'Failed to import CSV file.');
								}
							})
							.catch(function(err) {
								btnSubmitAmazonCsv.disabled = false;
								btnSubmitAmazonCsv.innerHTML = '<span class="dashicons dashicons-saved"></span> Upload & Update Links';
								amazonCsvAlert.style.background = '#fef2f2';
								amazonCsvAlert.style.color = '#b91c1c';
								amazonCsvAlert.style.border = '1px solid #fecaca';
								amazonCsvAlert.innerHTML = '<strong>Network Error:</strong> ' + err.message;
							});
						});
					}
				}

				// Bulk Delete Selected Components Handler
				if (bulkDeleteSelectedBtn) {
					bulkDeleteSelectedBtn.addEventListener('click', function() {
						if (selectedComps.length === 0) {
							alert('Please select at least one component using the checkboxes.');
							return;
						}
						var conf = confirm('⚠️ WARNING: Are you sure you want to permanently delete the ' + selectedComps.length + ' selected component(s)?\n\nThis will remove the canonical components, all their linked retailer store prices, price history, and linked WordPress posts.\n\nThis action cannot be undone.');
						if (!conf) return;

						bulkDeleteSelectedBtn.disabled = true;
						bulkDeleteSelectedBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Deleting...';

						var compIds = selectedComps.map(function(c) { return c.id; });
						var formData = new URLSearchParams();
						formData.append('action', 'hwsync_delete_components');
						formData.append('hwsync_nonce', nonce);
						compIds.forEach(function(id) { formData.append('component_ids[]', id); });

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: formData.toString()
						})
						.then(function(res) { return res.json(); })
						.then(function(data) {
							if (data.success) {
								alert(data.data.message || 'Selected components deleted successfully.');
								window.location.reload();
							} else {
								alert('Error: ' + (data.data ? data.data.message : 'Failed to delete selected components.'));
								bulkDeleteSelectedBtn.disabled = false;
								bulkDeleteSelectedBtn.innerHTML = '<span class="dashicons dashicons-trash"></span> Delete Selected (' + selectedComps.length + ')';
							}
						})
						.catch(function(err) {
							alert('Network error: ' + err.message);
							bulkDeleteSelectedBtn.disabled = false;
							bulkDeleteSelectedBtn.innerHTML = '<span class="dashicons dashicons-trash"></span> Delete Selected (' + selectedComps.length + ')';
						});
					});
				}

				// Vendor Deletion Handlers
				if (btnDeleteSelectedVendor) {
					btnDeleteSelectedVendor.addEventListener('click', function() {
						if (selectedComps.length === 0) {
							alert('Please select at least one component using the checkboxes.');
							return;
						}
						var vName = btnDeleteSelectedVendor.getAttribute('data-vendor-name') || currentVendorFilter;
						var conf = confirm('Are you sure you want to delete price listings from ' + vName + ' for the ' + selectedComps.length + ' selected component(s)?\n\nComponents with other stores will update their lowest price, and orphan components will be cleaned up.');
						if (!conf) return;

						btnDeleteSelectedVendor.disabled = true;
						btnDeleteSelectedVendor.innerHTML = '<span class="dashicons dashicons-update spin"></span> Deleting...';

						var compIds = selectedComps.map(function(c) { return c.id; });
						var formData = new URLSearchParams();
						formData.append('action', 'hwsync_delete_vendor_records');
						formData.append('hwsync_nonce', nonce);
						formData.append('vendor_slug', currentVendorFilter);
						compIds.forEach(function(id) { formData.append('component_ids[]', id); });

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: formData.toString()
						})
						.then(function(res) { return res.json(); })
						.then(function(data) {
							if (data.success) {
								alert(data.data.message || 'Vendor records deleted successfully.');
								window.location.reload();
							} else {
								alert('Error: ' + (data.data ? data.data.message : 'Failed to delete vendor records.'));
								btnDeleteSelectedVendor.disabled = false;
								btnDeleteSelectedVendor.innerHTML = '<span class="dashicons dashicons-trash"></span> Delete Selected';
							}
						})
						.catch(function(err) {
							alert('Network error: ' + err.message);
							btnDeleteSelectedVendor.disabled = false;
							btnDeleteSelectedVendor.innerHTML = '<span class="dashicons dashicons-trash"></span> Delete Selected';
						});
					});
				}

				if (btnDeleteAllVendor) {
					btnDeleteAllVendor.addEventListener('click', function() {
						var vName = btnDeleteAllVendor.getAttribute('data-vendor-name') || currentVendorFilter;
						var conf = confirm('⚠️ WARNING: Are you sure you want to delete ALL price listings from ' + vName + ' across the entire database?\n\nThis will remove all listings for this vendor, recalculate lowest prices for components with remaining stores, and clean up components that only belonged to ' + vName + '.\n\nAre you sure you want to proceed?');
						if (!conf) return;

						btnDeleteAllVendor.disabled = true;
						btnDeleteAllVendor.innerHTML = '<span class="dashicons dashicons-update spin"></span> Deleting All...';

						var formData = new URLSearchParams();
						formData.append('action', 'hwsync_delete_vendor_records');
						formData.append('hwsync_nonce', nonce);
						formData.append('vendor_slug', currentVendorFilter);

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: formData.toString()
						})
						.then(function(res) { return res.json(); })
						.then(function(data) {
							if (data.success) {
								alert(data.data.message || 'All vendor records deleted successfully.');
								window.location.reload();
							} else {
								alert('Error: ' + (data.data ? data.data.message : 'Failed to delete vendor records.'));
								btnDeleteAllVendor.disabled = false;
								btnDeleteAllVendor.innerHTML = '<span class="dashicons dashicons-warning"></span> Delete All';
							}
						})
						.catch(function(err) {
							alert('Network error: ' + err.message);
							btnDeleteAllVendor.disabled = false;
							btnDeleteAllVendor.innerHTML = '<span class="dashicons dashicons-warning"></span> Delete All';
						});
					});
				}

				function escapeHtml(text) {
					if (typeof text !== 'string') return text;
					var map = {
						'&': '&amp;',
						'<': '&lt;',
						'>': '&gt;',
						'"': '&quot;',
						"'": '&#039;'
					};
					return text.replace(/[&<>"']/g, function(m) { return map[m]; });
				}
			});
			</script>
		</div>
		<?php
	}

	public static function handle_save_vendor() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => \__( 'Unauthorized', 'hwsync' ) ) );
		}

		$id          = isset( $_POST['vendor_id'] ) ? intval( $_POST['vendor_id'] ) : 0;
		$vendor_name = isset( $_POST['vendor_name'] ) ? sanitize_text_field( $_POST['vendor_name'] ) : '';
		$vendor_slug = isset( $_POST['vendor_slug'] ) ? sanitize_title( $_POST['vendor_slug'] ) : '';
		$base_url    = isset( $_POST['base_url'] ) ? esc_url_raw( $_POST['base_url'] ) : '';
		$sync_method = isset( $_POST['sync_method'] ) ? sanitize_text_field( $_POST['sync_method'] ) : 'curl_html';
		$is_active   = ! empty( $_POST['is_active'] ) ? 1 : 0;
		$endpoints   = isset( $_POST['endpoints'] ) ? (array) $_POST['endpoints'] : array();

		if ( empty( $vendor_name ) || empty( $base_url ) ) {
			wp_send_json_error( array( 'message' => \__( 'Retailer name and store URL are required.', 'hwsync' ) ) );
		}

		if ( empty( $vendor_slug ) ) {
			$vendor_slug = sanitize_title( $vendor_name );
		}

		$clean_endpoints = array();
		foreach ( array( 'cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet' ) as $cat ) {
			if ( isset( $endpoints[ $cat ] ) ) {
				$clean_endpoints[ $cat ] = sanitize_text_field( $endpoints[ $cat ] );
			}
		}

		$vendor = $id ? Vendor::find_by_id( $id ) : null;
		if ( ! $vendor && ! empty( $vendor_slug ) ) {
			$vendor = Vendor::find_by_slug( $vendor_slug );
		}
		if ( ! $vendor ) {
			$vendor = new Vendor();
		}

		$vendor->vendor_name = $vendor_name;
		$vendor->vendor_slug = $vendor_slug;
		$vendor->base_url    = rtrim( $base_url, '/' );
		$vendor->sync_method = $sync_method;
		$vendor->is_active   = $is_active;
		$vendor->set_config( array( 'endpoints' => $clean_endpoints ) );

		$vendor_id = $vendor->save();
		if ( ! $vendor_id ) {
			global $wpdb;
			$err = ! empty( $wpdb->last_error ) ? $wpdb->last_error : \__( 'Failed to save retailer to database.', 'hwsync' );
			wp_send_json_error( array( 'message' => $err ) );
		}

		wp_send_json_success( array(
			'vendor_id' => $vendor_id,
			'message'   => \__( 'Retailer configuration saved successfully!', 'hwsync' ),
		) );
	}

	public static function handle_delete_vendor() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => \__( 'Unauthorized', 'hwsync' ) ) );
		}

		$id = isset( $_POST['vendor_id'] ) ? intval( $_POST['vendor_id'] ) : 0;
		$vendor = Vendor::find_by_id( $id );
		if ( ! $vendor ) {
			wp_send_json_error( array( 'message' => \__( 'Retailer not found.', 'hwsync' ) ) );
		}

		$vendor->delete();
		wp_send_json_success( array( 'message' => \__( 'Retailer deleted successfully.', 'hwsync' ) ) );
	}

	public static function handle_test_vendor_sync() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => \__( 'Unauthorized', 'hwsync' ) ) );
		}

		$vendor_slug = isset( $_POST['vendor_slug'] ) ? sanitize_text_field( $_POST['vendor_slug'] ) : '';
		$sync_method = isset( $_POST['sync_method'] ) ? sanitize_text_field( $_POST['sync_method'] ) : 'curl_html';
		$category    = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : 'cpu';
		$base_url    = isset( $_POST['base_url'] ) ? esc_url_raw( $_POST['base_url'] ) : '';
		$endpoint    = isset( $_POST['endpoint'] ) ? sanitize_text_field( $_POST['endpoint'] ) : '';

		$vendor = ! empty( $vendor_slug ) ? Vendor::find_by_slug( $vendor_slug ) : null;
		if ( $vendor ) {
			if ( empty( $base_url ) ) $base_url = $vendor->base_url;
			if ( empty( $sync_method ) ) $sync_method = $vendor->sync_method;
		}

		$start_time = microtime( true );

		// Instantiate adapter for test
		if ( $vendor && ! empty( $vendor->adapter_class ) && class_exists( $vendor->adapter_class ) ) {
			$adapter = new $vendor->adapter_class();
		} else {
			$endpoints = array( $category => $endpoint );
			if ( $vendor ) {
				$cfg = $vendor->get_config();
				if ( ! empty( $cfg['endpoints'] ) ) {
					$endpoints = wp_parse_args( $endpoints, $cfg['endpoints'] );
				}
			}
			$adapter = new \HWsync\Vendors\Configurable_Vendor_Adapter(
				$vendor_slug ?: 'test_vendor',
				$vendor ? $vendor->vendor_name : 'Test Retailer',
				$base_url,
				$sync_method,
				$endpoints
			);
		}

		try {
			$items = $adapter->fetch_products( $category, 1 );
			$duration = round( ( microtime( true ) - $start_time ) * 1000 );

			if ( ! empty( $items ) && is_array( $items ) ) {
				$sample = $items[0];
				wp_send_json_success( array(
					'category'      => $category,
					'sync_method'   => $sync_method,
					'success'       => true,
					'duration_ms'   => $duration,
					'items_found'   => count( $items ),
					'sample_item'   => array(
						'title'          => $sample['title'] ?? 'N/A',
						'price'          => isset( $sample['price'] ) ? floatval( $sample['price'] ) : 0.0,
						'display_price'  => isset( $sample['price'] ) && $sample['price'] > 0 ? '₹' . number_format( $sample['price'], 2 ) : 'NA',
						'url'            => $sample['url'] ?? '',
						'sku'            => $sample['sku'] ?? '',
						'stock_status'   => $sample['stock_status'] ?? 'in_stock',
						'in_stock'       => ! empty( $sample['in_stock'] ),
					),
					'message'       => sprintf( \__( 'Found %d items in %dms. Sample extracted.', 'hwsync' ), count( $items ), $duration ),
				) );
			} else {
				wp_send_json_success( array(
					'category'      => $category,
					'sync_method'   => $sync_method,
					'success'       => false,
					'duration_ms'   => $duration,
					'items_found'   => 0,
					'sample_item'   => null,
					'message'       => \__( 'No products found on category endpoint.', 'hwsync' ),
				) );
			}
		} catch ( \Throwable $e ) {
			$duration = round( ( microtime( true ) - $start_time ) * 1000 );
			wp_send_json_error( array(
				'category'    => $category,
				'duration_ms' => $duration,
				'message'     => $e->getMessage(),
			) );
		}
	}

	public static function handle_manual_sync() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' ) ) {
			wp_die( __( 'Unauthorized request', 'hwsync' ) );
		}

		$vendor = isset( $_POST['target_vendor'] ) ? sanitize_text_field( $_POST['target_vendor'] ) : 'all';
		$category = isset( $_POST['target_category'] ) ? sanitize_text_field( $_POST['target_category'] ) : 'all';

		$manager = new Sync_Manager();
		$report = $manager->run_sync( array( 'vendor' => $vendor, 'category' => $category ) );
		update_option( 'hwsync_last_sync_report', $report );

		wp_safe_redirect( admin_url( 'admin.php?page=hwsync-dashboard&sync_status=success' ) );
		exit;
	}

	public static function handle_toggle_vendor() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized request', 'hwsync' ) );
		}

		$vendor_id = isset( $_GET['vendor_id'] ) ? intval( $_GET['vendor_id'] ) : 0;
		if ( $vendor_id ) {
			$vendor = Vendor::find_by_id( $vendor_id );
			if ( $vendor ) {
				$vendor->is_active = $vendor->is_active ? 0 : 1;
				$vendor->save();
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=hwsync-vendors' ) );
		exit;
	}

	public static function handle_sync_batch() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => \__( 'Unauthorized', 'hwsync' ) ) );
		}

		$vendor_slug = isset( $_POST['target_vendor'] ) ? sanitize_text_field( $_POST['target_vendor'] ) : 'primeabgb';
		$category    = isset( $_POST['target_category'] ) ? sanitize_text_field( $_POST['target_category'] ) : 'cpu';
		$page        = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$delta_only  = ! empty( $_POST['delta_only'] );

		$manager = new Sync_Manager();
		$result  = $manager->sync_page( $vendor_slug, $category, $page, $delta_only );

		wp_send_json_success( $result );
	}

	public static function handle_sync_specs_chunk() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => \__( 'Unauthorized', 'hwsync' ) ) );
		}

		$category = isset( $_POST['target_category'] ) ? sanitize_text_field( $_POST['target_category'] ) : 'all';
		$offset   = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$limit    = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 5;

		$specs_manager = new Specs_Sync_Manager();
		$result = $specs_manager->sync_specs_chunk( array(
			'category' => $category,
			'offset'   => $offset,
			'limit'    => $limit,
		) );

		wp_send_json_success( $result );
	}

	public static function handle_stream_sync() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request', 'hwsync' ) ), 403 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 );
		}
		if ( function_exists( 'ignore_user_abort' ) ) {
			@ignore_user_abort( true );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'X-Accel-Buffering: no' );

		// Flush initial padding so reverse proxies/buffers don't delay the stream
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n";
		flush();

		$vendor   = isset( $_POST['target_vendor'] ) ? sanitize_text_field( $_POST['target_vendor'] ) : 'all';
		$category = isset( $_POST['target_category'] ) ? sanitize_text_field( $_POST['target_category'] ) : 'all';

		$stream_logger = function( $level, $message, $stats ) {
			$payload = array(
				'level'     => $level,
				'message'   => $message,
				'stats'     => $stats,
				'timestamp' => current_time( 'H:i:s' ),
			);
			echo "data: " . wp_json_encode( $payload ) . "\n\n";
			flush();
		};

		$manager = new Sync_Manager();
		$report  = $manager->run_sync( array( 'vendor' => $vendor, 'category' => $category ), $stream_logger );
		update_option( 'hwsync_last_sync_report', $report );

		exit;
	}

	public static function handle_browser_batch() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'hwsync' ) ) );
		}

		$vendor_slug = isset( $_POST['vendor_slug'] ) ? sanitize_text_field( $_POST['vendor_slug'] ) : 'mdcomputers';
		$vendor      = Vendor::find_by_slug( $vendor_slug );
		if ( ! $vendor ) {
			wp_send_json_error( array( 'message' => __( 'Vendor not found', 'hwsync' ) ) );
		}

		$items_raw = isset( $_POST['items'] ) ? json_decode( wp_unslash( $_POST['items'] ), true ) : array();
		if ( empty( $items_raw ) || ! is_array( $items_raw ) ) {
			wp_send_json_error( array( 'message' => __( 'No items provided in batch', 'hwsync' ) ) );
		}

		$manager = new Sync_Manager();
		$processed = 0;
		$prices_saved = 0;
		$touched_ids = array();

		foreach ( $items_raw as $item ) {
			$res = $manager->sync_single_item( $item, $vendor );
			if ( $res && ! empty( $res['component_id'] ) ) {
				$touched_ids[ $res['component_id'] ] = true;
				$prices_saved++;
			}
			$processed++;
		}

		$vendor->update_last_sync();

		wp_send_json_success( array(
			'processed'    => $processed,
			'prices_saved' => $prices_saved,
			'components'   => count( $touched_ids ),
			'posts_synced' => 0,
		) );
	}

	public static function handle_merge_components() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'hwsync' ) ) );
		}

		$category = isset( $_POST['target_category'] ) ? sanitize_text_field( $_POST['target_category'] ) : 'all';

		$result = Matching_Engine::merge_duplicate_components( $category );

		wp_send_json_success( $result );
	}

	public static function handle_get_component_prices() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'hwsync' ) ) );
		}

		$component_id = isset( $_POST['component_id'] ) ? intval( $_POST['component_id'] ) : 0;
		$component = Component::find_by_id( $component_id );
		if ( ! $component ) {
			wp_send_json_error( array( 'message' => __( 'Component not found.', 'hwsync' ) ) );
		}

		$prices = $component->get_prices();
		$data = array();
		foreach ( $prices as $p ) {
			$data[] = array(
				'id'                   => intval( $p->id ),
				'vendor_id'            => intval( $p->vendor_id ),
				'vendor_name'          => $p->vendor_name ?: __( 'Unknown Store', 'hwsync' ),
				'vendor_product_title' => $p->vendor_product_title,
				'price'                => floatval( $p->price ),
				'display_price'        => method_exists( $p, 'get_formatted_price' ) ? $p->get_formatted_price() : ( '₹' . number_format( floatval( $p->price ), 2 ) ),
				'original_price'       => $p->original_price ? floatval( $p->original_price ) : null,
				'display_original'     => $p->original_price ? '₹' . number_format( floatval( $p->original_price ), 2 ) : null,
				'product_url'          => $p->product_url,
				'is_in_stock'          => (bool) $p->is_in_stock,
				'stock_status'         => $p->stock_status,
				'last_checked_at'      => $p->last_checked_at,
			);
		}

		wp_send_json_success( array(
			'component_id' => $component->id,
			'brand'        => $component->brand,
			'model_name'   => $component->model_name,
			'category'     => $component->category,
			'prices'       => $data,
		) );
	}

	public static function handle_manual_merge_components() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'hwsync' ) ) );
		}

		$target_id = isset( $_POST['target_id'] ) ? intval( $_POST['target_id'] ) : 0;
		$source_id = isset( $_POST['source_id'] ) ? intval( $_POST['source_id'] ) : 0;

		$result = Matching_Engine::manual_merge_components( $target_id, $source_id );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	public static function handle_unmerge_vendor_price() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'hwsync' ) ) );
		}

		$vendor_price_id   = isset( $_POST['vendor_price_id'] ) ? intval( $_POST['vendor_price_id'] ) : 0;
		$custom_model_name = isset( $_POST['custom_model_name'] ) ? sanitize_text_field( $_POST['custom_model_name'] ) : null;

		$result = Matching_Engine::unmerge_vendor_price( $vendor_price_id, $custom_model_name );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	public static function handle_stream_specs_sync() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( \__( 'Unauthorized', 'hwsync' ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		$category = isset( $_POST['target_category'] ) ? sanitize_text_field( $_POST['target_category'] ) : 'all';

		$stream_logger = function( $level, $message, $stats = array() ) {
			$payload = array(
				'level'     => $level,
				'message'   => $message,
				'stats'     => $stats,
				'timestamp' => current_time( 'H:i:s' ),
			);
			echo "data: " . wp_json_encode( $payload ) . "\n\n";
			flush();
		};

		$specs_manager = new Specs_Sync_Manager();
		$report = $specs_manager->run_specs_sync( array( 'category' => $category ), $stream_logger );

		exit;
	}

	public static function handle_stream_image_sync() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( \__( 'Unauthorized', 'hwsync' ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		$category = isset( $_POST['target_category'] ) ? sanitize_text_field( $_POST['target_category'] ) : 'all';
		$force    = ! empty( $_POST['force_images'] );

		$stream_logger = function( $level, $message, $stats = array() ) {
			$payload = array(
				'level'     => $level,
				'message'   => $message,
				'stats'     => $stats,
				'timestamp' => current_time( 'H:i:s' ),
			);
			echo "data: " . wp_json_encode( $payload ) . "\n\n";
			flush();
		};

		$image_manager = new Image_Sync_Manager();
		$report = $image_manager->run_images_sync( array(
			'category' => $category,
			'limit'    => 100,
			'force'    => $force,
		), $stream_logger );

		exit;
	}

	public static function handle_sync_image_chunk() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'hwsync' ) ) );
		}

		$category = isset( $_POST['target_category'] ) ? sanitize_text_field( $_POST['target_category'] ) : 'all';
		$offset   = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$limit    = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 2;
		$force    = ! empty( $_POST['force_images'] );

		try {
			$image_manager = new Image_Sync_Manager();
			$report = $image_manager->sync_images_chunk( array(
				'category' => $category,
				'offset'   => $offset,
				'limit'    => $limit,
				'force'    => $force,
			) );

			wp_send_json_success( $report );
		} catch ( \Throwable $e ) {
			wp_send_json_success( array(
				'processed'    => 0,
				'images_saved' => 0,
				'skipped'      => 0,
				'has_more'     => false,
				'logs'         => array( array( 'level' => 'warning', 'message' => 'Skipped item: ' . $e->getMessage() ) ),
			) );
		}
	}

	public static function handle_clear_component_specs() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'hwsync' ) ) );
		}

		global $wpdb;
		$comp_table = Database::get_table_name( 'components' );
		$component_id = isset( $_POST['component_id'] ) ? intval( $_POST['component_id'] ) : 0;
		$component_ids = isset( $_POST['component_ids'] ) ? (array) $_POST['component_ids'] : array();
		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

		$cleared_count = 0;

		if ( $component_id > 0 ) {
			$comp = Component::find_by_id( $component_id );
			if ( $comp ) {
				$comp->specs_json = array();
				$comp->save();
				if ( ! empty( $comp->wp_post_id ) ) {
					delete_post_meta( $comp->wp_post_id, '_pcspecs_specs' );
					delete_post_meta( $comp->wp_post_id, '_hwsync_specs' );
				}
				$cleared_count = 1;
			}
		} elseif ( ! empty( $component_ids ) ) {
			$sanitized_ids = array_map( 'intval', $component_ids );
			$sanitized_ids = array_filter( $sanitized_ids );
			if ( ! empty( $sanitized_ids ) ) {
				$ids_placeholder = implode( ',', $sanitized_ids );
				$wpdb->query( "UPDATE {$comp_table} SET specs_json = NULL WHERE id IN ({$ids_placeholder})" );
				foreach ( $sanitized_ids as $cid ) {
					$c = Component::find_by_id( $cid );
					if ( $c && ! empty( $c->wp_post_id ) ) {
						delete_post_meta( $c->wp_post_id, '_pcspecs_specs' );
						delete_post_meta( $c->wp_post_id, '_hwsync_specs' );
					}
				}
				$cleared_count = count( $sanitized_ids );
			}
		} elseif ( ! empty( $category ) ) {
			$where = ( $category === 'all' ) ? "1=1" : $wpdb->prepare( "category = %s", $category );
			$all_to_clear = $wpdb->get_results( "SELECT id, wp_post_id FROM {$comp_table} WHERE {$where}", \ARRAY_A );
			$wpdb->query( "UPDATE {$comp_table} SET specs_json = NULL WHERE {$where}" );
			if ( ! empty( $all_to_clear ) ) {
				foreach ( $all_to_clear as $crow ) {
					if ( ! empty( $crow['wp_post_id'] ) ) {
						delete_post_meta( $crow['wp_post_id'], '_pcspecs_specs' );
						delete_post_meta( $crow['wp_post_id'], '_hwsync_specs' );
					}
				}
				$cleared_count = count( $all_to_clear );
			}
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Successfully cleared specifications for %d component(s).', 'hwsync' ), $cleared_count ),
			'cleared_count' => $cleared_count,
		) );
	}

	public static function handle_get_component_specs() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'hwsync' ) ) );
		}

		$component_id = isset( $_POST['component_id'] ) ? intval( $_POST['component_id'] ) : 0;
		$comp = Component::find_by_id( $component_id );
		if ( ! $comp ) {
			wp_send_json_error( array( 'message' => __( 'Component not found.', 'hwsync' ) ) );
		}

		$specs = $comp->get_specs();
		$flat_specs = array();
		if ( is_array( $specs ) ) {
			foreach ( $specs as $k => $v ) {
				if ( $k === 'raw_specs_table' || ! is_scalar( $v ) ) {
					continue;
				}
				$flat_specs[] = array(
					'key'   => (string) $k,
					'value' => (string) $v,
				);
			}
		}

		wp_send_json_success( array(
			'component_id' => $comp->id,
			'title'        => $comp->brand . ' ' . $comp->model_name,
			'category'     => $comp->category,
			'specs'        => $flat_specs,
		) );
	}

	public static function handle_save_component_specs() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'hwsync' ) ) );
		}

		$component_id = isset( $_POST['component_id'] ) ? intval( $_POST['component_id'] ) : 0;
		$comp = Component::find_by_id( $component_id );
		if ( ! $comp ) {
			wp_send_json_error( array( 'message' => __( 'Component not found.', 'hwsync' ) ) );
		}

		$keys   = isset( $_POST['keys'] ) ? (array) $_POST['keys'] : array();
		$values = isset( $_POST['values'] ) ? (array) $_POST['values'] : array();

		$clean_specs = array();
		$count = max( count( $keys ), count( $values ) );

		for ( $i = 0; $i < $count; $i++ ) {
			$k = isset( $keys[ $i ] ) ? sanitize_text_field( wp_unslash( $keys[ $i ] ) ) : '';
			$v = isset( $values[ $i ] ) ? sanitize_text_field( wp_unslash( $values[ $i ] ) ) : '';

			$k = trim( $k );
			$v = trim( $v );

			if ( ! empty( $k ) && ! empty( $v ) ) {
				$norm_k = Specs_Sync_Manager::normalize_spec_key( $k );
				$clean_specs[ $norm_k ] = $v;
			}
		}

		$comp->specs_json = $clean_specs;
		$comp->save();

		if ( ! empty( $comp->wp_post_id ) ) {
			if ( ! empty( $clean_specs ) ) {
				update_post_meta( $comp->wp_post_id, '_pcspecs_specs', $clean_specs );
				update_post_meta( $comp->wp_post_id, '_hwsync_specs', $clean_specs );
			} else {
				delete_post_meta( $comp->wp_post_id, '_pcspecs_specs' );
				delete_post_meta( $comp->wp_post_id, '_hwsync_specs' );
			}
		}

		wp_send_json_success( array(
			'message'     => sprintf( __( 'Specifications successfully updated (%d attributes).', 'hwsync' ), count( $clean_specs ) ),
			'specs_count' => count( $clean_specs ),
			'specs'       => $clean_specs,
		) );
	}

	public static function render_maintenance_page() {
		$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$count  = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : 0;
		$deleted= isset( $_GET['deleted'] ) ? intval( $_GET['deleted'] ) : 0;

		$schedule_enabled = get_option( 'hwsync_schedule_enabled', 1 );
		$schedule_freq    = get_option( 'hwsync_schedule_frequency', 'daily' );
		$schedule_time    = get_option( 'hwsync_schedule_time', '03:00' );
		$next_timestamp   = wp_next_scheduled( Cron::CRON_HOOK );
		$next_run_str     = $next_timestamp ? get_date_from_gmt( date( 'Y-m-d H:i:s', $next_timestamp ), 'd-m-Y H:i:s' ) . ' (Local Time)' : \__( 'Not Scheduled', 'hwsync' );
		?>
		<div class="wrap">
			<h1><span class="dashicons dashicons-admin-tools" style="font-size: 30px; width: 30px; height: 30px;"></span> <?php esc_html_e( 'HWsync - Backup, Restore & Maintenance', 'hwsync' ); ?></h1>
			<hr/>

			<?php if ( $status === 'restore_success' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo sprintf( esc_html__( 'CSV Restore Completed Successfully! Processed %d components and updated posts.', 'hwsync' ), $count ); ?></p></div>
			<?php elseif ( $status === 'wipe_success' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo sprintf( esc_html__( 'Database Clean Wipe Completed: Deleted %d component posts, truncated all tables, and reset AUTO_INCREMENT to 1.', 'hwsync' ), $deleted ); ?></p></div>
			<?php elseif ( $status === 'schedule_saved' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Automated Scheduled Sync Settings saved successfully!', 'hwsync' ); ?></p></div>
			<?php elseif ( $status === 'err_nofile' ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Please select a valid CSV file to restore.', 'hwsync' ); ?></p></div>
			<?php elseif ( $status === 'err_restore' ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'An error occurred during CSV restore.', 'hwsync' ); ?></p></div>
			<?php endif; ?>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-top: 20px;">
				
				<!-- Card 1: Export CSV -->
				<div style="background: #fff; padding: 22px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between;">
					<div>
						<h2 style="margin-top: 0; font-size: 18px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-download" style="color: #2563eb;"></span>
							<?php esc_html_e( 'Export Hardware Database (CSV)', 'hwsync' ); ?>
						</h2>
						<p style="color: #64748b; font-size: 13px; line-height: 1.6;">
							<?php esc_html_e( 'Export all canonical components, technical specifications, and multi-vendor pricing matrices into a clean CSV spreadsheet with UTF-8 Excel compatibility.', 'hwsync' ); ?>
						</p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
						<?php wp_nonce_field( 'hwsync_export_csv_action', 'hwsync_nonce' ); ?>
						<input type="hidden" name="action" value="hwsync_export_csv" />
						<button type="submit" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; height: 38px; padding: 0 20px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
							<span class="dashicons dashicons-media-spreadsheet"></span>
							<span><?php esc_html_e( 'Export & Download CSV', 'hwsync' ); ?></span>
						</button>
					</form>
				</div>

				<!-- Card 2: Restore CSV -->
				<div style="background: #fff; padding: 22px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between;">
					<div>
						<h2 style="margin-top: 0; font-size: 18px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-upload" style="color: #16a34a;"></span>
							<?php esc_html_e( 'Restore / Import from CSV', 'hwsync' ); ?>
						</h2>
						<p style="color: #64748b; font-size: 13px; line-height: 1.6;">
							<?php esc_html_e( 'Upload a previously exported HWsync CSV file to restore components, vendor pricing, and synchronize WordPress post catalog.', 'hwsync' ); ?>
						</p>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-top: 20px;">
						<?php wp_nonce_field( 'hwsync_restore_csv_action', 'hwsync_nonce' ); ?>
						<input type="hidden" name="action" value="hwsync_restore_csv" />
						<div style="margin-bottom: 12px;">
							<input type="file" name="csv_file" accept=".csv" required style="font-size: 12px;" />
						</div>
						<button type="submit" class="button button-primary" style="background: #16a34a; border-color: #15803d; height: 38px; padding: 0 20px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
							<span class="dashicons dashicons-backup"></span>
							<span><?php esc_html_e( 'Restore Catalog', 'hwsync' ); ?></span>
						</button>
					</form>
				</div>

				<!-- Card 3: Automated Scheduled Main Sync (Delta / Incremental Update) -->
				<div style="background: #fff; padding: 22px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; grid-column: 1 / -1;">
					<div>
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
							<h2 style="margin: 0; font-size: 18px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-clock" style="color: #6366f1;"></span>
								<?php esc_html_e( 'Automated Scheduled Main Sync (Incremental / Delta Updates)', 'hwsync' ); ?>
							</h2>
							<span style="background: <?php echo $schedule_enabled ? '#dcfce7; color: #15803d;' : '#f1f5f9; color: #64748b;'; ?> font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
								<?php echo $schedule_enabled ? esc_html__( 'Active', 'hwsync' ) : esc_html__( 'Disabled', 'hwsync' ); ?>
							</span>
						</div>
						<p style="color: #64748b; font-size: 13px; line-height: 1.6;">
							<?php esc_html_e( 'Schedule the main synchronization engine to run automatically at a specific time of day. Scheduled sync operates in lightweight Delta Mode: it only creates newly discovered listings and updates prices/stock for existing components without full catalog re-writes.', 'hwsync' ); ?>
						</p>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 16px; background: #f8fafc; padding: 18px; border-radius: 6px; border: 1px solid #e2e8f0;">
						<?php wp_nonce_field( 'hwsync_save_schedule_action', 'hwsync_nonce' ); ?>
						<input type="hidden" name="action" value="hwsync_save_schedule" />

						<div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap;">
							<label style="font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="checkbox" name="schedule_enabled" value="1" <?php checked( $schedule_enabled, 1 ); ?> />
								<span><?php esc_html_e( 'Enable Scheduled Background Sync', 'hwsync' ); ?></span>
							</label>

							<div style="display: flex; align-items: center; gap: 8px;">
								<label for="schedule_time" style="font-weight: 600; font-size: 13px;"><?php esc_html_e( 'Run At Time:', 'hwsync' ); ?></label>
								<input type="time" id="schedule_time" name="schedule_time" value="<?php echo esc_attr( $schedule_time ); ?>" style="height: 32px; border-radius: 4px; border: 1px solid #cbd5e1; padding: 0 8px;" />
							</div>

							<div style="display: flex; align-items: center; gap: 8px;">
								<label for="schedule_frequency" style="font-weight: 600; font-size: 13px;"><?php esc_html_e( 'Frequency:', 'hwsync' ); ?></label>
								<select id="schedule_frequency" name="schedule_frequency" style="height: 32px; border-radius: 4px; border: 1px solid #cbd5e1;">
									<option value="daily" <?php selected( $schedule_freq, 'daily' ); ?>><?php esc_html_e( 'Daily (Once a Day)', 'hwsync' ); ?></option>
									<option value="twicedaily" <?php selected( $schedule_freq, 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily (Every 12h)', 'hwsync' ); ?></option>
									<option value="every_six_hours" <?php selected( $schedule_freq, 'every_six_hours' ); ?>><?php esc_html_e( 'Every 6 Hours', 'hwsync' ); ?></option>
									<option value="hourly" <?php selected( $schedule_freq, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'hwsync' ); ?></option>
								</select>
							</div>

							<button type="submit" class="button button-primary" style="background: #6366f1; border-color: #4f46e5; height: 34px; font-weight: 600; border-radius: 6px;">
								<?php esc_html_e( 'Save Schedule Settings', 'hwsync' ); ?>
							</button>
						</div>

						<div style="margin-top: 12px; font-size: 12px; color: #475569;">
							<strong><?php esc_html_e( 'Next Scheduled Run:', 'hwsync' ); ?></strong> <?php echo esc_html( $next_run_str ); ?>
						</div>
					</form>
				</div>

			</div>

			<!-- Card 4: Danger Zone (Wipe & Reset to 1) -->
			<div style="margin-top: 24px; background: #fff; padding: 24px; border: 1px solid #fecaca; border-left: 5px solid #ef4444; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<h2 style="margin-top: 0; font-size: 18px; color: #b91c1c; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-trash" style="color: #ef4444;"></span>
					<?php esc_html_e( 'Danger Zone: Wipe Hardware & Reset All Records (IDs to 1)', 'hwsync' ); ?>
				</h2>
				<p style="color: #475569; font-size: 13px; line-height: 1.6; max-width: 800px;">
					<?php esc_html_e( 'This operation will permanently purge all hardware records: deletes all WordPress "hwsync_component" posts and postmeta, completely truncates all 3 custom database tables (wp_hwsync_components, wp_hwsync_vendor_prices, wp_hwsync_price_history), and resets table AUTO_INCREMENT IDs back to 1.', 'hwsync' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('⚠️ WARNING: This will permanently delete ALL hardware components, delete ALL WordPress component posts, and reset table IDs to 1. This action cannot be undone.\n\nAre you absolutely sure you want to proceed?');">
					<?php wp_nonce_field( 'hwsync_wipe_reset_action', 'hwsync_nonce' ); ?>
					<input type="hidden" name="action" value="hwsync_wipe_reset" />
					<button type="submit" class="button button-primary" style="background: #dc2626; border-color: #b91c1c; height: 38px; padding: 0 20px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; margin-top: 10px;">
						<span class="dashicons dashicons-warning"></span>
						<span><?php esc_html_e( 'Wipe Database & Reset IDs to 1', 'hwsync' ); ?></span>
					</button>
				</form>
			</div>
		</div>
		<?php
	}

	public static function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hwsync_export_csv_action', 'hwsync_nonce' ) ) {
			wp_die( \__( 'Unauthorized request', 'hwsync' ) );
		}
		Backup_Manager::export_csv();
	}

	public static function handle_restore_csv() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hwsync_restore_csv_action', 'hwsync_nonce' ) ) {
			wp_die( \__( 'Unauthorized request', 'hwsync' ) );
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=hwsync-maintenance&status=err_nofile' ) );
			exit;
		}

		$result = Backup_Manager::restore_csv( $_FILES['csv_file']['tmp_name'] );
		$status = ! empty( $result['success'] ) ? 'restore_success' : 'err_restore';
		$count  = isset( $result['components_imported'] ) ? intval( $result['components_imported'] ) : 0;
		wp_safe_redirect( admin_url( 'admin.php?page=hwsync-maintenance&status=' . $status . '&count=' . $count ) );
		exit;
	}

	public static function handle_save_schedule_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hwsync_save_schedule_action', 'hwsync_nonce' ) ) {
			wp_die( \__( 'Unauthorized request', 'hwsync' ) );
		}

		$enabled   = ! empty( $_POST['schedule_enabled'] );
		$frequency = isset( $_POST['schedule_frequency'] ) ? sanitize_text_field( $_POST['schedule_frequency'] ) : 'daily';
		$time_str  = isset( $_POST['schedule_time'] ) ? sanitize_text_field( $_POST['schedule_time'] ) : '03:00';

		Cron::update_schedule( $enabled, $frequency, $time_str );

		wp_safe_redirect( admin_url( 'admin.php?page=hwsync-maintenance&status=schedule_saved' ) );
		exit;
	}

	public static function handle_export_amazon_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( \__( 'Unauthorized request', 'hwsync' ) );
		}

		if ( isset( $_GET['hwsync_nonce'] ) ) {
			check_admin_referer( 'hwsync_export_amazon_csv_action', 'hwsync_nonce' );
		} elseif ( isset( $_REQUEST['nonce'] ) ) {
			check_ajax_referer( 'hwsync_manual_sync_action', 'nonce' );
		}

		global $wpdb;
		$prices_table = Database::get_table_name( 'vendor_prices' );
		$comp_table   = Database::get_table_name( 'components' );
		$vendor_table = Database::get_table_name( 'vendors' );

		$amazon_vendor = Vendor::find_by_slug( 'amazon-in' );
		$vendor_id = $amazon_vendor ? intval( $amazon_vendor->id ) : 0;

		$sql = "SELECT p.id as price_id, p.component_id, c.brand, c.model_name, c.category, p.vendor_sku as asin, p.price, p.product_url, p.stock_status, p.updated_at
				FROM {$prices_table} p
				LEFT JOIN {$comp_table} c ON p.component_id = c.id
				WHERE (p.vendor_id = %d OR p.product_url LIKE %s)
				ORDER BY c.category ASC, c.brand ASC, c.model_name ASC";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vendor_id, '%amazon.in%' ), \ARRAY_A );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		$filename = 'amazon-synced-products-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		// Output UTF-8 BOM for Excel
		fputs( $output, "\xEF\xBB\xBF" );

		// Header Row
		fputcsv( $output, array(
			'Price ID',
			'Component ID',
			'Brand',
			'Model Name',
			'Category',
			'ASIN / SKU',
			'Current Price (INR)',
			'Current Product URL',
			'Affiliate / Custom URL',
			'Stock Status',
			'Last Updated',
		) );

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				fputcsv( $output, array(
					$r['price_id'],
					$r['component_id'],
					$r['brand'],
					$r['model_name'],
					$r['category'],
					$r['asin'],
					number_format( floatval( $r['price'] ), 2, '.', '' ),
					$r['product_url'],
					$r['product_url'], // Prefill affiliate URL with current URL so user can simply append &tag= or edit
					$r['stock_status'],
					$r['updated_at'],
				) );
			}
		}

		fclose( $output );
		exit;
	}

	public static function handle_import_amazon_csv() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => \__( 'Unauthorized permission.', 'hwsync' ) ) );
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => \__( 'No valid CSV file uploaded.', 'hwsync' ) ) );
		}

		$handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
		if ( ! $handle ) {
			wp_send_json_error( array( 'message' => \__( 'Unable to open CSV file for reading.', 'hwsync' ) ) );
		}

		// Read header
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			wp_send_json_error( array( 'message' => \__( 'CSV file is empty.', 'hwsync' ) ) );
		}

		// Normalize header keys
		$header_map = array();
		foreach ( $header as $idx => $h ) {
			$clean = strtolower( trim( (string) $h ) );
			$clean = preg_replace( '/[^a-z0-9]/', '_', $clean );
			$header_map[ $clean ] = $idx;
		}

		global $wpdb;
		$prices_table = Database::get_table_name( 'vendor_prices' );

		$updated_count = 0;
		$skipped_count = 0;
		$total_rows = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( empty( $row ) || count( $row ) === 0 || empty( array_filter( $row ) ) ) {
				continue;
			}
			$total_rows++;

			$price_id = 0;
			if ( isset( $header_map['price_id'] ) && isset( $row[ $header_map['price_id'] ] ) ) {
				$price_id = intval( $row[ $header_map['price_id'] ] );
			}

			$component_id = 0;
			if ( isset( $header_map['component_id'] ) && isset( $row[ $header_map['component_id'] ] ) ) {
				$component_id = intval( $row[ $header_map['component_id'] ] );
			}

			$asin = '';
			if ( isset( $header_map['asin___sku'] ) && isset( $row[ $header_map['asin___sku'] ] ) ) {
				$asin = trim( $row[ $header_map['asin___sku'] ] );
			} elseif ( isset( $header_map['asin'] ) && isset( $row[ $header_map['asin'] ] ) ) {
				$asin = trim( $row[ $header_map['asin'] ] );
			}

			$affiliate_url = '';
			if ( isset( $header_map['affiliate___custom_url'] ) && isset( $row[ $header_map['affiliate___custom_url'] ] ) ) {
				$affiliate_url = trim( $row[ $header_map['affiliate___custom_url'] ] );
			} elseif ( isset( $header_map['affiliate_url'] ) && isset( $row[ $header_map['affiliate_url'] ] ) ) {
				$affiliate_url = trim( $row[ $header_map['affiliate_url'] ] );
			} elseif ( isset( $header_map['current_product_url'] ) && isset( $row[ $header_map['current_product_url'] ] ) ) {
				$affiliate_url = trim( $row[ $header_map['current_product_url'] ] );
			} elseif ( isset( $header_map['product_url'] ) && isset( $row[ $header_map['product_url'] ] ) ) {
				$affiliate_url = trim( $row[ $header_map['product_url'] ] );
			}

			if ( empty( $affiliate_url ) || ! filter_var( $affiliate_url, FILTER_VALIDATE_URL ) ) {
				$skipped_count++;
				continue;
			}

			$sanitized_url = esc_url_raw( $affiliate_url );

			$updated = false;
			if ( $price_id > 0 ) {
				$res = $wpdb->update(
					$prices_table,
					array(
						'product_url' => $sanitized_url,
						'updated_at'  => current_time( 'mysql' ),
					),
					array( 'id' => $price_id )
				);
				if ( $res !== false ) {
					$updated = true;
				}
			} elseif ( $component_id > 0 && ! empty( $asin ) ) {
				$res = $wpdb->update(
					$prices_table,
					array(
						'product_url' => $sanitized_url,
						'updated_at'  => current_time( 'mysql' ),
					),
					array(
						'component_id' => $component_id,
						'vendor_sku'   => $asin,
					)
				);
				if ( $res !== false ) {
					$updated = true;
				}
			}

			if ( $updated ) {
				$updated_count++;
			} else {
				$skipped_count++;
			}
		}

		fclose( $handle );

		wp_send_json_success( array(
			'updated' => $updated_count,
			'skipped' => $skipped_count,
			'total'   => $total_rows,
			'message' => sprintf( \__( 'Successfully processed %1$d rows: updated %2$d Amazon product URLs (%3$d unchanged/skipped).', 'hwsync' ), $total_rows, $updated_count, $skipped_count ),
		) );
	}

	public static function handle_wipe_reset() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hwsync_wipe_reset_action', 'hwsync_nonce' ) ) {
			wp_die( \__( 'Unauthorized request', 'hwsync' ) );
		}

		$result = Backup_Manager::wipe_and_reset_all_data();
		$deleted = isset( $result['deleted_posts_count'] ) ? intval( $result['deleted_posts_count'] ) : 0;
		wp_safe_redirect( admin_url( 'admin.php?page=hwsync-maintenance&status=wipe_success&deleted=' . $deleted ) );
		exit;
	}

	public static function handle_delete_components() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => \__( 'Unauthorized permission.', 'hwsync' ) ) );
		}

		$component_ids = isset( $_POST['component_ids'] ) ? array_map( 'intval', (array) $_POST['component_ids'] ) : array();
		$component_ids = array_filter( $component_ids );

		if ( empty( $component_ids ) ) {
			wp_send_json_error( array( 'message' => \__( 'No components selected for deletion.', 'hwsync' ) ) );
		}

		$deleted_count = 0;
		foreach ( $component_ids as $id ) {
			$comp = Component::find_by_id( $id );
			if ( $comp && $comp->delete() ) {
				$deleted_count++;
			}
		}

		wp_send_json_success( array(
			'deleted' => $deleted_count,
			'message' => sprintf( \__( 'Successfully deleted %d component(s) and their linked store prices.', 'hwsync' ), $deleted_count ),
		) );
	}

	public static function handle_delete_vendor_records() {
		check_ajax_referer( 'hwsync_manual_sync_action', 'hwsync_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => \__( 'Unauthorized permission.', 'hwsync' ) ) );
		}

		$vendor_slug   = isset( $_POST['vendor_slug'] ) ? sanitize_text_field( $_POST['vendor_slug'] ) : '';
		$component_ids = isset( $_POST['component_ids'] ) ? array_map( 'intval', (array) $_POST['component_ids'] ) : array();

		if ( empty( $vendor_slug ) || $vendor_slug === 'all' ) {
			wp_send_json_error( array( 'message' => \__( 'No valid vendor selected for record deletion.', 'hwsync' ) ) );
		}

		$result = Component::delete_vendor_records( $vendor_slug, $component_ids );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	public static function handle_restore_default_vendors() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hwsync_restore_default_vendors_action', 'hwsync_nonce' ) ) {
			wp_die( \__( 'Unauthorized request', 'hwsync' ) );
		}

		Database::seed_default_vendors();
		wp_safe_redirect( admin_url( 'admin.php?page=hwsync-vendors&status=seeded' ) );
		exit;
	}
}
