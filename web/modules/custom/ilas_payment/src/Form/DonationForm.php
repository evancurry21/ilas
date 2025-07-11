<?php

namespace Drupal\ilas_payment\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_payment\Service\PaymentProcessor;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Donation form.
 */
class DonationForm extends FormBase {

  /**
   * The payment processor.
   *
   * @var \Drupal\ilas_payment\Service\PaymentProcessor
   */
  protected $paymentProcessor;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a DonationForm.
   */
  public function __construct(PaymentProcessor $payment_processor, MessengerInterface $messenger) {
    $this->paymentProcessor = $payment_processor;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilas_payment.processor'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilas_donation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $campaign_id = NULL) {
    $config = \Drupal::config('ilas_payment.settings');
    
    // Add CSS and JS
    $form['#attached']['library'][] = 'ilas_payment/donation_form';
    
    // Donation amount
    $form['amount_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['donation-amount-wrapper']],
    ];
    
    // Suggested amounts
    $suggested_amounts = $config->get('suggested_amounts') ?? [25, 50, 100, 250, 500];
    $form['amount_wrapper']['suggested_amounts'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select an amount'),
      '#options' => array_combine($suggested_amounts, array_map(function($amount) {
        return '$' . $amount;
      }, $suggested_amounts)),
      '#attributes' => ['class' => ['suggested-amounts']],
    ];
    
    // Other amount
    $form['amount_wrapper']['other_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Other amount'),
      '#min' => 1,
      '#step' => 1,
      '#placeholder' => $this->t('Enter amount'),
      '#prefix' => '<div class="other-amount-wrapper">',
      '#suffix' => '</div>',
    ];
    
    // Frequency
    $form['frequency'] = [
      '#type' => 'radios',
      '#title' => $this->t('Donation frequency'),
      '#options' => [
        'one-time' => $this->t('One-time'),
        'monthly' => $this->t('Monthly'),
        'quarterly' => $this->t('Quarterly'),
        'annually' => $this->t('Annually'),
      ],
      '#default_value' => 'one-time',
      '#attributes' => ['class' => ['donation-frequency']],
    ];
    
    // Donor information
    $form['donor_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Your Information'),
    ];
    
    $form['donor_info']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];
    
    $form['donor_info']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];
    
    $form['donor_info']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#description' => $this->t('We will send your tax receipt to this email.'),
    ];
    
    $form['donor_info']['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
    ];
    
    // Address
    $form['address'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Billing Address'),
      '#states' => [
        'visible' => [
          ':input[name="include_address"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    $form['include_address'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include billing address'),
    ];
    
    $form['address']['street_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street Address'),
    ];
    
    $form['address']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
    ];
    
    $form['address']['state'] = [
      '#type' => 'select',
      '#title' => $this->t('State'),
      '#options' => $this->getStateOptions(),
      '#empty_option' => $this->t('- Select -'),
    ];
    
    $form['address']['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ZIP Code'),
      '#size' => 10,
    ];
    
    // Additional options
    $form['additional'] = [
      '#type' => 'details',
      '#title' => $this->t('Additional Options'),
      '#open' => FALSE,
    ];
    
    // Campaign
    if ($campaign_id) {
      $form['campaign_id'] = [
        '#type' => 'hidden',
        '#value' => $campaign_id,
      ];
    }
    else {
      $campaigns = ilas_payment_get_campaigns();
      if (!empty($campaigns)) {
        $form['additional']['campaign_id'] = [
          '#type' => 'select',
          '#title' => $this->t('Designate my gift to'),
          '#options' => ['' => $this->t('General Fund')] + array_column($campaigns, 'title', 'id'),
        ];
      }
    }
    
    // Honor/Memorial
    $form['additional']['tribute_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Make this gift'),
      '#options' => [
        '' => $this->t('- None -'),
        'honor' => $this->t('In honor of'),
        'memory' => $this->t('In memory of'),
      ],
    ];
    
    $form['additional']['tribute_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#states' => [
        'visible' => [
          ':input[name="tribute_type"]' => ['!value' => ''],
        ],
        'required' => [
          ':input[name="tribute_type"]' => ['!value' => ''],
        ],
      ],
    ];
    
    // Comments
    $form['additional']['comments'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comments'),
      '#rows' => 3,
    ];
    
    // Payment method
    $form['payment_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment Method'),
      '#options' => [],
      '#required' => TRUE,
    ];
    
    // Add enabled payment methods
    if ($config->get('stripe_enabled')) {
      $form['payment_method']['#options']['stripe'] = $this->t('Credit Card');
      
      // Stripe card element container
      $form['stripe_card'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'card-element',
          'class' => ['stripe-card-element'],
        ],
        '#states' => [
          'visible' => [
            ':input[name="payment_method"]' => ['value' => 'stripe'],
          ],
        ],
      ];
      
      $form['stripe_payment_method_id'] = [
        '#type' => 'hidden',
        '#attributes' => ['id' => 'stripe-payment-method-id'],
      ];
    }
    
    if ($config->get('paypal_enabled')) {
      $form['payment_method']['#options']['paypal'] = $this->t('PayPal');
    }
    
    $form['payment_method']['#default_value'] = key($form['payment_method']['#options']);
    
    // Consent
    $form['consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to the <a href="@terms" target="_blank">terms and conditions</a>', [
        '@terms' => '/terms',
      ]),
      '#required' => TRUE,
    ];
    
    // Submit button
    $form['actions'] = [
      '#type' => 'actions',
    ];
    
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Donate Now'),
      '#attributes' => ['class' => ['btn-primary', 'donation-submit']],
    ];
    
    // Add Stripe configuration
    if ($config->get('stripe_enabled')) {
      $stripe_service = \Drupal::service('ilas_payment.stripe');
      $form['#attached']['drupalSettings']['ilasPayment']['stripe'] = [
        'publicKey' => $stripe_service->getPublicKey(),
      ];
    }
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate amount
    $suggested = $form_state->getValue('suggested_amounts');
    $other = $form_state->getValue('other_amount');
    
    if (empty($suggested) && empty($other)) {
      $form_state->setErrorByName('suggested_amounts', $this->t('Please select or enter a donation amount.'));
    }
    
    $amount = $suggested ?: $other;
    if ($amount <= 0) {
      $form_state->setErrorByName('other_amount', $this->t('Please enter a valid amount.'));
    }
    
    // Validate payment method specific fields
    $payment_method = $form_state->getValue('payment_method');
    
    if ($payment_method == 'stripe') {
      $payment_method_id = $form_state->getValue('stripe_payment_method_id');
      if (empty($payment_method_id)) {
        $form_state->setErrorByName('stripe_card', $this->t('Please enter valid card information.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Prepare payment data
    $suggested = $form_state->getValue('suggested_amounts');
    $other = $form_state->getValue('other_amount');
    $amount = $suggested ?: $other;
    
    $frequency = $form_state->getValue('frequency');
    $is_recurring = ($frequency != 'one-time');
    
    $payment_data = [
      'amount' => $amount,
      'payment_method' => $form_state->getValue('payment_method'),
      'email' => $form_state->getValue('email'),
      'first_name' => $form_state->getValue('first_name'),
      'last_name' => $form_state->getValue('last_name'),
      'phone' => $form_state->getValue('phone'),
      'is_recurring' => $is_recurring,
      'financial_type_id' => 'Donation',
    ];
    
    // Add address if provided
    if ($form_state->getValue('include_address')) {
      $payment_data['street_address'] = $form_state->getValue('street_address');
      $payment_data['city'] = $form_state->getValue('city');
      $payment_data['state'] = $form_state->getValue('state');
      $payment_data['postal_code'] = $form_state->getValue('postal_code');
    }
    
    // Add frequency details
    if ($is_recurring) {
      $frequency_map = [
        'monthly' => ['unit' => 'month', 'interval' => 1],
        'quarterly' => ['unit' => 'month', 'interval' => 3],
        'annually' => ['unit' => 'year', 'interval' => 1],
      ];
      
      $payment_data['frequency_unit'] = $frequency_map[$frequency]['unit'];
      $payment_data['frequency_interval'] = $frequency_map[$frequency]['interval'];
    }
    
    // Add campaign
    if ($campaign_id = $form_state->getValue('campaign_id')) {
      $payment_data['campaign_id'] = $campaign_id;
    }
    
    // Add tribute
    if ($tribute_type = $form_state->getValue('tribute_type')) {
      $payment_data['soft_credit_type'] = ($tribute_type == 'honor') ? 'In Honor of' : 'In Memory of';
      $payment_data['soft_credit_name'] = $form_state->getValue('tribute_name');
    }
    
    // Add note
    if ($comments = $form_state->getValue('comments')) {
      $payment_data['note'] = $comments;
    }
    
    // Add payment method specific data
    if ($payment_data['payment_method'] == 'stripe') {
      $payment_data['stripe_payment_method_id'] = $form_state->getValue('stripe_payment_method_id');
    }
    
    // Process payment
    $result = $this->paymentProcessor->processPayment($payment_data);
    
    if ($result['success']) {
      $this->messenger->addStatus($this->t('Thank you for your donation! Your contribution has been processed successfully.'));
      
      // Redirect to confirmation page
      $form_state->setRedirect('ilas_payment.donation_confirmation', [
        'contribution_id' => $result['contribution_id'],
      ]);
    }
    else {
      $this->messenger->addError($this->t('We were unable to process your donation: @error', [
        '@error' => $result['error'],
      ]));
    }
  }

  /**
   * Get state options.
   */
  protected function getStateOptions() {
    return [
      'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
      'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
      'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
      'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
      'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
      'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
      'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
      'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
      'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
      'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
      'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
      'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
      'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    ];
  }
}