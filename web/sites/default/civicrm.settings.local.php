<?php
/**
 * CiviCRM Local Settings Override
 * 
 * This file provides dynamic path resolution for CiviCRM that works both
 * inside DDEV containers and on the host system.
 */

// Determine if we're running inside DDEV container
$is_ddev = !empty($_SERVER['IS_DDEV_PROJECT']) || (isset($_SERVER['DDEV_HOSTNAME']) && $_SERVER['DDEV_HOSTNAME'] !== '');

if ($is_ddev) {
    // Inside DDEV container - use container paths
    $base_path = '/var/www/html/web';
    $project_root = '/var/www/html';
} else {
    // Outside DDEV - use dynamic path resolution
    $base_path = defined('DRUPAL_ROOT') ? DRUPAL_ROOT : realpath(__DIR__ . '/../..');
    $project_root = realpath($base_path . '/..');
}

// Override the paths
$civicrm_paths['civicrm.files']['path'] = $base_path . '/sites/default/files/civicrm';
$civicrm_paths['civicrm.private']['path'] = $base_path . '/sites/default/files/civicrm';
$civicrm_paths['civicrm.root']['path'] = $project_root . '/vendor/civicrm/civicrm-core';

// Override civicrm_root
$civicrm_root = $project_root . '/vendor/civicrm/civicrm-core/';

// Override template compile dir if needed
if (defined('CIVICRM_TEMPLATE_COMPILEDIR')) {
    // Need to redefine since constants can't be changed
    if (!$is_ddev && CIVICRM_TEMPLATE_COMPILEDIR === '/var/www/html/web/sites/default/files/civicrm/templates_c') {
        // We can't override a constant, but we can set a variable that CiviCRM might use
        $civicrm_paths['civicrm.compile']['path'] = $base_path . '/sites/default/files/civicrm/templates_c';
    }
}