# ILAS Drupal Site Performance Audit Report

## Executive Summary

This audit reveals several critical performance issues that need to be addressed before moving to production. The site currently has development settings that override production performance configurations, which will significantly impact site speed and scalability.

## 1. Current Cache Configuration

### ❌ Critical Issues Found:

**Development Cache Overrides Active**
- File: `/sites/default/settings.local.php`
- Lines 35-37: All major cache bins are set to `cache.backend.null`
  ```php
  $settings['cache']['bins']['render'] = 'cache.backend.null';
  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
  $settings['cache']['bins']['page'] = 'cache.backend.null';
  ```
- **Impact**: No caching is happening for critical page components

### ✅ Redis Configuration Present but Inactive
- File: `/sites/default/redis.settings.inc` exists with proper configuration
- Redis is configured to handle multiple cache bins
- **Issue**: Development settings override Redis configuration

### Page Cache Settings
- Database shows page cache max_age is set to 900 seconds (15 minutes)
- This is reasonable for production but currently disabled by development settings

## 2. CSS/JS Aggregation Settings

### ❌ Critical Issues Found:

**Aggregation Disabled**
- File: `/sites/default/settings.local.php`
- Lines 31-32 and 45-46 (duplicate entries):
  ```php
  $config['system.performance']['css']['preprocess'] = FALSE;
  $config['system.performance']['js']['preprocess'] = FALSE;
  ```
- Database confirms: Both CSS and JS preprocessing are disabled
- **Impact**: Each page load requires multiple HTTP requests for assets

## 3. Performance Optimization Settings

### ✅ Positive Findings:
- Fast 404 pages are enabled for static assets
- Gzip compression is enabled for CSS/JS (when aggregation is on)
- Proper cache contexts configuration in default.services.yml

### ⚠️ Areas of Concern:
- No CDN configuration found
- No explicit reverse proxy configuration (commented out in settings.php)
- Large database tables that may need optimization:
  - `time_zone_transition`: 6.71 MB
  - `key_value`: 5.52 MB
  - `search_api_db_content_text`: 4.95 MB
  - Multiple cache tables over 4 MB each

## 4. BigPipe Status

### ✅ BigPipe is Enabled
- Module is active and should help with perceived performance
- However, effectiveness is limited when caching is disabled

## 5. Cache Contexts and Tags Configuration

### ✅ Proper Configuration
- Default required cache contexts are set: `['languages:language_interface', 'theme', 'user.permissions']`
- Auto-placeholder conditions are properly configured for high-cardinality contexts

## 6. Custom Module Performance Impact

### ⚠️ Potential Issues:
- Multiple custom modules found but no obvious performance bottlenecks in code
- No custom cache implementations found
- Recommend profiling under load

## 7. Development vs Production Settings

### ❌ Major Issue:

**Development settings are overriding production configurations:**
1. All caching disabled
2. CSS/JS aggregation disabled
3. Verbose error logging enabled
4. Twig debugging potentially enabled

## 8. Image Optimization

### ✅ Good Implementation:
- Multiple WebP image styles configured (16:9, 1:1, 2:3, 3:2 ratios)
- Responsive image module enabled
- Focal point integration for smart cropping

### ⚠️ Missing:
- No lazy loading module detected
- No image optimization service integration

## 9. CDN/Reverse Proxy Configuration

### ❌ Not Configured:
- No CDN settings found
- Reverse proxy settings are commented out in settings.php
- No custom headers for cache control

## 10. Database Performance

### ⚠️ Monitoring Enabled:
- Slow query log is enabled (10-second threshold)
- Large watchdog table (2.13 MB) should be pruned regularly
- Cache tables are large but will be cleared on cache rebuild

## Recommendations for Production

### Immediate Actions Required:

1. **Remove or Disable Development Settings**
   ```bash
   # Option 1: Rename the file
   mv sites/default/settings.local.php sites/default/settings.local.php.dev
   
   # Option 2: Add production check
   if (getenv('ENVIRONMENT') !== 'production') {
     include $app_root . '/' . $site_path . '/settings.local.php';
   }
   ```

2. **Enable CSS/JS Aggregation**
   ```bash
   drush config:set system.performance css.preprocess 1 -y
   drush config:set system.performance js.preprocess 1 -y
   ```

3. **Configure Redis Properly**
   - Ensure Redis service is running in production
   - Verify Redis connection settings match production environment

4. **Set Up Reverse Proxy (if applicable)**
   ```php
   $settings['reverse_proxy'] = TRUE;
   $settings['reverse_proxy_addresses'] = ['10.0.0.1', '10.0.0.2'];
   ```

5. **Implement Lazy Loading**
   ```bash
   composer require drupal/lazy
   drush en lazy -y
   ```

6. **Database Maintenance**
   ```bash
   # Clear old watchdog entries
   drush watchdog:delete all
   
   # Optimize tables
   drush sql:query "OPTIMIZE TABLE cache_config, cache_data, cache_default, cache_discovery"
   ```

### Additional Recommendations:

1. **Implement APCu for Class Loading**
   - Uncomment in settings.php: `$settings['class_loader_auto_detect'] = TRUE;`

2. **Configure File CDN**
   ```php
   $settings['file_public_base_url'] = 'https://cdn.example.com/files';
   ```

3. **Add Browser Caching Headers**
   - Already configured in .htaccess with 1-year expiration

4. **Monitor Performance**
   - Install New Relic or similar APM
   - Enable periodic cache warming
   - Monitor slow query log

5. **Load Testing**
   - Test with production-like settings
   - Identify bottlenecks under load
   - Optimize custom module queries if needed

## Conclusion

The site has good performance infrastructure in place (Redis, BigPipe, image optimization) but is severely hampered by development settings that disable all caching and aggregation. These settings MUST be removed or properly conditioned before deploying to production, or the site will experience significant performance issues under any meaningful load.