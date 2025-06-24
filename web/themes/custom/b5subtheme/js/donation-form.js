/**
 * @file
 * Custom donation form functionality
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.donationForm = {
    attach: function (context, settings) {
      // Try without once first to see if that's the issue
      const $forms = $('.custom-donation-form', context);
      
      // Check if once method exists
      if (typeof $forms.once === 'function') {
        $forms.once('donation-form').each(function() {
          initializeDonationForm($(this));
        });
      } else {
        $forms.each(function() {
          const $form = $(this);
          if (!$form.data('donation-form-initialized')) {
            $form.data('donation-form-initialized', true);
            initializeDonationForm($form);
          }
        });
      }
      
      function initializeDonationForm($form) {
        const $amountBtns = $form.find('.amount-btn');
        const $frequencyBtns = $form.find('.frequency-btn');
        const $customAmount = $form.find('#custom-amount-input');
        const $donateBtn = $form.find('.donate-button');
        
        let selectedAmount = null;
        let selectedFrequency = 'one-time';
        
        // Amount button selection
        $amountBtns.on('click', function(e) {
          e.preventDefault();
          $amountBtns.removeClass('active');
          $(this).addClass('active');
          selectedAmount = $(this).data('amount');
          $customAmount.val('');
          updateDonateButton();
        });
        
        // Custom amount input
        $customAmount.on('input', function() {
          const amount = parseFloat($(this).val());
          if (amount > 0) {
            $amountBtns.removeClass('active');
            selectedAmount = amount;
          } else {
            selectedAmount = null;
          }
          updateDonateButton();
        });
        
        // Frequency button selection
        $frequencyBtns.on('click', function(e) {
          e.preventDefault();
          $frequencyBtns.removeClass('active');
          $(this).addClass('active');
          selectedFrequency = $(this).data('frequency');
          updateDonateButton();
        });
        
        // Update donate button with selected amount and frequency
        function updateDonateButton() {
          let baseUrl = 'https://donorbox.org/ilas';
          let buttonText = 'Donate Now';
          
          if (selectedAmount) {
            baseUrl += '?default_interval=' + selectedFrequency + '&amount=' + selectedAmount;
            
            const formattedAmount = '$' + selectedAmount.toLocaleString();
            if (selectedFrequency === 'monthly') {
              buttonText = `Donate ${formattedAmount}/month`;
            } else if (selectedFrequency === 'quarterly') {
              buttonText = `Donate ${formattedAmount}/quarter`;
            } else {
              buttonText = `Donate ${formattedAmount}`;
            }
          }
          
          $donateBtn.attr('href', baseUrl).text(buttonText);
        }
        
        // Initialize with default state
        updateDonateButton();
      }
    }
  };

})(jQuery, Drupal);