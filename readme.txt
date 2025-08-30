=== Batci Force Login at Checkout ===
Contributors: batci
Tags: woocommerce, checkout, otp, authentication, security, mobile, phone verification
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Forces guests to authenticate via mobile OTP before completing WooCommerce checkout. Improves security and ensures accurate customer data.

== Description ==

**Batci Force Login at Checkout** is a comprehensive WooCommerce plugin that enforces mobile OTP (One-Time Password) authentication for guest users during the checkout process. This plugin ensures secure transactions while providing a seamless user experience through a modern, responsive interface.

### Key Features

**🔐 Enhanced Security**
* Mobile OTP verification for all guest checkout attempts
* Configurable OTP expiry time (1-60 minutes)
* Rate limiting to prevent spam (configurable attempts and time windows)
* IP-based security monitoring and analytics
* Comprehensive security logging

**📱 Modern User Interface**
* Progressive multi-step modal with visual progress indicator
* International telephone input with country picker and flag display
* Fully responsive design optimized for mobile devices
* Accessible interface following WCAG 2.1 guidelines
* RTL (Right-to-Left) language support

**⚙️ Admin Customization**
* Complete color customization (background, text, accent colors)
* Customizable modal text and error messages
* Security settings configuration (OTP expiry, attempt limits)
* Analytics dashboard with conversion metrics
* Export functionality for reporting

**📊 Analytics & Reporting**
* Real-time OTP usage statistics
* Success rate tracking and conversion metrics
* Security event logging and monitoring
* Export data for external analysis
* Automatic cleanup of old records

**🛒 WooCommerce Integration**
* Seamless integration with WooCommerce checkout
* Automatic user account creation and management
* Billing information synchronization
* Compatible with major checkout plugins
* Order completion tracking

### How It Works

1. **Guest Detection**: When a non-logged-in user attempts to access checkout, the plugin displays an authentication modal
2. **Phone Verification**: User enters their phone number using the international telephone input with country selection
3. **OTP Delivery**: A 6-digit verification code is sent via SMS (requires SMS service integration)
4. **Profile Completion**: After OTP verification, user provides their name and email address
5. **Account Creation**: Plugin automatically creates a user account and syncs with WooCommerce billing
6. **Checkout Unlock**: User can now complete their purchase with authenticated status

### SMS Service Integration

This plugin provides the framework for OTP delivery but requires integration with an SMS service provider such as:
* Twilio
* AWS SNS
* Nexmo/Vonage
* MessageBird
* Or any other SMS API service

Use the `batci_flc_send_sms` filter hook to integrate your preferred SMS provider.

### Developer Features

**Extensive Hook System**
* `batci_flc_should_restrict_checkout` - Control when restriction applies
* `batci_flc_send_sms` - Integrate custom SMS providers
* `batci_flc_enforce_serverside` - Configure server-side validation
* Multiple action hooks for customization

**REST API Endpoints**
* `/wp-json/batci-flc/v1/stats` - Get authentication statistics
* Requires administrator capabilities for access

**Database Management**
* Custom tables for OTP tracking and analytics
* Automatic data cleanup and optimization
* Configurable data retention periods

== Installation ==

### Automatic Installation

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "Batci Force Login at Checkout"
4. Click "Install Now" and then "Activate"

### Manual Installation

1. Download the plugin ZIP file
2. Extract the files to `/wp-content/plugins/batci-force-login-checkout/`
3. Activate the plugin through the WordPress admin panel

### Post-Installation Setup

1. Navigate to Settings > Batci FLC in your WordPress admin
2. Configure security settings (OTP expiry, attempt limits)
3. Customize the modal appearance to match your theme
4. Set up SMS integration using the provided filter hooks
5. Test the functionality on your checkout page

== Configuration ==

### Security Settings

* **OTP Expiry Time**: Set how long OTP codes remain valid (1-60 minutes)
* **Maximum OTP Attempts**: Limit attempts per phone number (1-20 attempts)
* **Rate Limit Window**: Time frame for counting attempts (5-120 minutes)

### Appearance Customization

* **Modal Background Color**: Customize the modal background
* **Modal Text Color**: Set the primary text color
* **Modal Accent Color**: Define the accent color for buttons and highlights

### Message Customization

* **Modal Title**: Customize the main heading
* **Modal Subtitle**: Set the descriptive text
* **Loading Text**: Define loading state messages
* **Error Messages**: Customize error feedback text

### Analytics Settings

* **Enable Analytics**: Toggle usage tracking and metrics
* **Security Logging**: Enable/disable security event logging
* **Auto Cleanup**: Set automatic data cleanup interval (7-365 days)

== SMS Provider Integration ==

To integrate with your SMS service provider, use the `batci_flc_send_sms` filter:

```php
add_filter( 'batci_flc_send_sms', 'my_custom_sms_handler', 10, 5 );
function my_custom_sms_handler( $sent, $phone_number, $otp_code, $message, $country_code ) {
    // Your SMS service integration code here
    // Return true on success, false on failure
    
    // Example with Twilio
    $twilio = new Twilio\Rest\Client( $account_sid, $auth_token );
    try {
        $twilio->messages->create( $phone_number, [
            'from' => $your_twilio_number,
            'body' => $message
        ]);
        return true;
    } catch ( Exception $e ) {
        return false;
    }
}
```

== Frequently Asked Questions ==

= Does this plugin work without WooCommerce? =

No, this plugin specifically requires WooCommerce and is designed to enhance the checkout security process.

= How do I integrate SMS functionality? =

The plugin provides hooks for SMS integration. You'll need to implement the `batci_flc_send_sms` filter with your preferred SMS service provider.

= Can I customize the appearance of the modal? =

Yes! The plugin provides extensive customization options in the admin panel, including colors, text, and messages.

= Is the plugin mobile-friendly? =

Absolutely! The interface is fully responsive and optimized for mobile devices with touch-friendly controls.

= How does user account creation work? =

When a user successfully verifies their phone number, the plugin automatically creates a WordPress user account and syncs the information with WooCommerce.

= Can I disable the plugin for specific pages? =

Yes, use the `batci_flc_should_restrict_checkout` filter to control when the restriction applies.

= What analytics data is collected? =

The plugin tracks OTP requests, verification success rates, failed attempts, and order completion metrics. No personally identifiable information is stored beyond what's necessary for functionality.

= How secure is the OTP system? =

The plugin implements multiple security layers including rate limiting, IP monitoring, OTP expiry, and attempt tracking to prevent abuse.

== Screenshots ==

1. **Modal Interface** - Clean, modern authentication modal with progress indicator
2. **Country Picker** - International telephone input with flag display and search
3. **Admin Dashboard** - Comprehensive analytics and statistics dashboard
4. **Settings Panel** - Extensive customization options for appearance and security
5. **Mobile Interface** - Fully responsive design optimized for mobile devices

== Changelog ==

= 1.2.0 - 2024-01-15 =
**Major Feature Release**

**New Features:**
* Progressive multi-step modal with visual progress indicator
* International telephone input with country picker and flags
* Comprehensive admin panel with analytics dashboard
* Advanced security features with rate limiting and attempt tracking
* Export functionality for analytics data
* REST API endpoints for external integrations

**Enhancements:**
* Completely redesigned user interface
* Improved mobile responsiveness and accessibility
* Enhanced error handling and user feedback
* Better WooCommerce integration and compatibility
* Optimized database structure and performance

**Security Improvements:**
* IP-based rate limiting and monitoring
* Enhanced OTP validation and expiry management
* Comprehensive security logging and analytics
* Improved input sanitization and validation

**Developer Features:**
* Extensive hook system for customization
* REST API for external integrations
* Better code organization and documentation
* WordPress coding standards compliance

= 1.1.0 - 2023-12-01 =
* Added basic OTP functionality
* Initial WooCommerce integration
* Basic modal interface

= 1.0.0 - 2023-11-15 =
* Initial release
* Basic checkout restriction functionality

== Upgrade Notice ==

= 1.2.0 =
Major update with new progressive modal interface, advanced security features, comprehensive admin panel, and analytics dashboard. Backup your site before updating. New database tables will be created automatically.

== Privacy Policy ==

This plugin collects and processes the following data:

**User Data:**
* Phone numbers for OTP verification
* Names and email addresses for account creation
* IP addresses for security monitoring (anonymized after 30 days)

**Analytics Data:**
* OTP request counts and success rates
* Failed authentication attempts
* Order completion statistics

**Data Retention:**
* OTP codes are automatically deleted after expiry
* Analytics data is retained for the configured period (default: 30 days)
* User accounts follow standard WordPress data retention policies

**Third-Party Services:**
* SMS delivery requires integration with external SMS providers
* No data is shared with third parties except as necessary for SMS delivery

== Support ==

For support, feature requests, or bug reports, please contact us through the WordPress plugin directory or visit our support website.

**Documentation:** Full documentation and integration guides available online
**Community:** Join our community forum for tips and best practices
**Professional Support:** Priority support available for business users