# Resend Email Integration

A WordPress plugin that integrates with Resend email service, replacing the default `wp_mail` functionality with Resend's API.

## Features

- Seamlessly replaces WordPress `wp_mail` with Resend API
- Admin settings page for API key configuration
- Email logs viewer with pagination
- Email detail viewer with HTML rendering
- Local database caching for email details
- Test email functionality
- Domain verification support

## Requirements

- PHP 8.1 or higher
- WordPress 5.0 or higher
- Resend API key (get one at https://resend.com)

## Installation

1. Download the plugin zip file
2. Upload it to your WordPress site via Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Go to Settings → Resend Email to configure your API key

## Configuration

1. Navigate to **Settings → Resend Email**
2. Enter your Resend API key
3. Select a verified domain from your Resend account
4. Configure the "From" name and email address
5. Optionally enable "Force From Name and Email" to override all WordPress emails
6. Click "Save Changes"

## Building for Distribution

### Automatic Deployment to WordPress.org

This repository is configured for automatic deployment to WordPress.org using GitHub Actions.

**Setup:**
1. Configure GitHub Secrets (see [DEPLOYMENT.md](DEPLOYMENT.md)):
   - `WORDPRESS_ORG_USERNAME` - Your WordPress.org username
   - `WORDPRESS_ORG_PASSWORD` - Your WordPress.org Application Password
2. Push to `main` branch - deployment happens automatically
3. Version is extracted from `resend-email-integration.php` header

See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed setup instructions.

### Manual Build Script

For local testing or manual distribution:

```bash
./build-plugin.sh
```

This creates `resend-email-integration.zip` with all files including `vendor/`.

### Manual Build

1. Ensure `vendor/` folder exists: `composer install --no-dev`
2. Create a zip file including all plugin files **including** the `vendor/` folder
3. The zip should contain:
   - All PHP files
   - All assets (CSS, JS)
   - The `vendor/` folder with all dependencies
   - `composer.json` and `composer.lock`

**Important:** The `vendor/` folder must be included in the distribution zip. Users should not need to run Composer.

## Development

For development, you can use Composer to manage dependencies:

```bash
composer install
```

Note: The `vendor/` folder is gitignored but must be included in distribution packages.

## Support

For issues and feature requests, please visit the plugin repository.

## License

MIT License

