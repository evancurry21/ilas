<?php

namespace Drupal\ilas_resources\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Filters resources to those that have at least one topic mapped to the service area.
 *
 * @ViewsFilter("strict_topic_service_area")
 */
class StrictTopicServiceArea extends FilterPluginBase {

  public function adminSummary() {
    return $this->t('Strict Topic/Service Area Filter');
  }

  public function query() {
    // We'll do all filtering in the postExecute handler.
    // So, no SQL filtering here.
  }

  /**
   * Implements postExecute filter.
   */
  public function postExecute(&$result) {
    if (empty($this->view->args[0])) {
      return;
    }
    $service_tid = $this->view->args[0]; // expects Service Area TID as first argument

    foreach ($result as $key => $row) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $row->_entity ?? NULL;
      if (!$node || !$node->hasField('field_topics')) {
        unset($result[$key]);
        continue;
      }
      $topic_tids = array_column($node->get('field_topics')->getValue(), 'target_id');
      $match = FALSE;
      foreach ($topic_tids as $tid) {
        $topic = \Drupal\taxonomy\Entity\Term::load($tid);
        if ($topic && $topic->hasField('field_service_areas')) {
          $mapped_service_areas = array_column($topic->get('field_service_areas')->getValue(), 'target_id');
          if (in_array($service_tid, $mapped_service_areas)) {
            $match = TRUE;
            break;
          }
        }
      }
      if (!$match) {
        unset($result[$key]);
      }
    }
    // Reindex the result array
    $result = array_values($result);
  }

  public function buildExposeForm(&$form, FormStateInterface $form_state) { }
  public function acceptExposedInput($input) { return TRUE; }
}