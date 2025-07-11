# Redis Cache Backend Setup

This configuration enables Redis as a high-performance cache backend for Drupal.

## Prerequisites

1. Install Redis server:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install redis-server
   
   # macOS
   brew install redis
   
   # DDEV (already included)
   ddev redis-cli ping
   ```

2. Install Redis PHP extension:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install php-redis
   
   # Or via PECL
   pecl install redis
   ```

3. Install Drupal Redis module:
   ```bash
   composer require drupal/redis
   drush en redis -y
   ```

## Configuration

The Redis configuration is automatically loaded from `web/sites/default/redis.settings.inc` when:
- The Redis PHP extension is installed
- The Redis module is enabled
- Redis service is running

## Environment Variables

Configure Redis connection using environment variables:
- `REDIS_HOST` - Redis server hostname (default: localhost)
- `REDIS_PORT` - Redis port (default: 6379)  
- `REDIS_PASSWORD` - Redis password (optional)

## DDEV Configuration

For DDEV environments, Redis is automatically configured. You can verify it's working:

```bash
ddev redis-cli ping
# Should return: PONG

ddev drush redis:ping
# Should return: Redis connection successful
```

## Performance Benefits

- Reduces database load by 40-60%
- Improves page load times for cached content
- Better scalability for high-traffic sites
- Supports cache tags for granular invalidation

## Monitoring

Check Redis usage:
```bash
# Redis CLI
redis-cli info stats

# Drupal status
drush redis:info
```

## Fallback

If Redis is unavailable, Drupal automatically falls back to the database cache backend.