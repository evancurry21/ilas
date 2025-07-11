<?php

namespace Drupal\ilas_reports\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for scheduled reports.
 */
class ScheduledReportsController extends ControllerBase {

  /**
   * Overview of scheduled reports.
   */
  public function overview() {
    $build = [
      '#markup' => $this->t('Scheduled reports management coming soon.'),
    ];
    
    // Add create link
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['action-links']],
      'add' => [
        '#type' => 'link',
        '#title' => $this->t('Add Scheduled Report'),
        '#url' => \Drupal\Core\Url::fromRoute('ilas_reports.scheduled.add'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];
    
    return $build;
  }
}