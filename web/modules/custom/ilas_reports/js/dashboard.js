/**
 * Dashboard behaviors
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.ilasReportsDashboard = {
    attach: function (context, settings) {
      var $dashboard = $('.report-dashboard', context).once('dashboard-init');
      
      if ($dashboard.length) {
        // Initialize dashboard
        this.initDashboard($dashboard, settings);
        
        // Refresh button
        $('#refresh-dashboard', context).once('refresh-btn').on('click', function () {
          Drupal.behaviors.ilasReportsDashboard.refreshDashboard($dashboard);
        });
        
        // Customize button
        $('#customize-dashboard', context).once('customize-btn').on('click', function () {
          Drupal.behaviors.ilasReportsDashboard.openCustomizeDialog();
        });
        
        // Auto-refresh every 5 minutes
        setInterval(function () {
          Drupal.behaviors.ilasReportsDashboard.refreshDashboard($dashboard);
        }, 300000);
      }
    },
    
    initDashboard: function ($dashboard, settings) {
      // Initialize charts
      if (settings.ilasReports && settings.ilasReports.dashboard) {
        var widgets = settings.ilasReports.dashboard.widgets;
        
        widgets.forEach(function (widget) {
          if (widget.type === 'chart' && widget.chart_data) {
            Drupal.behaviors.ilasReportsDashboard.initChart(widget);
          }
        });
      }
      
      // Initialize tooltips
      $('[data-toggle="tooltip"]', $dashboard).tooltip();
      
      // Make widgets sortable (if customization is enabled)
      if ($.fn.sortable) {
        $('.dashboard-widgets').sortable({
          handle: '.widget-header',
          placeholder: 'widget-placeholder',
          update: function (event, ui) {
            Drupal.behaviors.ilasReportsDashboard.saveWidgetOrder();
          }
        });
      }
    },
    
    initChart: function (widget) {
      var canvas = document.getElementById('chart-' + widget.id);
      if (!canvas) return;
      
      var ctx = canvas.getContext('2d');
      var chartConfig = {
        type: widget.chart_data.type || 'line',
        data: widget.chart_data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: widget.chart_data.type !== 'line',
              position: 'bottom'
            },
            title: {
              display: false
            }
          }
        }
      };
      
      // Store chart instance
      if (!window.ilasCharts) {
        window.ilasCharts = {};
      }
      window.ilasCharts[widget.id] = new Chart(ctx, chartConfig);
    },
    
    refreshDashboard: function ($dashboard) {
      $dashboard.addClass('dashboard-loading');
      
      $.ajax({
        url: '/api/reports/dashboard',
        type: 'GET',
        dataType: 'json',
        success: function (data) {
          // Update widget values
          data.widgets.forEach(function (widget) {
            Drupal.behaviors.ilasReportsDashboard.updateWidget(widget);
          });
          
          // Update last refresh time
          $('#last-update-time').text(new Date().toLocaleTimeString());
          
          $dashboard.removeClass('dashboard-loading');
        },
        error: function () {
          $dashboard.removeClass('dashboard-loading');
          Drupal.message('Failed to refresh dashboard', 'error');
        }
      });
    },
    
    updateWidget: function (widget) {
      var $widget = $('[data-widget-id="' + widget.id + '"]');
      if (!$widget.length) return;
      
      // Update metric value
      $widget.find('.metric-value').text(widget.value);
      
      // Update change indicator
      if (widget.change !== null) {
        var $change = $widget.find('.metric-change');
        $change.removeClass('up down').addClass(widget.trend);
        $change.find('.change-value').text(widget.change);
        $change.find('.change-percent').text('(' + widget.change_percent + '%)');
      }
      
      // Update chart if needed
      if (widget.type === 'chart' && widget.chart_data && window.ilasCharts[widget.id]) {
        window.ilasCharts[widget.id].data = widget.chart_data;
        window.ilasCharts[widget.id].update();
      }
    },
    
    openCustomizeDialog: function () {
      // Open dialog for dashboard customization
      var $dialog = $('<div>').dialog({
        title: Drupal.t('Customize Dashboard'),
        width: 600,
        height: 400,
        modal: true,
        buttons: {
          'Save': function () {
            Drupal.behaviors.ilasReportsDashboard.saveCustomization();
            $(this).dialog('close');
          },
          'Cancel': function () {
            $(this).dialog('close');
          }
        }
      });
      
      // Load customization form
      $dialog.load('/admin/reports/dashboard/customize');
    },
    
    saveWidgetOrder: function () {
      var order = [];
      $('.dashboard-widget').each(function () {
        order.push($(this).data('widget-id'));
      });
      
      $.ajax({
        url: '/admin/reports/dashboard/save-order',
        type: 'POST',
        data: {
          order: order
        },
        headers: {
          'X-CSRF-Token': drupalSettings.csrf_token
        }
      });
    },
    
    saveCustomization: function () {
      // Save customization preferences
      var preferences = {
        widgets: [],
        refresh_interval: $('#refresh-interval').val()
      };
      
      $('input[name="widgets[]"]:checked').each(function () {
        preferences.widgets.push($(this).val());
      });
      
      $.ajax({
        url: '/admin/reports/dashboard/save-preferences',
        type: 'POST',
        data: preferences,
        headers: {
          'X-CSRF-Token': drupalSettings.csrf_token
        },
        success: function () {
          location.reload();
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings);