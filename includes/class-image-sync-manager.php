<?php
namespace HWsync;

use HWsync\Models\Component;
use HWsync\Models\Vendor_Price;
use HWsync\Models\Vendor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Sync_Manager {

	/**
	 * Audit image status across canonical components in database.
	 *
	 * @param string $category Hardware category slug or 'all'.
	 * @return array Array with total, already_synced, needing_sync counts.
	 */
	public static function audit_image_status( $category = 'all' ) {
		global $wpdb;
		$comp_table = Database::get_table_name( 'components' );
		
		$where_clauses = array( "1=1" );
		if ( $category !== 'all' && ! empty( $category ) ) {
			$where_clauses[] = $wpdb->prepare( "category = %s", $category );
		}
		$where_sql = implode( ' AND ', $where_clauses );

		$total = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$comp_table} WHERE {$where_sql}" ) );
		$already_synced = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$comp_table} WHERE {$where_sql} AND image_url IS NOT NULL AND image_url != ''" ) );
		$needing_sync = max( 0, $total - $already_synced );

		return array(
			'total'          => $total,
			'already_synced' => $already_synced,
			'needing_sync'   => $needing_sync,
		);
	}

	/**
	 * Run product image synchronization for existing canonical components in DB.
	 * First performs an internal check: components with images already synced are skipped,
	 * and only the remaining components without images are synced.
	 *
	 * @param array $options Sync options ('category', 'component_id', 'limit', 'offset', 'force').
	 * @param callable|null $logger Progress callback logger.
	 * @return array Report metrics.
	 */
	public function run_images_sync( $options = array(), $logger = null ) {
		global $wpdb;
		$comp_table   = Database::get_table_name( 'components' );
		$prices_table = Database::get_table_name( 'vendor_prices' );

		$category     = isset( $options['category'] ) ? sanitize_text_field( $options['category'] ) : 'all';
		$component_id = isset( $options['component_id'] ) ? intval( $options['component_id'] ) : 0;
		$limit        = isset( $options['limit'] ) ? intval( $options['limit'] ) : 2;
		$offset       = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;
		$force        = ! empty( $options['force'] );

		// 1. Upfront Internal Check / Audit on initial step
		if ( $offset === 0 ) {
			$audit = self::audit_image_status( $category );
			$this->emit( $logger, 'info', "Image Pre-Check: Found {$audit['total']} total components ({$audit['already_synced']} already synced with photos, {$audit['needing_sync']} left to sync)." );
		}

		$where_clauses = array( "1=1" );
		if ( $component_id > 0 ) {
			$where_clauses[] = $wpdb->prepare( "id = %d", $component_id );
		} elseif ( $category !== 'all' && ! empty( $category ) ) {
			$where_clauses[] = $wpdb->prepare( "category = %s", $category );
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$components_raw = $wpdb->get_results( "SELECT * FROM {$comp_table} WHERE {$where_sql} ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}", \ARRAY_A );

		if ( empty( $components_raw ) ) {
			return array(
				'total_components' => 0,
				'images_saved'     => 0,
				'skipped'          => 0,
				'errors'           => 0,
			);
		}

		$report = array(
			'total_components' => count( $components_raw ),
			'images_saved'     => 0,
			'skipped'          => 0,
			'errors'           => 0,
		);

		foreach ( $components_raw as $c_row ) {
			$component = new Component( $c_row );
			$comp_name = trim( $component->brand . ' ' . $component->model_name );

			// 2. INTERNAL CHECK: If component already has an image, do NOT sync again
			if ( ! $force && ! empty( $component->image_url ) ) {
				$this->emit( $logger, 'debug', "Component #{$component->id} [{$comp_name}] already has image. Skipping." );
				$report['skipped']++;
				continue;
			}

			// Gather linked vendor prices
			$prices = $component->get_prices();
			if ( empty( $prices ) ) {
				$this->emit( $logger, 'debug', "Component #{$component->id} [{$comp_name}] has no linked vendor listings. Skipping." );
				$report['skipped']++;
				continue;
			}

			$image_downloaded = false;

			// 3. FAST PATH: Check if any linked price listing already has image_url in raw_data
			foreach ( $prices as $p ) {
				$raw_data = is_array( $p->raw_data_json ) ? $p->raw_data_json : ( json_decode( (string) $p->raw_data_json, true ) ?: array() );
				$candidate_url = '';

				if ( ! empty( $raw_data['image_url'] ) ) {
					$candidate_url = $raw_data['image_url'];
				} elseif ( ! empty( $raw_data['img_url'] ) ) {
					$candidate_url = $raw_data['img_url'];
				} elseif ( ! empty( $raw_data['image'] ) ) {
					$candidate_url = $raw_data['image'];
				}

				if ( ! empty( $candidate_url ) && filter_var( $candidate_url, FILTER_VALIDATE_URL ) ) {
					$vendor_slug = ! empty( $p->vendor_slug ) ? $p->vendor_slug : 'store';
					$this->emit( $logger, 'debug', "Found catalog image from {$vendor_slug} for #{$component->id} [{$comp_name}]..." );

					$save_res = $this->download_and_attach_image( $component, $candidate_url );
					if ( $save_res && ! empty( $save_res['url'] ) ) {
						$component->image_url = $save_res['url'];
						$component->save();

						$image_downloaded = true;
						$report['images_saved']++;

						$this->emit( $logger, 'success', "Attached 1 photo for [{$comp_name}] -> {$save_res['file_name']}" );
						break; // STRICTLY 1 PHOTO PER PRODUCT
					}
				}
			}

			// 4. FALLBACK: Visit only the PRIMARY store product page
			if ( ! $image_downloaded ) {
				foreach ( $prices as $p ) {
					if ( empty( $p->product_url ) ) {
						continue;
					}

					$vendor_slug = ! empty( $p->vendor_slug ) ? $p->vendor_slug : '';
					$this->emit( $logger, 'debug', "Checking {$vendor_slug} product page for #{$component->id} [{$comp_name}]..." );

					$remote_img_url = $this->fetch_image_from_product_url( $p->product_url, $vendor_slug );
					if ( empty( $remote_img_url ) ) {
						continue;
					}

					$save_res = $this->download_and_attach_image( $component, $remote_img_url );
					if ( $save_res && ! empty( $save_res['url'] ) ) {
						$component->image_url = $save_res['url'];
						$component->save();

						$image_downloaded = true;
						$report['images_saved']++;

						$this->emit( $logger, 'success', "Attached 1 photo for [{$comp_name}] -> {$save_res['file_name']}" );
						break; // STRICTLY 1 PHOTO PER PRODUCT
					}
				}
			}

			if ( ! $image_downloaded ) {
				$this->emit( $logger, 'warning', "Could not extract valid product photo for #{$component->id} [{$comp_name}]." );
				$report['errors']++;
			}
		}

		return $report;
	}

	/**
	 * Fetch HTML from product URL and extract main high-resolution product image.
	 *
	 * @param string $url Vendor product URL.
	 * @param string $vendor_slug Vendor identifier.
	 * @return string|null Absolute image URL or null.
	 */
	public function fetch_image_from_product_url( $url, $vendor_slug = '' ) {
		$url = trim( $url );
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		$default_ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

		$response = wp_remote_get( $url, array(
			'timeout'    => 10,
			'user-agent' => $default_ua,
			'sslverify'  => false,
			'headers'    => array(
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
				'Accept-Language' => 'en-US,en;q=0.9',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 400 ) {
			return null;
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return null;
		}

		return self::extract_product_image_from_html( $html, $url );
	}

	/**
	 * Extract product photo URL from HTML markup.
	 *
	 * @param string $html HTML source of product page.
	 * @param string $base_url Page base URL for resolving relative links.
	 * @return string|null Clean absolute image URL or null.
	 */
	public static function extract_product_image_from_html( $html, $base_url = '' ) {
		if ( empty( $html ) ) {
			return null;
		}

		$candidates = array();

		// 1. Check OpenGraph image
		if ( preg_match( '/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $html, $m ) ||
		     preg_match( '/<meta\s+content=["\']([^"\']+)["\']\s+property=["\']og:image["\']/i', $html, $m ) ) {
			$candidates[] = trim( $m[1] );
		}

		// 2. Check Twitter card image
		if ( preg_match( '/<meta\s+name=["\']twitter:image["\']\s+content=["\']([^"\']+)["\']/i', $html, $m ) ||
		     preg_match( '/<meta\s+content=["\']([^"\']+)["\']\s+name=["\']twitter:image["\']/i', $html, $m ) ) {
			$candidates[] = trim( $m[1] );
		}

		// 3. Check JSON-LD Schema.org product data
		if ( preg_match_all( '/<script\s+type=["\']application\/ld\+json["\']>(.*?)<\/script>/is', $html, $scripts ) ) {
			foreach ( $scripts[1] as $raw_json ) {
				$json = json_decode( trim( $raw_json ), true );
				if ( ! is_array( $json ) ) {
					continue;
				}
				if ( isset( $json['@graph'] ) && is_array( $json['@graph'] ) ) {
					$items = $json['@graph'];
				} else {
					$items = array( $json );
				}

				foreach ( $items as $item ) {
					if ( isset( $item['image'] ) ) {
						if ( is_string( $item['image'] ) ) {
							$candidates[] = $item['image'];
						} elseif ( is_array( $item['image'] ) ) {
							if ( isset( $item['image']['url'] ) ) {
								$candidates[] = $item['image']['url'];
							} elseif ( isset( $item['image'][0] ) && is_string( $item['image'][0] ) ) {
								$candidates[] = $item['image'][0];
							}
						}
					}
				}
			}
		}

		// 4. Check WooCommerce gallery large image
		if ( preg_match( '/data-large_image=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$candidates[] = trim( $m[1] );
		}
		if ( preg_match( '/<div[^>]*class=["\'][^"\']*woocommerce-product-gallery__image[^"\']*["\'][^>]*>\s*<a[^>]*href=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$candidates[] = trim( $m[1] );
		}

		// 5. Check OpenCart / Custom zoom images
		if ( preg_match( '/<a[^>]*class=["\'][^"\']*thumbnail[^"\']*["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$candidates[] = trim( $m[1] );
		}
		if ( preg_match( '/<img[^>]*id=["\']zoom_01["\'][^>]*src=["\']([^"\']+)["\']/i', $html, $m ) ||
		     preg_match( '/<img[^>]*class=["\'][^"\']*product-image[^"\']*["\'][^>]*src=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$candidates[] = trim( $m[1] );
		}

		// 6. Check Shopify CDN product images
		if ( preg_match_all( '/(https?:\/\/[^"\'\s>]+\/cdn\/shop\/files\/[^"\'\s>]+|https?:\/\/[^"\'\s>]+\/cdn\/shop\/products\/[^"\'\s>]+)/i', $html, $shopify_matches ) ) {
			foreach ( $shopify_matches[0] as $s_url ) {
				$candidates[] = $s_url;
			}
		}

		// Filter and pick first high-fidelity product image
		foreach ( $candidates as $candidate ) {
			$candidate = html_entity_decode( $candidate, ENT_QUOTES, 'UTF-8' );
			$candidate = strtok( $candidate, '?' );

			// Resolve relative URL
			if ( strpos( $candidate, '//' ) === 0 ) {
				$candidate = 'https:' . $candidate;
			} elseif ( strpos( $candidate, 'http' ) !== 0 && ! empty( $base_url ) ) {
				$parsed = parse_url( $base_url );
				$root = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );
				$candidate = $root . '/' . ltrim( $candidate, '/' );
			}

			// Blacklist logos, icons, placeholders, tracking pixels
			$lower = strtolower( $candidate );
			if ( preg_match( '/(logo|icon|favicon|badge|banner|payment|avatar|1x1|spacer|placeholder|no-image)/i', $lower ) ) {
				continue;
			}

			// Check valid image extension or CDN image path
			if ( preg_match( '/\.(jpg|jpeg|png|webp)($|\?)/i', $candidate ) || strpos( $lower, 'cdn.shopify.com' ) !== false || strpos( $lower, '/cache/' ) !== false || strpos( $lower, '/image/' ) !== false ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Download remote image, rename to component name, save to uploads, and attach to component / post.
	 *
	 * @param Component $component Hardware component.
	 * @param string $remote_url Remote image URL.
	 * @return array|null Result with local URL and filename.
	 */
	public function download_and_attach_image( Component $component, $remote_url ) {
		$remote_url = trim( $remote_url );
		if ( empty( $remote_url ) ) {
			return null;
		}

		try {
			$response = wp_remote_get( $remote_url, array(
				'timeout'    => 12,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 HWsync/0.0.1.7',
				'sslverify'  => false,
			) );

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$image_data = wp_remote_retrieve_body( $response );
			if ( empty( $image_data ) || strlen( $image_data ) < 500 ) {
				return null;
			}

			// Determine file extension
			$content_type = wp_remote_retrieve_header( $response, 'content-type' );
			$ext = 'jpg';
			if ( stripos( $content_type, 'png' ) !== false || preg_match( '/\.png($|\?)/i', $remote_url ) ) {
				$ext = 'png';
			} elseif ( stripos( $content_type, 'webp' ) !== false || preg_match( '/\.webp($|\?)/i', $remote_url ) ) {
				$ext = 'webp';
			}

			// Generate clean, SEO-friendly file name based on canonical component
			$comp_title = trim( $component->brand . '-' . $component->model_name );
			$file_name = sanitize_file_name( sanitize_title( $comp_title ) . '.' . $ext );
			if ( empty( $file_name ) || $file_name === '.' . $ext ) {
				$file_name = 'component-' . $component->id . '.' . $ext;
			}

			// Upload to WordPress uploads directory
			$upload = wp_upload_bits( $file_name, null, $image_data );
			if ( ! empty( $upload['error'] ) ) {
				return null;
			}

			$local_file_path = $upload['file'];
			$local_url       = $upload['url'];

			// Register in Media Library safely
			$attachment_id = 0;
			if ( function_exists( 'wp_insert_attachment' ) ) {
				if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/media.php';
				}

				$wp_filetype = wp_check_filetype( $file_name, null );
				$attachment = array(
					'post_mime_type' => $wp_filetype['type'] ?: 'image/jpeg',
					'post_title'     => trim( $component->brand . ' ' . $component->model_name ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				$attachment_id = wp_insert_attachment( $attachment, $local_file_path );
				if ( ! is_wp_error( $attachment_id ) && $attachment_id > 0 ) {
					if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
						try {
							$attach_data = @wp_generate_attachment_metadata( $attachment_id, $local_file_path );
							if ( is_array( $attach_data ) ) {
								wp_update_attachment_metadata( $attachment_id, $attach_data );
							}
						} catch ( \Throwable $t ) {
							// Ignore metadata generation failure, base image is saved
						}
					}

					// If component is linked to WordPress post, set featured thumbnail
					if ( ! empty( $component->wp_post_id ) && function_exists( 'set_post_thumbnail' ) ) {
						set_post_thumbnail( $component->wp_post_id, $attachment_id );
					}
				}
			}

			return array(
				'url'           => $local_url,
				'file_path'     => $local_file_path,
				'file_name'     => $file_name,
				'attachment_id' => $attachment_id,
			);
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Run a chunked step for Image synchronization via AJAX.
	 *
	 * @param array $options Chunk options ('category', 'offset', 'limit', 'force').
	 * @return array Chunk result.
	 */
	public function sync_images_chunk( $options = array() ) {
		$category = isset( $options['category'] ) ? sanitize_text_field( $options['category'] ) : 'all';
		$offset   = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;
		$limit    = isset( $options['limit'] ) ? intval( $options['limit'] ) : 2;
		$force    = ! empty( $options['force'] );

		$logs = array();
		$logger = function( $level, $message ) use ( &$logs ) {
			$logs[] = array( 'level' => $level, 'message' => $message );
		};

		try {
			$report = $this->run_images_sync( array(
				'category' => $category,
				'offset'   => $offset,
				'limit'    => $limit,
				'force'    => $force,
			), $logger );
		} catch ( \Throwable $e ) {
			$logs[] = array( 'level' => 'error', 'message' => "Exception during sync: " . $e->getMessage() );
			$report = array( 'total_components' => 0, 'images_saved' => 0, 'skipped' => 0, 'errors' => 1 );
		}

		global $wpdb;
		$comp_table = Database::get_table_name( 'components' );
		$where = ( $category !== 'all' && ! empty( $category ) ) ? $wpdb->prepare( "WHERE category = %s", $category ) : "WHERE 1=1";
		$total_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$comp_table} {$where}" ) );
		$has_more = ( $offset + $limit ) < $total_count;

		return array(
			'processed'    => $report['total_components'],
			'images_saved' => $report['images_saved'],
			'skipped'      => $report['skipped'],
			'has_more'     => $has_more,
			'logs'         => $logs,
		);
	}

	/**
	 * Helper for emitting logs to callable logger.
	 */
	private function emit( $logger, $level, $message, $stats = array() ) {
		if ( is_callable( $logger ) ) {
			call_user_func( $logger, $level, $message, $stats );
		}
	}
}
