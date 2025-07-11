<?php

namespace Drupal\ilas_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for event feeds.
 */
class EventFeedController extends ControllerBase {

  /**
   * Generate iCal feed for all events.
   */
  public function ical() {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get upcoming public events
      $events = civicrm_api3('Event', 'get', [
        'is_active' => 1,
        'is_public' => 1,
        'start_date' => ['>=' => date('Y-m-d')],
        'options' => ['limit' => 100],
      ]);
      
      $ical = $this->generateIcalContent($events['values']);
      
      $response = new Response($ical);
      $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
      $response->headers->set('Content-Disposition', 'attachment; filename="events.ics"');
      
      return $response;
    }
    catch (\Exception $e) {
      throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException();
    }
  }

  /**
   * Generate iCal for single event.
   */
  public function singleEventIcal($event_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      
      $ical = $this->generateIcalContent([$event]);
      
      $response = new Response($ical);
      $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
      $response->headers->set('Content-Disposition', 'attachment; filename="event.ics"');
      
      return $response;
    }
    catch (\Exception $e) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
  }

  /**
   * Generate iCal content.
   */
  protected function generateIcalContent($events) {
    $output = "BEGIN:VCALENDAR\r\n";
    $output .= "VERSION:2.0\r\n";
    $output .= "PRODID:-//Idaho Legal Aid Services//Events//EN\r\n";
    $output .= "CALSCALE:GREGORIAN\r\n";
    $output .= "METHOD:PUBLISH\r\n";
    
    foreach ($events as $event) {
      $output .= "BEGIN:VEVENT\r\n";
      $output .= "UID:" . $event['id'] . "@idaholegalaid.org\r\n";
      $output .= "DTSTART:" . $this->formatIcalDate($event['start_date']) . "\r\n";
      
      if (!empty($event['end_date'])) {
        $output .= "DTEND:" . $this->formatIcalDate($event['end_date']) . "\r\n";
      }
      else {
        // If no end date, assume 2 hours
        $end = strtotime($event['start_date']) + 7200;
        $output .= "DTEND:" . date('Ymd\\THis', $end) . "\r\n";
      }
      
      $output .= "SUMMARY:" . $this->escapeIcalText($event['title']) . "\r\n";
      
      if (!empty($event['summary'])) {
        $output .= "DESCRIPTION:" . $this->escapeIcalText($event['summary']) . "\r\n";
      }
      
      if (!empty($event['location'])) {
        $output .= "LOCATION:" . $this->escapeIcalText($event['location']) . "\r\n";
      }
      
      $output .= "URL:" . \Drupal::request()->getSchemeAndHttpHost() . "/event/" . $event['id'] . "\r\n";
      $output .= "STATUS:CONFIRMED\r\n";
      $output .= "END:VEVENT\r\n";
    }
    
    $output .= "END:VCALENDAR\r\n";
    
    return $output;
  }

  /**
   * Format date for iCal.
   */
  protected function formatIcalDate($date) {
    return date('Ymd\\THis', strtotime($date));
  }

  /**
   * Escape text for iCal.
   */
  protected function escapeIcalText($text) {
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace(",", "\\,", $text);
    $text = str_replace(";", "\\;", $text);
    $text = str_replace("\n", "\\n", $text);
    return $text;
  }
}