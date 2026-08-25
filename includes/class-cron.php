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
				'display'  => __( 'Every 6 Hours', 'hwsync' ),
			);
		}
		if ( ! isset( $schedules['every_two_hours'] ) ) {
			$schedules['every_two_hours'] = array(
				'interval' => 2 * HOUR_IN_SECONDS,
				'display'  => __( 'Every 2 Hours', 'hwsync' ),
			);
		}
		return $schedules;
	}

	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'every_six_hours', self::CRON_HOOK );
		}
	}

	public static function clear_events() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	public static function execute_sync() {
		$manager = new Sync_Manager();
		$report = $manager->run_sync( array( 'vendor' => 'all', 'category' => 'all' ) );
		update_option( 'hwsync_last_sync_report', $report );
	}
}
