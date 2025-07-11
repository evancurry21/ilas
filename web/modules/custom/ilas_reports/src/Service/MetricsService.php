<?php

namespace Drupal\ilas_reports\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Service for calculating metrics.
 */
class MetricsService {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a MetricsService.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    Connection $database,
    CacheBackendInterface $cache,
    TimeInterface $time
  ) {
    $this->logger = $logger_factory->get('ilas_reports');
    $this->database = $database;
    $this->cache = $cache;
    $this->time = $time;
  }

  /**
   * Get metric value.
   */
  public function getMetric($metric_name, $parameters = []) {
    // Check cache first
    $cache_key = 'ilas_reports:metric:' . $metric_name . ':' . md5(serialize($parameters));
    $cached = $this->cache->get($cache_key);
    
    if ($cached) {
      return $cached->data;
    }
    
    // Calculate metric
    $value = $this->calculateMetric($metric_name, $parameters);
    
    // Cache for 1 hour
    $this->cache->set($cache_key, $value, $this->time->getRequestTime() + 3600);
    
    return $value;
  }

  /**
   * Calculate metric.
   */
  protected function calculateMetric($metric_name, $parameters) {
    switch ($metric_name) {
      // Client metrics
      case 'total_clients_served':
        return $this->getTotalClientsServed($parameters);
        
      case 'active_cases':
        return $this->getActiveCases($parameters);
        
      case 'cases_closed_success':
        return $this->getCasesClosedSuccess($parameters);
        
      case 'average_case_duration':
        return $this->getAverageCaseDuration($parameters);
        
      // Financial metrics
      case 'total_donations_ytd':
        return $this->getTotalDonationsYTD($parameters);
        
      case 'donations_month':
        return $this->getDonationsMonth($parameters);
        
      case 'donor_retention':
        return $this->getDonorRetention($parameters);
        
      case 'average_donation':
        return $this->getAverageDonation($parameters);
        
      // Volunteer metrics
      case 'active_volunteers':
        return $this->getActiveVolunteers($parameters);
        
      case 'volunteer_hours_month':
        return $this->getVolunteerHoursMonth($parameters);
        
      case 'pro_bono_value':
        return $this->getProBonoValue($parameters);
        
      case 'volunteer_retention':
        return $this->getVolunteerRetention($parameters);
        
      // Event metrics
      case 'upcoming_events':
        return $this->getUpcomingEvents($parameters);
        
      case 'event_attendance_rate':
        return $this->getEventAttendanceRate($parameters);
        
      // Grant metrics
      case 'grant_utilization':
        return $this->getGrantUtilization($parameters);
        
      case 'grant_deadlines':
        return $this->getGrantDeadlines($parameters);
        
      default:
        return 0;
    }
  }

  /**
   * Get total clients served.
   */
  protected function getTotalClientsServed($parameters) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $start_date = $parameters['start_date'] ?? date('Y-01-01');
      $end_date = $parameters['end_date'] ?? date('Y-m-d');
      
      // Get unique clients from cases
      $sql = "SELECT COUNT(DISTINCT cc.contact_id) as total
              FROM civicrm_case c
              INNER JOIN civicrm_case_contact cc ON c.id = cc.case_id
              WHERE c.created_date BETWEEN %1 AND %2
              AND c.is_deleted = 0";
      
      $params = [
        1 => [$start_date, 'String'],
        2 => [$end_date, 'String'],
      ];
      
      $result = \CRM_Core_DAO::executeQuery($sql, $params);
      $result->fetch();
      
      return [
        'value' => $result->total,
        'period' => [
          'start' => $start_date,
          'end' => $end_date,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get total clients served: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return ['value' => 0];
    }
  }

  /**
   * Get active cases.
   */
  protected function getActiveCases($parameters) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $count = civicrm_api3('Case', 'getcount', [
        'status_id' => ['NOT IN' => ['Closed', 'Rejected']],
        'is_deleted' => 0,
      ]);
      
      // Get change from last period
      $last_month = civicrm_api3('Case', 'getcount', [
        'status_id' => ['NOT IN' => ['Closed', 'Rejected']],
        'is_deleted' => 0,
        'created_date' => ['<' => date('Y-m-01')],
      ]);
      
      $change = $count - $last_month;
      $change_percent = $last_month > 0 ? ($change / $last_month * 100) : 0;
      
      return [
        'value' => $count,
        'change' => $change,
        'change_percent' => round($change_percent, 1),
        'trend' => $change >= 0 ? 'up' : 'down',
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get active cases: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return ['value' => 0];
    }
  }

  /**
   * Get cases closed successfully.
   */
  protected function getCasesClosedSuccess($parameters) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $start_date = $parameters['start_date'] ?? date('Y-m-01');
      $end_date = $parameters['end_date'] ?? date('Y-m-d');
      
      // Get closed cases with successful outcome
      $sql = "SELECT COUNT(*) as total
              FROM civicrm_case c
              WHERE c.status_id IN (
                SELECT value FROM civicrm_option_value 
                WHERE option_group_id = (
                  SELECT id FROM civicrm_option_group 
                  WHERE name = 'case_status'
                )
                AND name IN ('Closed', 'Resolved')
              )
              AND c.modified_date BETWEEN %1 AND %2
              AND c.is_deleted = 0";
      
      $params = [
        1 => [$start_date, 'String'],
        2 => [$end_date, 'String'],
      ];
      
      $result = \CRM_Core_DAO::executeQuery($sql, $params);
      $result->fetch();
      
      return [
        'value' => $result->total,
        'period' => [
          'start' => $start_date,
          'end' => $end_date,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get successful case closures: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return ['value' => 0];
    }
  }

  /**
   * Get total donations YTD.
   */
  protected function getTotalDonationsYTD($parameters) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $year_start = date('Y-01-01');
      $today = date('Y-m-d');
      
      $result = civicrm_api3('Contribution', 'get', [
        'receive_date' => [
          '>=' => $year_start,
          '<=' => $today,
        ],
        'contribution_status_id' => 'Completed',
        'options' => ['limit' => 0],
        'return' => ['total_amount'],
      ]);
      
      $total = 0;
      foreach ($result['values'] as $contribution) {
        $total += $contribution['total_amount'];
      }
      
      // Get last year's total for comparison
      $last_year_start = date('Y-01-01', strtotime('-1 year'));
      $last_year_end = date('Y-m-d', strtotime('-1 year'));
      
      $last_year_result = civicrm_api3('Contribution', 'get', [
        'receive_date' => [
          '>=' => $last_year_start,
          '<=' => $last_year_end,
        ],
        'contribution_status_id' => 'Completed',
        'options' => ['limit' => 0],
        'return' => ['total_amount'],
      ]);
      
      $last_year_total = 0;
      foreach ($last_year_result['values'] as $contribution) {
        $last_year_total += $contribution['total_amount'];
      }
      
      $change = $total - $last_year_total;
      $change_percent = $last_year_total > 0 ? ($change / $last_year_total * 100) : 0;
      
      return [
        'value' => $total,
        'formatted' => '$' . number_format($total, 2),
        'change' => $change,
        'change_percent' => round($change_percent, 1),
        'trend' => $change >= 0 ? 'up' : 'down',
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get YTD donations: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return ['value' => 0, 'formatted' => '$0.00'];
    }
  }

  /**
   * Get volunteer hours for month.
   */
  protected function getVolunteerHoursMonth($parameters) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $start_date = date('Y-m-01');
      $end_date = date('Y-m-t');
      
      // Get volunteer activities
      $activities = civicrm_api3('Activity', 'get', [
        'activity_type_id' => ['IN' => ['Volunteer', 'Pro Bono']],
        'activity_date_time' => [
          '>=' => $start_date,
          '<=' => $end_date,
        ],
        'status_id' => 'Completed',
        'options' => ['limit' => 0],
        'return' => ['duration'],
      ]);
      
      $total_hours = 0;
      foreach ($activities['values'] as $activity) {
        $total_hours += ($activity['duration'] ?? 0) / 60; // Convert minutes to hours
      }
      
      return [
        'value' => round($total_hours, 1),
        'period' => date('F Y'),
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get volunteer hours: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return ['value' => 0];
    }
  }

  /**
   * Update cached metrics.
   */
  public function updateCachedMetrics() {
    $metrics_to_cache = [
      'total_clients_served',
      'active_cases',
      'total_donations_ytd',
      'active_volunteers',
      'upcoming_events',
    ];
    
    foreach ($metrics_to_cache as $metric) {
      try {
        $value = $this->calculateMetric($metric, []);
        
        $cache_key = 'ilas_reports:metric:' . $metric . ':' . md5(serialize([]));
        $this->cache->set($cache_key, $value, $this->time->getRequestTime() + 3600);
        
        $this->logger->info('Updated cached metric: @metric', ['@metric' => $metric]);
      }
      catch (\Exception $e) {
        $this->logger->error('Failed to update cached metric @metric: @error', [
          '@metric' => $metric,
          '@error' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Get upcoming events count.
   */
  protected function getUpcomingEvents($parameters) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $count = civicrm_api3('Event', 'getcount', [
        'is_active' => 1,
        'start_date' => ['>=' => date('Y-m-d')],
      ]);
      
      return [
        'value' => $count,
      ];
    }
    catch (\Exception $e) {
      return ['value' => 0];
    }
  }

  /**
   * Get donor retention rate.
   */
  protected function getDonorRetention($parameters) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $current_year = date('Y');
      $last_year = $current_year - 1;
      
      // Get donors from last year
      $last_year_donors = civicrm_api3('Contribution', 'get', [
        'receive_date' => [
          '>=' => $last_year . '-01-01',
          '<=' => $last_year . '-12-31',
        ],
        'contribution_status_id' => 'Completed',
        'options' => ['limit' => 0],
        'return' => ['contact_id'],
      ]);
      
      $last_year_donor_ids = array_unique(array_column($last_year_donors['values'], 'contact_id'));
      
      // Get donors who also gave this year
      $retained_donors = civicrm_api3('Contribution', 'get', [
        'contact_id' => ['IN' => $last_year_donor_ids],
        'receive_date' => ['>=' => $current_year . '-01-01'],
        'contribution_status_id' => 'Completed',
        'options' => ['limit' => 0],
        'return' => ['contact_id'],
      ]);
      
      $retained_donor_ids = array_unique(array_column($retained_donors['values'], 'contact_id'));
      
      $retention_rate = count($last_year_donor_ids) > 0 ? 
        (count($retained_donor_ids) / count($last_year_donor_ids) * 100) : 0;
      
      return [
        'value' => round($retention_rate, 1),
        'retained' => count($retained_donor_ids),
        'total' => count($last_year_donor_ids),
      ];
    }
    catch (\Exception $e) {
      return ['value' => 0];
    }
  }

  /**
   * Get additional helper methods...
   */
  protected function getAverageCaseDuration($parameters) {
    // Implementation
    return ['value' => 0];
  }

  protected function getDonationsMonth($parameters) {
    // Implementation
    return ['value' => 0, 'formatted' => '$0.00'];
  }

  protected function getAverageDonation($parameters) {
    // Implementation
    return ['value' => 0, 'formatted' => '$0.00'];
  }

  protected function getActiveVolunteers($parameters) {
    // Implementation
    return ['value' => 0];
  }

  protected function getProBonoValue($parameters) {
    // Implementation
    return ['value' => 0, 'formatted' => '$0.00'];
  }

  protected function getVolunteerRetention($parameters) {
    // Implementation
    return ['value' => 0];
  }

  protected function getEventAttendanceRate($parameters) {
    // Implementation
    return ['value' => 0];
  }

  protected function getGrantUtilization($parameters) {
    // Implementation
    return ['value' => 0];
  }

  protected function getGrantDeadlines($parameters) {
    // Implementation
    return ['value' => 0];
  }
}