<?php

namespace Drupal\ilas_civicrm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure ILAS CiviCRM Integration settings.
 */
class IlasCiviCrmSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilas_civicrm_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ilas_civicrm.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ilas_civicrm.settings');
    
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Settings'),
      '#open' => TRUE,
    ];
    
    $form['general']['enable_webform_integration'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Webform Integration'),
      '#description' => $this->t('Automatically process webform submissions through CiviCRM.'),
      '#default_value' => $config->get('enable_webform_integration') ?? TRUE,
    ];
    
    $form['general']['enable_chatbot_integration'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Chatbot Integration'),
      '#description' => $this->t('Process chatbot interactions through CiviCRM.'),
      '#default_value' => $config->get('enable_chatbot_integration') ?? TRUE,
    ];
    
    $form['general']['create_cases_automatically'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create Cases Automatically'),
      '#description' => $this->t('Automatically create cases for urgent requests.'),
      '#default_value' => $config->get('create_cases_automatically') ?? TRUE,
    ];
    
    $form['webform'] = [
      '#type' => 'details',
      '#title' => $this->t('Webform Settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="enable_webform_integration"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    $form['webform']['webform_mappings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Webform Field Mappings'),
      '#description' => $this->t('JSON configuration for mapping webform fields to CiviCRM fields. One mapping per line.'),
      '#default_value' => $config->get('webform_mappings') ?? '',
      '#rows' => 10,
    ];
    
    $form['webform']['priority_forms'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Priority Forms'),
      '#description' => $this->t('Forms that should create high-priority activities.'),
      '#options' => $this->getWebformOptions(),
      '#default_value' => $config->get('priority_forms') ?? [],
    ];
    
    $form['chatbot'] = [
      '#type' => 'details',
      '#title' => $this->t('Chatbot Settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="enable_chatbot_integration"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    $form['chatbot']['chatbot_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chatbot API Key'),
      '#description' => $this->t('API key for authenticating chatbot webhook requests.'),
      '#default_value' => $config->get('chatbot_api_key') ?? '',
    ];
    
    $form['chatbot']['chatbot_create_anonymous'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create Anonymous Contacts'),
      '#description' => $this->t('Create anonymous contacts for chatbot users without email.'),
      '#default_value' => $config->get('chatbot_create_anonymous') ?? TRUE,
    ];
    
    $form['chatbot']['urgent_intents'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Urgent Intents'),
      '#description' => $this->t('Chatbot intents that should create urgent cases (one per line).'),
      '#default_value' => $config->get('urgent_intents') ?? "emergency_eviction\ndomestic_violence\nchild_emergency",
      '#rows' => 5,
    ];
    
    $form['activity'] = [
      '#type' => 'details',
      '#title' => $this->t('Activity Types'),
      '#open' => FALSE,
    ];
    
    $form['activity']['custom_activity_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom Activity Types'),
      '#description' => $this->t('Additional activity types to create in CiviCRM (one per line).'),
      '#default_value' => $config->get('custom_activity_types') ?? "Legal Help Request\nEligibility Check\nResource Request\nVolunteer Application",
      '#rows' => 10,
    ];
    
    $form['tags'] = [
      '#type' => 'details',
      '#title' => $this->t('Tags Configuration'),
      '#open' => FALSE,
    ];
    
    $form['tags']['service_area_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Service Area Tags'),
      '#description' => $this->t('Service area tags to create (one per line).'),
      '#default_value' => $config->get('service_area_tags') ?? implode("\n", [
        'Housing',
        'Family Law',
        'Consumer',
        'Public Benefits',
        'Health',
        'Education',
        'Employment',
        'Immigration',
        'Criminal Record',
        'Native American Law',
      ]),
      '#rows' => 10,
    ];
    
    $form['tags']['auto_tag_rules'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Auto-tagging Rules'),
      '#description' => $this->t('Rules for automatically applying tags based on form data. Format: field_name:value:tag_name (one per line).'),
      '#default_value' => $config->get('auto_tag_rules') ?? '',
      '#rows' => 10,
    ];
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ilas_civicrm.settings');
    
    // Save all form values
    $config->set('enable_webform_integration', $form_state->getValue('enable_webform_integration'))
      ->set('enable_chatbot_integration', $form_state->getValue('enable_chatbot_integration'))
      ->set('create_cases_automatically', $form_state->getValue('create_cases_automatically'))
      ->set('webform_mappings', $form_state->getValue('webform_mappings'))
      ->set('priority_forms', $form_state->getValue('priority_forms'))
      ->set('chatbot_api_key', $form_state->getValue('chatbot_api_key'))
      ->set('chatbot_create_anonymous', $form_state->getValue('chatbot_create_anonymous'))
      ->set('urgent_intents', $form_state->getValue('urgent_intents'))
      ->set('custom_activity_types', $form_state->getValue('custom_activity_types'))
      ->set('service_area_tags', $form_state->getValue('service_area_tags'))
      ->set('auto_tag_rules', $form_state->getValue('auto_tag_rules'))
      ->save();
    
    // Create custom activity types if needed
    $this->createCustomActivityTypes($form_state->getValue('custom_activity_types'));
    
    // Create service area tags
    $this->createServiceAreaTags($form_state->getValue('service_area_tags'));
    
    parent::submitForm($form, $form_state);
  }

  /**
   * Get available webforms.
   */
  protected function getWebformOptions() {
    $options = [];
    
    try {
      $webforms = \Drupal::entityTypeManager()
        ->getStorage('webform')
        ->loadMultiple();
      
      foreach ($webforms as $webform) {
        $options[$webform->id()] = $webform->label();
      }
    }
    catch (\Exception $e) {
      // Log error
    }
    
    return $options;
  }

  /**
   * Create custom activity types in CiviCRM.
   */
  protected function createCustomActivityTypes($types_string) {
    if (empty($types_string)) {
      return;
    }
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      $types = array_filter(array_map('trim', explode("\n", $types_string)));
      
      foreach ($types as $type) {
        // Check if activity type exists
        $existing = civicrm_api3('OptionValue', 'get', [
          'option_group_id' => 'activity_type',
          'label' => $type,
        ]);
        
        if ($existing['count'] == 0) {
          // Create activity type
          civicrm_api3('OptionValue', 'create', [
            'option_group_id' => 'activity_type',
            'label' => $type,
            'name' => str_replace(' ', '_', strtolower($type)),
            'is_active' => 1,
          ]);
          
          \Drupal::messenger()->addMessage($this->t('Created activity type: @type', ['@type' => $type]));
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Failed to create activity types: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Create service area tags in CiviCRM.
   */
  protected function createServiceAreaTags($tags_string) {
    if (empty($tags_string)) {
      return;
    }
    
    try {
      \Drupal::service('civicrm')->initialize();
      
      $tags = array_filter(array_map('trim', explode("\n", $tags_string)));
      
      foreach ($tags as $tag) {
        // Check if tag exists
        $existing = civicrm_api3('Tag', 'get', [
          'label' => $tag,
          'used_for' => 'civicrm_contact',
        ]);
        
        if ($existing['count'] == 0) {
          // Create tag
          civicrm_api3('Tag', 'create', [
            'label' => $tag,
            'name' => str_replace(' ', '_', strtolower($tag)),
            'used_for' => 'civicrm_contact',
            'is_selectable' => 1,
          ]);
          
          \Drupal::messenger()->addMessage($this->t('Created tag: @tag', ['@tag' => $tag]));
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Failed to create tags: @error', ['@error' => $e->getMessage()]));
    }
  }
}