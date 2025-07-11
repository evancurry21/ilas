<?php

namespace Drupal\ilas_civicrm\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Site\Settings;

/**
 * Fixes CiviCRM resource URLs when accessed through proxy.
 */
class CiviCrmResourceSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
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

    // Check if we're in DDEV environment
    if (getenv('IS_DDEV_PROJECT') !== 'true') {
      return;
    }

    // Force the correct host for CiviCRM
    $request = $event->getRequest();
    $host = $request->getHost();
    
    // If accessed through localhost:3000 (BrowserSync) or just localhost
    if (strpos($host, 'localhost') !== FALSE) {
      // Set server variables to use DDEV URL
      $_SERVER['HTTP_HOST'] = 'ilas.ddev.site';
      $_SERVER['SERVER_NAME'] = 'ilas.ddev.site';
      $_SERVER['REQUEST_SCHEME'] = 'https';
      $_SERVER['HTTPS'] = 'on';
      $_SERVER['SERVER_PORT'] = 443;
      
      // Force trusted host
      $request->server->set('HTTP_HOST', 'ilas.ddev.site');
      $request->server->set('SERVER_NAME', 'ilas.ddev.site');
      $request->server->set('HTTPS', 'on');
      $request->server->set('SERVER_PORT', 443);
    }
  }
}