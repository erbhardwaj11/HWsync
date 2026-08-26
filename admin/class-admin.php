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
		add_action( 'admin_post_hwsync_save_schedule', array( __CLASS__, 'handle_save_schedule_settings' ) );
		add_action( 'wp_ajax_hwsync_sync_batch', array( __CLASS__, 'handle_sync_batch' ) );
		add_action( 'wp_ajax_hwsync_sync_specs_chunk', array( __CLASS__, 'handle_sync_specs_chunk' ) );
		add_action( 'wp_ajax_hwsync_stream_sync', array( __CLASS__, 'handle_stream_sync' ) );
		add_action( 'wp_ajax_hwsync_stream_specs_sync', array( __CLASS__, 'handle_stream_specs_sync' ) );
		add_action( 'wp_ajax_hwsync_process_browser_batch', array( __CLASS__, 'handle_browser_batch' ) );
		add_action( 'wp_ajax_hwsync_save_vendor', array( __CLASS__, 'handle_save_vendor' ) );
		add_action( 'wp_ajax_hwsync_delete_vendor', array( __CLASS__, 'handle_delete_vendor' ) );
		add_action( 'wp_ajax_hwsync_test_vendor_sync', array( __CLASS__, 'handle_test_vendor_sync' ) );
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

							<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 15px;">
								<button type="button" id="btn-start-live-sync" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; padding: 6px 16px; font-weight: 600; border-radius: 6px; height: 38px; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-update" style="line-height: 1;"></span>
									<span><?php esc_html_e( 'Live Scrape Sync', 'hwsync' ); ?></span>
								</button>
								<button type="button" id="btn-sync-specs" class="button" style="background: #0284c7; border-color: #0369a1; color: #fff; padding: 6px 14px; font-weight: 600; border-radius: 6px; height: 38px; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-admin-generic" style="line-height: 1;"></span>
									<span><?php esc_html_e( 'Sync Specs', 'hwsync' ); ?></span>
								</button>
								<button type="button" id="btn-stop-sync" class="button" style="display: none; border-color: #ef4444; color: #ef4444; height: 38px; border-radius: 6px;">
									<?php esc_html_e( 'Stop Sync', 'hwsync' ); ?>
								</button>
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
					<div style="background: #131d31; padding: 8px 16px; border-bottom: 1px solid #1e293b; display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; font-size: 11px; text-align: center;">
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
					</div>

					<!-- Terminal Stream Area -->
					<div id="hwsync-terminal" style="flex: 1; padding: 14px 16px; overflow-y: auto; max-height: 320px; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 12px; line-height: 1.6; color: #e2e8f0; background: #0b0f19;">
						<div class="log-line log-muted" style="color: #64748b;">
							<span style="color: #475569;">[--:--:--]</span> HWsync Live Engine ready. Click "Start Live Sync" to scrape or "Sync Specs" to extract deep technical specifications.
						</div>
					</div>

				</div>

			</div>

			<!-- Live Streaming JS Controller -->
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				var startBtn = document.getElementById('btn-start-live-sync');
				var syncSpecsBtn = document.getElementById('btn-sync-specs');
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
					startBtn.innerHTML = '<span class="dashicons dashicons-update spin" style="animation: rotation 1s infinite linear;"></span> Syncing...';
					stopBtn.style.display = 'inline-block';

					statusDot.style.background = '#22c55e';
					statusDot.style.boxShadow = '0 0 10px #22c55e';
					statusBadge.textContent = 'RUNNING';
					statusBadge.style.background = '#15803d';
					statusBadge.style.color = '#fff';

					appendLog('info', 'Starting rock-solid live sync for Vendor: [' + vendor + '], Category: [' + category + ']...');

					abortController = new AbortController();

					runChunkedMainSync(vendor, category, nonce);
				});

				syncSpecsBtn.addEventListener('click', function() {
					var category = document.getElementById('target_category').value;
					var nonce = document.querySelector('input[name="hwsync_nonce"]').value;

					startBtn.disabled = true;
					syncSpecsBtn.disabled = true;
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

				function runChunkedMainSync(vendorChoice, categoryChoice, nonce) {
					var allVendors = (vendorChoice === 'all') 
						? ['primeabgb', 'pcstudio', 'elitehubs'] 
						: (vendorChoice === 'mdcomputers' ? [] : [vendorChoice]);
					
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

						if (currentVendorIdx >= allVendors.length) {
							if (vendorChoice === 'all' || vendorChoice === 'mdcomputers') {
								appendLog('info', '=== Running In-Browser Headless Scraper for MDComputers ===');
								runBrowserHeadlessSync('mdcomputers', categoryChoice, nonce, function() {
									appendLog('success', 'Full sync cycle completed across all retailers!');
									finishSync();
								});
							} else {
								appendLog('success', 'Full sync cycle completed successfully!');
								finishSync();
							}
							return;
						}

						var curVendor = allVendors[currentVendorIdx++];
						var curCatIdx = 0;
						appendLog('info', '=== Connecting to ' + curVendor.toUpperCase() + ' ===');

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
							var retryCount = 0;

							function fetchPageStep() {
								if (abortController && abortController.signal.aborted) {
									finishSync();
									return;
								}

								var postData = new URLSearchParams();
								postData.append('action', 'hwsync_sync_batch');
								postData.append('target_vendor', curVendor);
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
											retryCount = 0;
											fetchPageStep();
										} else {
											processNextCategory();
										}
									} else {
										appendLog('warning', '[' + curVendor + '] Category ' + curCat + ' ended on Page ' + curPage);
										processNextCategory();
									}
								}).catch(function(err) {
									if (err.name === 'AbortError') {
										appendLog('warning', 'Sync aborted.');
										finishSync();
									} else if (retryCount < 2) {
										retryCount++;
										appendLog('warning', '[' + curVendor + ' - ' + curCat + ' Page ' + curPage + '] Network hiccup (' + err.message + '). Retrying step (' + retryCount + '/2)...');
										setTimeout(fetchPageStep, 1500);
									} else {
										appendLog('error', '[' + curVendor + ' - ' + curCat + '] Error after retries: ' + err.message + '. Moving to next category.');
										processNextCategory();
									}
								});
							}

							fetchPageStep();
						}

						processNextCategory();
					}

					processNextVendor();
				}

				function runChunkedSpecsSync(targetCategory, nonce) {
					var currentOffset = 0;
					var retryCount = 0;

					function fetchSpecsStep() {
						if (abortController && abortController.signal.aborted) {
							appendLog('warning', 'Specs Sync aborted by user.');
							finishSync();
							return;
						}

						var postData = new URLSearchParams();
						postData.append('action', 'hwsync_sync_specs_chunk');
						postData.append('target_category', targetCategory);
						postData.append('offset', currentOffset);
						postData.append('limit', 5);
						postData.append('hwsync_nonce', nonce);

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: postData.toString(),
							signal: abortController ? abortController.signal : null
						}).then(function(res) {
							return res.text();
						}).then(function(responseText) {
							try {
								var json = JSON.parse(responseText);
								if (json.success && json.data) {
									var d = json.data;
									if (d.logs && Array.isArray(d.logs)) {
										d.logs.forEach(function(l) {
											appendLog(l.level, l.message);
										});
									}

									var curSpecs = parseInt(mSpecs.textContent) || 0;
									mSpecs.textContent = curSpecs + (d.updated || 0);

									if (d.has_more && d.next_offset) {
										currentOffset = d.next_offset;
										retryCount = 0;
										fetchSpecsStep();
									} else {
										appendLog('finish', 'Technical Specifications Sync completed for all components in database!');
										finishSync();
									}
								} else {
									appendLog('finish', 'Specs sync completed.');
									finishSync();
								}
							} catch (e) {
								appendLog('warning', 'Specs response parsing issue: ' + responseText.substring(0, 150));
								if (retryCount < 2) {
									retryCount++;
									setTimeout(fetchSpecsStep, 2000);
								} else {
									finishSync();
								}
							}
						}).catch(function(err) {
							if (err.name === 'AbortError') {
								appendLog('warning', 'Specs sync aborted.');
								finishSync();
							} else if (retryCount < 2) {
								retryCount++;
								appendLog('warning', 'Specs sync transient error (' + err.message + '). Retrying batch in 2s (' + retryCount + '/2)...');
								setTimeout(fetchSpecsStep, 2000);
							} else {
								appendLog('error', 'Specs sync error: ' + err.message);
								finishSync();
							}
						});
					}

					fetchSpecsStep();
				}

				function runBrowserHeadlessSync(vendorSlug, category, nonce, nextCallback) {
					var endpoints = {
						'cpu': 'https://mdcomputers.in/catalog/processor',
						'gpu': 'https://mdcomputers.in/catalog/graphics-card',
						'motherboard': 'https://mdcomputers.in/catalog/motherboard',
						'ram': 'https://mdcomputers.in/catalog/ram/desktop-ram',
						'storage': 'https://mdcomputers.in/catalog/storage',
						'psu': 'https://mdcomputers.in/catalog/smps',
						'cooler': 'https://mdcomputers.in/cooling-system.html',
						'cabinet': 'https://mdcomputers.in/catalog/cabinet'
					};

					var catsToSync = (category === 'all') ? Object.keys(endpoints) : [category];
					var currentCatIndex = 0;

					function processNextCategory() {
						if (currentCatIndex >= catsToSync.length) {
							appendLog('success', 'In-Browser headless sync completed for MDComputers.');
							if (typeof nextCallback === 'function') {
								nextCallback();
							} else {
								finishSync();
							}
							return;
						}

						var currentCat = catsToSync[currentCatIndex++];
						var baseEndpoint = endpoints[currentCat] || endpoints['cpu'];
						var currentPage = 1;
						var maxPages = 25;

						function fetchCategoryPage(page) {
							if (page > maxPages) {
								processNextCategory();
								return;
							}

							var pageUrl = baseEndpoint + (baseEndpoint.indexOf('?') !== -1 ? '&' : '?') + 'page=' + page;
							appendLog('info', '[MDComputers] In-Browser Headless Request [' + currentCat + '] Page ' + page + ' (' + pageUrl + ')...');

							fetch(pageUrl, {
								method: 'GET',
								credentials: 'omit',
								signal: abortController ? abortController.signal : null
							}).then(function(resp) {
								return resp.text();
							}).then(function(htmlText) {
								var parser = new DOMParser();
								var doc = parser.parseFromString(htmlText, 'text/html');
								var productElements = doc.querySelectorAll('.product-grid-item, .product-item-container, .product-thumb, .product-layout');

								if (!productElements || productElements.length === 0) {
									appendLog('debug', '[MDComputers] No more items found on Page ' + page + ' for [' + currentCat + '].');
									processNextCategory();
									return;
								}

								appendLog('info', '[MDComputers] Detected ' + productElements.length + ' raw cards on Page ' + page + '.');

								var parsedItems = [];
								productElements.forEach(function(el) {
									var titleEl = el.querySelector('h3 a, h4 a, .product-entities-title a, .name a');
									if (!titleEl) return;
									var title = titleEl.textContent.trim();
									var link = titleEl.getAttribute('href');

									// Stock status check
									var cardText = el.textContent.toLowerCase();
									var isOutOfStock = cardText.indexOf('out of stock') !== -1 || cardText.indexOf('sold out') !== -1;
									if (isOutOfStock) {
										appendLog('debug', '[MDComputers] Skipped Out-of-Stock: "' + title + '"');
										return;
									}

									// Price extraction: prioritize discounted price-new first
									var price = 0;
									var priceNewEl = el.querySelector('.price-new, .special-price, ins .amount');
									if (priceNewEl) {
										var pMatch = priceNewEl.textContent.replace(/,/g, '').match(/[\d]+(?:\.\d+)?/);
										if (pMatch) price = parseFloat(pMatch[0]);
									} else {
										var priceEl = el.querySelector('.price, .amount');
										if (priceEl) {
											var clone = priceEl.cloneNode(true);
											var oldEls = clone.querySelectorAll('.price-old, del, .price-tax');
											oldEls.forEach(function(o) { o.remove(); });
											var pMatch = clone.textContent.replace(/,/g, '').match(/[\d]+(?:\.\d+)?/);
											if (pMatch) price = parseFloat(pMatch[0]);
										}
									}

									var priceDisplay = (price > 0) ? '₹' + price.toFixed(2) : 'NA';

									parsedItems.push({
										title: title,
										url: link,
										price: price,
										in_stock: true,
										stock_status: 'in_stock',
										category: currentCat,
										vendor_slug: 'mdcomputers',
										raw_data: { raw_title: title, display_price: priceDisplay }
									});
								});

								if (parsedItems.length === 0) {
									fetchCategoryPage(page + 1);
									return;
								}

								appendLog('info', '[MDComputers] Sending ' + parsedItems.length + ' in-stock items (Page ' + page + ') to database...');

								var batchData = new URLSearchParams();
								batchData.append('action', 'hwsync_process_browser_batch');
								batchData.append('vendor_slug', 'mdcomputers');
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

										appendLog('match', '[MDComputers] Page ' + page + ': Synced ' + (d.prices_saved || 0) + ' prices into component catalog.');
									}
									fetchCategoryPage(page + 1);
								}).catch(function(err) {
									appendLog('warning', '[MDComputers] Batch save warning: ' + err.message);
									fetchCategoryPage(page + 1);
								});
							}).catch(function(err) {
								appendLog('warning', '[MDComputers] In-browser request ended on Page ' + page + ': ' + err.message);
								processNextCategory();
							});
						}

						fetchCategoryPage(1);
					}

					processNextCategory();
				}

				stopBtn.addEventListener('click', function() {
					if (abortController) {
						abortController.abort();
					}
					finishSync();
				});

				function finishSync() {
					startBtn.disabled = false;
					startBtn.innerHTML = '<span class="dashicons dashicons-update"></span> <?php esc_html_e( "Start Live Sync", "hwsync" ); ?>';
					syncSpecsBtn.disabled = false;
					syncSpecsBtn.innerHTML = '<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( "Sync Specs", "hwsync" ); ?>';
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
				<div>
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
		$components = Component::get_all( array( 'limit' => 50 ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Canonical Component Catalog', 'hwsync' ); ?></h1>
			<p><?php esc_html_e( 'Normalized hardware items and aggregated multi-vendor prices.', 'hwsync' ); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Brand & Model', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Category', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'MPN / SKU', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Linked WP Post', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Lowest Price', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Stores', 'hwsync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $components ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No components synced yet. Run a sync to populate catalog.', 'hwsync' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $components as $comp ) :
							$prices = $comp->get_prices();
							$lowest = $comp->get_lowest_price();
						?>
							<tr>
								<td><strong><?php echo esc_html( $comp->brand . ' ' . $comp->model_name ); ?></strong></td>
								<td><span class="badge"><?php echo esc_html( ucfirst( $comp->category ) ); ?></span></td>
								<td><code><?php echo esc_html( $comp->mpn ?: ( $comp->sku ?: '-' ) ); ?></code></td>
								<td>
									<?php if ( $comp->wp_post_id ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $comp->wp_post_id ) ); ?>" target="_blank">Post #<?php echo intval( $comp->wp_post_id ); ?></a>
									<?php else : ?>
										-
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $lowest ) : ?>
										<strong style="color: #16a34a;">₹<?php echo esc_html( number_format( $lowest->price, 2 ) ); ?></strong>
										<br/><small>(<?php echo esc_html( $lowest->vendor_name ); ?>)</small>
									<?php else : ?>
										-
									<?php endif; ?>
								</td>
								<td><?php echo count( $prices ); ?> <?php esc_html_e( 'stores', 'hwsync' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
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

		$vendor = $id ? Vendor::find_by_id( $id ) : new Vendor();
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

	public static function handle_wipe_reset() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hwsync_wipe_reset_action', 'hwsync_nonce' ) ) {
			wp_die( \__( 'Unauthorized request', 'hwsync' ) );
		}

		$result = Backup_Manager::wipe_and_reset_all_data();
		$deleted = isset( $result['deleted_posts_count'] ) ? intval( $result['deleted_posts_count'] ) : 0;
		wp_safe_redirect( admin_url( 'admin.php?page=hwsync-maintenance&status=wipe_success&deleted=' . $deleted ) );
		exit;
	}
}
