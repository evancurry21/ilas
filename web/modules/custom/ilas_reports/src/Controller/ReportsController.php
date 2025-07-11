<?php

namespace Drupal\ilas_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_reports\Service\ReportGenerator;
use Drupal\ilas_reports\Service\ExportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller for reports.
 */
class ReportsController extends ControllerBase {

  /**
   * The report generator.
   *
   * @var \Drupal\ilas_reports\Service\ReportGenerator
   */
  protected $reportGenerator;

  /**
   * The export service.
   *
   * @var \Drupal\ilas_reports\Service\ExportService
   */
  protected $exportService;

  /**
   * Constructs a ReportsController.
   */
  public function __construct(
    ReportGenerator $report_generator,
    ExportService $export_service
  ) {
    $this->reportGenerator = $report_generator;
    $this->exportService = $export_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilas_reports.generator'),
      $container->get('ilas_reports.export')
    );
  }

  /**
   * Reports overview page.
   */
  public function overview() {
    $report_types = ilas_reports_get_report_types();
    
    $build = [
      '#theme' => 'item_list',
      '#title' => $this->t('Available Reports'),
      '#items' => [],
      '#attributes' => ['class' => ['reports-list']],
      '#attached' => [
        'library' => ['ilas_reports/reports'],
      ],
    ];
    
    foreach ($report_types as $type => $info) {
      $build['#items'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['report-item']],
        'icon' => [
          '#markup' => '<i class="fas ' . $info['icon'] . '"></i>',
        ],
        'content' => [
          '#type' => 'container',
          'title' => [
            '#type' => 'link',
            '#title' => $info['label'],
            '#url' => \Drupal\Core\Url::fromRoute('ilas_reports.report_view', [
              'report_type' => $type,
            ]),
            '#attributes' => ['class' => ['report-title']],
          ],
          'description' => [
            '#markup' => '<p>' . $info['description'] . '</p>',
          ],
        ],
      ];
    }
    
    return $build;
  }

  /**
   * View specific report.
   */
  public function viewReport($report_type, Request $request) {
    // Get report parameters from request
    $parameters = $this->getReportParameters($request);
    
    try {
      // Generate report
      $report = $this->reportGenerator->generateReport($report_type, $parameters);
      
      $build = [
        '#theme' => 'report_template',
        '#title' => $report['title'],
        '#description' => $report['description'],
        '#data' => $report['data'],
        '#charts' => $report['charts'],
        '#summary' => $report['summary'],
        '#generated_date' => $report['generated_date'],
        '#attached' => [
          'library' => [
            'ilas_reports/reports',
            'ilas_reports/charts',
          ],
          'drupalSettings' => [
            'ilasReports' => [
              'charts' => $report['charts'],
            ],
          ],
        ],
      ];
      
      // Add export links
      $build['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['report-actions']],
        'export_pdf' => [
          '#type' => 'link',
          '#title' => $this->t('Export as PDF'),
          '#url' => \Drupal\Core\Url::fromRoute('ilas_reports.report_export', [
            'report_type' => $report_type,
            'format' => 'pdf',
          ]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'export_excel' => [
          '#type' => 'link',
          '#title' => $this->t('Export as Excel'),
          '#url' => \Drupal\Core\Url::fromRoute('ilas_reports.report_export', [
            'report_type' => $report_type,
            'format' => 'excel',
          ]),
          '#attributes' => ['class' => ['button']],
        ],
        'export_csv' => [
          '#type' => 'link',
          '#title' => $this->t('Export as CSV'),
          '#url' => \Drupal\Core\Url::fromRoute('ilas_reports.report_export', [
            'report_type' => $report_type,
            'format' => 'csv',
          ]),
          '#attributes' => ['class' => ['button']],
        ],
      ];
      
      return $build;
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to generate report: @error', [
        '@error' => $e->getMessage(),
      ]));
      
      return $this->redirect('ilas_reports.reports');
    }
  }

  /**
   * Generate report.
   */
  public function generateReport($report_type, Request $request) {
    $parameters = $this->getReportParameters($request);
    
    try {
      $report = $this->reportGenerator->generateReport($report_type, $parameters);
      
      // Return JSON response
      return new \Symfony\Component\HttpFoundation\JsonResponse($report);
    }
    catch (\Exception $e) {
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Export report.
   */
  public function exportReport($report_type, $format, Request $request) {
    $parameters = $this->getReportParameters($request);
    
    try {
      // Generate report
      $report = $this->reportGenerator->generateReport($report_type, $parameters);
      
      // Export based on format
      switch ($format) {
        case 'pdf':
          $filepath = $this->reportGenerator->generatePdf($report);
          break;
          
        case 'excel':
          $filepath = $this->exportService->exportExcel($report);
          break;
          
        case 'csv':
          $filepath = $this->exportService->exportCsv($report);
          break;
          
        default:
          throw new \Exception('Invalid export format');
      }
      
      // Return file response
      $response = new BinaryFileResponse($filepath);
      $response->setContentDisposition(
        'attachment',
        'report_' . $report_type . '_' . date('Y-m-d') . '.' . $format
      );
      
      return $response;
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to export report: @error', [
        '@error' => $e->getMessage(),
      ]));
      
      return $this->redirect('ilas_reports.report_view', ['report_type' => $report_type]);
    }
  }

  /**
   * Get report title.
   */
  public function getReportTitle($report_type) {
    $report_types = ilas_reports_get_report_types();
    return $report_types[$report_type]['label'] ?? $this->t('Report');
  }

  /**
   * Get report parameters from request.
   */
  protected function getReportParameters(Request $request) {
    $parameters = [];
    
    // Date range
    $parameters['start_date'] = $request->query->get('start_date', date('Y-m-01'));
    $parameters['end_date'] = $request->query->get('end_date', date('Y-m-d'));
    
    // Other filters
    $parameters['program'] = $request->query->get('program');
    $parameters['office'] = $request->query->get('office');
    $parameters['staff'] = $request->query->get('staff');
    
    return array_filter($parameters);
  }
}