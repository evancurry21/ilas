<?php

namespace Drupal\ilas_events\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Service for generating event certificates.
 */
class CertificateService {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a CertificateService.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    RendererInterface $renderer
  ) {
    $this->logger = $logger_factory->get('ilas_events');
    $this->renderer = $renderer;
  }

  /**
   * Generate certificate for participant.
   */
  public function generateCertificate($event_id, $participant_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      
      // Load participant
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participant_id,
        'return' => ['contact_id', 'status_id', 'register_date'],
      ]);
      
      // Verify participant attended
      if ($participant['status_id'] != 'Attended') {
        throw new \Exception('Certificate only available for attended participants.');
      }
      
      // Load contact
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $participant['contact_id'],
        'return' => ['display_name', 'first_name', 'last_name'],
      ]);
      
      // Get certificate data
      $certificate_data = $this->prepareCertificateData($event, $participant, $contact);
      
      // Generate PDF
      return $this->generatePdf($certificate_data);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to generate certificate: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }

  /**
   * Prepare certificate data.
   */
  protected function prepareCertificateData($event, $participant, $contact) {
    $data = [
      'event' => $event,
      'participant' => $participant,
      'contact' => $contact,
      'date_issued' => date('F j, Y'),
      'certificate_number' => $this->generateCertificateNumber($participant['id']),
    ];
    
    // Add event-specific data
    if ($event['event_type_id'] == 'cle_training') {
      // Get CLE credits
      $data['credits'] = $this->getCleCredits($event['id']);
      $data['accreditation_number'] = $this->getAccreditationNumber($event['id']);
      $data['type'] = 'CLE Certificate';
    }
    elseif ($event['event_type_id'] == 'volunteer_training') {
      $data['type'] = 'Volunteer Training Certificate';
      $data['hours'] = $this->calculateEventHours($event);
    }
    else {
      $data['type'] = 'Certificate of Attendance';
    }
    
    return $data;
  }

  /**
   * Generate PDF certificate.
   */
  protected function generatePdf($data) {
    // Render certificate template
    $build = [
      '#theme' => 'event_certificate',
      '#event' => $data['event'],
      '#participant' => $data['participant'],
      '#contact' => $data['contact'],
      '#credits' => $data['credits'] ?? NULL,
      '#date_issued' => $data['date_issued'],
      '#certificate_number' => $data['certificate_number'],
      '#type' => $data['type'],
    ];
    
    $html = $this->renderer->renderRoot($build);
    
    // Configure PDF options
    $options = new Options();
    $options->set('isHtml5ParserEnabled', TRUE);
    $options->set('isPhpEnabled', TRUE);
    $options->set('defaultFont', 'Arial');
    
    // Generate PDF
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'landscape');
    $dompdf->render();
    
    // Return PDF content
    return $dompdf->output();
  }

  /**
   * Generate certificate number.
   */
  protected function generateCertificateNumber($participant_id) {
    $year = date('Y');
    $padded_id = str_pad($participant_id, 6, '0', STR_PAD_LEFT);
    return "ILAS-{$year}-{$padded_id}";
  }

  /**
   * Get CLE credits for event.
   */
  protected function getCleCredits($event_id) {
    try {
      // Check custom field
      $event = civicrm_api3('Event', 'getsingle', [
        'id' => $event_id,
        'return' => ['custom_cle_credits'],
      ]);
      
      return $event['custom_cle_credits'] ?? 0;
    }
    catch (\Exception $e) {
      return 0;
    }
  }

  /**
   * Get accreditation number.
   */
  protected function getAccreditationNumber($event_id) {
    try {
      // Check custom field
      $event = civicrm_api3('Event', 'getsingle', [
        'id' => $event_id,
        'return' => ['custom_accreditation_number'],
      ]);
      
      return $event['custom_accreditation_number'] ?? '';
    }
    catch (\Exception $e) {
      return '';
    }
  }

  /**
   * Calculate event hours.
   */
  protected function calculateEventHours($event) {
    if (empty($event['start_date']) || empty($event['end_date'])) {
      return 0;
    }
    
    $start = strtotime($event['start_date']);
    $end = strtotime($event['end_date']);
    
    $hours = ($end - $start) / 3600;
    
    return round($hours, 1);
  }

  /**
   * Verify certificate eligibility.
   */
  public function verifyCertificateEligibility($event_id, $participant_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load participant
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participant_id,
        'event_id' => $event_id,
      ]);
      
      // Check status
      if ($participant['status_id'] != 'Attended') {
        return [
          'eligible' => FALSE,
          'reason' => 'You must have attended the event to receive a certificate.',
        ];
      }
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      
      // Check if event has ended
      if (strtotime($event['end_date']) > time()) {
        return [
          'eligible' => FALSE,
          'reason' => 'Certificates are available after the event has ended.',
        ];
      }
      
      return [
        'eligible' => TRUE,
      ];
    }
    catch (\Exception $e) {
      return [
        'eligible' => FALSE,
        'reason' => 'Unable to verify eligibility.',
      ];
    }
  }

  /**
   * Get certificate metadata.
   */
  public function getCertificateMetadata($certificate_number) {
    // Parse certificate number
    if (!preg_match('/^ILAS-(\d{4})-(\d{6})$/', $certificate_number, $matches)) {
      return FALSE;
    }
    
    $year = $matches[1];
    $participant_id = (int) $matches[2];
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Load participant
      $participant = civicrm_api3('Participant', 'getsingle', [
        'id' => $participant_id,
      ]);
      
      // Load event
      $event = civicrm_api3('Event', 'getsingle', [
        'id' => $participant['event_id'],
      ]);
      
      // Load contact
      $contact = civicrm_api3('Contact', 'getsingle', [
        'id' => $participant['contact_id'],
        'return' => ['display_name'],
      ]);
      
      return [
        'certificate_number' => $certificate_number,
        'participant_name' => $contact['display_name'],
        'event_title' => $event['title'],
        'event_date' => date('F j, Y', strtotime($event['start_date'])),
        'issued_year' => $year,
        'valid' => TRUE,
      ];
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Generate bulk certificates.
   */
  public function generateBulkCertificates($event_id) {
    try {
      \Drupal::service('civicrm')->initialize();
      
      // Get all attended participants
      $participants = civicrm_api3('Participant', 'get', [
        'event_id' => $event_id,
        'status_id' => 'Attended',
        'options' => ['limit' => 0],
      ]);
      
      $certificates = [];
      
      foreach ($participants['values'] as $participant) {
        try {
          $pdf = $this->generateCertificate($event_id, $participant['id']);
          $certificates[] = [
            'participant_id' => $participant['id'],
            'pdf' => $pdf,
          ];
        }
        catch (\Exception $e) {
          $this->logger->warning('Failed to generate certificate for participant @id: @error', [
            '@id' => $participant['id'],
            '@error' => $e->getMessage(),
          ]);
        }
      }
      
      return $certificates;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to generate bulk certificates: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }
}