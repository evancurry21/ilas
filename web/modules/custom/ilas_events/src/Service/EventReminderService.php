<?php

namespace Drupal\ilas_events\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for event reminders.
 */
class EventReminderService {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The notification service.
   *
   * @var \Drupal\ilas_events\Service\EventNotificationService
   */
  protected $notificationService;

  /**
   * Constructs an EventReminderService.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EventNotificationService $notification_service
  ) {
    $this->logger = $logger_factory->get('ilas_events');
    $this->notificationService = $notification_service;
  }

  /**
   * Send pending reminders.
   */
  public function sendPendingReminders() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get reminder settings
      $reminder_days = $this->getReminderDays();
      
      foreach ($reminder_days as $days) {
        $this->sendRemindersForDay($days);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send reminders: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Send reminders for specific day.
   */
  protected function sendRemindersForDay($days_before) {
    try {
      // Calculate target date
      $target_date = date('Y-m-d', strtotime("+$days_before days"));
      
      // Find events happening on target date
      $events = civicrm_api3('Event', 'get', [
        'is_active' => 1,
        'start_date' => [
          '>=' => $target_date . ' 00:00:00',
          '<=' => $target_date . ' 23:59:59',
        ],
        'options' => ['limit' => 0],
      ]);
      
      foreach ($events['values'] as $event) {
        $this->sendEventReminders($event['id'], $days_before);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send reminders for day @days: @error', [
        '@days' => $days_before,
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Send reminders for an event.
   */
  protected function sendEventReminders($event_id, $days_before) {
    try {
      // Get participants
      $participants = civicrm_api3('Participant', 'get', [
        'event_id' => $event_id,
        'status_id' => ['IN' => ['Registered', 'Pending from pay later']],
        'options' => ['limit' => 0],
      ]);
      
      foreach ($participants['values'] as $participant) {
        // Check if reminder already sent
        if (!$this->isReminderSent($participant['id'], $days_before)) {
          $this->notificationService->sendEventReminder($participant['id'], $days_before);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send event reminders: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Check if reminder was already sent.
   */
  protected function isReminderSent($participant_id, $days_before) {
    try {
      // Check for activity
      $activities = civicrm_api3('Activity', 'get', [
        'activity_type_id' => 'Event Reminder Sent',
        'subject' => ['LIKE' => "%($days_before day)%"],
        'target_contact_id' => $participant_id,
        'activity_date_time' => [
          '>=' => date('Y-m-d', strtotime('-2 days')),
        ],
      ]);
      
      return $activities['count'] > 0;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Get reminder days configuration.
   */
  protected function getReminderDays() {
    $config = \Drupal::config('ilas_events.settings');
    $days = $config->get('reminder_days');
    
    if (empty($days)) {
      // Default reminder days
      $days = [7, 3, 1];
    }
    
    return $days;
  }

  /**
   * Send test reminder.
   */
  public function sendTestReminder($participant_id) {
    try {
      return $this->notificationService->sendEventReminder($participant_id, 1);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send test reminder: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Process waitlist for events starting tomorrow.
   */
  public function processWaitlistReminders() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get events starting tomorrow
      $tomorrow = date('Y-m-d', strtotime('+1 day'));
      
      $events = civicrm_api3('Event', 'get', [
        'is_active' => 1,
        'start_date' => [
          '>=' => $tomorrow . ' 00:00:00',
          '<=' => $tomorrow . ' 23:59:59',
        ],
        'has_waitlist' => 1,
        'options' => ['limit' => 0],
      ]);
      
      foreach ($events['values'] as $event) {
        // Check for open spots
        $event_manager = \Drupal::service('ilas_events.manager');
        $available = $event_manager->getAvailableSpots($event);
        
        if ($available > 0) {
          // Get waitlisted participants
          $waitlisted = civicrm_api3('Participant', 'get', [
            'event_id' => $event['id'],
            'status_id' => 'On waitlist',
            'options' => [
              'limit' => $available,
              'sort' => 'register_date ASC',
            ],
          ]);
          
          foreach ($waitlisted['values'] as $participant) {
            // Move to registered
            civicrm_api3('Participant', 'create', [
              'id' => $participant['id'],
              'status_id' => 'Registered',
            ]);
            
            // Send notification
            $this->notificationService->sendWaitlistConfirmation($participant['id']);
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to process waitlist reminders: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Send follow-up emails after events.
   */
  public function sendFollowUpEmails() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get events that ended yesterday
      $yesterday = date('Y-m-d', strtotime('-1 day'));
      
      $events = civicrm_api3('Event', 'get', [
        'is_active' => 1,
        'end_date' => [
          '>=' => $yesterday . ' 00:00:00',
          '<=' => $yesterday . ' 23:59:59',
        ],
        'options' => ['limit' => 0],
      ]);
      
      foreach ($events['values'] as $event) {
        // Get attended participants
        $participants = civicrm_api3('Participant', 'get', [
          'event_id' => $event['id'],
          'status_id' => 'Attended',
          'options' => ['limit' => 0],
        ]);
        
        foreach ($participants['values'] as $participant) {
          $this->sendFollowUpEmail($participant['id'], $event);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send follow-up emails: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Send follow-up email.
   */
  protected function sendFollowUpEmail($participant_id, $event) {
    try {
      // Load participant and contact
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participant_id,
      ]);
      
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $participant['contact_id'],
        'return' => ['email', 'first_name'],
      ]);
      
      if (empty($contact['email'])) {
        return;
      }
      
      // Prepare email
      $params = [
        'participant' => $participant,
        'contact' => $contact,
        'event' => $event,
        'feedback_url' => \Drupal::request()->getSchemeAndHttpHost() . 
          '/event/' . $event['id'] . '/feedback',
      ];
      
      // Add certificate link for CLE events
      if ($event['event_type_id'] == 'cle_training') {
        $params['certificate_url'] = \Drupal::request()->getSchemeAndHttpHost() . 
          '/event/' . $event['id'] . '/certificate/' . $participant_id;
      }
      
      // Send email
      $mail_manager = \Drupal::service('plugin.manager.mail');
      $result = $mail_manager->mail(
        'ilas_events',
        'event_follow_up',
        $contact['email'],
        \Drupal::languageManager()->getDefaultLanguage()->getId(),
        $params,
        NULL,
        TRUE
      );
      
      if ($result['result']) {
        $this->logger->info('Sent follow-up email for event @event to @email', [
          '@event' => $event['title'],
          '@email' => $contact['email'],
        ]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to send follow-up email: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }
}