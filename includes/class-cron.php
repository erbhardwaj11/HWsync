<?php
namespace HWsync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cron {
	const CRON_HOOK = 'hwsync_scheduled_sync_event';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_custom_intervals' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'execute_sync' ) );
	}

	public static function add_custom_intervals( $schedules ) {
		if ( ! isset( $schedules['every_six_hours'] ) ) {
			$schedules['every_six_hours'] = array(
				'interval' => 6 * HOUR_IN_SECONDS,
				'display'  => \__( 'Every 6 Hours', 'hwsync' ),
			);
		}
		if ( ! isset( $schedules['every_two_hours'] ) ) {
			$schedules['every_two_hours'] = array(
				'interval' => 2 * HOUR_IN_SECONDS,
				'display'  => \__( 'Every 2 Hours', 'hwsync' ),
			);
		}
		return $schedules;
	}

	/**
	 * Setup or update schedule based on user configuration.
	 */
	public static function update_schedule( $enabled, $frequency = 'daily', $time_str = '03:00' ) {
		self::clear_events();

		update_option( 'hwsync_schedule_enabled', $enabled ? 1 : 0 );
		update_option( 'hwsync_schedule_frequency', sanitize_text_field( $frequency ) );
		update_option( 'hwsync_schedule_time', sanitize_text_field( $time_str ) );

		if ( ! $enabled ) {
			return;
		}

		// Calculate first execution timestamp based on local site time
		$time_parts = explode( ':', $time_str );
		$hour       = isset( $time_parts[0] ) ? intval( $time_parts[0] ) : 3;
		$minute     = isset( $time_parts[1] ) ? intval( $time_parts[1] ) : 0;

		$now        = current_time( 'timestamp' );
		$target     = strtotime( sprintf( '%02d:%02d:00', $hour, $minute ), $now );

		// If time has already passed today, schedule for tomorrow
		if ( $target <= $now ) {
			$target += DAY_IN_SECONDS;
		}

		// Convert local timestamp to UTC timestamp for WP Cron
		$offset_seconds = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$utc_target     = $target - $offset_seconds;

		wp_schedule_event( $utc_target, $frequency, self::CRON_HOOK );
	}

	public static function schedule_events() {
		$enabled   = get_option( 'hwsync_schedule_enabled', 1 );
		$frequency = get_option( 'hwsync_schedule_frequency', 'daily' );
		$time_str  = get_option( 'hwsync_schedule_time', '03:00' );

		if ( $enabled && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::update_schedule( true, $frequency, $time_str );
		}
	}

	public static function clear_events() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Execute the scheduled background sync.
	 * Runs in DELTA mode to only update new listings or changed prices/stock.
	 */
	public static function execute_sync() {
		$manager = new Sync_Manager();
		// delta_only = true ensures we only update new listings or modified prices/stock
		$report = $manager->run_sync( array(
			'vendor'     => 'all',
			'category'   => 'all',
			'delta_only' => true,
		) );
		update_option( 'hwsync_last_sync_report', $report );
	}
}
