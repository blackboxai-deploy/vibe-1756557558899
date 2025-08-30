<?php
/**
 * Admin functionality for Batci Force Login at Checkout
 *
 * @package BatciFLC
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Batci_FLC_Admin class
 */
class Batci_FLC_Admin {

    /**
     * Initialize admin functionality
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_batci_flc_get_analytics', array( __CLASS__, 'ajax_get_analytics' ) );
        add_action( 'wp_ajax_batci_flc_export_data', array( __CLASS__, 'ajax_export_data' ) );
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_options_page(
            __( 'Batci Force Login at Checkout', 'batci-flc' ),
            __( 'Batci FLC', 'batci-flc' ),
            'manage_options',
            'batci-flc-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        // Security Settings
        register_setting( 'batci_flc_security', 'batci_flc_otp_expiry_minutes', array(
            'type'              => 'integer',
            'default'           => 5,
            'sanitize_callback' => array( __CLASS__, 'sanitize_positive_integer' ),
        ) );

        register_setting( 'batci_flc_security', 'batci_flc_max_otp_attempts', array(
            'type'              => 'integer',
            'default'           => 5,
            'sanitize_callback' => array( __CLASS__, 'sanitize_positive_integer' ),
        ) );

        register_setting( 'batci_flc_security', 'batci_flc_rate_limit_minutes', array(
            'type'              => 'integer',
            'default'           => 15,
            'sanitize_callback' => array( __CLASS__, 'sanitize_positive_integer' ),
        ) );

        // Appearance Settings
        register_setting( 'batci_flc_appearance', 'batci_flc_modal_bg_color', array(
            'type'              => 'string',
            'default'           => '#ffffff',
            'sanitize_callback' => 'sanitize_hex_color',
        ) );

        register_setting( 'batci_flc_appearance', 'batci_flc_modal_text_color', array(
            'type'              => 'string',
            'default'           => '#333333',
            'sanitize_callback' => 'sanitize_hex_color',
        ) );

        register_setting( 'batci_flc_appearance', 'batci_flc_modal_accent_color', array(
            'type'              => 'string',
            'default'           => '#0073aa',
            'sanitize_callback' => 'sanitize_hex_color',
        ) );

        // Message Settings
        register_setting( 'batci_flc_messages', 'batci_flc_custom_title', array(
            'type'              => 'string',
            'default'           => 'Sign in to continue',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'batci_flc_messages', 'batci_flc_custom_subtitle', array(
            'type'              => 'string',
            'default'           => 'Use your mobile number to log in or register to complete checkout.',
            'sanitize_callback' => 'sanitize_textarea_field',
        ) );

        register_setting( 'batci_flc_messages', 'batci_flc_custom_loading_text', array(
            'type'              => 'string',
            'default'           => 'Loading…',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'batci_flc_messages', 'batci_flc_custom_error_text', array(
            'type'              => 'string',
            'default'           => 'Something went wrong.',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        // Analytics Settings
        register_setting( 'batci_flc_analytics', 'batci_flc_enable_analytics', array(
            'type'    => 'boolean',
            'default' => true,
        ) );

        register_setting( 'batci_flc_analytics', 'batci_flc_enable_security_logs', array(
            'type'    => 'boolean',
            'default' => true,
        ) );

        register_setting( 'batci_flc_analytics', 'batci_flc_auto_cleanup_days', array(
            'type'              => 'integer',
            'default'           => 30,
            'sanitize_callback' => array( __CLASS__, 'sanitize_positive_integer' ),
        ) );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_admin_scripts( $hook ) {
        if ( 'settings_page_batci-flc-settings' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true );

        wp_enqueue_style(
            'batci-flc-admin',
            BATCI_FLC_URL . 'assets/css/admin.css',
            array( 'wp-color-picker' ),
            BATCI_FLC_VERSION
        );

        wp_enqueue_script(
            'batci-flc-admin',
            BATCI_FLC_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-color-picker', 'chart-js' ),
            BATCI_FLC_VERSION,
            true
        );

        wp_localize_script( 'batci-flc-admin', 'BatciFLCAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'batci_flc_admin' ),
            'strings'  => array(
                'loading'           => __( 'Loading...', 'batci-flc' ),
                'error'             => __( 'Error loading data', 'batci-flc' ),
                'export_success'    => __( 'Data exported successfully', 'batci-flc' ),
                'confirm_reset'     => __( 'Are you sure you want to reset all settings?', 'batci-flc' ),
                'confirm_cleanup'   => __( 'Are you sure you want to clean up old data?', 'batci-flc' ),
            ),
        ) );
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'security';
        $tabs = array(
            'security'    => __( 'Security', 'batci-flc' ),
            'appearance'  => __( 'Appearance', 'batci-flc' ),
            'messages'    => __( 'Messages', 'batci-flc' ),
            'analytics'   => __( 'Analytics', 'batci-flc' ),
            'dashboard'   => __( 'Dashboard', 'batci-flc' ),
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                    <a href="?page=batci-flc-settings&tab=<?php echo esc_attr( $tab_key ); ?>" 
                       class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="batci-flc-admin-content">
                <?php
                switch ( $active_tab ) {
                    case 'security':
                        self::render_security_tab();
                        break;
                    case 'appearance':
                        self::render_appearance_tab();
                        break;
                    case 'messages':
                        self::render_messages_tab();
                        break;
                    case 'analytics':
                        self::render_analytics_tab();
                        break;
                    case 'dashboard':
                        self::render_dashboard_tab();
                        break;
                    default:
                        self::render_security_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render security settings tab
     */
    private static function render_security_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'batci_flc_security' );
            do_settings_sections( 'batci_flc_security' );
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="batci_flc_otp_expiry_minutes">
                            <?php esc_html_e( 'OTP Expiry Time (minutes)', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" id="batci_flc_otp_expiry_minutes" 
                               name="batci_flc_otp_expiry_minutes" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_otp_expiry_minutes', 5 ) ); ?>" 
                               min="1" max="60" step="1" />
                        <p class="description">
                            <?php esc_html_e( 'How long the OTP remains valid (1-60 minutes)', 'batci-flc' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="batci_flc_max_otp_attempts">
                            <?php esc_html_e( 'Maximum OTP Attempts', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" id="batci_flc_max_otp_attempts" 
                               name="batci_flc_max_otp_attempts" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_max_otp_attempts', 5 ) ); ?>" 
                               min="1" max="20" step="1" />
                        <p class="description">
                            <?php esc_html_e( 'Maximum OTP requests allowed per phone number', 'batci-flc' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="batci_flc_rate_limit_minutes">
                            <?php esc_html_e( 'Rate Limit Window (minutes)', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" id="batci_flc_rate_limit_minutes" 
                               name="batci_flc_rate_limit_minutes" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_rate_limit_minutes', 15 ) ); ?>" 
                               min="5" max="120" step="1" />
                        <p class="description">
                            <?php esc_html_e( 'Time window for counting OTP attempts', 'batci-flc' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render appearance settings tab
     */
    private static function render_appearance_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'batci_flc_appearance' );
            do_settings_sections( 'batci_flc_appearance' );
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="batci_flc_modal_bg_color">
                            <?php esc_html_e( 'Modal Background Color', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="batci_flc_modal_bg_color" 
                               name="batci_flc_modal_bg_color" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_modal_bg_color', '#ffffff' ) ); ?>" 
                               class="batci-color-picker" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="batci_flc_modal_text_color">
                            <?php esc_html_e( 'Modal Text Color', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="batci_flc_modal_text_color" 
                               name="batci_flc_modal_text_color" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_modal_text_color', '#333333' ) ); ?>" 
                               class="batci-color-picker" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="batci_flc_modal_accent_color">
                            <?php esc_html_e( 'Modal Accent Color', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="batci_flc_modal_accent_color" 
                               name="batci_flc_modal_accent_color" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_modal_accent_color', '#0073aa' ) ); ?>" 
                               class="batci-color-picker" />
                    </td>
                </tr>
            </table>
            
            <div class="batci-flc-preview">
                <h3><?php esc_html_e( 'Preview', 'batci-flc' ); ?></h3>
                <div id="batci-flc-modal-preview" class="batci-flc-modal-preview">
                    <div class="preview-modal">
                        <div class="preview-header">
                            <div class="preview-brand">BATCI</div>
                            <h4><?php echo esc_html( get_option( 'batci_flc_custom_title', 'Sign in to continue' ) ); ?></h4>
                            <p><?php echo esc_html( get_option( 'batci_flc_custom_subtitle', 'Use your mobile number to log in or register to complete checkout.' ) ); ?></p>
                        </div>
                        <div class="preview-body">
                            <div class="preview-progress-bar">
                                <div class="preview-progress-fill"></div>
                            </div>
                            <div class="preview-form">
                                <input type="text" placeholder="+1 (555) 123-4567" disabled>
                                <button type="button" disabled>Send OTP</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render messages settings tab
     */
    private static function render_messages_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'batci_flc_messages' );
            do_settings_sections( 'batci_flc_messages' );
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="batci_flc_custom_title">
                            <?php esc_html_e( 'Modal Title', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="batci_flc_custom_title" 
                               name="batci_flc_custom_title" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_custom_title', 'Sign in to continue' ) ); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="batci_flc_custom_subtitle">
                            <?php esc_html_e( 'Modal Subtitle', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <textarea id="batci_flc_custom_subtitle" 
                                  name="batci_flc_custom_subtitle" 
                                  rows="3" 
                                  class="large-text"><?php echo esc_textarea( get_option( 'batci_flc_custom_subtitle', 'Use your mobile number to log in or register to complete checkout.' ) ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="batci_flc_custom_loading_text">
                            <?php esc_html_e( 'Loading Text', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="batci_flc_custom_loading_text" 
                               name="batci_flc_custom_loading_text" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_custom_loading_text', 'Loading…' ) ); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="batci_flc_custom_error_text">
                            <?php esc_html_e( 'Error Text', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="batci_flc_custom_error_text" 
                               name="batci_flc_custom_error_text" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_custom_error_text', 'Something went wrong.' ) ); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render analytics settings tab
     */
    private static function render_analytics_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'batci_flc_analytics' );
            do_settings_sections( 'batci_flc_analytics' );
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Analytics', 'batci-flc' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="batci_flc_enable_analytics" value="1" 
                                   <?php checked( get_option( 'batci_flc_enable_analytics', true ) ); ?> />
                            <?php esc_html_e( 'Track OTP usage and conversion metrics', 'batci-flc' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Security Logs', 'batci-flc' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="batci_flc_enable_security_logs" value="1" 
                                   <?php checked( get_option( 'batci_flc_enable_security_logs', true ) ); ?> />
                            <?php esc_html_e( 'Log security events and failed attempts', 'batci-flc' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="batci_flc_auto_cleanup_days">
                            <?php esc_html_e( 'Auto Cleanup (days)', 'batci-flc' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" id="batci_flc_auto_cleanup_days" 
                               name="batci_flc_auto_cleanup_days" 
                               value="<?php echo esc_attr( get_option( 'batci_flc_auto_cleanup_days', 30 ) ); ?>" 
                               min="7" max="365" step="1" />
                        <p class="description">
                            <?php esc_html_e( 'Automatically delete old records after this many days', 'batci-flc' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <div class="batci-flc-actions">
                <button type="button" id="batci-cleanup-data" class="button button-secondary">
                    <?php esc_html_e( 'Clean Up Old Data Now', 'batci-flc' ); ?>
                </button>
                <button type="button" id="batci-export-data" class="button button-secondary">
                    <?php esc_html_e( 'Export Analytics Data', 'batci-flc' ); ?>
                </button>
            </div>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render dashboard tab
     */
    private static function render_dashboard_tab() {
        ?>
        <div class="batci-flc-dashboard">
            <div class="batci-flc-stats-grid">
                <div class="batci-stat-card">
                    <h3><?php esc_html_e( 'Today\'s OTP Requests', 'batci-flc' ); ?></h3>
                    <div class="stat-number" id="today-otp-requests">-</div>
                </div>
                <div class="batci-stat-card">
                    <h3><?php esc_html_e( 'Success Rate', 'batci-flc' ); ?></h3>
                    <div class="stat-number" id="success-rate">-</div>
                </div>
                <div class="batci-stat-card">
                    <h3><?php esc_html_e( 'Total Users Authenticated', 'batci-flc' ); ?></h3>
                    <div class="stat-number" id="total-authenticated">-</div>
                </div>
                <div class="batci-stat-card">
                    <h3><?php esc_html_e( 'Failed Attempts', 'batci-flc' ); ?></h3>
                    <div class="stat-number" id="failed-attempts">-</div>
                </div>
            </div>
            
            <div class="batci-flc-charts">
                <div class="chart-container">
                    <h3><?php esc_html_e( 'OTP Usage Over Time', 'batci-flc' ); ?></h3>
                    <canvas id="otp-usage-chart" width="400" height="200"></canvas>
                </div>
                <div class="chart-container">
                    <h3><?php esc_html_e( 'Success vs Failed Attempts', 'batci-flc' ); ?></h3>
                    <canvas id="success-rate-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for analytics data
     */
    public static function ajax_get_analytics() {
        check_ajax_referer( 'batci_flc_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }

        $days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 30;
        $data = Batci_FLC_Database::get_analytics_data( $days );

        wp_send_json_success( $data );
    }

    /**
     * AJAX handler for data export
     */
    public static function ajax_export_data() {
        check_ajax_referer( 'batci_flc_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }

        $data = Batci_FLC_Database::get_analytics_data( 365 );
        
        // Prepare CSV data
        $csv_data = array();
        $csv_data[] = array( 'Date', 'Event Type', 'Count' );
        
        foreach ( $data as $row ) {
            $csv_data[] = array( $row->date, $row->event_type, $row->count );
        }

        wp_send_json_success( array( 'csv_data' => $csv_data ) );
    }

    /**
     * Sanitize positive integer
     */
    public static function sanitize_positive_integer( $value ) {
        $value = (int) $value;
        return max( 1, $value );
    }
}

// Initialize admin
if ( is_admin() ) {
    Batci_FLC_Admin::init();
}