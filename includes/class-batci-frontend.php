<?php
/**
 * Frontend functionality for Batci Force Login at Checkout
 *
 * @package BatciFLC
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Batci_FLC_Frontend class
 */
class Batci_FLC_Frontend {

    /**
     * Initialize frontend functionality
     */
    public static function init() {
        add_action( 'wp', array( __CLASS__, 'init_checkout_hooks' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
        add_action( 'wp_footer', array( __CLASS__, 'add_custom_css' ) );
    }

    /**
     * Initialize checkout hooks
     */
    public static function init_checkout_hooks() {
        if ( self::should_restrict_checkout() ) {
            add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_modal' ), 0 );
            add_action( 'cfw_checkout_main_container_end', array( __CLASS__, 'render_modal' ), 0 );
            add_action( 'wp_footer', array( __CLASS__, 'render_modal' ), 1 );
        }
    }

    /**
     * Check if checkout should be restricted
     */
    public static function should_restrict_checkout() {
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
        
        return true;
    }

    /**
     * Enqueue assets only when needed
     */
    public static function maybe_enqueue_assets() {
        if ( ! self::should_restrict_checkout() ) {
            return;
        }

        // Main CSS
        wp_enqueue_style(
            'batci-flc-frontend',
            BATCI_FLC_URL . 'assets/css/batci-flc.css',
            array(),
            BATCI_FLC_VERSION
        );

        // International Tel Input CSS
        wp_enqueue_style(
            'intl-tel-input',
            BATCI_FLC_URL . 'assets/js/intl-tel-input/css/intlTelInput.css',
            array(),
            '17.0.19'
        );

        // International Tel Input JS
        wp_enqueue_script(
            'intl-tel-input',
            BATCI_FLC_URL . 'assets/js/intl-tel-input/js/intlTelInput.min.js',
            array(),
            '17.0.19',
            true
        );

        // Main frontend JS
        wp_enqueue_script(
            'batci-flc-frontend',
            BATCI_FLC_URL . 'assets/js/batci-flc.js',
            array( 'jquery', 'intl-tel-input' ),
            BATCI_FLC_VERSION,
            true
        );

        // Localize script
        wp_localize_script( 'batci-flc-frontend', 'BatciFLC', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'batci_flc' ),
            'poll_interval' => 2000,
            'rtl'           => is_rtl(),
            'strings'       => self::get_localized_strings(),
            'settings'      => self::get_frontend_settings(),
            'lock_selectors' => array(
                '#place_order',
                '.place-order button',
                '.cfw-place-order',
                'button[name="woocommerce_checkout_place_order"]',
                '.wc-block-checkout__actions button',
                '.wp-block-woocommerce-checkout-actions-block button',
            ),
        ) );
    }

    /**
     * Get localized strings
     */
    private static function get_localized_strings() {
        return array(
            'title'              => get_option( 'batci_flc_custom_title', __( 'Sign in to continue', 'batci-flc' ) ),
            'subtitle'           => get_option( 'batci_flc_custom_subtitle', __( 'Use your mobile number to log in or register to complete checkout.', 'batci-flc' ) ),
            'loading'            => get_option( 'batci_flc_custom_loading_text', __( 'Loading…', 'batci-flc' ) ),
            'error'              => get_option( 'batci_flc_custom_error_text', __( 'Something went wrong.', 'batci-flc' ) ),
            'phone_label'        => __( 'Phone Number', 'batci-flc' ),
            'phone_placeholder'  => __( 'Enter your phone number', 'batci-flc' ),
            'send_otp'           => __( 'Send OTP', 'batci-flc' ),
            'sending_otp'        => __( 'Sending...', 'batci-flc' ),
            'otp_label'          => __( 'Verification Code', 'batci-flc' ),
            'otp_placeholder'    => __( 'Enter 6-digit code', 'batci-flc' ),
            'verify_otp'         => __( 'Verify Code', 'batci-flc' ),
            'verifying'          => __( 'Verifying...', 'batci-flc' ),
            'resend_otp'         => __( 'Resend Code', 'batci-flc' ),
            'back_to_phone'      => __( 'Back to phone', 'batci-flc' ),
            'first_name'         => __( 'First Name', 'batci-flc' ),
            'last_name'          => __( 'Last Name', 'batci-flc' ),
            'email'              => __( 'Email Address', 'batci-flc' ),
            'save_continue'      => __( 'Save & Continue', 'batci-flc' ),
            'saving'             => __( 'Saving...', 'batci-flc' ),
            'invalid_phone'      => __( 'Please enter a valid phone number', 'batci-flc' ),
            'invalid_otp'        => __( 'Please enter a valid 6-digit code', 'batci-flc' ),
            'invalid_profile'    => __( 'Please fill in all required fields', 'batci-flc' ),
            'rate_limited'       => __( 'Too many attempts. Please try again later.', 'batci-flc' ),
            'otp_sent'           => __( 'Verification code sent to your phone', 'batci-flc' ),
            'otp_expired'        => __( 'Code has expired. Please request a new one.', 'batci-flc' ),
            'step_phone'         => __( 'Enter Phone Number', 'batci-flc' ),
            'step_verify'        => __( 'Verify Code', 'batci-flc' ),
            'step_profile'       => __( 'Complete Profile', 'batci-flc' ),
        );
    }

    /**
     * Get frontend settings
     */
    private static function get_frontend_settings() {
        return array(
            'otp_expiry_minutes' => (int) get_option( 'batci_flc_otp_expiry_minutes', 5 ),
            'enable_country_picker' => true,
            'default_country' => 'us',
            'preferred_countries' => array( 'us', 'ca', 'gb', 'au' ),
        );
    }

    /**
     * Render the authentication modal
     */
    public static function render_modal() {
        static $modal_rendered = false;
        if ( $modal_rendered ) {
            return;
        }
        $modal_rendered = true;

        $dir = is_rtl() ? 'rtl' : 'ltr';
        $strings = self::get_localized_strings();
        ?>
        <div id="batci-login-overlay" class="batci-flc-overlay" dir="<?php echo esc_attr( $dir ); ?>" aria-hidden="true" style="display:none;">
            <div class="batci-flc-modal" role="dialog" aria-modal="true" aria-labelledby="batci-flc-title">
                <div class="batci-flc-modal-content">
                    
                    <!-- Header -->
                    <div class="batci-flc-header">
                        <div class="batci-flc-brand">BATCI</div>
                        <h2 id="batci-flc-title"><?php echo esc_html( $strings['title'] ); ?></h2>
                        <p class="batci-flc-subtitle"><?php echo esc_html( $strings['subtitle'] ); ?></p>
                    </div>

                    <!-- Progress Bar -->
                    <div class="batci-flc-progress">
                        <div class="batci-progress-bar">
                            <div class="batci-progress-fill" id="batci-progress-fill"></div>
                        </div>
                        <div class="batci-progress-steps">
                            <div class="batci-step active" data-step="phone">
                                <span class="step-number">1</span>
                                <span class="step-label"><?php echo esc_html( $strings['step_phone'] ); ?></span>
                            </div>
                            <div class="batci-step" data-step="verify">
                                <span class="step-number">2</span>
                                <span class="step-label"><?php echo esc_html( $strings['step_verify'] ); ?></span>
                            </div>
                            <div class="batci-step" data-step="profile">
                                <span class="step-number">3</span>
                                <span class="step-label"><?php echo esc_html( $strings['step_profile'] ); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="batci-flc-body">
                        
                        <!-- Phone Number Step -->
                        <div id="batci-step-phone" class="batci-step-content active">
                            <div class="batci-flc-form">
                                <div class="batci-form-group">
                                    <label for="batci-phone-input"><?php echo esc_html( $strings['phone_label'] ); ?></label>
                                    <input type="tel" id="batci-phone-input" class="batci-phone-input" 
                                           placeholder="<?php echo esc_attr( $strings['phone_placeholder'] ); ?>" 
                                           autocomplete="tel">
                                </div>
                                <div class="batci-form-group">
                                    <button type="button" id="batci-send-otp" class="batci-btn batci-btn-primary">
                                        <span class="btn-text"><?php echo esc_html( $strings['send_otp'] ); ?></span>
                                        <span class="btn-spinner" style="display:none;"></span>
                                    </button>
                                </div>
                                <div id="batci-phone-error" class="batci-error-message" style="display:none;"></div>
                            </div>
                        </div>

                        <!-- OTP Verification Step -->
                        <div id="batci-step-verify" class="batci-step-content">
                            <div class="batci-flc-form">
                                <div class="batci-form-group">
                                    <label for="batci-otp-input"><?php echo esc_html( $strings['otp_label'] ); ?></label>
                                    <input type="text" id="batci-otp-input" class="batci-otp-input" 
                                           placeholder="<?php echo esc_attr( $strings['otp_placeholder'] ); ?>" 
                                           maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code">
                                </div>
                                <div class="batci-form-group">
                                    <button type="button" id="batci-verify-otp" class="batci-btn batci-btn-primary">
                                        <span class="btn-text"><?php echo esc_html( $strings['verify_otp'] ); ?></span>
                                        <span class="btn-spinner" style="display:none;"></span>
                                    </button>
                                </div>
                                <div class="batci-form-actions">
                                    <button type="button" id="batci-resend-otp" class="batci-btn-link">
                                        <?php echo esc_html( $strings['resend_otp'] ); ?>
                                    </button>
                                    <button type="button" id="batci-back-to-phone" class="batci-btn-link">
                                        <?php echo esc_html( $strings['back_to_phone'] ); ?>
                                    </button>
                                </div>
                                <div id="batci-otp-error" class="batci-error-message" style="display:none;"></div>
                                <div id="batci-otp-success" class="batci-success-message" style="display:none;"></div>
                            </div>
                        </div>

                        <!-- Profile Completion Step -->
                        <div id="batci-step-profile" class="batci-step-content">
                            <div class="batci-flc-form">
                                <div class="batci-form-row">
                                    <div class="batci-form-group batci-form-group-half">
                                        <label for="batci-first-name"><?php echo esc_html( $strings['first_name'] ); ?></label>
                                        <input type="text" id="batci-first-name" class="batci-input" 
                                               autocomplete="given-name" required>
                                    </div>
                                    <div class="batci-form-group batci-form-group-half">
                                        <label for="batci-last-name"><?php echo esc_html( $strings['last_name'] ); ?></label>
                                        <input type="text" id="batci-last-name" class="batci-input" 
                                               autocomplete="family-name" required>
                                    </div>
                                </div>
                                <div class="batci-form-group">
                                    <label for="batci-email"><?php echo esc_html( $strings['email'] ); ?></label>
                                    <input type="email" id="batci-email" class="batci-input" 
                                           autocomplete="email" required>
                                </div>
                                <div class="batci-form-group">
                                    <button type="button" id="batci-save-profile" class="batci-btn batci-btn-primary">
                                        <span class="btn-text"><?php echo esc_html( $strings['save_continue'] ); ?></span>
                                        <span class="btn-spinner" style="display:none;"></span>
                                    </button>
                                </div>
                                <div id="batci-profile-error" class="batci-error-message" style="display:none;"></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <?php

        // Add inline JavaScript for modal functionality
        self::add_inline_javascript();
    }

    /**
     * Add inline JavaScript for modal functionality
     */
    private static function add_inline_javascript() {
        ?>
        <script>
        (function($) {
            'use strict';

            var BatciModal = {
                currentStep: 'phone',
                phoneNumber: '',
                countryCode: '',
                iti: null,
                
                init: function() {
                    this.initPhoneInput();
                    this.bindEvents();
                    this.lockCheckout();
                    this.showModal();
                },

                initPhoneInput: function() {
                    var input = document.querySelector('#batci-phone-input');
                    if (input && typeof intlTelInput !== 'undefined') {
                        this.iti = intlTelInput(input, {
                            initialCountry: BatciFLC.settings.default_country || 'us',
                            preferredCountries: BatciFLC.settings.preferred_countries || ['us', 'ca', 'gb'],
                            utilsScript: BatciFLC.plugin_url + 'assets/js/intl-tel-input/js/utils.js',
                            autoPlaceholder: 'aggressive',
                            formatOnDisplay: true,
                            nationalMode: false,
                            placeholderNumberType: 'MOBILE'
                        });
                    }
                },

                bindEvents: function() {
                    $(document).on('click', '#batci-send-otp', this.sendOTP.bind(this));
                    $(document).on('click', '#batci-verify-otp', this.verifyOTP.bind(this));
                    $(document).on('click', '#batci-resend-otp', this.resendOTP.bind(this));
                    $(document).on('click', '#batci-back-to-phone', this.backToPhone.bind(this));
                    $(document).on('click', '#batci-save-profile', this.saveProfile.bind(this));
                    
                    // Enter key handling
                    $(document).on('keypress', '#batci-phone-input', function(e) {
                        if (e.which === 13) $('#batci-send-otp').click();
                    });
                    $(document).on('keypress', '#batci-otp-input', function(e) {
                        if (e.which === 13) $('#batci-verify-otp').click();
                    });
                    
                    // OTP input formatting
                    $(document).on('input', '#batci-otp-input', function() {
                        this.value = this.value.replace(/[^0-9]/g, '');
                    });
                },

                showModal: function() {
                    $('#batci-login-overlay').css({
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center'
                    }).hide().fadeIn(200);
                    $('body').addClass('batci-modal-open').css('overflow', 'hidden');
                },

                hideModal: function() {
                    $('#batci-login-overlay').fadeOut(200, function() {
                        $(this).remove();
                    });
                    $('body').removeClass('batci-modal-open').css('overflow', '');
                },

                lockCheckout: function() {
                    var selectors = BatciFLC.lock_selectors || [];
                    selectors.forEach(function(selector) {
                        $(selector).prop('disabled', true).addClass('batci-disabled');
                    });
                    
                    // Prevent form submission
                    $(document).on('submit.batci', 'form.checkout', function(e) {
                        if (!window.BatciFLCAuthenticated) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            return false;
                        }
                    });
                },

                unlockCheckout: function() {
                    var selectors = BatciFLC.lock_selectors || [];
                    selectors.forEach(function(selector) {
                        $(selector).prop('disabled', false).removeClass('batci-disabled');
                    });
                    $(document).off('.batci');
                    window.BatciFLCAuthenticated = true;
                },

                setStep: function(step) {
                    // Hide all step contents
                    $('.batci-step-content').removeClass('active');
                    $('.batci-step').removeClass('active completed');
                    
                    // Show current step
                    $('#batci-step-' + step).addClass('active');
                    $('.batci-step[data-step="' + step + '"]').addClass('active');
                    
                    // Mark previous steps as completed
                    var steps = ['phone', 'verify', 'profile'];
                    var currentIndex = steps.indexOf(step);
                    for (var i = 0; i < currentIndex; i++) {
                        $('.batci-step[data-step="' + steps[i] + '"]').addClass('completed');
                    }
                    
                    // Update progress bar
                    var progress = ((currentIndex + 1) / steps.length) * 100;
                    $('#batci-progress-fill').css('width', progress + '%');
                    
                    this.currentStep = step;
                },

                sendOTP: function() {
                    var $btn = $('#batci-send-otp');
                    var phoneNumber = this.iti ? this.iti.getNumber() : $('#batci-phone-input').val();
                    var countryData = this.iti ? this.iti.getSelectedCountryData() : {};
                    
                    if (!phoneNumber || (this.iti && !this.iti.isValidNumber())) {
                        this.showError('phone', BatciFLC.strings.invalid_phone);
                        return;
                    }
                    
                    this.setButtonLoading($btn, true);
                    this.hideError('phone');
                    
                    $.ajax({
                        url: BatciFLC.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'batci_flc_send_otp',
                            nonce: BatciFLC.nonce,
                            phone_number: phoneNumber,
                            country_code: countryData.dialCode || ''
                        },
                        success: function(response) {
                            if (response.success) {
                                this.phoneNumber = phoneNumber;
                                this.countryCode = countryData.dialCode || '';
                                this.setStep('verify');
                                this.showSuccess('otp', BatciFLC.strings.otp_sent);
                                $('#batci-otp-input').focus();
                            } else {
                                this.showError('phone', response.data.message || BatciFLC.strings.error);
                            }
                        }.bind(this),
                        error: function() {
                            this.showError('phone', BatciFLC.strings.error);
                        }.bind(this),
                        complete: function() {
                            this.setButtonLoading($btn, false);
                        }.bind(this)
                    });
                },

                verifyOTP: function() {
                    var $btn = $('#batci-verify-otp');
                    var otpCode = $('#batci-otp-input').val();
                    
                    if (!otpCode || otpCode.length !== 6) {
                        this.showError('otp', BatciFLC.strings.invalid_otp);
                        return;
                    }
                    
                    this.setButtonLoading($btn, true);
                    this.hideError('otp');
                    
                    $.ajax({
                        url: BatciFLC.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'batci_flc_verify_otp',
                            nonce: BatciFLC.nonce,
                            phone_number: this.phoneNumber,
                            otp_code: otpCode
                        },
                        success: function(response) {
                            if (response.success) {
                                this.setStep('profile');
                                $('#batci-first-name').focus();
                                // Pre-fill profile if available
                                if (response.data.prefill) {
                                    this.prefillProfile(response.data.prefill);
                                }
                            } else {
                                this.showError('otp', response.data.message || BatciFLC.strings.error);
                            }
                        }.bind(this),
                        error: function() {
                            this.showError('otp', BatciFLC.strings.error);
                        }.bind(this),
                        complete: function() {
                            this.setButtonLoading($btn, false);
                        }.bind(this)
                    });
                },

                resendOTP: function() {
                    this.sendOTP();
                },

                backToPhone: function() {
                    this.setStep('phone');
                    $('#batci-phone-input').focus();
                },

                saveProfile: function() {
                    var $btn = $('#batci-save-profile');
                    var firstName = $('#batci-first-name').val().trim();
                    var lastName = $('#batci-last-name').val().trim();
                    var email = $('#batci-email').val().trim();
                    
                    if (!firstName || !lastName || !email || !this.isValidEmail(email)) {
                        this.showError('profile', BatciFLC.strings.invalid_profile);
                        return;
                    }
                    
                    this.setButtonLoading($btn, true);
                    this.hideError('profile');
                    
                    $.ajax({
                        url: BatciFLC.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'batci_flc_save_profile',
                            nonce: BatciFLC.nonce,
                            first_name: firstName,
                            last_name: lastName,
                            email: email
                        },
                        success: function(response) {
                            if (response.success) {
                                this.unlockCheckout();
                                this.hideModal();
                                // Refresh page to update checkout with user data
                                window.location.reload();
                            } else {
                                this.showError('profile', response.data.message || BatciFLC.strings.error);
                            }
                        }.bind(this),
                        error: function() {
                            this.showError('profile', BatciFLC.strings.error);
                        }.bind(this),
                        complete: function() {
                            this.setButtonLoading($btn, false);
                        }.bind(this)
                    });
                },

                prefillProfile: function(data) {
                    if (data.first_name) $('#batci-first-name').val(data.first_name);
                    if (data.last_name) $('#batci-last-name').val(data.last_name);
                    if (data.email) $('#batci-email').val(data.email);
                },

                setButtonLoading: function($btn, loading) {
                    if (loading) {
                        $btn.prop('disabled', true).addClass('loading');
                        $btn.find('.btn-text').hide();
                        $btn.find('.btn-spinner').show();
                    } else {
                        $btn.prop('disabled', false).removeClass('loading');
                        $btn.find('.btn-text').show();
                        $btn.find('.btn-spinner').hide();
                    }
                },

                showError: function(step, message) {
                    $('#batci-' + step + '-error').text(message).fadeIn();
                },

                hideError: function(step) {
                    $('#batci-' + step + '-error').fadeOut();
                },

                showSuccess: function(step, message) {
                    $('#batci-' + step + '-success').text(message).fadeIn();
                },

                isValidEmail: function(email) {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                }
            };

            // Initialize when DOM is ready
            $(document).ready(function() {
                BatciModal.init();
            });

        })(jQuery);
        </script>
        <?php
    }

    /**
     * Add custom CSS based on admin settings
     */
    public static function add_custom_css() {
        if ( ! self::should_restrict_checkout() ) {
            return;
        }

        $bg_color = get_option( 'batci_flc_modal_bg_color', '#ffffff' );
        $text_color = get_option( 'batci_flc_modal_text_color', '#333333' );
        $accent_color = get_option( 'batci_flc_modal_accent_color', '#0073aa' );
        ?>
        <style>
        .batci-flc-modal {
            background-color: <?php echo esc_attr( $bg_color ); ?>;
            color: <?php echo esc_attr( $text_color ); ?>;
        }
        .batci-btn-primary {
            background-color: <?php echo esc_attr( $accent_color ); ?>;
            border-color: <?php echo esc_attr( $accent_color ); ?>;
        }
        .batci-btn-primary:hover {
            background-color: <?php echo esc_attr( self::darken_color( $accent_color, 10 ) ); ?>;
            border-color: <?php echo esc_attr( self::darken_color( $accent_color, 10 ) ); ?>;
        }
        .batci-progress-fill {
            background-color: <?php echo esc_attr( $accent_color ); ?>;
        }
        .batci-step.active .step-number,
        .batci-step.completed .step-number {
            background-color: <?php echo esc_attr( $accent_color ); ?>;
        }
        </style>
        <?php
    }

    /**
     * Darken a hex color
     */
    private static function darken_color( $hex, $percent ) {
        $hex = str_replace( '#', '', $hex );
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        $r = max( 0, min( 255, $r - ( $r * $percent / 100 ) ) );
        $g = max( 0, min( 255, $g - ( $g * $percent / 100 ) ) );
        $b = max( 0, min( 255, $b - ( $b * $percent / 100 ) ) );
        
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }
}

// Initialize frontend
Batci_FLC_Frontend::init();