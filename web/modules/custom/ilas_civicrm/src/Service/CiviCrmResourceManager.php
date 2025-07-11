<?php

namespace Drupal\ilas_civicrm\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Manages CiviCRM resource URLs and ensures they are correct.
 */
class CiviCrmResourceManager {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The correct base URL for resources.
   *
   * @var string
   */
  protected $correctBaseUrl;

  /**
   * Constructs a CiviCrmResourceManager.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack
  ) {
    $this->logger = $logger_factory->get('ilas_civicrm');
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    
    // Determine correct base URL from DDEV environment
    if (getenv('IS_DDEV_PROJECT') === 'true') {
      $this->correctBaseUrl = 'https://ilas.ddev.site';
    } else {
      // Production URL would go here
      $this->correctBaseUrl = $this->getBaseUrlFromConfig();
    }
  }

  /**
   * Fix CiviCRM resource URLs in the current environment.
   */
  public function fixResourceUrls() {
    try {
      // Initialize CiviCRM
      \Drupal::service('civicrm')->initialize();
      
      // Get CiviCRM config
      $config = \CRM_Core_Config::singleton();
      
      // Check current URLs
      $currentResourceUrl = $config->userFrameworkResourceURL;
      $expectedResourceUrl = $this->correctBaseUrl . '/libraries/civicrm/';
      
      // Fix if incorrect
      if (strpos($currentResourceUrl, 'localhost') !== FALSE || 
          $currentResourceUrl !== $expectedResourceUrl) {
        
        // Update the config object
        $config->userFrameworkResourceURL = $expectedResourceUrl;
        $config->userFrameworkBaseURL = $this->correctBaseUrl . '/';
        $config->imageUploadURL = $this->correctBaseUrl . '/sites/default/files/civicrm/persist/contribute/';
        
        // Update in database
        $this->updateCiviCrmSettings();
        
        // Clear CiviCRM cache
        \CRM_Core_Config::singleton()->cleanupCaches();
        
        $this->logger->info('Fixed CiviCRM resource URLs from @old to @new', [
          '@old' => $currentResourceUrl,
          '@new' => $expectedResourceUrl,
        ]);
        
        return TRUE;
      }
      
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fix CiviCRM resource URLs: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Update CiviCRM settings in database.
   */
  protected function updateCiviCrmSettings() {
    $settings = [
      'userFrameworkResourceURL' => $this->correctBaseUrl . '/libraries/civicrm/',
      'imageUploadURL' => $this->correctBaseUrl . '/sites/default/files/civicrm/persist/contribute/',
      'customCSSURL' => $this->correctBaseUrl . '/sites/default/files/civicrm/custom_css/',
      'extensionsURL' => $this->correctBaseUrl . '/sites/default/files/civicrm/ext/',
    ];
    
    foreach ($settings as $name => $value) {
      try {
        // Use CiviCRM API to update settings
        civicrm_api3('Setting', 'create', [
          $name => $value,
        ]);
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed to update CiviCRM setting @name: @error', [
          '@name' => $name,
          '@error' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Get base URL from Drupal configuration.
   */
  protected function getBaseUrlFromConfig() {
    $request = $this->requestStack->getCurrentRequest();
    
    if ($request) {
      return $request->getSchemeAndHttpHost();
    }
    
    // Fallback to site config
    $site_config = $this->configFactory->get('system.site');
    $base_url = $site_config->get('base_url');
    
    return $base_url ?: 'https://ilas.ddev.site';
  }

  /**
   * Fix URLs in HTML content.
   */
  public function fixUrlsInContent($content) {
    $patterns = [
      'https://localhost:3000' => $this->correctBaseUrl,
      'http://localhost:3000' => $this->correctBaseUrl,
      'https://localhost' => $this->correctBaseUrl,
      'http://localhost' => $this->correctBaseUrl,
    ];
    
    foreach ($patterns as $search => $replace) {
      $content = str_replace($search, $replace, $content);
    }
    
    return $content;
  }

  /**
   * Check if a URL needs fixing.
   */
  public function isUrlIncorrect($url) {
    $incorrect_patterns = [
      'localhost:3000',
      'localhost/',
      '://localhost',
    ];
    
    foreach ($incorrect_patterns as $pattern) {
      if (strpos($url, $pattern) !== FALSE) {
        return TRUE;
      }
    }
    
    return FALSE;
  }

  /**
   * Fix a single URL.
   */
  public function fixUrl($url) {
    if (!$this->isUrlIncorrect($url)) {
      return $url;
    }
    
    // Replace various incorrect patterns
    $url = preg_replace('#https?://localhost:3000#', $this->correctBaseUrl, $url);
    $url = preg_replace('#https?://localhost/#', $this->correctBaseUrl . '/', $url);
    $url = preg_replace('#https?://localhost#', $this->correctBaseUrl, $url);
    
    return $url;
  }

  /**
   * Verify CiviCRM resources are accessible.
   */
  public function verifyResourcesAccessible() {
    $resources = [
      '/libraries/civicrm/css/civicrm.css',
      '/libraries/civicrm/css/crm-i.css',
      '/libraries/civicrm/js/crm.ajax.js',
    ];
    
    $results = [];
    $webroot = DRUPAL_ROOT;
    
    foreach ($resources as $resource) {
      $path = $webroot . $resource;
      $realpath = realpath($path);
      $results[$resource] = [
        'exists' => file_exists($path) || file_exists($realpath),
        'readable' => is_readable($path) || ($realpath && is_readable($realpath)),
        'url' => $this->correctBaseUrl . $resource,
        'path' => $path,
        'realpath' => $realpath ?: 'not found',
      ];
    }
    
    return $results;
  }
}