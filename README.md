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

== Configuration ==

1. Navigate to **Settings → Resend Email**
2. Enter your Resend API key
3. Select a verified domain from your Resend account
4. Configure the "From" name and email address
5. Optionally enable "Force From Name and Email" to override all WordPress emails
6. Click "Save Changes"

== Frequently Asked Questions ==

= What is Resend? =

Resend is a modern email API for developers. Learn more at [resend.com](https://resend.com).

= Do I need a Resend account? =

Yes, you'll need a Resend account and API key to use this plugin. Sign up at [resend.com](https://resend.com).

= What PHP version is required? =

PHP 8.1 or higher is required.

= Will this work with other email plugins? =

This plugin replaces WordPress's default `wp_mail` function. It should work with most plugins that use WordPress's standard email functions.

== Screenshots ==

1. Settings page for configuring API key and email settings
2. Email logs viewer showing sent emails
3. Email detail viewer with HTML rendering

== Changelog ==

= 1.0.0 =
* Initial release
* WordPress wp_mail integration
* Admin settings page
* Email logs viewer
* Domain verification support

== Upgrade Notice ==

= 1.0.0 =
Initial release of Resend Email Integration.

== Building for Distribution ==

=== Automatic Deployment to WordPress.org ===

This repository is configured for automatic deployment to WordPress.org using GitHub Actions.

**Setup:**
1. Configure GitHub Secrets (see [DEPLOYMENT.md](DEPLOYMENT.md)):
   - `WORDPRESS_ORG_USERNAME` - Your WordPress.org username
   - `WORDPRESS_ORG_PASSWORD` - Your WordPress.org Application Password
2. Push to `main` branch - deployment happens automatically
3. Version is extracted from `resend-email-integration.php` header

See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed setup instructions.

=== Manual Build Script ===

For local testing or manual distribution:

```bash
./build-plugin.sh
```

This creates `resend-email-integration.zip` with all files including `vendor/`.

=== Manual Build ===

1. Ensure `vendor/` folder exists: `composer install --no-dev`
2. Create a zip file including all plugin files **including** the `vendor/` folder
3. The zip should contain:
   - All PHP files
   - All assets (CSS, JS)
   - The `vendor/` folder with all dependencies
   - `composer.json` and `composer.lock`

**Important:** The `vendor/` folder must be included in the distribution zip. Users should not need to run Composer.

== Development ==

For development, you can use Composer to manage dependencies:

```bash
composer install
```

Note: The `vendor/` folder is gitignored but must be included in distribution packages.

== Support ==

For issues and feature requests, please visit the plugin repository.

== License ==

GPLv2 or later
