<?php

namespace Drupal\Tests\ilas_events\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Tests event registration functionality.
 *
 * @group ilas_events
 */
class EventRegistrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'datetime',
    'datetime_range',
    'civicrm',
    'ilas_events',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test event node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $event;

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test user
    $this->user = $this->drupalCreateUser([
      'access content',
      'register for events',
    ]);

    // Create test event
    $this->event = Node::create([
      'type' => 'event',
      'title' => 'Test Legal Clinic',
      'field_event_date' => [
        'value' => date('Y-m-d 10:00:00', strtotime('+1 week')),
        'end_value' => date('Y-m-d 12:00:00', strtotime('+1 week')),
      ],
      'field_event_type' => 'legal_clinic',
      'field_max_participants' => 20,
      'field_enable_registration' => TRUE,
      'status' => 1,
    ]);
    $this->event->save();
  }

  /**
   * Test event listing page.
   */
  public function testEventListing() {
    $this->drupalGet('/events');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Upcoming Events');
    $this->assertSession()->pageTextContains('Test Legal Clinic');
  }

  /**
   * Test event detail page.
   */
  public function testEventDetailPage() {
    $this->drupalGet('/event/' . $this->event->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test Legal Clinic');
    $this->assertSession()->pageTextContains('Register Now');
    $this->assertSession()->pageTextContains('20 spots available');
  }

  /**
   * Test event registration form.
   */
  public function testEventRegistration() {
    $this->drupalLogin($this->user);
    $this->drupalGet('/event/' . $this->event->id() . '/register');
    $this->assertSession()->statusCodeEquals(200);
    
    // Fill registration form
    $edit = [
      'first_name' => 'Test',
      'last_name' => 'User',
      'email' => 'test@example.com',
      'phone' => '555-1234',
      'legal_issue' => 'Housing',
      'income_eligible' => 1,
    ];
    
    $this->submitForm($edit, 'Register');
    
    // Check confirmation page
    $this->assertSession()->pageTextContains('Registration Confirmed!');
    $this->assertSession()->pageTextContains('Test Legal Clinic');
    $this->assertSession()->pageTextContains('Your Check-in Code');
  }

  /**
   * Test registration when event is full.
   */
  public function testEventFullRegistration() {
    // Set max participants to 0
    $this->event->set('field_max_participants', 0);
    $this->event->save();
    
    $this->drupalLogin($this->user);
    $this->drupalGet('/event/' . $this->event->id());
    
    $this->assertSession()->pageTextContains('Event Full');
    $this->assertSession()->pageTextNotContains('Register Now');
  }

  /**
   * Test waitlist functionality.
   */
  public function testWaitlistRegistration() {
    // Create registrations to fill event
    for ($i = 0; $i < 20; $i++) {
      $this->createRegistration($this->event->id(), 'user' . $i . '@example.com');
    }
    
    $this->drupalLogin($this->user);
    $this->drupalGet('/event/' . $this->event->id() . '/register');
    
    $this->assertSession()->pageTextContains('Event is full - registering for waitlist');
    
    $edit = [
      'first_name' => 'Waitlist',
      'last_name' => 'User',
      'email' => 'waitlist@example.com',
      'phone' => '555-9999',
    ];
    
    $this->submitForm($edit, 'Join Waitlist');
    
    $this->assertSession()->pageTextContains('You are currently on the waitlist');
  }

  /**
   * Test my registrations page.
   */
  public function testMyRegistrations() {
    $this->drupalLogin($this->user);
    
    // Create a registration for the user
    $this->createRegistration($this->event->id(), $this->user->getEmail());
    
    $this->drupalGet('/my-events');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Event Registrations');
    $this->assertSession()->pageTextContains('Test Legal Clinic');
  }

  /**
   * Test event check-in.
   */
  public function testEventCheckIn() {
    // Create admin user
    $admin = $this->drupalCreateUser([
      'manage event registrations',
    ]);
    
    $this->drupalLogin($admin);
    
    // Create a registration
    $participant_id = $this->createRegistration($this->event->id(), 'checkin@example.com');
    
    $this->drupalGet('/event/' . $this->event->id() . '/check-in');
    $this->assertSession()->statusCodeEquals(200);
    
    // Test QR code check-in
    $edit = [
      'check_in_method' => 'qr',
      'qr_code' => 'QR-' . $participant_id,
    ];
    
    $this->submitForm($edit, 'Check In');
    
    $this->assertSession()->pageTextContains('successfully checked in!');
  }

  /**
   * Test event cancellation.
   */
  public function testEventCancellation() {
    $this->drupalLogin($this->user);
    
    // Create a registration
    $participant_id = $this->createRegistration($this->event->id(), $this->user->getEmail());
    
    // Cancel registration
    $this->drupalGet('/event/' . $this->event->id() . '/cancel/' . $participant_id);
    $this->submitForm([], 'Cancel Registration');
    
    $this->assertSession()->pageTextContains('Your registration has been cancelled');
  }

  /**
   * Test certificate generation for CLE events.
   */
  public function testCertificateGeneration() {
    // Update event to CLE type
    $this->event->set('field_event_type', 'cle_training');
    $this->event->save();
    
    $this->drupalLogin($this->user);
    
    // Create a registration
    $participant_id = $this->createRegistration($this->event->id(), $this->user->getEmail());
    
    // Mark as attended
    $this->markAsAttended($participant_id);
    
    // Access certificate
    $this->drupalGet('/event/' . $this->event->id() . '/certificate/' . $participant_id);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Type', 'application/pdf');
  }

  /**
   * Test event reminder emails.
   */
  public function testEventReminders() {
    // Create registration
    $participant_id = $this->createRegistration($this->event->id(), 'reminder@example.com');
    
    // Run cron to trigger reminders
    $this->cronRun();
    
    // Check that reminder was sent
    $emails = $this->getMails();
    $reminder_sent = FALSE;
    
    foreach ($emails as $email) {
      if (strpos($email['subject'], 'Reminder:') !== FALSE) {
        $reminder_sent = TRUE;
        $this->assertStringContainsString('Test Legal Clinic', $email['body']);
        break;
      }
    }
    
    $this->assertTrue($reminder_sent, 'Reminder email was sent');
  }

  /**
   * Test accessibility of event pages.
   */
  public function testEventAccessibility() {
    $this->drupalGet('/events');
    
    // Check for basic accessibility elements
    $this->assertSession()->elementExists('css', 'h1');
    $this->assertSession()->elementAttributeContains('css', 'html', 'lang', 'en');
    
    // Check form labels
    $this->drupalGet('/event/' . $this->event->id() . '/register');
    $this->assertSession()->elementExists('css', 'label[for="edit-first-name"]');
    $this->assertSession()->elementExists('css', 'label[for="edit-email"]');
  }

  /**
   * Helper: Create a registration.
   */
  protected function createRegistration($event_id, $email) {
    // This would create a CiviCRM participant record
    // For testing, return a mock ID
    return rand(1000, 9999);
  }

  /**
   * Helper: Mark participant as attended.
   */
  protected function markAsAttended($participant_id) {
    // This would update the participant status in CiviCRM
  }
}