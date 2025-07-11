# Webform Configuration Instructions

## Step 1: Create the Webform
1. Go to `/admin/structure/webform/add`
2. Enter:
   - Title: "Donation Inquiry"
   - Machine name: `donation_inquiry`
3. Click "Save"

## Step 2: Add Form Elements
1. Click on "Build" tab
2. Click "Source" button
3. Delete any existing content
4. Paste the contents of `donation-inquiry-webform-elements.yml`
5. Click "Save elements"

## Step 3: Configure Email Handler
1. Go to "Settings" > "Emails / Handlers" tab
2. Click "Add handler"
3. Select "Email" and click "Add handler"
4. Configure:
   - **Title**: Email Notification
   - **Send to**: donations@idaholegalaid.org
   - **Send from**: [webform_submission:values:email:raw]
   - **From name**: [webform_submission:values:first_name] [webform_submission:values:last_name]
   - **Subject**: Donation Inquiry from [webform_submission:values:first_name] [webform_submission:values:last_name]
   - **Body**: Use this template:

```html
<p>A new donation inquiry has been submitted.</p>

<h3>Contact Information:</h3>
<p>
Name: [webform_submission:values:first_name] [webform_submission:values:last_name]<br>
Email: [webform_submission:values:email]<br>
Phone: [webform_submission:values:phone]<br>
Address: [webform_submission:values:address]
</p>

<h3>Areas of Interest:</h3>
[webform_submission:values:interests]

<h3>All Submission Details:</h3>
[webform_submission:values]
```

## Step 4: Configure Form Settings
1. Go to "Settings" > "Form" tab
2. Set:
   - **Submission label**: Donation Inquiry
   - **AJAX**: Check "Use AJAX"
   - **Confirmation type**: Inline
   - **Confirmation message**:
   ```html
   <div class="alert alert-success">
     <h3>Thank you for contacting us!</h3>
     <p>We've received your inquiry and will respond to your donation-related questions soon.</p>
   </div>
   ```

## Step 5: Configure Access Permissions
1. Go to "Settings" > "Access" tab
2. Ensure "Anonymous user" can:
   - View webform
   - Create webform submissions

## Step 6: Test the Integration
The custom JavaScript (`donation-inquiry.js`) will submit to this webform using:
- Endpoint: `/webform/donation_inquiry`
- The form will handle AJAX submissions automatically

## Optional: Enable REST API Submission
If you want to use the REST API endpoint:
1. Enable the Webform REST module
2. Configure REST permissions at `/admin/people/permissions`
3. Grant "Access POST on Webform submission resource" to anonymous users