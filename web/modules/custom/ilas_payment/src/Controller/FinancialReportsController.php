<?php

namespace Drupal\ilas_payment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_payment\Service\DonationManager;

/**
 * Controller for financial reports.
 */
class FinancialReportsController extends ControllerBase {

  /**
   * The donation manager.
   *
   * @var \Drupal\ilas_payment\Service\DonationManager
   */
  protected $donationManager;

  /**
   * Constructs a FinancialReportsController.
   */
  public function __construct(DonationManager $donation_manager) {
    $this->donationManager = $donation_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilas_payment.donation_manager')
    );
  }

  /**
   * Overview page for donation reports.
   */
  public function overview() {
    // Get date range from query parameters
    $request = \Drupal::request();
    $start_date = $request->query->get('start_date', date('Y-m-01'));
    $end_date = $request->query->get('end_date', date('Y-m-t'));
    
    // Get statistics
    $stats = $this->donationManager->getStatistics($start_date, $end_date);
    
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['financial-reports']],
    ];
    
    // Date filter form
    $build['filter'] = [
      '#type' => 'form',
      '#form_id' => 'donation_report_filter',
      'start_date' => [
        '#type' => 'date',
        '#title' => $this->t('Start Date'),
        '#default_value' => $start_date,
      ],
      'end_date' => [
        '#type' => 'date',
        '#title' => $this->t('End Date'),
        '#default_value' => $end_date,
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Filter'),
      ],
    ];
    
    // Summary statistics
    $build['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['report-summary']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Donation Summary'),
      ],
      'stats' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Total Donations: @count', ['@count' => $stats['total_count']]),
          $this->t('Total Amount: $@amount', ['@amount' => number_format($stats['total_amount'], 2)]),
          $this->t('Average Donation: $@amount', ['@amount' => number_format($stats['average_amount'], 2)]),
        ],
      ],
    ];
    
    // Monthly breakdown
    if (!empty($stats['by_month'])) {
      $header = [
        $this->t('Month'),
        $this->t('Number of Donations'),
        $this->t('Total Amount'),
        $this->t('Average'),
      ];
      
      $rows = [];
      foreach ($stats['by_month'] as $month => $data) {
        $average = $data['count'] > 0 ? $data['amount'] / $data['count'] : 0;
        $rows[] = [
          date('F Y', strtotime($month . '-01')),
          $data['count'],
          '$' . number_format($data['amount'], 2),
          '$' . number_format($average, 2),
        ];
      }
      
      $build['monthly'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['monthly-breakdown']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Monthly Breakdown'),
        ],
        'table' => [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#empty' => $this->t('No donations found for the selected period.'),
        ],
      ];
    }
    
    // Active campaigns
    $build['campaigns'] = $this->getCampaignReport();
    
    // Export options
    $build['export'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['export-options']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Export Options'),
      ],
      'links' => [
        '#theme' => 'links',
        '#links' => [
          'csv' => [
            'title' => $this->t('Export to CSV'),
            'url' => \Drupal\Core\Url::fromRoute('ilas_payment.export_csv', [
              'start_date' => $start_date,
              'end_date' => $end_date,
            ]),
          ],
          'pdf' => [
            'title' => $this->t('Export to PDF'),
            'url' => \Drupal\Core\Url::fromRoute('ilas_payment.export_pdf', [
              'start_date' => $start_date,
              'end_date' => $end_date,
            ]),
          ],
        ],
      ],
    ];
    
    // Add CSS
    $build['#attached']['library'][] = 'ilas_payment/reports';
    
    return $build;
  }

  /**
   * Get campaign report section.
   */
  protected function getCampaignReport() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get active campaigns
      $campaigns = civicrm_api3('Campaign', 'get', [
        'is_active' => 1,
        'options' => ['limit' => 10],
      ]);
      
      if ($campaigns['count'] == 0) {
        return [];
      }
      
      $build = [
        '#type' => 'container',
        '#attributes' => ['class' => ['campaign-report']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Active Campaigns'),
        ],
      ];
      
      $header = [
        $this->t('Campaign'),
        $this->t('Goal'),
        $this->t('Raised'),
        $this->t('Progress'),
        $this->t('Contributors'),
      ];
      
      $rows = [];
      foreach ($campaigns['values'] as $campaign) {
        $progress = $this->donationManager->getCampaignProgress($campaign['id']);
        
        $rows[] = [
          $campaign['title'],
          '$' . number_format($progress['goal'], 2),
          '$' . number_format($progress['raised'], 2),
          $progress['percentage'] . '%',
          $progress['contributors'],
        ];
      }
      
      $build['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
      
      return $build;
    }
    catch (\Exception $e) {
      $this->getLogger('ilas_payment')->error('Failed to get campaign report: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }
}