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
		add_action( 'wp_ajax_hwsync_stream_sync', array( __CLASS__, 'handle_stream_sync' ) );
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

							<div style="margin-bottom: 20px;">
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

							<div style="display: flex; gap: 10px; align-items: center;">
								<button type="button" id="btn-start-live-sync" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; padding: 6px 20px; font-weight: 600; border-radius: 6px; height: 38px; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-update" style="line-height: 1;"></span>
									<span><?php esc_html_e( 'Start Live Sync', 'hwsync' ); ?></span>
								</button>
								<button type="button" id="btn-stop-sync" class="button" style="display: none; border-color: #ef4444; color: #ef4444; height: 38px; border-radius: 6px;">
									<?php esc_html_e( 'Stop Sync', 'hwsync' ); ?>
								</button>
							</div>
						</form>
					</div>

					<div style="margin-top: 20px; padding-top: 14px; border-top: 1px solid #f1f5f9; color: #94a3b8; font-size: 12px;">
						<?php esc_html_e( 'Sync processes vendor listings, creates/links component tables, and auto-generates WordPress component posts.', 'hwsync' ); ?>
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
							WP Posts: <strong id="m-posts" style="color: #4ade80;">0</strong>
						</div>
					</div>

					<!-- Terminal Stream Area -->
					<div id="hwsync-terminal" style="flex: 1; padding: 14px 16px; overflow-y: auto; max-height: 320px; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 12px; line-height: 1.6; color: #e2e8f0; background: #0b0f19;">
						<div class="log-line log-muted" style="color: #64748b;">
							<span style="color: #475569;">[--:--:--]</span> HWsync Live Engine ready. Click "Start Live Sync" to begin scraping and synchronizing.
						</div>
					</div>

				</div>

			</div>

			<!-- Live Streaming JS Controller -->
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				var startBtn = document.getElementById('btn-start-live-sync');
				var stopBtn = document.getElementById('btn-stop-sync');
				var clearBtn = document.getElementById('btn-clear-console');
				var terminal = document.getElementById('hwsync-terminal');
				var statusDot = document.getElementById('console-status-dot');
				var statusBadge = document.getElementById('console-status-badge');
				var chkAutoScroll = document.getElementById('chk-autoscroll');

				var mScraped = document.getElementById('m-scraped');
				var mMatched = document.getElementById('m-matched');
				var mPrices = document.getElementById('m-prices');
				var mPosts = document.getElementById('m-posts');

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
						'<span style="color:' + color + '; font-weight:600; text-transform:uppercase; font-size:11px;">[' + level + ']</span> ' +
						'<span style="color:' + (level === 'error' ? '#fca5a5' : '#e2e8f0') + ';">' + escapeHtml(message) + '</span>';

					terminal.appendChild(line);

					if (chkAutoScroll.checked) {
						terminal.scrollTop = terminal.scrollHeight;
					}
				}

				function escapeHtml(text) {
					var div = document.createElement('div');
					div.textContent = text;
					return div.innerHTML;
				}

				clearBtn.addEventListener('click', function() {
					terminal.innerHTML = '';
					appendLog('info', 'Console cleared.');
				});

				startBtn.addEventListener('click', function() {
					var vendor = document.getElementById('target_vendor').value;
					var category = document.getElementById('target_category').value;
					var nonce = document.querySelector('input[name="hwsync_nonce"]').value;

					startBtn.disabled = true;
					startBtn.innerHTML = '<span class="dashicons dashicons-update spin" style="animation: rotation 1s infinite linear;"></span> Syncing...';
					stopBtn.style.display = 'inline-block';

					statusDot.style.background = '#22c55e';
					statusDot.style.boxShadow = '0 0 10px #22c55e';
					statusBadge.textContent = 'RUNNING';
					statusBadge.style.background = '#15803d';
					statusBadge.style.color = '#fff';

					appendLog('info', 'Starting live sync for Vendor: [' + vendor + '], Category: [' + category + ']...');

					abortController = new AbortController();

					var postData = new URLSearchParams();
					postData.append('action', 'hwsync_stream_sync');
					postData.append('target_vendor', vendor);
					postData.append('target_category', category);
					postData.append('hwsync_nonce', nonce);

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: postData.toString(),
						signal: abortController.signal
					}).then(function(response) {
						var reader = response.body.getReader();
						var decoder = new TextDecoder('utf-8');
						var buffer = '';

						function readChunk() {
							return reader.read().then(function(result) {
								if (result.done) {
									finishSync();
									return;
								}
								buffer += decoder.decode(result.value, { stream: true });
								var lines = buffer.split('\n\n');
								buffer = lines.pop(); // Keep partial chunk

								lines.forEach(function(block) {
									var trimmed = block.trim();
									if (trimmed.startsWith('data:')) {
										try {
											var jsonStr = trimmed.substring(5).trim();
											var data = JSON.parse(jsonStr);
											appendLog(data.level, data.message, data.timestamp);

											if (data.stats) {
												if (data.stats.total_items !== undefined) mScraped.textContent = data.stats.total_items;
												if (data.stats.components !== undefined) mMatched.textContent = data.stats.components;
												if (data.stats.prices !== undefined) mPrices.textContent = data.stats.prices;
												if (data.stats.posts !== undefined) mPosts.textContent = data.stats.posts;
											}
										} catch (e) {
											// Ignore parse errors on raw stream fragments
										}
									}
								});

								return readChunk();
							});
						}

						return readChunk();
					}).catch(function(err) {
						if (err.name === 'AbortError') {
							appendLog('warning', 'Sync aborted by user.');
						} else {
							appendLog('error', 'Sync stream error: ' + err.message);
						}
						finishSync();
					});
				});

				stopBtn.addEventListener('click', function() {
					if (abortController) {
						abortController.abort();
					}
					finishSync();
				});

				function finishSync() {
					startBtn.disabled = false;
					startBtn.innerHTML = '<span class="dashicons dashicons-update"></span> <?php esc_html_e( "Start Live Sync", "hwsync" ); ?>';
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Registered Indian PC Hardware Retailers', 'hwsync' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Vendor Name', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Base Store URL', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Status', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Last Sync', 'hwsync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $vendors as $vendor ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $vendor->vendor_name ); ?></strong></td>
							<td><code><?php echo esc_html( $vendor->vendor_slug ); ?></code></td>
							<td><a href="<?php echo esc_url( $vendor->base_url ); ?>" target="_blank"><?php echo esc_html( $vendor->base_url ); ?></a></td>
							<td>
								<span style="color: <?php echo $vendor->is_active ? '#16a34a' : '#94a3b8'; ?>; font-weight: bold;">
									<?php echo $vendor->is_active ? esc_html__( 'Active', 'hwsync' ) : esc_html__( 'Disabled', 'hwsync' ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $vendor->last_sync_at ?: __( 'Never', 'hwsync' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
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
}
