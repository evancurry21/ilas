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
      'agent_id' => '',
      'language_code' => 'en',
      'welcome_intent' => 'WELCOME',
      'form_mappings' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['agent_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dialogflow Agent ID'),
      '#description' => $this->t('The unique identifier for your Dialogflow agent.'),
      '#default_value' => $this->configuration['agent_id'],
      '#required' => TRUE,
    ];

    $form['language_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Language Code'),
      '#options' => [
        'en' => $this->t('English'),
        'es' => $this->t('Spanish'),
        'fr' => $this->t('French'),
      ],
      '#default_value' => $this->configuration['language_code'],
    ];

    $form['welcome_intent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Welcome Intent'),
      '#description' => $this->t('The intent to trigger when the chat opens.'),
      '#default_value' => $this->configuration['welcome_intent'],
    ];

    $form['form_mappings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Form Mappings'),
      '#description' => $this->t('JSON mapping of form types to URLs. Example: {"eviction": "/form/eviction", "divorce": "/form/divorce"}'),
      '#default_value' => json_encode($this->configuration['form_mappings']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['agent_id'] = $form_state->getValue('agent_id');
    $this->configuration['language_code'] = $form_state->getValue('language_code');
    $this->configuration['welcome_intent'] = $form_state->getValue('welcome_intent');
    $this->configuration['form_mappings'] = json_decode($form_state->getValue('form_mappings'), TRUE) ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    
    // Add settings for JavaScript
    $build['#attached']['drupalSettings']['ilasChatbot'] = [
      'agentId' => $this->configuration['agent_id'],
      'languageCode' => $this->configuration['language_code'],
      'welcomeIntent' => $this->configuration['welcome_intent'],
      'formMappings' => $this->configuration['form_mappings'],
    ];
    
    // Attach library
    $build['#attached']['library'][] = 'ilas_chatbot/chatbot';
    
    // The actual chatbot is rendered via JavaScript
    $build['#markup'] = '<div id="ilas-chatbot-root"></div>';
    
    return $build;
  }

}