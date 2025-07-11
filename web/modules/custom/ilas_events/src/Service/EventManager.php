<?php

namespace Drupal\ilas_events\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Service for managing events.
 */
class EventManager {

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
   * Constructs an EventManager.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->logger = $logger_factory->get('ilas_events');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Create an event in CiviCRM.
   */
  public function createEvent(array $event_data) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Prepare event parameters
      $params = [
        'title' => $event_data['title'],
        'summary' => $event_data['summary'] ?? '',
        'description' => $event_data['description'] ?? '',
        'event_type_id' => $event_data['event_type_id'] ?? 'Conference',
        'start_date' => $this->formatDateTime($event_data['start_date']),
        'end_date' => $this->formatDateTime($event_data['end_date'] ?? $event_data['start_date']),
        'is_online_registration' => $event_data['enable_registration'] ?? TRUE,
        'is_monetary' => $event_data['is_paid'] ?? FALSE,
        'is_active' => 1,
        'is_public' => 1,
      ];
      
      // Add registration settings
      if ($params['is_online_registration']) {
        $params['registration_start_date'] = date('YmdHis');
        
        if (!empty($event_data['registration_deadline'])) {
          $params['registration_end_date'] = $this->formatDateTime($event_data['registration_deadline']);
        }
        
        if (!empty($event_data['max_participants'])) {
          $params['max_participants'] = $event_data['max_participants'];
          $params['has_waitlist'] = TRUE;
        }
      }
      
      // Add location if provided
      if (!empty($event_data['location'])) {
        $loc_block_id = $this->createLocationBlock($event_data['location']);
        if ($loc_block_id) {
          $params['loc_block_id'] = $loc_block_id;
        }
      }
      
      // Add fee if paid event
      if ($params['is_monetary'] && !empty($event_data['registration_fee'])) {
        $params['financial_type_id'] = 'Event Fee';
        $params['is_pay_later'] = TRUE;
        $params['pay_later_text'] = 'Pay at the door';
      }
      
      // Create the event
      $event = civicrm_api3('Event', 'create', $params);
      
      // Add price set if paid
      if ($params['is_monetary']) {
        $this->createEventPricing($event['id'], $event_data);
      }
      
      // Add custom fields for legal events
      if (in_array($event_data['event_type_id'], ['legal_clinic', 'cle_training'])) {
        $this->addLegalEventFields($event['id'], $event_data);
      }
      
      $this->logger->info('Created event: @title (ID: @id)', [
        '@title' => $params['title'],
        '@id' => $event['id'],
      ]);
      
      return $event;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create event: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }

  /**
   * Update event in CiviCRM.
   */
  public function updateEvent($event_id, array $event_data) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $params = ['id' => $event_id];
      
      // Update allowed fields
      $update_fields = [
        'title', 'summary', 'description', 'event_type_id',
        'start_date', 'end_date', 'max_participants',
        'is_online_registration', 'is_monetary', 'is_active',
      ];
      
      foreach ($update_fields as $field) {
        if (isset($event_data[$field])) {
          $params[$field] = $event_data[$field];
        }
      }
      
      // Format dates
      if (isset($params['start_date'])) {
        $params['start_date'] = $this->formatDateTime($params['start_date']);
      }
      if (isset($params['end_date'])) {
        $params['end_date'] = $this->formatDateTime($params['end_date']);
      }
      
      $event = civicrm_api3('Event', 'create', $params);
      
      $this->logger->info('Updated event ID: @id', ['@id' => $event_id]);
      
      return $event;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update event: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }

  /**
   * Register participant for event.
   */
  public function registerParticipant($event_id, $contact_id, array $registration_data = []) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Check if already registered
      $existing = civicrm_api3('Participant', 'get', [
        'event_id' => $event_id,
        'contact_id' => $contact_id,
        'status_id' => ['NOT IN' => ['Cancelled']],
      ]);
      
      if ($existing['count'] > 0) {
        throw new \Exception('Already registered for this event.');
      }
      
      // Check event capacity
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      
      if (!empty($event['max_participants'])) {
        $registered = civicrm_api3('Participant', 'getcount', [
          'event_id' => $event_id,
          'status_id' => ['IN' => ['Registered', 'Attended']],
        ]);
        
        if ($registered >= $event['max_participants']) {
          // Add to waitlist
          $registration_data['status_id'] = 'On waitlist';
        }
      }
      
      // Create participant record
      $params = [
        'contact_id' => $contact_id,
        'event_id' => $event_id,
        'status_id' => $registration_data['status_id'] ?? 'Registered',
        'role_id' => $registration_data['role_id'] ?? 'Attendee',
        'register_date' => date('YmdHis'),
        'source' => 'Online Registration',
      ];
      
      // Add custom fields
      if (!empty($registration_data['dietary_restrictions'])) {
        $params['custom_dietary_restrictions'] = $registration_data['dietary_restrictions'];
      }
      if (!empty($registration_data['special_needs'])) {
        $params['custom_special_needs'] = $registration_data['special_needs'];
      }
      
      $participant = civicrm_api3('Participant', 'create', $params);
      
      // Handle payment if paid event
      if ($event['is_monetary'] && !empty($registration_data['payment_method'])) {
        $this->processEventPayment($participant['id'], $registration_data);
      }
      
      $this->logger->info('Registered participant @contact for event @event', [
        '@contact' => $contact_id,
        '@event' => $event_id,
      ]);
      
      return $participant;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to register participant: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }

  /**
   * Get upcoming events.
   */
  public function getUpcomingEvents($limit = 10, $event_type = NULL) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $params = [
        'is_active' => 1,
        'is_public' => 1,
        'start_date' => ['>=' => date('Y-m-d')],
        'options' => [
          'limit' => $limit,
          'sort' => 'start_date ASC',
        ],
        'return' => ['id', 'title', 'summary', 'start_date', 'end_date', 
                    'event_type_id', 'is_online_registration', 'max_participants'],
      ];
      
      if ($event_type) {
        $params['event_type_id'] = $event_type;
      }
      
      $events = civicrm_api3('Event', 'get', $params);
      
      // Add registration counts
      foreach ($events['values'] as &$event) {
        $event['registered_count'] = $this->getRegisteredCount($event['id']);
        $event['available_spots'] = $this->getAvailableSpots($event);
        $event['registration_open'] = $this->isRegistrationOpen($event);
      }
      
      return $events['values'];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get upcoming events: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [];
    }
  }

  /**
   * Get registered participants count.
   */
  public function getRegisteredCount($event_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      return civicrm_api3('Participant', 'getcount', [
        'event_id' => $event_id,
        'status_id' => ['IN' => ['Registered', 'Attended', 'Pending from pay later']],
      ]);
    }
    catch (\Exception $e) {
      return 0;
    }
  }

  /**
   * Get available spots for event.
   */
  public function getAvailableSpots($event) {
    if (empty($event['max_participants'])) {
      return 'unlimited';
    }
    
    $registered = $this->getRegisteredCount($event['id']);
    $available = $event['max_participants'] - $registered;
    
    return max(0, $available);
  }

  /**
   * Check if registration is open.
   */
  public function isRegistrationOpen($event) {
    if (!$event['is_online_registration']) {
      return FALSE;
    }
    
    $now = time();
    $start_date = strtotime($event['start_date']);
    
    // Can't register for past events
    if ($now > $start_date) {
      return FALSE;
    }
    
    // Check registration deadline if set
    if (!empty($event['registration_end_date'])) {
      $deadline = strtotime($event['registration_end_date']);
      if ($now > $deadline) {
        return FALSE;
      }
    }
    
    return TRUE;
  }

  /**
   * Update event statuses.
   */
  public function updateEventStatuses() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Find events that have ended
      $events = civicrm_api3('Event', 'get', [
        'is_active' => 1,
        'end_date' => ['<' => date('Y-m-d')],
        'options' => ['limit' => 50],
      ]);
      
      foreach ($events['values'] as $event) {
        // Mark as completed
        civicrm_api3('Event', 'create', [
          'id' => $event['id'],
          'is_active' => 0,
        ]);
        
        $this->logger->info('Marked event @id as completed', ['@id' => $event['id']]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update event statuses: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Process waitlists.
   */
  public function processWaitlists() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Find events with waitlists
      $events = civicrm_api3('Event', 'get', [
        'is_active' => 1,
        'has_waitlist' => 1,
        'max_participants' => ['>=' => 1],
        'options' => ['limit' => 50],
      ]);
      
      foreach ($events['values'] as $event) {
        $this->processEventWaitlist($event['id']);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to process waitlists: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Process waitlist for a specific event.
   */
  protected function processEventWaitlist($event_id) {
    // Get available spots
    $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
    $available = $this->getAvailableSpots($event);
    
    if ($available <= 0) {
      return;
    }
    
    // Get waitlisted participants
    $waitlisted = civicrm_api3('Participant', 'get', [
      'event_id' => $event_id,
      'status_id' => 'On waitlist',
      'options' => [
        'limit' => $available,
        'sort' => 'register_date ASC',
      ],
    ]);
    
    foreach ($waitlisted['values'] as $participant) {
      // Move from waitlist to registered
      civicrm_api3('Participant', 'create', [
        'id' => $participant['id'],
        'status_id' => 'Registered',
      ]);
      
      // Send notification
      $notification = \Drupal::service('ilas_events.notification');
      $notification->sendWaitlistConfirmation($participant['id']);
      
      $this->logger->info('Moved participant @id from waitlist to registered', [
        '@id' => $participant['id'],
      ]);
    }
  }

  /**
   * Create location block for event.
   */
  protected function createLocationBlock($location_data) {
    try {
      $params = [
        'address' => [
          'street_address' => $location_data['street_address'] ?? '',
          'city' => $location_data['city'] ?? '',
          'state_province_id' => $this->getStateProvinceId($location_data['state'] ?? ''),
          'postal_code' => $location_data['postal_code'] ?? '',
        ],
      ];
      
      if (!empty($location_data['venue_name'])) {
        $params['address']['name'] = $location_data['venue_name'];
      }
      
      $loc_block = civicrm_api3('LocBlock', 'create', $params);
      
      return $loc_block['id'];
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to create location block: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return NULL;
    }
  }

  /**
   * Create event pricing.
   */
  protected function createEventPricing($event_id, $event_data) {
    try {
      // Create price set
      $price_set = civicrm_api3('PriceSet', 'create', [
        'title' => $event_data['title'] . ' - Registration',
        'name' => 'event_' . $event_id . '_registration',
        'is_active' => 1,
        'extends' => 'CiviEvent',
        'financial_type_id' => 'Event Fee',
      ]);
      
      // Create price field
      $price_field = civicrm_api3('PriceField', 'create', [
        'price_set_id' => $price_set['id'],
        'label' => 'Registration',
        'name' => 'registration',
        'html_type' => 'Radio',
        'is_required' => 1,
        'is_active' => 1,
      ]);
      
      // Create price options
      $options = [
        [
          'label' => 'Standard Registration',
          'amount' => $event_data['registration_fee'],
          'is_default' => 1,
        ],
      ];
      
      // Add early bird if configured
      if (!empty($event_data['early_bird_fee']) && !empty($event_data['early_bird_deadline'])) {
        $options[] = [
          'label' => 'Early Bird Registration',
          'amount' => $event_data['early_bird_fee'],
          'end_date' => $this->formatDateTime($event_data['early_bird_deadline']),
        ];
      }
      
      foreach ($options as $option) {
        $option['price_field_id'] = $price_field['id'];
        $option['financial_type_id'] = 'Event Fee';
        $option['is_active'] = 1;
        
        civicrm_api3('PriceFieldValue', 'create', $option);
      }
      
      // Assign price set to event
      civicrm_api3('PriceSetEntity', 'create', [
        'entity_table' => 'civicrm_event',
        'entity_id' => $event_id,
        'price_set_id' => $price_set['id'],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create event pricing: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Add custom fields for legal events.
   */
  protected function addLegalEventFields($event_id, $event_data) {
    // Add CLE credits if applicable
    if ($event_data['event_type_id'] == 'cle_training' && !empty($event_data['cle_credits'])) {
      // This would update custom fields in CiviCRM
      // Implementation depends on custom field configuration
    }
  }

  /**
   * Process event payment.
   */
  protected function processEventPayment($participant_id, $payment_data) {
    // Integrate with payment processor from Phase 5
    $payment_processor = \Drupal::service('ilas_payment.processor');
    
    // Process payment...
  }

  /**
   * Format date/time for CiviCRM.
   */
  protected function formatDateTime($datetime) {
    if (is_string($datetime)) {
      $datetime = new DrupalDateTime($datetime);
    }
    
    return $datetime->format('YmdHis');
  }

  /**
   * Get state/province ID.
   */
  protected function getStateProvinceId($state) {
    if (empty($state)) {
      return NULL;
    }
    
    try {
      $result = civicrm_api3('StateProvince', 'get', [
        'abbreviation' => $state,
        'country_id' => 1228, // United States
      ]);
      
      if ($result['count'] > 0) {
        return reset($result['values'])['id'];
      }
    }
    catch (\Exception $e) {
      // Log error
    }
    
    return NULL;
  }
}