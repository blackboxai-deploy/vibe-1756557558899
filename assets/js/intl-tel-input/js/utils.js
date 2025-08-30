/**
 * International Telephone Input Utilities (Simplified)
 * This provides basic validation utilities for the intl-tel-input library
 */

(function() {
    'use strict';

    // Basic phone number validation utilities
    window.intlTelInputUtils = {
        // Phone number types
        numberType: {
            FIXED_LINE: 0,
            MOBILE: 1,
            FIXED_LINE_OR_MOBILE: 2,
            TOLL_FREE: 3,
            PREMIUM_RATE: 4,
            SHARED_COST: 5,
            VOIP: 6,
            PERSONAL_NUMBER: 7,
            PAGER: 8,
            UAN: 9,
            VOICEMAIL: 10,
            UNKNOWN: -1
        },

        // Validation result types
        validationError: {
            IS_POSSIBLE: 0,
            INVALID_COUNTRY_CODE: 1,
            TOO_SHORT: 2,
            TOO_LONG: 3,
            NOT_A_NUMBER: 4
        },

        // Number format types
        numberFormat: {
            E164: 0,
            INTERNATIONAL: 1,
            NATIONAL: 2,
            RFC3966: 3
        },

        // Simple validation function
        isValidNumber: function(number, countryCode) {
            if (!number) return false;
            
            // Remove all non-digit characters except +
            var cleanNumber = number.replace(/[^\d+]/g, '');
            
            // Must start with + and have 7-15 digits total
            if (!/^\+\d{7,15}$/.test(cleanNumber)) {
                return false;
            }
            
            return true;
        },

        // Get number type (simplified)
        getNumberType: function(number, countryCode) {
            if (!this.isValidNumber(number, countryCode)) {
                return this.numberType.UNKNOWN;
            }
            
            // For simplicity, assume all valid numbers are mobile
            return this.numberType.MOBILE;
        },

        // Format number (simplified)
        formatNumber: function(number, countryCode, format) {
            if (!number) return '';
            
            format = format || this.numberFormat.INTERNATIONAL;
            
            // Remove all non-digit characters except +
            var cleanNumber = number.replace(/[^\d+]/g, '');
            
            if (format === this.numberFormat.E164) {
                return cleanNumber.startsWith('+') ? cleanNumber : '+' + cleanNumber;
            }
            
            if (format === this.numberFormat.INTERNATIONAL) {
                if (cleanNumber.startsWith('+')) {
                    // Add some basic formatting for international numbers
                    var formatted = cleanNumber.substring(0, 4) + ' ';
                    var remaining = cleanNumber.substring(4);
                    
                    // Group remaining digits
                    for (var i = 0; i < remaining.length; i += 3) {
                        formatted += remaining.substring(i, i + 3);
                        if (i + 3 < remaining.length) formatted += ' ';
                    }
                    
                    return formatted;
                }
            }
            
            return cleanNumber;
        },

        // Get validation error
        getValidationError: function(number, countryCode) {
            if (!number) {
                return this.validationError.NOT_A_NUMBER;
            }
            
            var cleanNumber = number.replace(/[^\d+]/g, '');
            
            if (!cleanNumber.startsWith('+')) {
                return this.validationError.INVALID_COUNTRY_CODE;
            }
            
            if (cleanNumber.length < 8) {
                return this.validationError.TOO_SHORT;
            }
            
            if (cleanNumber.length > 16) {
                return this.validationError.TOO_LONG;
            }
            
            if (!/^\+\d+$/.test(cleanNumber)) {
                return this.validationError.NOT_A_NUMBER;
            }
            
            return this.validationError.IS_POSSIBLE;
        },

        // Check if number is possible
        isPossibleNumber: function(number, countryCode) {
            return this.getValidationError(number, countryCode) === this.validationError.IS_POSSIBLE;
        },

        // Get country code for number
        getCountryCodeForNumber: function(number) {
            if (!number || !number.startsWith('+')) {
                return '';
            }
            
            var cleanNumber = number.replace(/[^\d]/g, '');
            
            // Simple country code detection (first 1-3 digits)
            var countryCode = '';
            
            // Common country codes
            var codes = {
                '1': 'us',    // US/Canada
                '44': 'gb',   // UK
                '49': 'de',   // Germany
                '33': 'fr',   // France
                '39': 'it',   // Italy
                '34': 'es',   // Spain
                '31': 'nl',   // Netherlands
                '61': 'au',   // Australia
                '81': 'jp',   // Japan
                '86': 'cn',   // China
                '91': 'in',   // India
                '55': 'br',   // Brazil
                '7': 'ru',    // Russia
                '27': 'za',   // South Africa
                '52': 'mx'    // Mexico
            };
            
            // Try 3-digit codes first, then 2-digit, then 1-digit
            for (var len = 3; len >= 1; len--) {
                var code = cleanNumber.substring(0, len);
                if (codes[code]) {
                    return codes[code];
                }
            }
            
            return '';
        },

        // Get example number for country
        getExampleNumber: function(countryCode, type) {
            type = type || this.numberType.MOBILE;
            
            var examples = {
                'us': '+1 555 123 4567',
                'gb': '+44 7700 123456',
                'de': '+49 1234 567890',
                'fr': '+33 6 12 34 56 78',
                'it': '+39 123 456 7890',
                'es': '+34 612 345 678',
                'nl': '+31 6 12345678',
                'au': '+61 4 1234 5678',
                'jp': '+81 90 1234 5678',
                'cn': '+86 138 1234 5678',
                'in': '+91 9876543210',
                'br': '+55 11 91234 5678',
                'ru': '+7 912 345 6789',
                'za': '+27 82 123 4567',
                'mx': '+52 55 1234 5678'
            };
            
            return examples[countryCode] || '+1 555 123 4567';
        }
    };

})();