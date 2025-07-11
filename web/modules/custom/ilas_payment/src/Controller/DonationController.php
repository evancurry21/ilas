<?php

namespace Drupal\ilas_payment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for donation pages.
 */
class DonationController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * Donation confirmation page.
   */
  public function confirmation($contribution_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get contribution details
      $contribution = civicrm_api3('Contribution', 'getsingle', [
        'id' => $contribution_id,
        'return' => ['total_amount', 'receive_date', 'contact_id', 'contribution_recur_id', 'trxn_id'],
      ]);
      
      // Get contact details
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $contribution['contact_id'],
        'return' => ['display_name', 'email'],
      ]);
      
      // Check if recurring
      $is_recurring = !empty($contribution['contribution_recur_id']);
      
      $build = [
        '#theme' => 'donation_confirmation',
        '#contribution' => $contribution,
        '#contact' => $contact,
        '#is_recurring' => $is_recurring,
        '#attached' => [
          'library' => ['ilas_payment/donation_confirmation'],
        ],
      ];
      
      // Add Google Analytics event if available
      $build['#attached']['drupalSettings']['ilasPayment']['confirmation'] = [
        'amount' => $contribution['total_amount'],
        'transaction_id' => $contribution['trxn_id'],
        'is_recurring' => $is_recurring,
      ];
      
      return $build;
    }
    catch (\Exception $e) {
      $this->getLogger('ilas_payment')->error('Failed to load contribution @id: @error', [
        '@id' => $contribution_id,
        '@error' => $e->getMessage(),
      ]);
      
      throw new NotFoundHttpException();
    }
  }

  /**
   * Donation campaign page.
   */
  public function campaign($campaign_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get campaign details
      $campaign = civicrm_api3('Campaign', 'getsingle', [
        'id' => $campaign_id,
        'return' => ['title', 'description', 'goal_revenue', 'start_date', 'end_date'],
      ]);
      
      // Get campaign progress
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
      
      $percentage = 0;
      if (!empty($campaign['goal_revenue'])) {
        $percentage = round(($raised / $campaign['goal_revenue']) * 100);
      }
      
      $build = [
        '#type' => 'container',
        '#attributes' => ['class' => ['campaign-donation-page']],
      ];
      
      // Campaign header
      $build['header'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['campaign-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#value' => $campaign['title'],
        ],
        'description' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $campaign['description'],
          '#attributes' => ['class' => ['campaign-description']],
        ],
      ];
      
      // Progress bar
      if (!empty($campaign['goal_revenue'])) {
        $build['progress'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['campaign-progress']],
          'bar' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'class' => ['progress'],
              'role' => 'progressbar',
              'aria-valuenow' => $percentage,
              'aria-valuemin' => 0,
              'aria-valuemax' => 100,
            ],
            'fill' => [
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#attributes' => [
                'class' => ['progress-bar'],
                'style' => 'width: ' . $percentage . '%',
              ],
              '#value' => $percentage . '%',
            ],
          ],
          'stats' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class' => ['campaign-stats']],
            '#value' => $this->t('$@raised raised of $@goal goal', [
              '@raised' => number_format($raised),
              '@goal' => number_format($campaign['goal_revenue']),
            ]),
          ],
        ];
      }
      
      // Donation form
      $build['form'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['campaign-form-wrapper']],
        'form' => \Drupal::formBuilder()->getForm('Drupal\ilas_payment\Form\DonationForm', $campaign_id),
      ];
      
      return $build;
    }
    catch (\Exception $e) {
      $this->getLogger('ilas_payment')->error('Failed to load campaign @id: @error', [
        '@id' => $campaign_id,
        '@error' => $e->getMessage(),
      ]);
      
      throw new NotFoundHttpException();
    }
  }
}