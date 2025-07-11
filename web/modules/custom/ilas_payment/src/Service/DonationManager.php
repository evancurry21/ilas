<?php

namespace Drupal\ilas_payment\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for managing donations.
 */
class DonationManager {

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
   * Constructs a DonationManager.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->logger = $logger_factory->get('ilas_payment');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get donation statistics.
   */
  public function getStatistics($start_date = NULL, $end_date = NULL) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $params = [
        'contribution_status_id' => 'Completed',
        'options' => ['limit' => 0],
        'return' => ['total_amount', 'receive_date'],
      ];
      
      if ($start_date) {
        $params['receive_date']['>'] = $start_date;
      }
      if ($end_date) {
        $params['receive_date']['<'] = $end_date;
      }
      
      $contributions = civicrm_api3('Contribution', 'get', $params);
      
      $stats = [
        'total_count' => $contributions['count'],
        'total_amount' => 0,
        'average_amount' => 0,
        'by_month' => [],
      ];
      
      foreach ($contributions['values'] as $contribution) {
        $stats['total_amount'] += $contribution['total_amount'];
        
        // Group by month
        $month = date('Y-m', strtotime($contribution['receive_date']));
        if (!isset($stats['by_month'][$month])) {
          $stats['by_month'][$month] = [
            'count' => 0,
            'amount' => 0,
          ];
        }
        $stats['by_month'][$month]['count']++;
        $stats['by_month'][$month]['amount'] += $contribution['total_amount'];
      }
      
      if ($stats['total_count'] > 0) {
        $stats['average_amount'] = $stats['total_amount'] / $stats['total_count'];
      }
      
      return $stats;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get donation statistics: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }

  /**
   * Get top donors.
   */
  public function getTopDonors($limit = 10, $start_date = NULL) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // This would be a custom SQL query in production
      // For now, return empty array
      return [];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get top donors: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }

  /**
   * Get campaign progress.
   */
  public function getCampaignProgress($campaign_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get campaign details
      $campaign = civicrm_api3('Campaign', 'getsingle', [
        'id' => $campaign_id,
      ]);
      
      // Get contributions for campaign
      $contributions = civicrm_api3('Contribution', 'get', [
        'campaign_id' => $campaign_id,
        'contribution_status_id' => 'Completed',
        'options' => ['limit' => 0],
        'return' => ['total_amount'],
      ]);
      
      $raised = 0;
      foreach ($contributions['values'] as $contribution) {
        $raised += $contribution['total_amount'];
      }
      
      return [
        'campaign' => $campaign,
        'goal' => $campaign['goal_revenue'] ?? 0,
        'raised' => $raised,
        'percentage' => $campaign['goal_revenue'] ? 
          round(($raised / $campaign['goal_revenue']) * 100) : 0,
        'contributors' => $contributions['count'],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get campaign progress: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }
}