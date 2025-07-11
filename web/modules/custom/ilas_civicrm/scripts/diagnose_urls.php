<?php

/**
 * @file
 * Diagnostic script for CiviCRM URL issues.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once 'web/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$kernel->prepareLegacyRequest();

print "=== CiviCRM URL Diagnostics ===\n\n";

// Check server variables
print "Server Variables:\n";
print "  HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
print "  SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'not set') . "\n";
print "  REQUEST_SCHEME: " . ($_SERVER['REQUEST_SCHEME'] ?? 'not set') . "\n";
print "  HTTPS: " . ($_SERVER['HTTPS'] ?? 'not set') . "\n";
print "\n";

// Check Drupal request
print "Drupal Request:\n";
print "  Host: " . \Drupal::request()->getHost() . "\n";
print "  Scheme: " . \Drupal::request()->getScheme() . "\n";
print "  Base URL: " . \Drupal::request()->getSchemeAndHttpHost() . "\n";
print "\n";

// Initialize CiviCRM
\Drupal::service('civicrm')->initialize();

print "CiviCRM Configuration:\n";
$config = CRM_Core_Config::singleton();
print "  userFrameworkBaseURL: " . $config->userFrameworkBaseURL . "\n";
print "  userFrameworkResourceURL: " . $config->userFrameworkResourceURL . "\n";
print "  imageUploadURL: " . $config->imageUploadURL . "\n";
print "  extensionsURL: " . ($config->extensionsURL ?? 'not set') . "\n";
print "\n";

// Check if CIVICRM_UF_BASEURL is defined
print "Constants:\n";
print "  CIVICRM_UF_BASEURL: " . (defined('CIVICRM_UF_BASEURL') ? CIVICRM_UF_BASEURL : 'not defined') . "\n";
print "\n";

// Test resource URL generation
print "Test Resource URLs:\n";
$resourceUrl = CRM_Core_Resources::singleton()->getUrl('civicrm', 'css/civicrm.css');
print "  civicrm.css URL: " . $resourceUrl . "\n";

$resourceUrl2 = CRM_Core_Resources::singleton()->getUrl('civicrm', 'css/crm-i.css');
print "  crm-i.css URL: " . $resourceUrl2 . "\n";
print "\n";

// Check for any localhost:3000 references
print "Checking for localhost:3000 issues:\n";
$issues = [];

if (strpos($config->userFrameworkBaseURL, 'localhost:3000') !== FALSE) {
  $issues[] = "userFrameworkBaseURL contains localhost:3000";
}
if (strpos($config->userFrameworkResourceURL, 'localhost:3000') !== FALSE) {
  $issues[] = "userFrameworkResourceURL contains localhost:3000";
}
if (strpos($resourceUrl, 'localhost:3000') !== FALSE) {
  $issues[] = "Generated resource URLs contain localhost:3000";
}

if (empty($issues)) {
  print "  ✓ No localhost:3000 references found\n";
} else {
  print "  ✗ Issues found:\n";
  foreach ($issues as $issue) {
    print "    - $issue\n";
  }
}

print "\n=== End Diagnostics ===\n";