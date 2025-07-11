# Phase 6: Event Management Integration

## Overview
This phase implements comprehensive event management capabilities for Idaho Legal Aid Services, including:
- Legal clinics and workshops
- Training sessions for volunteers
- Fundraising events
- Community outreach programs
- Pro bono attorney recruitment events

## Components to Implement

### 1. Event Types Configuration
- Legal Clinics
- CLE Training Sessions
- Volunteer Orientations
- Fundraising Galas
- Community Workshops
- Pro Bono Recruitment
- Board Meetings

### 2. Event Management Module
Custom module: `ilas_events` for Drupal-CiviCRM event integration

### 3. Features
- Online event registration
- Capacity management
- Waitlist functionality
- Multi-session events
- Event calendars
- Automated reminders
- Check-in system
- Certificate generation
- Virtual event support

### 4. Registration Features
- Public registration forms
- Member-only events
- Early bird pricing
- Group registrations
- Discount codes
- Special accommodations
- Dietary restrictions
- CLE credit tracking

### 5. Integration Points
- Payment processing (from Phase 5)
- Email notifications
- Calendar feeds (iCal)
- Zoom integration for virtual events
- Social media promotion
- Website event listings

## Technical Architecture

### Database Schema
CiviCRM Event tables:
- civicrm_event
- civicrm_participant
- civicrm_participant_status_type
- civicrm_event_carts
- civicrm_loc_block (event locations)

### Custom Fields
- CLE Credits offered
- Materials provided
- Accessibility information
- Virtual meeting details
- Parking information

### Drupal Integration
- Event nodes synced with CiviCRM
- Views for event listings
- Blocks for upcoming events
- Event registration forms

## Implementation Plan

1. Configure CiviCRM Event component
2. Create ilas_events module
3. Build event templates
4. Implement registration workflows
5. Create event displays
6. Set up automated communications
7. Implement reporting
8. Test event scenarios

## User Stories

### Legal Services Coordinator
- Create and manage legal clinics
- Track attendance and outcomes
- Generate attendance reports
- Send reminder emails

### Volunteer Coordinator
- Schedule training sessions
- Manage volunteer registrations
- Track training completion
- Issue certificates

### Development Director
- Create fundraising events
- Manage ticket sales
- Track sponsorships
- Generate donor lists

### Public User
- Browse upcoming events
- Register online
- Receive confirmations
- Add to calendar

## Success Metrics
- Events created and managed in system
- Online registration functioning
- Payment integration working
- Automated emails sending
- Reports generating correctly
- Calendar integration active