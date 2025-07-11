# ILAS CiviCRM Integration Module

This module provides custom integration between Idaho Legal Aid Services Drupal site and CiviCRM, specifically designed for Drupal 11 compatibility.

## Features

### 1. Webform Integration
- Automatic processing of webform submissions to CiviCRM
- Creates/updates contacts based on form data
- Creates activities and cases as appropriate
- Supports multiple webform types:
  - Contact forms → CiviCRM contacts + activities
  - Legal help requests → Contacts + cases + activities
  - Volunteer applications → Contacts + volunteer tags
  - Employment applications → Contacts + job applicant tags
  - Donation forms → Contacts + contributions

### 2. Chatbot Integration
- Enhanced webhook endpoint for Dialogflow integration
- Processes chatbot intents and creates CiviCRM records
- Supports:
  - Consultation scheduling
  - Resource finding
  - Eligibility checking
  - Anonymous contact tracking
- Context API for retrieving contact history
- Configurable API key authentication

### 3. Dashboard & Reporting
- Admin dashboard at `/admin/ilas/civicrm-dashboard`
- Real-time statistics:
  - Contact metrics (total, new this month)
  - Case statistics (open, urgent, by type)
  - Activity tracking
  - Contribution summaries
- Service area breakdown
- Office performance metrics

### 4. Data Synchronization
- Works with `ilas_civicrm_sync` module for:
  - User → Contact sync
  - Taxonomy → Tag mapping
  - Office nodes → Organization sync

## Configuration

Access settings at `/admin/config/ilas/civicrm`

### General Settings
- Enable/disable webform integration
- Enable/disable chatbot integration
- Automatic case creation for urgent requests

### Webform Settings
- Field mappings (JSON format)
- Priority form designation

### Chatbot Settings
- API key for webhook authentication
- Anonymous contact creation
- Urgent intent configuration

### Activity Types
- Custom activity types creation
- Default types included:
  - Legal Help Request
  - Eligibility Check
  - Resource Request
  - Volunteer Application
  - Chatbot Interaction

### Tags
- Service area tags
- Auto-tagging rules

## API Endpoints

### Chatbot Webhook (Enhanced)
```
POST /api/chatbot/webhook/enhanced
Headers: X-API-Key: [configured_key]
```

Processes Dialogflow webhook with CiviCRM integration.

### Chatbot Context
```
GET /api/chatbot/context?email=user@example.com
```

Retrieves CiviCRM contact context for chatbot conversations.

## Usage Examples

### Processing a Webform
```php
$processor = \Drupal::service('ilas_civicrm.webform_processor');
$result = $processor->processSubmission($submission);
```

### Processing Chatbot Intent
```php
$chatbot = \Drupal::service('ilas_civicrm.chatbot');
$result = $chatbot->processIntent([
  'intent' => 'schedule_consultation',
  'parameters' => [...],
  'contact_info' => [...],
]);
```

### Getting Dashboard Statistics
```php
$dashboard = \Drupal::service('ilas_civicrm.dashboard');
$stats = $dashboard->getStatistics();
```

## Development

### Services
- `ilas_civicrm.chatbot` - Chatbot CiviCRM integration
- `ilas_civicrm.webform_processor` - Webform processing
- `ilas_civicrm.dashboard` - Dashboard statistics

### Hooks Implemented
- `hook_webform_submission_insert()` - Process new submissions
- `hook_civicrm_post()` - Sync CiviCRM changes back to Drupal

## Requirements
- Drupal 11
- CiviCRM 5.75+
- Webform module
- ilas_civicrm_sync module
- ilas_chatbot module (for chatbot features)

## Security Considerations
- API endpoints use configurable authentication
- Webhook endpoint validates API key in X-API-Key header
- All user input is sanitized before CiviCRM operations
- Access permissions follow Drupal standards

## Troubleshooting

### Module won't install
- Ensure CiviCRM is properly installed and initialized
- Check that all dependencies are met
- Clear Drupal cache after installation

### Webforms not processing
- Check module settings to ensure integration is enabled
- Verify webform machine names match configuration
- Check Drupal logs for error messages

### Chatbot webhook errors
- Verify API key is configured and matches
- Check Dialogflow webhook URL configuration
- Review logs at `/admin/reports/dblog`

### Dashboard not loading
- Ensure user has 'administer site configuration' permission
- Check that CiviCRM is accessible
- Verify database connection to CiviCRM database