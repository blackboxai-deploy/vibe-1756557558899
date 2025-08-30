<?php
/**
 * Plugin Name: Batci Force Login at Checkout
 * Description: Forces guests to authenticate via mobile OTP before interacting with WooCommerce checkout. After OTP verify, collects first name, last name, and email, then saves to user + Woo billing fields.
 * Version: 1.2.0
 * Author: Batci
 * Text Domain: batci-flc
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BATCI_FLC_VERSION', '1.2.0' );
define( 'BATCI_FLC_FILE', __FILE__ );
define( 'BATCI_FLC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BATCI_FLC_URL',  plugin_dir_url( __FILE__ ) );

// Load required files
require_once BATCI_FLC_PATH . 'includes/class-batci-database.php';
require_once BATCI_FLC_PATH . 'includes/class-batci-security.php';
require_once BATCI_FLC_PATH . 'includes/class-batci-frontend.php';

if ( is_admin() ) {
    require_once BATCI_FLC_PATH . 'includes/class-batci-admin.php';
}

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, 'batci_flc_activate' );
function batci_flc_activate() {
    // Create database tables
    Batci_FLC_Database::create_tables();
    
    // Schedule cleanup cron
    if ( ! wp_next_scheduled( 'batci_flc_cleanup_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'batci_flc_cleanup_cron' );
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, 'batci_flc_deactivate' );
function batci_flc_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'batci_flc_cleanup_cron' );
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin uninstall hook
 */
register_uninstall_hook( __FILE__, 'batci_flc_uninstall' );
function batci_flc_uninstall() {
    // Remove database tables and options
    Batci_FLC_Database::drop_tables();
    
    // Delete plugin options
    delete_option( 'batci_flc_db_version' );
    
    $options = array(
        'batci_flc_otp_expiry_minutes',
        'batci_flc_max_otp_attempts',
        'batci_flc_rate_limit_minutes',
        'batci_flc_modal_bg_color',
        'batci_flc_modal_text_color',
        'batci_flc_modal_accent_color',
        'batci_flc_custom_title',
        'batci_flc_custom_subtitle',
        'batci_flc_custom_loading_text',
        'batci_flc_custom_error_text',
        'batci_flc_enable_analytics',
        'batci_flc_enable_security_logs',
        'batci_flc_auto_cleanup_days',
    );
    
    foreach ( $options as $option ) {
        delete_option( $option );
    }
}

/**
 * Load plugin textdomain
 */
add_action( 'plugins_loaded', 'batci_flc_load_textdomain' );
function batci_flc_load_textdomain() {
    load_plugin_textdomain( 'batci-flc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Add plugin action links
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'batci_flc_action_links' );
function batci_flc_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=batci-flc-settings' ) . '">' . __( 'Settings', 'batci-flc' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

/**
 * Check plugin dependencies
 */
add_action( 'admin_notices', 'batci_flc_check_dependencies' );
function batci_flc_check_dependencies() {
    if ( ! function_exists( 'WC' ) ) {
        $class = 'notice notice-error';
        $message = __( 'Batci Force Login at Checkout requires WooCommerce to be installed and active.', 'batci-flc' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }
}

/**
 * Enhanced checkout restriction with new features
 */
add_action( 'wp', 'batci_flc_init_checkout_restrictions' );
function batci_flc_init_checkout_restrictions() {
    if ( ! batci_flc_should_restrict_checkout() ) {
        return;
    }
    
    // Add hooks for new frontend functionality
    add_action( 'woocommerce_before_checkout_form', 'batci_flc_render_enhanced_modal', 0 );
    add_action( 'cfw_checkout_main_container_end', 'batci_flc_render_enhanced_modal', 0 );
    add_action( 'wp_footer', 'batci_flc_render_enhanced_modal', 1 );
}

/**
 * Check if checkout should be restricted
 */
function batci_flc_should_restrict_checkout() {
    if ( is_user_logged_in() ) {
        return false;
    }
    
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
        return false;
    }
    
    if ( function_exists( 'is_wc_endpoint_url' ) ) {
        if ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
            return false;
        }
    }
    
    return apply_filters( 'batci_flc_should_restrict_checkout', true );
}

/**
 * Render enhanced modal (uses new frontend class)
 */
function batci_flc_render_enhanced_modal() {
    // This function is now handled by Batci_FLC_Frontend class
    // Keeping for backward compatibility
}

/**
 * Legacy AJAX handlers for backward compatibility
 */
add_action( 'wp_ajax_batci_login_status', 'batci_flc_ajax_login_status' );
add_action( 'wp_ajax_nopriv_batci_login_status', 'batci_flc_ajax_login_status' );
function batci_flc_ajax_login_status() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'batci_flc' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'batci-flc' ) ), 403 );
    }

    $data = array( 'logged_in' => is_user_logged_in() );

    if ( is_user_logged_in() ) {
        $uid   = get_current_user_id();
        $user  = get_userdata( $uid );
        $first = get_user_meta( $uid, 'billing_first_name', true );
        $last  = get_user_meta( $uid, 'billing_last_name', true );
        $mail  = get_user_meta( $uid, 'billing_email', true );
        if ( empty( $mail ) && $user && ! empty( $user->user_email ) ) {
            $mail = $user->user_email;
        }
        $data['prefill'] = array(
            'first_name' => $first ?: get_user_meta( $uid, 'first_name', true ),
            'last_name'  => $last  ?: get_user_meta( $uid, 'last_name', true ),
            'email'      => $mail,
        );
    }

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_batci_flc_save_profile', 'batci_flc_save_profile' );
add_action( 'wp_ajax_nopriv_batci_flc_save_profile', 'batci_flc_save_profile' );
function batci_flc_save_profile(){
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'batci_flc' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'batci-flc' ) ), 403 );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'Not logged in', 'batci-flc' ) ), 403 );
    }

    $first = isset($_POST['first_name']) ? sanitize_text_field( wp_unslash($_POST['first_name']) ) : '';
    $last  = isset($_POST['last_name'])  ? sanitize_text_field( wp_unslash($_POST['last_name']) )  : '';
    $email = isset($_POST['email'])      ? sanitize_email( wp_unslash($_POST['email']) )           : '';

    if ( empty($first) || empty($last) || empty($email) || ! is_email($email) ) {
        wp_send_json_error( array( 'message' => __( 'Please enter a valid name and email.', 'batci-flc' ) ), 400 );
    }

    $uid = get_current_user_id();
    $exists = email_exists( $email );
    if ( $exists && intval($exists) !== intval($uid) ) {
        wp_send_json_error( array( 'message' => __( 'Email already in use by another account.', 'batci-flc' ) ), 400 );
    }

    // Update WP user
    $update = wp_update_user( array( 'ID' => $uid, 'user_email' => $email, 'display_name' => trim($first.' '.$last) ) );
    if ( is_wp_error( $update ) ) {
        wp_send_json_error( array( 'message' => $update->get_error_message() ), 500 );
    }

    // Update user meta + Woo billing
    update_user_meta( $uid, 'first_name', $first );
    update_user_meta( $uid, 'last_name',  $last );
    update_user_meta( $uid, 'billing_first_name', $first );
    update_user_meta( $uid, 'billing_last_name',  $last );
    update_user_meta( $uid, 'billing_email',      $email );

    if ( function_exists('WC') && WC()->customer ) {
        $c = WC()->customer;
        $c->set_billing_first_name( $first );
        $c->set_billing_last_name(  $last );
        $c->set_billing_email(      $email );
        $c->save();
    }

    wp_send_json_success( array( 'saved' => true ) );
}

/**
 * Enhanced server-side checkout validation
 */
add_action( 'woocommerce_checkout_process', 'batci_flc_enhanced_checkout_validation', 0 );
function batci_flc_enhanced_checkout_validation() {
    $enabled = apply_filters( 'batci_flc_enforce_serverside', true );
    if ( ! $enabled || is_user_logged_in() ) {
        return;
    }
    
    // Log failed checkout attempt
    Batci_FLC_Database::log_analytics_event( 
        'checkout_blocked', 
        '', 
        0, 
        array( 'reason' => 'not_authenticated' ) 
    );
    
    wc_add_notice( 
        __( 'Please sign in with your mobile number to complete checkout.', 'batci-flc' ), 
        'error' 
    );
}

/**
 * Add WooCommerce integration hooks
 */
add_action( 'woocommerce_thankyou', 'batci_flc_track_successful_order', 10, 1 );
function batci_flc_track_successful_order( $order_id ) {
    if ( ! $order_id ) return;
    
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    
    $phone = $order->get_billing_phone();
    if ( $phone ) {
        Batci_FLC_Database::log_analytics_event( 
            'order_completed', 
            $phone, 
            $order->get_customer_id(), 
            array( 
                'order_id' => $order_id,
                'total' => $order->get_total(),
            ) 
        );
    }
}

/**
 * Add REST API endpoints for external integrations
 */
add_action( 'rest_api_init', 'batci_flc_register_rest_routes' );
function batci_flc_register_rest_routes() {
    register_rest_route( 'batci-flc/v1', '/stats', array(
        'methods' => 'GET',
        'callback' => 'batci_flc_rest_get_stats',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
}

/**
 * REST API callback for stats
 */
function batci_flc_rest_get_stats( $request ) {
    $days = $request->get_param( 'days' ) ? (int) $request->get_param( 'days' ) : 30;
    $stats = Batci_FLC_Security::get_security_stats( $days );
    
    return rest_ensure_response( $stats );
}

/**
 * Add custom capabilities for role-based access
 */
add_action( 'init', 'batci_flc_add_capabilities' );
function batci_flc_add_capabilities() {
    $role = get_role( 'administrator' );
    if ( $role ) {
        $role->add_cap( 'manage_batci_flc' );
    }
}