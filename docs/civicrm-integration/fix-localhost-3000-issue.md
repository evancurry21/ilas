# Fix for CiviCRM localhost:3000 Resource Loading Issue

## Problem
CiviCRM CSS files are being loaded from `https://localhost:3000` instead of the correct DDEV URL (`https://ilas.ddev.site`), causing:
- MIME type errors (text/html instead of text/css)
- Missing styles
- Performance issues

## Root Cause
BrowserSync (development tool) was configured to run on port 3000 and proxy the site, which interfered with CiviCRM's resource URL generation.

## Solutions Implemented

### 1. Disabled BrowserSync
- Commented out BrowserSync configuration in `webpack.mix.js`
- This prevents the proxy server from running

### 2. Created Local Settings Override
- Added `settings.local.php` to force correct URLs when accessed via localhost
- Ensures $_SERVER variables are set correctly

### 3. Added CiviCRM Settings Override
- Modified `civicrm.settings.php` to force correct resource URLs
- Added explicit URL preferences for resources, images, and extensions

### 4. Created Event Subscriber
- Added `CiviCrmResourceSubscriber` to fix URLs on request
- Ensures correct host detection in DDEV environment

### 5. Added Hook Implementations
- `hook_page_attachments_alter()` - Fixes URLs in page attachments
- `hook_civicrm_buildAsset()` - Ensures assets built with correct URL
- `hook_civicrm_coreResourceList()` - Fixes resource list URLs

## How to Access the Site

### Correct URLs:
- Main site: `https://ilas.ddev.site`
- Admin: `https://ilas.ddev.site/admin`
- CiviCRM: `https://ilas.ddev.site/civicrm`

### Incorrect URLs (DO NOT USE):
- `http://localhost:3000`
- `http://localhost`

## If Issues Persist

1. **Clear all caches:**
   ```bash
   ddev drush cr
   ddev drush ev "\Drupal::service('civicrm')->initialize(); CRM_Core_Config::singleton()->cleanupCaches();"
   ```

2. **Check for running processes:**
   ```bash
   ps aux | grep -i "browsersync\|3000"
   # Kill any BrowserSync processes found
   ```

3. **Clear browser cache:**
   - Hard refresh: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)
   - Or open Developer Tools > Network > Disable cache

4. **Verify URLs are correct:**
   ```bash
   ddev drush ev "print \Drupal::request()->getSchemeAndHttpHost();"
   # Should output: https://ilas.ddev.site
   ```

## For Theme Development

If you need live reload for theme development:

1. Use DDEV's built-in file watching instead of BrowserSync
2. Or use the alternative webpack configuration that excludes CiviCRM:
   ```bash
   cp webpack.mix.js.alternative webpack.mix.js
   npm run watch
   ```

## Prevention

- Always access the site via `https://ilas.ddev.site`
- Don't use BrowserSync with CiviCRM
- If using development tools, ensure they don't proxy CiviCRM resources