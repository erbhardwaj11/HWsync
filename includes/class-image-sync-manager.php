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
	 * Returns clean inline SVG vector icon for a specific hardware category.
	 *
	 * @param string $category Hardware category slug.
	 * @return string Vector SVG markup.
	 */
	public static function get_default_category_svg( $category ) {
		$cat = strtolower( trim( (string) $category ) );
		switch ( $cat ) {
			case 'cpu':
			case 'processor':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="28" y="28" width="144" height="144" rx="14" fill="#131e36" stroke="#00f2fe" stroke-width="2.5" stroke-opacity="0.8"/><rect x="52" y="52" width="96" height="96" rx="8" fill="#1c2b4c" stroke="#38bdf8" stroke-width="2"/><rect x="70" y="70" width="60" height="60" rx="6" fill="#0f172a" stroke="#00f2fe" stroke-width="2.5"/><path d="M78 86h44M78 100h44M78 114h44" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-opacity="0.7"/><path d="M40 28v-12M60 28v-12M80 28v-12M100 28v-12M120 28v-12M140 28v-12M160 28v-12" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round"/><path d="M40 184v-12M60 184v-12M80 184v-12M100 184v-12M120 184v-12M140 184v-12M160 184v-12" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round"/><path d="M28 40h-12M28 60h-12M28 80h-12M28 100h-12M28 120h-12M28 140h-12M28 160h-12" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round"/><path d="M184 40h-12M184 60h-12M184 80h-12M184 100h-12M184 120h-12M184 140h-12M184 160h-12" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round"/><polygon points="34,34 46,34 34,46" fill="#00f2fe"/></svg>';

			case 'gpu':
			case 'graphics-card':
			case 'video-card':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="24" y="55" width="152" height="85" rx="10" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><circle cx="68" cy="97" r="28" fill="#1c2b4c" stroke="#38bdf8" stroke-width="2"/><circle cx="68" cy="97" r="10" fill="#00f2fe"/><path d="M68 73v48M44 97h48M51 80l34 34M51 114l34-34" stroke="#38bdf8" stroke-width="2" stroke-linecap="round"/><circle cx="132" cy="97" r="28" fill="#1c2b4c" stroke="#38bdf8" stroke-width="2"/><circle cx="132" cy="97" r="10" fill="#00f2fe"/><path d="M132 73v48M108 97h48M115 80l34 34M115 114l34-34" stroke="#38bdf8" stroke-width="2" stroke-linecap="round"/><rect x="40" y="140" width="80" height="12" rx="2" fill="#00f2fe"/><path d="M46 140v12M54 140v12M62 140v12M70 140v12M78 140v12M86 140v12M94 140v12M102 140v12M110 140v12" stroke="#0c1322" stroke-width="2"/><rect x="18" y="45" width="8" height="105" rx="3" fill="#38bdf8"/></svg>';

			case 'motherboard':
			case 'mobo':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="25" y="25" width="150" height="150" rx="10" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><rect x="45" y="45" width="50" height="50" rx="6" fill="#1c2b4c" stroke="#38bdf8" stroke-width="2"/><rect x="55" y="55" width="30" height="30" rx="4" fill="#0c1322" stroke="#00f2fe" stroke-width="1.5"/><rect x="115" y="42" width="8" height="60" rx="2" fill="#00f2fe"/><rect x="128" y="42" width="8" height="60" rx="2" fill="#38bdf8"/><rect x="141" y="42" width="8" height="60" rx="2" fill="#00f2fe"/><rect x="154" y="42" width="8" height="60" rx="2" fill="#38bdf8"/><rect x="45" y="115" width="115" height="10" rx="2" fill="#38bdf8"/><rect x="45" y="135" width="80" height="8" rx="2" fill="#1c2b4c" stroke="#00f2fe" stroke-width="1.5"/><rect x="45" y="152" width="115" height="10" rx="2" fill="#38bdf8"/><rect x="25" y="40" width="12" height="40" fill="#00f2fe" opacity="0.8"/><rect x="125" y="125" width="35" height="35" rx="4" fill="#1c2b4c" stroke="#00f2fe" stroke-width="2"/></svg>';

			case 'ram':
			case 'memory':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="20" y="70" width="160" height="60" rx="6" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><path d="M20 70 L40 54 L160 54 L180 70 Z" fill="#1c2b4c" stroke="#00f2fe" stroke-width="2"/><rect x="35" y="78" width="22" height="34" rx="3" fill="#0c1322" stroke="#38bdf8" stroke-width="1.5"/><rect x="68" y="78" width="22" height="34" rx="3" fill="#0c1322" stroke="#38bdf8" stroke-width="1.5"/><rect x="101" y="78" width="22" height="34" rx="3" fill="#0c1322" stroke="#38bdf8" stroke-width="1.5"/><rect x="134" y="78" width="22" height="34" rx="3" fill="#0c1322" stroke="#38bdf8" stroke-width="1.5"/><rect x="26" y="130" width="148" height="14" rx="2" fill="#00f2fe"/><path d="M34 130v14M44 130v14M54 130v14M64 130v14M74 130v14M84 130v14M94 130v14M106 130v14M116 130v14M126 130v14M136 130v14M146 130v14M156 130v14M166 130v14" stroke="#0c1322" stroke-width="2"/><rect x="97" y="132" width="6" height="12" fill="#0c1322"/></svg>';

			case 'storage':
			case 'ssd':
			case 'hdd':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="25" y="60" width="150" height="80" rx="8" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><rect x="40" y="75" width="30" height="30" rx="4" fill="#1c2b4c" stroke="#38bdf8" stroke-width="2"/><circle cx="55" cy="90" r="5" fill="#00f2fe"/><rect x="85" y="75" width="36" height="50" rx="4" fill="#0c1322" stroke="#38bdf8" stroke-width="2"/><rect x="130" y="75" width="36" height="50" rx="4" fill="#0c1322" stroke="#38bdf8" stroke-width="2"/><rect x="25" y="112" width="40" height="18" rx="2" fill="#00f2fe"/><path d="M30 112v18M36 112v18M42 112v18M48 112v18M54 112v18M60 112v18" stroke="#0c1322" stroke-width="2"/><path d="M175 90 a10,10 0 0,0 0,20 Z" fill="#0c1322" stroke="#00f2fe" stroke-width="2"/></svg>';

			case 'psu':
			case 'power-supply':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="25" y="30" width="150" height="140" rx="12" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><circle cx="85" cy="100" r="42" fill="#1c2b4c" stroke="#38bdf8" stroke-width="2"/><circle cx="85" cy="100" r="16" fill="#00f2fe"/><path d="M85 62v76M47 100h76M58 73l54 54M58 127l54-54" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round"/><rect x="145" y="55" width="20" height="32" rx="4" fill="#0c1322" stroke="#38bdf8" stroke-width="1.5"/><rect x="147" y="105" width="16" height="24" rx="2" fill="#00f2fe"/><rect x="40" y="148" width="14" height="10" fill="#00f2fe"/><rect x="62" y="148" width="14" height="10" fill="#00f2fe"/><rect x="84" y="148" width="14" height="10" fill="#00f2fe"/><rect x="106" y="148" width="14" height="10" fill="#00f2fe"/></svg>';

			case 'cooler':
			case 'cpu-cooler':
			case 'fan':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="35" y="30" width="130" height="60" rx="8" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><path d="M45 30v60M55 30v60M65 30v60M75 30v60M85 30v60M95 30v60M105 30v60M115 30v60M125 30v60M135 30v60M145 30v60M155 30v60" stroke="#38bdf8" stroke-width="1.5" stroke-opacity="0.6"/><path d="M70 90 C 70 125, 85 130, 85 145" fill="none" stroke="#00f2fe" stroke-width="4" stroke-linecap="round"/><path d="M95 90 C 95 120, 115 125, 115 145" fill="none" stroke="#38bdf8" stroke-width="4" stroke-linecap="round"/><circle cx="100" cy="155" r="28" fill="#1c2b4c" stroke="#00f2fe" stroke-width="2.5"/><circle cx="100" cy="155" r="12" fill="#00f2fe"/><path d="M100 130v50M75 155h50M82 137l36 36M82 173l36-36" stroke="#38bdf8" stroke-width="2" stroke-linecap="round"/></svg>';

			case 'cabinet':
			case 'case':
			case 'chassis':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="40" y="25" width="120" height="150" rx="10" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><rect x="50" y="35" width="85" height="110" rx="6" fill="#1c2b4c" stroke="#38bdf8" stroke-width="2" stroke-opacity="0.8"/><rect x="60" y="45" width="30" height="30" rx="4" fill="#00f2fe" opacity="0.8"/><rect x="60" y="85" width="65" height="20" rx="3" fill="#38bdf8" opacity="0.9"/><rect x="142" y="35" width="10" height="130" rx="2" fill="#0c1322" stroke="#00f2fe" stroke-width="1.5"/><rect x="48" y="175" width="16" height="8" rx="2" fill="#38bdf8"/><rect x="136" y="175" width="16" height="8" rx="2" fill="#38bdf8"/><circle cx="147" cy="30" r="3" fill="#00f2fe"/></svg>';

			case 'case_fan':
			case 'case-fan':
			case 'fan':
			case 'fans':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="30" y="30" width="140" height="140" rx="16" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><circle cx="45" cy="45" r="5" fill="#0c1322" stroke="#38bdf8" stroke-width="2"/><circle cx="155" cy="45" r="5" fill="#0c1322" stroke="#38bdf8" stroke-width="2"/><circle cx="45" cy="155" r="5" fill="#0c1322" stroke="#38bdf8" stroke-width="2"/><circle cx="155" cy="155" r="5" fill="#0c1322" stroke="#38bdf8" stroke-width="2"/><circle cx="100" cy="100" r="58" fill="#1c2b4c" stroke="#38bdf8" stroke-width="2"/><circle cx="100" cy="100" r="24" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><circle cx="100" cy="100" r="10" fill="#00f2fe"/><path d="M100 76 C105 60 120 48 135 46 C130 58 120 69 110 77 Z" fill="#00f2fe" opacity="0.85"/><path d="M124 100 C140 105 152 120 154 135 C142 130 131 120 123 110 Z" fill="#00f2fe" opacity="0.85"/><path d="M100 124 C95 140 80 152 65 154 C70 142 80 131 90 123 Z" fill="#00f2fe" opacity="0.85"/><path d="M76 100 C60 95 48 80 46 65 C58 70 69 80 77 90 Z" fill="#00f2fe" opacity="0.85"/></svg>';

			default:
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="100%" height="100%"><rect width="200" height="200" rx="24" fill="#0c1322"/><rect x="35" y="35" width="130" height="130" rx="16" fill="#131e36" stroke="#00f2fe" stroke-width="2.5"/><path d="M100 60 L140 100 L100 140 L60 100 Z" fill="#1c2b4c" stroke="#38bdf8" stroke-width="2.5"/><circle cx="100" cy="100" r="16" fill="#00f2fe"/></svg>';
		}
	}

	/**
	 * Returns clean self-contained Data URI for a hardware category vector icon.
	 *
	 * @param string $category
	 * @return string Data URI format.
	 */
	public static function get_default_category_data_uri( $category ) {
		$svg = self::get_default_category_svg( $category );
		return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
	}

	/**
	 * Returns canonical URL for default category fallback icon.
	 *
	 * @param string $category
	 * @return string Absolute URL or Data URI.
	 */
	public static function get_default_category_image_url( $category ) {
		$cat = strtolower( trim( (string) $category ) );
		$valid_cats = array( 'cpu', 'gpu', 'motherboard', 'ram', 'storage', 'psu', 'cooler', 'cabinet', 'case_fan' );
		$slug = in_array( $cat, $valid_cats, true ) ? $cat : 'other';

		if ( defined( 'HWSYNC_PLUGIN_URL' ) ) {
			return HWSYNC_PLUGIN_URL . 'assets/images/defaults/' . $slug . '.svg';
		}

		return self::get_default_category_data_uri( $category );
	}

	/**
	 * Check if an image URL is hosted locally on this WordPress installation or is a valid SVG vector.
	 *
	 * @param string $url Image URL to check.
	 * @return bool True if local or internal, false if external link to web.
	 */
	public static function is_local_image_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}

		if ( strpos( $url, 'data:image/' ) === 0 ) {
			return true;
		}

		if ( strpos( $url, '/wp-content/' ) !== false || strpos( $url, '/plugins/hwsync/' ) !== false ) {
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
	 * Synchronize a component image URL across all backend tables and postmeta destinations.
	 *
	 * @param Component $component Hardware component model.
	 * @param string $image_url Target image URL.
	 * @param int $attachment_id Optional attachment ID.
	 * @return bool True on success.
	 */
	public static function sync_component_image( Component $component, $image_url, $attachment_id = 0 ) {
		global $wpdb;
		if ( empty( $component->id ) || empty( $image_url ) ) {
			return false;
		}

		// 1. Update HWsync Component
		$component->image_url = $image_url;
		$component->save();

		// 2. Update native PCSpecs theme table (wp_pc_components) if installed
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			$pc_comp_table = ( isset( $wpdb->prefix ) ? $wpdb->prefix : 'wp_' ) . 'pc_components';
			$has_pc_table = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $pc_comp_table ) ) === $pc_comp_table );
			if ( $has_pc_table ) {
				$wpdb->update( $pc_comp_table, array( 'image_url' => $image_url ), array( 'id' => $component->id ) );
				if ( ! empty( $component->model_name ) ) {
					$wpdb->update( $pc_comp_table, array( 'image_url' => $image_url ), array( 'model_name' => $component->model_name ) );
				}
			}
		}

		// 3. Resolve WordPress Post and update postmeta & thumbnail
		$post_id = ! empty( $component->wp_post_id ) ? intval( $component->wp_post_id ) : 0;
		if ( ! $post_id && isset( $wpdb->postmeta ) ) {
			$post_id = intval( $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE (meta_key = '_pcspecs_component_id' OR meta_key = '_hwsync_component_id') AND meta_value = %d LIMIT 1",
				$component->id
			) ) );
		}

		if ( $post_id > 0 && function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, '_pcspecs_image_url', $image_url );
			update_post_meta( $post_id, '_hwsync_image_url', $image_url );

			if ( $attachment_id > 0 && function_exists( 'set_post_thumbnail' ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		return true;
	}

	/**
	 * Audit image status across canonical components in database.
	 * Components with local images or default SVGs are counted as already synced.
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
			self::sync_component_image( $component, $component->image_url );
			$this->emit( $logger, 'debug', "[LOCAL MATCH] Component #{$component->id} [{$comp_name}] already has local image associated." );
			return true;
		}

		// 2. Check if associated WordPress post already has a featured image attachment
		if ( ! empty( $component->wp_post_id ) && function_exists( 'get_post_thumbnail_id' ) && function_exists( 'wp_get_attachment_url' ) ) {
			$thumb_id = get_post_thumbnail_id( $component->wp_post_id );
			if ( $thumb_id ) {
				$thumb_url = wp_get_attachment_url( $thumb_id );
				if ( ! empty( $thumb_url ) ) {
					self::sync_component_image( $component, $thumb_url, $thumb_id );
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
			$this->emit( $logger, 'info', "Image Pre-Check: Found {$audit['total']} total components ({$audit['already_synced']} with local/vector photos, {$audit['needing_sync']} needing images)." );
		}

		$where_clauses = array( "1=1" );
		if ( $component_id > 0 ) {
			$where_clauses[] = $wpdb->prepare( "id = %d", $component_id );
		} elseif ( $category !== 'all' && ! empty( $category ) ) {
			$where_clauses[] = $wpdb->prepare( "category = %s", $category );
		}

		if ( ! $force ) {
			$where_clauses[] = "(image_url IS NULL OR image_url = '' OR (image_url NOT LIKE 'data:image/%' AND image_url NOT LIKE '%/uploads/%' AND image_url NOT LIKE '%/plugins/hwsync/%'))";
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
			$image_downloaded = false;

			if ( ! empty( $prices ) ) {
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
							self::sync_component_image( $component, $save_res['url'], $save_res['attachment_id'] ?? 0 );

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
							self::sync_component_image( $component, $save_res['url'], $save_res['attachment_id'] ?? 0 );

							$image_downloaded = true;
							$report['images_saved']++;

							$this->emit( $logger, 'success', "Downloaded & Saved 1 local photo for [{$comp_name}] -> {$save_res['file_name']}" );
							break; // STRICTLY 1 PHOTO PER PRODUCT
						}
					}
				}
			}

			// STEP 4: FALLBACK TO DEFAULT CATEGORY VECTOR ICON
			// Never leave any component with a broken image in the catalog or UI!
			if ( ! $image_downloaded ) {
				$default_img = self::get_default_category_image_url( $component->category );
				self::sync_component_image( $component, $default_img );
				$report['images_saved']++;
				$this->emit( $logger, 'info', "[DEFAULT ICON] Linked clean vector icon for {$component->category} to [{$comp_name}]." );
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
