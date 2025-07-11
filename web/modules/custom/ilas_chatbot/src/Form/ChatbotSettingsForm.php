<?php

namespace Drupal\ilas_chatbot\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure ILAS Chatbot settings.
 */
class ChatbotSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ilas_chatbot.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilas_chatbot_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ilas_chatbot.settings');

    $form['basic'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic Configuration'),
      '#collapsible' => FALSE,
    ];

    $form['basic']['agent_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dialogflow Agent ID'),
      '#description' => $this->t('The unique identifier for your Dialogflow agent (UUID format).'),
      '#default_value' => $config->get('agent_id'),
      '#required' => TRUE,
      '#maxlength' => 36,
      '#pattern' => '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}',
    ];

    $form['basic']['language_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Language Code'),
      '#description' => $this->t('The primary language for the chatbot interface.'),
      '#options' => [
        'en' => $this->t('English'),
        'es' => $this->t('Spanish'),
        'fr' => $this->t('French'),
        'de' => $this->t('German'),
        'it' => $this->t('Italian'),
        'pt' => $this->t('Portuguese'),
        'ja' => $this->t('Japanese'),
        'ko' => $this->t('Korean'),
        'zh' => $this->t('Chinese'),
      ],
      '#default_value' => $config->get('language_code') ?: 'en',
    ];

    $form['basic']['welcome_intent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Welcome Intent'),
      '#description' => $this->t('The intent to trigger when the chat opens.'),
      '#default_value' => $config->get('welcome_intent') ?: 'WELCOME',
      '#maxlength' => 100,
    ];

    $form['deployment'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Deployment Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['deployment']['load_globally'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load chatbot globally'),
      '#description' => $this->t('If checked, the chatbot will be loaded on all pages. Otherwise, use the chatbot block to place it selectively.'),
      '#default_value' => $config->get('load_globally'),
    ];

    $form['deployment']['enable_analytics'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable analytics tracking'),
      '#description' => $this->t('Track chatbot interactions for analytics (requires Google Analytics or compatible tracking).'),
      '#default_value' => $config->get('enable_analytics') ?? TRUE,
    ];

    $form['forms'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Form Integration'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['forms']['form_mappings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Form Mappings'),
      '#description' => $this->t('JSON mapping of form types to URLs. Example: {"eviction": "/webform/eviction_assistance", "divorce": "/webform/divorce_custody"}'),
      '#default_value' => json_encode($config->get('form_mappings') ?: [], JSON_PRETTY_PRINT),
      '#rows' => 8,
    ];

    $form['forms']['trusted_domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Trusted Domains'),
      '#description' => $this->t('One domain per line. Forms will only load from these domains for security.'),
      '#default_value' => implode("\n", $config->get('trusted_domains') ?: []),
      '#rows' => 4,
    ];

    $form['forms']['form_titles'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Form Titles'),
      '#description' => $this->t('JSON mapping of form types to titles. Example: {"eviction": "Eviction Assistance Form", "divorce": "Divorce and Custody Form"}'),
      '#default_value' => json_encode($config->get('form_titles') ?: [], JSON_PRETTY_PRINT),
      '#rows' => 6,
    ];

    $form['forms']['form_descriptions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Form Descriptions'),
      '#description' => $this->t('JSON mapping of form types to descriptions. Example: {"eviction": "Get help with eviction proceedings"}'),
      '#default_value' => json_encode($config->get('form_descriptions') ?: [], JSON_PRETTY_PRINT),
      '#rows' => 6,
    ];

    $form['forms']['legal_categories'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Legal Categories'),
      '#description' => $this->t('One category per line. These appear as quick reply options in the chatbot.'),
      '#default_value' => implode("\n", $config->get('legal_categories') ?: ['Eviction Help', 'Divorce/Custody', 'Benefits Appeal', 'Small Claims']),
      '#rows' => 6,
    ];

    $form['advanced'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['advanced']['css_override'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom CSS'),
      '#description' => $this->t('Additional CSS to customize the chatbot appearance.'),
      '#default_value' => $config->get('css_override'),
      '#rows' => 10,
    ];

    $form['security'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Webhook Security'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description' => $this->t('Configure security for the Dialogflow webhook endpoint.'),
    ];

    $form['security']['webhook_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webhook Secret Token'),
      '#description' => $this->t('A secret token that Dialogflow must include in the Authorization header. Leave empty to disable token-based authentication.'),
      '#default_value' => $config->get('webhook_secret'),
      '#maxlength' => 255,
    ];

    $form['security']['webhook_allowed_ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed IP Addresses'),
      '#description' => $this->t('One IP address per line. Only requests from these IPs will be accepted. Leave empty to disable IP-based authentication.'),
      '#default_value' => implode("\n", $config->get('webhook_allowed_ips') ?: []),
      '#rows' => 4,
    ];

    $form['security']['security_note'] = [
      '#markup' => '<div class="messages messages--warning">' . 
        $this->t('Important: You must configure at least one authentication method (secret token or IP whitelist) to secure your webhook endpoint.') . 
        '</div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate agent ID format
    $agent_id = $form_state->getValue('agent_id');
    if ($agent_id && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $agent_id)) {
      $form_state->setErrorByName('agent_id', $this->t('Agent ID must be a valid UUID format.'));
    }

    // Validate JSON format for form mappings
    $form_mappings = $form_state->getValue('form_mappings');
    if ($form_mappings) {
      $decoded = json_decode($form_mappings, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $form_state->setErrorByName('form_mappings', $this->t('Form mappings must be valid JSON.'));
      } else {
        // Validate URLs in form mappings
        foreach ($decoded as $type => $url) {
          if (!filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, '/')) {
            $form_state->setErrorByName('form_mappings', $this->t('Invalid URL for form mapping "@type": @url', ['@type' => $type, '@url' => $url]));
          }
        }
      }
    }

    // Validate trusted domains
    $trusted_domains = $form_state->getValue('trusted_domains');
    if ($trusted_domains) {
      $domains = array_filter(array_map('trim', explode("\n", $trusted_domains)));
      foreach ($domains as $domain) {
        if (!filter_var('https://' . $domain, FILTER_VALIDATE_URL)) {
          $form_state->setErrorByName('trusted_domains', $this->t('Invalid domain: @domain', ['@domain' => $domain]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ilas_chatbot.settings');

    // Process form mappings
    $form_mappings = $form_state->getValue('form_mappings');
    $form_mappings_array = $form_mappings ? json_decode($form_mappings, TRUE) : [];

    // Process trusted domains
    $trusted_domains = $form_state->getValue('trusted_domains');
    $trusted_domains_array = $trusted_domains ? array_filter(array_map('trim', explode("\n", $trusted_domains))) : [];

    // Process allowed IPs
    $webhook_allowed_ips = $form_state->getValue('webhook_allowed_ips');
    $webhook_allowed_ips_array = $webhook_allowed_ips ? array_filter(array_map('trim', explode("\n", $webhook_allowed_ips))) : [];

    // Process form titles
    $form_titles = $form_state->getValue('form_titles');
    $form_titles_array = $form_titles ? json_decode($form_titles, TRUE) : [];

    // Process form descriptions
    $form_descriptions = $form_state->getValue('form_descriptions');
    $form_descriptions_array = $form_descriptions ? json_decode($form_descriptions, TRUE) : [];

    // Process legal categories
    $legal_categories = $form_state->getValue('legal_categories');
    $legal_categories_array = $legal_categories ? array_filter(array_map('trim', explode("\n", $legal_categories))) : [];

    $config
      ->set('agent_id', $form_state->getValue('agent_id'))
      ->set('language_code', $form_state->getValue('language_code'))
      ->set('welcome_intent', $form_state->getValue('welcome_intent'))
      ->set('load_globally', $form_state->getValue('load_globally'))
      ->set('enable_analytics', $form_state->getValue('enable_analytics'))
      ->set('form_mappings', $form_mappings_array)
      ->set('trusted_domains', $trusted_domains_array)
      ->set('form_titles', $form_titles_array)
      ->set('form_descriptions', $form_descriptions_array)
      ->set('legal_categories', $legal_categories_array)
      ->set('css_override', $form_state->getValue('css_override'))
      ->set('webhook_secret', $form_state->getValue('webhook_secret'))
      ->set('webhook_allowed_ips', $webhook_allowed_ips_array)
      ->save();

    // Clear cache to ensure new settings take effect
    drupal_flush_all_caches();

    parent::submitForm($form, $form_state);
  }

} 