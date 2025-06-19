<?php

namespace Drupal\ilas_chatbot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\webform\Entity\Webform;

/**
 * Controller for chatbot form endpoints.
 */
class ChatbotController extends ControllerBase {

  /**
   * Returns form configuration for chatbot.
   */
  public function getFormConfig($form_type) {
    $forms = [
      'eviction' => [
        'webform_id' => 'eviction_assistance',
        'title' => 'Eviction Assistance Form',
        'description' => 'Get help with eviction proceedings',
      ],
      'divorce' => [
        'webform_id' => 'divorce_custody',
        'title' => 'Divorce and Custody Form',
        'description' => 'File for divorce or custody modifications',
      ],
      'benefits' => [
        'webform_id' => 'benefits_appeal',
        'title' => 'Benefits Appeal Form',
        'description' => 'Appeal denied benefits',
      ],
      'small_claims' => [
        'webform_id' => 'small_claims',
        'title' => 'Small Claims Court Form',
        'description' => 'File a small claims case',
      ],
    ];

    if (!isset($forms[$form_type])) {
      return new JsonResponse(['error' => 'Form type not found'], 404);
    }

    $form_info = $forms[$form_type];
    $webform = Webform::load($form_info['webform_id']);

    if (!$webform) {
      return new JsonResponse(['error' => 'Webform not configured'], 404);
    }

    return new JsonResponse([
      'form_type' => $form_type,
      'webform_id' => $form_info['webform_id'],
      'title' => $form_info['title'],
      'description' => $form_info['description'],
      'url' => '/form/embed/' . $form_info['webform_id'],
    ]);
  }

  /**
   * Renders embedded form for iframe.
   */
  public function embedForm($webform_id) {
    $webform = Webform::load($webform_id);
    
    if (!$webform) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $build = [
      '#type' => 'webform',
      '#webform' => $webform,
      '#attached' => [
        'library' => ['ilas_chatbot/embedded_form'],
      ],
    ];

    // Add wrapper for styling
    $output = [
      '#theme' => 'ilas_chatbot_embedded_form',
      '#content' => $build,
      '#webform_id' => $webform_id,
    ];

    return $output;
  }

  /**
   * Webhook endpoint for Dialogflow fulfillment.
   */
  public function webhook(Request $request) {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (!$data) {
      return new JsonResponse(['error' => 'Invalid request'], 400);
    }

    $intent = $data['queryResult']['intent']['displayName'] ?? '';
    $parameters = $data['queryResult']['parameters'] ?? [];

    $response = $this->processIntent($intent, $parameters);

    return new JsonResponse($response);
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
        $response['fulfillmentMessages'][] = [
          'quickReplies' => [
            'title' => 'Select a category:',
            'quickReplies' => [
              'Eviction Help',
              'Divorce/Custody',
              'Benefits Appeal',
              'Small Claims',
            ],
          ],
        ];
        break;

      case 'StartForm':
        $formType = $parameters['formType'] ?? '';
        if ($formType) {
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
        }
        break;

      default:
        $response['fulfillmentText'] = 'How can I assist you with your legal needs today?';
    }

    return $response;
  }

}