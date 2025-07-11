(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.ilasStripeIntegration = {
    attach: function (context, settings) {
      if (typeof Stripe === 'undefined' || !drupalSettings.ilasPayment || !drupalSettings.ilasPayment.stripe) {
        return;
      }
      
      const $cardElement = $('#card-element', context).once('stripe-card');
      
      if ($cardElement.length) {
        // Initialize Stripe
        const stripe = Stripe(drupalSettings.ilasPayment.stripe.publicKey);
        const elements = stripe.elements();
        
        // Create card element
        const cardElement = elements.create('card', {
          style: {
            base: {
              fontSize: '16px',
              color: '#32325d',
              '::placeholder': {
                color: '#aab7c4'
              }
            },
            invalid: {
              color: '#fa755a',
              iconColor: '#fa755a'
            }
          }
        });
        
        // Mount card element
        cardElement.mount('#card-element');
        
        // Handle card validation errors
        cardElement.on('change', function(event) {
          const $errorElement = $('#card-errors');
          
          if (event.error) {
            $errorElement.text(event.error.message).show();
          } else {
            $errorElement.text('').hide();
          }
        });
        
        // Handle form submission
        const $form = $('#ilas-donation-form');
        
        $form.on('submit', async function(e) {
          const paymentMethod = $form.find('input[name="payment_method"]:checked').val();
          
          if (paymentMethod !== 'stripe') {
            return true;
          }
          
          e.preventDefault();
          
          const $submitButton = $form.find('.donation-submit');
          $submitButton.prop('disabled', true);
          
          // Create payment method
          const {error, paymentMethod: stripePaymentMethod} = await stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
            billing_details: {
              name: $form.find('input[name="first_name"]').val() + ' ' + $form.find('input[name="last_name"]').val(),
              email: $form.find('input[name="email"]').val(),
              phone: $form.find('input[name="phone"]').val(),
              address: {
                line1: $form.find('input[name="street_address"]').val(),
                city: $form.find('input[name="city"]').val(),
                state: $form.find('select[name="state"]').val(),
                postal_code: $form.find('input[name="postal_code"]').val()
              }
            }
          });
          
          if (error) {
            // Show error
            showStripeError(error.message);
            $submitButton.prop('disabled', false);
          } else {
            // Add payment method ID to form
            $form.find('input[name="stripe_payment_method_id"]').val(stripePaymentMethod.id);
            
            // Submit form
            $form.off('submit').submit();
          }
        });
        
        // Add error container
        $cardElement.after('<div id="card-errors" class="payment-error" style="display: none;"></div>');
        
        function showStripeError(message) {
          const $errorElement = $('#card-errors');
          $errorElement.text(message).show();
          
          // Scroll to error
          $('html, body').animate({
            scrollTop: $errorElement.offset().top - 100
          }, 500);
        }
      }
    }
  };

})(jQuery, Drupal, drupalSettings);