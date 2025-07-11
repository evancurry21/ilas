<?php

namespace Drupal\Tests\ilas_civicrm\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;

/**
 * Tests CiviCRM synchronization.
 *
 * @group ilas_civicrm
 */
class CiviCrmSyncTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'civicrm',
    'ilas_civicrm',
  ];

  /**
   * The sync service.
   *
   * @var \Drupal\ilas_civicrm\Service\SyncService
   */
  protected $syncService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);
    
    // Initialize CiviCRM
    \Drupal::service('civicrm')->initialize();
    
    // Get sync service
    $this->syncService = \Drupal::service('ilas_civicrm.sync');
  }

  /**
   * Test user to contact synchronization.
   */
  public function testUserContactSync() {
    // Create Drupal user
    $user = User::create([
      'name' => 'testuser',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $user->save();
    
    // Sync to CiviCRM
    $contact_id = $this->syncService->syncUserToContact($user);
    
    $this->assertNotNull($contact_id);
    $this->assertIsNumeric($contact_id);
    
    // Verify contact was created
    $contact = civicrm_api3('Contact', 'getsingle', [
      'id' => $contact_id,
    ]);
    
    $this->assertEquals('test@example.com', $contact['email']);
    $this->assertEquals('testuser', $contact['display_name']);
  }

  /**
   * Test contact to user synchronization.
   */
  public function testContactUserSync() {
    // Create CiviCRM contact
    $contact = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'John',
      'last_name' => 'Doe',
      'email' => 'john.doe@example.com',
    ]);
    
    // Sync to Drupal
    $user = $this->syncService->syncContactToUser($contact['id']);
    
    $this->assertInstanceOf(User::class, $user);
    $this->assertEquals('john.doe@example.com', $user->getEmail());
    $this->assertEquals('john.doe', $user->getAccountName());
  }

  /**
   * Test duplicate prevention.
   */
  public function testDuplicatePrevention() {
    // Create user
    $user = User::create([
      'name' => 'duplicate',
      'mail' => 'duplicate@example.com',
      'status' => 1,
    ]);
    $user->save();
    
    // Sync twice
    $contact_id1 = $this->syncService->syncUserToContact($user);
    $contact_id2 = $this->syncService->syncUserToContact($user);
    
    // Should return same contact ID
    $this->assertEquals($contact_id1, $contact_id2);
    
    // Verify only one contact exists
    $count = civicrm_api3('Contact', 'getcount', [
      'email' => 'duplicate@example.com',
    ]);
    
    $this->assertEquals(1, $count);
  }

  /**
   * Test field mapping.
   */
  public function testFieldMapping() {
    // Create user with profile fields
    $user = User::create([
      'name' => 'fieldtest',
      'mail' => 'field@example.com',
      'field_first_name' => 'Field',
      'field_last_name' => 'Test',
      'field_phone' => '555-1234',
      'status' => 1,
    ]);
    $user->save();
    
    // Sync to CiviCRM
    $contact_id = $this->syncService->syncUserToContact($user);
    
    // Verify fields were mapped
    $contact = civicrm_api3('Contact', 'getsingle', [
      'id' => $contact_id,
      'return' => ['first_name', 'last_name', 'phone'],
    ]);
    
    $this->assertEquals('Field', $contact['first_name']);
    $this->assertEquals('Test', $contact['last_name']);
    $this->assertStringContainsString('555-1234', $contact['phone']);
  }

  /**
   * Test activity synchronization.
   */
  public function testActivitySync() {
    // Create contact
    $contact = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'email' => 'activity@example.com',
    ]);
    
    // Create activity
    $activity = civicrm_api3('Activity', 'create', [
      'activity_type_id' => 'Meeting',
      'subject' => 'Test Meeting',
      'activity_date_time' => date('YmdHis'),
      'source_contact_id' => $contact['id'],
    ]);
    
    // Sync activities
    $synced = $this->syncService->syncActivities($contact['id']);
    
    $this->assertTrue($synced);
  }

  /**
   * Test case synchronization.
   */
  public function testCaseSync() {
    // Create contact
    $contact = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'email' => 'case@example.com',
    ]);
    
    // Create case
    $case = civicrm_api3('Case', 'create', [
      'contact_id' => $contact['id'],
      'case_type_id' => 'housing',
      'subject' => 'Test Housing Case',
      'status_id' => 'Open',
    ]);
    
    // Sync case data
    $node = $this->syncService->syncCaseToNode($case['id']);
    
    $this->assertInstanceOf(Node::class, $node);
    $this->assertEquals('Test Housing Case', $node->getTitle());
  }

  /**
   * Test contribution synchronization.
   */
  public function testContributionSync() {
    // Create contact
    $contact = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'email' => 'donor@example.com',
    ]);
    
    // Create contribution
    $contribution = civicrm_api3('Contribution', 'create', [
      'contact_id' => $contact['id'],
      'total_amount' => 100,
      'financial_type_id' => 'Donation',
      'contribution_status_id' => 'Completed',
    ]);
    
    // Sync contributions
    $synced = $this->syncService->syncContributions($contact['id']);
    
    $this->assertTrue($synced);
  }

  /**
   * Test batch synchronization.
   */
  public function testBatchSync() {
    // Create multiple users
    $users = [];
    for ($i = 0; $i < 5; $i++) {
      $users[] = User::create([
        'name' => 'batchuser' . $i,
        'mail' => 'batch' . $i . '@example.com',
        'status' => 1,
      ]);
      $users[$i]->save();
    }
    
    // Batch sync
    $results = $this->syncService->batchSyncUsers($users);
    
    $this->assertCount(5, $results);
    
    foreach ($results as $result) {
      $this->assertTrue($result['success']);
      $this->assertNotNull($result['contact_id']);
    }
  }

  /**
   * Test sync error handling.
   */
  public function testSyncErrorHandling() {
    // Create user with invalid email
    $user = User::create([
      'name' => 'errortest',
      'mail' => 'invalid-email',
      'status' => 1,
    ]);
    $user->save();
    
    // Attempt sync
    $contact_id = $this->syncService->syncUserToContact($user);
    
    // Should handle error gracefully
    $this->assertFalse($contact_id);
    
    // Check error was logged
    $logs = $this->syncService->getErrorLog();
    $this->assertNotEmpty($logs);
  }

  /**
   * Test sync queue processing.
   */
  public function testSyncQueue() {
    // Add items to sync queue
    for ($i = 0; $i < 10; $i++) {
      $this->syncService->addToQueue('user', $i, 'sync');
    }
    
    // Process queue
    $processed = $this->syncService->processQueue(5);
    
    $this->assertEquals(5, $processed);
    
    // Check remaining items
    $remaining = $this->syncService->getQueueCount();
    $this->assertEquals(5, $remaining);
  }
}