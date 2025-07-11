<?php

namespace Drupal\ilas_payment\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure payment settings.
 */
class PaymentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilas_payment_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ilas_payment.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ilas_payment.settings');
    
    // General settings
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Settings'),
      '#open' => TRUE,
    ];
    
    $form['general']['test_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Test Mode'),
      '#description' => $this->t('Enable test mode for payment processing.'),
      '#default_value' => $config->get('test_mode') ?? TRUE,
    ];
    
    $form['general']['suggested_amounts'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suggested Donation Amounts'),
      '#description' => $this->t('Comma-separated list of suggested amounts (e.g., 25,50,100,250,500).'),
      '#default_value' => implode(',', $config->get('suggested_amounts') ?? [25, 50, 100, 250, 500]),
    ];
    
    $form['general']['minimum_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Donation Amount'),
      '#min' => 1,
      '#default_value' => $config->get('minimum_amount') ?? 5,
    ];
    
    $form['general']['receipt_email_from'] = [
      '#type' => 'email',
      '#title' => $this->t('Receipt Email From Address'),
      '#default_value' => $config->get('receipt_email_from') ?? \Drupal::config('system.site')->get('mail'),
      '#required' => TRUE,
    ];
    
    // Stripe settings
    $form['stripe'] = [
      '#type' => 'details',
      '#title' => $this->t('Stripe Settings'),
      '#open' => TRUE,
    ];
    
    $form['stripe']['stripe_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Stripe'),
      '#default_value' => $config->get('stripe_enabled') ?? FALSE,
    ];
    
    $form['stripe']['stripe_test_public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Publishable Key'),
      '#default_value' => $config->get('stripe_test_public_key'),
      '#states' => [
        'visible' => [
          ':input[name="stripe_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    $form['stripe']['stripe_test_secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Secret Key'),
      '#default_value' => $config->get('stripe_test_secret_key'),
      '#states' => [
        'visible' => [
          ':input[name="stripe_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    $form['stripe']['stripe_live_public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Live Publishable Key'),
      '#default_value' => $config->get('stripe_live_public_key'),
      '#states' => [
        'visible' => [
          ':input[name="stripe_enabled"]' => ['checked' => TRUE],
          ':input[name="test_mode"]' => ['checked' => FALSE],
        ],
      ],
    ];
    
    $form['stripe']['stripe_live_secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Live Secret Key'),
      '#default_value' => $config->get('stripe_live_secret_key'),
      '#states' => [
        'visible' => [
          ':input[name="stripe_enabled"]' => ['checked' => TRUE],
          ':input[name="test_mode"]' => ['checked' => FALSE],
        ],
      ],
    ];
    
    $form['stripe']['stripe_webhook_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webhook Signing Secret'),
      '#description' => $this->t('Stripe webhook endpoint: @url', [
        '@url' => \Drupal::request()->getSchemeAndHttpHost() . '/payment/stripe/webhook',
      ]),
      '#default_value' => $config->get('stripe_webhook_secret'),
      '#states' => [
        'visible' => [
          ':input[name="stripe_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    // PayPal settings
    $form['paypal'] = [
      '#type' => 'details',
      '#title' => $this->t('PayPal Settings'),
      '#open' => FALSE,
    ];
    
    $form['paypal']['paypal_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable PayPal'),
      '#default_value' => $config->get('paypal_enabled') ?? FALSE,
    ];
    
    $form['paypal']['paypal_business_email'] = [
      '#type' => 'email',
      '#title' => $this->t('PayPal Business Email'),
      '#default_value' => $config->get('paypal_business_email'),
      '#states' => [
        'visible' => [
          ':input[name="paypal_enabled"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="paypal_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    $form['paypal']['paypal_ipn_url'] = [
      '#type' => 'item',
      '#title' => $this->t('PayPal IPN URL'),
      '#markup' => \Drupal::request()->getSchemeAndHttpHost() . '/payment/paypal/ipn',
      '#description' => $this->t('Configure this URL in your PayPal account settings.'),
      '#states' => [
        'visible' => [
          ':input[name="paypal_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    // Financial types
    $form['financial'] = [
      '#type' => 'details',
      '#title' => $this->t('Financial Configuration'),
      '#open' => FALSE,
    ];
    
    $form['financial']['default_financial_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Financial Type'),
      '#options' => ilas_payment_get_financial_types(),
      '#default_value' => $config->get('default_financial_type') ?? 'Donation',
    ];
    
    $form['financial']['create_financial_types'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create default financial types'),
      '#description' => $this->t('Create standard financial types in CiviCRM if they do not exist.'),
      '#default_value' => FALSE,
    ];
    
    // Email templates
    $form['emails'] = [
      '#type' => 'details',
      '#title' => $this->t('Email Templates'),
      '#open' => FALSE,
    ];
    
    $form['emails']['send_receipts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send automatic receipts'),
      '#default_value' => $config->get('send_receipts') ?? TRUE,
    ];
    
    $form['emails']['receipt_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Receipt Email Subject'),
      '#default_value' => $config->get('receipt_subject') ?? 'Thank you for your donation to Idaho Legal Aid Services',
      '#states' => [
        'visible' => [
          ':input[name="send_receipts"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    $form['emails']['receipt_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Receipt Email Template'),
      '#description' => $this->t('Available tokens: [donor_name], [amount], [date], [transaction_id]'),
      '#default_value' => $config->get('receipt_template') ?? $this->getDefaultReceiptTemplate(),
      '#rows' => 10,
      '#states' => [
        'visible' => [
          ':input[name="send_receipts"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate suggested amounts
    $amounts = $form_state->getValue('suggested_amounts');
    $amounts_array = array_map('trim', explode(',', $amounts));
    
    foreach ($amounts_array as $amount) {
      if (!is_numeric($amount) || $amount <= 0) {
        $form_state->setErrorByName('suggested_amounts', $this->t('All suggested amounts must be positive numbers.'));
        break;
      }
    }
    
    // Validate Stripe keys if enabled
    if ($form_state->getValue('stripe_enabled')) {
      if ($form_state->getValue('test_mode')) {
        if (empty($form_state->getValue('stripe_test_public_key')) || 
            empty($form_state->getValue('stripe_test_secret_key'))) {
          $form_state->setErrorByName('stripe_test_public_key', $this->t('Test keys are required when Stripe is enabled in test mode.'));
        }
      }
      else {
        if (empty($form_state->getValue('stripe_live_public_key')) || 
            empty($form_state->getValue('stripe_live_secret_key'))) {
          $form_state->setErrorByName('stripe_live_public_key', $this->t('Live keys are required when Stripe is enabled in live mode.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ilas_payment.settings');
    
    // Process suggested amounts
    $amounts = $form_state->getValue('suggested_amounts');
    $amounts_array = array_map('intval', array_map('trim', explode(',', $amounts)));
    
    // Save configuration
    $config
      ->set('test_mode', $form_state->getValue('test_mode'))
      ->set('suggested_amounts', $amounts_array)
      ->set('minimum_amount', $form_state->getValue('minimum_amount'))
      ->set('receipt_email_from', $form_state->getValue('receipt_email_from'))
      ->set('stripe_enabled', $form_state->getValue('stripe_enabled'))
      ->set('stripe_test_public_key', $form_state->getValue('stripe_test_public_key'))
      ->set('stripe_test_secret_key', $form_state->getValue('stripe_test_secret_key'))
      ->set('stripe_live_public_key', $form_state->getValue('stripe_live_public_key'))
      ->set('stripe_live_secret_key', $form_state->getValue('stripe_live_secret_key'))
      ->set('stripe_webhook_secret', $form_state->getValue('stripe_webhook_secret'))
      ->set('paypal_enabled', $form_state->getValue('paypal_enabled'))
      ->set('paypal_business_email', $form_state->getValue('paypal_business_email'))
      ->set('default_financial_type', $form_state->getValue('default_financial_type'))
      ->set('send_receipts', $form_state->getValue('send_receipts'))
      ->set('receipt_subject', $form_state->getValue('receipt_subject'))
      ->set('receipt_template', $form_state->getValue('receipt_template'))
      ->save();
    
    // Create financial types if requested
    if ($form_state->getValue('create_financial_types')) {
      $this->createDefaultFinancialTypes();
    }
    
    // Create payment processors in CiviCRM if enabled
    if ($form_state->getValue('stripe_enabled')) {
      $this->createStripeProcessor($form_state);
    }
    
    if ($form_state->getValue('paypal_enabled')) {
      $this->createPayPalProcessor($form_state);
    }
    
    parent::submitForm($form, $form_state);
  }

  /**
   * Get default receipt template.
   */
  protected function getDefaultReceiptTemplate() {
    return "Dear [donor_name],

Thank you for your generous donation of $[amount] to Idaho Legal Aid Services.

Your donation helps us provide critical legal services to low-income individuals and families throughout Idaho. With your support, we can continue to ensure equal access to justice for all.

Donation Details:
Amount: $[amount]
Date: [date]
Transaction ID: [transaction_id]

This letter serves as your official tax receipt. Idaho Legal Aid Services is a 501(c)(3) nonprofit organization. Our tax ID number is XX-XXXXXXX. No goods or services were provided in exchange for this donation.

If you have any questions about your donation, please contact us at donate@idaholegalaid.org.

With gratitude,
Idaho Legal Aid Services";
  }

  /**
   * Create default financial types.
   */
  protected function createDefaultFinancialTypes() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $types = [
        'Donation' => 'General donations',
        'Event Fee' => 'Event registration fees',
        'Member Dues' => 'Membership dues',
        'Grant' => 'Grant funding',
        'Restricted Donation' => 'Donations restricted for specific purposes',
      ];
      
      foreach ($types as $name => $description) {
        // Check if exists
        $existing = civicrm_api3('FinancialType', 'get', [
          'name' => $name,
        ]);
        
        if ($existing['count'] == 0) {
          civicrm_api3('FinancialType', 'create', [
            'name' => $name,
            'description' => $description,
            'is_deductible' => 1,
            'is_active' => 1,
          ]);
          
          $this->messenger()->addStatus($this->t('Created financial type: @type', ['@type' => $name]));
        }
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to create financial types: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Create Stripe processor in CiviCRM.
   */
  protected function createStripeProcessor(FormStateInterface $form_state) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Check if Stripe processor type exists
      $processor_types = civicrm_api3('PaymentProcessorType', 'get', [
        'name' => 'Stripe',
      ]);
      
      if ($processor_types['count'] == 0) {
        // Create Stripe processor type
        $type_result = civicrm_api3('PaymentProcessorType', 'create', [
          'name' => 'Stripe',
          'title' => 'Stripe',
          'class_name' => 'Payment_Stripe',
          'billing_mode' => 1,
          'is_recur' => 1,
          'payment_type' => 1,
        ]);
        
        $processor_type_id = $type_result['id'];
      }
      else {
        $processor_type_id = reset($processor_types['values'])['id'];
      }
      
      // Create processor instance
      $test_mode = $form_state->getValue('test_mode');
      
      $processor_params = [
        'payment_processor_type_id' => $processor_type_id,
        'is_test' => $test_mode ? 1 : 0,
        'is_active' => 1,
        'is_default' => 1,
        'name' => 'Stripe ' . ($test_mode ? '(Test)' : '(Live)'),
        'user_name' => $test_mode ? 
          $form_state->getValue('stripe_test_public_key') : 
          $form_state->getValue('stripe_live_public_key'),
        'password' => $test_mode ? 
          $form_state->getValue('stripe_test_secret_key') : 
          $form_state->getValue('stripe_live_secret_key'),
        'signature' => $form_state->getValue('stripe_webhook_secret'),
      ];
      
      $processor = civicrm_api3('PaymentProcessor', 'create', $processor_params);
      
      // Save processor ID
      $config = \Drupal::configFactory()->getEditable('ilas_payment.settings');
      $config->set('stripe_processor_id', $processor['id'])->save();
      
      $this->messenger()->addStatus($this->t('Stripe payment processor configured in CiviCRM.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to create Stripe processor: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Create PayPal processor in CiviCRM.
   */
  protected function createPayPalProcessor(FormStateInterface $form_state) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $test_mode = $form_state->getValue('test_mode');
      
      $processor_params = [
        'payment_processor_type_id' => 1, // PayPal Standard
        'is_test' => $test_mode ? 1 : 0,
        'is_active' => 1,
        'name' => 'PayPal ' . ($test_mode ? '(Test)' : '(Live)'),
        'user_name' => $form_state->getValue('paypal_business_email'),
      ];
      
      $processor = civicrm_api3('PaymentProcessor', 'create', $processor_params);
      
      // Save processor ID
      $config = \Drupal::configFactory()->getEditable('ilas_payment.settings');
      $config->set('paypal_processor_id', $processor['id'])->save();
      
      $this->messenger()->addStatus($this->t('PayPal payment processor configured in CiviCRM.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to create PayPal processor: @error', ['@error' => $e->getMessage()]));
    }
  }
}