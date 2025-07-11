<?php

/**
 * @file
 * Test script for ILAS CiviCRM integration.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal
$autoloader = require_once 'web/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$kernel->prepareLegacyRequest();

// Initialize CiviCRM
\Drupal::service('civicrm')->initialize();

print "=== ILAS CiviCRM Integration Test ===\n\n";

// Test 1: Check CiviCRM is initialized
try {
  $version = civicrm_api3('System', 'get', []);
  print "✓ CiviCRM Version: " . $version['values'][0]['version'] . "\n";
} catch (Exception $e) {
  print "✗ CiviCRM initialization failed: " . $e->getMessage() . "\n";
  exit(1);
}

// Test 2: Check custom services
print "\n--- Testing Custom Services ---\n";

// Chatbot service
if (\Drupal::hasService('ilas_civicrm.chatbot')) {
  print "✓ Chatbot CiviCRM service registered\n";
  $chatbot = \Drupal::service('ilas_civicrm.chatbot');
  print "  - Service class: " . get_class($chatbot) . "\n";
} else {
  print "✗ Chatbot service not found\n";
}

// Webform processor service
if (\Drupal::hasService('ilas_civicrm.webform_processor')) {
  print "✓ Webform processor service registered\n";
  $processor = \Drupal::service('ilas_civicrm.webform_processor');
  print "  - Service class: " . get_class($processor) . "\n";
} else {
  print "✗ Webform processor service not found\n";
}

// Dashboard service
if (\Drupal::hasService('ilas_civicrm.dashboard')) {
  print "✓ Dashboard service registered\n";
  $dashboard = \Drupal::service('ilas_civicrm.dashboard');
  print "  - Service class: " . get_class($dashboard) . "\n";
  
  // Test dashboard statistics
  print "\n--- Dashboard Statistics ---\n";
  $stats = $dashboard->getStatistics();
  print "  - Total contacts: " . $stats['contacts']['total'] . "\n";
  print "  - Open cases: " . $stats['cases']['open'] . "\n";
  print "  - Activities this month: " . $stats['activities']['total_this_month'] . "\n";
} else {
  print "✗ Dashboard service not found\n";
}

// Test 3: Check routes
print "\n--- Testing Routes ---\n";
$router = \Drupal::service('router.route_provider');

$routes = [
  'ilas_civicrm.chatbot_webhook_enhanced' => '/api/chatbot/webhook/enhanced',
  'ilas_civicrm.chatbot_context' => '/api/chatbot/context',
  'ilas_civicrm.dashboard' => '/admin/ilas/civicrm-dashboard',
  'ilas_civicrm.settings' => '/admin/config/ilas/civicrm',
];

foreach ($routes as $name => $path) {
  try {
    $route = $router->getRouteByName($name);
    print "✓ Route '$name' registered at $path\n";
  } catch (Exception $e) {
    print "✗ Route '$name' not found\n";
  }
}

// Test 4: Check configuration
print "\n--- Testing Configuration ---\n";
$config = \Drupal::config('ilas_civicrm.settings');
print "  - Webform integration: " . ($config->get('enable_webform_integration') ? 'Enabled' : 'Disabled') . "\n";
print "  - Chatbot integration: " . ($config->get('enable_chatbot_integration') ? 'Enabled' : 'Disabled') . "\n";
print "  - Auto case creation: " . ($config->get('create_cases_automatically') ? 'Enabled' : 'Disabled') . "\n";

// Test 5: Test activity types
print "\n--- Testing Activity Types ---\n";
$activity_types = [
  'Legal Help Request',
  'Eligibility Check',
  'Resource Request',
  'Volunteer Application',
  'Chatbot Interaction',
];

foreach ($activity_types as $type) {
  try {
    $result = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'activity_type',
      'label' => $type,
    ]);
    if ($result['count'] > 0) {
      print "✓ Activity type '$type' exists\n";
    } else {
      print "✗ Activity type '$type' not found\n";
    }
  } catch (Exception $e) {
    print "✗ Error checking activity type '$type': " . $e->getMessage() . "\n";
  }
}

print "\n=== Integration Test Complete ===\n";