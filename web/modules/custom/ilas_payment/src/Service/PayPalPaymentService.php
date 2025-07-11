<?php

namespace Drupal\ilas_payment\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * PayPal payment processing service.
 */
class PayPalPaymentService {

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
   * The payment processor.
   *
   * @var \Drupal\ilas_payment\Service\PaymentProcessor
   */
  protected $paymentProcessor;

  /**
   * Constructs a PayPalPaymentService.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    PaymentProcessor $payment_processor
  ) {
    $this->logger = $logger_factory->get('ilas_payment.paypal');
    $this->configFactory = $config_factory;
    $this->paymentProcessor = $payment_processor;
  }

  /**
   * Process a PayPal payment.
   */
  public function processPayment(array $payment_data) {
    try {
      // For PayPal Standard, we redirect to PayPal
      // This is a simplified implementation
      
      $config = $this->configFactory->get('ilas_payment.settings');
      $business_email = $config->get('paypal_business_email');
      $test_mode = $config->get('test_mode');
      
      // Build PayPal URL
      $paypal_url = $test_mode ? 
        'https://www.sandbox.paypal.com/cgi-bin/webscr' : 
        'https://www.paypal.com/cgi-bin/webscr';
      
      // Build return URLs
      $return_url = \Drupal::request()->getSchemeAndHttpHost() . '/donate/thank-you/paypal';
      $cancel_url = \Drupal::request()->getSchemeAndHttpHost() . '/donate';
      $notify_url = \Drupal::request()->getSchemeAndHttpHost() . '/payment/paypal/ipn';
      
      // Store payment data in session for processing after return
      $session = \Drupal::request()->getSession();
      $session->set('paypal_payment_data', $payment_data);
      
      // Build PayPal parameters
      $params = [
        'cmd' => '_donations',
        'business' => $business_email,
        'amount' => $payment_data['amount'],
        'currency_code' => 'USD',
        'item_name' => 'Donation to Idaho Legal Aid Services',
        'return' => $return_url,
        'cancel_return' => $cancel_url,
        'notify_url' => $notify_url,
        'custom' => json_encode([
          'email' => $payment_data['email'],
          'campaign_id' => $payment_data['campaign_id'] ?? '',
        ]),
      ];
      
      // Add donor information
      if (!empty($payment_data['first_name'])) {
        $params['first_name'] = $payment_data['first_name'];
      }
      if (!empty($payment_data['last_name'])) {
        $params['last_name'] = $payment_data['last_name'];
      }
      if (!empty($payment_data['email'])) {
        $params['email'] = $payment_data['email'];
      }
      
      // For recurring payments
      if (!empty($payment_data['is_recurring'])) {
        $params['cmd'] = '_xclick-subscriptions';
        $params['a3'] = $payment_data['amount']; // Regular rate
        $params['p3'] = $payment_data['frequency_interval'] ?? 1; // Billing frequency
        $params['t3'] = $this->getPayPalPeriod($payment_data['frequency_unit'] ?? 'month');
        $params['src'] = '1'; // Recurring payments
        $params['sra'] = '1'; // Reattempt on failure
      }
      
      // Build redirect URL
      $redirect_url = $paypal_url . '?' . http_build_query($params);
      
      return [
        'success' => TRUE,
        'redirect_url' => $redirect_url,
        'transaction_id' => 'pending_paypal',
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('PayPal payment failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Get PayPal period code.
   */
  protected function getPayPalPeriod($unit) {
    $mapping = [
      'day' => 'D',
      'week' => 'W',
      'month' => 'M',
      'year' => 'Y',
    ];
    
    return $mapping[$unit] ?? 'M';
  }

  /**
   * Process PayPal IPN notification.
   */
  public function processIpn($post_data) {
    try {
      // Verify IPN
      if (!$this->verifyIpn($post_data)) {
        throw new \Exception('IPN verification failed');
      }
      
      // Process based on transaction type
      $txn_type = $post_data['txn_type'] ?? '';
      
      switch ($txn_type) {
        case 'web_accept':
          // One-time payment
          $this->processPayment($post_data);
          break;
          
        case 'subscr_signup':
          // Subscription created
          $this->processSubscriptionSignup($post_data);
          break;
          
        case 'subscr_payment':
          // Subscription payment
          $this->processSubscriptionPayment($post_data);
          break;
          
        case 'subscr_cancel':
        case 'subscr_eot':
          // Subscription canceled
          $this->processSubscriptionCancel($post_data);
          break;
      }
      
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('IPN processing failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      return FALSE;
    }
  }

  /**
   * Verify PayPal IPN.
   */
  protected function verifyIpn($post_data) {
    $config = $this->configFactory->get('ilas_payment.settings');
    $test_mode = $config->get('test_mode');
    
    $paypal_url = $test_mode ? 
      'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' : 
      'https://ipnpb.paypal.com/cgi-bin/webscr';
    
    // Build verification request
    $req = 'cmd=_notify-validate';
    foreach ($post_data as $key => $value) {
      $req .= '&' . $key . '=' . urlencode($value);
    }
    
    // Post back to PayPal
    $ch = curl_init($paypal_url);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: Close']);
    
    $res = curl_exec($ch);
    curl_close($ch);
    
    return $res == 'VERIFIED';
  }

  /**
   * Process one-time payment IPN.
   */
  protected function processPaymentIpn($data) {
    if ($data['payment_status'] != 'Completed') {
      return;
    }
    
    // Parse custom data
    $custom = json_decode($data['custom'] ?? '{}', TRUE);
    
    // Create payment data
    $payment_data = [
      'amount' => $data['payment_gross'],
      'email' => $data['payer_email'],
      'first_name' => $data['first_name'] ?? '',
      'last_name' => $data['last_name'] ?? '',
      'payment_method' => 'paypal',
      'campaign_id' => $custom['campaign_id'] ?? '',
    ];
    
    // Process through main processor
    $result = [
      'success' => TRUE,
      'transaction_id' => $data['txn_id'],
    ];
    
    $this->paymentProcessor->createContribution($payment_data, $result);
  }

  /**
   * Process subscription signup.
   */
  protected function processSubscriptionSignup($data) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Parse custom data
      $custom = json_decode($data['custom'] ?? '{}', TRUE);
      
      // Find or create contact
      $contact_params = [
        'email' => $data['payer_email'],
        'first_name' => $data['first_name'] ?? '',
        'last_name' => $data['last_name'] ?? '',
      ];
      
      $contact_id = $this->paymentProcessor->findOrCreateContact($contact_params);
      
      // Create recurring contribution
      $recur_params = [
        'contact_id' => $contact_id,
        'amount' => $data['amount3'],
        'currency' => 'USD',
        'frequency_unit' => $this->getFrequencyUnit($data['period3']),
        'frequency_interval' => $data['period3'],
        'start_date' => date('YmdHis'),
        'create_date' => date('YmdHis'),
        'contribution_status_id' => 'In Progress',
        'payment_processor_id' => $this->configFactory->get('ilas_payment.settings')->get('paypal_processor_id'),
        'processor_id' => $data['subscr_id'],
      ];
      
      civicrm_api3('ContributionRecur', 'create', $recur_params);
      
      $this->logger->info('PayPal subscription created: @id', [
        '@id' => $data['subscr_id'],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to process subscription signup: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Get frequency unit from PayPal period.
   */
  protected function getFrequencyUnit($period) {
    $unit = substr($period, -1);
    $mapping = [
      'D' => 'day',
      'W' => 'week',
      'M' => 'month',
      'Y' => 'year',
    ];
    
    return $mapping[$unit] ?? 'month';
  }
}