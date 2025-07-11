<?php

namespace Drupal\ilas_reports\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\RendererInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Service for generating reports.
 */
class ReportGenerator {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a ReportGenerator.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    RendererInterface $renderer
  ) {
    $this->logger = $logger_factory->get('ilas_reports');
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->renderer = $renderer;
  }

  /**
   * Generate a report.
   */
  public function generateReport($report_type, array $parameters = []) {
    try {
      // Get report definition
      $report_def = $this->getReportDefinition($report_type);
      
      if (!$report_def) {
        throw new \Exception('Invalid report type: ' . $report_type);
      }
      
      // Gather report data
      $data = $this->gatherReportData($report_type, $parameters);
      
      // Generate charts if needed
      $charts = $this->generateCharts($report_type, $data);
      
      // Calculate summary statistics
      $summary = $this->calculateSummary($report_type, $data);
      
      // Build report array
      $report = [
        'type' => $report_type,
        'title' => $report_def['title'],
        'description' => $report_def['description'],
        'generated_date' => time(),
        'parameters' => $parameters,
        'data' => $data,
        'charts' => $charts,
        'summary' => $summary,
      ];
      
      $this->logger->info('Generated report: @type', ['@type' => $report_type]);
      
      return $report;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to generate report: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }

  /**
   * Generate report as PDF.
   */
  public function generatePdf($report) {
    try {
      // Render report template
      $build = [
        '#theme' => 'report_template',
        '#title' => $report['title'],
        '#description' => $report['description'],
        '#data' => $report['data'],
        '#charts' => $report['charts'],
        '#summary' => $report['summary'],
        '#generated_date' => $report['generated_date'],
      ];
      
      $html = $this->renderer->renderRoot($build);
      
      // Configure PDF options
      $options = new Options();
      $options->set('isHtml5ParserEnabled', TRUE);
      $options->set('isPhpEnabled', TRUE);
      $options->set('defaultFont', 'Arial');
      
      // Generate PDF
      $dompdf = new Dompdf($options);
      $dompdf->loadHtml($html);
      $dompdf->setPaper('letter', 'portrait');
      $dompdf->render();
      
      // Save to file
      $filename = 'report_' . $report['type'] . '_' . date('Y-m-d_His') . '.pdf';
      $directory = 'private://reports/' . date('Y/m');
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      
      $filepath = $directory . '/' . $filename;
      file_put_contents($filepath, $dompdf->output());
      
      return $filepath;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to generate PDF: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }

  /**
   * Process scheduled reports.
   */
  public function processScheduledReports() {
    try {
      // Get scheduled reports
      $scheduled = $this->getScheduledReports();
      
      foreach ($scheduled as $schedule) {
        if ($this->shouldRunReport($schedule)) {
          $this->runScheduledReport($schedule);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to process scheduled reports: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Get report definition.
   */
  protected function getReportDefinition($report_type) {
    $definitions = $this->getAllReportDefinitions();
    return $definitions[$report_type] ?? NULL;
  }

  /**
   * Get all report definitions.
   */
  protected function getAllReportDefinitions() {
    return [
      'client_services_monthly' => [
        'title' => 'Monthly Client Services Report',
        'description' => 'Overview of client services delivered in the past month',
        'data_sources' => ['cases', 'clients', 'services'],
        'charts' => ['cases_by_type', 'outcomes_chart', 'demographics'],
      ],
      'financial_summary' => [
        'title' => 'Financial Summary Report',
        'description' => 'Financial overview including donations, grants, and expenses',
        'data_sources' => ['donations', 'grants', 'events'],
        'charts' => ['revenue_trend', 'donation_sources', 'expense_breakdown'],
      ],
      'volunteer_impact' => [
        'title' => 'Volunteer Impact Report',
        'description' => 'Volunteer contributions and pro bono services',
        'data_sources' => ['volunteers', 'pro_bono', 'training'],
        'charts' => ['volunteer_hours', 'pro_bono_value', 'volunteer_retention'],
      ],
      'grant_compliance' => [
        'title' => 'Grant Compliance Report',
        'description' => 'Grant utilization and performance metrics',
        'data_sources' => ['grants', 'services', 'outcomes'],
        'charts' => ['grant_utilization', 'performance_metrics'],
      ],
      'impact_assessment' => [
        'title' => 'Community Impact Assessment',
        'description' => 'Measurement of legal aid impact on the community',
        'data_sources' => ['cases', 'outcomes', 'demographics'],
        'charts' => ['impact_metrics', 'geographic_distribution'],
      ],
    ];
  }

  /**
   * Gather report data.
   */
  protected function gatherReportData($report_type, $parameters) {
    $data = [];
    
    switch ($report_type) {
      case 'client_services_monthly':
        $data = $this->gatherClientServicesData($parameters);
        break;
        
      case 'financial_summary':
        $data = $this->gatherFinancialData($parameters);
        break;
        
      case 'volunteer_impact':
        $data = $this->gatherVolunteerData($parameters);
        break;
        
      case 'grant_compliance':
        $data = $this->gatherGrantData($parameters);
        break;
        
      case 'impact_assessment':
        $data = $this->gatherImpactData($parameters);
        break;
    }
    
    return $data;
  }

  /**
   * Gather client services data.
   */
  protected function gatherClientServicesData($parameters) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $start_date = $parameters['start_date'] ?? date('Y-m-01');
      $end_date = $parameters['end_date'] ?? date('Y-m-t');
      
      // Get cases
      $cases = civicrm_api3('Case', 'get', [
        'modified_date' => [
          '>=' => $start_date,
          '<=' => $end_date,
        ],
        'options' => ['limit' => 0],
        'return' => ['id', 'case_type_id', 'status_id', 'subject'],
      ]);
      
      // Get clients
      $client_ids = [];
      foreach ($cases['values'] as $case) {
        $contacts = civicrm_api3('CaseContact', 'get', [
          'case_id' => $case['id'],
        ]);
        foreach ($contacts['values'] as $contact) {
          $client_ids[] = $contact['contact_id'];
        }
      }
      
      $unique_clients = count(array_unique($client_ids));
      
      // Get case activities
      $activities = civicrm_api3('Activity', 'get', [
        'case_id' => ['IS NOT NULL' => 1],
        'activity_date_time' => [
          '>=' => $start_date,
          '<=' => $end_date,
        ],
        'options' => ['limit' => 0],
      ]);
      
      return [
        'total_cases' => $cases['count'],
        'unique_clients' => $unique_clients,
        'total_activities' => $activities['count'],
        'cases_by_type' => $this->groupCasesByType($cases['values']),
        'cases_by_status' => $this->groupCasesByStatus($cases['values']),
        'period' => [
          'start' => $start_date,
          'end' => $end_date,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to gather client services data: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }

  /**
   * Gather financial data.
   */
  protected function gatherFinancialData($parameters) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $start_date = $parameters['start_date'] ?? date('Y-01-01');
      $end_date = $parameters['end_date'] ?? date('Y-m-d');
      
      // Get contributions
      $contributions = civicrm_api3('Contribution', 'get', [
        'receive_date' => [
          '>=' => $start_date,
          '<=' => $end_date,
        ],
        'contribution_status_id' => 'Completed',
        'options' => ['limit' => 0],
        'return' => ['total_amount', 'financial_type_id', 'source'],
      ]);
      
      // Calculate totals
      $total_revenue = 0;
      $by_type = [];
      
      foreach ($contributions['values'] as $contribution) {
        $total_revenue += $contribution['total_amount'];
        
        $type = $contribution['financial_type_id'];
        if (!isset($by_type[$type])) {
          $by_type[$type] = 0;
        }
        $by_type[$type] += $contribution['total_amount'];
      }
      
      // Get event revenue
      $event_revenue = $this->getEventRevenue($start_date, $end_date);
      
      return [
        'total_revenue' => $total_revenue,
        'total_contributions' => $contributions['count'],
        'average_donation' => $contributions['count'] > 0 ? 
          $total_revenue / $contributions['count'] : 0,
        'revenue_by_type' => $by_type,
        'event_revenue' => $event_revenue,
        'period' => [
          'start' => $start_date,
          'end' => $end_date,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to gather financial data: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }

  /**
   * Generate charts for report.
   */
  protected function generateCharts($report_type, $data) {
    $charts = [];
    
    switch ($report_type) {
      case 'client_services_monthly':
        $charts['cases_by_type'] = [
          'type' => 'pie',
          'data' => $data['cases_by_type'] ?? [],
          'title' => 'Cases by Type',
        ];
        
        $charts['cases_by_status'] = [
          'type' => 'bar',
          'data' => $data['cases_by_status'] ?? [],
          'title' => 'Cases by Status',
        ];
        break;
        
      case 'financial_summary':
        $charts['revenue_trend'] = [
          'type' => 'line',
          'data' => $this->getRevenueTrend($data),
          'title' => 'Revenue Trend',
        ];
        
        $charts['revenue_by_type'] = [
          'type' => 'doughnut',
          'data' => $data['revenue_by_type'] ?? [],
          'title' => 'Revenue by Source',
        ];
        break;
    }
    
    return $charts;
  }

  /**
   * Calculate summary statistics.
   */
  protected function calculateSummary($report_type, $data) {
    $summary = [];
    
    switch ($report_type) {
      case 'client_services_monthly':
        $summary['key_metrics'] = [
          'Total Cases' => $data['total_cases'] ?? 0,
          'Unique Clients' => $data['unique_clients'] ?? 0,
          'Activities' => $data['total_activities'] ?? 0,
        ];
        break;
        
      case 'financial_summary':
        $summary['key_metrics'] = [
          'Total Revenue' => '$' . number_format($data['total_revenue'] ?? 0, 2),
          'Total Donations' => $data['total_contributions'] ?? 0,
          'Average Donation' => '$' . number_format($data['average_donation'] ?? 0, 2),
        ];
        break;
    }
    
    return $summary;
  }

  /**
   * Helper: Group cases by type.
   */
  protected function groupCasesByType($cases) {
    $grouped = [];
    
    foreach ($cases as $case) {
      $type = $case['case_type_id'];
      if (!isset($grouped[$type])) {
        $grouped[$type] = 0;
      }
      $grouped[$type]++;
    }
    
    return $grouped;
  }

  /**
   * Helper: Group cases by status.
   */
  protected function groupCasesByStatus($cases) {
    $grouped = [];
    
    foreach ($cases as $case) {
      $status = $case['status_id'];
      if (!isset($grouped[$status])) {
        $grouped[$status] = 0;
      }
      $grouped[$status]++;
    }
    
    return $grouped;
  }

  /**
   * Get scheduled reports.
   */
  protected function getScheduledReports() {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', 'scheduled_report')
          ->condition('status', 1)
          ->condition('field_enabled', TRUE);
    
    $nids = $query->execute();
    
    if (empty($nids)) {
      return [];
    }
    
    return $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
  }

  /**
   * Check if report should run.
   */
  protected function shouldRunReport($schedule) {
    $frequency = $schedule->get('field_frequency')->value;
    $last_run = $schedule->get('field_last_run')->value;
    
    if (!$last_run) {
      return TRUE;
    }
    
    $last_run_time = strtotime($last_run);
    $now = time();
    
    switch ($frequency) {
      case 'daily':
        return ($now - $last_run_time) >= 86400;
        
      case 'weekly':
        return ($now - $last_run_time) >= 604800;
        
      case 'monthly':
        $last_month = date('n', $last_run_time);
        $current_month = date('n');
        return $last_month != $current_month;
        
      case 'quarterly':
        $last_quarter = ceil(date('n', $last_run_time) / 3);
        $current_quarter = ceil(date('n') / 3);
        return $last_quarter != $current_quarter;
    }
    
    return FALSE;
  }

  /**
   * Run scheduled report.
   */
  protected function runScheduledReport($schedule) {
    try {
      // Generate report
      $report_type = $schedule->get('field_report_type')->value;
      $parameters = json_decode($schedule->get('field_parameters')->value, TRUE) ?? [];
      
      $report = $this->generateReport($report_type, $parameters);
      
      // Generate PDF
      $pdf_path = $this->generatePdf($report);
      
      // Send to recipients
      $recipients = $schedule->get('field_recipients')->getValue();
      foreach ($recipients as $recipient) {
        $this->sendScheduledReport($report, $pdf_path, $recipient['value']);
      }
      
      // Update last run
      $schedule->set('field_last_run', date('Y-m-d H:i:s'));
      $schedule->save();
      
      $this->logger->info('Ran scheduled report: @title', [
        '@title' => $schedule->label(),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to run scheduled report: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Send scheduled report email.
   */
  protected function sendScheduledReport($report, $pdf_path, $email) {
    $mail_manager = \Drupal::service('plugin.manager.mail');
    
    $params = [
      'report' => [
        'name' => $report['title'],
        'period' => date('F Y'),
        'summary' => $this->generateEmailSummary($report),
        'file' => $pdf_path,
      ],
      'recipient_name' => 'Team Member',
    ];
    
    $result = $mail_manager->mail(
      'ilas_reports',
      'scheduled_report',
      $email,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      $params,
      NULL,
      TRUE
    );
    
    return $result['result'];
  }

  /**
   * Generate email summary.
   */
  protected function generateEmailSummary($report) {
    $summary = '';
    
    if (!empty($report['summary']['key_metrics'])) {
      foreach ($report['summary']['key_metrics'] as $label => $value) {
        $summary .= $label . ': ' . $value . "\n";
      }
    }
    
    return $summary;
  }

  /**
   * Get event revenue.
   */
  protected function getEventRevenue($start_date, $end_date) {
    // This would query event-related contributions
    return 0;
  }

  /**
   * Get revenue trend data.
   */
  protected function getRevenueTrend($data) {
    // This would calculate monthly revenue trends
    return [];
  }
}