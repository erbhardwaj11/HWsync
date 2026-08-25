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

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 20px 0;">
				<div style="background: #fff; border-left: 4px solid #2563eb; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin:0 0 8px 0; color: #64748b;"><?php esc_html_e( 'Active Vendors', 'hwsync' ); ?></h3>
					<div style="font-size: 28px; font-weight: bold; color: #1e293b;"><?php echo intval( $total_vendors ); ?></div>
				</div>
				<div style="background: #fff; border-left: 4px solid #16a34a; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin:0 0 8px 0; color: #64748b;"><?php esc_html_e( 'Canonical Components', 'hwsync' ); ?></h3>
					<div style="font-size: 28px; font-weight: bold; color: #1e293b;"><?php echo intval( $total_components ); ?></div>
				</div>
				<div style="background: #fff; border-left: 4px solid #f59e0b; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<h3 style="margin:0 0 8px 0; color: #64748b;"><?php esc_html_e( 'Live Vendor Prices', 'hwsync' ); ?></h3>
					<div style="font-size: 28px; font-weight: bold; color: #1e293b;"><?php echo intval( $total_prices ); ?> <small style="font-size: 14px; color: #16a34a;">(<?php echo intval( $in_stock_prices ); ?> in stock)</small></div>
				</div>
			</div>

			<div style="background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 24px;">
				<h2><?php esc_html_e( 'Trigger Manual Sync', 'hwsync' ); ?></h2>
				<p><?php esc_html_e( 'Initiate an on-demand scrape and match cycle across Indian PC retail vendors, updating component tables and creating/updating WordPress posts.', 'hwsync' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'hwsync_manual_sync_action', 'hwsync_nonce' ); ?>
					<input type="hidden" name="action" value="hwsync_manual_sync" />
					<table class="form-table" style="max-width: 600px;">
						<tr>
							<th scope="row"><label for="target_vendor"><?php esc_html_e( 'Target Vendor', 'hwsync' ); ?></label></th>
							<td>
								<select name="target_vendor" id="target_vendor" class="regular-text">
									<option value="all"><?php esc_html_e( 'All Active Retailers', 'hwsync' ); ?></option>
									<?php foreach ( Vendor::get_all() as $v ) : ?>
										<option value="<?php echo esc_attr( $v->vendor_slug ); ?>"><?php echo esc_html( $v->vendor_name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="target_category"><?php esc_html_e( 'Category', 'hwsync' ); ?></label></th>
							<td>
								<select name="target_category" id="target_category" class="regular-text">
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
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Start Sync Now', 'hwsync' ), 'primary', 'submit_sync' ); ?>
				</form>
			</div>

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
}
