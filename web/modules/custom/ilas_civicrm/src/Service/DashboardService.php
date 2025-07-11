<?php

namespace Drupal\ilas_civicrm\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for CiviCRM dashboard data.
 */
class DashboardService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a DashboardService.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->logger = $logger_factory->get('ilas_civicrm');
  }

  /**
   * Get dashboard statistics.
   *
   * @return array
   *   Array of dashboard statistics.
   */
  public function getStatistics() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $stats = [
        'contacts' => $this->getContactStats(),
        'cases' => $this->getCaseStats(),
        'activities' => $this->getActivityStats(),
        'contributions' => $this->getContributionStats(),
        'recent_activities' => $this->getRecentActivities(),
      ];
      
      return $stats;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get dashboard statistics: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }

  /**
   * Get contact statistics.
   */
  protected function getContactStats() {
    $stats = [
      'total' => 0,
      'individuals' => 0,
      'organizations' => 0,
      'new_this_month' => 0,
    ];
    
    // Total contacts
    $total = civicrm_api3('Contact', 'getcount', [
      'is_deleted' => 0,
    ]);
    $stats['total'] = $total;
    
    // Individuals
    $individuals = civicrm_api3('Contact', 'getcount', [
      'contact_type' => 'Individual',
      'is_deleted' => 0,
    ]);
    $stats['individuals'] = $individuals;
    
    // Organizations
    $organizations = civicrm_api3('Contact', 'getcount', [
      'contact_type' => 'Organization',
      'is_deleted' => 0,
    ]);
    $stats['organizations'] = $organizations;
    
    // New this month
    $first_of_month = date('Y-m-01');
    $new_contacts = civicrm_api3('Contact', 'getcount', [
      'created_date' => ['>' => $first_of_month],
      'is_deleted' => 0,
    ]);
    $stats['new_this_month'] = $new_contacts;
    
    return $stats;
  }

  /**
   * Get case statistics.
   */
  protected function getCaseStats() {
    $stats = [
      'total' => 0,
      'open' => 0,
      'urgent' => 0,
      'closed_this_month' => 0,
      'by_type' => [],
    ];
    
    // Total cases
    $total = civicrm_api3('Case', 'getcount', [
      'is_deleted' => 0,
    ]);
    $stats['total'] = $total;
    
    // Open cases
    $open = civicrm_api3('Case', 'getcount', [
      'is_deleted' => 0,
      'status_id' => ['!=' => 'Closed'],
    ]);
    $stats['open'] = $open;
    
    // Urgent cases (custom logic)
    $urgent = civicrm_api3('Case', 'get', [
      'is_deleted' => 0,
      'status_id' => ['!=' => 'Closed'],
      'return' => ['id'],
      'options' => ['limit' => 0],
    ]);
    
    $urgent_count = 0;
    foreach ($urgent['values'] as $case) {
      // Check for urgent tag or priority
      $activities = civicrm_api3('Activity', 'get', [
        'case_id' => $case['id'],
        'priority_id' => 'Urgent',
        'is_deleted' => 0,
        'options' => ['limit' => 1],
      ]);
      
      if ($activities['count'] > 0) {
        $urgent_count++;
      }
    }
    $stats['urgent'] = $urgent_count;
    
    // Closed this month
    $first_of_month = date('Y-m-01');
    $closed = civicrm_api3('Case', 'getcount', [
      'is_deleted' => 0,
      'status_id' => 'Closed',
      'modified_date' => ['>' => $first_of_month],
    ]);
    $stats['closed_this_month'] = $closed;
    
    // Cases by type
    $case_types = civicrm_api3('Case', 'get', [
      'is_deleted' => 0,
      'return' => ['case_type_id'],
      'options' => ['limit' => 0],
    ]);
    
    $type_counts = [];
    foreach ($case_types['values'] as $case) {
      $type = $case['case_type_id'];
      if (!isset($type_counts[$type])) {
        $type_counts[$type] = 0;
      }
      $type_counts[$type]++;
    }
    
    $stats['by_type'] = $type_counts;
    
    return $stats;
  }

  /**
   * Get activity statistics.
   */
  protected function getActivityStats() {
    $stats = [
      'total_this_month' => 0,
      'scheduled' => 0,
      'completed' => 0,
      'by_type' => [],
    ];
    
    $first_of_month = date('Y-m-01');
    
    // Total activities this month
    $total = civicrm_api3('Activity', 'getcount', [
      'activity_date_time' => ['>' => $first_of_month],
      'is_deleted' => 0,
    ]);
    $stats['total_this_month'] = $total;
    
    // Scheduled activities
    $scheduled = civicrm_api3('Activity', 'getcount', [
      'status_id' => 'Scheduled',
      'is_deleted' => 0,
    ]);
    $stats['scheduled'] = $scheduled;
    
    // Completed this month
    $completed = civicrm_api3('Activity', 'getcount', [
      'status_id' => 'Completed',
      'activity_date_time' => ['>' => $first_of_month],
      'is_deleted' => 0,
    ]);
    $stats['completed'] = $completed;
    
    // Activities by type (this month)
    $activities = civicrm_api3('Activity', 'get', [
      'activity_date_time' => ['>' => $first_of_month],
      'is_deleted' => 0,
      'return' => ['activity_type_id'],
      'options' => ['limit' => 0],
    ]);
    
    $type_counts = [];
    foreach ($activities['values'] as $activity) {
      $type = $activity['activity_type_id'];
      if (!isset($type_counts[$type])) {
        $type_counts[$type] = 0;
      }
      $type_counts[$type]++;
    }
    
    $stats['by_type'] = $type_counts;
    
    return $stats;
  }

  /**
   * Get contribution statistics.
   */
  protected function getContributionStats() {
    $stats = [
      'total_this_month' => 0,
      'total_amount_this_month' => 0,
      'recurring_active' => 0,
      'average_donation' => 0,
    ];
    
    $first_of_month = date('Y-m-01');
    
    // Total contributions this month
    $contributions = civicrm_api3('Contribution', 'get', [
      'receive_date' => ['>' => $first_of_month],
      'contribution_status_id' => 'Completed',
      'return' => ['total_amount'],
      'options' => ['limit' => 0],
    ]);
    
    $stats['total_this_month'] = $contributions['count'];
    
    $total_amount = 0;
    foreach ($contributions['values'] as $contribution) {
      $total_amount += $contribution['total_amount'];
    }
    $stats['total_amount_this_month'] = $total_amount;
    
    if ($stats['total_this_month'] > 0) {
      $stats['average_donation'] = $total_amount / $stats['total_this_month'];
    }
    
    // Active recurring contributions
    $recurring = civicrm_api3('ContributionRecur', 'getcount', [
      'contribution_status_id' => ['IN' => ['In Progress', 'Pending']],
    ]);
    $stats['recurring_active'] = $recurring;
    
    return $stats;
  }

  /**
   * Get recent activities.
   */
  protected function getRecentActivities() {
    $activities = civicrm_api3('Activity', 'get', [
      'is_deleted' => 0,
      'options' => [
        'limit' => 10,
        'sort' => 'activity_date_time DESC',
      ],
      'return' => ['activity_type_id', 'subject', 'activity_date_time', 'status_id', 'source_contact_id'],
    ]);
    
    $recent = [];
    foreach ($activities['values'] as $activity) {
      // Get contact name
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $activity['source_contact_id'],
        'return' => ['display_name'],
      ]);
      
      $recent[] = [
        'id' => $activity['id'],
        'type' => $activity['activity_type_id'],
        'subject' => $activity['subject'],
        'date' => $activity['activity_date_time'],
        'status' => $activity['status_id'],
        'contact' => $contact['display_name'],
      ];
    }
    
    return $recent;
  }

  /**
   * Get service area breakdown.
   */
  public function getServiceAreaBreakdown() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get all service area tags
      $tags = civicrm_api3('Tag', 'get', [
        'used_for' => 'civicrm_contact',
        'label' => ['LIKE' => '%Service Area%'],
        'options' => ['limit' => 0],
      ]);
      
      $breakdown = [];
      foreach ($tags['values'] as $tag) {
        // Count contacts with this tag
        $count = civicrm_api3('EntityTag', 'getcount', [
          'entity_table' => 'civicrm_contact',
          'tag_id' => $tag['id'],
        ]);
        
        $breakdown[] = [
          'area' => $tag['label'],
          'count' => $count,
        ];
      }
      
      // Sort by count descending
      usort($breakdown, function($a, $b) {
        return $b['count'] - $a['count'];
      });
      
      return $breakdown;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get service area breakdown: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }

  /**
   * Get office performance metrics.
   */
  public function getOfficeMetrics() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get all office organizations
      $offices = civicrm_api3('Contact', 'get', [
        'contact_type' => 'Organization',
        'contact_sub_type' => 'Legal_Services_Office',
        'is_deleted' => 0,
        'options' => ['limit' => 0],
      ]);
      
      $metrics = [];
      foreach ($offices['values'] as $office) {
        // Get staff count (employees of this org)
        $staff_count = civicrm_api3('Relationship', 'getcount', [
          'contact_id_b' => $office['id'],
          'relationship_type_id' => 5, // Employee of
          'is_active' => 1,
        ]);
        
        // Get active cases assigned to this office
        $cases_count = 0; // This would require custom field or tag logic
        
        // Get activities this month
        $first_of_month = date('Y-m-01');
        $activities_count = civicrm_api3('Activity', 'getcount', [
          'source_contact_id' => $office['id'],
          'activity_date_time' => ['>' => $first_of_month],
          'is_deleted' => 0,
        ]);
        
        $metrics[] = [
          'office' => $office['display_name'],
          'staff' => $staff_count,
          'active_cases' => $cases_count,
          'activities_this_month' => $activities_count,
        ];
      }
      
      return $metrics;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get office metrics: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }
}