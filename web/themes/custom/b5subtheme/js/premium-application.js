/**
 * Premium Employment Application Wizard
 * Sets the standard for nonprofit employment applications
 */
(function ($, Drupal) {
  'use strict';

  // Configuration
  const CONFIG = {
    STORAGE_KEY: 'employment_application_data',
    AUTO_SAVE_INTERVAL: 2000, // 2 seconds
    ANIMATION_DURATION: 300,
    
    SELECTORS: {
      WIZARD: '.application-wizard',
      STEP: '.wizard-step',
      PROGRESS_BAR: '.progress-bar-fill',
      PROGRESS_STEPS: '.progress-steps .step',
      NAV_PREV: '.btn-prev',
      NAV_NEXT: '.btn-next',
      NAV_SUBMIT: '.btn-submit',
      CURRENT_STEP: '.current-step',
      FILE_INPUT: '.file-input',
      FILE_UPLOAD_AREA: '.file-upload-area',
      AUTO_SAVE_STATUS: '.auto-save-status',
      SUCCESS_MODAL: '.application-success-modal'
    },

    VALIDATION: {
      EMAIL_PATTERN: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
      PHONE_PATTERN: /^[\+]?[\s\-\(\)]?[\d\s\-\(\)]{10,}$/,
      FILE_MAX_SIZE: 5 * 1024 * 1024, // 5MB
      ALLOWED_EXTENSIONS: ['pdf', 'doc', 'docx']
    },

    MESSAGES: {
      REQUIRED: 'This field is required',
      INVALID_EMAIL: 'Please enter a valid email address',
      INVALID_PHONE: 'Please enter a valid phone number',
      FILE_TOO_LARGE: 'File size must be less than 5MB',
      INVALID_FILE_TYPE: 'Only PDF, DOC, and DOCX files are allowed',
      SAVE_SUCCESS: 'All changes saved',
      SAVE_ERROR: 'Error saving progress'
    }
  };

  // Main behavior
  Drupal.behaviors.premiumApplicationWizard = {
    attach: function (context, settings) {
      try {
        const $wizard = $(CONFIG.SELECTORS.WIZARD, context);
        
        if ($wizard.length) {
          $wizard.each(function () {
            if (!$(this).data('wizard-initialized')) {
              $(this).data('wizard-initialized', true);
              const wizard = new PremiumApplicationWizard($(this));
              wizard.init();
            }
          });
        }
      } catch (error) {
        console.error('Premium application wizard initialization error:', error);
      }
    }
  };

  /**
   * Premium Application Wizard Class
   */
  function PremiumApplicationWizard($container) {
    this.$container = $container;
    this.currentStep = 1;
    this.totalSteps = 5;
    this.formData = {};
    this.autoSaveTimer = null;
    this.uploadedFiles = {};
    
    // Initialize form data from localStorage if available
    this.loadSavedData();
  }

  PremiumApplicationWizard.prototype = {
    /**
     * Initialize the wizard
     */
    init: function() {
      try {
        this.setupEventListeners();
        this.setupFileUploads();
        this.setupAutoSave();
        this.updateProgress();
        this.validateCurrentStep();
        this.initializeAccessibility();
        
        // Focus first input
        this.focusFirstInput();
        
      } catch (error) {
        console.error('Wizard initialization error:', error);
      }
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function() {
      const self = this;
      

      // Navigation buttons
      this.$container.on('click', CONFIG.SELECTORS.NAV_NEXT, function(e) {
        e.preventDefault();
        self.nextStep();
      });

      this.$container.on('click', CONFIG.SELECTORS.NAV_PREV, function(e) {
        e.preventDefault();
        self.prevStep();
      });

      this.$container.on('click', CONFIG.SELECTORS.NAV_SUBMIT, function(e) {
        e.preventDefault();
        self.submitApplication();
      });

      // Edit section buttons
      this.$container.on('click', '.edit-section', function(e) {
        e.preventDefault();
        const targetStep = parseInt($(this).data('step'));
        self.goToStep(targetStep);
      });

      // Form field changes
      this.$container.on('input change', 'input, select, textarea', function() {
        self.handleFieldChange($(this));
      });

      // Conditional fields
      this.$container.on('change', '#position_interest', function() {
        self.toggleConditionalFields();
      });

      // Auto-resize textareas
      this.$container.on('input', '.auto-resize', function() {
        self.autoResizeTextarea($(this));
      });

      // Keyboard navigation
      $(document).on('keydown', function(e) {
        if (e.key === 'Enter' && !$(e.target).is('textarea')) {
          e.preventDefault();
          self.nextStep();
        }
      });
    },

    /**
     * Setup file upload functionality
     */
    setupFileUploads: function() {
      const self = this;

      this.$container.find(CONFIG.SELECTORS.FILE_UPLOAD_AREA).each(function() {
        const $area = $(this);
        const $input = $area.find(CONFIG.SELECTORS.FILE_INPUT);

        // Click to browse
        $area.on('click', '.upload-placeholder', function() {
          $input.click();
        });

        // File input change
        $input.on('change', function(e) {
          self.handleFileUpload($(this), e.target.files);
        });

        // Drag and drop
        $area.on('dragover dragenter', function(e) {
          e.preventDefault();
          e.stopPropagation();
          $area.addClass('drag-over');
        });

        $area.on('dragleave dragend', function(e) {
          e.preventDefault();
          e.stopPropagation();
          $area.removeClass('drag-over');
        });

        $area.on('drop', function(e) {
          e.preventDefault();
          e.stopPropagation();
          $area.removeClass('drag-over');
          
          const files = e.originalEvent.dataTransfer.files;
          if (files.length > 0) {
            self.handleFileUpload($input, files);
          }
        });

        // Remove file
        $area.on('click', '.remove-file', function(e) {
          e.preventDefault();
          self.removeFile($area);
        });
      });
    },

    /**
     * Handle file upload
     */
    handleFileUpload: function($input, files) {
      const self = this;
      const $area = $input.closest(CONFIG.SELECTORS.FILE_UPLOAD_AREA);
      const fieldName = $area.data('field');
      
      if (files.length === 0) return;

      const file = files[0];
      
      // Validate file
      if (!this.validateFile(file)) {
        return;
      }

      // Show progress
      this.showUploadProgress($area);

      // Simulate upload progress (in real implementation, this would be actual upload)
      setTimeout(() => {
        this.showFilePreview($area, file);
        this.uploadedFiles[fieldName] = file;
        this.saveFormData();
        this.showAutoSaveStatus(true);
      }, 1500);
    },

    /**
     * Validate file
     */
    validateFile: function(file) {
      // Check file size
      if (file.size > CONFIG.VALIDATION.FILE_MAX_SIZE) {
        this.showValidationError(CONFIG.MESSAGES.FILE_TOO_LARGE);
        return false;
      }

      // Check file extension
      const extension = file.name.split('.').pop().toLowerCase();
      if (!CONFIG.VALIDATION.ALLOWED_EXTENSIONS.includes(extension)) {
        this.showValidationError(CONFIG.MESSAGES.INVALID_FILE_TYPE);
        return false;
      }

      return true;
    },

    /**
     * Show upload progress
     */
    showUploadProgress: function($area) {
      $area.find('.upload-placeholder').hide();
      $area.find('.file-preview').hide();
      
      const $progress = $area.find('.upload-progress').show();
      const $bar = $progress.find('.progress-bar');
      
      // Animate progress bar
      let progress = 0;
      const interval = setInterval(() => {
        progress += Math.random() * 30;
        if (progress >= 100) {
          progress = 100;
          clearInterval(interval);
        }
        $bar.css('width', progress + '%');
      }, 100);
    },

    /**
     * Show file preview
     */
    showFilePreview: function($area, file) {
      $area.find('.upload-progress').hide();
      
      const $preview = $area.find('.file-preview').show();
      $preview.find('.file-name').text(file.name);
      $preview.find('.file-size').text(this.formatFileSize(file.size));
    },

    /**
     * Remove file
     */
    removeFile: function($area) {
      const fieldName = $area.data('field');
      
      $area.find('.file-preview').hide();
      $area.find('.upload-placeholder').show();
      $area.find(CONFIG.SELECTORS.FILE_INPUT).val('');
      
      delete this.uploadedFiles[fieldName];
      this.saveFormData();
    },

    /**
     * Format file size
     */
    formatFileSize: function(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    /**
     * Setup auto-save functionality
     */
    setupAutoSave: function() {
      const self = this;
      
      this.$container.on('input change', 'input, select, textarea', function() {
        clearTimeout(self.autoSaveTimer);
        self.autoSaveTimer = setTimeout(() => {
          self.saveFormData();
          self.showAutoSaveStatus(true);
        }, CONFIG.AUTO_SAVE_INTERVAL);
      });
    },

    /**
     * Handle field changes
     */
    handleFieldChange: function($field) {
      // Real-time validation
      this.validateField($field);
      
      // Update form data
      const name = $field.attr('name');
      const value = $field.val();
      this.formData[name] = value;

      // Re-validate current step to update Next button state
      this.validateCurrentStep();

      // Update review section if on review step
      if (this.currentStep === 5) {
        this.updateReviewSection();
      }
    },

    /**
     * Validate individual field
     */
    validateField: function($field) {
      const value = $field.val().trim();
      const isRequired = $field.prop('required');
      const type = $field.attr('type');
      const fieldName = $field.attr('name') || $field.attr('id');
      const $group = $field.closest('.form-group');
      
      
      let isValid = true;
      let message = '';

      // Required validation
      if (isRequired && !value) {
        isValid = false;
        message = CONFIG.MESSAGES.REQUIRED;
      }
      // Email validation
      else if (type === 'email' && value && !CONFIG.VALIDATION.EMAIL_PATTERN.test(value)) {
        isValid = false;
        message = CONFIG.MESSAGES.INVALID_EMAIL;
      }
      // Phone validation
      else if (type === 'tel' && value && !CONFIG.VALIDATION.PHONE_PATTERN.test(value)) {
        isValid = false;
        message = CONFIG.MESSAGES.INVALID_PHONE;
      }

      // Update UI
      this.updateFieldValidation($group, isValid, message);
      
      return isValid;
    },

    /**
     * Update field validation UI
     */
    updateFieldValidation: function($group, isValid, message) {
      const $feedback = $group.find('.form-feedback');
      
      $group.removeClass('is-valid is-invalid');
      
      if (isValid) {
        $group.addClass('is-valid');
        $feedback.removeClass('invalid-feedback').addClass('valid-feedback').text('');
      } else {
        $group.addClass('is-invalid');
        $feedback.removeClass('valid-feedback').addClass('invalid-feedback').text(message);
      }
    },

    /**
     * Validate current step
     */
    validateCurrentStep: function() {
      const $currentStep = this.$container.find(CONFIG.SELECTORS.STEP + '[data-step="' + this.currentStep + '"]');
      const $requiredFields = $currentStep.find('[required]');
      let isValid = true;

      $requiredFields.each((index, field) => {
        const $field = $(field);
        const fieldValid = this.validateField($field);
        if (!fieldValid) {
          isValid = false;
        }
      });

      // Update next button state
      const $nextBtn = this.$container.find(CONFIG.SELECTORS.NAV_NEXT);
      $nextBtn.prop('disabled', !isValid);

      return isValid;
    },

    /**
     * Go to next step
     */
    nextStep: function() {
      const isValid = this.validateCurrentStep();
      
      if (!isValid) {
        this.showValidationError('Please complete all required fields before continuing.');
        return;
      }

      if (this.currentStep < this.totalSteps) {
        this.goToStep(this.currentStep + 1);
      } else {
      }
    },

    /**
     * Go to previous step
     */
    prevStep: function() {
      if (this.currentStep > 1) {
        this.goToStep(this.currentStep - 1);
      }
    },

    /**
     * Go to specific step
     */
    goToStep: function(stepNumber) {
      if (stepNumber < 1 || stepNumber > this.totalSteps) return;

      const $currentStepEl = this.$container.find(CONFIG.SELECTORS.STEP + '[data-step="' + this.currentStep + '"]');
      const $targetStepEl = this.$container.find(CONFIG.SELECTORS.STEP + '[data-step="' + stepNumber + '"]');

      // Animate step transition
      $currentStepEl.fadeOut(CONFIG.ANIMATION_DURATION, () => {
        $currentStepEl.removeClass('active');
        $targetStepEl.addClass('active').fadeIn(CONFIG.ANIMATION_DURATION);
        
        // Focus first input in new step
        this.focusFirstInput();
      });

      this.currentStep = stepNumber;
      this.updateProgress();
      this.updateNavigation();

      // Announce step change
      const stepName = this.$container.find(CONFIG.SELECTORS.STEP + '[data-step="' + stepNumber + '"] .step-header h2').text();
      this.announce(`Step ${stepNumber} of ${this.totalSteps}: ${stepName}`);

      // Update review if on review step
      if (stepNumber === 5) {
        this.updateReviewSection();
      }

      // Save progress
      this.saveFormData();
    },

    /**
     * Update progress indicator
     */
    updateProgress: function() {
      const progressPercent = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
      
      // Update progress bar
      this.$container.find(CONFIG.SELECTORS.PROGRESS_BAR).css('width', progressPercent + '%');
      
      // Update step indicators
      this.$container.find(CONFIG.SELECTORS.PROGRESS_STEPS).each((index, step) => {
        const $step = $(step);
        const stepNum = parseInt($step.data('step'));
        
        $step.removeClass('active completed');
        
        if (stepNum === this.currentStep) {
          $step.addClass('active');
        } else if (stepNum < this.currentStep) {
          $step.addClass('completed');
        }
      });

      // Update step counter
      this.$container.find(CONFIG.SELECTORS.CURRENT_STEP).text(this.currentStep);
    },

    /**
     * Update navigation buttons
     */
    updateNavigation: function() {
      const $prevBtn = this.$container.find(CONFIG.SELECTORS.NAV_PREV);
      const $nextBtn = this.$container.find(CONFIG.SELECTORS.NAV_NEXT);
      const $submitBtn = this.$container.find(CONFIG.SELECTORS.NAV_SUBMIT);

      // Show/hide previous button
      if (this.currentStep === 1) {
        $prevBtn.hide();
      } else {
        $prevBtn.show();
      }

      // Show/hide next/submit buttons
      if (this.currentStep === this.totalSteps) {
        $nextBtn.hide();
        $submitBtn.show();
      } else {
        $nextBtn.show();
        $submitBtn.hide();
      }
    },

    /**
     * Toggle conditional fields
     */
    toggleConditionalFields: function() {
      const positionValue = this.$container.find('#position_interest').val();
      const $otherField = this.$container.find('.other-position');
      
      if (positionValue === 'other') {
        $otherField.show().find('input').prop('required', true);
      } else {
        $otherField.hide().find('input').prop('required', false);
      }
    },

    /**
     * Auto-resize textarea
     */
    autoResizeTextarea: function($textarea) {
      $textarea[0].style.height = 'auto';
      $textarea[0].style.height = $textarea[0].scrollHeight + 'px';
    },

    /**
     * Focus first input in current step
     */
    focusFirstInput: function() {
      const $currentStep = this.$container.find(CONFIG.SELECTORS.STEP + '[data-step="' + this.currentStep + '"]');
      const $firstInput = $currentStep.find('input, select, textarea').not(':hidden').first();
      
      setTimeout(() => {
        $firstInput.focus();
      }, CONFIG.ANIMATION_DURATION);
    },

    /**
     * Update review section
     */
    updateReviewSection: function() {
      // Personal Information
      this.updateReviewPersonal();
      
      // Position Details
      this.updateReviewPosition();
      
      // Experience
      this.updateReviewExperience();
      
      // Documents
      this.updateReviewDocuments();
    },

    /**
     * Update personal info review
     */
    updateReviewPersonal: function() {
      const $review = this.$container.find('#review-personal');
      const data = this.getFormData();
      
      const html = `
        <div class="review-item"><strong>Name:</strong> ${data.first_name || ''} ${data.last_name || ''}</div>
        <div class="review-item"><strong>Email:</strong> ${data.email || ''}</div>
        <div class="review-item"><strong>Phone:</strong> ${data.phone || ''}</div>
        <div class="review-item"><strong>Preferred Contact:</strong> ${data.preferred_contact || 'Not specified'}</div>
      `;
      
      $review.html(html);
    },

    /**
     * Update position review
     */
    updateReviewPosition: function() {
      const $review = this.$container.find('#review-position');
      const data = this.getFormData();
      
      const positionText = data.position_interest === 'other' ? data.position_other : data.position_interest;
      
      const html = `
        <div class="review-item"><strong>Position:</strong> ${positionText || ''}</div>
        <div class="review-item"><strong>Employment Type:</strong> ${data.employment_type || ''}</div>
        <div class="review-item"><strong>Start Date:</strong> ${data.start_date || 'Flexible'}</div>
        <div class="review-item"><strong>Salary Expectations:</strong> ${data.salary_type || ''} ${data.salary_amount || ''}</div>
      `;
      
      $review.html(html);
    },

    /**
     * Update experience review
     */
    updateReviewExperience: function() {
      const $review = this.$container.find('#review-experience');
      const data = this.getFormData();
      
      const html = `
        <div class="review-item"><strong>Education:</strong> ${data.education || 'Not provided'}</div>
        <div class="review-item"><strong>Experience:</strong> ${data.experience || 'Not provided'}</div>
        <div class="review-item"><strong>Why Join Us:</strong> ${data.why_join || ''}</div>
        <div class="review-item"><strong>Languages:</strong> ${data.languages || 'Not specified'}</div>
        <div class="review-item"><strong>References:</strong> ${data.references_available || 'Yes, upon request'}</div>
      `;
      
      $review.html(html);
    },

    /**
     * Update documents review
     */
    updateReviewDocuments: function() {
      const $review = this.$container.find('#review-documents');
      
      let html = '<div class="uploaded-files">';
      
      for (const [fieldName, file] of Object.entries(this.uploadedFiles)) {
        const displayName = fieldName.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        html += `<div class="review-item"><strong>${displayName}:</strong> ${file.name} (${this.formatFileSize(file.size)})</div>`;
      }
      
      if (Object.keys(this.uploadedFiles).length === 0) {
        html += '<div class="review-item">No files uploaded</div>';
      }
      
      html += '</div>';
      
      $review.html(html);
    },

    /**
     * Get all form data
     */
    getFormData: function() {
      const data = {};
      
      this.$container.find('input, select, textarea').each(function() {
        const $field = $(this);
        const name = $field.attr('name');
        if (name && $field.attr('type') !== 'file') {
          data[name] = $field.val();
        }
      });
      
      return data;
    },

    /**
     * Save form data to localStorage
     */
    saveFormData: function() {
      try {
        const data = {
          formData: this.getFormData(),
          currentStep: this.currentStep,
          uploadedFiles: Object.keys(this.uploadedFiles),
          timestamp: Date.now()
        };
        
        localStorage.setItem(CONFIG.STORAGE_KEY, JSON.stringify(data));
      } catch (error) {
        console.error('Error saving form data:', error);
      }
    },

    /**
     * Load saved data from localStorage
     */
    loadSavedData: function() {
      try {
        const saved = localStorage.getItem(CONFIG.STORAGE_KEY);
        if (saved) {
          const data = JSON.parse(saved);
          
          // Only load if saved within last 24 hours
          if (Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
            this.formData = data.formData || {};
            // Note: We can't restore uploaded files from localStorage
            // In a real implementation, this would check server-side storage
          }
        }
      } catch (error) {
        console.error('Error loading saved data:', error);
      }
    },

    /**
     * Show auto-save status
     */
    showAutoSaveStatus: function(success) {
      const $status = this.$container.find(CONFIG.SELECTORS.AUTO_SAVE_STATUS);
      const $icon = $status.find('i');
      const $text = $status.find('.save-text');
      
      if (success) {
        $icon.removeClass('fa-exclamation-triangle').addClass('fa-check-circle');
        $text.text(CONFIG.MESSAGES.SAVE_SUCCESS);
        $status.removeClass('error').addClass('success');
      } else {
        $icon.removeClass('fa-check-circle').addClass('fa-exclamation-triangle');
        $text.text(CONFIG.MESSAGES.SAVE_ERROR);
        $status.removeClass('success').addClass('error');
      }
      
      $status.fadeIn().delay(2000).fadeOut();
    },

    /**
     * Show validation error
     */
    showValidationError: function(message) {
      // In a real implementation, this would show a toast or alert
      alert(message);
    },

    /**
     * Submit application
     */
    submitApplication: function() {
      
      // Final validation
      if (!this.validateFinalSubmission()) {
        return;
      }


      // Show loading state
      const $submitBtn = this.$container.find(CONFIG.SELECTORS.NAV_SUBMIT);
      const originalText = $submitBtn.html();
      $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');

      // Collect all form data
      const applicationData = this.collectAllFormData();
      
      // Submit to Drupal
      this.submitToDrupal(applicationData)
        .then(() => {
          this.showSuccessModal();
          
          // Clear saved data
          localStorage.removeItem(CONFIG.STORAGE_KEY);
          
          // Reset button
          $submitBtn.prop('disabled', false).html(originalText);
        })
        .catch((error) => {
          console.error('Submission failed:', error);
          this.showValidationError('There was an error submitting your application. Please try again.');
          
          // Reset button
          $submitBtn.prop('disabled', false).html(originalText);
        });
    },

    /**
     * Validate final submission
     */
    validateFinalSubmission: function() {
      // Check required agreements
      const requiredAgreements = ['accuracy_agreement'];
      
      for (const agreementId of requiredAgreements) {
        if (!this.$container.find('#' + agreementId).is(':checked')) {
          this.showValidationError('Please agree to the required terms before submitting.');
          return false;
        }
      }

      // Check required files
      if (!this.uploadedFiles.resume) {
        this.showValidationError('Please upload your resume before submitting.');
        return false;
      }

      return true;
    },

    /**
     * Show success modal
     */
    showSuccessModal: function() {
      // Modal is outside the wizard container, so search in the entire document
      const $modal = $(CONFIG.SELECTORS.SUCCESS_MODAL);
      
      $modal.fadeIn();

      // Close modal when clicking outside
      $modal.on('click', function(e) {
        if (e.target === this) {
          $modal.fadeOut();
        }
      });
    },

    /**
     * Collect all form data including files
     */
    collectAllFormData: function() {
      const formData = this.getFormData();
      
      // Add uploaded files info
      formData.uploadedFiles = {};
      for (const [fieldName, file] of Object.entries(this.uploadedFiles)) {
        formData.uploadedFiles[fieldName] = {
          name: file.name,
          size: file.size,
          type: file.type
        };
      }
      
      // Add metadata
      formData._metadata = {
        submittedAt: new Date().toISOString(),
        userAgent: navigator.userAgent,
        currentStep: this.currentStep
      };
      
      return formData;
    },

    /**
     * Submit application data to Drupal
     */
    submitToDrupal: function(applicationData) {
      return new Promise((resolve, reject) => {
        // Create FormData for file uploads
        const formData = new FormData();
        
        // Add all form fields
        for (const [key, value] of Object.entries(applicationData)) {
          if (key !== 'uploadedFiles' && key !== '_metadata') {
            formData.append(key, value || '');
          }
        }
        
        // Add uploaded files
        for (const [fieldName, file] of Object.entries(this.uploadedFiles)) {
          formData.append(`files[${fieldName}]`, file);
        }
        
        // Add metadata
        formData.append('_metadata', JSON.stringify(applicationData._metadata));
        // Drupal handles CSRF tokens automatically in form submissions
        
        // Submit via AJAX
        $.ajax({
          url: '/employment-application/submit',
          method: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          timeout: 30000,
          success: function(response) {
            if (response.success) {
              resolve(response);
            } else {
              reject(new Error(response.message || 'Submission failed'));
            }
          },
          error: function(xhr, status, error) {
            reject(new Error(`Submission failed: ${error}`));
          }
        });
      });
    },

    /**
     * Phone number formatter
     */
    formatPhoneNumber: function(value) {
      const cleaned = value.replace(/\D/g, '');
      const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
      if (match) {
        return '(' + match[1] + ') ' + match[2] + '-' + match[3];
      }
      return value;
    },

    /**
     * Initialize accessibility features
     */
    initializeAccessibility: function() {
      // Add ARIA labels
      this.$container.attr('role', 'application');
      this.$container.attr('aria-label', 'Employment Application Form');
      
      // Add keyboard navigation
      this.$container.on('keydown', (e) => {
        if (e.altKey) {
          switch(e.key) {
            case 'n':
            case 'N':
              e.preventDefault();
              this.nextStep();
              break;
            case 'p':
            case 'P':
              e.preventDefault();
              this.prevStep();
              break;
          }
        }
      });
      
      // Announce step changes to screen readers
      this.createLiveRegion();
    },

    /**
     * Create ARIA live region for announcements
     */
    createLiveRegion: function() {
      if (!$('#application-live-region').length) {
        $('<div>')
          .attr('id', 'application-live-region')
          .attr('role', 'status')
          .attr('aria-live', 'polite')
          .attr('aria-atomic', 'true')
          .addClass('sr-only')
          .appendTo('body');
      }
    },

    /**
     * Announce to screen readers
     */
    announce: function(message) {
      $('#application-live-region').text(message);
      setTimeout(() => {
        $('#application-live-region').text('');
      }, 100);
    }
  };

})(jQuery, Drupal);