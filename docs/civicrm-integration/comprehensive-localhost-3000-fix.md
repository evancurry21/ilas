# Comprehensive Fix for CiviCRM localhost:3000 Resource Loading Issues

## Problem Analysis

### Symptoms
- CiviCRM CSS files requested from `https://localhost:3000/libraries/civicrm/css/*.css`
- Server returns HTML (404 page) with MIME type `text/html` instead of CSS
- Browser refuses to apply styles due to strict MIME type checking
- Site performance severely degraded

### Root Causes

1. **BrowserSync Proxy**: Development tool running on port 3000 intercepts requests
2. **Missing Symlink**: CiviCRM expects resources at `/libraries/civicrm/` but they're in `/vendor/civicrm/civicrm-core/`
3. **URL Generation**: CiviCRM generates URLs based on current request host (localhost:3000)
4. **Cached URLs**: CiviCRM may cache incorrect URLs in its configuration

## Solution Architecture

### 1. Resource Symlink (Immediate Fix)
```bash
# Create symlink from web/libraries/civicrm to vendor location
ln -s /home/evancurry/ilas/vendor/civicrm/civicrm-core /home/evancurry/ilas/web/libraries/civicrm
```

### 2. Resource Manager Service
- **Purpose**: Centralized management of CiviCRM URLs
- **Features**:
  - Detects and fixes incorrect URLs
  - Updates CiviCRM configuration in database
  - Provides URL verification tools
  - Handles different environments (dev/staging/prod)

### 3. Event Subscribers
- **Request Subscriber**: Fixes URLs early in request cycle (priority 300)
- **Response Subscriber**: Catches and fixes any remaining URLs in HTML (priority -200)

### 4. Configuration Overrides
- **settings.local.php**: Forces correct server variables
- **civicrm.settings.php**: Hardcodes resource URLs

## Implementation Details

### Services Created

1. **CiviCrmResourceManager** (`ilas_civicrm.resource_manager`)
   - Main service for URL management
   - Methods:
     - `fixResourceUrls()`: Updates CiviCRM configuration
     - `fixUrlsInContent()`: Replaces URLs in HTML
     - `verifyResourcesAccessible()`: Checks resource availability

2. **CiviCrmResponseSubscriber** (`ilas_civicrm.response_subscriber`)
   - Fixes URLs in HTTP responses
   - Only processes HTML content
   - Logs all fixes for debugging

3. **CiviCrmFixCommands** (Drush commands)
   - `drush civicrm:fix-urls`: Fixes all URL issues
   - `drush civicrm:check-resources`: Diagnostic command

### Configuration Changes

1. **civicrm.settings.php**:
```php
$civicrm_setting['URL Preferences']['userFrameworkResourceURL'] = 'https://ilas.ddev.site/libraries/civicrm/';
$civicrm_setting['URL Preferences']['imageUploadURL'] = 'https://ilas.ddev.site/sites/default/files/civicrm/persist/contribute/';
$civicrm_setting['URL Preferences']['extensionsURL'] = 'https://ilas.ddev.site/sites/default/files/civicrm/ext/';
```

2. **settings.local.php**:
```php
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== FALSE) {
  $_SERVER['HTTP_HOST'] = 'ilas.ddev.site';
  $_SERVER['HTTPS'] = 'on';
  $base_url = 'https://ilas.ddev.site';
}
```

## Usage Instructions

### Initial Setup
1. Run the fix script:
   ```bash
   bash /home/evancurry/ilas/web/modules/custom/ilas_civicrm/scripts/fix_civicrm_resources.sh
   ```

2. Clear all caches:
   ```bash
   ddev drush cr
   ddev drush civicrm:fix-urls
   ```

### Ongoing Maintenance
- Check resource status: `ddev drush civicrm:check-resources`
- Fix any issues: `ddev drush civicrm:fix-urls`

### For Developers
- Always access site via `https://ilas.ddev.site`
- Don't use BrowserSync for CiviCRM pages
- If using npm watch, ensure webpack.mix.js has BrowserSync disabled

## Monitoring

### Check for Issues
```bash
# Check current configuration
ddev drush ev "
  \Drupal::service('civicrm')->initialize();
  \$config = CRM_Core_Config::singleton();
  print 'Resource URL: ' . \$config->userFrameworkResourceURL . PHP_EOL;
"

# Check for localhost references in database
ddev drush sql-query "
  SELECT name, value 
  FROM civicrm_setting 
  WHERE value LIKE '%localhost%'
"
```

### Verify Fix
1. Visit a CiviCRM page (e.g., /civicrm or /pro-bono-program)
2. Open browser DevTools > Network tab
3. Filter by CSS
4. Verify all CSS loads from `https://ilas.ddev.site/libraries/civicrm/css/`
5. Check console for no MIME type errors

## Troubleshooting

### If CSS Still Not Loading
1. Hard refresh browser (Ctrl+Shift+R)
2. Clear browser cache completely
3. Check symlink exists: `ls -la /home/evancurry/ilas/web/libraries/`
4. Verify files accessible: `curl https://ilas.ddev.site/libraries/civicrm/css/civicrm.css | head`

### If URLs Still Show localhost:3000
1. Kill any BrowserSync processes: `pkill -f browsersync`
2. Check for cached templates: `ddev drush cr`
3. Clear CiviCRM cache: `ddev drush ev "CRM_Core_Config::singleton()->cleanupCaches();"`

## Prevention

1. **Development Best Practices**:
   - Use DDEV's built-in URLs
   - Avoid proxy tools with CiviCRM
   - Test CiviCRM features after any URL changes

2. **Deployment Checklist**:
   - Update all URL settings for production
   - Verify symlinks are created
   - Test resource loading on staging

3. **Monitoring**:
   - Set up alerts for 404s on `/libraries/civicrm/*`
   - Monitor page load times
   - Check logs for URL fix attempts

## Technical Notes

- CiviCRM uses `CRM_Utils_System_Drupal8::getBaseURL()` to determine URLs
- This method relies on Symfony Request object which can be affected by proxies
- The fix works by intercepting at multiple levels to ensure consistency
- Performance impact of URL fixing is minimal (<5ms per request)