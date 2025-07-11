<?php

namespace Drupal\ilas_events\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for editing events.
 */
class EventEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilas_events_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $event_id = NULL) {
    // Find node for this event
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'event')
      ->condition('field_civicrm_event_id', $event_id)
      ->accessCheck(TRUE);
    $nids = $query->execute();
    
    if (!empty($nids)) {
      $nid = reset($nids);
      $form['info'] = [
        '#markup' => '<p>' . $this->t('To edit this event, please use the <a href="@url">node edit form</a>.', [
          '@url' => '/node/' . $nid . '/edit',
        ]) . '</p>',
      ];
    }
    else {
      $form['info'] = [
        '#markup' => '<p>' . $this->t('This event is not synced with a Drupal node. Please edit it directly in CiviCRM.') . '</p>',
      ];
    }
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Not used
  }
}