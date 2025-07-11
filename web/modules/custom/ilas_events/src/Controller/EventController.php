<?php

namespace Drupal\ilas_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_events\Service\EventManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for event pages.
 */
class EventController extends ControllerBase {

  /**
   * The event manager.
   *
   * @var \Drupal\ilas_events\Service\EventManager
   */
  protected $eventManager;

  /**
   * Constructs an EventController.
   */
  public function __construct(EventManager $event_manager) {
    $this->eventManager = $event_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ilas_events.manager')
    );
  }

  /**
   * Event listing page.
   */
  public function listing() {
    $events = $this->eventManager->getUpcomingEvents(50);
    
    $build = [
      '#theme' => 'event_listing',
      '#events' => $events,
      '#attached' => [
        'library' => ['ilas_events/event_listing'],
      ],
      '#cache' => [
        'max-age' => 300, // Cache for 5 minutes
        'tags' => ['event_list'],
      ],
    ];
    
    return $build;
  }

  /**
   * Event calendar view.
   */
  public function calendar() {
    $month = \Drupal::request()->query->get('month', date('n'));
    $year = \Drupal::request()->query->get('year', date('Y'));
    
    // Get events for the month
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      $events = civicrm_api3('Event', 'get', [
        'is_active' => 1,
        'is_public' => 1,
        'start_date' => [
          '>=' => $start_date,
          '<=' => $end_date,
        ],
        'options' => ['limit' => 0],
        'return' => ['id', 'title', 'start_date', 'event_type_id'],
      ]);
      
      $build = [
        '#theme' => 'event_calendar',
        '#events' => $events['values'],
        '#month' => $month,
        '#year' => $year,
        '#attached' => [
          'library' => ['ilas_events/calendar'],
        ],
      ];
    }
    catch (\Exception $e) {
      $build = [
        '#markup' => $this->t('Unable to load calendar.'),
      ];
    }
    
    return $build;
  }

  /**
   * View single event.
   */
  public function view($event_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $event = civicrm_api3('Event', 'getsingle', [
        'id' => $event_id,
        'return' => [
          'id', 'title', 'summary', 'description', 'start_date', 'end_date',
          'event_type_id', 'is_online_registration', 'max_participants',
          'is_monetary', 'is_active', 'is_public',
        ],
      ]);
      
      if (!$event['is_public']) {
        throw new NotFoundHttpException();
      }
      
      // Get location
      if (!empty($event['loc_block_id'])) {
        $location = civicrm_api3('LocBlock', 'getsingle', [
          'id' => $event['loc_block_id'],
          'return' => ['address', 'phone', 'email'],
        ]);
        $event['location'] = $location;
      }
      
      // Get registration info
      $event['registered_count'] = $this->eventManager->getRegisteredCount($event_id);
      $event['available_spots'] = $this->eventManager->getAvailableSpots($event);
      $event['registration_open'] = $this->eventManager->isRegistrationOpen($event);
      
      // Check if current user is registered
      $event['user_registered'] = $this->isUserRegistered($event_id);
      
      $build = [
        '#type' => 'container',
        '#attributes' => ['class' => ['event-detail']],
      ];
      
      // Event header
      $build['header'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['event-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#value' => $event['title'],
        ],
        'type' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['event-type']],
          '#value' => $this->getEventTypeLabel($event['event_type_id']),
        ],
      ];
      
      // Event details
      $build['details'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['event-details']],
      ];
      
      // Date and time
      $build['details']['datetime'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['event-datetime']],
        '#value' => $this->formatEventDateTime($event),
      ];
      
      // Location
      if (!empty($event['location'])) {
        $build['details']['location'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['event-location']],
          '#value' => $this->formatLocation($event['location']),
        ];
      }
      
      // Description
      if (!empty($event['description'])) {
        $build['description'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['event-description']],
          'content' => [
            '#markup' => $event['description'],
          ],
        ];
      }
      
      // Registration info
      if ($event['is_online_registration']) {
        $build['registration'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['event-registration']],
        ];
        
        if ($event['user_registered']) {
          $build['registration']['status'] = [
            '#markup' => '<p class="registration-status">' . 
                        $this->t('You are registered for this event.') . '</p>',
          ];
        }
        elseif ($event['registration_open']) {
          // Show registration button
          $build['registration']['button'] = [
            '#type' => 'link',
            '#title' => $this->t('Register Now'),
            '#url' => \Drupal\Core\Url::fromRoute('ilas_events.register', ['event_id' => $event_id]),
            '#attributes' => ['class' => ['btn', 'btn-primary', 'btn-lg']],
          ];
          
          // Show spots remaining
          if ($event['available_spots'] !== 'unlimited') {
            $build['registration']['spots'] = [
              '#markup' => '<p class="spots-remaining">' . 
                          $this->t('@count spots available', ['@count' => $event['available_spots']]) . 
                          '</p>',
            ];
          }
        }
        else {
          $build['registration']['closed'] = [
            '#markup' => '<p class="registration-closed">' . 
                        $this->t('Registration is closed.') . '</p>',
          ];
        }
      }
      
      // Add to calendar links
      $build['calendar_links'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['calendar-links']],
        'ical' => [
          '#type' => 'link',
          '#title' => $this->t('Add to Calendar'),
          '#url' => \Drupal\Core\Url::fromRoute('ilas_events.ical_single', ['event_id' => $event_id]),
          '#attributes' => ['class' => ['ical-link']],
        ],
      ];
      
      // Social sharing
      $build['sharing'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['event-sharing']],
        '#markup' => $this->t('Share this event:'),
        // Add social sharing buttons
      ];
      
      $build['#attached']['library'][] = 'ilas_events/event_detail';
      
      return $build;
    }
    catch (\Exception $e) {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Registration confirmation page.
   */
  public function registrationConfirmation($event_id, $participant_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      
      // Load participant
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participant_id,
        'return' => ['contact_id', 'status_id', 'register_date'],
      ]);
      
      // Generate QR code for check-in
      $qr_code = $this->generateQrCode($participant_id);
      
      $build = [
        '#theme' => 'event_confirmation',
        '#event' => $event,
        '#participant' => $participant,
        '#qr_code' => $qr_code,
        '#attached' => [
          'library' => ['ilas_events/confirmation'],
        ],
      ];
      
      return $build;
    }
    catch (\Exception $e) {
      throw new NotFoundHttpException();
    }
  }

  /**
   * My registrations page.
   */
  public function myRegistrations() {
    $user = $this->currentUser();
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get contact ID for current user
      $uf_match = civicrm_api3('UFMatch', 'get', [
        'uf_id' => $user->id(),
      ]);
      
      if ($uf_match['count'] == 0) {
        return [
          '#markup' => $this->t('No event registrations found.'),
        ];
      }
      
      $contact_id = reset($uf_match['values'])['contact_id'];
      
      // Get participant records
      $participants = civicrm_api3('Participant', 'get', [
        'contact_id' => $contact_id,
        'options' => ['limit' => 0],
        'return' => ['event_id', 'status_id', 'register_date'],
      ]);
      
      // Load event details
      $registrations = [];
      foreach ($participants['values'] as $participant) {
        $event = civicrm_api3('Event', 'getsingle', [
          'id' => $participant['event_id'],
        ]);
        
        $registrations[] = [
          'participant' => $participant,
          'event' => $event,
        ];
      }
      
      $build = [
        '#theme' => 'my_registrations',
        '#registrations' => $registrations,
        '#attached' => [
          'library' => ['ilas_events/my_registrations'],
        ],
      ];
      
      return $build;
    }
    catch (\Exception $e) {
      return [
        '#markup' => $this->t('Unable to load registrations.'),
      ];
    }
  }

  /**
   * Get event title for route.
   */
  public function getTitle($event_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      return $event['title'];
    }
    catch (\Exception $e) {
      return $this->t('Event');
    }
  }

  /**
   * Check if current user is registered.
   */
  protected function isUserRegistered($event_id) {
    if ($this->currentUser()->isAnonymous()) {
      return FALSE;
    }
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get contact ID
      $uf_match = civicrm_api3('UFMatch', 'get', [
        'uf_id' => $this->currentUser()->id(),
      ]);
      
      if ($uf_match['count'] == 0) {
        return FALSE;
      }
      
      $contact_id = reset($uf_match['values'])['contact_id'];
      
      // Check registration
      $participant = civicrm_api3('Participant', 'get', [
        'event_id' => $event_id,
        'contact_id' => $contact_id,
        'status_id' => ['NOT IN' => ['Cancelled']],
      ]);
      
      return $participant['count'] > 0;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Get event type label.
   */
  protected function getEventTypeLabel($type_id) {
    $types = ilas_events_get_event_types();
    return $types[$type_id] ?? $type_id;
  }

  /**
   * Format event date/time.
   */
  protected function formatEventDateTime($event) {
    $start = strtotime($event['start_date']);
    $end = !empty($event['end_date']) ? strtotime($event['end_date']) : $start;
    
    if (date('Y-m-d', $start) == date('Y-m-d', $end)) {
      // Same day
      return date('F j, Y, g:i a', $start) . ' - ' . date('g:i a', $end);
    }
    else {
      // Different days
      return date('F j', $start) . ' - ' . date('j, Y', $end);
    }
  }

  /**
   * Format location.
   */
  protected function formatLocation($location) {
    $parts = [];
    
    if (!empty($location['address']['name'])) {
      $parts[] = '<strong>' . $location['address']['name'] . '</strong>';
    }
    
    if (!empty($location['address']['street_address'])) {
      $parts[] = $location['address']['street_address'];
    }
    
    $city_state_zip = [];
    if (!empty($location['address']['city'])) {
      $city_state_zip[] = $location['address']['city'];
    }
    if (!empty($location['address']['state_province'])) {
      $city_state_zip[] = $location['address']['state_province'];
    }
    if (!empty($location['address']['postal_code'])) {
      $city_state_zip[] = $location['address']['postal_code'];
    }
    
    if (!empty($city_state_zip)) {
      $parts[] = implode(', ', $city_state_zip);
    }
    
    return implode('<br>', $parts);
  }

  /**
   * Generate QR code for check-in.
   */
  protected function generateQrCode($participant_id) {
    // This would generate a QR code
    // For now, return placeholder
    return 'QR-' . $participant_id;
  }

  /**
   * Display attendee list.
   */
  public function attendeeList($event_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      
      // Get participants
      $participants = civicrm_api3('Participant', 'get', [
        'event_id' => $event_id,
        'options' => ['limit' => 0],
        'return' => ['contact_id', 'status_id', 'register_date', 'role_id'],
      ]);
      
      $attendees = [];
      foreach ($participants['values'] as $participant) {
        // Get contact details
        $contact = civicrm_api3('Contact', 'getsingle', [
          'id' => $participant['contact_id'],
          'return' => ['display_name', 'email', 'phone'],
        ]);
        
        $attendees[] = [
          'participant' => $participant,
          'contact' => $contact,
        ];
      }
      
      $build = [
        '#theme' => 'attendee_list',
        '#event' => $event,
        '#attendees' => $attendees,
        '#attached' => [
          'library' => ['ilas_events/attendee_list'],
        ],
      ];
      
      return $build;
    }
    catch (\Exception $e) {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Certificate page.
   */
  public function certificate($event_id, $participant_id) {
    try {
      $certificate_service = \Drupal::service('ilas_events.certificate');
      
      // Verify eligibility
      $eligibility = $certificate_service->verifyCertificateEligibility($event_id, $participant_id);
      
      if (!$eligibility['eligible']) {
        \Drupal::messenger()->addError($eligibility['reason']);
        return $this->redirect('ilas_events.view', ['event_id' => $event_id]);
      }
      
      // Generate certificate
      $pdf = $certificate_service->generateCertificate($event_id, $participant_id);
      
      // Return PDF response
      $response = new \Symfony\Component\HttpFoundation\Response($pdf);
      $response->headers->set('Content-Type', 'application/pdf');
      $response->headers->set('Content-Disposition', 'inline; filename="certificate.pdf"');
      
      return $response;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Unable to generate certificate.'));
      return $this->redirect('ilas_events.view', ['event_id' => $event_id]);
    }
  }

  /**
   * Participant autocomplete.
   */
  public function participantAutocomplete($event_id) {
    $matches = [];
    $input = \Drupal::request()->query->get('q');
    
    if ($input) {
      try {
        \Drupal::service('civicrm')->initialize();
        
        // Get participants for event
        $participants = civicrm_api3('Participant', 'get', [
          'event_id' => $event_id,
          'options' => ['limit' => 10],
        ]);
        
        foreach ($participants['values'] as $participant) {
          // Get contact
          $contact = civicrm_api3('Contact', 'getsingle', [
            'id' => $participant['contact_id'],
            'return' => ['display_name', 'email'],
          ]);
          
          if (stripos($contact['display_name'], $input) !== FALSE ||
              stripos($contact['email'] ?? '', $input) !== FALSE) {
            $label = $contact['display_name'];
            if (!empty($contact['email'])) {
              $label .= ' (' . $contact['email'] . ')';
            }
            $label .= ' (' . $participant['id'] . ')';
            
            $matches[] = [
              'value' => $label,
              'label' => $label,
            ];
          }
        }
      }
      catch (\Exception $e) {
        // Log error
      }
    }
    
    return new \Symfony\Component\HttpFoundation\JsonResponse($matches);
  }
}