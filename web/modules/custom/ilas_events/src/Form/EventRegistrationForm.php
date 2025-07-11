<?php

namespace Drupal\ilas_events\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_events\Service\EventManager;
use Drupal\ilas_payment\Service\PaymentProcessor;

/**
 * Event registration form.
 */
class EventRegistrationForm extends FormBase {

  /**
   * The event manager.
   *
   * @var \Drupal\ilas_events\Service\EventManager
   */
  protected $eventManager;

  /**
   * The payment processor.
   *
   * @var \Drupal\ilas_payment\Service\PaymentProcessor
   */
  protected $paymentProcessor;

  /**
   * Constructs an EventRegistrationForm.
   */
  public function __construct(EventManager $event_manager, PaymentProcessor $payment_processor) {
    $this->eventManager = $event_manager;
    $this->paymentProcessor = $payment_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilas_events.manager'),
      $container->get('ilas_payment.processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilas_event_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $event_id = NULL) {
    if (!$event_id) {
      return ['#markup' => $this->t('Event not found.')];
    }
    
    // Load event from CiviCRM
    try {
      \Drupal::service('civicrm')->initialize();
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
    }
    catch (\Exception $e) {
      return ['#markup' => $this->t('Event not found.')];
    }
    
    // Check if registration is open
    if (!$this->eventManager->isRegistrationOpen($event)) {
      return ['#markup' => $this->t('Registration is closed for this event.')];
    }
    
    // Store event info
    $form_state->set('event', $event);
    
    $form['#attributes']['class'][] = 'event-registration-form';
    
    // Event info
    $form['event_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Event Information'),
      '#attributes' => ['class' => ['event-info']],
    ];
    
    $form['event_info']['title'] = [
      '#markup' => '<h2>' . $event['title'] . '</h2>',
    ];
    
    $form['event_info']['date'] = [
      '#markup' => '<p><strong>' . $this->t('Date:') . '</strong> ' . 
                   date('F j, Y g:i a', strtotime($event['start_date'])) . '</p>',
    ];
    
    if (!empty($event['summary'])) {
      $form['event_info']['summary'] = [
        '#markup' => '<p>' . $event['summary'] . '</p>',
      ];
    }
    
    // Show available spots
    $available = $this->eventManager->getAvailableSpots($event);
    if ($available !== 'unlimited' && $available < 10) {
      $form['event_info']['spots'] = [
        '#markup' => '<p class="spots-available"><strong>' . 
                     $this->t('Only @count spots remaining!', ['@count' => $available]) . 
                     '</strong></p>',
      ];
    }
    
    // Contact information
    $form['contact'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact Information'),
    ];
    
    $form['contact']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];
    
    $form['contact']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];
    
    $form['contact']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#description' => $this->t('We will send confirmation to this email.'),
    ];
    
    $form['contact']['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#required' => TRUE,
    ];
    
    // Organization (for professional events)
    if (in_array($event['event_type_id'], ['cle_training', 'pro_bono_recruitment'])) {
      $form['contact']['organization'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Organization/Firm'),
      ];
      
      $form['contact']['job_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Job Title'),
      ];
      
      if ($event['event_type_id'] == 'cle_training') {
        $form['contact']['bar_number'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Bar Number'),
          '#description' => $this->t('Required for CLE credit.'),
        ];
      }
    }
    
    // Additional fields
    $form['additional'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additional Information'),
    ];
    
    $form['additional']['dietary_restrictions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dietary Restrictions'),
      '#description' => $this->t('Please list any dietary restrictions or allergies.'),
    ];
    
    $form['additional']['special_needs'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Special Accommodations'),
      '#description' => $this->t('Please describe any accommodations you need.'),
      '#rows' => 3,
    ];
    
    // For legal clinics, collect case type interest
    if ($event['event_type_id'] == 'legal_clinic') {
      $form['additional']['case_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Legal issues you need help with'),
        '#options' => [
          'housing' => $this->t('Housing/Eviction'),
          'family' => $this->t('Family Law'),
          'consumer' => $this->t('Consumer/Debt'),
          'benefits' => $this->t('Public Benefits'),
          'employment' => $this->t('Employment'),
          'other' => $this->t('Other'),
        ],
      ];
      
      $form['additional']['case_description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Brief description of your legal issue'),
        '#rows' => 3,
        '#states' => [
          'visible' => [
            ':input[name="case_types[other]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    
    // Payment section for paid events
    if ($event['is_monetary']) {
      $form['payment'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Payment Information'),
      ];
      
      // Get pricing
      $fee = $this->getEventFee($event_id);
      
      $form['payment']['fee_info'] = [
        '#markup' => '<p><strong>' . $this->t('Registration Fee: $@amount', [
          '@amount' => number_format($fee, 2),
        ]) . '</strong></p>',
      ];
      
      $form['payment']['payment_method'] = [
        '#type' => 'radios',
        '#title' => $this->t('Payment Method'),
        '#options' => [
          'credit_card' => $this->t('Credit Card'),
          'pay_later' => $this->t('Pay at the door'),
        ],
        '#default_value' => 'credit_card',
        '#required' => TRUE,
      ];
      
      // Credit card fields (would integrate with Stripe)
      $form['payment']['card_container'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            ':input[name="payment_method"]' => ['value' => 'credit_card'],
          ],
        ],
      ];
      
      $form['payment']['card_container']['card_element'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['id' => 'card-element'],
      ];
      
      // Add Stripe library
      $form['#attached']['library'][] = 'ilas_payment/stripe';
    }
    
    // Terms and conditions
    $form['terms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand that by registering, I commit to attending this event.'),
      '#required' => TRUE,
    ];
    
    // Submit button
    $form['actions'] = [
      '#type' => 'actions',
    ];
    
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $event['is_monetary'] ? $this->t('Complete Registration') : $this->t('Register'),
      '#attributes' => ['class' => ['btn-primary']],
    ];
    
    // Add CSS/JS
    $form['#attached']['library'][] = 'ilas_events/registration_form';
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $event = $form_state->get('event');
    
    // Verify event still has spots
    $available = $this->eventManager->getAvailableSpots($event);
    if ($available !== 'unlimited' && $available <= 0) {
      $form_state->setError($form, $this->t('Sorry, this event is now full.'));
    }
    
    // Validate email uniqueness for this event
    try {
      \Drupal::service('civicrm')->initialize();
      
      $existing = civicrm_api3('Contact', 'get', [
        'email' => $form_state->getValue('email'),
      ]);
      
      if ($existing['count'] > 0) {
        $contact_id = reset($existing['values'])['id'];
        
        // Check if already registered
        $participant = civicrm_api3('Participant', 'get', [
          'event_id' => $event['id'],
          'contact_id' => $contact_id,
          'status_id' => ['NOT IN' => ['Cancelled']],
        ]);
        
        if ($participant['count'] > 0) {
          $form_state->setErrorByName('email', $this->t('This email is already registered for this event.'));
        }
      }
    }
    catch (\Exception $e) {
      // Log error
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $event = $form_state->get('event');
    $values = $form_state->getValues();
    
    try {
      // Create or find contact
      $contact_id = $this->findOrCreateContact($values);
      
      // Prepare registration data
      $registration_data = [
        'dietary_restrictions' => $values['dietary_restrictions'],
        'special_needs' => $values['special_needs'],
      ];
      
      // Add professional fields if applicable
      if (!empty($values['organization'])) {
        $registration_data['organization'] = $values['organization'];
        $registration_data['job_title'] = $values['job_title'];
      }
      if (!empty($values['bar_number'])) {
        $registration_data['bar_number'] = $values['bar_number'];
      }
      
      // Add legal clinic fields
      if (!empty($values['case_types'])) {
        $registration_data['case_types'] = array_filter($values['case_types']);
        if (!empty($values['case_description'])) {
          $registration_data['case_description'] = $values['case_description'];
        }
      }
      
      // Handle payment for monetary events
      if ($event['is_monetary']) {
        $registration_data['payment_method'] = $values['payment_method'];
        
        if ($values['payment_method'] == 'credit_card') {
          // Process payment through Stripe
          // This would integrate with payment processing from Phase 5
          $registration_data['payment_status'] = 'Completed';
        }
        else {
          $registration_data['payment_status'] = 'Pending';
        }
      }
      
      // Register participant
      $participant = $this->eventManager->registerParticipant(
        $event['id'],
        $contact_id,
        $registration_data
      );
      
      // Send confirmation
      $notification = \Drupal::service('ilas_events.notification');
      $notification->sendRegistrationConfirmation($participant['id']);
      
      // Success message
      $this->messenger()->addStatus($this->t('You have been successfully registered for @event!', [
        '@event' => $event['title'],
      ]));
      
      // Redirect to confirmation page
      $form_state->setRedirect('ilas_events.registration_confirmation', [
        'event_id' => $event['id'],
        'participant_id' => $participant['id'],
      ]);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Registration failed: @error', [
        '@error' => $e->getMessage(),
      ]));
      
      $this->logger('ilas_events')->error('Registration failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Find or create contact.
   */
  protected function findOrCreateContact($data) {
    \Drupal::service('civicrm')->initialize();
    
    // Check if contact exists
    $existing = civicrm_api3('Contact', 'get', [
      'email' => $data['email'],
      'contact_type' => 'Individual',
    ]);
    
    if ($existing['count'] > 0) {
      return reset($existing['values'])['id'];
    }
    
    // Create new contact
    $params = [
      'contact_type' => 'Individual',
      'first_name' => $data['first_name'],
      'last_name' => $data['last_name'],
      'email' => $data['email'],
    ];
    
    if (!empty($data['phone'])) {
      $params['api.Phone.create'] = [
        'phone' => $data['phone'],
        'location_type_id' => 'Primary',
        'phone_type_id' => 'Phone',
      ];
    }
    
    if (!empty($data['organization'])) {
      $params['organization_name'] = $data['organization'];
      $params['job_title'] = $data['job_title'] ?? '';
    }
    
    $contact = civicrm_api3('Contact', 'create', $params);
    
    return $contact['id'];
  }

  /**
   * Get event registration fee.
   */
  protected function getEventFee($event_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get price set for event
      $price_set = civicrm_api3('PriceSet', 'get', [
        'entity_table' => 'civicrm_event',
        'entity_id' => $event_id,
      ]);
      
      if ($price_set['count'] > 0) {
        // Get default price
        $price_set_id = reset($price_set['values'])['id'];
        
        $price_field = civicrm_api3('PriceField', 'get', [
          'price_set_id' => $price_set_id,
          'is_active' => 1,
        ]);
        
        if ($price_field['count'] > 0) {
          $field_id = reset($price_field['values'])['id'];
          
          $price_option = civicrm_api3('PriceFieldValue', 'get', [
            'price_field_id' => $field_id,
            'is_default' => 1,
          ]);
          
          if ($price_option['count'] > 0) {
            return reset($price_option['values'])['amount'];
          }
        }
      }
    }
    catch (\Exception $e) {
      // Log error
    }
    
    return 0;
  }
}