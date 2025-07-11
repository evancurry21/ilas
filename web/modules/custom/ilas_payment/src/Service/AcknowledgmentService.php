<?php

namespace Drupal\ilas_payment\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Render\RendererInterface;

/**
 * Service for handling donation acknowledgments.
 */
class AcknowledgmentService {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an AcknowledgmentService.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    MailManagerInterface $mail_manager,
    Token $token,
    RendererInterface $renderer
  ) {
    $this->logger = $logger_factory->get('ilas_payment');
    $this->mailManager = $mail_manager;
    $this->token = $token;
    $this->renderer = $renderer;
  }

  /**
   * Send donation acknowledgment email.
   */
  public function sendAcknowledgment($contribution_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get contribution details
      $contribution = civicrm_api3('Contribution', 'getsingle', [
        'id' => $contribution_id,
      ]);
      
      // Get contact details
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $contribution['contact_id'],
      ]);
      
      if (empty($contact['email'])) {
        $this->logger->warning('No email address for contact @id', [
          '@id' => $contact['id'],
        ]);
        return FALSE;
      }
      
      // Prepare tokens
      $tokens = [
        'donor_name' => $contact['display_name'],
        'amount' => number_format($contribution['total_amount'], 2),
        'date' => date('F j, Y', strtotime($contribution['receive_date'])),
        'transaction_id' => $contribution['trxn_id'] ?? 'N/A',
      ];
      
      // Get email configuration
      $config = \Drupal::config('ilas_payment.settings');
      $subject = $config->get('receipt_subject');
      $template = $config->get('receipt_template');
      
      // Replace tokens
      foreach ($tokens as $key => $value) {
        $subject = str_replace('[' . $key . ']', $value, $subject);
        $template = str_replace('[' . $key . ']', $value, $template);
      }
      
      // Send email
      $params = [
        'subject' => $subject,
        'body' => $template,
        'contribution' => $contribution,
        'contact' => $contact,
      ];
      
      $result = $this->mailManager->mail(
        'ilas_payment',
        'donation_receipt',
        $contact['email'],
        \Drupal::languageManager()->getDefaultLanguage()->getId(),
        $params,
        $config->get('receipt_email_from')
      );
      
      if ($result['result']) {
        $this->logger->info('Acknowledgment sent for contribution @id', [
          '@id' => $contribution_id,
        ]);
        
        // Mark acknowledgment as sent
        civicrm_api3('Contribution', 'create', [
          'id' => $contribution_id,
          'thankyou_date' => date('YmdHis'),
        ]);
        
        return TRUE;
      }
      
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send acknowledgment: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Send pending acknowledgments.
   */
  public function sendPendingAcknowledgments() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get contributions without acknowledgment
      $contributions = civicrm_api3('Contribution', 'get', [
        'contribution_status_id' => 'Completed',
        'thankyou_date' => ['IS NULL' => 1],
        'receive_date' => ['>=' => date('Y-m-d', strtotime('-30 days'))],
        'options' => ['limit' => 50],
      ]);
      
      $sent = 0;
      foreach ($contributions['values'] as $contribution) {
        if ($this->sendAcknowledgment($contribution['id'])) {
          $sent++;
        }
      }
      
      if ($sent > 0) {
        $this->logger->info('Sent @count pending acknowledgments', [
          '@count' => $sent,
        ]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send pending acknowledgments: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }
}