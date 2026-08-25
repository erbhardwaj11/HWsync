<?php
/**
 * Frontend Price Comparison Table Template
 *
 * @var \HWsync\Models\Component $component
 * @var array $prices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="hwsync-price-comparison-widget" id="hwsync-comp-<?php echo esc_attr( $component->id ); ?>">
	<div class="hwsync-widget-header">
		<h3 class="hwsync-widget-title">
			<span class="dashicons dashicons-money-alt"></span>
			<?php esc_html_e( 'Live Price Comparison', 'hwsync' ); ?>
		</h3>
		<span class="hwsync-retailers-count">
			<?php echo sprintf( esc_html__( '%d Retailers Compared', 'hwsync' ), count( $prices ) ); ?>
		</span>
	</div>

	<?php if ( empty( $prices ) ) : ?>
		<p class="hwsync-no-prices"><?php esc_html_e( 'No vendor pricing currently available.', 'hwsync' ); ?></p>
	<?php else : ?>
		<div class="hwsync-table-responsive">
			<table class="hwsync-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Retailer', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Listing Title', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Stock', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Price (INR)', 'hwsync' ); ?></th>
						<th><?php esc_html_e( 'Action', 'hwsync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$is_lowest = true;
					foreach ( $prices as $p ) :
						$buy_url = ! empty( $p->affiliate_url ) ? $p->affiliate_url : $p->product_url;
						$in_stock = (bool) $p->is_in_stock;
					?>
						<tr class="<?php echo $is_lowest && $in_stock ? 'hwsync-lowest-deal' : ''; ?>">
							<td class="hwsync-col-store">
								<span class="hwsync-store-name">
									<strong><?php echo esc_html( $p->vendor_name ?: ucfirst( $p->vendor_slug ) ); ?></strong>
								</span>
							</td>
							<td class="hwsync-col-title">
								<span class="hwsync-vendor-title" title="<?php echo esc_attr( $p->vendor_product_title ); ?>">
									<?php echo esc_html( wp_trim_words( $p->vendor_product_title, 8, '...' ) ); ?>
								</span>
							</td>
							<td class="hwsync-col-stock">
								<?php if ( $in_stock ) : ?>
									<span class="hwsync-badge hwsync-badge-instock"><?php esc_html_e( 'In Stock', 'hwsync' ); ?></span>
								<?php else : ?>
									<span class="hwsync-badge hwsync-badge-outofstock"><?php esc_html_e( 'Out of Stock', 'hwsync' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="hwsync-col-price">
								<span class="hwsync-price-value">₹<?php echo esc_html( number_format( $p->price, 2 ) ); ?></span>
								<?php if ( ! empty( $p->original_price ) && $p->original_price > $p->price ) : ?>
									<del class="hwsync-original-price">₹<?php echo esc_html( number_format( $p->original_price, 2 ) ); ?></del>
								<?php endif; ?>
								<?php if ( $is_lowest && $in_stock ) : ?>
									<span class="hwsync-best-tag"><?php esc_html_e( 'Best Price', 'hwsync' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="hwsync-col-action">
								<a href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="nofollow noopener sponsored" class="hwsync-buy-btn <?php echo ! $in_stock ? 'hwsync-btn-disabled' : ''; ?>">
									<?php echo $in_stock ? esc_html__( 'Buy Now', 'hwsync' ) : esc_html__( 'View Store', 'hwsync' ); ?>
									<span class="dashicons dashicons-external"></span>
								</a>
							</td>
						</tr>
					<?php
						if ( $in_stock ) {
							$is_lowest = false;
						}
					endforeach;
					?>
				</tbody>
			</table>
		</div>
		<p class="hwsync-disclaimer">
			<small><?php esc_html_e( 'Prices and stock availability are updated periodically and are subject to retailer changes.', 'hwsync' ); ?></small>
		</p>
	<?php endif; ?>
</div>
