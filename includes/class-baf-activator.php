<?php
/**
 * Plugin activator
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Activator class
 * 
 * Handles plugin activation tasks including database table creation.
 */
class BAF_Activator {

    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::create_default_options();
        self::create_upload_directories();
        self::set_capabilities();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag for welcome redirect
        set_transient( 'baf_activation_redirect', true, 30 );
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Forms table
        $table_forms = $wpdb->prefix . 'baf_forms';
        $sql_forms = "CREATE TABLE $table_forms (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            config longtext NOT NULL,
            bfl_source longtext,
            status enum('draft','active','inactive') DEFAULT 'draft',
            settings longtext,
            confirmations longtext,
            notifications longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            created_by bigint(20) unsigned,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Entries table
        $table_entries = $wpdb->prefix . 'baf_entries';
        $sql_entries = "CREATE TABLE $table_entries (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned,
            status enum('active','trash','spam','pending','approved','rejected') DEFAULT 'active',
            ip_address varchar(45),
            user_agent varchar(255),
            source_url varchar(2000),
            referrer_url varchar(2000),
            payment_status varchar(50),
            payment_amount decimal(10,2),
            payment_date datetime,
            payment_transaction_id varchar(255),
            is_starred tinyint(1) DEFAULT 0,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY is_starred (is_starred),
            KEY is_read (is_read)
        ) $charset_collate;";

        // Entry meta table
        $table_entry_meta = $wpdb->prefix . 'baf_entry_meta';
        $sql_entry_meta = "CREATE TABLE $table_entry_meta (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            field_id varchar(50) NOT NULL,
            field_value longtext,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY field_id (field_id)
        ) $charset_collate;";

        // Entry notes table
        $table_entry_notes = $wpdb->prefix . 'baf_entry_notes';
        $sql_entry_notes = "CREATE TABLE $table_entry_notes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            note_type enum('note','email','status_change','edit') DEFAULT 'note',
            content text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Views table
        $table_views = $wpdb->prefix . 'baf_views';
        $sql_views = "CREATE TABLE $table_views (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            form_id bigint(20) unsigned NOT NULL,
            view_type enum('table','cards','list','map','calendar') NOT NULL,
            config longtext NOT NULL,
            status enum('draft','active','inactive') DEFAULT 'draft',
            permissions longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            created_by bigint(20) unsigned,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY form_id (form_id),
            KEY status (status)
        ) $charset_collate;";

        // Drafts table (Save & Continue)
        $table_drafts = $wpdb->prefix . 'baf_drafts';
        $sql_drafts = "CREATE TABLE $table_drafts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            resume_token varchar(64) NOT NULL,
            user_id bigint(20) unsigned,
            email varchar(255),
            data longtext NOT NULL,
            current_page int DEFAULT 1,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY resume_token (resume_token),
            KEY form_id (form_id),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Rate limiting table
        $table_rate_limits = $wpdb->prefix . 'baf_rate_limits';
        $sql_rate_limits = "CREATE TABLE $table_rate_limits (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            ip_address varchar(45) NOT NULL,
            attempts int DEFAULT 1,
            window_start datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY form_ip (form_id, ip_address),
            KEY window_start (window_start)
        ) $charset_collate;";

        // File uploads table
        $table_uploads = $wpdb->prefix . 'baf_uploads';
        $sql_uploads = "CREATE TABLE $table_uploads (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned,
            field_id varchar(50),
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_url varchar(500) NOT NULL,
            file_type varchar(100),
            file_size bigint(20) unsigned,
            upload_token varchar(64),
            status enum('temp','permanent','deleted') DEFAULT 'temp',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY upload_token (upload_token),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $sql_forms );
        dbDelta( $sql_entries );
        dbDelta( $sql_entry_meta );
        dbDelta( $sql_entry_notes );
        dbDelta( $sql_views );
        dbDelta( $sql_drafts );
        dbDelta( $sql_rate_limits );
        dbDelta( $sql_uploads );

        // Store database version
        update_option( 'baf_db_version', BAF_VERSION );
    }

    /**
     * Create default plugin options
     */
    private static function create_default_options() {
        $defaults = array(
            'baf_settings' => array(
                // General settings
                'default_form_status'      => 'draft',
                'enable_honeypot'          => true,
                'enable_csrf'              => true,
                'enable_rate_limiting'     => true,
                'rate_limit_submissions'   => 10,
                'rate_limit_period'        => 'minute',
                
                // Security
                'captcha_provider'         => 'none',
                'recaptcha_site_key'       => '',
                'recaptcha_secret_key'     => '',
                'hcaptcha_site_key'        => '',
                'hcaptcha_secret_key'      => '',
                'turnstile_site_key'       => '',
                'turnstile_secret_key'     => '',
                'block_disposable_emails'  => true,
                
                // Accessibility
                'enforce_wcag'             => true,
                'default_focus_style'      => 'outline',
                'respect_reduced_motion'   => true,
                'enable_skip_links'        => true,
                'announce_errors'          => true,
                'high_contrast_support'    => true,
                
                // File uploads
                'max_upload_size'          => 10, // MB
                'allowed_file_types'       => 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx',
                'upload_path'              => 'baf-uploads',
                
                // Currency
                'default_currency'         => 'USD',
                'currency_position'        => 'before',
                'thousand_separator'       => ',',
                'decimal_separator'        => '.',
                'decimal_places'           => 2,
                
                // Date/Time
                'date_format'              => 'Y-m-d',
                'time_format'              => 'H:i',
                'week_starts_on'           => 0, // Sunday
                
                // Save & Continue
                'draft_expiration_days'    => 30,
                'auto_save_interval'       => 30, // seconds
                
                // Notifications
                'admin_email'              => get_option( 'admin_email' ),
                'from_name'                => get_option( 'blogname' ),
                'from_email'               => get_option( 'admin_email' ),
            ),
        );

        foreach ( $defaults as $option => $value ) {
            if ( false === get_option( $option ) ) {
                add_option( $option, $value );
            }
        }
    }

    /**
     * Create upload directories
     */
    private static function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $baf_dir    = $upload_dir['basedir'] . '/baf-uploads';

        if ( ! file_exists( $baf_dir ) ) {
            wp_mkdir_p( $baf_dir );
        }

        // Create .htaccess for security
        $htaccess = $baf_dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $htaccess_content = "# Deny direct access to uploaded files\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "<FilesMatch \"\\.(php|php\\d?|phtml|phar|inc)$\">\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</FilesMatch>\n";

            file_put_contents( $htaccess, $htaccess_content );
        }

        // Create index.php for security
        $index = $baf_dir . '/index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, '<?php // Silence is golden.' );
        }
    }

    /**
     * Set up user capabilities
     */
    private static function set_capabilities() {
        $admin = get_role( 'administrator' );

        if ( $admin ) {
            // Form management capabilities
            $admin->add_cap( 'baf_manage_forms' );
            $admin->add_cap( 'baf_create_forms' );
            $admin->add_cap( 'baf_edit_forms' );
            $admin->add_cap( 'baf_delete_forms' );
            $admin->add_cap( 'baf_publish_forms' );
            
            // Entry management capabilities
            $admin->add_cap( 'baf_view_entries' );
            $admin->add_cap( 'baf_edit_entries' );
            $admin->add_cap( 'baf_delete_entries' );
            $admin->add_cap( 'baf_export_entries' );
            
            // View management capabilities
            $admin->add_cap( 'baf_manage_views' );
            $admin->add_cap( 'baf_create_views' );
            $admin->add_cap( 'baf_edit_views' );
            $admin->add_cap( 'baf_delete_views' );
            
            // Settings capabilities
            $admin->add_cap( 'baf_manage_settings' );
        }

        // Editor role
        $editor = get_role( 'editor' );

        if ( $editor ) {
            $editor->add_cap( 'baf_create_forms' );
            $editor->add_cap( 'baf_edit_forms' );
            $editor->add_cap( 'baf_view_entries' );
            $editor->add_cap( 'baf_edit_entries' );
            $editor->add_cap( 'baf_export_entries' );
            $editor->add_cap( 'baf_create_views' );
            $editor->add_cap( 'baf_edit_views' );
        }
    }
}
