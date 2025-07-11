<?php

namespace Drupal\ilas_chatbot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\webform\Entity\Webform;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Access\CsrfTokenGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for chatbot form endpoints.
 */
class ChatbotController extends ControllerBase {

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * Constructs a ChatbotController object.
   *
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   */
  public function __construct(CsrfTokenGenerator $csrf_token) {
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('csrf_token')
    );
  }

  /**
   * Returns form configuration for chatbot.
   */
  public function getFormConfig($form_type) {
    // Validate form type parameter
    if (!preg_match('/^[a-z_]+$/', $form_type)) {
      return new JsonResponse(['error' => 'Invalid form type'], 400);
    }

    $config = $this->config('ilas_chatbot.settings');
    $form_mappings = $config->get('form_mappings') ?: [];

    if (!isset($form_mappings[$form_type])) {
      return new JsonResponse(['error' => 'Form type not found'], 404);
    }

    $form_url = $form_mappings[$form_type];

    // Security: Validate the URL against trusted domains
    if (!$this->isValidFormUrl($form_url)) {
      return new JsonResponse(['error' => 'Form URL not allowed'], 403);
    }

    // Create cacheable response
    $response = new CacheableJsonResponse([
      'form_type' => $form_type,
      'title' => $this->getFormTitle($form_type),
      'description' => $this->getFormDescription($form_type),
      'url' => $form_url,
      'secure' => strpos($form_url, 'https://') === 0,
    ]);

    // Add cache metadata
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheTags(['config:ilas_chatbot.settings']);
    $cache_metadata->setCacheMaxAge(3600); // 1 hour cache
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  /**
   * Renders embedded form for iframe.
   */
  public function embedForm($webform_id) {
    // Validate webform ID parameter
    if (!preg_match('/^[a-z0-9_]+$/', $webform_id)) {
      throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid webform ID');
    }

    $webform = Webform::load($webform_id);
    
    if (!$webform) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Check webform access
    if (!$webform->access('submission_create')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $build = [
      '#type' => 'webform',
      '#webform' => $webform,
      '#attached' => [
        'library' => ['ilas_chatbot/embedded_form'],
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'name' => 'robots',
                'content' => 'noindex, nofollow',
              ],
            ],
            'robots_noindex',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'http-equiv' => 'X-Frame-Options',
                'content' => 'SAMEORIGIN',
              ],
            ],
            'x_frame_options',
          ],
        ],
      ],
    ];

    // Add wrapper for styling
    $output = [
      '#theme' => 'ilas_chatbot_embedded_form',
      '#content' => $build,
      '#webform_id' => $webform_id,
      '#cache' => [
        'tags' => $webform->getCacheTags(),
        'contexts' => ['user', 'session'],
        'max-age' => 0, // Don't cache form submissions
      ],
    ];

    return $output;
  }

  /**
   * Webhook endpoint for Dialogflow fulfillment.
   */
  public function webhook(Request $request) {
    // Security headers
    $response_headers = [
      'X-Content-Type-Options' => 'nosniff',
      'X-Frame-Options' => 'DENY',
      'X-XSS-Protection' => '1; mode=block',
    ];

    // Validate content type
    if ($request->headers->get('Content-Type') !== 'application/json') {
      return new JsonResponse(['error' => 'Invalid content type'], 400, $response_headers);
    }

    // Verify webhook authentication
    if (!$this->verifyWebhookAuth($request)) {
      return new JsonResponse(['error' => 'Unauthorized'], 401, $response_headers);
    }

    $content = $request->getContent();
    
    // Basic input validation
    if (strlen($content) > 10000) { // 10KB limit
      return new JsonResponse(['error' => 'Request too large'], 413, $response_headers);
    }

    $data = json_decode($content, TRUE);

    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
      return new JsonResponse(['error' => 'Invalid JSON'], 400, $response_headers);
    }

    // Validate required fields
    if (!isset($data['queryResult'])) {
      return new JsonResponse(['error' => 'Missing queryResult'], 400, $response_headers);
    }

    $intent = $data['queryResult']['intent']['displayName'] ?? '';
    $parameters = $data['queryResult']['parameters'] ?? [];

    // Sanitize parameters
    $parameters = $this->sanitizeParameters($parameters);

    $response_data = $this->processIntent($intent, $parameters);

    return new JsonResponse($response_data, 200, $response_headers);
  }

  /**
   * Process Dialogflow intent and return response.
   */
  protected function processIntent($intent, $parameters) {
    $response = [
      'fulfillmentText' => '',
      'fulfillmentMessages' => [],
    ];

    switch ($intent) {
      case 'GetLegalHelp':
        $response['fulfillmentText'] = 'I can help you with various legal matters. What type of assistance do you need?';
        $config = $this->config('ilas_chatbot.settings');
        $categories = $config->get('legal_categories') ?: [
          'Eviction Help',
          'Divorce/Custody', 
          'Benefits Appeal',
          'Small Claims',
        ];
        $response['fulfillmentMessages'][] = [
          'quickReplies' => [
            'title' => 'Select a category:',
            'quickReplies' => $categories,
          ],
        ];
        break;

      case 'StartForm':
        $formType = $parameters['formType'] ?? '';
        if ($formType && $this->isValidFormType($formType)) {
          $response['fulfillmentText'] = "I'll help you start the {$formType} form. Click the button below to begin.";
          $response['fulfillmentMessages'][] = [
            'payload' => [
              'richContent' => [[
                [
                  'type' => 'button',
                  'text' => 'Start Form',
                  'link' => "/form/embed/{$formType}",
                  'event' => [
                    'name' => 'start_form',
                    'parameters' => ['formType' => $formType],
                  ],
                ],
              ]],
            ],
          ];
        } else {
          $response['fulfillmentText'] = 'I\'m sorry, that form type is not available.';
        }
        break;

      default:
        $response['fulfillmentText'] = 'How can I assist you with your legal needs today?';
    }

    return $response;
  }

  /**
   * Validates form URL against trusted domains.
   */
  protected function isValidFormUrl($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, '/')) {
      return FALSE;
    }

    if (str_starts_with($url, '/')) {
      return TRUE; // Relative URLs are always allowed
    }

    $config = $this->config('ilas_chatbot.settings');
    $trusted_domains = $config->get('trusted_domains') ?: [];
    
    $url_parts = parse_url($url);
    $domain = $url_parts['host'] ?? '';

    return in_array($domain, $trusted_domains) || $domain === $this->getRequest()->getHost();
  }

  /**
   * Validates form type.
   */
  protected function isValidFormType($form_type) {
    $config = $this->config('ilas_chatbot.settings');
    $form_mappings = $config->get('form_mappings') ?: [];
    return isset($form_mappings[$form_type]);
  }

  /**
   * Gets form title by type.
   */
  protected function getFormTitle($form_type) {
    $config = $this->config('ilas_chatbot.settings');
    $form_titles = $config->get('form_titles') ?: [];
    
    // Use configured title if available, otherwise generate from form type
    return $form_titles[$form_type] ?? ucfirst(str_replace('_', ' ', $form_type)) . ' Form';
  }

  /**
   * Gets form description by type.
   */
  protected function getFormDescription($form_type) {
    $config = $this->config('ilas_chatbot.settings');
    $form_descriptions = $config->get('form_descriptions') ?: [];
    
    // Use configured description if available
    return $form_descriptions[$form_type] ?? 'Legal assistance form';
  }

  /**
   * Sanitizes webhook parameters.
   */
  protected function sanitizeParameters(array $parameters) {
    $sanitized = [];
    foreach ($parameters as $key => $value) {
      if (is_string($value)) {
        $sanitized[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
      } elseif (is_array($value)) {
        $sanitized[$key] = $this->sanitizeParameters($value);
      } else {
        $sanitized[$key] = $value;
      }
    }
    return $sanitized;
  }

  /**
   * Verifies webhook authentication.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return bool
   *   TRUE if authenticated, FALSE otherwise.
   */
  protected function verifyWebhookAuth(Request $request) {
    $config = $this->config('ilas_chatbot.settings');
    
    // Option 1: Verify by secret token (recommended for Dialogflow)
    $webhook_secret = $config->get('webhook_secret');
    if ($webhook_secret) {
      $auth_header = $request->headers->get('Authorization');
      if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
        $token = substr($auth_header, 7);
        return hash_equals($webhook_secret, $token);
      }
    }
    
    // Option 2: Verify by IP whitelist
    $allowed_ips = $config->get('webhook_allowed_ips') ?: [];
    if (!empty($allowed_ips)) {
      $client_ip = $request->getClientIp();
      return in_array($client_ip, $allowed_ips, TRUE);
    }
    
    // If no authentication is configured, reject the request
    return FALSE;
  }

}