<?php

namespace Drupal\ilas_resources\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * @ViewsArgument("current_service_tid")
 *
 * Supplies the first Service‑Area term ID attached to the page being viewed.
 */
class CurrentServiceTid extends ArgumentPluginBase {

  public function getArgument() {
    $route_match = \Drupal::routeMatch();
    $node = $route_match->getParameter('node');
    if ($node instanceof NodeInterface && $node->hasField('field_service_area')) {
      $terms = $node->get('field_service_area')->referencedEntities();
      return $terms ? $terms[0]->id() : NULL;
    }
    return NULL;
  }

  public function summaryName($data) {
    return $this->t('Current service TID');
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }
  
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);
  }
}