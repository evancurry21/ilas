<?php

namespace Drupal\ilas_events\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Service for syncing events between Drupal and CiviCRM.
 */
class EventSyncService {

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
   * The event manager.
   *
   * @var \Drupal\ilas_events\Service\EventManager
   */
  protected $eventManager;

  /**
   * Constructs an EventSyncService.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EventManager $event_manager
  ) {
    $this->logger = $logger_factory->get('ilas_events');
    $this->entityTypeManager = $entity_type_manager;
    $this->eventManager = $event_manager;
  }

  /**
   * Sync node to CiviCRM event.
   */
  public function syncNodeToEvent(NodeInterface $node) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get CiviCRM event ID if exists
      $event_id = $node->get('field_civicrm_event_id')->value;
      
      // Prepare event data
      $event_data = [
        'title' => $node->getTitle(),
        'summary' => $node->get('field_summary')->value,
        'description' => $node->get('body')->value,
        'start_date' => $node->get('field_event_date')->value,
        'end_date' => $node->get('field_event_date')->end_value,
        'event_type_id' => $node->get('field_event_type')->value,
        'is_public' => $node->isPublished() ? 1 : 0,
        'is_active' => 1,
        'is_online_registration' => $node->get('field_enable_registration')->value,
        'max_participants' => $node->get('field_max_participants')->value,
        'registration_end_date' => $node->get('field_registration_deadline')->value,
      ];
      
      // Handle location
      if ($node->hasField('field_location') && !$node->get('field_location')->isEmpty()) {
        $location_data = $this->prepareLocationData($node);
        $event_data['loc_block_id'] = $this->createOrUpdateLocation($location_data);
      }
      
      // Handle paid events
      if ($node->get('field_is_paid')->value) {
        $event_data['is_monetary'] = 1;
        $event_data['financial_type_id'] = 'Event Fee';
        $event_data['currency'] = 'USD';
        
        // Create price set
        $price_set_id = $this->createEventPriceSet($node);
        if ($price_set_id) {
          $event_data['price_set_id'] = $price_set_id;
        }
      }
      
      if ($event_id) {
        // Update existing event
        $event_data['id'] = $event_id;
        $result = civicrm_api3('Event', 'create', $event_data);
        
        $this->logger->info('Updated CiviCRM event @id for node @nid', [
          '@id' => $event_id,
          '@nid' => $node->id(),
        ]);
      }
      else {
        // Create new event
        $result = civicrm_api3('Event', 'create', $event_data);
        $event_id = $result['id'];
        
        // Save event ID back to node
        $node->set('field_civicrm_event_id', $event_id);
        $node->save();
        
        $this->logger->info('Created CiviCRM event @id for node @nid', [
          '@id' => $event_id,
          '@nid' => $node->id(),
        ]);
      }
      
      // Sync custom fields
      $this->syncCustomFields($event_id, $node);
      
      return $event_id;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to sync node to CiviCRM event: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Sync CiviCRM event to node.
   */
  public function syncEventToNode($event_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      
      // Find existing node
      $query = $this->entityTypeManager->getStorage('node')->getQuery();
      $query->condition('type', 'event')
            ->condition('field_civicrm_event_id', $event_id);
      $nids = $query->execute();
      
      if (!empty($nids)) {
        // Update existing node
        $node = $this->entityTypeManager->getStorage('node')->load(reset($nids));
      }
      else {
        // Create new node
        $node = $this->entityTypeManager->getStorage('node')->create([
          'type' => 'event',
          'uid' => 1,
        ]);
      }
      
      // Update node fields
      $node->setTitle($event['title']);
      $node->set('field_summary', $event['summary'] ?? '');
      $node->set('body', [
        'value' => $event['description'] ?? '',
        'format' => 'full_html',
      ]);
      $node->set('field_event_date', [
        'value' => $event['start_date'],
        'end_value' => $event['end_date'] ?? $event['start_date'],
      ]);
      $node->set('field_event_type', $event['event_type_id']);
      $node->set('field_enable_registration', $event['is_online_registration'] ?? 0);
      $node->set('field_max_participants', $event['max_participants'] ?? NULL);
      $node->set('field_registration_deadline', $event['registration_end_date'] ?? NULL);
      $node->set('field_civicrm_event_id', $event_id);
      $node->set('field_is_paid', $event['is_monetary'] ?? 0);
      
      if ($event['is_monetary']) {
        // Get registration fee
        $fee = $this->getEventRegistrationFee($event_id);
        if ($fee) {
          $node->set('field_registration_fee', $fee);
        }
      }
      
      // Set published status
      $node->setPublished($event['is_public'] && $event['is_active']);
      
      // Save node
      $node->save();
      
      $this->logger->info('Synced CiviCRM event @id to node @nid', [
        '@id' => $event_id,
        '@nid' => $node->id(),
      ]);
      
      return $node;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to sync CiviCRM event to node: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Cancel event from node deletion.
   */
  public function cancelEventFromNode(NodeInterface $node) {
    try {
      $event_id = $node->get('field_civicrm_event_id')->value;
      
      if (!$event_id) {
        return;
      }
      
      \Drupal::service('civicrm')->initialize();
      
      // Cancel event instead of deleting
      civicrm_api3('Event', 'create', [
        'id' => $event_id,
        'is_active' => 0,
        'is_public' => 0,
      ]);
      
      // Cancel all registrations
      $participants = civicrm_api3('Participant', 'get', [
        'event_id' => $event_id,
        'status_id' => ['NOT IN' => ['Cancelled']],
        'options' => ['limit' => 0],
      ]);
      
      foreach ($participants['values'] as $participant) {
        civicrm_api3('Participant', 'create', [
          'id' => $participant['id'],
          'status_id' => 'Cancelled',
        ]);
        
        // Send cancellation notice
        $notification = \Drupal::service('ilas_events.notification');
        $notification->sendCancellationNotice($participant['id']);
      }
      
      $this->logger->info('Cancelled CiviCRM event @id', ['@id' => $event_id]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to cancel CiviCRM event: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Prepare location data from node.
   */
  protected function prepareLocationData(NodeInterface $node) {
    $location = $node->get('field_location')->getValue()[0];
    
    return [
      'address' => [
        'name' => $location['organization'] ?? '',
        'street_address' => $location['address_line1'] ?? '',
        'supplemental_address_1' => $location['address_line2'] ?? '',
        'city' => $location['locality'] ?? '',
        'state_province' => $location['administrative_area'] ?? '',
        'postal_code' => $location['postal_code'] ?? '',
        'country' => $location['country_code'] ?? 'US',
      ],
    ];
  }

  /**
   * Create or update location block.
   */
  protected function createOrUpdateLocation($location_data) {
    try {
      // Create address
      $address = civicrm_api3('Address', 'create', $location_data['address']);
      
      // Create location block
      $loc_block = civicrm_api3('LocBlock', 'create', [
        'address_id' => $address['id'],
      ]);
      
      return $loc_block['id'];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create location: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return NULL;
    }
  }

  /**
   * Create event price set.
   */
  protected function createEventPriceSet(NodeInterface $node) {
    try {
      $fee = $node->get('field_registration_fee')->value;
      
      if (!$fee) {
        return NULL;
      }
      
      // Create price set
      $price_set = civicrm_api3('PriceSet', 'create', [
        'title' => $node->getTitle() . ' Registration',
        'name' => 'event_' . $node->id() . '_registration',
        'is_active' => 1,
        'extends' => 'CiviEvent',
        'financial_type_id' => 'Event Fee',
      ]);
      
      // Create price field
      $price_field = civicrm_api3('PriceField', 'create', [
        'price_set_id' => $price_set['id'],
        'label' => 'Registration Fee',
        'name' => 'registration_fee',
        'html_type' => 'Radio',
        'is_required' => 1,
        'is_active' => 1,
      ]);
      
      // Create price field value
      civicrm_api3('PriceFieldValue', 'create', [
        'price_field_id' => $price_field['id'],
        'label' => 'Standard Registration',
        'amount' => $fee,
        'is_default' => 1,
        'is_active' => 1,
        'financial_type_id' => 'Event Fee',
      ]);
      
      // Add early bird pricing if applicable
      if ($node->hasField('field_early_bird_fee') && 
          !$node->get('field_early_bird_fee')->isEmpty()) {
        civicrm_api3('PriceFieldValue', 'create', [
          'price_field_id' => $price_field['id'],
          'label' => 'Early Bird Registration',
          'amount' => $node->get('field_early_bird_fee')->value,
          'is_active' => 1,
          'financial_type_id' => 'Event Fee',
        ]);
      }
      
      return $price_set['id'];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create price set: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return NULL;
    }
  }

  /**
   * Get event registration fee.
   */
  protected function getEventRegistrationFee($event_id) {
    try {
      // Get price set
      $price_sets = civicrm_api3('PriceSetEntity', 'get', [
        'entity_table' => 'civicrm_event',
        'entity_id' => $event_id,
      ]);
      
      if ($price_sets['count'] == 0) {
        return NULL;
      }
      
      $price_set_id = reset($price_sets['values'])['price_set_id'];
      
      // Get default price
      $price_fields = civicrm_api3('PriceField', 'get', [
        'price_set_id' => $price_set_id,
        'options' => ['limit' => 1],
      ]);
      
      if ($price_fields['count'] == 0) {
        return NULL;
      }
      
      $price_field_id = reset($price_fields['values'])['id'];
      
      $price_values = civicrm_api3('PriceFieldValue', 'get', [
        'price_field_id' => $price_field_id,
        'is_default' => 1,
      ]);
      
      if ($price_values['count'] > 0) {
        return reset($price_values['values'])['amount'];
      }
      
      return NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Sync custom fields.
   */
  protected function syncCustomFields($event_id, NodeInterface $node) {
    // Sync legal clinic specific fields
    if ($node->get('field_event_type')->value == 'legal_clinic') {
      $custom_data = [];
      
      if ($node->hasField('field_case_types')) {
        $custom_data['custom_case_types'] = implode(',', 
          array_column($node->get('field_case_types')->getValue(), 'value')
        );
      }
      
      if ($node->hasField('field_income_eligible')) {
        $custom_data['custom_income_eligible'] = $node->get('field_income_eligible')->value;
      }
      
      if ($node->hasField('field_languages')) {
        $custom_data['custom_languages'] = implode(',', 
          array_column($node->get('field_languages')->getValue(), 'value')
        );
      }
      
      if (!empty($custom_data)) {
        $custom_data['id'] = $event_id;
        civicrm_api3('Event', 'create', $custom_data);
      }
    }
    
    // Sync CLE training fields
    if ($node->get('field_event_type')->value == 'cle_training') {
      $custom_data = [];
      
      if ($node->hasField('field_cle_credits')) {
        $custom_data['custom_cle_credits'] = $node->get('field_cle_credits')->value;
      }
      
      if ($node->hasField('field_accreditation_number')) {
        $custom_data['custom_accreditation_number'] = 
          $node->get('field_accreditation_number')->value;
      }
      
      if (!empty($custom_data)) {
        $custom_data['id'] = $event_id;
        civicrm_api3('Event', 'create', $custom_data);
      }
    }
  }

  /**
   * Sync all events.
   */
  public function syncAllEvents($direction = 'drupal_to_civicrm') {
    if ($direction == 'drupal_to_civicrm') {
      // Sync all event nodes to CiviCRM
      $query = $this->entityTypeManager->getStorage('node')->getQuery();
      $query->condition('type', 'event');
      $nids = $query->execute();
      
      foreach ($nids as $nid) {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        $this->syncNodeToEvent($node);
      }
    }
    else {
      // Sync all CiviCRM events to Drupal
      try {
        \Drupal::service('civicrm')->initialize();
        
        $events = civicrm_api3('Event', 'get', [
          'options' => ['limit' => 0],
        ]);
        
        foreach ($events['values'] as $event) {
          $this->syncEventToNode($event['id']);
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Failed to sync all events: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }
  }
}