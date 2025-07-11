<?php

namespace Drupal\ilas_reports\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure reports settings.
 */
class ReportsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ilas_reports_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ilas_reports.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ilas_reports.settings');
    
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Settings'),
      '#open' => TRUE,
    ];
    
    $form['general']['cache_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache duration (minutes)'),
      '#default_value' => $config->get('cache_duration') ?? 60,
      '#min' => 0,
      '#description' => $this->t('How long to cache report data. Set to 0 to disable caching.'),
    ];
    
    $form['general']['date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date format'),
      '#default_value' => $config->get('date_format') ?? 'F j, Y',
      '#description' => $this->t('PHP date format for report dates.'),
    ];
    
    $form['dashboard'] = [
      '#type' => 'details',
      '#title' => $this->t('Dashboard Settings'),
      '#open' => TRUE,
    ];
    
    $form['dashboard']['refresh_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Dashboard refresh interval (seconds)'),
      '#default_value' => $config->get('dashboard.refresh_interval') ?? 300,
      '#min' => 60,
      '#description' => $this->t('How often to automatically refresh dashboard data.'),
    ];
    
    $form['dashboard']['default_widgets'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Default dashboard widgets'),
      '#options' => $this->getAvailableWidgets(),
      '#default_value' => $config->get('dashboard.default_widgets') ?? [],
      '#description' => $this->t('Select which widgets to show by default on new dashboards.'),
    ];
    
    $form['export'] = [
      '#type' => 'details',
      '#title' => $this->t('Export Settings'),
      '#open' => FALSE,
    ];
    
    $form['export']['pdf_header'] = [
      '#type' => 'textarea',
      '#title' => $this->t('PDF header text'),
      '#default_value' => $config->get('export.pdf_header') ?? '',
      '#description' => $this->t('Text to include in PDF report headers.'),
    ];
    
    $form['export']['pdf_footer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('PDF footer text'),
      '#default_value' => $config->get('export.pdf_footer') ?? '',
      '#description' => $this->t('Text to include in PDF report footers.'),
    ];
    
    $form['export']['excel_template'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Excel template'),
      '#upload_location' => 'private://reports/templates',
      '#upload_validators' => [
        'file_validate_extensions' => ['xlsx'],
      ],
      '#default_value' => $config->get('export.excel_template'),
      '#description' => $this->t('Upload a template file for Excel exports.'),
    ];
    
    $form['scheduled'] = [
      '#type' => 'details',
      '#title' => $this->t('Scheduled Reports'),
      '#open' => FALSE,
    ];
    
    $form['scheduled']['email_from'] = [
      '#type' => 'email',
      '#title' => $this->t('From email address'),
      '#default_value' => $config->get('scheduled.email_from') ?? \Drupal::config('system.site')->get('mail'),
      '#description' => $this->t('Email address to send scheduled reports from.'),
    ];
    
    $form['scheduled']['email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email subject template'),
      '#default_value' => $config->get('scheduled.email_subject') ?? '[report:name] - [site:name]',
      '#description' => $this->t('Subject line for scheduled report emails. Tokens are supported.'),
    ];
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ilas_reports.settings');
    
    $config->set('cache_duration', $form_state->getValue('cache_duration'));
    $config->set('date_format', $form_state->getValue('date_format'));
    
    $config->set('dashboard.refresh_interval', $form_state->getValue('refresh_interval'));
    $config->set('dashboard.default_widgets', array_filter($form_state->getValue('default_widgets')));
    
    $config->set('export.pdf_header', $form_state->getValue('pdf_header'));
    $config->set('export.pdf_footer', $form_state->getValue('pdf_footer'));
    $config->set('export.excel_template', $form_state->getValue('excel_template'));
    
    $config->set('scheduled.email_from', $form_state->getValue('email_from'));
    $config->set('scheduled.email_subject', $form_state->getValue('email_subject'));
    
    $config->save();
    
    parent::submitForm($form, $form_state);
  }

  /**
   * Get available widgets.
   */
  protected function getAvailableWidgets() {
    return [
      'total_clients_served' => $this->t('Total Clients Served'),
      'active_cases' => $this->t('Active Cases'),
      'cases_closed_success' => $this->t('Successful Case Closures'),
      'total_donations_ytd' => $this->t('Donations YTD'),
      'volunteer_hours_month' => $this->t('Volunteer Hours'),
      'upcoming_events' => $this->t('Upcoming Events'),
      'grant_utilization' => $this->t('Grant Utilization'),
    ];
  }
}