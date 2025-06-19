/**
 * @file
 * Down Arrow Accordion Component
 * 
 * Provides an accessible accordion with the following features:
 * - Centered heading with animated chevron icon
 * - Smooth expand/collapse animations
 * - "Close others" functionality - only one accordion open at a time
 * - Proper ARIA attributes for accessibility
 * - Memory-efficient instance management
 * 
 * @requires Bootstrap 5 Collapse API
 */

(function(Drupal, once) {
  'use strict';

  /**
   * WeakMap to store accordion instances without memory leaks
   * Instances are automatically garbage collected when DOM elements are removed
   */
  const accordionRegistry = new WeakMap();

  /**
   * Initialize Down Arrow Accordion behavior
   */
  Drupal.behaviors.downArrowAccordion = {
    /**
     * Attach behavior to accordion elements
     * 
     * @param {HTMLDocument|HTMLElement} context - DOM context
     * @param {Object} settings - Drupal settings object
     */
    attach: function(context, settings) {
      // Process each accordion container - support both new and legacy classes
      const accordions = context.querySelectorAll('.down-arrow-accordion, .pp-er');
      
      once('down-arrow-accordion', accordions, context).forEach(element => {
        try {
          initializeAccordion(element);
        } catch (error) {
          console.error('Down Arrow Accordion initialization error:', error);
        }
      });
    },

    /**
     * Detach behavior and clean up
     * 
     * @param {HTMLDocument|HTMLElement} context - DOM context
     * @param {Object} settings - Drupal settings object
     * @param {string} trigger - Detach trigger type
     */
    detach: function(context, settings, trigger) {
      if (trigger === 'unload') {
        // Clean up any global event listeners if needed
        // WeakMap automatically handles memory cleanup
      }
    }
  };

  /**
   * Initialize a single accordion instance
   * 
   * @param {HTMLElement} container - The accordion container element
   */
  function initializeAccordion(container) {
    // Support both new BEM classes and legacy classes
    const toggle = container.querySelector('.daa-header__toggle, .pp-er-title-wrap');
    const icon = container.querySelector('.daa-header__icon i, .pp-er-arrow');
    
    if (!toggle || !icon) {
      console.warn('Down Arrow Accordion: Missing required elements');
      return;
    }
    
    const targetId = toggle.getAttribute('data-bs-target');
    const target = targetId ? document.querySelector(targetId) : null;
    
    if (!target) {
      console.warn('Down Arrow Accordion: Target panel not found');
      return;
    }

    // Store instance data in WeakMap
    const instance = {
      container: container,
      toggle: toggle,
      icon: icon,
      target: target,
      targetId: targetId
    };
    
    accordionRegistry.set(container, instance);

    // Set up event listeners
    setupEventListeners(instance);
    
    // Set initial ARIA states
    setInitialState(instance);
  }

  /**
   * Set up Bootstrap collapse event listeners
   * 
   * @param {Object} instance - Accordion instance object
   */
  function setupEventListeners(instance) {
    const { target, toggle, icon } = instance;

    // Handle accordion expansion
    target.addEventListener('show.bs.collapse', function(event) {
      // Prevent event bubbling from nested accordions
      if (event.target !== target) return;

      // Close other accordions
      closeOtherAccordions(instance);
      
      // Update ARIA states and icon
      icon.classList.remove('collapsed');
      toggle.setAttribute('aria-expanded', 'true');
    });

    // Handle accordion collapse
    target.addEventListener('hide.bs.collapse', function(event) {
      // Prevent event bubbling from nested accordions
      if (event.target !== target) return;

      // Update ARIA states and icon
      icon.classList.add('collapsed');
      toggle.setAttribute('aria-expanded', 'false');
    });

    // Handle errors from Bootstrap Collapse API
    target.addEventListener('error', function(event) {
      console.error('Bootstrap Collapse error:', event);
    });
  }

  /**
   * Close all other open accordions
   * 
   * @param {Object} currentInstance - The accordion instance being opened
   */
  function closeOtherAccordions(currentInstance) {
    // Find all accordion containers on the page
    const allAccordions = document.querySelectorAll('.down-arrow-accordion, .pp-er');
    
    allAccordions.forEach(accordion => {
      if (accordion === currentInstance.container) return;
      
      const instance = accordionRegistry.get(accordion);
      if (!instance || !instance.target.classList.contains('show')) return;
      
      try {
        // Use Bootstrap's Collapse API to close
        const bsCollapse = bootstrap.Collapse.getInstance(instance.target);
        if (bsCollapse) {
          bsCollapse.hide();
        } else {
          // Fallback: Create new instance and hide
          new bootstrap.Collapse(instance.target, { toggle: false }).hide();
        }
      } catch (error) {
        console.error('Error closing accordion:', error);
        // Manual fallback
        instance.target.classList.remove('show');
        instance.icon.classList.add('collapsed');
        instance.toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /**
   * Set initial ARIA states based on accordion state
   * 
   * @param {Object} instance - Accordion instance object
   */
  function setInitialState(instance) {
    const { target, toggle, icon } = instance;
    const isExpanded = target.classList.contains('show');
    
    toggle.setAttribute('aria-expanded', isExpanded);
    icon.classList.toggle('collapsed', !isExpanded);
  }

})(Drupal, once);