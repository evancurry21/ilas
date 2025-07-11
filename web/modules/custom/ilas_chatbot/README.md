# ILAS Chatbot Module

Integrates Google Dialogflow chatbot for providing automated legal assistance to Idaho residents.

## Overview

The ILAS Chatbot module provides a conversational AI interface that helps users:
- Navigate legal resources
- Find appropriate forms
- Get answers to common legal questions
- Connect with legal aid services

## Features

- **Dialogflow Integration**: Seamless integration with Google Dialogflow
- **Secure Webhook**: CSRF-protected webhook endpoint for fulfillment
- **Form Integration**: Dynamic form loading within chat interface
- **Multi-language Support**: Configurable language settings
- **Analytics Tracking**: Optional analytics for user interactions
- **Configurable UI**: Customizable appearance and positioning

## Installation

1. Install the module:
   ```bash
   drush en ilas_chatbot -y
   ```

2. Configure Dialogflow credentials:
   - Navigate to `/admin/config/services/ilas-chatbot`
   - Enter your Dialogflow Agent ID
   - Configure webhook security settings

3. Place the chatbot block or enable global loading

## Configuration

### Basic Settings

- **Agent ID**: Your Dialogflow agent UUID
- **Language Code**: Primary language (default: 'en')
- **Welcome Intent**: Intent triggered on chat open

### Security Settings

The webhook endpoint requires authentication via one of:
1. **Bearer Token**: Secret token in Authorization header
2. **IP Whitelist**: Restrict to specific IP addresses

### Form Integration

Map form types to URLs in JSON format:
```json
{
  "eviction": "/form/eviction-assistance",
  "divorce": "/form/divorce-custody"
}
```

## Architecture

### Components

1. **ChatbotController**: Handles webhook requests and form configuration
2. **ChatbotBlock**: Provides block plugin for placement
3. **JavaScript Client**: Manages chat UI and form loading

### Security

- CSRF protection on all endpoints
- Input sanitization for webhook data
- Domain validation for form URLs
- XSS prevention in responses

### Webhook Flow

1. Dialogflow sends POST request to `/api/chatbot/webhook`
2. Module validates authentication (token or IP)
3. Intent is processed and response generated
4. Response includes fulfillment text and rich content

## JavaScript API

### Initialization

```javascript
Drupal.behaviors.ilasChatbot = {
  attach: function(context, settings) {
    // Chatbot initialized automatically
  }
};
```

### Events

The chatbot triggers custom events:
- `ilas-chatbot-ready`: Chat interface loaded
- `ilas-chatbot-form-load`: Form loading initiated
- `ilas-chatbot-error`: Error occurred

## Theming

### CSS Variables

```css
:root {
  --df-messenger-fab-color: #1263a0;
  --df-messenger-primary-color: #1263a0;
  --df-messenger-chat-width: 400px;
}
```

### Templates

- `templates/ilas-chatbot-embedded-form.html.twig`: Form wrapper template

## Testing

Run tests with PHPUnit:
```bash
./vendor/bin/phpunit web/modules/custom/ilas_chatbot/tests/
```

### Test Coverage

- Unit tests for controller logic
- Security validation tests
- Form configuration tests
- Webhook authentication tests

## Dialogflow Setup

### Required Intents

1. **Welcome Intent**: Initial greeting
2. **GetLegalHelp**: Main navigation
3. **StartForm**: Form initiation

### Entities

- `@formType`: Types of legal forms
- `@legalCategory`: Legal issue categories

### Fulfillment

Enable webhook for intents requiring dynamic responses:
- Webhook URL: `https://yoursite.com/api/chatbot/webhook`
- Add authentication header

## Troubleshooting

### Chat not appearing

1. Check Agent ID is correct
2. Verify Dialogflow API is enabled
3. Check browser console for errors

### Webhook failures

1. Verify authentication configured
2. Check webhook URL in Dialogflow
3. Review Drupal logs for errors

### Form loading issues

1. Confirm form URLs are correct
2. Check CORS settings if cross-origin
3. Verify form permissions

## API Reference

### Endpoints

#### GET `/api/chatbot/form/{form_type}`
Returns form configuration for specified type.

**Response:**
```json
{
  "form_type": "eviction",
  "title": "Eviction Assistance Form",
  "description": "Get help with eviction",
  "url": "/form/eviction"
}
```

#### POST `/api/chatbot/webhook`
Dialogflow fulfillment webhook.

**Headers:**
- `Authorization: Bearer <token>`
- `Content-Type: application/json`

## Extending

### Custom Intents

Add intent handlers in `ChatbotController::processIntent()`:

```php
case 'CustomIntent':
  $response['fulfillmentText'] = 'Custom response';
  break;
```

### Form Types

Register new form types in configuration:
1. Add to form_mappings
2. Add title and description
3. Update Dialogflow entities

## Performance

- Webhook responses cached for 1 hour
- Form configurations cached with config tags
- JavaScript loaded asynchronously

## Security Considerations

- Never expose sensitive data in responses
- Validate all user inputs
- Keep webhook token secret
- Regularly review IP whitelist
- Monitor webhook logs for anomalies

## License

Part of the ILAS project - see main project license.