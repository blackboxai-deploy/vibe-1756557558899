/**
 * Batci Force Login at Checkout - Frontend JavaScript
 * Version: 1.2.0
 */

(function($) {
    'use strict';

    // Global namespace
    window.BatciFLCModal = {
        initialized: false,
        currentStep: 'phone',
        phoneNumber: '',
        countryCode: '',
        iti: null,
        
        /**
         * Initialize the modal system
         */
        init: function() {
            if (this.initialized) return;
            this.initialized = true;
            
            this.initPhoneInput();
            this.bindEvents();
            this.lockCheckout();
            this.showModal();
            this.startPolling();
            
            // Hide any conflicting elements
            this.hideConflictingElements();
        },

        /**
         * Initialize international telephone input
         */
        initPhoneInput: function() {
            var input = document.querySelector('#batci-phone-input');
            if (!input || typeof intlTelInput === 'undefined') {
                console.warn('Batci FLC: International tel input not available');
                return;
            }

            try {
                var settings = window.BatciFLC && window.BatciFLC.settings ? window.BatciFLC.settings : {};
                
                this.iti = intlTelInput(input, {
                    initialCountry: settings.default_country || 'us',
                    preferredCountries: settings.preferred_countries || ['us', 'ca', 'gb', 'au'],
                    utilsScript: this.getPluginUrl() + 'assets/js/intl-tel-input/js/utils.js',
                    autoPlaceholder: 'aggressive',
                    formatOnDisplay: true,
                    nationalMode: false,
                    placeholderNumberType: 'MOBILE',
                    separateDialCode: false,
                    hiddenInput: 'full_number',
                    allowDropdown: true,
                    autoHideDialCode: false,
                    dropdownContainer: document.body
                });

                // Handle country change
                input.addEventListener('countrychange', function() {
                    this.validatePhoneNumber();
                }.bind(this));

            } catch (error) {
                console.error('Batci FLC: Error initializing phone input', error);
            }
        },

        /**
         * Get plugin URL
         */
        getPluginUrl: function() {
            var scripts = document.getElementsByTagName('script');
            for (var i = 0; i < scripts.length; i++) {
                var src = scripts[i].src;
                if (src.indexOf('batci-flc.js') > -1) {
                    return src.substring(0, src.lastIndexOf('/assets/js/')) + '/';
                }
            }
            return window.BatciFLC && window.BatciFLC.plugin_url ? window.BatciFLC.plugin_url : '';
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Button events
            $(document).on('click', '#batci-send-otp', function(e) {
                e.preventDefault();
                self.sendOTP();
            });
            
            $(document).on('click', '#batci-verify-otp', function(e) {
                e.preventDefault();
                self.verifyOTP();
            });
            
            $(document).on('click', '#batci-resend-otp', function(e) {
                e.preventDefault();
                self.resendOTP();
            });
            
            $(document).on('click', '#batci-back-to-phone', function(e) {
                e.preventDefault();
                self.backToPhone();
            });
            
            $(document).on('click', '#batci-save-profile', function(e) {
                e.preventDefault();
                self.saveProfile();
            });

            // Enter key handling
            $(document).on('keypress', '#batci-phone-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#batci-send-otp').click();
                }
            });
            
            $(document).on('keypress', '#batci-otp-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#batci-verify-otp').click();
                }
            });
            
            $(document).on('keypress', '#batci-step-profile input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#batci-save-profile').click();
                }
            });

            // Input formatting and validation
            $(document).on('input', '#batci-otp-input', function() {
                var value = this.value.replace(/[^0-9]/g, '');
                this.value = value;
                
                if (value.length === 6) {
                    $('#batci-verify-otp').focus();
                }
            });

            // Real-time validation
            $(document).on('input blur', '#batci-phone-input', function() {
                self.validatePhoneNumber();
            });
            
            $(document).on('input blur', '#batci-email', function() {
                self.validateEmail();
            });

            // Prevent modal close on outside click (force authentication)
            $(document).on('click', '.batci-flc-overlay', function(e) {
                if (e.target === this) {
                    // Show a message instead of closing
                    self.showMessage('error', self.getString('auth_required'), 'phone');
                }
            });
        },

        /**
         * Show the modal
         */
        showModal: function() {
            var $overlay = $('#batci-login-overlay');
            
            $overlay.css({
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
            }).hide().fadeIn(200);
            
            $('body').addClass('batci-modal-open').css('overflow', 'hidden');
            
            // Focus management
            setTimeout(function() {
                $('#batci-phone-input').focus();
            }, 300);
        },

        /**
         * Hide the modal
         */
        hideModal: function() {
            $('#batci-login-overlay').fadeOut(200, function() {
                $(this).remove();
            });
            $('body').removeClass('batci-modal-open').css('overflow', '');
        },

        /**
         * Lock checkout functionality
         */
        lockCheckout: function() {
            var selectors = this.getConfig('lock_selectors', [
                '#place_order',
                '.place-order button',
                '.cfw-place-order',
                'button[name="woocommerce_checkout_place_order"]',
                '.wc-block-checkout__actions button'
            ]);
            
            // Disable buttons
            selectors.forEach(function(selector) {
                $(selector).prop('disabled', true).addClass('batci-disabled');
            });
            
            // Prevent form submission
            var self = this;
            $(document).on('submit.batci', 'form.checkout, form.woocommerce-checkout', function(e) {
                if (!window.BatciFLCAuthenticated) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    self.showMessage('error', self.getString('complete_auth'), 'phone');
                    return false;
                }
            });
            
            // Prevent button clicks
            if (selectors.length) {
                $(document).on('click.batci', selectors.join(','), function(e) {
                    if (!window.BatciFLCAuthenticated) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        self.showMessage('error', self.getString('complete_auth'), 'phone');
                        return false;
                    }
                });
            }
        },

        /**
         * Unlock checkout functionality
         */
        unlockCheckout: function() {
            var selectors = this.getConfig('lock_selectors', []);
            
            selectors.forEach(function(selector) {
                $(selector).prop('disabled', false).removeClass('batci-disabled');
            });
            
            $(document).off('.batci');
            window.BatciFLCAuthenticated = true;
        },

        /**
         * Set current step and update UI
         */
        setStep: function(step) {
            var steps = ['phone', 'verify', 'profile'];
            var stepIndex = steps.indexOf(step);
            
            if (stepIndex === -1) return;
            
            // Hide all step contents
            $('.batci-step-content').removeClass('active');
            $('.batci-step').removeClass('active completed');
            
            // Show current step
            $('#batci-step-' + step).addClass('active');
            $('.batci-step[data-step="' + step + '"]').addClass('active');
            
            // Mark previous steps as completed
            for (var i = 0; i < stepIndex; i++) {
                $('.batci-step[data-step="' + steps[i] + '"]').addClass('completed');
            }
            
            // Update progress bar
            var progress = ((stepIndex + 1) / steps.length) * 100;
            $('#batci-progress-fill').css('width', progress + '%');
            
            this.currentStep = step;
            this.clearMessages();
            
            // Focus management
            setTimeout(function() {
                if (step === 'phone') {
                    $('#batci-phone-input').focus();
                } else if (step === 'verify') {
                    $('#batci-otp-input').focus();
                } else if (step === 'profile') {
                    $('#batci-first-name').focus();
                }
            }, 100);
        },

        /**
         * Send OTP to phone number
         */
        sendOTP: function() {
            var $btn = $('#batci-send-otp');
            var phoneNumber = this.iti ? this.iti.getNumber() : $('#batci-phone-input').val();
            var countryData = this.iti ? this.iti.getSelectedCountryData() : {};
            
            // Validate phone number
            if (!phoneNumber || (this.iti && !this.iti.isValidNumber())) {
                this.showMessage('error', this.getString('invalid_phone'), 'phone');
                $('#batci-phone-input').focus();
                return;
            }
            
            this.setButtonLoading($btn, true);
            this.clearMessages();
            
            var self = this;
            var data = {
                action: 'batci_flc_send_otp',
                nonce: this.getConfig('nonce'),
                phone_number: phoneNumber,
                country_code: countryData.dialCode || ''
            };
            
            $.ajax({
                url: this.getConfig('ajax_url'),
                type: 'POST',
                data: data,
                timeout: 30000,
                success: function(response) {
                    if (response && response.success) {
                        self.phoneNumber = phoneNumber;
                        self.countryCode = countryData.dialCode || '';
                        self.setStep('verify');
                        self.showMessage('success', self.getString('otp_sent'), 'otp');
                        self.startOTPTimer(response.data.expires_in || 300);
                    } else {
                        var message = response && response.data && response.data.message 
                            ? response.data.message 
                            : self.getString('error');
                        self.showMessage('error', message, 'phone');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Batci FLC: Send OTP error', { xhr: xhr, status: status, error: error });
                    var message = status === 'timeout' 
                        ? self.getString('timeout_error') 
                        : self.getString('error');
                    self.showMessage('error', message, 'phone');
                },
                complete: function() {
                    self.setButtonLoading($btn, false);
                }
            });
        },

        /**
         * Verify OTP code
         */
        verifyOTP: function() {
            var $btn = $('#batci-verify-otp');
            var otpCode = $('#batci-otp-input').val().trim();
            
            if (!otpCode || otpCode.length !== 6 || !/^\d{6}$/.test(otpCode)) {
                this.showMessage('error', this.getString('invalid_otp'), 'otp');
                $('#batci-otp-input').focus();
                return;
            }
            
            this.setButtonLoading($btn, true);
            this.clearMessages();
            
            var self = this;
            var data = {
                action: 'batci_flc_verify_otp',
                nonce: this.getConfig('nonce'),
                phone_number: this.phoneNumber,
                otp_code: otpCode
            };
            
            $.ajax({
                url: this.getConfig('ajax_url'),
                type: 'POST',
                data: data,
                timeout: 30000,
                success: function(response) {
                    if (response && response.success) {
                        // Set authenticated flag
                        window.BatciFLCLoggedIn = true;
                        
                        self.setStep('profile');
                        
                        // Pre-fill profile if available
                        if (response.data && response.data.prefill) {
                            self.prefillProfile(response.data.prefill);
                        }
                        
                        self.stopPolling();
                    } else {
                        var message = response && response.data && response.data.message 
                            ? response.data.message 
                            : self.getString('error');
                        self.showMessage('error', message, 'otp');
                        $('#batci-otp-input').focus().select();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Batci FLC: Verify OTP error', { xhr: xhr, status: status, error: error });
                    var message = status === 'timeout' 
                        ? self.getString('timeout_error') 
                        : self.getString('error');
                    self.showMessage('error', message, 'otp');
                },
                complete: function() {
                    self.setButtonLoading($btn, false);
                }
            });
        },

        /**
         * Resend OTP
         */
        resendOTP: function() {
            $('#batci-otp-input').val('');
            this.sendOTP();
        },

        /**
         * Go back to phone step
         */
        backToPhone: function() {
            this.setStep('phone');
        },

        /**
         * Save user profile
         */
        saveProfile: function() {
            var $btn = $('#batci-save-profile');
            var firstName = $('#batci-first-name').val().trim();
            var lastName = $('#batci-last-name').val().trim();
            var email = $('#batci-email').val().trim();
            
            // Validate inputs
            if (!firstName || !lastName || !email) {
                this.showMessage('error', this.getString('invalid_profile'), 'profile');
                return;
            }
            
            if (!this.isValidEmail(email)) {
                this.showMessage('error', this.getString('invalid_email'), 'profile');
                $('#batci-email').focus();
                return;
            }
            
            this.setButtonLoading($btn, true);
            this.clearMessages();
            
            var self = this;
            var data = {
                action: 'batci_flc_save_profile',
                nonce: this.getConfig('nonce'),
                first_name: firstName,
                last_name: lastName,
                email: email
            };
            
            $.ajax({
                url: this.getConfig('ajax_url'),
                type: 'POST',
                data: data,
                timeout: 30000,
                success: function(response) {
                    if (response && response.success) {
                        window.BatciFLCProfileDone = true;
                        self.unlockCheckout();
                        self.hideModal();
                        
                        // Refresh page to update checkout with user data
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        var message = response && response.data && response.data.message 
                            ? response.data.message 
                            : self.getString('error');
                        self.showMessage('error', message, 'profile');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Batci FLC: Save profile error', { xhr: xhr, status: status, error: error });
                    var message = status === 'timeout' 
                        ? self.getString('timeout_error') 
                        : self.getString('error');
                    self.showMessage('error', message, 'profile');
                },
                complete: function() {
                    self.setButtonLoading($btn, false);
                }
            });
        },

        /**
         * Pre-fill profile form
         */
        prefillProfile: function(data) {
            if (data.first_name) $('#batci-first-name').val(data.first_name);
            if (data.last_name) $('#batci-last-name').val(data.last_name);
            if (data.email) $('#batci-email').val(data.email);
        },

        /**
         * Start login status polling (fallback)
         */
        startPolling: function() {
            if (this.pollInterval) return;
            
            var self = this;
            this.pollInterval = setInterval(function() {
                self.checkLoginStatus();
            }, this.getConfig('poll_interval', 3000));
        },

        /**
         * Stop login status polling
         */
        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        /**
         * Check login status (fallback method)
         */
        checkLoginStatus: function() {
            if (window.BatciFLCLoggedIn) {
                this.stopPolling();
                return;
            }
            
            var self = this;
            $.ajax({
                url: this.getConfig('ajax_url'),
                type: 'POST',
                data: {
                    action: 'batci_login_status',
                    nonce: this.getConfig('nonce')
                },
                success: function(response) {
                    if (response && response.success && response.data && response.data.logged_in) {
                        window.BatciFLCLoggedIn = true;
                        self.stopPolling();
                        
                        if (self.currentStep === 'phone' || self.currentStep === 'verify') {
                            self.setStep('profile');
                            
                            if (response.data.prefill) {
                                self.prefillProfile(response.data.prefill);
                            }
                        }
                    }
                },
                error: function() {
                    // Silently continue polling
                }
            });
        },

        /**
         * Start OTP expiration timer
         */
        startOTPTimer: function(seconds) {
            if (this.otpTimer) {
                clearInterval(this.otpTimer);
            }
            
            var self = this;
            var timeLeft = seconds;
            
            this.otpTimer = setInterval(function() {
                timeLeft--;
                
                if (timeLeft <= 0) {
                    clearInterval(self.otpTimer);
                    self.showMessage('error', self.getString('otp_expired'), 'otp');
                    $('#batci-verify-otp').prop('disabled', true);
                }
            }, 1000);
        },

        /**
         * Set button loading state
         */
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

        /**
         * Show message in current step
         */
        showMessage: function(type, message, step) {
            step = step || this.currentStep;
            var $container = $('#batci-' + step + '-' + type);
            
            if ($container.length) {
                $container.text(message).fadeIn();
                
                // Auto-hide success messages
                if (type === 'success') {
                    setTimeout(function() {
                        $container.fadeOut();
                    }, 5000);
                }
            }
        },

        /**
         * Clear all messages
         */
        clearMessages: function() {
            $('.batci-error-message, .batci-success-message').fadeOut();
        },

        /**
         * Validate phone number
         */
        validatePhoneNumber: function() {
            var $input = $('#batci-phone-input');
            var isValid = this.iti ? this.iti.isValidNumber() : $input.val().length > 6;
            
            $input.removeClass('invalid valid');
            if ($input.val().length > 0) {
                $input.addClass(isValid ? 'valid' : 'invalid');
            }
            
            return isValid;
        },

        /**
         * Validate email
         */
        validateEmail: function() {
            var $input = $('#batci-email');
            var email = $input.val().trim();
            var isValid = this.isValidEmail(email);
            
            $input.removeClass('invalid valid');
            if (email.length > 0) {
                $input.addClass(isValid ? 'valid' : 'invalid');
            }
            
            return isValid;
        },

        /**
         * Check if email is valid
         */
        isValidEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        /**
         * Hide conflicting elements from other plugins
         */
        hideConflictingElements: function() {
            // Hide "Use email instead" buttons and similar
            var selectors = [
                '#batci-use-email',
                '.batci-btn-use-email',
                '#batci-flc-email-pane',
                '[data-action="register-new"]',
                '[data-action="link-existing"]'
            ];
            
            selectors.forEach(function(selector) {
                $(selector).hide().attr('aria-hidden', 'true');
            });
            
            // Hide elements with specific text content
            var badTexts = [
                'register as a new user',
                'link phone to existing account',
                'use email instead'
            ];
            
            $('a, button, input[type="button"]').each(function() {
                var text = (this.textContent || this.value || '').replace(/\s+/g, ' ').trim().toLowerCase();
                if (badTexts.some(function(badText) { return text.indexOf(badText) !== -1; })) {
                    $(this).hide().attr('aria-hidden', 'true');
                }
            });
        },

        /**
         * Get configuration value
         */
        getConfig: function(key, defaultValue) {
            return window.BatciFLC && window.BatciFLC[key] !== undefined 
                ? window.BatciFLC[key] 
                : defaultValue;
        },

        /**
         * Get localized string
         */
        getString: function(key) {
            var strings = this.getConfig('strings', {});
            return strings[key] || key;
        },

        /**
         * Destroy modal and cleanup
         */
        destroy: function() {
            this.stopPolling();
            if (this.otpTimer) {
                clearInterval(this.otpTimer);
            }
            $(document).off('.batci');
            this.hideModal();
            this.unlockCheckout();
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize if we're on checkout and not logged in
        if ($('#batci-login-overlay').length > 0) {
            window.BatciFLCModal.init();
        }
    });

    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (window.BatciFLCModal.pollInterval) {
                window.BatciFLCModal.stopPolling();
            }
        } else {
            if (window.BatciFLCModal.initialized && !window.BatciFLCLoggedIn) {
                window.BatciFLCModal.startPolling();
            }
        }
    });

})(jQuery);