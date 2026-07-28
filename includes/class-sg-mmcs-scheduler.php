<?php
namespace SoftGlaze\MMCS;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lightweight scheduler/automation for WP.org compliance.
 *
 * - Uses WP-Cron (no external services)
 * - Never runs on admin-ajax/rest requests
 * - Uses stored UTC timestamps for start/end
 */
class Scheduler {

    const EVENT_HOOK = 'sg_mmcs_schedule_check';

    /**
     * Register hooks.
     */
    public static function boot() {
        add_action( self::EVENT_HOOK, [ __CLASS__, 'run' ] );

        // Opportunistic check on normal page loads (keeps schedule accurate even if cron is disabled).
        add_action( 'init', function() {
            if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
                return;
            }
            self::maybe_run_opportunistically();
        }, 5 );
    }

    /**
     * Ensure cron event is scheduled.
     */
    public static function activate() {
        if ( ! wp_next_scheduled( self::EVENT_HOOK ) ) {
            wp_schedule_event( time() + 300, 'sg_mmcs_five_minutes', self::EVENT_HOOK );
        }
    }

    /**
     * Clear cron event.
     */
    public static function deactivate() {
        $ts = wp_next_scheduled( self::EVENT_HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::EVENT_HOOK );
        }
    }

    /**
     * Register custom interval.
     */
    public static function register_intervals() {
        add_filter( 'cron_schedules', function( $schedules ) {
            if ( ! isset( $schedules['sg_mmcs_five_minutes'] ) ) {
                $schedules['sg_mmcs_five_minutes'] = [
                    'interval' => 5 * MINUTE_IN_SECONDS,
                    'display'  => __( 'Every 5 minutes (SoftGlaze MMCS)', 'softglaze-maintenance-mode-coming-soon' ),
                ];
            }
            return $schedules;
        } );
    }

    /**
     * Run schedule check and apply mode changes.
     */
    public static function run() {
        $opts = Options::get();
        if ( empty( $opts['schedule_enabled'] ) ) {
            return;
        }

        $start = isset( $opts['schedule_start_ts'] ) ? (int) $opts['schedule_start_ts'] : 0;
        $end   = isset( $opts['schedule_end_ts'] ) ? (int) $opts['schedule_end_ts'] : 0;
        if ( $start <= 0 || $end <= 0 || $end <= $start ) {
            return;
        }

        $now = time();
        $target_mode = 'off';

        if ( $now >= $start && $now < $end ) {
            $sm = isset( $opts['schedule_mode'] ) ? (string) $opts['schedule_mode'] : 'maintenance';
            $target_mode = in_array( $sm, [ 'coming_soon', 'maintenance' ], true ) ? $sm : 'maintenance';
        }

        // Restore mode after schedule ends.
        if ( 'off' === $target_mode && $now >= $end ) {
            $restore = isset( $opts['schedule_restore_mode'] ) ? (string) $opts['schedule_restore_mode'] : 'off';
            if ( in_array( $restore, [ 'off', 'coming_soon', 'maintenance' ], true ) ) {
                $target_mode = $restore;
            }
        }

        if ( $opts['mode'] !== $target_mode ) {
            $new = $opts;
            $new['mode'] = $target_mode;
            // Ensure correct HTTP status for maintenance.
            if ( 'maintenance' === $target_mode ) {
                $new['http_status'] = '503';
            }
            if ( 'coming_soon' === $target_mode ) {
                $new['http_status'] = '200';
            }
            update_option( Options::OPTION_KEY, $new );
        }
    }

    /**
     * Throttle opportunistic checks to avoid excessive DB writes.
     */
    private static function maybe_run_opportunistically() {
        $key = 'sg_mmcs_schedule_last_run';
        $last = (int) get_transient( $key );
        if ( $last > 0 && ( time() - $last ) < 60 ) {
            return;
        }
        set_transient( $key, time(), 120 );
        self::run();
    }
}

// Register cron schedule interval.
Scheduler::register_intervals();
