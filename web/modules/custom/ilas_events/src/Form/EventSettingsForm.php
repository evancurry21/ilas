<?php

namespace Drupal\ilas_events\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure event settings.
 */
class EventSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilas_events_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ilas_events.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ilas_events.settings');
    
    // General settings
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Settings'),
      '#open' => TRUE,
    ];
    
    $form['general']['default_capacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Default event capacity'),
      '#min' => 0,
      '#default_value' => $config->get('default_capacity'),
      '#description' => $this->t('Default maximum number of participants for new events.'),
    ];
    
    $form['general']['reminder_days'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reminder days'),
      '#default_value' => implode(', ', $config->get('reminder_days') ?? [7, 3, 1]),
      '#description' => $this->t('Days before event to send reminders (comma-separated).'),
    ];
    
    // Registration settings
    $form['registration'] = [
      '#type' => 'details',
      '#title' => $this->t('Registration Settings'),
      '#open' => TRUE,
    ];
    
    $form['registration']['allow_waitlist'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow waitlist registrations'),
      '#default_value' => $config->get('registration_settings.allow_waitlist'),
      '#description' => $this->t('Allow participants to join a waitlist when events are full.'),
    ];
    
    $form['registration']['require_approval'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require registration approval'),
      '#default_value' => $config->get('registration_settings.require_approval'),
      '#description' => $this->t('Registrations must be approved by an administrator.'),
    ];
    
    $form['registration']['confirmation_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send confirmation emails'),
      '#default_value' => $config->get('registration_settings.confirmation_email'),
    ];
    
    $form['registration']['reminder_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send reminder emails'),
      '#default_value' => $config->get('registration_settings.reminder_email'),
    ];
    
    // Email templates
    $form['emails'] = [
      '#type' => 'details',
      '#title' => $this->t('Email Templates'),
      '#open' => FALSE,
    ];
    
    $email_types = [
      'registration_confirmation' => $this->t('Registration Confirmation'),
      'event_reminder' => $this->t('Event Reminder'),
      'waitlist_confirmation' => $this->t('Waitlist Confirmation'),
      'cancellation_notice' => $this->t('Cancellation Notice'),
      'follow_up' => $this->t('Follow-up Email'),
    ];
    
    foreach ($email_types as $key => $label) {
      $form['emails'][$key] = [
        '#type' => 'fieldset',
        '#title' => $label,
      ];
      
      $form['emails'][$key][$key . '_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable @type emails', ['@type' => $label]),
        '#default_value' => $config->get('email_templates.' . $key . '.enabled'),
      ];
      
      $form['emails'][$key][$key . '_subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#default_value' => $config->get('email_templates.' . $key . '.subject'),
        '#states' => [
          'visible' => [
            ':input[name="' . $key . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    
    // CiviCRM integration
    $form['civicrm'] = [
      '#type' => 'details',
      '#title' => $this->t('CiviCRM Integration'),
      '#open' => FALSE,
    ];
    
    $form['civicrm']['sync_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automatic synchronization'),
      '#default_value' => $config->get('civicrm.sync_enabled'),
      '#description' => $this->t('Automatically sync events between Drupal and CiviCRM.'),
    ];
    
    $form['civicrm']['sync_direction'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sync direction'),
      '#options' => [
        'drupal_to_civicrm' => $this->t('Drupal to CiviCRM'),
        'civicrm_to_drupal' => $this->t('CiviCRM to Drupal'),
        'bidirectional' => $this->t('Bidirectional'),
      ],
      '#default_value' => $config->get('civicrm.sync_direction') ?? 'drupal_to_civicrm',
      '#states' => [
        'visible' => [
          ':input[name="sync_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ilas_events.settings');
    
    // Save general settings
    $config->set('default_capacity', $form_state->getValue('default_capacity'));
    
    // Parse reminder days
    $reminder_days = array_map('trim', explode(',', $form_state->getValue('reminder_days')));
    $reminder_days = array_filter(array_map('intval', $reminder_days));
    $config->set('reminder_days', $reminder_days);
    
    // Save registration settings
    $config->set('registration_settings.allow_waitlist', $form_state->getValue('allow_waitlist'));
    $config->set('registration_settings.require_approval', $form_state->getValue('require_approval'));
    $config->set('registration_settings.confirmation_email', $form_state->getValue('confirmation_email'));
    $config->set('registration_settings.reminder_email', $form_state->getValue('reminder_email'));
    
    // Save email templates
    $email_types = ['registration_confirmation', 'event_reminder', 'waitlist_confirmation', 
                    'cancellation_notice', 'follow_up'];
    
    foreach ($email_types as $key) {
      $config->set('email_templates.' . $key . '.enabled', 
                   $form_state->getValue($key . '_enabled'));
      $config->set('email_templates.' . $key . '.subject', 
                   $form_state->getValue($key . '_subject'));
    }
    
    // Save CiviCRM settings
    $config->set('civicrm.sync_enabled', $form_state->getValue('sync_enabled'));
    $config->set('civicrm.sync_direction', $form_state->getValue('sync_direction'));
    
    $config->save();
    
    parent::submitForm($form, $form_state);
  }
}