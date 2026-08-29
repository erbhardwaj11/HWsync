<?php
namespace HWsync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cron {
	const CRON_HOOK       = 'hwsync_scheduled_sync_event';
	const CRON_SPECS_HOOK = 'hwsync_scheduled_specs_sync_event';
	const CRON_IMAGE_HOOK = 'hwsync_scheduled_image_sync_event';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_custom_intervals' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'execute_sync' ) );
		add_action( self::CRON_SPECS_HOOK, array( __CLASS__, 'execute_specs_sync' ) );
		add_action( self::CRON_IMAGE_HOOK, array( __CLASS__, 'execute_image_sync' ) );
	}

	public static function add_custom_intervals( $schedules ) {
		$hour = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;

		if ( ! isset( $schedules['every_six_hours'] ) ) {
			$schedules['every_six_hours'] = array(
				'interval' => 6 * $hour,
				'display'  => __( 'Every 6 Hours', 'hwsync' ),
			);
		}
		if ( ! isset( $schedules['every_two_hours'] ) ) {
			$schedules['every_two_hours'] = array(
				'interval' => 2 * $hour,
				'display'  => __( 'Every 2 Hours', 'hwsync' ),
			);
		}
		return $schedules;
	}

	public static function calculate_utc_timestamp( $time_str = '03:00' ) {
		$time_parts = explode( ':', (string) $time_str );
		$hour       = isset( $time_parts[0] ) ? intval( $time_parts[0] ) : 3;
		$minute     = isset( $time_parts[1] ) ? intval( $time_parts[1] ) : 0;

		$now        = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time();
		if ( ! is_numeric( $now ) ) {
			$now = time();
		}
		$target     = strtotime( sprintf( '%02d:%02d:00', $hour, $minute ), $now );

		$day_sec    = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$hour_sec   = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;

		if ( $target <= $now ) {
			$target += $day_sec;
		}

		$gmt_offset     = get_option( 'gmt_offset', 0 );
		$offset_seconds = ( is_numeric( $gmt_offset ) ? floatval( $gmt_offset ) : 0 ) * $hour_sec;
		return $target - $offset_seconds;
	}

	public static function clear_single_event( $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
			$timestamp = wp_next_scheduled( $hook );
		}
	}

	/**
	 * Setup or update Price & Product schedule.
	 */
	public static function update_schedule( $enabled, $frequency = 'daily', $time_str = '03:00' ) {
		self::clear_single_event( self::CRON_HOOK );

		$freq_clean = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $frequency ) : trim( (string) $frequency );
		$time_clean = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $time_str ) : trim( (string) $time_str );

		update_option( 'hwsync_schedule_enabled', $enabled ? 1 : 0 );
		update_option( 'hwsync_schedule_frequency', $freq_clean );
		update_option( 'hwsync_schedule_time', $time_clean );

		if ( ! $enabled ) {
			return;
		}

		$utc_target = self::calculate_utc_timestamp( $time_clean );
		wp_schedule_event( $utc_target, $freq_clean, self::CRON_HOOK );
	}

	/**
	 * Setup or update Specifications schedule.
	 */
	public static function update_specs_schedule( $enabled, $frequency = 'daily', $time_str = '04:00' ) {
		self::clear_single_event( self::CRON_SPECS_HOOK );

		$freq_clean = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $frequency ) : trim( (string) $frequency );
		$time_clean = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $time_str ) : trim( (string) $time_str );

		update_option( 'hwsync_schedule_specs_enabled', $enabled ? 1 : 0 );
		update_option( 'hwsync_schedule_specs_frequency', $freq_clean );
		update_option( 'hwsync_schedule_specs_time', $time_clean );

		if ( ! $enabled ) {
			return;
		}

		$utc_target = self::calculate_utc_timestamp( $time_clean );
		wp_schedule_event( $utc_target, $freq_clean, self::CRON_SPECS_HOOK );
	}

	/**
	 * Setup or update Images schedule.
	 */
	public static function update_image_schedule( $enabled, $frequency = 'daily', $time_str = '05:00' ) {
		self::clear_single_event( self::CRON_IMAGE_HOOK );

		$freq_clean = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $frequency ) : trim( (string) $frequency );
		$time_clean = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $time_str ) : trim( (string) $time_str );

		update_option( 'hwsync_schedule_image_enabled', $enabled ? 1 : 0 );
		update_option( 'hwsync_schedule_image_frequency', $freq_clean );
		update_option( 'hwsync_schedule_image_time', $time_clean );

		if ( ! $enabled ) {
			return;
		}

		$utc_target = self::calculate_utc_timestamp( $time_clean );
		wp_schedule_event( $utc_target, $freq_clean, self::CRON_IMAGE_HOOK );
	}

	public static function schedule_events() {
		// 1. Price Sync
		$price_enabled = get_option( 'hwsync_schedule_enabled', 1 );
		$price_freq    = get_option( 'hwsync_schedule_frequency', 'daily' );
		$price_time    = get_option( 'hwsync_schedule_time', '03:00' );
		if ( $price_enabled && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::update_schedule( true, $price_freq, $price_time );
		}

		// 2. Specs Sync
		$specs_enabled = get_option( 'hwsync_schedule_specs_enabled', 0 );
		$specs_freq    = get_option( 'hwsync_schedule_specs_frequency', 'daily' );
		$specs_time    = get_option( 'hwsync_schedule_specs_time', '04:00' );
		if ( $specs_enabled && ! wp_next_scheduled( self::CRON_SPECS_HOOK ) ) {
			self::update_specs_schedule( true, $specs_freq, $specs_time );
		}

		// 3. Image Sync
		$img_enabled   = get_option( 'hwsync_schedule_image_enabled', 0 );
		$img_freq      = get_option( 'hwsync_schedule_image_frequency', 'daily' );
		$img_time      = get_option( 'hwsync_schedule_image_time', '05:00' );
		if ( $img_enabled && ! wp_next_scheduled( self::CRON_IMAGE_HOOK ) ) {
			self::update_image_schedule( true, $img_freq, $img_time );
		}
	}

	public static function clear_events() {
		self::clear_single_event( self::CRON_HOOK );
		self::clear_single_event( self::CRON_SPECS_HOOK );
		self::clear_single_event( self::CRON_IMAGE_HOOK );
	}

	/**
	 * Execute the scheduled background price sync (Delta Mode).
	 */
	public static function execute_sync() {
		$manager = new Sync_Manager();
		$report = $manager->run_sync( array(
			'vendor'     => 'all',
			'category'   => 'all',
			'delta_only' => true,
		) );
		update_option( 'hwsync_last_sync_report', $report );
	}

	/**
	 * Execute scheduled background specifications sync.
	 */
	public static function execute_specs_sync() {
		$specs_manager = new Specs_Sync_Manager();
		$report = $specs_manager->run_specs_sync( array(
			'category' => 'all',
		) );
		update_option( 'hwsync_last_specs_sync_report', $report );
	}

	/**
	 * Execute scheduled background image downloading sync.
	 */
	public static function execute_image_sync() {
		$image_manager = new Image_Sync_Manager();
		$report = $image_manager->run_images_sync( array(
			'category' => 'all',
		) );
		update_option( 'hwsync_last_image_sync_report', $report );
	}
}
