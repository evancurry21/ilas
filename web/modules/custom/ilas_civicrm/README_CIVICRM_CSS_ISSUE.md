# CiviCRM CSS Loading Issue - Analysis and Solutions

## Problem Description

CiviCRM CSS files (`civicrm.css` and `crm-i.css`) are being requested from `https://localhost:3000/libraries/civicrm/css/` when accessing the site through BrowserSync, resulting in:
- 404 errors because the files don't exist at that location
- MIME type errors because the server returns HTML (404 page) instead of CSS
- Broken CiviCRM styling on affected pages

## Root Causes

1. **Missing Symlink**: CiviCRM is configured to load resources from `/libraries/civicrm/` but no symlink exists from this location to the actual CiviCRM core files in `/vendor/civicrm/civicrm-core/`.

2. **BrowserSync Proxy**: When accessing the site through BrowserSync (localhost:3000), CiviCRM generates resource URLs using the proxy hostname instead of the actual site URL.

3. **Configuration Issue**: The `userFrameworkResourceURL` in `civicrm.settings.php` is set to `https://ilas.ddev.site/libraries/civicrm/` but this gets overridden when accessed through localhost.

## Solutions Implemented

### 1. Create Resource Symlink
Run the fix script to create the necessary symlink:
```bash
bash /home/evancurry/ilas/web/modules/custom/ilas_civicrm/scripts/fix_civicrm_resources.sh
```

This creates: `/web/libraries/civicrm` → `/vendor/civicrm/civicrm-core`

### 2. Enhanced Event Subscribers
Two event subscribers work together to fix URLs:

- **CiviCrmResourceSubscriber**: Original subscriber that fixes server variables on request
- **CiviCrmUrlFixSubscriber**: New subscriber that:
  - Fixes URLs during request processing (high priority)
  - Replaces localhost URLs in HTML responses (low priority)
  - Updates CiviCRM configuration dynamically

### 3. Clear Caches
After implementing fixes:
```bash
drush cr
```

## Alternative Solutions

If the above doesn't work completely:

### Option A: Disable BrowserSync
In `webpack.mix.js`, keep BrowserSync commented out (already done).

### Option B: Configure BrowserSync Proxy
If you need BrowserSync, configure it to preserve the original host:
```javascript
mix.browserSync({
    proxy: {
        target: 'https://ilas.ddev.site',
        ws: true,
        proxyOptions: {
            changeOrigin: false,
            preserveHeaderKeyCase: true
        }
    },
    // ... other options
});
```

### Option C: Update CiviCRM Configuration
Modify `/web/sites/default/civicrm.settings.php` to force correct URLs:
```php
// Add after existing configuration
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== FALSE) {
    $civicrm_setting['domain']['userFrameworkResourceURL'] = 'https://ilas.ddev.site/libraries/civicrm/';
}
```

## Verification

To verify the fix is working:

1. Access the site through BrowserSync (localhost:3000)
2. Open browser developer tools
3. Check Network tab for CiviCRM CSS files
4. Verify they load from `https://ilas.ddev.site/libraries/civicrm/css/` (not localhost)
5. Verify no MIME type errors in console

## Long-term Recommendations

1. **Development Workflow**: Consider accessing the site directly via `https://ilas.ddev.site` instead of through BrowserSync for CiviCRM pages.

2. **Deployment**: Ensure the symlink creation is part of your deployment process or use a post-install script in composer.json.

3. **Alternative Resource Handling**: Consider configuring CiviCRM to serve resources through Drupal's asset system instead of direct file access.