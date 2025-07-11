<?php

namespace Drupal\ilas_civicrm\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Service for processing webform submissions with CiviCRM.
 */
class WebformCiviCrmProcessor {

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
   * Constructs a WebformCiviCrmProcessor.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->logger = $logger_factory->get('ilas_civicrm');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Process a webform submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
   * @param array $mapping
   *   Field mapping configuration.
   *
   * @return array
   *   Result of the processing.
   */
  public function processSubmission(WebformSubmissionInterface $submission, array $mapping = []) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $data = $submission->getData();
      $webform = $submission->getWebform();
      
      // Process based on webform type
      $result = [];
      
      switch ($webform->id()) {
        case 'legal_help':
          $result = $this->processLegalHelpForm($data, $mapping);
          break;
          
        case 'volunteer_application':
          $result = $this->processVolunteerApplication($data, $mapping);
          break;
          
        case 'donation_form':
          $result = $this->processDonationForm($data, $mapping);
          break;
          
        default:
          $result = $this->processGenericForm($data, $mapping);
      }
      
      // Store CiviCRM IDs in submission data
      if (!empty($result['contact_id'])) {
        $submission->setElementData('civicrm_contact_id', $result['contact_id']);
      }
      if (!empty($result['activity_id'])) {
        $submission->setElementData('civicrm_activity_id', $result['activity_id']);
      }
      if (!empty($result['case_id'])) {
        $submission->setElementData('civicrm_case_id', $result['case_id']);
      }
      
      $submission->save();
      
      return $result;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to process webform submission: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Process legal help form submission.
   */
  protected function processLegalHelpForm($data, $mapping) {
    // Create or update contact
    $contact_id = $this->createOrUpdateContact($data);
    
    // Create activity
    $activity_type = $this->getActivityType('Legal Help Request');
    
    $activity_params = [
      'activity_type_id' => $activity_type,
      'subject' => 'Legal Help Request - ' . ($data['legal_issue_type'] ?? 'General'),
      'details' => $this->formatLegalHelpDetails($data),
      'source_contact_id' => $contact_id,
      'target_contact_id' => $contact_id,
      'status_id' => 'Scheduled',
      'activity_date_time' => date('YmdHis'),
    ];
    
    // Add priority if urgent
    if (!empty($data['is_urgent']) && $data['is_urgent'] == 'yes') {
      $activity_params['priority_id'] = 'Urgent';
    }
    
    $activity = civicrm_api3('Activity', 'create', $activity_params);
    
    // Create case if appropriate
    $case_id = null;
    if ($this->shouldCreateCase($data)) {
      $case = $this->createLegalCase($contact_id, $data);
      $case_id = $case['id'];
      
      // Link activity to case
      civicrm_api3('CaseActivity', 'create', [
        'case_id' => $case_id,
        'activity_id' => $activity['id'],
      ]);
    }
    
    // Apply tags
    $this->applyTags($contact_id, $data);
    
    return [
      'success' => TRUE,
      'contact_id' => $contact_id,
      'activity_id' => $activity['id'],
      'case_id' => $case_id,
    ];
  }

  /**
   * Process volunteer application.
   */
  protected function processVolunteerApplication($data, $mapping) {
    // Create or update contact
    $contact_id = $this->createOrUpdateContact($data);
    
    // Create volunteer activity
    $activity_params = [
      'activity_type_id' => 'Volunteer',
      'subject' => 'Volunteer Application - ' . ($data['volunteer_area'] ?? 'General'),
      'details' => $this->formatVolunteerDetails($data),
      'source_contact_id' => $contact_id,
      'target_contact_id' => $contact_id,
      'status_id' => 'Scheduled',
      'activity_date_time' => date('YmdHis'),
    ];
    
    $activity = civicrm_api3('Activity', 'create', $activity_params);
    
    // Add volunteer tag
    $this->applyTag($contact_id, 'Volunteer');
    
    // Add skills as tags
    if (!empty($data['skills'])) {
      foreach ($data['skills'] as $skill) {
        $this->applyTag($contact_id, 'Skill: ' . $skill);
      }
    }
    
    return [
      'success' => TRUE,
      'contact_id' => $contact_id,
      'activity_id' => $activity['id'],
    ];
  }

  /**
   * Process donation form.
   */
  protected function processDonationForm($data, $mapping) {
    // Create or update contact
    $contact_id = $this->createOrUpdateContact($data);
    
    // Create contribution
    $contribution_params = [
      'contact_id' => $contact_id,
      'financial_type_id' => 'Donation',
      'total_amount' => $data['amount'] ?? 0,
      'currency' => 'USD',
      'receive_date' => date('YmdHis'),
      'contribution_status_id' => 'Pending',
      'source' => 'Online Donation Form',
    ];
    
    if (!empty($data['recurring']) && $data['recurring'] == 'yes') {
      // Set up recurring contribution
      $contribution_params['is_recur'] = 1;
      $contribution_params['frequency_interval'] = 1;
      $contribution_params['frequency_unit'] = $data['frequency'] ?? 'month';
    }
    
    $contribution = civicrm_api3('Contribution', 'create', $contribution_params);
    
    // Add donor tag
    $this->applyTag($contact_id, 'Donor');
    
    return [
      'success' => TRUE,
      'contact_id' => $contact_id,
      'contribution_id' => $contribution['id'],
    ];
  }

  /**
   * Process generic form submission.
   */
  protected function processGenericForm($data, $mapping) {
    // Create or update contact
    $contact_id = $this->createOrUpdateContact($data);
    
    // Create generic activity
    $activity_params = [
      'activity_type_id' => 'Contact',
      'subject' => 'Website Form Submission',
      'details' => json_encode($data, JSON_PRETTY_PRINT),
      'source_contact_id' => $contact_id,
      'target_contact_id' => $contact_id,
      'status_id' => 'Completed',
      'activity_date_time' => date('YmdHis'),
    ];
    
    $activity = civicrm_api3('Activity', 'create', $activity_params);
    
    return [
      'success' => TRUE,
      'contact_id' => $contact_id,
      'activity_id' => $activity['id'],
    ];
  }

  /**
   * Create or update a contact from form data.
   */
  protected function createOrUpdateContact($data) {
    $params = [
      'contact_type' => 'Individual',
    ];
    
    // Email is primary identifier
    if (!empty($data['email'])) {
      $params['email'] = $data['email'];
      
      // Check if contact exists
      $existing = civicrm_api3('Contact', 'get', [
        'email' => $data['email'],
        'contact_type' => 'Individual',
      ]);
      
      if ($existing['count'] > 0) {
        $params['id'] = reset($existing['values'])['id'];
      }
    }
    
    // Name fields
    if (!empty($data['first_name'])) {
      $params['first_name'] = $data['first_name'];
    }
    if (!empty($data['last_name'])) {
      $params['last_name'] = $data['last_name'];
    }
    if (!empty($data['name']) && empty($params['first_name'])) {
      $name_parts = explode(' ', $data['name'], 2);
      $params['first_name'] = $name_parts[0];
      if (isset($name_parts[1])) {
        $params['last_name'] = $name_parts[1];
      }
    }
    
    // Phone
    if (!empty($data['phone'])) {
      $params['api.Phone.create'] = [
        'phone' => $data['phone'],
        'location_type_id' => 'Home',
        'phone_type_id' => 'Phone',
      ];
    }
    
    // Address
    $address_params = [];
    if (!empty($data['street_address'])) {
      $address_params['street_address'] = $data['street_address'];
    }
    if (!empty($data['city'])) {
      $address_params['city'] = $data['city'];
    }
    if (!empty($data['state_province'])) {
      $address_params['state_province_id'] = $this->getStateProvinceId($data['state_province']);
    }
    if (!empty($data['postal_code'])) {
      $address_params['postal_code'] = $data['postal_code'];
    }
    
    if (!empty($address_params)) {
      $address_params['location_type_id'] = 'Home';
      $params['api.Address.create'] = $address_params;
    }
    
    $result = civicrm_api3('Contact', 'create', $params);
    
    return $result['id'];
  }

  /**
   * Get activity type ID.
   */
  protected function getActivityType($label) {
    static $types = [];
    
    if (!isset($types[$label])) {
      $result = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'activity_type',
        'label' => $label,
      ]);
      
      if ($result['count'] > 0) {
        $types[$label] = reset($result['values'])['value'];
      } else {
        // Create the activity type
        $create = civicrm_api3('OptionValue', 'create', [
          'option_group_id' => 'activity_type',
          'label' => $label,
          'name' => str_replace(' ', '_', strtolower($label)),
        ]);
        $types[$label] = $create['values'][$create['id']]['value'];
      }
    }
    
    return $types[$label];
  }

  /**
   * Get state/province ID from abbreviation or name.
   */
  protected function getStateProvinceId($state) {
    $result = civicrm_api3('StateProvince', 'get', [
      'abbreviation' => $state,
      'country_id' => 1228, // United States
    ]);
    
    if ($result['count'] == 0) {
      // Try by name
      $result = civicrm_api3('StateProvince', 'get', [
        'name' => $state,
        'country_id' => 1228,
      ]);
    }
    
    if ($result['count'] > 0) {
      return reset($result['values'])['id'];
    }
    
    return NULL;
  }

  /**
   * Apply tags to contact.
   */
  protected function applyTags($contact_id, $data) {
    // Apply service area tags
    if (!empty($data['legal_issue_type'])) {
      $this->applyTag($contact_id, $data['legal_issue_type']);
    }
    
    // Apply income level tag
    if (!empty($data['income_level'])) {
      $this->applyTag($contact_id, 'Income: ' . $data['income_level']);
    }
  }

  /**
   * Apply a single tag to contact.
   */
  protected function applyTag($contact_id, $tag_label) {
    // Find or create tag
    $tag_result = civicrm_api3('Tag', 'get', [
      'label' => $tag_label,
      'used_for' => 'civicrm_contact',
    ]);
    
    if ($tag_result['count'] == 0) {
      // Create tag
      $tag_result = civicrm_api3('Tag', 'create', [
        'label' => $tag_label,
        'name' => str_replace([' ', ':'], '_', strtolower($tag_label)),
        'used_for' => 'civicrm_contact',
      ]);
    }
    
    $tag_id = reset($tag_result['values'])['id'];
    
    // Apply tag to contact
    civicrm_api3('EntityTag', 'create', [
      'entity_table' => 'civicrm_contact',
      'entity_id' => $contact_id,
      'tag_id' => $tag_id,
    ]);
  }

  /**
   * Determine if a case should be created.
   */
  protected function shouldCreateCase($data) {
    // Create case for urgent requests or specific issue types
    return (!empty($data['is_urgent']) && $data['is_urgent'] == 'yes') ||
           (!empty($data['legal_issue_type']) && in_array($data['legal_issue_type'], [
             'Eviction', 'Domestic Violence', 'Child Custody', 'Emergency Benefits'
           ]));
  }

  /**
   * Create a legal case.
   */
  protected function createLegalCase($contact_id, $data) {
    $case_params = [
      'contact_id' => $contact_id,
      'case_type_id' => 'Legal Services',
      'subject' => $data['legal_issue_type'] ?? 'General Legal Assistance',
      'status_id' => 'Open',
      'start_date' => date('Y-m-d'),
    ];
    
    if (!empty($data['case_details'])) {
      $case_params['details'] = $data['case_details'];
    }
    
    $case = civicrm_api3('Case', 'create', $case_params);
    
    return $case;
  }

  /**
   * Format legal help details.
   */
  protected function formatLegalHelpDetails($data) {
    $details = [];
    
    $fields = [
      'legal_issue_type' => 'Legal Issue Type',
      'county' => 'County',
      'income_level' => 'Income Level',
      'household_size' => 'Household Size',
      'has_attorney' => 'Has Attorney',
      'court_date' => 'Court Date',
      'description' => 'Description',
    ];
    
    foreach ($fields as $key => $label) {
      if (!empty($data[$key])) {
        $details[] = "<strong>$label:</strong> " . htmlspecialchars($data[$key]);
      }
    }
    
    return implode("<br>\n", $details);
  }

  /**
   * Format volunteer details.
   */
  protected function formatVolunteerDetails($data) {
    $details = [];
    
    $fields = [
      'volunteer_area' => 'Volunteer Area',
      'availability' => 'Availability',
      'skills' => 'Skills',
      'experience' => 'Experience',
      'languages' => 'Languages',
      'motivation' => 'Motivation',
    ];
    
    foreach ($fields as $key => $label) {
      if (!empty($data[$key])) {
        $value = is_array($data[$key]) ? implode(', ', $data[$key]) : $data[$key];
        $details[] = "<strong>$label:</strong> " . htmlspecialchars($value);
      }
    }
    
    return implode("<br>\n", $details);
  }
}