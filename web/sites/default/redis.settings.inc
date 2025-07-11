<?php

/**
 * @file
 * Redis configuration for improved caching performance.
 */

// Check if Redis module is enabled and Redis is available.
if (extension_loaded('redis') && class_exists('Drupal\redis\ClientFactory')) {
  
  // Redis connection settings.
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = getenv('REDIS_HOST') ?: 'localhost';
  $settings['redis.connection']['port'] = getenv('REDIS_PORT') ?: 6379;
  
  // Optional: Redis password if configured.
  if ($redis_password = getenv('REDIS_PASSWORD')) {
    $settings['redis.connection']['password'] = $redis_password;
  }
  
  // Use Redis for cache bins.
  $settings['cache']['default'] = 'cache.backend.redis';
  
  // Set specific cache bins to use Redis.
  $cache_bins = [
    'bootstrap',
    'config',
    'container',
    'data',
    'default',
    'discovery',
    'dynamic_page_cache',
    'entity',
    'menu',
    'page',
    'render',
    'toolbar',
  ];
  
  foreach ($cache_bins as $bin) {
    $settings['cache']['bins'][$bin] = 'cache.backend.redis';
  }
  
  // Optional: Use Redis for flood control.
  $settings['redis_flood_storage'] = TRUE;
  
  // Performance settings.
  $settings['redis.settings']['compression_level'] = 1;
  $settings['redis.settings']['compression_threshold'] = 100;
  
  // Use Redis for lock backend (optional but recommended).
  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';
  
  // Cache tag checksum backend.
  $settings['cache_tags.invalidator.checksum'] = 'redis';
}