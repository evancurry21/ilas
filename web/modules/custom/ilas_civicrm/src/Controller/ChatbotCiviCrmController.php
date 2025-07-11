<?php

namespace Drupal\ilas_civicrm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_civicrm\Service\ChatbotCiviCrmService;

/**
 * Controller for chatbot CiviCRM integration endpoints.
 */
class ChatbotCiviCrmController extends ControllerBase {

  /**
   * The chatbot CiviCRM service.
   *
   * @var \Drupal\ilas_civicrm\Service\ChatbotCiviCrmService
   */
  protected $chatbotService;

  /**
   * Constructs a ChatbotCiviCrmController.
   */
  public function __construct(ChatbotCiviCrmService $chatbot_service) {
    $this->chatbotService = $chatbot_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilas_civicrm.chatbot')
    );
  }

  /**
   * Process chatbot webhook with CiviCRM integration.
   */
  public function webhookEnhanced(Request $request) {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);
    
    if (!$data) {
      return new JsonResponse(['error' => 'Invalid request'], 400);
    }
    
    // Extract intent and parameters
    $intent = $data['queryResult']['intent']['displayName'] ?? '';
    $parameters = $data['queryResult']['parameters'] ?? [];
    $session = $data['session'] ?? '';
    
    // Process through CiviCRM service
    $result = $this->chatbotService->processIntent([
      'intent' => $intent,
      'parameters' => $parameters,
      'session' => $session,
      'contact_info' => $this->extractContactInfo($data),
    ]);
    
    // Build Dialogflow response
    $response = [
      'fulfillmentText' => $result['data']['message'] ?? 'Thank you for your inquiry.',
      'fulfillmentMessages' => [],
    ];
    
    // Add custom payload if additional data
    if (!empty($result['data']['resources']) || !empty($result['data']['next_steps'])) {
      $response['fulfillmentMessages'][] = [
        'payload' => [
          'custom' => $result['data'],
        ],
      ];
    }
    
    // Add output contexts for session management
    if (!empty($result['data']['case_id'])) {
      $response['outputContexts'] = [
        [
          'name' => $session . '/contexts/case-created',
          'lifespanCount' => 5,
          'parameters' => [
            'case_id' => $result['data']['case_id'],
          ],
        ],
      ];
    }
    
    return new JsonResponse($response);
  }

  /**
   * Extract contact information from Dialogflow data.
   */
  protected function extractContactInfo($data) {
    $contact_info = [];
    
    // Check output contexts for stored contact info
    $contexts = $data['queryResult']['outputContexts'] ?? [];
    foreach ($contexts as $context) {
      if (strpos($context['name'], '/contact-info') !== FALSE) {
        $params = $context['parameters'] ?? [];
        if (!empty($params['email'])) {
          $contact_info['email'] = $params['email'];
        }
        if (!empty($params['name'])) {
          $contact_info['name'] = $params['name'];
        }
        if (!empty($params['phone'])) {
          $contact_info['phone'] = $params['phone'];
        }
      }
    }
    
    // Also check direct parameters
    $params = $data['queryResult']['parameters'] ?? [];
    if (!empty($params['email'])) {
      $contact_info['email'] = $params['email'];
    }
    if (!empty($params['person'])) {
      $contact_info['name'] = $params['person']['name'] ?? '';
    }
    
    return $contact_info;
  }

  /**
   * Custom access callback for webhook.
   */
  public static function access() {
    // Check for API key in headers if configured
    $config = \Drupal::config('ilas_civicrm.settings');
    $api_key = $config->get('chatbot_api_key');
    
    if (empty($api_key)) {
      // No API key configured, allow access
      return \Drupal\Core\Access\AccessResult::allowed();
    }
    
    $request = \Drupal::request();
    $provided_key = $request->headers->get('X-API-Key');
    
    if ($provided_key === $api_key) {
      return \Drupal\Core\Access\AccessResult::allowed();
    }
    
    return \Drupal\Core\Access\AccessResult::forbidden();
  }

  /**
   * Get CiviCRM data for chatbot context.
   */
  public function getChatbotContext(Request $request) {
    $email = $request->query->get('email');
    
    if (!$email) {
      return new JsonResponse(['error' => 'Email required'], 400);
    }
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Find contact
      $contact = civicrm_api3('Contact', 'get', [
        'email' => $email,
        'contact_type' => 'Individual',
        'return' => ['id', 'first_name', 'last_name', 'email'],
      ]);
      
      if ($contact['count'] == 0) {
        return new JsonResponse(['exists' => FALSE]);
      }
      
      $contact_data = reset($contact['values']);
      $contact_id = $contact_data['id'];
      
      // Get recent activities
      $activities = civicrm_api3('Activity', 'get', [
        'contact_id' => $contact_id,
        'options' => ['limit' => 5, 'sort' => 'activity_date_time DESC'],
        'return' => ['subject', 'activity_type_id', 'activity_date_time'],
      ]);
      
      // Get open cases
      $cases = civicrm_api3('Case', 'get', [
        'contact_id' => $contact_id,
        'is_deleted' => 0,
        'status_id' => ['!=' => 'Closed'],
        'return' => ['id', 'subject', 'status_id'],
      ]);
      
      return new JsonResponse([
        'exists' => TRUE,
        'contact' => [
          'id' => $contact_id,
          'name' => $contact_data['first_name'] . ' ' . $contact_data['last_name'],
          'email' => $contact_data['email'],
        ],
        'recent_activities' => array_values($activities['values']),
        'open_cases' => array_values($cases['values']),
      ]);
      
    }
    catch (\Exception $e) {
      $this->getLogger('ilas_civicrm')->error('Failed to get chatbot context: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return new JsonResponse(['error' => 'Unable to retrieve data'], 500);
    }
  }
}