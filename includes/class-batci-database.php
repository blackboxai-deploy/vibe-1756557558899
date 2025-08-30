<?php
/**
 * Database operations for Batci Force Login at Checkout
 *
 * @package BatciFLC
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Batci_FLC_Database class
 */
class Batci_FLC_Database {

    /**
     * Database version
     */
    const DB_VERSION = '1.2.0';

    /**
     * Table names
     */
    private static $tables = array(
        'otp_attempts' => 'batci_flc_otp_attempts',
        'analytics'    => 'batci_flc_analytics',
        'settings'     => 'batci_flc_settings',
    );

    /**
     * Initialize database operations
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'maybe_update_db' ) );
    }

    /**
     * Create database tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // OTP Attempts table
        $table_otp = $wpdb->prefix . self::$tables['otp_attempts'];
        $sql_otp = "CREATE TABLE $table_otp (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            phone_number varchar(20) NOT NULL,
            country_code varchar(5) NOT NULL,
            otp_code varchar(10) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            attempts_count int(3) DEFAULT 1,
            status enum('pending','verified','expired','failed') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            verified_at datetime NULL,
            PRIMARY KEY (id),
            KEY phone_number (phone_number),
            KEY status (status),
            KEY ip_address (ip_address),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Analytics table
        $table_analytics = $wpdb->prefix . self::$tables['analytics'];
        $sql_analytics = "CREATE TABLE $table_analytics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            phone_number varchar(20),
            user_id bigint(20) unsigned,
            ip_address varchar(45),
            user_agent text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY phone_number (phone_number),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Settings table
        $table_settings = $wpdb->prefix . self::$tables['settings'];
        $sql_settings = "CREATE TABLE $table_settings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            autoload enum('yes','no') DEFAULT 'yes',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key),
            KEY autoload (autoload)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_otp );
        dbDelta( $sql_analytics );
        dbDelta( $sql_settings );

        // Insert default settings
        self::insert_default_settings();

        // Update database version
        update_option( 'batci_flc_db_version', self::DB_VERSION );
    }

    /**
     * Insert default settings
     */
    private static function insert_default_settings() {
        $defaults = array(
            'otp_expiry_minutes'     => 5,
            'max_otp_attempts'       => 5,
            'rate_limit_minutes'     => 15,
            'modal_bg_color'         => '#ffffff',
            'modal_text_color'       => '#333333',
            'modal_accent_color'     => '#0073aa',
            'enable_analytics'       => 'yes',
            'enable_security_logs'   => 'yes',
            'auto_cleanup_days'      => 30,
            'custom_title'           => 'Sign in to continue',
            'custom_subtitle'        => 'Use your mobile number to log in or register to complete checkout.',
            'custom_loading_text'    => 'Loading…',
            'custom_error_text'      => 'Something went wrong.',
        );

        foreach ( $defaults as $key => $value ) {
            self::update_setting( $key, $value );
        }
    }

    /**
     * Check if database needs updating
     */
    public static function maybe_update_db() {
        $current_version = get_option( 'batci_flc_db_version' );
        if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
            self::create_tables();
        }
    }

    /**
     * Get table name
     */
    public static function get_table_name( $table ) {
        global $wpdb;
        return $wpdb->prefix . self::$tables[ $table ];
    }

    /**
     * Store OTP attempt
     */
    public static function store_otp_attempt( $phone_number, $country_code, $otp_code, $expires_at ) {
        global $wpdb;

        $table = self::get_table_name( 'otp_attempts' );
        
        $data = array(
            'phone_number' => sanitize_text_field( $phone_number ),
            'country_code' => sanitize_text_field( $country_code ),
            'otp_code'     => sanitize_text_field( $otp_code ),
            'ip_address'   => self::get_client_ip(),
            'user_agent'   => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
            'expires_at'   => $expires_at,
        );

        return $wpdb->insert( $table, $data );
    }

    /**
     * Verify OTP
     */
    public static function verify_otp( $phone_number, $otp_code ) {
        global $wpdb;

        $table = self::get_table_name( 'otp_attempts' );
        
        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE phone_number = %s 
             AND otp_code = %s 
             AND status = 'pending' 
             AND expires_at > NOW() 
             ORDER BY created_at DESC 
             LIMIT 1",
            $phone_number,
            $otp_code
        ) );

        if ( $result ) {
            // Mark as verified
            $wpdb->update(
                $table,
                array(
                    'status'      => 'verified',
                    'verified_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $result->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            // Log analytics event
            self::log_analytics_event( 'otp_verified', $phone_number, get_current_user_id() );

            return true;
        }

        // Log failed attempt
        self::log_analytics_event( 'otp_failed', $phone_number, get_current_user_id() );
        return false;
    }

    /**
     * Get OTP attempts count for phone number
     */
    public static function get_attempts_count( $phone_number, $minutes = 15 ) {
        global $wpdb;

        $table = self::get_table_name( 'otp_attempts' );
        
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE phone_number = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $phone_number,
            $minutes
        ) );
    }

    /**
     * Check if phone number is rate limited
     */
    public static function is_rate_limited( $phone_number ) {
        $max_attempts = (int) self::get_setting( 'max_otp_attempts', 5 );
        $rate_limit_minutes = (int) self::get_setting( 'rate_limit_minutes', 15 );
        
        $attempts = self::get_attempts_count( $phone_number, $rate_limit_minutes );
        
        return $attempts >= $max_attempts;
    }

    /**
     * Log analytics event
     */
    public static function log_analytics_event( $event_type, $phone_number = '', $user_id = 0, $metadata = array() ) {
        global $wpdb;

        if ( self::get_setting( 'enable_analytics', 'yes' ) !== 'yes' ) {
            return false;
        }

        $table = self::get_table_name( 'analytics' );
        
        $data = array(
            'event_type'   => sanitize_text_field( $event_type ),
            'phone_number' => sanitize_text_field( $phone_number ),
            'user_id'      => (int) $user_id,
            'ip_address'   => self::get_client_ip(),
            'user_agent'   => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
            'metadata'     => wp_json_encode( $metadata ),
        );

        return $wpdb->insert( $table, $data );
    }

    /**
     * Get analytics data
     */
    public static function get_analytics_data( $days = 30 ) {
        global $wpdb;

        $table = self::get_table_name( 'analytics' );
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, COUNT(*) as count, DATE(created_at) as date
             FROM $table 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY event_type, DATE(created_at)
             ORDER BY created_at DESC",
            $days
        ) );

        return $results;
    }

    /**
     * Update setting
     */
    public static function update_setting( $key, $value ) {
        global $wpdb;

        $table = self::get_table_name( 'settings' );
        
        return $wpdb->replace(
            $table,
            array(
                'setting_key'   => sanitize_key( $key ),
                'setting_value' => wp_json_encode( $value ),
            ),
            array( '%s', '%s' )
        );
    }

    /**
     * Get setting
     */
    public static function get_setting( $key, $default = '' ) {
        global $wpdb;

        $table = self::get_table_name( 'settings' );
        
        $value = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_key = %s",
            sanitize_key( $key )
        ) );

        if ( null === $value ) {
            return $default;
        }

        $decoded = json_decode( $value, true );
        return null === $decoded ? $value : $decoded;
    }

    /**
     * Clean up expired records
     */
    public static function cleanup_expired_records() {
        global $wpdb;

        $cleanup_days = (int) self::get_setting( 'auto_cleanup_days', 30 );

        // Clean OTP attempts
        $otp_table = self::get_table_name( 'otp_attempts' );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $otp_table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $cleanup_days
        ) );

        // Clean analytics (keep longer for reporting)
        $analytics_table = self::get_table_name( 'analytics' );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $analytics_table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $cleanup_days * 2
        ) );
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
        
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );
    }

    /**
     * Drop tables on plugin uninstall
     */
    public static function drop_tables() {
        global $wpdb;

        foreach ( self::$tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        }

        delete_option( 'batci_flc_db_version' );
    }
}

// Initialize database
Batci_FLC_Database::init();