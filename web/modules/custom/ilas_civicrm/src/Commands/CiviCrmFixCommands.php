<?php

namespace Drupal\ilas_civicrm\Commands;

use Drush\Commands\DrushCommands;
use Drupal\ilas_civicrm\Service\CiviCrmResourceManager;

/**
 * Drush commands for fixing CiviCRM issues.
 */
class CiviCrmFixCommands extends DrushCommands {

  /**
   * The resource manager.
   *
   * @var \Drupal\ilas_civicrm\Service\CiviCrmResourceManager
   */
  protected $resourceManager;

  /**
   * Constructs a CiviCrmFixCommands object.
   */
  public function __construct(CiviCrmResourceManager $resource_manager) {
    parent::__construct();
    $this->resourceManager = $resource_manager;
  }

  /**
   * Fix CiviCRM resource URLs.
   *
   * @command civicrm:fix-urls
   * @aliases cfu
   * @usage drush civicrm:fix-urls
   *   Fix all CiviCRM resource URLs to use the correct domain.
   */
  public function fixUrls() {
    $this->output()->writeln('Fixing CiviCRM resource URLs...');
    
    // Fix URLs
    $fixed = $this->resourceManager->fixResourceUrls();
    
    if ($fixed) {
      $this->output()->writeln('<info>✓ CiviCRM URLs have been fixed.</info>');
    } else {
      $this->output()->writeln('<comment>No URL fixes were needed.</comment>');
    }
    
    // Verify resources
    $this->output()->writeln("\nVerifying resource accessibility:");
    $results = $this->resourceManager->verifyResourcesAccessible();
    
    foreach ($results as $resource => $info) {
      if ($info['exists'] && $info['readable']) {
        $this->output()->writeln("<info>✓ $resource - OK</info>");
      } else {
        $this->output()->writeln("<error>✗ $resource - NOT FOUND</error>");
      }
    }
    
    // Clear caches
    $this->output()->writeln("\nClearing caches...");
    drupal_flush_all_caches();
    
    // Initialize CiviCRM and clear its cache
    try {
      \Drupal::service('civicrm')->initialize();
      \CRM_Core_Config::singleton()->cleanupCaches();
      $this->output()->writeln('<info>✓ Caches cleared successfully.</info>');
    }
    catch (\Exception $e) {
      $this->output()->writeln('<error>Failed to clear CiviCRM cache: ' . $e->getMessage() . '</error>');
    }
    
    $this->output()->writeln("\n<info>Fix complete! Please test the site.</info>");
  }

  /**
   * Check CiviCRM resource status.
   *
   * @command civicrm:check-resources
   * @aliases ccr
   * @usage drush civicrm:check-resources
   *   Check the status of CiviCRM resources and URLs.
   */
  public function checkResources() {
    $this->output()->writeln('Checking CiviCRM resource configuration...');
    
    // Initialize CiviCRM
    try {
      \Drupal::service('civicrm')->initialize();
      $config = \CRM_Core_Config::singleton();
      
      $this->output()->writeln("\n<comment>Current Configuration:</comment>");
      $this->output()->writeln("Base URL: " . $config->userFrameworkBaseURL);
      $this->output()->writeln("Resource URL: " . $config->userFrameworkResourceURL);
      $this->output()->writeln("Image Upload URL: " . $config->imageUploadURL);
      
      // Check for problems
      $problems = [];
      
      if (strpos($config->userFrameworkResourceURL, 'localhost') !== FALSE) {
        $problems[] = "Resource URL contains 'localhost'";
      }
      
      if (strpos($config->userFrameworkResourceURL, ':3000') !== FALSE) {
        $problems[] = "Resource URL contains port 3000 (BrowserSync)";
      }
      
      // Check resource accessibility
      $this->output()->writeln("\n<comment>Resource Accessibility:</comment>");
      $results = $this->resourceManager->verifyResourcesAccessible();
      
      foreach ($results as $resource => $info) {
        if ($info['exists'] && $info['readable']) {
          $this->output()->writeln("<info>✓ $resource</info>");
        } else {
          $this->output()->writeln("<error>✗ $resource - NOT ACCESSIBLE</error>");
          $problems[] = "Resource not accessible: $resource";
        }
      }
      
      // Check symlink
      $libraries_path = DRUPAL_ROOT . '/libraries/civicrm';
      if (is_link($libraries_path)) {
        $target = readlink($libraries_path);
        $this->output()->writeln("\n<comment>Symlink Status:</comment>");
        $this->output()->writeln("<info>✓ /libraries/civicrm → $target</info>");
      } else {
        $problems[] = "/libraries/civicrm symlink is missing";
      }
      
      // Summary
      if (empty($problems)) {
        $this->output()->writeln("\n<info>✓ All checks passed!</info>");
      } else {
        $this->output()->writeln("\n<error>Problems found:</error>");
        foreach ($problems as $problem) {
          $this->output()->writeln("  - $problem");
        }
        $this->output()->writeln("\n<comment>Run 'drush civicrm:fix-urls' to fix these issues.</comment>");
      }
    }
    catch (\Exception $e) {
      $this->output()->writeln('<error>Failed to check CiviCRM: ' . $e->getMessage() . '</error>');
    }
  }
}