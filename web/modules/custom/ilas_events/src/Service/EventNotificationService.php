<?php

namespace Drupal\ilas_events\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Render\RendererInterface;

/**
 * Service for event notifications.
 */
class EventNotificationService {

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
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an EventNotificationService.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    MailManagerInterface $mail_manager,
    Token $token,
    RendererInterface $renderer
  ) {
    $this->logger = $logger_factory->get('ilas_events');
    $this->mailManager = $mail_manager;
    $this->token = $token;
    $this->renderer = $renderer;
  }

  /**
   * Send registration confirmation.
   */
  public function sendRegistrationConfirmation($participant_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load participant
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participant_id,
        'return' => ['contact_id', 'event_id', 'status_id'],
      ]);
      
      // Load contact
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $participant['contact_id'],
        'return' => ['email', 'display_name', 'first_name'],
      ]);
      
      if (empty($contact['email'])) {
        return FALSE;
      }
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', [
        'id' => $participant['event_id'],
      ]);
      
      // Prepare email
      $params = [
        'participant' => $participant,
        'contact' => $contact,
        'event' => $event,
        'confirmation_url' => \Drupal::request()->getSchemeAndHttpHost() . 
          '/event/' . $event['id'] . '/confirmation/' . $participant_id,
      ];
      
      // Send email
      $result = $this->mailManager->mail(
        'ilas_events',
        'registration_confirmation',
        $contact['email'],
        \Drupal::languageManager()->getDefaultLanguage()->getId(),
        $params,
        NULL,
        TRUE
      );
      
      if ($result['result']) {
        $this->logger->info('Sent registration confirmation to @email for event @event', [
          '@email' => $contact['email'],
          '@event' => $event['title'],
        ]);
        
        return TRUE;
      }
      
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send registration confirmation: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Send event reminder.
   */
  public function sendEventReminder($participant_id, $days_before = 1) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load participant
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participant_id,
        'return' => ['contact_id', 'event_id', 'status_id'],
      ]);
      
      // Only send reminders to registered participants
      if (!in_array($participant['status_id'], ['Registered', 'Pending from pay later'])) {
        return FALSE;
      }
      
      // Load contact
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $participant['contact_id'],
        'return' => ['email', 'display_name', 'first_name'],
      ]);
      
      if (empty($contact['email'])) {
        return FALSE;
      }
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', [
        'id' => $participant['event_id'],
      ]);
      
      // Prepare email
      $params = [
        'participant' => $participant,
        'contact' => $contact,
        'event' => $event,
        'days_before' => $days_before,
      ];
      
      // Send email
      $result = $this->mailManager->mail(
        'ilas_events',
        'event_reminder',
        $contact['email'],
        \Drupal::languageManager()->getDefaultLanguage()->getId(),
        $params,
        NULL,
        TRUE
      );
      
      if ($result['result']) {
        $this->logger->info('Sent event reminder to @email for event @event', [
          '@email' => $contact['email'],
          '@event' => $event['title'],
        ]);
        
        // Mark reminder as sent
        $this->markReminderSent($participant_id, $days_before);
        
        return TRUE;
      }
      
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send event reminder: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Send waitlist confirmation.
   */
  public function sendWaitlistConfirmation($participant_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load participant
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participant_id,
        'return' => ['contact_id', 'event_id'],
      ]);
      
      // Load contact
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $participant['contact_id'],
        'return' => ['email', 'display_name', 'first_name'],
      ]);
      
      if (empty($contact['email'])) {
        return FALSE;
      }
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', [
        'id' => $participant['event_id'],
      ]);
      
      // Prepare email
      $params = [
        'participant' => $participant,
        'contact' => $contact,
        'event' => $event,
        'registration_url' => \Drupal::request()->getSchemeAndHttpHost() . 
          '/event/' . $event['id'] . '/register',
      ];
      
      // Send email
      $result = $this->mailManager->mail(
        'ilas_events',
        'waitlist_confirmation',
        $contact['email'],
        \Drupal::languageManager()->getDefaultLanguage()->getId(),
        $params,
        NULL,
        TRUE
      );
      
      if ($result['result']) {
        $this->logger->info('Sent waitlist confirmation to @email for event @event', [
          '@email' => $contact['email'],
          '@event' => $event['title'],
        ]);
        
        return TRUE;
      }
      
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send waitlist confirmation: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Send cancellation notice.
   */
  public function sendCancellationNotice($participant_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load participant
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participant_id,
        'return' => ['contact_id', 'event_id'],
      ]);
      
      // Load contact
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $participant['contact_id'],
        'return' => ['email', 'display_name', 'first_name'],
      ]);
      
      if (empty($contact['email'])) {
        return FALSE;
      }
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', [
        'id' => $participant['event_id'],
      ]);
      
      // Prepare email
      $params = [
        'participant' => $participant,
        'contact' => $contact,
        'event' => $event,
      ];
      
      // Send email
      $result = $this->mailManager->mail(
        'ilas_events',
        'cancellation_notice',
        $contact['email'],
        \Drupal::languageManager()->getDefaultLanguage()->getId(),
        $params,
        NULL,
        TRUE
      );
      
      if ($result['result']) {
        $this->logger->info('Sent cancellation notice to @email for event @event', [
          '@email' => $contact['email'],
          '@event' => $event['title'],
        ]);
        
        return TRUE;
      }
      
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send cancellation notice: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Mark reminder as sent.
   */
  protected function markReminderSent($participant_id, $days_before) {
    // Store in custom field or activity
    try {
      civicrm_api3('Activity', 'create', [
        'activity_type_id' => 'Event Reminder Sent',
        'subject' => 'Event reminder sent (' . $days_before . ' day)',
        'source_contact_id' => 1, // System
        'target_contact_id' => $participant_id,
        'status_id' => 'Completed',
        'activity_date_time' => date('YmdHis'),
      ]);
    }
    catch (\Exception $e) {
      // Log error
    }
  }
}