/**
 * Event listing behaviors
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.ilasEventListing = {
    attach: function (context, settings) {
      // Initialize event filters
      $('.event-filters', context).once('event-filters').each(function () {
        var $filters = $(this);
        var $events = $('.event-item');
        
        // Event type filter
        $filters.find('#event-type-filter').on('change', function () {
          var selectedType = $(this).val();
          
          if (selectedType === 'all') {
            $events.show();
          } else {
            $events.hide();
            $events.filter('[data-event-type="' + selectedType + '"]').show();
          }
        });
        
        // Search filter
        $filters.find('#event-search').on('keyup', function () {
          var searchTerm = $(this).val().toLowerCase();
          
          $events.each(function () {
            var $event = $(this);
            var title = $event.find('.event-title').text().toLowerCase();
            var summary = $event.find('.event-summary').text().toLowerCase();
            
            if (title.includes(searchTerm) || summary.includes(searchTerm)) {
              $event.show();
            } else {
              $event.hide();
            }
          });
        });
      });
      
      // Add to calendar functionality
      $('.add-to-calendar', context).once('add-calendar').on('click', function (e) {
        e.preventDefault();
        
        var eventData = $(this).data();
        var calendarUrl = generateCalendarUrl(eventData);
        
        window.open(calendarUrl, '_blank');
      });
    }
  };
  
  /**
   * Generate calendar URL for different providers
   */
  function generateCalendarUrl(eventData) {
    var provider = eventData.provider || 'google';
    var baseUrl = '';
    var params = {};
    
    switch (provider) {
      case 'google':
        baseUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE';
        params = {
          text: eventData.title,
          dates: eventData.start + '/' + eventData.end,
          details: eventData.description,
          location: eventData.location
        };
        break;
        
      case 'outlook':
        baseUrl = 'https://outlook.live.com/calendar/0/deeplink/compose';
        params = {
          subject: eventData.title,
          startdt: eventData.start,
          enddt: eventData.end,
          body: eventData.description,
          location: eventData.location
        };
        break;
        
      case 'ical':
        // For iCal, we'd typically generate a .ics file
        return '/event/' + eventData.eventId + '/ical';
    }
    
    return baseUrl + '&' + $.param(params);
  }

})(jQuery, Drupal);