/**
 * @file
 * Debug version of resources filtering.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.ilasResourcesFilterDebug = {
    attach: function (context, settings) {
      console.log('Resources filter debug: Behavior attached');
      
      // Check if filter container exists
      const filterContainers = document.querySelectorAll('.resource-filters');
      console.log('Filter containers found:', filterContainers.length);
      
      // Check if resource cards exist
      const resourceCards = document.querySelectorAll('.resource-card');
      console.log('Resource cards found:', resourceCards.length);
      
      // Log data-topics attributes
      resourceCards.forEach(function(card, index) {
        console.log('Card ' + index + ' topics:', card.getAttribute('data-topics'));
      });
      
      // Check filter pills
      const pills = document.querySelectorAll('.resource-filters .pill-link');
      console.log('Filter pills found:', pills.length);
      
      pills.forEach(function(pill) {
        console.log('Pill:', pill.textContent, 'Filter:', pill.getAttribute('data-filter'));
      });
    }
  };

})(jQuery, Drupal, once);