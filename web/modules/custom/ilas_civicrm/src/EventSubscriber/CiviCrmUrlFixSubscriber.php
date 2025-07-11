<?php

namespace Drupal\ilas_civicrm\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Fixes CiviCRM URLs when accessed through proxy or BrowserSync.
 */
class CiviCrmUrlFixSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // High priority to run early
      KernelEvents::REQUEST => ['onRequest', 200],
      // Low priority to run after content is generated
      KernelEvents::RESPONSE => ['onResponse', -100],
    ];
  }

  /**
   * Fix CiviCRM URLs on request.
   */
  public function onRequest(RequestEvent $event) {
    // Only process main requests
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $host = $request->getHost();
    
    // Check if accessed through localhost (including localhost:3000 for BrowserSync)
    if (strpos($host, 'localhost') !== FALSE) {
      // Determine the correct host based on environment
      $correctHost = $this->getCorrectHost();
      
      if ($correctHost) {
        // Set server variables to use correct URL
        $_SERVER['HTTP_HOST'] = $correctHost;
        $_SERVER['SERVER_NAME'] = $correctHost;
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = 443;
        
        // Update request object
        $request->server->set('HTTP_HOST', $correctHost);
        $request->server->set('SERVER_NAME', $correctHost);
        $request->server->set('HTTPS', 'on');
        $request->server->set('SERVER_PORT', 443);
        
        // If CiviCRM is already initialized, update its config
        if (function_exists('CRM_Core_Config')) {
          try {
            $config = \CRM_Core_Config::singleton();
            $baseUrl = "https://{$correctHost}/";
            $config->userFrameworkBaseURL = $baseUrl;
            $config->userFrameworkResourceURL = $baseUrl . 'libraries/civicrm/';
            
            // Force update of any cached URLs
            if (method_exists($config, 'cleanURL')) {
              $config->cleanURL();
            }
          }
          catch (\Exception $e) {
            // CiviCRM might not be initialized yet, that's okay
            $this->logger->debug('Could not update CiviCRM config: ' . $e->getMessage());
          }
        }
        
        $this->logger->debug('Fixed CiviCRM URLs from localhost to ' . $correctHost);
      }
    }
  }

  /**
   * Fix CiviCRM URLs in response.
   */
  public function onResponse(ResponseEvent $event) {
    // Only process HTML responses
    $response = $event->getResponse();
    $contentType = $response->headers->get('Content-Type', '');
    
    if (strpos($contentType, 'text/html') === FALSE) {
      return;
    }

    $request = $event->getRequest();
    $host = $request->getHost();
    
    // Only process if accessed through localhost
    if (strpos($host, 'localhost') === FALSE) {
      return;
    }

    $content = $response->getContent();
    $correctHost = $this->getCorrectHost();
    
    if ($correctHost && $content) {
      // Replace localhost:3000 URLs with correct host
      $patterns = [
        '|https?://localhost:3000/libraries/civicrm/|i',
        '|https?://localhost/libraries/civicrm/|i',
        '|//localhost:3000/libraries/civicrm/|i',
        '|//localhost/libraries/civicrm/|i',
      ];
      
      $replacement = "https://{$correctHost}/libraries/civicrm/";
      $newContent = preg_replace($patterns, $replacement, $content);
      
      if ($newContent !== $content) {
        $response->setContent($newContent);
        $this->logger->debug('Replaced CiviCRM resource URLs in response');
      }
    }
  }

  /**
   * Get the correct host based on environment.
   */
  protected function getCorrectHost() {
    // Check for DDEV environment
    if (getenv('IS_DDEV_PROJECT') === 'true') {
      return 'ilas.ddev.site';
    }
    
    // Check for other environments
    $config = $this->configFactory->get('system.site');
    $baseUrl = $config->get('base_url');
    
    if ($baseUrl) {
      $parsedUrl = parse_url($baseUrl);
      if (isset($parsedUrl['host'])) {
        return $parsedUrl['host'];
      }
    }
    
    // Default fallback
    return NULL;
  }
}