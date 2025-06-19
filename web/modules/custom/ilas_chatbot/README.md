# ILAS Chatbot Module - Professional Implementation Guide

## Overview
This module implements a Google Dialogflow-powered chatbot for legal assistance, replicating the Idaho Legal Aid chatbot functionality with professional-grade features.

## Prerequisites
- Drupal 10/11
- Google Cloud Account with billing enabled
- Webform module installed
- PHP 8.1+

## Installation Steps

### 1. Google Cloud Setup

1. **Create Google Cloud Project**
   ```bash
   gcloud projects create ilas-chatbot-[unique-id]
   gcloud config set project ilas-chatbot-[unique-id]
   ```

2. **Enable Required APIs**
   ```bash
   gcloud services enable dialogflow.googleapis.com
   gcloud services enable cloudbuild.googleapis.com
   ```

3. **Create Service Account**
   ```bash
   gcloud iam service-accounts create dialogflow-chatbot \
     --display-name="ILAS Chatbot Service Account"
   
   gcloud projects add-iam-policy-binding [PROJECT-ID] \
     --member="serviceAccount:dialogflow-chatbot@[PROJECT-ID].iam.gserviceaccount.com" \
     --role="roles/dialogflow.client"
   ```

### 2. Dialogflow Agent Configuration

1. **Create Agent**
   - Go to [Dialogflow Console](https://dialogflow.cloud.google.com/)
   - Create new agent
   - Select your project
   - Choose "en" as default language

2. **Import Intents**
   Create these essential intents:

   **Welcome Intent**
   - Training phrases: "Hi", "Hello", "Help"
   - Response: "Welcome to Legal Aid Services. How can I help you today?"
   - Add quick replies for main categories

   **Legal Categories Intent**
   - Training phrases: "I need legal help", "What services do you offer"
   - Response: Present category options
   - Parameters: `category` (entity type: @legal_category)

   **Form Trigger Intents**
   - Create intent for each form type
   - Training phrases: "I need help with eviction", "File for divorce"
   - Parameters: `formType` (maps to form IDs)

### 3. Module Installation

1. **Enable Module**
   ```bash
   drush en ilas_chatbot -y
   ```

2. **Configure Block**
   - Navigate to Structure > Block layout
   - Place "ILAS Chatbot" block
   - Configure with your Dialogflow agent ID

3. **Create Webforms**
   Create webforms for each legal service:
   - eviction_assistance
   - divorce_custody
   - benefits_appeal
   - small_claims

### 4. Advanced Configuration

#### Custom Styling
Edit `/css/ilas-chatbot.css` to match your brand:
```css
df-messenger {
  --df-messenger-button-titlebar-color: #your-brand-color;
  --df-messenger-user-message: #your-accent-color;
}
```

#### Form Mappings
Configure in block settings:
```json
{
  "eviction": "/form/embed/eviction_assistance",
  "divorce": "/form/embed/divorce_custody",
  "benefits": "/form/embed/benefits_appeal",
  "small_claims": "/form/embed/small_claims"
}
```

#### Webhook Setup (Optional)
For advanced fulfillment:
1. Set webhook URL in Dialogflow: `https://yoursite.com/api/chatbot/webhook`
2. Enable webhook for relevant intents

## Testing Strategy

### 1. Unit Tests
```php
// Test chatbot initialization
public function testChatbotInitialization() {
  $this->drupalPlaceBlock('chatbot_block', [
    'agent_id' => 'test-agent-id',
  ]);
  $this->drupalGet('');
  $this->assertSession()->responseContains('df-messenger');
}
```

### 2. Integration Tests
- Test form loading in iframe
- Test mobile responsiveness
- Test conversation flows

### 3. User Acceptance Testing
- Test with real users
- Gather feedback on conversation quality
- Monitor Dialogflow analytics

## Performance Optimization

1. **Lazy Loading**
   - Chatbot loads only when user interacts
   - Forms load on-demand

2. **Caching**
   - Cache form configurations
   - Use Drupal's dynamic page cache

3. **CDN Integration**
   - Serve static assets via CDN
   - Minimize JavaScript bundle

## Security Considerations

1. **CORS Configuration**
   ```php
   // In settings.php
   $settings['cors_enabled'] = TRUE;
   $settings['cors_allowed_origins'] = ['https://dialogflow.googleapis.com'];
   ```

2. **Content Security Policy**
   ```
   frame-src 'self' https://dialogflow.googleapis.com;
   script-src 'self' https://www.gstatic.com;
   ```

3. **Data Privacy**
   - No PII in Dialogflow logs
   - Implement data retention policies
   - Add privacy notice to chat

## Monitoring & Analytics

1. **Dialogflow Analytics**
   - Monitor intent usage
   - Track conversation success rates
   - Identify improvement areas

2. **Custom Analytics**
   ```javascript
   // Track form completions
   gtag('event', 'form_complete', {
     'form_type': formType,
     'source': 'chatbot'
   });
   ```

## Troubleshooting

**Chat not appearing:**
- Verify agent ID in block configuration
- Check browser console for errors
- Ensure Dialogflow API is enabled

**Forms not loading:**
- Verify webform IDs match configuration
- Check CORS settings
- Test form URLs directly

**Mobile issues:**
- Clear caches after CSS changes
- Test on actual devices
- Check viewport meta tag

## Support
For issues or questions:
- Check Dialogflow logs
- Review Drupal watchdog
- Contact: support@ilas.org