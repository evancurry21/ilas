<?php

namespace Drupal\ilas_reports\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Service for dashboard functionality.
 */
class DashboardService {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The metrics service.
   *
   * @var \Drupal\ilas_reports\Service\MetricsService
   */
  protected $metricsService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a DashboardService.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    MetricsService $metrics_service,
    AccountProxyInterface $current_user
  ) {
    $this->logger = $logger_factory->get('ilas_reports');
    $this->metricsService = $metrics_service;
    $this->currentUser = $current_user;
  }

  /**
   * Get dashboard data for current user.
   */
  public function getDashboard() {
    // Determine user role
    $role = $this->getUserDashboardRole();
    
    // Get widgets for role
    $widget_names = ilas_reports_get_dashboard_widgets($role);
    
    // Load widget data
    $widgets = [];
    foreach ($widget_names as $widget_name) {
      $widget = $this->loadWidget($widget_name);
      if ($widget) {
        $widgets[] = $widget;
      }
    }
    
    return [
      'role' => $role,
      'widgets' => $widgets,
      'last_updated' => time(),
    ];
  }

  /**
   * Load widget data.
   */
  protected function loadWidget($widget_name) {
    try {
      $widget_def = $this->getWidgetDefinition($widget_name);
      
      if (!$widget_def) {
        return NULL;
      }
      
      // Get metric data
      $metric_data = $this->metricsService->getMetric($widget_def['metric']);
      
      // Build widget
      $widget = [
        'id' => $widget_name,
        'title' => $widget_def['title'],
        'value' => $metric_data['formatted'] ?? $metric_data['value'] ?? 0,
        'change' => $metric_data['change'] ?? NULL,
        'change_percent' => $metric_data['change_percent'] ?? NULL,
        'trend' => $metric_data['trend'] ?? NULL,
        'icon' => $widget_def['icon'],
        'color' => $widget_def['color'],
        'link' => $widget_def['link'] ?? NULL,
        'type' => $widget_def['type'] ?? 'metric',
      ];
      
      // Add chart data if applicable
      if ($widget_def['type'] == 'chart') {
        $widget['chart_data'] = $this->getChartData($widget_name);
      }
      
      return $widget;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to load widget @widget: @error', [
        '@widget' => $widget_name,
        '@error' => $e->getMessage(),
      ]);
      
      return NULL;
    }
  }

  /**
   * Get widget definition.
   */
  protected function getWidgetDefinition($widget_name) {
    $definitions = [
      // Client service widgets
      'total_clients_served' => [
        'title' => 'Clients Served YTD',
        'metric' => 'total_clients_served',
        'icon' => 'fa-users',
        'color' => 'primary',
        'link' => '/reports/client-services',
      ],
      'active_cases' => [
        'title' => 'Active Cases',
        'metric' => 'active_cases',
        'icon' => 'fa-folder-open',
        'color' => 'info',
        'link' => '/reports/cases',
      ],
      'cases_closed_success' => [
        'title' => 'Cases Closed (Success)',
        'metric' => 'cases_closed_success',
        'icon' => 'fa-check-circle',
        'color' => 'success',
      ],
      'cases_by_type' => [
        'title' => 'Cases by Type',
        'type' => 'chart',
        'metric' => 'cases_by_type',
        'icon' => 'fa-chart-pie',
        'color' => 'primary',
      ],
      'client_wait_time' => [
        'title' => 'Avg. Wait Time',
        'metric' => 'client_wait_time',
        'icon' => 'fa-clock',
        'color' => 'warning',
      ],
      'service_areas_map' => [
        'title' => 'Service Areas',
        'type' => 'map',
        'metric' => 'service_areas',
        'icon' => 'fa-map',
        'color' => 'info',
      ],
      
      // Financial widgets
      'total_donations_ytd' => [
        'title' => 'Donations YTD',
        'metric' => 'total_donations_ytd',
        'icon' => 'fa-dollar-sign',
        'color' => 'success',
        'link' => '/reports/financial',
      ],
      'donations_month' => [
        'title' => 'Donations This Month',
        'metric' => 'donations_month',
        'icon' => 'fa-chart-line',
        'color' => 'primary',
      ],
      'donor_retention' => [
        'title' => 'Donor Retention',
        'metric' => 'donor_retention',
        'icon' => 'fa-percentage',
        'color' => 'info',
      ],
      'major_donors' => [
        'title' => 'Major Donors',
        'type' => 'list',
        'metric' => 'major_donors',
        'icon' => 'fa-star',
        'color' => 'warning',
      ],
      'fundraising_goals' => [
        'title' => 'Fundraising Goals',
        'type' => 'progress',
        'metric' => 'fundraising_goals',
        'icon' => 'fa-target',
        'color' => 'primary',
      ],
      
      // Volunteer widgets
      'active_volunteers' => [
        'title' => 'Active Volunteers',
        'metric' => 'active_volunteers',
        'icon' => 'fa-hands-helping',
        'color' => 'info',
      ],
      'volunteer_hours_month' => [
        'title' => 'Volunteer Hours',
        'metric' => 'volunteer_hours_month',
        'icon' => 'fa-clock',
        'color' => 'primary',
      ],
      'volunteer_hours_week' => [
        'title' => 'Hours This Week',
        'metric' => 'volunteer_hours_week',
        'icon' => 'fa-calendar-week',
        'color' => 'success',
      ],
      'pro_bono_value' => [
        'title' => 'Pro Bono Value',
        'metric' => 'pro_bono_value',
        'icon' => 'fa-balance-scale',
        'color' => 'warning',
      ],
      'training_completion' => [
        'title' => 'Training Completion',
        'metric' => 'training_completion',
        'icon' => 'fa-graduation-cap',
        'color' => 'info',
      ],
      'volunteer_retention' => [
        'title' => 'Volunteer Retention',
        'metric' => 'volunteer_retention',
        'icon' => 'fa-user-check',
        'color' => 'success',
      ],
      
      // Event widgets
      'upcoming_events' => [
        'title' => 'Upcoming Events',
        'metric' => 'upcoming_events',
        'icon' => 'fa-calendar-alt',
        'color' => 'primary',
        'link' => '/events',
      ],
      
      // Grant widgets
      'grant_utilization' => [
        'title' => 'Grant Utilization',
        'metric' => 'grant_utilization',
        'icon' => 'fa-file-contract',
        'color' => 'info',
      ],
      'grant_deadlines' => [
        'title' => 'Upcoming Deadlines',
        'metric' => 'grant_deadlines',
        'icon' => 'fa-exclamation-triangle',
        'color' => 'warning',
      ],
    ];
    
    return $definitions[$widget_name] ?? NULL;
  }

  /**
   * Get user dashboard role.
   */
  protected function getUserDashboardRole() {
    $roles = $this->currentUser->getRoles();
    
    // Check for specific dashboard roles
    if (in_array('executive', $roles)) {
      return 'executive';
    }
    elseif (in_array('program_manager', $roles)) {
      return 'program_manager';
    }
    elseif (in_array('development', $roles)) {
      return 'development';
    }
    elseif (in_array('volunteer_coordinator', $roles)) {
      return 'volunteer_coordinator';
    }
    
    // Default role
    return 'staff';
  }

  /**
   * Get chart data for widget.
   */
  protected function getChartData($widget_name) {
    switch ($widget_name) {
      case 'cases_by_type':
        return $this->getCasesByTypeChartData();
        
      case 'service_areas_map':
        return $this->getServiceAreasMapData();
        
      default:
        return [];
    }
  }

  /**
   * Get cases by type chart data.
   */
  protected function getCasesByTypeChartData() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get case types
      $case_types = civicrm_api3('CaseType', 'get', [
        'is_active' => 1,
        'options' => ['limit' => 0],
      ]);
      
      $data = [];
      $labels = [];
      
      foreach ($case_types['values'] as $type) {
        // Count cases of this type
        $count = civicrm_api3('Case', 'getcount', [
          'case_type_id' => $type['id'],
          'status_id' => ['NOT IN' => ['Closed', 'Rejected']],
        ]);
        
        if ($count > 0) {
          $labels[] = $type['title'];
          $data[] = $count;
        }
      }
      
      return [
        'labels' => $labels,
        'datasets' => [
          [
            'data' => $data,
            'backgroundColor' => [
              '#007bff',
              '#28a745',
              '#ffc107',
              '#dc3545',
              '#6f42c1',
              '#17a2b8',
            ],
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Get service areas map data.
   */
  protected function getServiceAreasMapData() {
    // This would return geographic data for mapping
    return [];
  }

  /**
   * Save dashboard preferences.
   */
  public function saveDashboardPreferences($preferences) {
    $uid = $this->currentUser->id();
    
    // Save to user data
    \Drupal::service('user.data')->set(
      'ilas_reports',
      $uid,
      'dashboard_preferences',
      $preferences
    );
    
    $this->logger->info('Saved dashboard preferences for user @uid', [
      '@uid' => $uid,
    ]);
  }

  /**
   * Get dashboard preferences.
   */
  public function getDashboardPreferences() {
    $uid = $this->currentUser->id();
    
    return \Drupal::service('user.data')->get(
      'ilas_reports',
      $uid,
      'dashboard_preferences'
    ) ?? [];
  }
}