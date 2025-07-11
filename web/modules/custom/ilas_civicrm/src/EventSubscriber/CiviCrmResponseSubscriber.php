<?php

namespace Drupal\ilas_civicrm\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\ilas_civicrm\Service\CiviCrmResourceManager;

/**
 * Fixes CiviCRM URLs in responses.
 */
class CiviCrmResponseSubscriber implements EventSubscriberInterface {

  /**
   * The resource manager.
   *
   * @var \Drupal\ilas_civicrm\Service\CiviCrmResourceManager
   */
  protected $resourceManager;

  /**
   * Whether CiviCRM is initialized for this request.
   *
   * @var bool
   */
  protected $civiInitialized = FALSE;

  /**
   * Constructs a CiviCrmResponseSubscriber.
   */
  public function __construct(CiviCrmResourceManager $resource_manager) {
    $this->resourceManager = $resource_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // High priority to fix URLs early
      KernelEvents::REQUEST => ['onRequest', 300],
      // Low priority to catch all response modifications
      KernelEvents::RESPONSE => ['onResponse', -200],
    ];
  }

  /**
   * Fix CiviCRM configuration on request.
   */
  public function onRequest(RequestEvent $event) {
    // Only process main requests
    if (!$event->isMainRequest()) {
      return;
    }

    // Check if this is a CiviCRM-related request
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    
    if (strpos($path, '/civicrm') !== FALSE || 
        strpos($path, '/pro-bono-program') !== FALSE ||
        $request->query->has('q') && strpos($request->query->get('q'), 'civicrm') !== FALSE) {
      
      // Fix CiviCRM resource URLs
      $this->resourceManager->fixResourceUrls();
      $this->civiInitialized = TRUE;
    }
  }

  /**
   * Fix URLs in response content.
   */
  public function onResponse(ResponseEvent $event) {
    // Only process main requests
    if (!$event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();
    $content = $response->getContent();
    
    // Only process HTML responses
    if (!$content || strpos($response->headers->get('Content-Type', ''), 'text/html') === FALSE) {
      return;
    }

    // Check if content has localhost references
    if (strpos($content, 'localhost:3000') !== FALSE || 
        strpos($content, '://localhost') !== FALSE) {
      
      // Fix URLs in content
      $fixedContent = $this->resourceManager->fixUrlsInContent($content);
      
      if ($fixedContent !== $content) {
        $response->setContent($fixedContent);
        
        // Log that we fixed URLs
        \Drupal::logger('ilas_civicrm')->notice('Fixed localhost URLs in response for path: @path', [
          '@path' => $event->getRequest()->getPathInfo(),
        ]);
      }
    }
  }
}