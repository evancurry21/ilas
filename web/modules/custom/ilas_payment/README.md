# ILAS Payment Processing Module

This module provides secure payment processing and donation management for Idaho Legal Aid Services, integrated with CiviCRM.

## Features

### 1. Payment Processing
- **Stripe Integration**
  - Credit/debit card processing
  - Recurring donations
  - Apple Pay & Google Pay support
  - PCI-compliant tokenization
  - Webhook handling for async events

- **PayPal Integration**
  - PayPal Standard for one-time donations
  - Recurring donations via PayPal subscriptions
  - IPN (Instant Payment Notification) handling

### 2. Donation Forms
- Responsive donation form with suggested amounts
- One-time and recurring donation options
- Honor/memorial giving
- Campaign-specific donation pages
- Custom donation amounts
- Billing address collection (optional)

### 3. CiviCRM Integration
- Automatic contact creation/updating
- Contribution tracking
- Recurring contribution management
- Campaign allocation
- Soft credits for honor/memorial gifts
- Financial type categorization

### 4. Automated Features
- Email receipt generation
- Tax acknowledgment letters
- Recurring donation processing
- Failed payment retry logic
- Donor tagging

### 5. Reporting
- Donation dashboard with real-time statistics
- Monthly/yearly summaries
- Campaign progress tracking
- Top donor reports
- Export to CSV/PDF

## Configuration

Access settings at `/admin/config/ilas/payment`

### Payment Processors

#### Stripe Setup
1. Create a Stripe account at https://stripe.com
2. Get your API keys from the Stripe Dashboard
3. Configure webhook endpoint: `https://yoursite.org/payment/stripe/webhook`
4. Add webhook events:
   - payment_intent.succeeded
   - payment_intent.payment_failed
   - customer.subscription.created
   - customer.subscription.deleted
   - invoice.payment_succeeded

#### PayPal Setup
1. Create a PayPal Business account
2. Configure IPN URL: `https://yoursite.org/payment/paypal/ipn`
3. Enable IPN in PayPal account settings

### Email Templates
Customize receipt templates with available tokens:
- `[donor_name]` - Donor's full name
- `[amount]` - Donation amount
- `[date]` - Donation date
- `[transaction_id]` - Payment transaction ID

## Security

### PCI Compliance
- No credit card data stored in database
- All payment processing handled by certified providers
- SSL/TLS required for all payment pages
- Tokenization for recurring payments

### Access Control
- Payment settings restricted to administrators
- Financial reports require specific permission
- Webhook endpoints use signature verification

## Usage

### Donation Page
The main donation form is available at `/donate`

### Campaign-Specific Pages
Create targeted donation pages: `/donate/campaign/{campaign_id}`

### Embedding Donation Forms
```php
$form = \Drupal::formBuilder()->getForm('Drupal\ilas_payment\Form\DonationForm');
```

### Processing Payments Programmatically
```php
$processor = \Drupal::service('ilas_payment.processor');
$result = $processor->processPayment([
  'amount' => 100,
  'payment_method' => 'stripe',
  'email' => 'donor@example.com',
  'stripe_payment_method_id' => 'pm_xxx',
]);
```

## Hooks

### Alter donation form
```php
function mymodule_form_ilas_donation_form_alter(&$form, FormStateInterface $form_state) {
  // Add custom fields or modify form
}
```

### React to successful donations
```php
function mymodule_ilas_payment_donation_complete($contribution_id) {
  // Custom actions after successful donation
}
```

## Troubleshooting

### Payments not processing
1. Check payment processor configuration
2. Verify SSL certificate is valid
3. Check Drupal logs at `/admin/reports/dblog`
4. Verify API keys are correct

### Webhooks not working
1. Ensure webhook URLs are accessible
2. Check webhook signing secrets
3. Verify server allows POST requests
4. Review webhook logs in payment processor dashboard

### Email receipts not sending
1. Check email configuration
2. Verify receipt template is configured
3. Check that CiviCRM has valid email for contact
4. Review mail logs

## Testing

### Test Cards (Stripe)
- Success: 4242 4242 4242 4242
- Decline: 4000 0000 0000 0002
- Requires authentication: 4000 0025 0000 3155

### Test Mode
Enable test mode in settings to use sandbox environments for both Stripe and PayPal.

## Cron Tasks
The module performs these tasks on cron:
- Process recurring donations
- Send pending acknowledgments
- Retry failed payments
- Update subscription statuses

## API Endpoints

### Webhooks
- Stripe: `/payment/stripe/webhook`
- PayPal IPN: `/payment/paypal/ipn`

### Reporting API
- Get donation stats: `/api/donations/stats`
- Campaign progress: `/api/campaign/{id}/progress`

## Requirements
- Drupal 11
- CiviCRM 5.75+
- SSL certificate
- PHP 8.1+
- Composer packages:
  - stripe/stripe-php (if using Stripe)

## Support
For issues or questions:
- Email: support@idaholegalaid.org
- Documentation: /admin/help/ilas_payment