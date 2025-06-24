/**
 * @file
 * Resources functionality for filtering and navigation.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Resources filtering behavior.
   */
  Drupal.behaviors.ilasResourcesFilter = {
    attach: function (context, settings) {
      // Initialize topic filter pills
      once('resources-filter', '.resource-filters', context).forEach(function (filterContainer) {
        
        // First, collect all unique topics from the cards
        const resourceCards = document.querySelectorAll('.resource-card');
        
        // Create a map of unique topic text to all their IDs
        const topicTextToIds = new Map();
        
        resourceCards.forEach(function(card) {
          // Get the topics from the card body
          const topicElements = card.querySelectorAll('.resource-topics .topic-item');
          const dataTopics = card.getAttribute('data-topics');
          
          if (dataTopics) {
            const topicIds = dataTopics.split(' ');
            
            // Collect unique topic texts with their IDs
            topicElements.forEach(function(topicEl, index) {
              const topicText = topicEl.textContent.trim();
              if (topicIds[index] && topicText) {
                if (!topicTextToIds.has(topicText)) {
                  topicTextToIds.set(topicText, new Set());
                }
                topicTextToIds.get(topicText).add(topicIds[index]);
              }
            });
          }
        });
        
        // Build the topic pills if we have topics
        if (topicTextToIds.size > 0) {
          const pillList = filterContainer.querySelector('.nav-pills');
          
          // Convert the map to an array and sort alphabetically
          const sortedTopics = Array.from(topicTextToIds.keys()).sort();
          
          sortedTopics.forEach(function(topicText) {
            const li = document.createElement('li');
            li.className = 'nav-item';
            
            const button = document.createElement('button');
            button.className = 'nav-link pill-link';
            // Store all IDs for this topic text as a space-separated string
            const topicIds = Array.from(topicTextToIds.get(topicText)).join(' ');
            button.setAttribute('data-filter', topicIds);
            button.textContent = topicText;
            
            
            li.appendChild(button);
            pillList.appendChild(li);
          });
        }
        
        // Now set up click handlers for all pills (including dynamically created ones)
        const allPills = filterContainer.querySelectorAll('.pill-link');
        
        allPills.forEach(function (pill) {
          pill.addEventListener('click', function (e) {
            e.preventDefault();
            
            // Remove active class from all pills
            allPills.forEach(p => p.classList.remove('active'));
            
            // Add active class to clicked pill
            this.classList.add('active');
            
            // Get the filter value
            const filterValue = this.getAttribute('data-filter');
            
            // Get all resource cards - they're inside column wrappers
            // The template uses: <div class="row row-cols-1 row-cols-md-2 g-4 resource-card-grid">
            const resourceColumns = document.querySelectorAll('.resource-card-grid.row .col, .resource-card-grid .col');
            
            resourceColumns.forEach(function (column) {
              const card = column.querySelector('.resource-card');
              if (card) {
                const cardTopics = card.getAttribute('data-topics') || '';
                
                if (filterValue === 'all') {
                  // Show all cards
                  column.style.display = '';
                  column.classList.remove('d-none');
                  column.classList.add('d-flex'); // Ensure flex display is maintained
                } else {
                  // Check if card has any of the filter topic IDs
                  const filterTopicIds = filterValue.split(' ');
                  const cardTopicIds = cardTopics.split(' ');
                  
                  // Check if any of the filter IDs match any of the card IDs
                  const hasMatchingTopic = filterTopicIds.some(filterId => 
                    cardTopicIds.includes(filterId)
                  );
                  
                  
                  if (hasMatchingTopic) {
                    column.style.display = '';
                    column.classList.remove('d-none');
                    column.classList.add('d-flex'); // Ensure flex display is maintained
                  } else {
                    column.style.display = 'none';
                    column.classList.add('d-none');
                    column.classList.remove('d-flex');
                  }
                }
              }
            });
          });
        });
      });

      // Initialize resource card interactions
      once('resource-cards', '.resource-card', context).forEach(function (card) {
        // Add hover effect
        card.addEventListener('mouseenter', function () {
          this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function () {
          this.style.transform = '';
        });
        
        // Make entire card clickable
        card.addEventListener('click', function (e) {
          // Don't trigger if clicking on a link
          if (e.target.tagName !== 'A' && !e.target.closest('a')) {
            const link = this.querySelector('.resource-actions a');
            if (link) {
              link.click();
            }
          }
        });
      });
    }
  };

})(jQuery, Drupal, once);