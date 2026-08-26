<?php
namespace HWsync;

use HWsync\Models\Component;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post_Sync_Processor {

	const POST_TYPE = 'hwsync_component';
	const TAXONOMY_CAT = 'hwsync_category';
	const TAXONOMY_BRAND = 'hwsync_brand';

	public static function register_post_type() {
		$labels = array(
			'name'               => _x( 'PC Components', 'post type general name', 'hwsync' ),
			'singular_name'      => _x( 'PC Component', 'post type singular name', 'hwsync' ),
			'menu_name'          => _x( 'Hardware Sync', 'admin menu', 'hwsync' ),
			'name_admin_bar'     => _x( 'Component', 'add new on admin bar', 'hwsync' ),
			'add_new'            => _x( 'Add New', 'component', 'hwsync' ),
			'add_new_item'       => __( 'Add New Component', 'hwsync' ),
			'new_item'           => __( 'New Component', 'hwsync' ),
			'edit_item'          => __( 'Edit Component', 'hwsync' ),
			'view_item'          => __( 'View Component', 'hwsync' ),
			'all_items'          => __( 'All Components', 'hwsync' ),
			'search_items'       => __( 'Search Components', 'hwsync' ),
			'not_found'          => __( 'No components found.', 'hwsync' ),
			'not_found_in_trash' => __( 'No components found in Trash.', 'hwsync' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'component' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-hardware',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'show_in_rest'       => true,
		);

		register_post_type( self::POST_TYPE, $args );

		// Register Taxonomy: Category
		register_taxonomy(
			self::TAXONOMY_CAT,
			array( self::POST_TYPE ),
			array(
				'hierarchical'      => true,
				'labels'            => array( 'name' => __( 'Component Categories', 'hwsync' ), 'singular_name' => __( 'Category', 'hwsync' ) ),
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'component-category' ),
				'show_in_rest'      => true,
			)
		);

		// Register Taxonomy: Brand
		register_taxonomy(
			self::TAXONOMY_BRAND,
			array( self::POST_TYPE ),
			array(
				'hierarchical'      => false,
				'labels'            => array( 'name' => __( 'Hardware Brands', 'hwsync' ), 'singular_name' => __( 'Brand', 'hwsync' ) ),
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'hardware-brand' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Synchronize all canonical components into WordPress posts.
	 *
	 * @param array $component_ids Optional specific component IDs to process
	 * @return array Stats of processed, created, and updated posts
	 */
	public static function process_all( $component_ids = array() ) {
		$stats = array(
			'total'   => 0,
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
		);

		if ( ! empty( $component_ids ) ) {
			$components = array();
			foreach ( $component_ids as $cid ) {
				$c = Component::find_by_id( $cid );
				if ( $c ) {
					$components[] = $c;
				}
			}
		} else {
			$components = Component::get_all( array( 'limit' => 1000 ) );
		}

		$stats['total'] = count( $components );

		foreach ( $components as $component ) {
			$result = self::sync_component_to_post( $component );
			if ( $result === 'created' ) {
				$stats['created']++;
			} elseif ( $result === 'updated' ) {
				$stats['updated']++;
			} else {
				$stats['skipped']++;
			}
		}

		return $stats;
	}

	/**
	 * Sync a single canonical Component to wp_posts.
	 *
	 * @param Component $component
	 * @return string 'created'|'updated'|'skipped'
	 */
	public static function sync_component_to_post( Component $component ) {
		$prices = $component->get_prices();
		if ( empty( $prices ) ) {
			return 'skipped';
		}

		$lowest_price = null;
		$highest_price = 0;
		$in_stock_count = 0;

		foreach ( $prices as $p ) {
			if ( $p->is_in_stock ) {
				$in_stock_count++;
				if ( null === $lowest_price || $p->price < $lowest_price ) {
					$lowest_price = $p->price;
				}
			}
			if ( $p->price > $highest_price ) {
				$highest_price = $p->price;
			}
		}

		$post_title = $component->brand . ' ' . $component->model_name;
		$post_content = self::build_post_content( $component, $lowest_price, $in_stock_count, count( $prices ) );

		$post_data = array(
			'post_title'   => $post_title,
			'post_content' => $post_content,
			'post_status'  => 'publish',
			'post_type'    => self::POST_TYPE,
		);

		$action = 'updated';

		if ( ! empty( $component->wp_post_id ) && get_post( $component->wp_post_id ) ) {
			$post_data['ID'] = $component->wp_post_id;
			wp_update_post( $post_data );
			$post_id = $component->wp_post_id;
		} else {
			$post_id = wp_insert_post( $post_data );
			$component->wp_post_id = $post_id;
			$component->save();
			$action = 'created';
		}

		if ( $post_id && ! ( function_exists( 'is_wp_error' ) && \is_wp_error( $post_id ) ) ) {
			// Update Post Meta
			update_post_meta( $post_id, '_hwsync_component_id', $component->id );
			update_post_meta( $post_id, '_hwsync_lowest_price', $lowest_price );
			update_post_meta( $post_id, '_hwsync_highest_price', $highest_price );
			update_post_meta( $post_id, '_hwsync_vendor_count', count( $prices ) );
			update_post_meta( $post_id, '_hwsync_in_stock_count', $in_stock_count );
			update_post_meta( $post_id, '_hwsync_specs', $component->get_specs() );
			update_post_meta( $post_id, '_hwsync_mpn', $component->mpn );
			update_post_meta( $post_id, '_hwsync_last_synced_at', current_time( 'mysql' ) );

			// Assign Taxonomies
			if ( ! empty( $component->category ) ) {
				wp_set_object_terms( $post_id, ucfirst( $component->category ), self::TAXONOMY_CAT );
			}
			if ( ! empty( $component->brand ) ) {
				wp_set_object_terms( $post_id, $component->brand, self::TAXONOMY_BRAND );
			}
		}

		return $action;
	}

	protected static function build_post_content( Component $component, $lowest_price, $in_stock_count, $vendor_count ) {
		$specs = $component->get_specs();
		$price_formatted = $lowest_price ? '₹' . number_format( $lowest_price, 2 ) : 'Check Retailers';

		$content = '<div class="hwsync-component-overview">';
		$content .= '<p class="hwsync-summary">Compare live prices for <strong>' . esc_html( $component->brand . ' ' . $component->model_name ) . '</strong> across verified Indian computer hardware retailers. Best price starting at <strong>' . esc_html( $price_formatted ) . '</strong> across ' . intval( $vendor_count ) . ' stores.</p>';

		if ( ! empty( $specs ) && is_array( $specs ) ) {
			$content .= '<h3>Technical Specifications</h3>';
			$content .= '<table class="hwsync-specs-table" style="width:100%; border-collapse: collapse; margin-bottom: 24px; border: 1px solid #e2e8f0;"><tbody>';
			
			foreach ( $specs as $spec_k => $spec_v ) {
				if ( $spec_k === 'raw_specs_table' || is_array( $spec_v ) ) {
					continue;
				}
				$label = ucwords( str_replace( '_', ' ', $spec_k ) );
				$content .= '<tr style="border-bottom: 1px solid #e2e8f0;"><th style="text-align:left; padding: 8px 12px; background:#f8fafc; width:35%; font-weight:600; color:#334155;">' . esc_html( $label ) . '</th><td style="padding: 8px 12px; color:#0f172a;">' . esc_html( (string) $spec_v ) . '</td></tr>';
			}

			if ( ! empty( $specs['raw_specs_table'] ) && is_array( $specs['raw_specs_table'] ) ) {
				foreach ( $specs['raw_specs_table'] as $rk => $rv ) {
					if ( is_scalar( $rv ) && ! empty( $rv ) ) {
						$content .= '<tr style="border-bottom: 1px solid #e2e8f0;"><th style="text-align:left; padding: 8px 12px; background:#f8fafc; width:35%; font-weight:600; color:#334155;">' . esc_html( $rk ) . '</th><td style="padding: 8px 12px; color:#0f172a;">' . esc_html( (string) $rv ) . '</td></tr>';
					}
				}
			}
			$content .= '</tbody></table>';
		}

		$content .= '</div>';
		$content .= '<!-- Shortcode for live interactive multi-vendor price comparison table -->';
		$content .= '[hwsync_price_table id="' . intval( $component->id ) . '"]';

		return $content;
	}
}
