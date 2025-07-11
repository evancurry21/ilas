<?php

namespace Drupal\ilas_chatbot\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a 'ChatbotBlock' block.
 *
 * @Block(
 *  id = "chatbot_block",
 *  admin_label = @Translation("ILAS Chatbot"),
 *  category = @Translation("Custom"),
 * )
 */
class ChatbotBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'override_global_settings' => FALSE,
      'agent_id' => '',
      'language_code' => '',
      'welcome_intent' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('ilas_chatbot.settings');

    $form['info'] = [
      '#type' => 'item',
      '#markup' => $this->t('This block displays the ILAS chatbot. Global settings can be configured at <a href="@url">ILAS Chatbot Settings</a>.', [
        '@url' => '/admin/config/services/ilas-chatbot'
      ]),
    ];

    $form['override_global_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override global settings'),
      '#description' => $this->t('Use custom settings for this block instead of the global configuration.'),
      '#default_value' => $this->configuration['override_global_settings'],
    ];

    $form['block_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Block-specific Settings'),
      '#states' => [
        'visible' => [
          ':input[name="settings[override_global_settings]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['block_settings']['agent_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dialogflow Agent ID'),
      '#description' => $this->t('Override the global agent ID for this block.'),
      '#default_value' => $this->configuration['agent_id'],
      '#maxlength' => 36,
      '#states' => [
        'required' => [
          ':input[name="settings[override_global_settings]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['block_settings']['language_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Language Code'),
      '#options' => [
        '' => $this->t('- Use global setting -'),
        'en' => $this->t('English'),
        'es' => $this->t('Spanish'),
        'fr' => $this->t('French'),
      ],
      '#default_value' => $this->configuration['language_code'],
    ];

    $form['block_settings']['welcome_intent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Welcome Intent'),
      '#description' => $this->t('Override the global welcome intent for this block.'),
      '#default_value' => $this->configuration['welcome_intent'],
      '#maxlength' => 100,
    ];

    // Display current global settings
    $form['current_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Current Global Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['current_settings']['current_agent_id'] = [
      '#type' => 'item',
      '#title' => $this->t('Global Agent ID'),
      '#markup' => $config->get('agent_id') ?: $this->t('Not configured'),
    ];

    $form['current_settings']['current_language'] = [
      '#type' => 'item',
      '#title' => $this->t('Global Language'),
      '#markup' => $config->get('language_code') ?: 'en',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    if ($form_state->getValue('override_global_settings')) {
      $agent_id = $form_state->getValue(['block_settings', 'agent_id']);
      if ($agent_id && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $agent_id)) {
        $form_state->setErrorByName('block_settings][agent_id', $this->t('Agent ID must be a valid UUID format.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['override_global_settings'] = $form_state->getValue('override_global_settings');
    $this->configuration['agent_id'] = $form_state->getValue(['block_settings', 'agent_id']);
    $this->configuration['language_code'] = $form_state->getValue(['block_settings', 'language_code']);
    $this->configuration['welcome_intent'] = $form_state->getValue(['block_settings', 'welcome_intent']);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $global_config = $this->configFactory->get('ilas_chatbot.settings');
    
    // Use block-specific settings if override is enabled, otherwise use global
    if ($this->configuration['override_global_settings']) {
      $agent_id = $this->configuration['agent_id'];
      $language_code = $this->configuration['language_code'] ?: $global_config->get('language_code');
      $welcome_intent = $this->configuration['welcome_intent'] ?: $global_config->get('welcome_intent');
      $form_mappings = $global_config->get('form_mappings'); // Always use global form mappings
    } else {
      $agent_id = $global_config->get('agent_id');
      $language_code = $global_config->get('language_code');
      $welcome_intent = $global_config->get('welcome_intent');
      $form_mappings = $global_config->get('form_mappings');
    }

    // Don't render if no agent ID is configured
    if (!$agent_id) {
      return [
        '#markup' => $this->t('<div class="messages messages--warning">ILAS Chatbot: No agent ID configured. Please configure the chatbot in the <a href="@url">settings</a>.</div>', [
          '@url' => '/admin/config/services/ilas-chatbot'
        ]),
        '#cache' => [
          'tags' => ['config:ilas_chatbot.settings'],
        ],
      ];
    }
    
    $build = [];
    
    // Add settings for JavaScript
    $build['#attached']['drupalSettings']['ilasChatbot'] = [
      'agentId' => $agent_id,
      'languageCode' => $language_code ?: 'en',
      'welcomeIntent' => $welcome_intent ?: 'WELCOME',
      'formMappings' => $form_mappings ?: [],
      'trustedDomains' => $global_config->get('trusted_domains') ?: [],
      'enableAnalytics' => $global_config->get('enable_analytics') ?: TRUE,
    ];
    
    // Attach library
    $build['#attached']['library'][] = 'ilas_chatbot/chatbot';
    
    // The actual chatbot is rendered via JavaScript
    $build['#markup'] = '<div id="ilas-chatbot-root" data-block-instance="' . $this->getPluginId() . '"></div>';
    
    // Add cache tags
    $build['#cache'] = [
      'tags' => ['config:ilas_chatbot.settings'],
      'contexts' => ['url.path', 'user.permissions'],
    ];
    
    return $build;
  }

}