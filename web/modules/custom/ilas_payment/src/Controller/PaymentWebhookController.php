<?php

namespace Drupal\ilas_payment\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_payment\Service\StripePaymentService;
use Drupal\ilas_payment\Service\PayPalPaymentService;

/**
 * Controller for payment webhook endpoints.
 */
class PaymentWebhookController extends ControllerBase {

  /**
   * The Stripe payment service.
   *
   * @var \Drupal\ilas_payment\Service\StripePaymentService
   */
  protected $stripeService;

  /**
   * The PayPal payment service.
   *
   * @var \Drupal\ilas_payment\Service\PayPalPaymentService
   */
  protected $paypalService;

  /**
   * Constructs a PaymentWebhookController.
   */
  public function __construct(StripePaymentService $stripe_service, PayPalPaymentService $paypal_service) {
    $this->stripeService = $stripe_service;
    $this->paypalService = $paypal_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilas_payment.stripe'),
      $container->get('ilas_payment.paypal')
    );
  }

  /**
   * Handle Stripe webhook.
   */
  public function stripeWebhook(Request $request) {
    $payload = $request->getContent();
    $signature = $request->headers->get('Stripe-Signature');
    
    $this->getLogger('ilas_payment')->info('Stripe webhook received');
    
    $result = $this->stripeService->handleWebhook($payload, $signature);
    
    if ($result['success']) {
      return new Response('OK', 200);
    }
    else {
      return new Response('Error: ' . ($result['error'] ?? 'Unknown error'), 400);
    }
  }

  /**
   * Handle PayPal IPN.
   */
  public function paypalIpn(Request $request) {
    $post_data = $request->request->all();
    
    $this->getLogger('ilas_payment')->info('PayPal IPN received');
    
    if ($this->paypalService->processIpn($post_data)) {
      return new Response('OK', 200);
    }
    else {
      return new Response('IPN processing failed', 400);
    }
  }
}