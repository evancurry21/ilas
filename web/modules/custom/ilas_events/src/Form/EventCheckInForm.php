<?php

namespace Drupal\ilas_events\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_events\Service\EventManager;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Event check-in form.
 */
class EventCheckInForm extends FormBase {

  /**
   * The event manager.
   *
   * @var \Drupal\ilas_events\Service\EventManager
   */
  protected $eventManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs an EventCheckInForm.
   */
  public function __construct(EventManager $event_manager, MessengerInterface $messenger) {
    $this->eventManager = $event_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilas_events.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilas_events_check_in_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $event_id = NULL) {
    // Store event ID
    $form['event_id'] = [
      '#type' => 'hidden',
      '#value' => $event_id,
    ];
    
    // Load event details
    try {
      \Drupal::service('civicrm')->initialize();
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      
      $form['event_info'] = [
        '#markup' => '<h2>' . $event['title'] . '</h2>' .
                    '<p>' . $this->t('Event Date: @date', [
                      '@date' => date('F j, Y g:i a', strtotime($event['start_date'])),
                    ]) . '</p>',
      ];
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Unable to load event details.'));
      return $form;
    }
    
    // Check-in method
    $form['check_in_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Check-in method'),
      '#options' => [
        'qr' => $this->t('QR Code'),
        'search' => $this->t('Name Search'),
        'manual' => $this->t('Manual Entry'),
      ],
      '#default_value' => 'search',
      '#required' => TRUE,
    ];
    
    // QR code scanner
    $form['qr_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scan QR Code'),
      '#description' => $this->t('Use a QR scanner to read the participant code.'),
      '#states' => [
        'visible' => [
          ':input[name="check_in_method"]' => ['value' => 'qr'],
        ],
      ],
    ];
    
    // Name search
    $form['name_search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search by name'),
      '#description' => $this->t('Enter participant name to search.'),
      '#autocomplete_route_name' => 'ilas_events.participant_autocomplete',
      '#autocomplete_route_parameters' => ['event_id' => $event_id],
      '#states' => [
        'visible' => [
          ':input[name="check_in_method"]' => ['value' => 'search'],
        ],
      ],
    ];
    
    // Manual entry
    $form['manual_entry'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Manual Registration'),
      '#states' => [
        'visible' => [
          ':input[name="check_in_method"]' => ['value' => 'manual'],
        ],
      ],
    ];
    
    $form['manual_entry']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
    ];
    
    $form['manual_entry']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
    ];
    
    $form['manual_entry']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
    ];
    
    // Actions
    $form['actions'] = [
      '#type' => 'actions',
    ];
    
    $form['actions']['check_in'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check In'),
      '#button_type' => 'primary',
    ];
    
    // Show checked-in participants
    $form['participants'] = [
      '#type' => 'details',
      '#title' => $this->t('Checked-in Participants'),
      '#open' => TRUE,
    ];
    
    // Get checked-in participants
    try {
      $participants = civicrm_api3('Participant', 'get', [
        'event_id' => $event_id,
        'status_id' => 'Attended',
        'options' => ['limit' => 0],
        'return' => ['contact_id', 'register_date'],
      ]);
      
      if ($participants['count'] > 0) {
        $rows = [];
        foreach ($participants['values'] as $participant) {
          // Get contact details
          $contact = civicrm_api3('Contact', 'getsingle', [
            'id' => $participant['contact_id'],
            'return' => ['display_name', 'email'],
          ]);
          
          $rows[] = [
            $contact['display_name'],
            $contact['email'] ?? '',
            date('g:i a', strtotime($participant['register_date'])),
          ];
        }
        
        $form['participants']['table'] = [
          '#type' => 'table',
          '#header' => [
            $this->t('Name'),
            $this->t('Email'),
            $this->t('Check-in Time'),
          ],
          '#rows' => $rows,
          '#empty' => $this->t('No participants checked in yet.'),
        ];
        
        $form['participants']['count'] = [
          '#markup' => '<p><strong>' . $this->t('Total checked in: @count', [
            '@count' => $participants['count'],
          ]) . '</strong></p>',
        ];
      }
      else {
        $form['participants']['empty'] = [
          '#markup' => '<p>' . $this->t('No participants checked in yet.') . '</p>',
        ];
      }
    }
    catch (\Exception $e) {
      $form['participants']['error'] = [
        '#markup' => '<p>' . $this->t('Unable to load participant list.') . '</p>',
      ];
    }
    
    // Add AJAX wrapper
    $form['#prefix'] = '<div id="check-in-form-wrapper">';
    $form['#suffix'] = '</div>';
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $event_id = $form_state->getValue('event_id');
    $method = $form_state->getValue('check_in_method');
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      $participant_id = NULL;
      
      switch ($method) {
        case 'qr':
          // Parse QR code
          $qr_code = $form_state->getValue('qr_code');
          if (preg_match('/QR-(\d+)/', $qr_code, $matches)) {
            $participant_id = $matches[1];
          }
          break;
          
        case 'search':
          // Parse autocomplete result
          $name_search = $form_state->getValue('name_search');
          if (preg_match('/\((\d+)\)$/', $name_search, $matches)) {
            $participant_id = $matches[1];
          }
          break;
          
        case 'manual':
          // Create or find contact
          $contact_params = [
            'first_name' => $form_state->getValue('first_name'),
            'last_name' => $form_state->getValue('last_name'),
            'email' => $form_state->getValue('email'),
          ];
          
          // Find existing contact
          $contacts = civicrm_api3('Contact', 'get', [
            'email' => $contact_params['email'],
          ]);
          
          if ($contacts['count'] > 0) {
            $contact_id = reset($contacts['values'])['id'];
          }
          else {
            // Create new contact
            $contact = civicrm_api3('Contact', 'create', array_merge(
              $contact_params,
              ['contact_type' => 'Individual']
            ));
            $contact_id = $contact['id'];
          }
          
          // Check if already registered
          $existing = civicrm_api3('Participant', 'get', [
            'event_id' => $event_id,
            'contact_id' => $contact_id,
          ]);
          
          if ($existing['count'] > 0) {
            $participant_id = reset($existing['values'])['id'];
          }
          else {
            // Register as walk-in
            $participant = civicrm_api3('Participant', 'create', [
              'event_id' => $event_id,
              'contact_id' => $contact_id,
              'status_id' => 'Attended',
              'role_id' => 'Attendee',
              'register_date' => date('YmdHis'),
            ]);
            $participant_id = $participant['id'];
          }
          break;
      }
      
      if ($participant_id) {
        // Update status to attended
        civicrm_api3('Participant', 'create', [
          'id' => $participant_id,
          'status_id' => 'Attended',
        ]);
        
        // Get participant details
        $participant = civicrm_api3('Participant', 'getsingle', [
          'id' => $participant_id,
          'return' => ['contact_id'],
        ]);
        
        $contact = civicrm_api3('Contact', 'getsingle', [
          'id' => $participant['contact_id'],
          'return' => ['display_name'],
        ]);
        
        $this->messenger->addStatus($this->t('@name successfully checked in!', [
          '@name' => $contact['display_name'],
        ]));
      }
      else {
        $this->messenger->addError($this->t('Unable to find participant.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Check-in failed: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
    
    // Rebuild form to show updated list
    $form_state->setRebuild();
  }
}