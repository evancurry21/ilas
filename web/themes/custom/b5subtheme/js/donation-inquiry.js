(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.donationInquiry = {
    attach: function (context, settings) {
      once('donation-inquiry', '#ways-to-give-form', context).forEach(function(form) {
        let currentStep = 1;
        const totalSteps = 3;
        
        // Handle checkbox changes in step 1
        $(form).find('input[name="interests[]"]').on('change', function() {
          const anyChecked = $(form).find('input[name="interests[]"]:checked').length > 0;
          $(form).find('[data-step="1"] .next-step').prop('disabled', !anyChecked);
        });
        
        // Handle next button
        $(form).on('click', '.next-step', function() {
          if (currentStep === 1) {
            // Show relevant conditional sections in step 2
            const selectedInterests = $(form).find('input[name="interests[]"]:checked').map(function() {
              return this.value;
            }).get();
            
            $(form).find('.conditional-section').removeClass('visible');
            selectedInterests.forEach(function(interest) {
              $(form).find(`.conditional-section[data-condition="${interest}"]`).addClass('visible');
            });
          }
          
          // Validate current step before moving forward
          if (currentStep === 3) {
            if (!validateContactForm(form)) {
              return;
            }
          }
          
          moveToStep(currentStep + 1);
        });
        
        // Handle previous button
        $(form).on('click', '.prev-step', function() {
          moveToStep(currentStep - 1);
        });
        
        // Handle form submission
        $(form).on('submit', function(e) {
          e.preventDefault();
          
          // Validate reCAPTCHA if it exists
          if (typeof grecaptcha !== 'undefined') {
            const recaptchaResponse = grecaptcha.getResponse();
            if (!recaptchaResponse) {
              alert('Please complete the reCAPTCHA verification.');
              return;
            }
          }
          
          // Collect all form data into a structured object
          const formData = {
            // Initial interests
            interests: $('input[name="interests[]"]:checked').map(function() {
              return $(this).val();
            }).get(),
            
            // Making donation fields
            making_donation_issues: $('input[name="making_donation_issues[]"]:checked').map(function() {
              return $(this).val();
            }).get(),
            making_donation_other: $('#making-donation-other').val(),
            
            // Existing donation fields
            existing_donation_issues: $('input[name="existing_donation_issues[]"]:checked').map(function() {
              return $(this).val();
            }).get(),
            existing_donation_other: $('#existing-donation-other').val(),
            
            // Program info
            program_info_details: $('#program-info-details').val(),
            
            // Other ways fields
            other_ways_options: $('input[name="other_ways_options[]"]:checked').map(function() {
              return $(this).val();
            }).get(),
            other_ways_additional: $('#other-ways-additional').val(),
            
            // Contact information
            first_name: $('#first_name').val(),
            last_name: $('#last_name').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            address: $('#address').val()
          };
          
          // Show loading state
          const submitBtn = $(this).find('button[type="submit"]');
          const originalText = submitBtn.text();
          submitBtn.prop('disabled', true).text('Submitting...');
          
          // Submit to Webform AJAX endpoint
          const submitData = new FormData();
          
          // Convert formData to proper format for Webform
          submitData.append('interests', formData.interests.join(','));
          
          // Add conditional fields only if they have values
          if (formData.making_donation_issues.length > 0) {
            submitData.append('making_donation_issues', formData.making_donation_issues.join(','));
          }
          if (formData.making_donation_other) {
            submitData.append('making_donation_other', formData.making_donation_other);
          }
          
          if (formData.existing_donation_issues.length > 0) {
            submitData.append('existing_donation_issues', formData.existing_donation_issues.join(','));
          }
          if (formData.existing_donation_other) {
            submitData.append('existing_donation_other', formData.existing_donation_other);
          }
          
          if (formData.program_info_details) {
            submitData.append('program_info_details', formData.program_info_details);
          }
          
          if (formData.other_ways_options.length > 0) {
            submitData.append('other_ways_options', formData.other_ways_options.join(','));
          }
          if (formData.other_ways_additional) {
            submitData.append('other_ways_additional', formData.other_ways_additional);
          }
          
          // Add contact fields
          submitData.append('first_name', formData.first_name);
          submitData.append('last_name', formData.last_name);
          submitData.append('email', formData.email);
          submitData.append('phone', formData.phone);
          if (formData.address) {
            submitData.append('address', formData.address);
          }
          
          // Add required Drupal AJAX parameters
          submitData.append('form_id', 'webform_submission_donation_inquiry_add_form');
          submitData.append('_drupal_ajax', '1');
          
          $.ajax({
            url: '/webform/donation_inquiry',
            method: 'POST',
            data: submitData,
            processData: false,
            contentType: false,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
              $(form).html(`
                <div class="alert alert-success">
                  <h3>Thank you for contacting us!</h3>
                  <p>We've received your inquiry and will respond to your donation-related questions soon.</p>
                </div>
              `);
            },
            error: function(xhr, status, error) {
              console.error('Form submission error:', error);
              alert('There was an error submitting your form. Please try again later or contact us directly.');
              submitBtn.prop('disabled', false).text(originalText);
            }
          });
        });
        
        // Validate contact form fields
        function validateContactForm(form) {
          let isValid = true;
          const requiredFields = $(form).find('[data-step="3"] input[required]');
          
          requiredFields.each(function() {
            const field = $(this);
            const value = field.val().trim();
            
            if (!value) {
              field.addClass('is-invalid');
              isValid = false;
            } else {
              field.removeClass('is-invalid');
            }
            
            // Email validation
            if (field.attr('type') === 'email' && value) {
              const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
              if (!emailRegex.test(value)) {
                field.addClass('is-invalid');
                isValid = false;
              }
            }
            
            // Phone validation (basic)
            if (field.attr('type') === 'tel' && value) {
              const phoneRegex = /^[\d\s\-\+\(\)]+$/;
              if (!phoneRegex.test(value) || value.length < 10) {
                field.addClass('is-invalid');
                isValid = false;
              }
            }
          });
          
          if (!isValid) {
            alert('Please fill in all required fields correctly.');
          }
          
          return isValid;
        }
        
        // Remove invalid class on input
        $(form).on('input', 'input.is-invalid', function() {
          $(this).removeClass('is-invalid');
        });
        
        function moveToStep(step) {
          if (step < 1 || step > totalSteps) return;
          
          $(form).find('.form-step').removeClass('active');
          $(form).find(`[data-step="${step}"]`).addClass('active');
          
          // Update progress bar
          const progress = (step / totalSteps) * 100;
          $(form).find('.progress-bar').css('width', progress + '%');
          $(form).find('.current-step').text(step);
          
          currentStep = step;
          
          // Scroll to top of form
          $('html, body').animate({
            scrollTop: $(form).offset().top - 100
          }, 300);
        }
        
        // Handle Enter key in form fields
        $(form).on('keypress', 'input', function(e) {
          if (e.which === 13) {
            e.preventDefault();
            const nextButton = $(this).closest('.form-step').find('.next-step');
            if (nextButton.length && !nextButton.prop('disabled')) {
              nextButton.click();
            }
          }
        });
        
        // Accessibility: Allow space bar to toggle checkboxes
        $(form).on('keypress', '.checkbox-option', function(e) {
          if (e.which === 32) {
            e.preventDefault();
            $(this).find('input[type="checkbox"]').click();
          }
        });
      });
    }
  };

})(jQuery, Drupal);