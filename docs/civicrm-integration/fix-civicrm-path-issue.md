# CiviCRM Path Resolution Fix

## Problem
The CiviCRM ClassLoader.php file couldn't be found because `civicrm.settings.php` contained hardcoded paths pointing to `/var/www/html/` (the DDEV container's internal path) instead of using dynamic path resolution that works both inside and outside the DDEV container.

## Root Cause
When CiviCRM was installed via DDEV, the installer hardcoded the container's internal paths into `civicrm.settings.php`:
- `/var/www/html/vendor/civicrm/civicrm-core/`
- `/var/www/html/web/sites/default/files/civicrm`

These paths don't exist on the host system where the actual project is located at `/home/evancurry/ilas/`.

## Solution Implemented

### 1. Updated civicrm.settings.php
Modified the hardcoded paths to use dynamic path resolution:

```php
// Dynamically determine the base path
$base_path = defined('DRUPAL_ROOT') ? DRUPAL_ROOT : realpath(__DIR__ . '/../..');
$project_root = realpath($base_path . '/..');

$civicrm_paths['civicrm.files']['path'] = $base_path . '/sites/default/files/civicrm';
$civicrm_paths['civicrm.private']['path'] = $base_path . '/sites/default/files/civicrm';
$civicrm_paths['civicrm.root']['path'] = $project_root . '/vendor/civicrm/civicrm-core';
```

### 2. Created civicrm.settings.local.php
Added a local settings file that can detect whether the code is running inside DDEV or on the host system and adjust paths accordingly.

### 3. Added Include Statement
Modified `civicrm.settings.php` to include the local settings file:

```php
// Include local settings overrides if they exist
if (file_exists(__DIR__ . '/civicrm.settings.local.php')) {
    require_once __DIR__ . '/civicrm.settings.local.php';
}
```

## Benefits
- Works both inside DDEV containers and on the host system
- No need to manually update paths when switching environments
- Maintains compatibility with the original DDEV setup
- Allows for environment-specific overrides

## Files Modified
1. `/home/evancurry/ilas/web/sites/default/civicrm.settings.php` - Updated with dynamic path resolution
2. `/home/evancurry/ilas/web/sites/default/civicrm.settings.local.php` - Created for environment detection

## Backup
A backup of the original civicrm.settings.php was created with timestamp suffix.