<?php
/**
 * Security functionality for Batci Force Login at Checkout
 *
 * @package BatciFLC
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Batci_FLC_Security class
 */
class Batci_FLC_Security {

    /**
     * Initialize security functionality
     */
    public static function init() {
        add_action( 'wp_ajax_batci_flc_send_otp', array( __CLASS__, 'ajax_send_otp' ) );
        add_action( 'wp_ajax_nopriv_batci_flc_send_otp', array( __CLASS__, 'ajax_send_otp' ) );
        add_action( 'wp_ajax_batci_flc_verify_otp', array( __CLASS__, 'ajax_verify_otp' ) );
        add_action( 'wp_ajax_nopriv_batci_flc_verify_otp', array( __CLASS__, 'ajax_verify_otp' ) );
        add_action( 'wp_ajax_batci_flc_resend_otp', array( __CLASS__, 'ajax_resend_otp' ) );
        add_action( 'wp_ajax_nopriv_batci_flc_resend_otp', array( __CLASS__, 'ajax_resend_otp' ) );
        
        // Cleanup cron job
        add_action( 'batci_flc_cleanup_cron', array( 'Batci_FLC_Database', 'cleanup_expired_records' ) );
        
        if ( ! wp_next_scheduled( 'batci_flc_cleanup_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'batci_flc_cleanup_cron' );
        }
    }

    /**
     * Generate OTP code
     */
    public static function generate_otp( $length = 6 ) {
        $otp = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $otp .= wp_rand( 0, 9 );
        }
        return $otp;
    }

    /**
     * Get OTP expiry time
     */
    public static function get_otp_expiry() {
        $minutes = (int) get_option( 'batci_flc_otp_expiry_minutes', 5 );
        return date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( $minutes * 60 ) );
    }

    /**
     * Validate phone number format
     */
    public static function validate_phone_number( $phone_number, $country_code = '' ) {
        // Remove all non-numeric characters except +
        $clean_phone = preg_replace( '/[^+\d]/', '', $phone_number );
        
        // Basic validation - should start with + and have 7-15 digits
        if ( preg_match( '/^\+\d{7,15}$/', $clean_phone ) ) {
            return $clean_phone;
        }
        
        // If country code provided, try to format
        if ( ! empty( $country_code ) && ! empty( $phone_number ) ) {
            $clean_phone = preg_replace( '/[^\d]/', '', $phone_number );
            $formatted = '+' . $country_code . $clean_phone;
            
            if ( preg_match( '/^\+\d{7,15}$/', $formatted ) ) {
                return $formatted;
            }
        }
        
        return false;
    }

    /**
     * Check if IP is rate limited
     */
    public static function is_ip_rate_limited( $ip_address ) {
        global $wpdb;
        
        $table = Batci_FLC_Database::get_table_name( 'otp_attempts' );
        $rate_limit_minutes = (int) get_option( 'batci_flc_rate_limit_minutes', 15 );
        $max_attempts = (int) get_option( 'batci_flc_max_otp_attempts', 5 );
        
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE ip_address = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $ip_address,
            $rate_limit_minutes
        ) );
        
        return $count >= $max_attempts;
    }

    /**
     * Send OTP via SMS (placeholder - integrate with SMS service)
     */
    public static function send_sms_otp( $phone_number, $otp_code, $country_code = '' ) {
        // This is a placeholder function. In a real implementation, you would:
        // 1. Integrate with an SMS service like Twilio, AWS SNS, or similar
        // 2. Format the message appropriately
        // 3. Handle API responses and errors
        
        $message = sprintf(
            __( 'Your verification code is: %s. This code will expire in %d minutes.', 'batci-flc' ),
            $otp_code,
            (int) get_option( 'batci_flc_otp_expiry_minutes', 5 )
        );
        
        // Log the attempt for development/testing
        Batci_FLC_Database::log_analytics_event( 'otp_sent', $phone_number, 0, array(
            'country_code' => $country_code,
            'message_length' => strlen( $message ),
        ) );
        
        // For development: You can uncomment this to see OTP in logs
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "Batci FLC OTP for {$phone_number}: {$otp_code}" );
        }
        
        /**
         * Filter to allow custom SMS implementation
         * 
         * @param bool   $sent         Whether the SMS was sent successfully
         * @param string $phone_number The phone number
         * @param string $otp_code     The OTP code
         * @param string $message      The SMS message
         * @param string $country_code The country code
         */
        $sent = apply_filters( 'batci_flc_send_sms', false, $phone_number, $otp_code, $message, $country_code );
        
        // Default implementation (for testing) - always return true
        if ( ! $sent ) {
            $sent = true; // Change to false in production until SMS service is integrated
        }
        
        return $sent;
    }

    /**
     * AJAX handler for sending OTP
     */
    public static function ajax_send_otp() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'batci_flc' ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid security token', 'batci-flc' ) ), 403 );
        }

        $phone_number = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';
        $country_code = isset( $_POST['country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['country_code'] ) ) : '';

        // Validate phone number
        $validated_phone = self::validate_phone_number( $phone_number, $country_code );
        if ( ! $validated_phone ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a valid phone number', 'batci-flc' ) ), 400 );
        }

        // Check rate limiting by phone number
        if ( Batci_FLC_Database::is_rate_limited( $validated_phone ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Too many attempts. Please try again later.', 'batci-flc' ),
                'rate_limited' => true,
            ), 429 );
        }

        // Check rate limiting by IP
        $ip_address = self::get_client_ip();
        if ( self::is_ip_rate_limited( $ip_address ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Too many requests from your location. Please try again later.', 'batci-flc' ),
                'rate_limited' => true,
            ), 429 );
        }

        // Generate OTP
        $otp_code = self::generate_otp();
        $expires_at = self::get_otp_expiry();

        // Store OTP attempt
        $stored = Batci_FLC_Database::store_otp_attempt( $validated_phone, $country_code, $otp_code, $expires_at );
        if ( ! $stored ) {
            wp_send_json_error( array( 'message' => __( 'Failed to generate OTP. Please try again.', 'batci-flc' ) ), 500 );
        }

        // Send SMS
        $sent = self::send_sms_otp( $validated_phone, $otp_code, $country_code );
        if ( ! $sent ) {
            wp_send_json_error( array( 'message' => __( 'Failed to send OTP. Please try again.', 'batci-flc' ) ), 500 );
        }

        // Log analytics
        Batci_FLC_Database::log_analytics_event( 'otp_requested', $validated_phone, 0, array(
            'country_code' => $country_code,
            'ip_address' => $ip_address,
        ) );

        wp_send_json_success( array(
            'message' => __( 'OTP sent successfully', 'batci-flc' ),
            'phone_number' => $validated_phone,
            'expires_in' => (int) get_option( 'batci_flc_otp_expiry_minutes', 5 ) * 60,
        ) );
    }

    /**
     * AJAX handler for verifying OTP
     */
    public static function ajax_verify_otp() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'batci_flc' ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid security token', 'batci-flc' ) ), 403 );
        }

        $phone_number = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';
        $otp_code = isset( $_POST['otp_code'] ) ? sanitize_text_field( wp_unslash( $_POST['otp_code'] ) ) : '';

        if ( empty( $phone_number ) || empty( $otp_code ) ) {
            wp_send_json_error( array( 'message' => __( 'Phone number and OTP code are required', 'batci-flc' ) ), 400 );
        }

        // Verify OTP
        $verified = Batci_FLC_Database::verify_otp( $phone_number, $otp_code );
        if ( ! $verified ) {
            wp_send_json_error( array( 
                'message' => __( 'Invalid or expired OTP code', 'batci-flc' ),
                'invalid_otp' => true,
            ), 400 );
        }

        // Create or login user (this would integrate with your user management system)
        $user_data = self::handle_user_authentication( $phone_number );
        if ( is_wp_error( $user_data ) ) {
            wp_send_json_error( array( 'message' => $user_data->get_error_message() ), 500 );
        }

        wp_send_json_success( array(
            'message' => __( 'OTP verified successfully', 'batci-flc' ),
            'user_id' => $user_data['user_id'],
            'redirect_to_profile' => $user_data['needs_profile'],
        ) );
    }

    /**
     * AJAX handler for resending OTP
     */
    public static function ajax_resend_otp() {
        // Use the same logic as send_otp but with additional checks
        self::ajax_send_otp();
    }

    /**
     * Handle user authentication after OTP verification
     */
    private static function handle_user_authentication( $phone_number ) {
        // Check if user exists with this phone number
        $users = get_users( array(
            'meta_query' => array(
                array(
                    'key' => 'billing_phone',
                    'value' => $phone_number,
                    'compare' => '='
                )
            ),
            'number' => 1,
        ) );

        if ( ! empty( $users ) ) {
            // Existing user - log them in
            $user = $users[0];
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID );
            
            // Check if profile is complete
            $needs_profile = empty( get_user_meta( $user->ID, 'first_name', true ) ) ||
                           empty( get_user_meta( $user->ID, 'last_name', true ) ) ||
                           empty( get_user_meta( $user->ID, 'billing_email', true ) );

            return array(
                'user_id' => $user->ID,
                'needs_profile' => $needs_profile,
            );
        } else {
            // New user - create account
            $username = 'user_' . sanitize_user( str_replace( '+', '', $phone_number ) );
            $email = ''; // Will be collected in profile step
            
            $user_id = wp_create_user( $username, wp_generate_password(), $email );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }

            // Set phone number
            update_user_meta( $user_id, 'billing_phone', $phone_number );
            
            // Log them in
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id );

            return array(
                'user_id' => $user_id,
                'needs_profile' => true,
            );
        }
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
     * Clean up expired OTPs
     */
    public static function cleanup_expired_otps() {
        global $wpdb;
        
        $table = Batci_FLC_Database::get_table_name( 'otp_attempts' );
        
        // Mark expired OTPs
        $wpdb->query(
            "UPDATE $table 
             SET status = 'expired' 
             WHERE status = 'pending' 
             AND expires_at < NOW()"
        );
    }

    /**
     * Get security statistics
     */
    public static function get_security_stats( $days = 30 ) {
        global $wpdb;
        
        $table = Batci_FLC_Database::get_table_name( 'otp_attempts' );
        
        $stats = array();
        
        // Total attempts
        $stats['total_attempts'] = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
        
        // Successful verifications
        $stats['successful_verifications'] = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'verified' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
        
        // Failed attempts
        $stats['failed_attempts'] = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
        
        // Success rate
        $stats['success_rate'] = $stats['total_attempts'] > 0 
            ? round( ( $stats['successful_verifications'] / $stats['total_attempts'] ) * 100, 2 )
            : 0;
        
        return $stats;
    }
}

// Initialize security
Batci_FLC_Security::init();