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
        console.log('Initializing resource filters');
        
        // First, collect all unique topics from the cards
        const resourceCards = document.querySelectorAll('.resource-card');
        console.log('Found resource cards:', resourceCards.length);
        
        const topicsMap = new Map();
        
        resourceCards.forEach(function(card) {
          // Get the topics from the card body
          const topicElements = card.querySelectorAll('.resource-topics .topic-item');
          topicElements.forEach(function(topicEl) {
            const topicText = topicEl.textContent.trim();
            const dataTopics = card.getAttribute('data-topics');
            if (dataTopics) {
              // Extract individual topic IDs
              const topicIds = dataTopics.split(' ');
              topicIds.forEach(function(topicId) {
                if (topicId && !topicsMap.has(topicId)) {
                  // Try to match this topic ID with the topic text
                  topicsMap.set(topicId, topicText);
                }
              });
            }
          });
        });
        
        // Build the topic pills if we have topics
        if (topicsMap.size > 0) {
          const pillList = filterContainer.querySelector('.nav-pills');
          
          // Create a mapping of topic text to IDs for deduplication
          const textToId = new Map();
          const cardTopicData = [];
          
          resourceCards.forEach(function(card) {
            const topicElements = card.querySelectorAll('.resource-topics .topic-item');
            const dataTopics = card.getAttribute('data-topics') || '';
            const topicIds = dataTopics.split(' ').filter(id => id);
            
            console.log('Card data-topics:', dataTopics);
            console.log('Topic elements found:', topicElements.length);
            
            topicElements.forEach(function(topicEl, index) {
              const topicText = topicEl.textContent.trim();
              if (topicIds[index] && topicText) {
                console.log('Mapping topic:', topicText, 'to ID:', topicIds[index]);
                if (!textToId.has(topicText)) {
                  textToId.set(topicText, topicIds[index]);
                  cardTopicData.push({id: topicIds[index], text: topicText});
                }
              }
            });
          });
          
          // Sort topics alphabetically and add pills
          cardTopicData.sort((a, b) => a.text.localeCompare(b.text));
          
          cardTopicData.forEach(function(topic) {
            const li = document.createElement('li');
            li.className = 'nav-item';
            
            const button = document.createElement('button');
            button.className = 'nav-link pill-link';
            button.setAttribute('data-filter', topic.id);
            button.textContent = topic.text;
            
            console.log('Creating pill:', topic.text, 'with filter:', topic.id);
            
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
            console.log('Filter clicked:', filterValue);
            
            // Get all resource cards - they're inside column wrappers
            const resourceColumns = document.querySelectorAll('.resource-card-grid .col');
            console.log('Found columns:', resourceColumns.length);
            
            resourceColumns.forEach(function (column) {
              const card = column.querySelector('.resource-card');
              if (card) {
                const cardTopics = card.getAttribute('data-topics') || '';
                console.log('Card topics:', cardTopics, 'Filter value:', filterValue);
                
                if (filterValue === 'all') {
                  // Show all cards
                  column.style.display = '';
                  column.classList.add('d-flex'); // Ensure flex display is maintained
                } else {
                  // Check if card has this topic
                  if (cardTopics.split(' ').includes(filterValue)) {
                    column.style.display = '';
                    column.classList.add('d-flex'); // Ensure flex display is maintained
                  } else {
                    column.style.display = 'none';
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