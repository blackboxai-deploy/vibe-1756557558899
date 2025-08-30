/**
 * Batci Force Login at Checkout - Admin JavaScript
 * Version: 1.2.0
 */

(function($) {
    'use strict';

    var BatciAdmin = {
        charts: {},
        
        init: function() {
            this.initColorPickers();
            this.bindEvents();
            this.initCharts();
            this.loadDashboardData();
            this.initPreview();
        },

        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.batci-color-picker').wpColorPicker({
                    change: function(event, ui) {
                        BatciAdmin.updatePreview();
                    }
                });
            }
        },

        bindEvents: function() {
            // Export data
            $(document).on('click', '#batci-export-data', this.exportData);
            
            // Cleanup data
            $(document).on('click', '#batci-cleanup-data', this.cleanupData);
            
            // Settings form changes
            $(document).on('change', 'input[name^="batci_flc_"]', this.updatePreview);
            
            // Tab switching
            $(document).on('click', '.nav-tab', function(e) {
                e.preventDefault();
                var tab = $(this).attr('href').split('tab=')[1];
                BatciAdmin.switchTab(tab);
            });

            // Form validation
            $(document).on('submit', 'form', this.validateForm);
        },

        initCharts: function() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded');
                return;
            }

            // OTP Usage Chart
            var usageCtx = document.getElementById('otp-usage-chart');
            if (usageCtx) {
                this.charts.usage = new Chart(usageCtx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'OTP Requests',
                            data: [],
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            // Success Rate Chart
            var successCtx = document.getElementById('success-rate-chart');
            if (successCtx) {
                this.charts.success = new Chart(successCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Successful', 'Failed'],
                        datasets: [{
                            data: [0, 0],
                            backgroundColor: ['#28a745', '#dc3545'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        },

        loadDashboardData: function() {
            if (!$('#today-otp-requests').length) return;

            this.showLoading(true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'batci_flc_get_analytics',
                    nonce: BatciFLCAdmin.nonce,
                    days: 30
                },
                success: function(response) {
                    if (response.success) {
                        BatciAdmin.updateDashboard(response.data);
                    } else {
                        BatciAdmin.showNotice('error', BatciFLCAdmin.strings.error);
                    }
                },
                error: function() {
                    BatciAdmin.showNotice('error', BatciFLCAdmin.strings.error);
                },
                complete: function() {
                    BatciAdmin.showLoading(false);
                }
            });
        },

        updateDashboard: function(data) {
            if (!data || !Array.isArray(data)) return;

            var stats = this.processAnalyticsData(data);
            
            // Update stat cards
            $('#today-otp-requests').text(stats.todayRequests);
            $('#success-rate').text(stats.successRate + '%');
            $('#total-authenticated').text(stats.totalAuthenticated);
            $('#failed-attempts').text(stats.failedAttempts);

            // Update charts
            this.updateCharts(stats);
        },

        processAnalyticsData: function(data) {
            var today = new Date().toISOString().split('T')[0];
            var stats = {
                todayRequests: 0,
                totalAuthenticated: 0,
                failedAttempts: 0,
                successRate: 0,
                dailyData: {},
                successData: { successful: 0, failed: 0 }
            };

            data.forEach(function(item) {
                var date = item.date;
                var type = item.event_type;
                var count = parseInt(item.count) || 0;

                // Today's requests
                if (date === today && type === 'otp_sent') {
                    stats.todayRequests += count;
                }

                // Total stats
                if (type === 'otp_verified') {
                    stats.totalAuthenticated += count;
                    stats.successData.successful += count;
                } else if (type === 'otp_failed') {
                    stats.failedAttempts += count;
                    stats.successData.failed += count;
                }

                // Daily data for chart
                if (!stats.dailyData[date]) {
                    stats.dailyData[date] = 0;
                }
                if (type === 'otp_sent') {
                    stats.dailyData[date] += count;
                }
            });

            // Calculate success rate
            var totalAttempts = stats.successData.successful + stats.successData.failed;
            if (totalAttempts > 0) {
                stats.successRate = Math.round((stats.successData.successful / totalAttempts) * 100);
            }

            return stats;
        },

        updateCharts: function(stats) {
            // Update usage chart
            if (this.charts.usage) {
                var dates = Object.keys(stats.dailyData).sort();
                var values = dates.map(function(date) {
                    return stats.dailyData[date];
                });

                this.charts.usage.data.labels = dates.map(function(date) {
                    return new Date(date).toLocaleDateString();
                });
                this.charts.usage.data.datasets[0].data = values;
                this.charts.usage.update();
            }

            // Update success rate chart
            if (this.charts.success) {
                this.charts.success.data.datasets[0].data = [
                    stats.successData.successful,
                    stats.successData.failed
                ];
                this.charts.success.update();
            }
        },

        exportData: function(e) {
            e.preventDefault();
            
            var $btn = $(this).prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'batci_flc_export_data',
                    nonce: BatciFLCAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.csv_data) {
                        BatciAdmin.downloadCSV(response.data.csv_data);
                        BatciAdmin.showNotice('success', BatciFLCAdmin.strings.export_success);
                    } else {
                        BatciAdmin.showNotice('error', BatciFLCAdmin.strings.error);
                    }
                },
                error: function() {
                    BatciAdmin.showNotice('error', BatciFLCAdmin.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        cleanupData: function(e) {
            e.preventDefault();
            
            if (!confirm(BatciFLCAdmin.strings.confirm_cleanup)) {
                return;
            }
            
            var $btn = $(this).prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'batci_flc_cleanup_data',
                    nonce: BatciFLCAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BatciAdmin.showNotice('success', 'Old data cleaned up successfully');
                        BatciAdmin.loadDashboardData();
                    } else {
                        BatciAdmin.showNotice('error', BatciFLCAdmin.strings.error);
                    }
                },
                error: function() {
                    BatciAdmin.showNotice('error', BatciFLCAdmin.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        downloadCSV: function(data) {
            var csv = '';
            data.forEach(function(row) {
                csv += row.map(function(cell) {
                    return '"' + String(cell).replace(/"/g, '""') + '"';
                }).join(',') + '\n';
            });

            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'batci-flc-analytics-' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        initPreview: function() {
            if (!$('#batci-flc-modal-preview').length) return;
            
            this.updatePreview();
        },

        updatePreview: function() {
            var $preview = $('.preview-modal');
            if (!$preview.length) return;

            // Get current settings
            var bgColor = $('#batci_flc_modal_bg_color').val() || '#ffffff';
            var textColor = $('#batci_flc_modal_text_color').val() || '#333333';
            var accentColor = $('#batci_flc_modal_accent_color').val() || '#0073aa';
            var title = $('#batci_flc_custom_title').val() || 'Sign in to continue';
            var subtitle = $('#batci_flc_custom_subtitle').val() || 'Use your mobile number to log in or register to complete checkout.';

            // Update preview
            $preview.css({
                'background-color': bgColor,
                'color': textColor
            });

            $preview.find('.preview-brand').css('color', accentColor);
            $preview.find('.preview-progress-fill').css('background-color', accentColor);
            $preview.find('button').css('background-color', accentColor);
            $preview.find('h4').text(title);
            $preview.find('p').text(subtitle);
        },

        switchTab: function(tab) {
            // Update URL without reload
            var url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[href*="tab=' + tab + '"]').addClass('nav-tab-active');
        },

        validateForm: function(e) {
            var $form = $(this);
            var valid = true;

            // Validate positive integers
            $form.find('input[type="number"]').each(function() {
                var $input = $(this);
                var value = parseInt($input.val());
                var min = parseInt($input.attr('min')) || 1;
                var max = parseInt($input.attr('max')) || 9999;

                if (isNaN(value) || value < min || value > max) {
                    valid = false;
                    $input.addClass('error');
                    BatciAdmin.showNotice('error', 'Please enter valid numbers within the specified range');
                } else {
                    $input.removeClass('error');
                }
            });

            // Validate email format in custom messages
            $form.find('input[type="email"]').each(function() {
                var $input = $(this);
                var email = $input.val().trim();

                if (email && !BatciAdmin.isValidEmail(email)) {
                    valid = false;
                    $input.addClass('error');
                    BatciAdmin.showNotice('error', 'Please enter a valid email address');
                } else {
                    $input.removeClass('error');
                }
            });

            if (!valid) {
                e.preventDefault();
                return false;
            }
        },

        showLoading: function(show) {
            if (show) {
                $('.stat-number').html('<span class="batci-loading"></span>');
            }
        },

        showNotice: function(type, message) {
            var $notice = $('.batci-admin-notice').removeClass('success error').addClass(type);
            $notice.text(message).fadeIn();
            
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        isValidEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof BatciFLCAdmin !== 'undefined') {
            BatciAdmin.init();
        }
    });

    // Auto-refresh dashboard every 5 minutes
    setInterval(function() {
        if ($('#today-otp-requests').length && document.visibilityState === 'visible') {
            BatciAdmin.loadDashboardData();
        }
    }, 300000); // 5 minutes

})(jQuery);