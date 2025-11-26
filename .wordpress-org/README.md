=== Resend Email Integration ===
Contributors: resend
Tags: email, resend, wp_mail, smtp, transactional-email
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin that integrates with Resend email service, replacing the default `wp_mail` functionality with Resend's API.

== Description ==

Resend Email Integration seamlessly replaces WordPress's default email functionality with Resend's powerful email API. This plugin:

* Sends all WordPress emails through Resend's API
* Provides an admin interface for easy configuration
* Displays email logs and history
* Supports domain verification
* Caches email details locally for faster access

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/resend-email-integration` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings → Resend Email screen to configure the plugin

== Frequently Asked Questions ==

### What is Resend?

Resend is a modern email API for developers. Learn more at [resend.com](https://resend.com).

### Do I need a Resend account?

Yes, you'll need a Resend account and API key to use this plugin. Sign up at [resend.com](https://resend.com).

### What PHP version is required?

PHP 8.1 or higher is required.

### Will this work with other email plugins?

This plugin replaces WordPress's default `wp_mail` function. It should work with most plugins that use WordPress's standard email functions.

== Screenshots ==

1. Settings page for configuring API key and email settings
2. Email logs viewer showing sent emails
3. Email detail viewer with HTML rendering

== Changelog ==

### 1.0.0
* Initial release
* WordPress wp_mail integration
* Admin settings page
* Email logs viewer
* Domain verification support

== Upgrade Notice ==

### 1.0.0
Initial release of Resend Email Integration.

