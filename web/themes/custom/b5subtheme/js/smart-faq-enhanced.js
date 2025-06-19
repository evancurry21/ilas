/**
 * Smart FAQ Enhanced Component
 * Provides advanced FAQ functionality with search, filtering, and accessibility
 */
(function ($, Drupal, once) {
  'use strict';

  // Configuration constants
  const CONFIG = {
    SEARCH_DEBOUNCE_MS: 300,
    MIN_SEARCH_LENGTH: 2,
    ANIMATION_DURATION: 300,
    FOCUS_VISIBLE_CLASS: 'focus-visible'
  };

  // Cache for DOM elements per FAQ instance
  const instanceCache = new WeakMap();

  Drupal.behaviors.smartFaqEnhanced = {
    attach: function (context, settings) {
      once('smart-faq-enhanced', '.faq-smart-section', context).forEach(element => {
        initializeFAQ(element);
      });
    }
  };

  /**
   * Initialize a single FAQ instance
   */
  function initializeFAQ(element) {
    const $section = $(element);
    const instance = {
      $section: $section,
      $searchInput: $section.find('.faq-search'),
      $searchButton: $section.find('[id$="-button"]'),
      $searchResults: $section.find('[id$="-results"]'),
      $statusArea: $section.find('[id$="-status"]'),
      $accordion: $section.find('.accordion'),
      $accordionItems: $section.find('.accordion-item'),
      $filterButtons: $section.find('.faq-filters .filter-btn'),
      searchTimeout: null,
      currentFilter: 'all'
    };

    // Store instance for later use
    instanceCache.set(element, instance);

    // Set up all functionality
    setupSearch(instance);
    setupFilters(instance);
    setupKeyboardNavigation(instance);
    setupAccessibility(instance);
    addLoadingStates(instance);
  }

  /**
   * Set up search functionality with debouncing
   */
  function setupSearch(instance) {
    const { $searchInput, $searchButton, searchTimeout } = instance;

    // Debounced search on input
    $searchInput.on('input', function() {
      clearTimeout(instance.searchTimeout);
      const searchTerm = $(this).val().trim();
      
      if (searchTerm.length >= CONFIG.MIN_SEARCH_LENGTH) {
        instance.searchTimeout = setTimeout(() => {
          performSearch(instance, searchTerm);
        }, CONFIG.SEARCH_DEBOUNCE_MS);
      } else {
        clearSearch(instance);
      }
    });

    // Immediate search on button click
    $searchButton.on('click', function() {
      const searchTerm = $searchInput.val().trim();
      if (searchTerm.length >= CONFIG.MIN_SEARCH_LENGTH) {
        performSearch(instance, searchTerm);
      }
    });

    // Enter key submits search
    $searchInput.on('keypress', function(e) {
      if (e.which === 13) {
        e.preventDefault();
        $searchButton.click();
      }
    });
  }

  /**
   * Perform search with loading states and announcements
   */
  function performSearch(instance, searchTerm) {
    const { $accordionItems, $statusArea, $searchResults } = instance;
    
    // Show loading state
    showLoading(instance);
    
    // Announce search start
    $statusArea.text(`Searching for "${searchTerm}"...`);

    // Simulate async search (in real implementation, this might be an API call)
    setTimeout(() => {
      let matches = 0;
      const results = [];

      // Reset all accordions first
      collapseAll(instance);

      // Search through FAQ items
      $accordionItems.each(function() {
        const $item = $(this);
        const $button = $item.find('.accordion-button');
        const $body = $item.find('.accordion-body');
        const questionText = $button.text().toLowerCase();
        const answerText = $body.text().toLowerCase();
        const searchLower = searchTerm.toLowerCase();

        if (questionText.includes(searchLower) || answerText.includes(searchLower)) {
          matches++;
          results.push($item);
          
          // Expand matching item
          expandItem($item);
          
          // Highlight search term
          highlightSearchTerm($item, searchTerm);
        }
      });

      // Hide loading state
      hideLoading(instance);

      // Handle results
      if (matches > 0) {
        $statusArea.text(`Found ${matches} result${matches > 1 ? 's' : ''} for "${searchTerm}"`);
        
        // Scroll to first result
        if (results.length > 0) {
          smoothScrollTo(results[0]);
        }
      } else {
        showNoResults(instance, searchTerm);
      }
    }, 300); // Simulate network delay
  }

  /**
   * Set up filter functionality
   */
  function setupFilters(instance) {
    const { $filterButtons, $accordionItems } = instance;

    $filterButtons.on('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const $button = $(this);
      const filter = $button.data('filter');
      
      
      // Remove active class from ALL filter buttons in this FAQ section
      instance.$section.find('.filter-btn').removeClass('active');
      
      // Add active class only to clicked button
      $button.addClass('active');
      
      // Store current filter
      instance.currentFilter = filter;
      
      // Apply filter
      applyFilter(instance, filter);
      
      // Clear any search when switching filters
      instance.$searchInput.val('');
      clearSearch(instance);
    });

    // Add data attributes to FAQ items for filtering
    $accordionItems.each(function(index) {
      const $item = $(this);
      // Assign categories based on content or index (customize as needed)
      if (index % 3 === 0) {
        $item.attr('data-category', 'services');
      } else if (index % 3 === 1) {
        $item.attr('data-category', 'resources');
      } else {
        $item.attr('data-category', 'general');
      }
    });
  }

  /**
   * Apply filter to FAQ items
   */
  function applyFilter(instance, filter) {
    const { $accordionItems, $statusArea } = instance;
    let visibleCount = 0;

    $accordionItems.each(function() {
      const $item = $(this);
      const category = $item.data('category') || 'general';
      
      if (filter === 'all' || category === filter) {
        $item.show();
        visibleCount++;
      } else {
        $item.hide();
      }
    });

    // Announce filter results
    $statusArea.text(`Showing ${visibleCount} FAQs${filter !== 'all' ? ` for ${filter}` : ''}`);
  }

  /**
   * Set up keyboard navigation
   */
  function setupKeyboardNavigation(instance) {
    const { $accordionItems, $section } = instance;

    // Make accordion buttons keyboard navigable
    $section.on('keydown', '.accordion-button', function(e) {
      const $currentButton = $(this);
      const $currentItem = $currentButton.closest('.accordion-item');
      let $targetItem;

      switch(e.key) {
        case 'ArrowDown':
          e.preventDefault();
          $targetItem = $currentItem.nextAll(':visible').first();
          if ($targetItem.length) {
            $targetItem.find('.accordion-button').focus();
          }
          break;
          
        case 'ArrowUp':
          e.preventDefault();
          $targetItem = $currentItem.prevAll(':visible').first();
          if ($targetItem.length) {
            $targetItem.find('.accordion-button').focus();
          }
          break;
          
        case 'Home':
          e.preventDefault();
          $accordionItems.filter(':visible').first().find('.accordion-button').focus();
          break;
          
        case 'End':
          e.preventDefault();
          $accordionItems.filter(':visible').last().find('.accordion-button').focus();
          break;
      }
    });
  }

  /**
   * Set up accessibility features
   */
  function setupAccessibility(instance) {
    const { $section } = instance;

    // Add ARIA labels
    $section.attr('role', 'region');
    $section.attr('aria-label', 'Frequently Asked Questions');

    // Ensure proper focus management
    $section.on('focusin', function(e) {
      $(e.target).addClass(CONFIG.FOCUS_VISIBLE_CLASS);
    });

    $section.on('focusout', function(e) {
      $(e.target).removeClass(CONFIG.FOCUS_VISIBLE_CLASS);
    });
  }

  /**
   * Add loading states HTML
   */
  function addLoadingStates(instance) {
    const { $searchButton, $searchResults } = instance;

    // Add loading spinner to search button
    $searchButton.append('<span class="spinner-border spinner-border-sm ms-2 d-none" role="status"><span class="visually-hidden">Loading...</span></span>');

    // Add loading skeleton to results area
    $searchResults.append(`
      <div class="loading-skeleton d-none">
        <div class="skeleton-item"></div>
        <div class="skeleton-item"></div>
        <div class="skeleton-item"></div>
      </div>
    `);
  }

  /**
   * Show loading state
   */
  function showLoading(instance) {
    const { $searchButton, $searchResults } = instance;
    
    $searchButton.prop('disabled', true);
    $searchButton.find('.spinner-border').removeClass('d-none');
    $searchResults.find('.loading-skeleton').removeClass('d-none');
  }

  /**
   * Hide loading state
   */
  function hideLoading(instance) {
    const { $searchButton, $searchResults } = instance;
    
    $searchButton.prop('disabled', false);
    $searchButton.find('.spinner-border').addClass('d-none');
    $searchResults.find('.loading-skeleton').addClass('d-none');
  }

  /**
   * Show no results message
   */
  function showNoResults(instance, searchTerm) {
    const { $statusArea, $searchResults } = instance;
    
    $statusArea.text(`No results found for "${searchTerm}"`);
    
    // Show helpful message in results area
    $searchResults.removeClass('d-none').html(`
      <div class="no-results p-3">
        <h5>No FAQs match your search</h5>
        <p>Try searching with different keywords or browse all FAQs below.</p>
        <a href="/site-search?keys=${encodeURIComponent(searchTerm)}" class="btn btn-primary btn-sm">
          Search entire site
        </a>
      </div>
    `);
  }

  /**
   * Clear search and reset view
   */
  function clearSearch(instance) {
    const { $searchResults, $statusArea } = instance;
    
    $searchResults.addClass('d-none').empty();
    $statusArea.empty();
    removeHighlights(instance);
    
    // Reapply current filter
    applyFilter(instance, instance.currentFilter);
  }

  /**
   * Collapse all accordion items
   */
  function collapseAll(instance) {
    const { $accordionItems } = instance;
    
    $accordionItems.each(function() {
      const $item = $(this);
      const $button = $item.find('.accordion-button');
      const $collapse = $item.find('.accordion-collapse');
      
      $button.addClass('collapsed');
      $collapse.removeClass('show');
    });
  }

  /**
   * Expand a specific accordion item
   */
  function expandItem($item) {
    const $button = $item.find('.accordion-button');
    const $collapse = $item.find('.accordion-collapse');
    
    $button.removeClass('collapsed');
    $collapse.addClass('show');
  }

  /**
   * Highlight search term in FAQ item
   */
  function highlightSearchTerm($item, searchTerm) {
    // This is a simple implementation - in production, use a proper highlighting library
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    
    $item.find('.accordion-button, .accordion-body').each(function() {
      const $element = $(this);
      const html = $element.html();
      const highlighted = html.replace(regex, '<mark class="search-highlight">$1</mark>');
      $element.html(highlighted);
    });
  }

  /**
   * Remove all search highlights
   */
  function removeHighlights(instance) {
    const { $accordionItems } = instance;
    
    $accordionItems.find('mark.search-highlight').each(function() {
      const $mark = $(this);
      $mark.replaceWith($mark.text());
    });
  }

  /**
   * Smooth scroll to element
   */
  function smoothScrollTo($element) {
    $('html, body').animate({
      scrollTop: $element.offset().top - 100
    }, CONFIG.ANIMATION_DURATION);
  }

})(jQuery, Drupal, once);