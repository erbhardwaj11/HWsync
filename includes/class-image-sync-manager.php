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
	 * Check if an image URL is hosted locally on this WordPress installation.
	 *
	 * @param string $url Image URL to check.
	 * @return bool True if local, false if external link to web.
	 */
	public static function is_local_image_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}

		if ( strpos( $url, '/wp-content/uploads/' ) !== false ) {
			return true;
		}

		if ( function_exists( 'wp_upload_dir' ) ) {
			$upload_dir = wp_upload_dir();
			$baseurl = $upload_dir['baseurl'] ?? '';
			if ( ! empty( $baseurl ) && strpos( $url, $baseurl ) === 0 ) {
				return true;
			}
		}

		if ( function_exists( 'site_url' ) ) {
			$siteurl = site_url();
			if ( ! empty( $siteurl ) && strpos( $url, $siteurl ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Audit image status across canonical components in database.
	 * Components with local images are counted as already synced.
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
		$all_comps = $wpdb->get_results( "SELECT id, image_url FROM {$comp_table} WHERE {$where_sql}", \ARRAY_A );

		$already_synced = 0;
		if ( ! empty( $all_comps ) ) {
			foreach ( $all_comps as $c ) {
				if ( ! empty( $c['image_url'] ) && self::is_local_image_url( $c['image_url'] ) ) {
					$already_synced++;
				}
			}
		}
		$needing_sync = max( 0, $total - $already_synced );

		return array(
			'total'          => $total,
			'already_synced' => $already_synced,
			'needing_sync'   => $needing_sync,
		);
	}

	/**
	 * Check local sources first to associate an existing saved image to the component
	 * without performing any remote network requests.
	 *
	 * Checks:
	 * 1. Current component image_url if already local.
	 * 2. Associated WordPress post featured image thumbnail.
	 * 3. Local disk files in wp-content/uploads/hwsync/ or wp-content/uploads/.
	 * 4. WordPress Media Library attachments matching component name / slug / MPN.
	 * 5. Other canonical components in DB matching same model / MPN with a local image.
	 *
	 * @param Component $component Hardware component.
	 * @param callable|null $logger Progress logger.
	 * @return bool True if local image was found and associated, false otherwise.
	 */
	public function try_associate_existing_local_image( Component $component, $logger = null ) {
		global $wpdb;

		$comp_name = trim( (string) $component->brand . ' ' . (string) $component->model_name );

		// 1. Current component image_url is already local
		if ( ! empty( $component->image_url ) && self::is_local_image_url( $component->image_url ) ) {
			if ( ! empty( $component->wp_post_id ) && function_exists( 'update_post_meta' ) ) {
				update_post_meta( $component->wp_post_id, '_pcspecs_image_url', $component->image_url );
				update_post_meta( $component->wp_post_id, '_hwsync_image_url', $component->image_url );
			}
			$this->emit( $logger, 'debug', "[LOCAL MATCH] Component #{$component->id} [{$comp_name}] already has local image associated." );
			return true;
		}

		// 2. Check if associated WordPress post already has a featured image attachment
		if ( ! empty( $component->wp_post_id ) && function_exists( 'get_post_thumbnail_id' ) && function_exists( 'wp_get_attachment_url' ) ) {
			$thumb_id = get_post_thumbnail_id( $component->wp_post_id );
			if ( $thumb_id ) {
				$thumb_url = wp_get_attachment_url( $thumb_id );
				if ( ! empty( $thumb_url ) ) {
					$component->image_url = $thumb_url;
					$component->save();
					if ( function_exists( 'update_post_meta' ) ) {
						update_post_meta( $component->wp_post_id, '_pcspecs_image_url', $thumb_url );
						update_post_meta( $component->wp_post_id, '_hwsync_image_url', $thumb_url );
					}
					$this->emit( $logger, 'success', "[LOCAL ATTACHED] Associated existing WordPress featured image to [{$comp_name}]." );
					return true;
				}
			}
		}

		// Generate candidate slugs and filenames based on canonical component name and MPN
		$brand_name = trim( (string) $component->brand );
		$model_name = trim( (string) $component->model_name );
		$mpn        = trim( (string) $component->mpn );

		if ( ! empty( $brand_name ) && stripos( $model_name, $brand_name ) === false ) {
			$full_name = $brand_name . ' ' . $model_name;
		} else {
			$full_name = $model_name ?: ( $brand_name ?: 'component' );
		}

		$slug1 = function_exists( 'sanitize_title' ) ? sanitize_title( $full_name ) : preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $full_name ) );
		$slug2 = function_exists( 'sanitize_title' ) ? sanitize_title( $model_name ) : preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $model_name ) );
		$slug3 = ! empty( $mpn ) ? ( function_exists( 'sanitize_title' ) ? sanitize_title( $mpn ) : preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $mpn ) ) ) : '';

		$candidate_slugs = array_unique( array_filter( array( $slug1, $slug2, $slug3 ) ) );
		$extensions = array( 'webp', 'jpg', 'png', 'jpeg' );

		// 3. Check Local Disk Files in wp-content/uploads/hwsync/
		if ( function_exists( 'wp_upload_dir' ) ) {
			$upload_dir = wp_upload_dir();
			$hwsync_dir = trailingslashit( $upload_dir['basedir'] ) . 'hwsync';
			$hwsync_url = trailingslashit( $upload_dir['baseurl'] ) . 'hwsync';

			if ( file_exists( $hwsync_dir ) ) {
				foreach ( $candidate_slugs as $c_slug ) {
					foreach ( $extensions as $ext ) {
						$test_file = $hwsync_dir . '/' . $c_slug . '.' . $ext;
						if ( file_exists( $test_file ) && filesize( $test_file ) > 100 ) {
							$local_url = $hwsync_url . '/' . $c_slug . '.' . $ext;
							$component->image_url = $local_url;
							$component->save();

							$this->attach_local_file_to_post( $component, $test_file, $local_url, $full_name );

							$this->emit( $logger, 'success', "[LOCAL FILE MATCH] Found saved image '{$c_slug}.{$ext}' on disk -> Associated with [{$comp_name}]." );
							return true;
						}
					}
				}
			}
		}

		// 4. Check WordPress Media Library Attachments
		if ( isset( $wpdb ) && is_object( $wpdb ) && isset( $wpdb->posts ) ) {
			foreach ( $candidate_slugs as $c_slug ) {
				$safe_slug = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $c_slug ) : addcslashes( (string) $c_slug, '_%\\' );
				$like_pattern = '%' . $safe_slug . '%';
				$attach_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ( post_name = %s OR guid LIKE %s OR post_title LIKE %s ) LIMIT 1",
					$c_slug,
					$like_pattern,
					$like_pattern
				) );

				if ( $attach_id && function_exists( 'wp_get_attachment_url' ) ) {
					$attach_url = wp_get_attachment_url( $attach_id );
					if ( ! empty( $attach_url ) ) {
						$component->image_url = $attach_url;
						$component->save();

						if ( ! empty( $component->wp_post_id ) ) {
							if ( function_exists( 'set_post_thumbnail' ) ) {
								set_post_thumbnail( $component->wp_post_id, $attach_id );
							}
							if ( function_exists( 'update_post_meta' ) ) {
								update_post_meta( $component->wp_post_id, '_pcspecs_image_url', $attach_url );
								update_post_meta( $component->wp_post_id, '_hwsync_image_url', $attach_url );
							}
						}

						$this->emit( $logger, 'success', "[MEDIA LIBRARY MATCH] Found media attachment #{$attach_id} -> Associated with [{$comp_name}]." );
						return true;
					}
				}
			}

			// 5. Check Other Canonical Components with same Model or MPN with existing local image
			$comp_table = Database::get_table_name( 'components' );
			$where_parts = array();
			$params = array();

			if ( ! empty( $component->mpn ) ) {
				$where_parts[] = "mpn = %s";
				$params[] = $component->mpn;
			}
			if ( ! empty( $component->model_name ) ) {
				$where_parts[] = "( brand = %s AND model_name = %s )";
				$params[] = $component->brand;
				$params[] = $component->model_name;
			}

			if ( ! empty( $where_parts ) ) {
				$params[] = $component->id;
				$sql = "SELECT image_url FROM {$comp_table} WHERE (" . implode( ' OR ', $where_parts ) . ") AND image_url LIKE '%/uploads/%' AND id != %d LIMIT 1";
				$sibling_img = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

				if ( ! empty( $sibling_img ) && self::is_local_image_url( $sibling_img ) ) {
					$component->image_url = $sibling_img;
					$component->save();

					if ( ! empty( $component->wp_post_id ) && function_exists( 'update_post_meta' ) ) {
						update_post_meta( $component->wp_post_id, '_pcspecs_image_url', $sibling_img );
						update_post_meta( $component->wp_post_id, '_hwsync_image_url', $sibling_img );
					}

					$this->emit( $logger, 'success', "[SIBLING MATCH] Found local image from matching component -> Associated with [{$comp_name}]." );
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Attach a local file to WordPress post as thumbnail and postmeta.
	 */
	private function attach_local_file_to_post( Component $component, $file_path, $local_url, $title ) {
		if ( ! empty( $component->wp_post_id ) && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $component->wp_post_id, '_pcspecs_image_url', $local_url );
			update_post_meta( $component->wp_post_id, '_hwsync_image_url', $local_url );

			if ( function_exists( 'wp_insert_attachment' ) && file_exists( $file_path ) ) {
				$ext = pathinfo( $file_path, PATHINFO_EXTENSION );
				$wp_filetype = function_exists( 'wp_check_filetype' ) ? wp_check_filetype( $file_path, null ) : array( 'type' => 'image/' . $ext );
				$attachment = array(
					'post_mime_type' => $wp_filetype['type'] ?: 'image/jpeg',
					'post_title'     => $title,
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				$attachment_id = wp_insert_attachment( $attachment, $file_path );
				if ( ! is_wp_error( $attachment_id ) && $attachment_id > 0 ) {
					if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
						try {
							$attach_data = @wp_generate_attachment_metadata( $attachment_id, $file_path );
							if ( is_array( $attach_data ) && function_exists( 'wp_update_attachment_metadata' ) ) {
								wp_update_attachment_metadata( $attachment_id, $attach_data );
							}
						} catch ( \Throwable $t ) {}
					}
					if ( function_exists( 'set_post_thumbnail' ) ) {
						set_post_thumbnail( $component->wp_post_id, $attachment_id );
					}
				}
			}
		}
	}

	/**
	 * Run product image synchronization for existing canonical components in DB.
	 * First performs an internal check: components with local images already saved are skipped,
	 * and only components without local images are downloaded and saved to disk.
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
			$this->emit( $logger, 'info', "Image Pre-Check: Found {$audit['total']} total components ({$audit['already_synced']} local photos saved, {$audit['needing_sync']} left to download)." );
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

			// STEP 1: Check Local First (Existing saved image, local file on disk, Media Library, or Post Thumbnail)
			if ( ! $force ) {
				$local_associated = $this->try_associate_existing_local_image( $component, $logger );
				if ( $local_associated ) {
					$report['skipped']++;
					continue;
				}
			}

			// Gather linked vendor prices
			$prices = $component->get_prices();
			if ( empty( $prices ) ) {
				$this->emit( $logger, 'debug', "Component #{$component->id} [{$comp_name}] has no linked vendor listings. Skipping." );
				$report['skipped']++;
				continue;
			}

			$image_downloaded = false;

			// STEP 2: FAST PATH - Check if any linked price listing already has image_url in raw_data
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

						$this->emit( $logger, 'success', "Downloaded & Saved 1 local photo for [{$comp_name}] -> {$save_res['file_name']}" );
						break; // STRICTLY 1 PHOTO PER PRODUCT
					}
				}
			}

			// STEP 3: FALLBACK - Visit primary store product page and extract main photo
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

						$this->emit( $logger, 'success', "Downloaded & Saved 1 local photo for [{$comp_name}] -> {$save_res['file_name']}" );
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
	 * Download remote image, rename to component name, save to local files, and attach to component / post.
	 *
	 * @param Component $component Hardware component.
	 * @param string $remote_url Remote image URL.
	 * @return array|null Result with local URL and filename.
	 */
	public function download_and_attach_image( Component $component, $remote_url ) {
		$remote_url = trim( (string) $remote_url );
		if ( empty( $remote_url ) ) {
			return null;
		}

		try {
			$image_data = null;
			$content_type = '';

			// 1. Try cURL first for speed & reliability
			if ( function_exists( 'curl_init' ) ) {
				$ch = curl_init();
				curl_setopt_array( $ch, array(
					CURLOPT_URL            => $remote_url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS      => 5,
					CURLOPT_TIMEOUT        => 15,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => 0,
					CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 HWsync/0.0.1.7',
					CURLOPT_HTTPHEADER     => array(
						'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
						'Accept-Language: en-US,en;q=0.9',
					),
				) );
				$body = curl_exec( $ch );
				$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
				$content_type = (string) curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
				curl_close( $ch );

				if ( $code >= 200 && $code < 400 && ! empty( $body ) && strlen( $body ) >= 300 ) {
					$image_data = $body;
				}
			}

			// 2. Fallback to wp_remote_get
			if ( empty( $image_data ) && function_exists( 'wp_remote_get' ) ) {
				$response = wp_remote_get( $remote_url, array(
					'timeout'    => 15,
					'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 HWsync/0.0.1.7',
					'sslverify'  => false,
				) );

				if ( ! is_wp_error( $response ) ) {
					$code = wp_remote_retrieve_response_code( $response );
					$body = wp_remote_retrieve_body( $response );
					if ( $code >= 200 && $code < 400 && ! empty( $body ) && strlen( $body ) >= 300 ) {
						$image_data = $body;
						$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
					}
				}
			}

			if ( empty( $image_data ) ) {
				return null;
			}

			// Determine file extension
			$ext = 'jpg';
			if ( stripos( $content_type, 'png' ) !== false || preg_match( '/\.png($|\?)/i', $remote_url ) ) {
				$ext = 'png';
			} elseif ( stripos( $content_type, 'webp' ) !== false || preg_match( '/\.webp($|\?)/i', $remote_url ) ) {
				$ext = 'webp';
			}

			// Generate clean, SEO-friendly file name based exactly on canonical component name
			$brand_name = trim( (string) $component->brand );
			$model_name = trim( (string) $component->model_name );
			if ( ! empty( $brand_name ) && stripos( $model_name, $brand_name ) === false ) {
				$comp_full_name = $brand_name . ' ' . $model_name;
			} else {
				$comp_full_name = $model_name ?: ( $brand_name ?: 'component' );
			}

			$slug = function_exists( 'sanitize_title' ) ? sanitize_title( $comp_full_name ) : preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $comp_full_name ) );
			$slug = trim( (string) $slug, '-' );
			if ( empty( $slug ) ) {
				$slug = 'component-' . $component->id;
			}

			$file_name = sanitize_file_name( $slug . '.' . $ext );

			// Save to local uploads directory (wp-content/uploads/hwsync/)
			$local_file_path = '';
			$local_url       = '';

			if ( function_exists( 'wp_upload_dir' ) ) {
				$upload_dir = wp_upload_dir();
				$hwsync_dir = trailingslashit( $upload_dir['basedir'] ) . 'hwsync';
				$hwsync_url = trailingslashit( $upload_dir['baseurl'] ) . 'hwsync';

				if ( function_exists( 'wp_mkdir_p' ) ) {
					wp_mkdir_p( $hwsync_dir );
				} elseif ( ! file_exists( $hwsync_dir ) ) {
					@mkdir( $hwsync_dir, 0755, true );
				}

				$local_file_path = $hwsync_dir . '/' . $file_name;
				$local_url       = $hwsync_url . '/' . $file_name;

				@file_put_contents( $local_file_path, $image_data );
			} elseif ( function_exists( 'wp_upload_bits' ) ) {
				$upload = wp_upload_bits( $file_name, null, $image_data );
				if ( empty( $upload['error'] ) ) {
					$local_file_path = $upload['file'];
					$local_url       = $upload['url'];
				}
			}

			if ( empty( $local_file_path ) || empty( $local_url ) ) {
				return null;
			}

			// Register in Media Library safely
			$attachment_id = 0;
			if ( function_exists( 'wp_insert_attachment' ) && file_exists( $local_file_path ) ) {
				if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/media.php';
				}

				$wp_filetype = function_exists( 'wp_check_filetype' ) ? wp_check_filetype( $file_name, null ) : array( 'type' => 'image/' . $ext );
				$attachment = array(
					'post_mime_type' => $wp_filetype['type'] ?: 'image/jpeg',
					'post_title'     => $comp_full_name,
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				$attachment_id = wp_insert_attachment( $attachment, $local_file_path );
				if ( ! is_wp_error( $attachment_id ) && $attachment_id > 0 ) {
					if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
						try {
							$attach_data = @wp_generate_attachment_metadata( $attachment_id, $local_file_path );
							if ( is_array( $attach_data ) && function_exists( 'wp_update_attachment_metadata' ) ) {
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

			// Update postmeta for _pcspecs_image_url and _hwsync_image_url
			if ( ! empty( $component->wp_post_id ) && function_exists( 'update_post_meta' ) ) {
				update_post_meta( $component->wp_post_id, '_pcspecs_image_url', $local_url );
				update_post_meta( $component->wp_post_id, '_hwsync_image_url', $local_url );
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
