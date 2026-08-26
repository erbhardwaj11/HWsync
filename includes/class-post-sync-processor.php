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
	 * Get the target post type for theme syncing (defaults to pcspecs_component if exists, else hwsync_component or post)
	 */
	public static function get_target_post_type() {
		$saved = function_exists( 'get_option' ) ? get_option( 'hwsync_target_post_type' ) : '';
		if ( ! empty( $saved ) ) {
			return $saved;
		}

		if ( function_exists( 'post_type_exists' ) && \post_type_exists( 'pcspecs_component' ) ) {
			return 'pcspecs_component';
		}

		return self::POST_TYPE;
	}

	/**
	 * Synchronize a batch chunk of canonical components into the theme catalog posts.
	 *
	 * @param array $options Filter & pagination options
	 * @return array Result summary with processed counts and item details
	 */
	public static function sync_theme_chunk( $options = array() ) {
		$category         = isset( $options['category'] ) ? $options['category'] : 'all';
		$offset           = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;
		$limit            = isset( $options['limit'] ) ? intval( $options['limit'] ) : 10;
		$target_post_type = isset( $options['post_type'] ) && ! empty( $options['post_type'] ) ? $options['post_type'] : self::get_target_post_type();

		$args = array(
			'limit'  => $limit,
			'offset' => $offset,
		);
		if ( $category !== 'all' ) {
			$args['category'] = $category;
		}

		$total_count = Component::count( $category !== 'all' ? array( 'category' => $category ) : array() );
		$components  = Component::get_all( $args );

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$logs    = array();

		foreach ( $components as $component ) {
			$res = self::sync_component_to_post( $component, $target_post_type );
			$action = $res['action'];
			$post_id = $res['post_id'];
			$prices_count = $res['vendor_count'];
			$lowest = $res['lowest_price'];

			if ( $action === 'created' ) {
				$created++;
				$logs[] = sprintf(
					__( '[NEW POST #%d] Created "%s" with %d vendor prices (Lowest: %s)', 'hwsync' ),
					$post_id,
					$component->brand . ' ' . $component->model_name,
					$prices_count,
					$lowest > 0 ? '₹' . number_format( $lowest, 2 ) : 'NA'
				);
			} elseif ( $action === 'updated' ) {
				$updated++;
				$logs[] = sprintf(
					__( '[UPDATED POST #%d] "%s" mapped to %d vendor prices (Lowest: %s)', 'hwsync' ),
					$post_id,
					$component->brand . ' ' . $component->model_name,
					$prices_count,
					$lowest > 0 ? '₹' . number_format( $lowest, 2 ) : 'NA'
				);
			} else {
				$skipped++;
				$logs[] = sprintf(
					__( '[SKIPPED] "%s" (No active vendor pricing found)', 'hwsync' ),
					$component->brand . ' ' . $component->model_name
				);
			}
		}

		$next_offset = $offset + count( $components );
		$is_done = ( $next_offset >= $total_count || empty( $components ) );

		return array(
			'success'     => true,
			'total'       => $total_count,
			'processed'   => count( $components ),
			'offset'      => $next_offset,
			'created'     => $created,
			'updated'     => $updated,
			'skipped'     => $skipped,
			'is_done'     => $is_done,
			'logs'        => $logs,
		);
	}

	/**
	 * Synchronize all canonical components into WordPress posts.
	 *
	 * @param array $component_ids Optional specific component IDs to process
	 * @return array Stats of processed, created, and updated posts
	 */
	public static function process_all( $component_ids = array(), $target_post_type = '' ) {
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
			$components = Component::get_all( array( 'limit' => 2000 ) );
		}

		$stats['total'] = count( $components );

		foreach ( $components as $component ) {
			$res = self::sync_component_to_post( $component, $target_post_type );
			if ( $res['action'] === 'created' ) {
				$stats['created']++;
			} elseif ( $res['action'] === 'updated' ) {
				$stats['updated']++;
			} else {
				$stats['skipped']++;
			}
		}

		return $stats;
	}

	/**
	 * Sync a single canonical Component to a WordPress post without duplication.
	 * Aggregates all vendor prices under this single post.
	 *
	 * @param Component $component
	 * @param string $target_post_type
	 * @return array ['action' => 'created'|'updated'|'skipped', 'post_id' => int, 'vendor_count' => int, 'lowest_price' => float]
	 */
	public static function sync_component_to_post( Component $component, $target_post_type = '' ) {
		$post_type = ! empty( $target_post_type ) ? $target_post_type : self::get_target_post_type();
		$prices    = $component->get_prices();

		if ( empty( $prices ) ) {
			return array(
				'action'       => 'skipped',
				'post_id'      => 0,
				'vendor_count' => 0,
				'lowest_price' => 0,
			);
		}

		$lowest_price   = null;
		$highest_price  = 0;
		$in_stock_count = 0;
		$vendor_prices_data = array();

		foreach ( $prices as $p ) {
			$is_stock = (bool) $p->is_in_stock;
			if ( $is_stock ) {
				$in_stock_count++;
				if ( $p->price > 0 && ( null === $lowest_price || $p->price < $lowest_price ) ) {
					$lowest_price = floatval( $p->price );
				}
			}
			if ( $p->price > $highest_price ) {
				$highest_price = floatval( $p->price );
			}

			$v_slug = ! empty( $p->vendor_slug ) ? $p->vendor_slug : '';
			$v_name = ! empty( $p->vendor_name ) ? $p->vendor_name : '';
			if ( empty( $v_slug ) && ! empty( $p->vendor_id ) ) {
				$v_obj = \HWsync\Models\Vendor::find_by_id( $p->vendor_id );
				if ( $v_obj ) {
					$v_slug = $v_obj->vendor_slug;
					$v_name = $v_obj->vendor_name;
				}
			}
			if ( empty( $v_name ) ) {
				$v_name = ! empty( $v_slug ) ? ucfirst( $v_slug ) : 'Retailer';
			}

			$vendor_prices_data[] = array(
				'vendor_id'            => $p->vendor_id,
				'vendor_slug'          => $v_slug,
				'vendor_name'          => $v_name,
				'vendor_product_title' => $p->vendor_product_title,
				'price'                => floatval( $p->price ),
				'original_price'       => ! empty( $p->original_price ) ? floatval( $p->original_price ) : null,
				'is_in_stock'          => $is_stock,
				'stock_status'         => $p->stock_status ?: ( $is_stock ? 'in_stock' : 'out_of_stock' ),
				'product_url'          => $p->product_url,
				'affiliate_url'        => ! empty( $p->affiliate_url ) ? $p->affiliate_url : $p->product_url,
				'last_checked_at'      => $p->last_checked_at,
			);
		}

		if ( null === $lowest_price ) {
			$lowest_price = $highest_price > 0 ? $highest_price : 0.0;
		}

		$post_title   = trim( $component->brand . ' ' . $component->model_name );
		$post_content = self::build_post_content( $component, $prices, $lowest_price, $in_stock_count );

		// 1. Locate existing post to avoid any duplication
		$post_id = self::find_existing_post( $component, $post_title, $post_type );

		$post_data = array(
			'post_title'   => $post_title,
			'post_content' => $post_content,
			'post_status'  => 'publish',
			'post_type'    => $post_type,
		);

		$action = 'updated';

		if ( $post_id ) {
			$post_data['ID'] = $post_id;
			wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data );
			$action  = 'created';
		}

		if ( $post_id && ! ( function_exists( 'is_wp_error' ) && \is_wp_error( $post_id ) ) ) {
			// Update Component record with linked post ID
			$component->wp_post_id = $post_id;
			$component->save();

			// Store comprehensive custom fields and multi-vendor prices
			update_post_meta( $post_id, '_hwsync_component_id', $component->id );
			update_post_meta( $post_id, '_pcspecs_component_id', $component->id );
			update_post_meta( $post_id, '_hwsync_lowest_price', $lowest_price );
			update_post_meta( $post_id, '_lowest_price', $lowest_price );
			update_post_meta( $post_id, '_price', $lowest_price );
			update_post_meta( $post_id, 'price', $lowest_price );
			update_post_meta( $post_id, '_hwsync_highest_price', $highest_price );
			update_post_meta( $post_id, '_hwsync_vendor_count', count( $prices ) );
			update_post_meta( $post_id, '_vendor_count', count( $prices ) );
			update_post_meta( $post_id, '_hwsync_in_stock_count', $in_stock_count );
			update_post_meta( $post_id, '_in_stock_count', $in_stock_count );
			update_post_meta( $post_id, '_hwsync_specs', $component->get_specs() );
			update_post_meta( $post_id, '_pcspecs_specs', $component->get_specs() );
			update_post_meta( $post_id, '_hwsync_vendor_prices', $vendor_prices_data );
			update_post_meta( $post_id, '_pcspecs_vendor_prices', $vendor_prices_data );
			update_post_meta( $post_id, '_hwsync_mpn', $component->mpn );
			update_post_meta( $post_id, '_hwsync_sku', $component->sku );
			update_post_meta( $post_id, '_hwsync_last_synced_at', current_time( 'mysql' ) );

			// Assign Taxonomies
			if ( ! empty( $component->category ) && function_exists( 'wp_set_object_terms' ) ) {
				$cat_name = ucfirst( $component->category );
				if ( function_exists( 'taxonomy_exists' ) && \taxonomy_exists( self::TAXONOMY_CAT ) ) {
					\wp_set_object_terms( $post_id, $cat_name, self::TAXONOMY_CAT );
				}
				if ( function_exists( 'taxonomy_exists' ) && \taxonomy_exists( 'category' ) && $post_type === 'post' ) {
					\wp_set_object_terms( $post_id, $cat_name, 'category' );
				}
				if ( function_exists( 'taxonomy_exists' ) && \taxonomy_exists( 'pcspecs_category' ) ) {
					\wp_set_object_terms( $post_id, $cat_name, 'pcspecs_category' );
				}
			}

			if ( ! empty( $component->brand ) && function_exists( 'wp_set_object_terms' ) ) {
				if ( function_exists( 'taxonomy_exists' ) && \taxonomy_exists( self::TAXONOMY_BRAND ) ) {
					\wp_set_object_terms( $post_id, $component->brand, self::TAXONOMY_BRAND );
				}
				if ( function_exists( 'taxonomy_exists' ) && \taxonomy_exists( 'post_tag' ) && $post_type === 'post' ) {
					\wp_set_object_terms( $post_id, $component->brand, 'post_tag' );
				}
				if ( function_exists( 'taxonomy_exists' ) && \taxonomy_exists( 'pcspecs_brand' ) ) {
					\wp_set_object_terms( $post_id, $component->brand, 'pcspecs_brand' );
				}
			}
		}

		return array(
			'action'       => $action,
			'post_id'      => $post_id,
			'vendor_count' => count( $prices ),
			'lowest_price' => $lowest_price,
		);
	}

	/**
	 * Find an existing post for this component across meta, title, or slug to prevent duplicate posts.
	 *
	 * @param Component $component
	 * @param string $post_title
	 * @param string $post_type
	 * @return int|null Existing Post ID
	 */
	protected static function find_existing_post( Component $component, $post_title, $post_type ) {
		global $wpdb;

		// 1. Direct Component wp_post_id match
		if ( ! empty( $component->wp_post_id ) ) {
			$p = get_post( $component->wp_post_id );
			if ( $p && $p->post_status !== 'trash' ) {
				return intval( $component->wp_post_id );
			}
		}

		// 2. Query by _hwsync_component_id meta key
		$meta_match = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} pm 
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
			 WHERE pm.meta_key = '_hwsync_component_id' AND pm.meta_value = %d AND p.post_status != 'trash' LIMIT 1",
			$component->id
		) );
		if ( $meta_match ) {
			return intval( $meta_match );
		}

		// 3. Query by exact Post Title in target post type
		$title_match = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s AND post_status != 'trash' LIMIT 1",
			$post_title,
			$post_type
		) );
		if ( $title_match ) {
			return intval( $title_match );
		}

		// 4. Query by slug
		$slug = function_exists( 'sanitize_title' ) 
			? \sanitize_title( $post_title ) 
			: strtolower( trim( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $post_title ), '-' ) );

		$slug_match = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_status != 'trash' LIMIT 1",
			$slug,
			$post_type
		) );
		if ( $slug_match ) {
			return intval( $slug_match );
		}

		return null;
	}

	/**
	 * Build comprehensive HTML post content featuring specs and multi-vendor comparison table.
	 */
	protected static function build_post_content( Component $component, $prices, $lowest_price, $in_stock_count ) {
		$specs = $component->get_specs();
		$price_formatted = $lowest_price > 0 ? '₹' . number_format( $lowest_price, 2 ) : 'Check Retailers';
		$vendor_count = count( $prices );

		$content = '<div class="hwsync-component-overview">';
		$content .= '<p class="hwsync-summary">Compare live prices for <strong>' . esc_html( $component->brand . ' ' . $component->model_name ) . '</strong> across verified Indian computer hardware retailers. Best price starting at <strong>' . esc_html( $price_formatted ) . '</strong> across ' . intval( $vendor_count ) . ' stores (' . intval( $in_stock_count ) . ' in stock).</p>';

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
