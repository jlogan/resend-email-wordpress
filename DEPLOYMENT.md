# Deployment Guide

This guide explains how to set up automatic deployment to WordPress.org plugin repository using GitHub Actions.

## Prerequisites

1. A WordPress.org account with plugin submission access
2. SVN credentials for WordPress.org (username and password)
3. The plugin must be approved and have a repository on WordPress.org

## Setup Instructions

### 1. Get WordPress.org SVN Credentials

1. Log in to [WordPress.org](https://wordpress.org)
2. Go to your profile and generate an Application Password
3. This will be your `SVN_PASSWORD`
4. Your WordPress.org username is your `SVN_USERNAME`

### 2. Configure GitHub Secrets

In your GitHub repository, go to **Settings → Secrets and variables → Actions** and add:

- **`WORDPRESS_ORG_USERNAME`**: Your WordPress.org username
- **`WORDPRESS_ORG_PASSWORD`**: Your WordPress.org Application Password (not your regular password)

### 3. Plugin Slug

Make sure the plugin slug in `.github/workflows/deploy.yml` matches your WordPress.org plugin slug:
- Current slug: `resend-email-integration`
- If your WordPress.org slug is different, update the `SLUG` value in the workflow file

### 4. Version Management

The workflow automatically extracts the version from the plugin header in `resend-email-integration.php`:
```php
* Version: 1.0.0
```

To release a new version:
1. Update the version number in `resend-email-integration.php`
2. Commit and push to the `main` branch
3. The workflow will automatically deploy to WordPress.org

### 5. WordPress.org Assets

The `.wordpress-org/` directory contains:
- `README.md` - Plugin description for WordPress.org
- `banner-772x250.png` - Plugin banner (772x250px)
- `banner-1544x500.png` - Plugin banner (1544x500px)  
- `icon-256x256.png` - Plugin icon (256x256px)

Replace the placeholder files with actual images before your first deployment.

## How It Works

1. **On push to main branch**: The workflow triggers automatically
2. **Install dependencies**: Runs `composer install --no-dev` to ensure `vendor/` folder exists
3. **Extract version**: Reads version from plugin header
4. **Deploy to SVN**: Uses the 10up WordPress plugin deploy action to:
   - Copy plugin files to WordPress.org SVN repository
   - Commit to `trunk/` (for development)
   - Create a tag for the version (e.g., `tags/1.0.0/`)
   - Update assets from `.wordpress-org/` directory

## Manual Deployment

If you need to deploy manually:

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader

# The vendor/ folder will be included in the deployment
```

Then use SVN commands or the WordPress.org web interface to upload.

## Troubleshooting

### Deployment fails with authentication error
- Verify your `WORDPRESS_ORG_USERNAME` and `WORDPRESS_ORG_PASSWORD` secrets are correct
- Make sure you're using an Application Password, not your regular password
- Check that your WordPress.org account has access to the plugin repository

### Version not updating
- Ensure the version in `resend-email-integration.php` is updated
- Check that the version format matches WordPress.org requirements (e.g., `1.0.0`)

### Vendor folder missing
- The workflow runs `composer install --no-dev` automatically
- If issues persist, check that `composer.json` and `composer.lock` are committed

## Notes

- The `vendor/` folder is gitignored but **must** be included in WordPress.org deployment
- The workflow automatically installs Composer dependencies before deployment
- Only pushes to `main` branch trigger deployment
- Changes to `.md` files are ignored to prevent unnecessary deployments

