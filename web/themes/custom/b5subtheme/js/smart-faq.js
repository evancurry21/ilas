(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.smartFaq = {
    attach: function (context, settings) {
      once('smartFaq', '.faq-smart-section', context).forEach(function (element) {
        const $section = $(element);
        const $searchInput = $section.find('.faq-search');
        const $searchButton = $section.find('[id$="-button"]');
        const $accordion = $section.find('.accordion');
        const $accordionItems = $section.find('.accordion-item');
        
        // Handle search input
        $searchButton.on('click', performSearch);
        $searchInput.on('keypress', function(e) {
          if (e.which === 13) {
            e.preventDefault();
            performSearch();
          }
        });
        
        function performSearch() {
          const searchTerm = $searchInput.val().trim();
          if (searchTerm.length < 2) return;
          
          // First search within FAQs
          let matches = 0;
          
          // Reset all accordions first
          $accordionItems.each(function() {
            const $item = $(this);
            const $button = $item.find('.accordion-button');
            const $collapse = $item.find('.accordion-collapse');
            
            if (!$button.hasClass('collapsed')) {
              $button.addClass('collapsed');
              $collapse.removeClass('show');
            }
          });
          
          // Then search and expand matches
          $accordionItems.each(function() {
            const $item = $(this);
            const questionText = $item.find('.accordion-button').text().toLowerCase();
            const answerText = $item.find('.accordion-body').text().toLowerCase();
            
            if (questionText.includes(searchTerm.toLowerCase()) || answerText.includes(searchTerm.toLowerCase())) {
              // Expand this item
              const $button = $item.find('.accordion-button');
              const $collapse = $item.find('.accordion-collapse');
              
              $button.removeClass('collapsed');
              $collapse.addClass('show');
              matches++;
              
              // Scroll to first match
              if (matches === 1) {
                $('html, body').animate({
                  scrollTop: $item.offset().top - 100
                }, 500);
              }
            }
          });
          
          // If no matches in accordion, redirect to your Views search page
          if (matches === 0) {
            // Make sure this matches your Views page path and exposed filter name
            window.location.href = '/site-search?keys=' + encodeURIComponent(searchTerm);
          }
        }
      });
    }
  };
})(jQuery, Drupal);