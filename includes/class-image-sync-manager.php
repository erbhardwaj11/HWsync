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
	 * Run product image synchronization for existing canonical components in DB.
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
		$limit        = isset( $options['limit'] ) ? intval( $options['limit'] ) : 50;
		$offset       = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;
		$force        = ! empty( $options['force'] );

		$this->emit( $logger, 'info', "Starting Product Image Synchronization Engine..." );

		$where_clauses = array( "1=1" );
		if ( $component_id > 0 ) {
			$where_clauses[] = $wpdb->prepare( "id = %d", $component_id );
		} elseif ( $category !== 'all' && ! empty( $category ) ) {
			$where_clauses[] = $wpdb->prepare( "category = %s", $category );
		}

		if ( ! $force ) {
			$where_clauses[] = "(image_url IS NULL OR image_url = '')";
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$components_raw = $wpdb->get_results( "SELECT * FROM {$comp_table} WHERE {$where_sql} ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}", \ARRAY_A );

		if ( empty( $components_raw ) ) {
			$this->emit( $logger, 'warning', "No components found needing image sync." );
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

		$this->emit( $logger, 'info', "Found " . count( $components_raw ) . " components. Scanning vendor listings for product photos..." );

		foreach ( $components_raw as $c_row ) {
			$component = new Component( $c_row );
			$comp_name = trim( $component->brand . ' ' . $component->model_name );

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

			// Scan linked vendor product pages until 1 clean image is found
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

				$this->emit( $logger, 'match', "Found product image at {$vendor_slug}: {$remote_img_url}" );

				// Download, rename, and store image
				$save_res = $this->download_and_attach_image( $component, $remote_img_url );
				if ( $save_res && ! empty( $save_res['url'] ) ) {
					$component->image_url = $save_res['url'];
					$component->save();

					$image_downloaded = true;
					$report['images_saved']++;

					$this->emit( $logger, 'success', "Successfully attached photo for [{$comp_name}] -> {$save_res['file_name']}" );
					break; // Exactly one photo per product
				}
			}

			if ( ! $image_downloaded ) {
				$this->emit( $logger, 'warning', "Could not extract valid product photo for #{$component->id} [{$comp_name}]." );
				$report['errors']++;
			}
		}

		$this->emit( $logger, 'finish', "Product Image Sync finished! Downloaded {$report['images_saved']} images ({$report['skipped']} skipped, {$report['errors']} missing)." );

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

		$response = wp_remote_get( $url, array(
			'timeout'    => 15,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 HWsync/0.0.1.3',
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

		$response = wp_remote_get( $remote_url, array(
			'timeout'    => 20,
			'user-agent' => 'Mozilla/5.0 HWsync/0.0.1.3 Image Downloader',
			'sslverify'  => false,
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$image_data = wp_remote_retrieve_body( $response );
		if ( empty( $image_data ) || strlen( $image_data ) < 1000 ) {
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

		// Register in Media Library if WordPress core media functions are available
		$attachment_id = 0;
		if ( function_exists( 'wp_insert_attachment' ) && function_exists( 'wp_generate_attachment_metadata' ) ) {
			if ( ! function_exists( 'wp_read_image_metadata' ) ) {
				if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/media.php';
				}
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
				$attach_data = wp_generate_attachment_metadata( $attachment_id, $local_file_path );
				wp_update_attachment_metadata( $attachment_id, $attach_data );

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
		$limit    = isset( $options['limit'] ) ? intval( $options['limit'] ) : 4;
		$force    = ! empty( $options['force'] );

		$logs = array();
		$logger = function( $level, $message ) use ( &$logs ) {
			$logs[] = array( 'level' => $level, 'message' => $message );
		};

		$report = $this->run_images_sync( array(
			'category' => $category,
			'offset'   => $offset,
			'limit'    => $limit,
			'force'    => $force,
		), $logger );

		global $wpdb;
		$comp_table = Database::get_table_name( 'components' );
		$where = ( $category !== 'all' && ! empty( $category ) ) ? $wpdb->prepare( "WHERE category = %s", $category ) : "WHERE 1=1";
		if ( ! $force ) {
			$where .= " AND (image_url IS NULL OR image_url = '')";
		}
		$remaining = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$comp_table} {$where}" ) );
		$has_more = ( $offset + $limit ) < $remaining;

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
