<?php

namespace Drupal\ilas_civicrm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_civicrm\Service\DashboardService;

/**
 * Controller for CiviCRM dashboard.
 */
class DashboardController extends ControllerBase {

  /**
   * The dashboard service.
   *
   * @var \Drupal\ilas_civicrm\Service\DashboardService
   */
  protected $dashboardService;

  /**
   * Constructs a DashboardController.
   */
  public function __construct(DashboardService $dashboard_service) {
    $this->dashboardService = $dashboard_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilas_civicrm.dashboard')
    );
  }

  /**
   * Dashboard overview page.
   */
  public function overview() {
    $stats = $this->dashboardService->getStatistics();
    
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ilas-civicrm-dashboard']],
    ];
    
    // Summary cards
    $build['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-summary']],
      'contacts' => $this->buildSummaryCard('Contacts', $stats['contacts']),
      'cases' => $this->buildSummaryCard('Cases', $stats['cases']),
      'activities' => $this->buildSummaryCard('Activities', $stats['activities']),
      'contributions' => $this->buildSummaryCard('Contributions', $stats['contributions']),
    ];
    
    // Recent activities
    if (!empty($stats['recent_activities'])) {
      $build['recent_activities'] = [
        '#type' => 'details',
        '#title' => $this->t('Recent Activities'),
        '#open' => TRUE,
        'table' => $this->buildActivitiesTable($stats['recent_activities']),
      ];
    }
    
    // Service area breakdown
    $service_areas = $this->dashboardService->getServiceAreaBreakdown();
    if (!empty($service_areas)) {
      $build['service_areas'] = [
        '#type' => 'details',
        '#title' => $this->t('Service Area Breakdown'),
        '#open' => TRUE,
        'chart' => $this->buildServiceAreaChart($service_areas),
      ];
    }
    
    // Office metrics
    $office_metrics = $this->dashboardService->getOfficeMetrics();
    if (!empty($office_metrics)) {
      $build['office_metrics'] = [
        '#type' => 'details',
        '#title' => $this->t('Office Performance'),
        '#open' => TRUE,
        'table' => $this->buildOfficeMetricsTable($office_metrics),
      ];
    }
    
    // Add CSS
    $build['#attached']['library'][] = 'ilas_civicrm/dashboard';
    
    return $build;
  }

  /**
   * Build a summary card.
   */
  protected function buildSummaryCard($title, $data) {
    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-card']],
    ];
    
    $card['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t($title),
    ];
    
    $items = [];
    
    switch ($title) {
      case 'Contacts':
        $items[] = $this->t('Total: @count', ['@count' => $data['total']]);
        $items[] = $this->t('Individuals: @count', ['@count' => $data['individuals']]);
        $items[] = $this->t('Organizations: @count', ['@count' => $data['organizations']]);
        $items[] = $this->t('New this month: @count', ['@count' => $data['new_this_month']]);
        break;
        
      case 'Cases':
        $items[] = $this->t('Total: @count', ['@count' => $data['total']]);
        $items[] = $this->t('Open: @count', ['@count' => $data['open']]);
        $items[] = $this->t('Urgent: @count', ['@count' => $data['urgent']]);
        $items[] = $this->t('Closed this month: @count', ['@count' => $data['closed_this_month']]);
        break;
        
      case 'Activities':
        $items[] = $this->t('This month: @count', ['@count' => $data['total_this_month']]);
        $items[] = $this->t('Scheduled: @count', ['@count' => $data['scheduled']]);
        $items[] = $this->t('Completed: @count', ['@count' => $data['completed']]);
        break;
        
      case 'Contributions':
        $items[] = $this->t('This month: @count', ['@count' => $data['total_this_month']]);
        $items[] = $this->t('Total amount: $@amount', ['@amount' => number_format($data['total_amount_this_month'], 2)]);
        $items[] = $this->t('Average: $@amount', ['@amount' => number_format($data['average_donation'], 2)]);
        $items[] = $this->t('Recurring active: @count', ['@count' => $data['recurring_active']]);
        break;
    }
    
    $card['stats'] = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    
    return $card;
  }

  /**
   * Build activities table.
   */
  protected function buildActivitiesTable($activities) {
    $header = [
      $this->t('Date'),
      $this->t('Contact'),
      $this->t('Type'),
      $this->t('Subject'),
      $this->t('Status'),
    ];
    
    $rows = [];
    foreach ($activities as $activity) {
      $rows[] = [
        date('M j, g:i a', strtotime($activity['date'])),
        $activity['contact'],
        $activity['type'],
        $activity['subject'],
        $activity['status'],
      ];
    }
    
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No recent activities.'),
    ];
  }

  /**
   * Build service area chart.
   */
  protected function buildServiceAreaChart($data) {
    // For now, return a simple table
    // In production, this would render a chart using a JS library
    $header = [
      $this->t('Service Area'),
      $this->t('Contacts'),
    ];
    
    $rows = [];
    foreach ($data as $item) {
      $rows[] = [
        $item['area'],
        $item['count'],
      ];
    }
    
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No service area data.'),
    ];
  }

  /**
   * Build office metrics table.
   */
  protected function buildOfficeMetricsTable($metrics) {
    $header = [
      $this->t('Office'),
      $this->t('Staff'),
      $this->t('Active Cases'),
      $this->t('Activities (This Month)'),
    ];
    
    $rows = [];
    foreach ($metrics as $office) {
      $rows[] = [
        $office['office'],
        $office['staff'],
        $office['active_cases'],
        $office['activities_this_month'],
      ];
    }
    
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No office data available.'),
    ];
  }
}