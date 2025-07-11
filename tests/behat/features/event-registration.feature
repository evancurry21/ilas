Feature: Event registration
  As a community member
  I want to register for legal aid events
  So that I can receive legal assistance or training

  Background:
    Given I am on the events page
    
  Scenario: Browse upcoming events
    Then I should see "Upcoming Events"
    And I should see event cards with:
      | title | date | location | spots available |
    When I click on "Legal Clinic - Boise"
    Then I should see event details
    And I should see "Register Now" button
    
  Scenario: Register for a free legal clinic
    Given there is a legal clinic "Housing Rights Clinic" next week
    When I visit the event page
    And I click "Register Now"
    Then I should see "Register for Housing Rights Clinic"
    When I fill in:
      | First Name | Maria |
      | Last Name | Rodriguez |
      | Email | maria@example.com |
      | Phone | 208-555-1234 |
      | Legal Issue | Eviction |
      | Income Eligible | Yes |
    And I press "Complete Registration"
    Then I should see "Registration Confirmed!"
    And I should see a QR code for check-in
    And I should receive a confirmation email
    
  Scenario: Register for paid CLE training
    Given there is a CLE training "Family Law Update" for "$75"
    When I register for the event
    And I reach the payment step
    Then I should see "Registration Fee: $75.00"
    When I complete the payment
    Then I should see "Registration Confirmed!"
    And I should see "Certificate will be available after event"
    
  Scenario: Join waitlist for full event
    Given the event "Tax Assistance Clinic" is full
    When I try to register
    Then I should see "This event is full"
    And I should see "Join Waitlist" button
    When I click "Join Waitlist"
    And I provide my contact information
    Then I should see "You have been added to the waitlist"
    
  Scenario: Check in to event
    Given I am registered for "Legal Clinic Today"
    And I am at the event location
    When the staff scans my QR code
    Then I should be marked as attended
    And I should appear on the attendee list
    
  Scenario: Download CLE certificate
    Given I attended the CLE training "Ethics in Legal Aid"
    When I visit "/my-events"
    And I click "Download Certificate" for the event
    Then I should receive a PDF certificate
    And the certificate should show "2.0 CLE Credits"
    
  Scenario: Filter events by type
    When I select "Legal Clinics" from "Event Type"
    Then I should only see legal clinic events
    When I select "CLE Trainings" from "Event Type"  
    Then I should only see CLE training events
    
  Scenario: View event on calendar
    When I click "Calendar View"
    Then I should see a monthly calendar
    And events should be displayed on their dates
    When I click on an event in the calendar
    Then I should see the event details popup