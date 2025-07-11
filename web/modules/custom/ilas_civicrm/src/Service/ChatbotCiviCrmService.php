<?php

namespace Drupal\ilas_civicrm\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service for integrating chatbot with CiviCRM.
 */
class ChatbotCiviCrmService {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ChatbotCiviCrmService.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory) {
    $this->logger = $logger_factory->get('ilas_civicrm');
    $this->configFactory = $config_factory;
  }

  /**
   * Process chatbot interaction and create CiviCRM activity.
   *
   * @param array $intent_data
   *   The intent data from chatbot.
   *
   * @return array
   *   Response data for chatbot.
   */
  public function processIntent(array $intent_data) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $response = [
        'success' => TRUE,
        'data' => [],
      ];
      
      switch ($intent_data['intent']) {
        case 'schedule_consultation':
          $response['data'] = $this->scheduleConsultation($intent_data);
          break;
          
        case 'find_resources':
          $response['data'] = $this->findResources($intent_data);
          break;
          
        case 'check_eligibility':
          $response['data'] = $this->checkEligibility($intent_data);
          break;
          
        default:
          // Log unknown intent
          $this->createChatbotActivity($intent_data, 'Unknown Intent');
      }
      
      return $response;
    }
    catch (\Exception $e) {
      $this->logger->error('Chatbot CiviCRM integration error: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [
        'success' => FALSE,
        'error' => 'Unable to process request',
      ];
    }
  }

  /**
   * Schedule a consultation.
   */
  protected function scheduleConsultation($data) {
    // Create or find contact
    $contact_id = $this->findOrCreateContact($data['contact_info'] ?? []);
    
    // Create activity for consultation request
    $activity = civicrm_api3('Activity', 'create', [
      'activity_type_id' => 'Meeting',
      'subject' => 'Legal Consultation Request - ' . ($data['service_area'] ?? 'General'),
      'details' => $this->formatConsultationDetails($data),
      'source_contact_id' => $contact_id,
      'target_contact_id' => $contact_id,
      'status_id' => 'Scheduled',
      'activity_date_time' => date('YmdHis'),
    ]);
    
    // Apply service area tag if provided
    if (!empty($data['service_area'])) {
      $this->applyServiceAreaTag($contact_id, $data['service_area']);
    }
    
    // Create case if appropriate
    if ($this->shouldCreateCase($data)) {
      $case = $this->createLegalCase($contact_id, $data);
      
      return [
        'message' => 'Your consultation request has been received. Case #' . $case['id'] . ' has been created.',
        'case_id' => $case['id'],
        'activity_id' => $activity['id'],
      ];
    }
    
    return [
      'message' => 'Your consultation request has been received. We will contact you within 2 business days.',
      'activity_id' => $activity['id'],
    ];
  }

  /**
   * Find legal resources.
   */
  protected function findResources($data) {
    $service_area = $data['service_area'] ?? '';
    $topic = $data['topic'] ?? '';
    
    // Log the resource request
    $this->createChatbotActivity($data, 'Resource Request', [
      'service_area' => $service_area,
      'topic' => $topic,
    ]);
    
    // Get resources based on tags
    $resources = [];
    
    // This would query your Drupal resources based on the service area/topic
    // For now, return a structured response
    return [
      'message' => 'Here are resources for ' . $service_area,
      'resources' => $resources,
      'form_url' => '/form/legal-help?area=' . urlencode($service_area),
    ];
  }

  /**
   * Check eligibility for services.
   */
  protected function checkEligibility($data) {
    // Create anonymous activity for eligibility check
    $activity = civicrm_api3('Activity', 'create', [
      'activity_type_id' => 'Eligibility Check',
      'subject' => 'Online Eligibility Check',
      'details' => json_encode($data),
      'status_id' => 'Completed',
      'activity_date_time' => date('YmdHis'),
    ]);
    
    // Basic eligibility logic (this would be more complex in reality)
    $income = $data['household_income'] ?? 0;
    $household_size = $data['household_size'] ?? 1;
    
    // Federal poverty guidelines calculation (simplified)
    $poverty_base = 14580;
    $poverty_increment = 5140;
    $poverty_guideline = $poverty_base + (($household_size - 1) * $poverty_increment);
    $eligibility_threshold = $poverty_guideline * 1.25; // 125% of poverty line
    
    $eligible = $income <= $eligibility_threshold;
    
    return [
      'eligible' => $eligible,
      'message' => $eligible ? 
        'Based on your information, you may qualify for our services.' : 
        'Your income may exceed our guidelines, but you might qualify for reduced-fee services.',
      'next_steps' => $eligible ? 
        ['Complete an application', 'Schedule a consultation'] : 
        ['Explore self-help resources', 'Contact us for sliding scale options'],
    ];
  }

  /**
   * Find or create a contact from chatbot data.
   */
  protected function findOrCreateContact($contact_info) {
    if (empty($contact_info['email'])) {
      // Create anonymous contact for tracking
      $result = civicrm_api3('Contact', 'create', [
        'contact_type' => 'Individual',
        'first_name' => 'Anonymous',
        'last_name' => 'Chatbot User ' . date('Y-m-d'),
      ]);
      return $result['id'];
    }
    
    // Check if contact exists
    $existing = civicrm_api3('Contact', 'get', [
      'email' => $contact_info['email'],
      'contact_type' => 'Individual',
    ]);
    
    if ($existing['count'] > 0) {
      return reset($existing['values'])['id'];
    }
    
    // Create new contact
    $params = [
      'contact_type' => 'Individual',
      'email' => $contact_info['email'],
    ];
    
    if (!empty($contact_info['name'])) {
      $name_parts = explode(' ', $contact_info['name'], 2);
      $params['first_name'] = $name_parts[0];
      if (isset($name_parts[1])) {
        $params['last_name'] = $name_parts[1];
      }
    }
    
    if (!empty($contact_info['phone'])) {
      $params['api.Phone.create'] = [
        'phone' => $contact_info['phone'],
        'location_type_id' => 'Home',
        'phone_type_id' => 'Phone',
      ];
    }
    
    $result = civicrm_api3('Contact', 'create', $params);
    
    return $result['id'];
  }

  /**
   * Create a generic chatbot activity.
   */
  protected function createChatbotActivity($data, $type = 'Chatbot Interaction', $additional_details = []) {
    $details = array_merge($data, $additional_details);
    
    $activity = civicrm_api3('Activity', 'create', [
      'activity_type_id' => $type,
      'subject' => 'Chatbot: ' . ($data['intent'] ?? 'Unknown Intent'),
      'details' => json_encode($details, JSON_PRETTY_PRINT),
      'status_id' => 'Completed',
      'activity_date_time' => date('YmdHis'),
    ]);
    
    return $activity['id'];
  }

  /**
   * Apply service area tag to contact.
   */
  protected function applyServiceAreaTag($contact_id, $service_area) {
    // Find the tag for this service area
    $tag_result = civicrm_api3('Tag', 'get', [
      'label' => $service_area,
      'used_for' => 'civicrm_contact',
    ]);
    
    if ($tag_result['count'] > 0) {
      $tag_id = reset($tag_result['values'])['id'];
      
      civicrm_api3('EntityTag', 'create', [
        'entity_table' => 'civicrm_contact',
        'entity_id' => $contact_id,
        'tag_id' => $tag_id,
      ]);
    }
  }

  /**
   * Determine if a case should be created.
   */
  protected function shouldCreateCase($data) {
    // Logic to determine if this warrants a case
    return !empty($data['create_case']) || 
           (!empty($data['urgency']) && $data['urgency'] == 'urgent');
  }

  /**
   * Create a legal case.
   */
  protected function createLegalCase($contact_id, $data) {
    // Create case
    $case_params = [
      'contact_id' => $contact_id,
      'case_type_id' => 'Legal Services',
      'subject' => $data['service_area'] ?? 'General Legal Assistance',
      'status_id' => 'Open',
      'start_date' => date('Y-m-d'),
    ];
    
    if (!empty($data['details'])) {
      $case_params['details'] = $data['details'];
    }
    
    $case = civicrm_api3('Case', 'create', $case_params);
    
    return $case;
  }

  /**
   * Format consultation details for activity.
   */
  protected function formatConsultationDetails($data) {
    $details = [];
    
    $fields = [
      'service_area' => 'Service Area',
      'urgency' => 'Urgency',
      'preferred_contact' => 'Preferred Contact Method',
      'best_time' => 'Best Time to Contact',
      'brief_description' => 'Description',
    ];
    
    foreach ($fields as $key => $label) {
      if (!empty($data[$key])) {
        $details[] = "<strong>$label:</strong> " . htmlspecialchars($data[$key]);
      }
    }
    
    return implode("<br>\n", $details);
  }
}