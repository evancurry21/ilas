<?php

namespace Drupal\ilas_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_reports\Service\DashboardService;

/**
 * Controller for dashboard.
 */
class DashboardController extends ControllerBase {

  /**
   * The dashboard service.
   *
   * @var \Drupal\ilas_reports\Service\DashboardService
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
      $container->get('ilas_reports.dashboard')
    );
  }

  /**
   * Display dashboard.
   */
  public function view() {
    $dashboard_data = $this->dashboardService->getDashboard();
    
    $build = [
      '#theme' => 'report_dashboard',
      '#widgets' => $dashboard_data['widgets'],
      '#user_role' => $dashboard_data['role'],
      '#attached' => [
        'library' => [
          'ilas_reports/dashboard',
          'ilas_reports/charts',
        ],
        'drupalSettings' => [
          'ilasReports' => [
            'dashboard' => $dashboard_data,
          ],
        ],
      ],
      '#cache' => [
        'max-age' => 300, // Cache for 5 minutes
        'contexts' => ['user.roles'],
        'tags' => ['ilas_reports_dashboard'],
      ],
    ];
    
    // Add quick actions based on role
    $build['#filters'] = $this->getQuickActions($dashboard_data['role']);
    
    return $build;
  }

  /**
   * Get quick actions for dashboard.
   */
  protected function getQuickActions($role) {
    $actions = [];
    
    switch ($role) {
      case 'executive':
        $actions = [
          [
            'title' => $this->t('Monthly Report'),
            'url' => '/admin/reports/ilas/client_services_monthly',
            'icon' => 'fa-file-alt',
          ],
          [
            'title' => $this->t('Financial Summary'),
            'url' => '/admin/reports/ilas/financial_summary',
            'icon' => 'fa-dollar-sign',
          ],
          [
            'title' => $this->t('Impact Assessment'),
            'url' => '/admin/reports/ilas/impact_assessment',
            'icon' => 'fa-chart-line',
          ],
        ];
        break;
        
      case 'program_manager':
        $actions = [
          [
            'title' => $this->t('Case Reports'),
            'url' => '/admin/reports/ilas/client_services_monthly',
            'icon' => 'fa-folder',
          ],
          [
            'title' => $this->t('Staff Reports'),
            'url' => '/admin/reports/staff',
            'icon' => 'fa-users',
          ],
        ];
        break;
        
      case 'development':
        $actions = [
          [
            'title' => $this->t('Donor Reports'),
            'url' => '/admin/reports/ilas/financial_summary',
            'icon' => 'fa-heart',
          ],
          [
            'title' => $this->t('Grant Reports'),
            'url' => '/admin/reports/ilas/grant_compliance',
            'icon' => 'fa-file-contract',
          ],
        ];
        break;
    }
    
    // Add common actions
    $actions[] = [
      'title' => $this->t('All Reports'),
      'url' => '/admin/reports/ilas',
      'icon' => 'fa-list',
    ];
    
    $actions[] = [
      'title' => $this->t('Export Data'),
      'url' => '/admin/reports/export',
      'icon' => 'fa-download',
    ];
    
    return $actions;
  }
}