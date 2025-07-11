<?php

namespace Drupal\ilas_events\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for event reports.
 */
class EventReportsController extends ControllerBase {

  /**
   * Reports overview page.
   */
  public function overview() {
    $build = [];
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Event statistics
      $stats = $this->getEventStatistics();
      
      $build['statistics'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Event Statistics'),
        '#items' => [
          $this->t('Total Events: @count', ['@count' => $stats['total_events']]),
          $this->t('Upcoming Events: @count', ['@count' => $stats['upcoming_events']]),
          $this->t('Total Registrations: @count', ['@count' => $stats['total_registrations']]),
          $this->t('Total Attendees: @count', ['@count' => $stats['total_attendees']]),
        ],
      ];
      
      // Popular events
      $popular_events = $this->getPopularEvents();
      
      $rows = [];
      foreach ($popular_events as $event) {
        $rows[] = [
          $event['title'],
          $event['registrations'],
          $event['attendees'],
          number_format($event['attendance_rate'], 1) . '%',
        ];
      }
      
      $build['popular_events'] = [
        '#type' => 'table',
        '#caption' => $this->t('Most Popular Events'),
        '#header' => [
          $this->t('Event'),
          $this->t('Registrations'),
          $this->t('Attendees'),
          $this->t('Attendance Rate'),
        ],
        '#rows' => $rows,
      ];
      
      // Revenue summary for paid events
      $revenue = $this->getRevenueStatistics();
      
      if ($revenue['total'] > 0) {
        $build['revenue'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('Revenue Summary'),
          '#items' => [
            $this->t('Total Revenue: $@amount', ['@amount' => number_format($revenue['total'], 2)]),
            $this->t('Average per Event: $@amount', ['@amount' => number_format($revenue['average'], 2)]),
            $this->t('Paid Events: @count', ['@count' => $revenue['paid_events']]),
          ],
        ];
      }
    }
    catch (\Exception $e) {
      $build['error'] = [
        '#markup' => $this->t('Unable to generate reports.'),
      ];
    }
    
    return $build;
  }

  /**
   * Get event statistics.
   */
  protected function getEventStatistics() {
    $stats = [
      'total_events' => 0,
      'upcoming_events' => 0,
      'total_registrations' => 0,
      'total_attendees' => 0,
    ];
    
    try {
      // Total events
      $stats['total_events'] = civicrm_api3('Event', 'getcount', [
        'is_active' => 1,
      ]);
      
      // Upcoming events
      $stats['upcoming_events'] = civicrm_api3('Event', 'getcount', [
        'is_active' => 1,
        'start_date' => ['>=' => date('Y-m-d')],
      ]);
      
      // Total registrations
      $stats['total_registrations'] = civicrm_api3('Participant', 'getcount', [
        'status_id' => ['NOT IN' => ['Cancelled']],
      ]);
      
      // Total attendees
      $stats['total_attendees'] = civicrm_api3('Participant', 'getcount', [
        'status_id' => 'Attended',
      ]);
    }
    catch (\Exception $e) {
      // Log error
    }
    
    return $stats;
  }

  /**
   * Get popular events.
   */
  protected function getPopularEvents($limit = 10) {
    $popular = [];
    
    try {
      // Get events with registrations
      $sql = "SELECT e.id, e.title, COUNT(p.id) as registrations,
              SUM(CASE WHEN p.status_id = 2 THEN 1 ELSE 0 END) as attendees
              FROM civicrm_event e
              LEFT JOIN civicrm_participant p ON e.id = p.event_id
              WHERE e.is_active = 1
              GROUP BY e.id
              HAVING registrations > 0
              ORDER BY registrations DESC
              LIMIT {$limit}";
      
      $dao = \CRM_Core_DAO::executeQuery($sql);
      
      while ($dao->fetch()) {
        $popular[] = [
          'id' => $dao->id,
          'title' => $dao->title,
          'registrations' => $dao->registrations,
          'attendees' => $dao->attendees,
          'attendance_rate' => $dao->registrations > 0 ? 
            ($dao->attendees / $dao->registrations * 100) : 0,
        ];
      }
    }
    catch (\Exception $e) {
      // Log error
    }
    
    return $popular;
  }

  /**
   * Get revenue statistics.
   */
  protected function getRevenueStatistics() {
    $revenue = [
      'total' => 0,
      'average' => 0,
      'paid_events' => 0,
    ];
    
    try {
      // This would need to query contribution data linked to events
      // For now, return placeholder data
      $revenue['total'] = 0;
      $revenue['paid_events'] = civicrm_api3('Event', 'getcount', [
        'is_monetary' => 1,
      ]);
    }
    catch (\Exception $e) {
      // Log error
    }
    
    return $revenue;
  }
}