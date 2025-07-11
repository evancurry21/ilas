<?php

namespace Drupal\ilas_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ilas_events\Service\EventManager;

/**
 * Controller for event administration.
 */
class EventAdminController extends ControllerBase {

  /**
   * The event manager.
   *
   * @var \Drupal\ilas_events\Service\EventManager
   */
  protected $eventManager;

  /**
   * Constructs an EventAdminController.
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
   * Event management overview.
   */
  public function overview() {
    $build = [];
    
    // Add create event link
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['action-links']],
      'create' => [
        '#type' => 'link',
        '#title' => $this->t('Create Event'),
        '#url' => \Drupal\Core\Url::fromRoute('ilas_events.admin.create'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get upcoming events
      $events = civicrm_api3('Event', 'get', [
        'is_active' => 1,
        'options' => [
          'limit' => 50,
          'sort' => 'start_date DESC',
        ],
        'return' => [
          'id', 'title', 'start_date', 'is_online_registration',
          'max_participants', 'is_monetary', 'is_public',
        ],
      ]);
      
      $rows = [];
      foreach ($events['values'] as $event) {
        // Get participant count
        $participants = civicrm_api3('Participant', 'getcount', [
          'event_id' => $event['id'],
          'status_id' => ['NOT IN' => ['Cancelled']],
        ]);
        
        $operations = [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => \Drupal\Core\Url::fromRoute('ilas_events.admin.edit', ['event_id' => $event['id']]),
            ],
            'attendees' => [
              'title' => $this->t('Attendees'),
              'url' => \Drupal\Core\Url::fromRoute('ilas_events.attendee_list', ['event_id' => $event['id']]),
            ],
            'check_in' => [
              'title' => $this->t('Check-in'),
              'url' => \Drupal\Core\Url::fromRoute('ilas_events.check_in', ['event_id' => $event['id']]),
            ],
          ],
        ];
        
        $rows[] = [
          $event['title'],
          date('M j, Y g:i a', strtotime($event['start_date'])),
          $participants . '/' . ($event['max_participants'] ?: '∞'),
          $event['is_public'] ? $this->t('Yes') : $this->t('No'),
          $event['is_online_registration'] ? $this->t('Open') : $this->t('Closed'),
          $operations,
        ];
      }
      
      $build['events'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Event'),
          $this->t('Date'),
          $this->t('Registrations'),
          $this->t('Public'),
          $this->t('Registration'),
          $this->t('Operations'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No events found.'),
      ];
    }
    catch (\Exception $e) {
      $build['error'] = [
        '#markup' => $this->t('Unable to load events.'),
      ];
    }
    
    return $build;
  }
}