<?php

namespace Drupal\Tests\ilas_donations\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ilas_donations\Service\DonationManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for DonationManager service.
 *
 * @group ilas_donations
 */
class DonationManagerTest extends UnitTestCase {

  /**
   * The donation manager.
   *
   * @var \Drupal\ilas_donations\Service\DonationManager
   */
  protected $donationManager;

  /**
   * The mock logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create mock logger
    $this->logger = $this->createMock(LoggerInterface::class);
    
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->any())
      ->method('get')
      ->willReturn($this->logger);

    // Create donation manager with mocked dependencies
    $this->donationManager = new DonationManager($loggerFactory);
  }

  /**
   * Test processing donation data.
   */
  public function testProcessDonation() {
    $donationData = [
      'amount' => 100.00,
      'first_name' => 'John',
      'last_name' => 'Doe',
      'email' => 'john.doe@example.com',
      'payment_method' => 'credit_card',
    ];

    // Test that logger is called for successful processing
    $this->logger->expects($this->once())
      ->method('info')
      ->with($this->stringContains('Processing donation'));

    $result = $this->donationManager->processDonation($donationData);
    
    $this->assertIsArray($result);
    $this->assertArrayHasKey('contribution_id', $result);
    $this->assertArrayHasKey('contact_id', $result);
  }

  /**
   * Test validation of donation amounts.
   */
  public function testValidateDonationAmount() {
    // Test valid amounts
    $this->assertTrue($this->donationManager->validateAmount(10.00));
    $this->assertTrue($this->donationManager->validateAmount(1000.00));
    $this->assertTrue($this->donationManager->validateAmount(50.50));

    // Test invalid amounts
    $this->assertFalse($this->donationManager->validateAmount(0));
    $this->assertFalse($this->donationManager->validateAmount(-10));
    $this->assertFalse($this->donationManager->validateAmount('invalid'));
  }

  /**
   * Test recurring donation setup.
   */
  public function testSetupRecurringDonation() {
    $recurringData = [
      'amount' => 50.00,
      'frequency' => 'monthly',
      'start_date' => date('Y-m-d'),
      'contact_id' => 123,
    ];

    $result = $this->donationManager->setupRecurring($recurringData);
    
    $this->assertIsArray($result);
    $this->assertArrayHasKey('recurring_id', $result);
    $this->assertEquals('monthly', $result['frequency']);
  }

  /**
   * Test donation receipt generation.
   */
  public function testGenerateReceipt() {
    $donation = [
      'id' => 456,
      'amount' => 100.00,
      'date' => '2024-01-15',
      'contact' => [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@example.com',
      ],
    ];

    $receipt = $this->donationManager->generateReceipt($donation);
    
    $this->assertIsArray($receipt);
    $this->assertArrayHasKey('receipt_number', $receipt);
    $this->assertArrayHasKey('tax_deductible', $receipt);
    $this->assertEquals(100.00, $receipt['amount']);
  }

  /**
   * Test campaign allocation.
   */
  public function testAllocateToCampaign() {
    $donationId = 789;
    $campaignId = 10;

    $result = $this->donationManager->allocateToCampaign($donationId, $campaignId);
    
    $this->assertTrue($result);
  }

  /**
   * Test donation statistics calculation.
   */
  public function testCalculateDonationStats() {
    $dateRange = [
      'start' => '2024-01-01',
      'end' => '2024-01-31',
    ];

    $stats = $this->donationManager->getStatistics($dateRange);
    
    $this->assertIsArray($stats);
    $this->assertArrayHasKey('total_amount', $stats);
    $this->assertArrayHasKey('total_count', $stats);
    $this->assertArrayHasKey('average_donation', $stats);
    $this->assertArrayHasKey('top_donors', $stats);
  }

  /**
   * Test soft credit handling.
   */
  public function testCreateSoftCredit() {
    $donationId = 321;
    $softCreditData = [
      'contact_id' => 654,
      'amount' => 50.00,
      'type' => 'in_honor_of',
    ];

    $result = $this->donationManager->createSoftCredit($donationId, $softCreditData);
    
    $this->assertTrue($result);
  }

  /**
   * Test payment processor integration.
   */
  public function testPaymentProcessorIntegration() {
    $paymentData = [
      'amount' => 75.00,
      'currency' => 'USD',
      'payment_processor' => 'stripe',
      'token' => 'tok_test123',
    ];

    $result = $this->donationManager->processPayment($paymentData);
    
    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
    $this->assertArrayHasKey('transaction_id', $result);
  }

  /**
   * Test donor communication preferences.
   */
  public function testUpdateDonorPreferences() {
    $contactId = 987;
    $preferences = [
      'email_receipts' => TRUE,
      'newsletter' => FALSE,
      'annual_report' => TRUE,
    ];

    $result = $this->donationManager->updateDonorPreferences($contactId, $preferences);
    
    $this->assertTrue($result);
  }

  /**
   * Test donation anonymization.
   */
  public function testAnonymizeDonation() {
    $donationId = 111;

    $result = $this->donationManager->anonymizeDonation($donationId);
    
    $this->assertTrue($result);
    
    // Verify anonymization
    $donation = $this->donationManager->getDonation($donationId);
    $this->assertEquals('Anonymous', $donation['display_name']);
  }
}