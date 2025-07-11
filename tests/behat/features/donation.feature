Feature: Donation functionality
  As a donor
  I want to make donations to Idaho Legal Aid Services
  So that I can support their mission

  Background:
    Given I am on the homepage
    
  Scenario: Make a one-time donation
    When I click "Donate"
    Then I should see "Support Idaho Legal Aid Services"
    When I fill in "Amount" with "100"
    And I fill in "First Name" with "John"
    And I fill in "Last Name" with "Doe"
    And I fill in "Email" with "john.doe@example.com"
    And I select "Credit Card" from "Payment Method"
    And I press "Continue to Payment"
    Then I should see "Payment Information"
    When I fill in "Card Number" with "4242424242424242"
    And I fill in "Expiration" with "12/25"
    And I fill in "CVV" with "123"
    And I press "Complete Donation"
    Then I should see "Thank you for your donation!"
    And I should see "Your donation of $100.00 has been processed"
    
  Scenario: Set up recurring donation
    When I click "Donate"
    And I click "Make it monthly"
    Then I should see "Monthly Donation"
    When I fill in "Monthly Amount" with "25"
    And I fill in "First Name" with "Jane"
    And I fill in "Last Name" with "Smith"
    And I fill in "Email" with "jane.smith@example.com"
    And I press "Continue"
    Then I should see "Your monthly donation of $25.00"
    
  Scenario: Donate in honor of someone
    When I click "Donate"
    And I check "This is a tribute gift"
    Then I should see "Tribute Information"
    When I select "In Honor Of" from "Tribute Type"
    And I fill in "Honoree Name" with "Mary Johnson"
    And I fill in "Amount" with "50"
    And I fill in donation details
    And I press "Continue to Payment"
    Then I should see "In Honor of Mary Johnson"
    
  Scenario: View donation history
    Given I am logged in as a donor
    When I visit "/my-account/donations"
    Then I should see "Donation History"
    And I should see "Download Receipt" links
    And I should see my total contributions
    
  Scenario: Donate to specific campaign
    Given there is an active campaign "Access to Justice"
    When I visit the campaign page
    And I click "Donate to this Campaign"
    Then the donation form should have "Access to Justice" pre-selected
    When I complete the donation form with "75"
    Then I should see "Your donation to Access to Justice"