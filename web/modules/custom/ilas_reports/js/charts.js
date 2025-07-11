/**
 * Chart initialization and helpers
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.ilasReportsCharts = {
    attach: function (context, settings) {
      // Initialize any charts in the context
      $('.report-chart', context).once('chart-init').each(function () {
        var $canvas = $(this);
        var chartId = $canvas.attr('id');
        var chartData = settings.ilasReports && settings.ilasReports.charts 
          ? settings.ilasReports.charts[chartId] : null;
        
        if (chartData) {
          Drupal.behaviors.ilasReportsCharts.createChart($canvas[0], chartData);
        }
      });
    },
    
    createChart: function (canvas, config) {
      if (!canvas || !config) return;
      
      var ctx = canvas.getContext('2d');
      
      // Default options
      var defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: config.type !== 'line',
            position: 'bottom'
          },
          tooltip: {
            mode: 'index',
            intersect: false
          }
        },
        scales: config.type === 'line' || config.type === 'bar' ? {
          y: {
            beginAtZero: true
          }
        } : undefined
      };
      
      // Merge with provided options
      var options = $.extend(true, {}, defaultOptions, config.options || {});
      
      // Create chart
      return new Chart(ctx, {
        type: config.type || 'bar',
        data: config.data,
        options: options
      });
    },
    
    updateChart: function (chart, newData) {
      if (!chart) return;
      
      chart.data = newData;
      chart.update();
    },
    
    exportChart: function (chart, filename) {
      if (!chart) return;
      
      // Convert to image
      var url = chart.toBase64Image();
      
      // Create download link
      var link = document.createElement('a');
      link.download = filename || 'chart.png';
      link.href = url;
      link.click();
    }
  };

  // Chart color schemes
  Drupal.ilasReports = Drupal.ilasReports || {};
  Drupal.ilasReports.chartColors = {
    primary: '#004080',
    success: '#28a745',
    info: '#17a2b8',
    warning: '#ffc107',
    danger: '#dc3545',
    secondary: '#6c757d',
    
    // Chart color palette
    palette: [
      '#004080',
      '#28a745',
      '#ffc107',
      '#dc3545',
      '#6f42c1',
      '#17a2b8',
      '#fd7e14',
      '#20c997',
      '#e83e8c',
      '#6c757d'
    ]
  };

})(jQuery, Drupal);