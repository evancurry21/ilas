<?php

/*
 * Copyright (c) 2003-2025, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Base class for ckeditor5_ai_provider plugins.
 */
abstract class CKEditor5AiProviderPluginBase extends PluginBase implements CKEditor5AiProviderInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateFields(FormStateInterface &$form_state): void {
  }

}
