(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.ilasDonationForm = {
    attach: function (context, settings) {
      const $form = $('#ilas-donation-form', context).once('donation-form');
      
      if ($form.length) {
        // Handle amount selection
        $form.find('input[name="suggested_amounts"]').on('change', function() {
          // Clear other amount when suggested amount is selected
          $form.find('input[name="other_amount"]').val('');
        });
        
        $form.find('input[name="other_amount"]').on('input', function() {
          // Clear suggested amount when other amount is entered
          $form.find('input[name="suggested_amounts"]:checked').prop('checked', false);
        });
        
        // Handle frequency change
        $form.find('input[name="frequency"]').on('change', function() {
          const isRecurring = $(this).val() !== 'one-time';
          
          // Update submit button text
          if (isRecurring) {
            $form.find('.donation-submit').val(Drupal.t('Start Monthly Donation'));
          } else {
            $form.find('.donation-submit').val(Drupal.t('Donate Now'));
          }
        });
        
        // Handle form submission
        $form.on('submit', function(e) {
          const $submitButton = $form.find('.donation-submit');
          const $errorContainer = $form.find('.payment-error');
          
          // Get amount
          const suggestedAmount = $form.find('input[name="suggested_amounts"]:checked').val();
          const otherAmount = $form.find('input[name="other_amount"]').val();
          const amount = suggestedAmount || otherAmount;
          
          if (!amount || amount <= 0) {
            e.preventDefault();
            showError(Drupal.t('Please select or enter a donation amount.'));
            return;
          }
          
          // Disable submit button
          $submitButton.prop('disabled', true).val(Drupal.t('Processing...'));
          
          // Clear any previous errors
          $errorContainer.remove();
        });
        
        // Helper function to show errors
        function showError(message) {
          const $errorContainer = $('<div class="payment-error"></div>').text(message);
          $form.find('.form-actions').before($errorContainer);
        }
      }
    }
  };

})(jQuery, Drupal, drupalSettings);