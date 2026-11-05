<?php
/**
 * Plugin deactivator
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Deactivator class
 * 
 * Handles plugin deactivation tasks.
 */
class QAF_Deactivator {

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        self::clear_scheduled_events();
        self::flush_caches();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        wp_clear_scheduled_hook( 'qaf_cleanup_drafts' );
        wp_clear_scheduled_hook( 'qaf_cleanup_rate_limits' );
        wp_clear_scheduled_hook( 'qaf_cleanup_temp_uploads' );
        wp_clear_scheduled_hook( 'qaf_send_scheduled_notifications' );
    }

    /**
     * Flush caches
     */
    private static function flush_caches() {
        wp_cache_flush();
        
        // Clear transients
        delete_transient( 'qaf_form_cache' );
        delete_transient( 'qaf_field_cache' );
        delete_transient( 'qaf_view_cache' );
    }
}
