/**
 * @file
 * Scroll behaviors for sticky navbar and back-to-top button.
 */

(function () {
  'use strict';

  // Run when DOM is ready
  function init() {
    const navbar = document.querySelector('.centered-logo-navbar');
    const backToTopBtn = document.querySelector('.back-to-top');
    const scrollThreshold = 100;
    const shrinkThreshold = 50;
    
    if (!navbar && !backToTopBtn) {
      return;
    }
    
    // Check for sticky positioning issues
    if (navbar) {
      let parent = navbar.parentElement;
      while (parent && parent !== document.body) {
        const style = window.getComputedStyle(parent);
        if (style.overflow !== 'visible' || style.transform !== 'none' || style.filter !== 'none') {
          console.warn('Sticky positioning blocked by parent:', parent, {
            overflow: style.overflow,
            transform: style.transform,
            filter: style.filter
          });
        }
        parent = parent.parentElement;
      }
    }
    
    // Back to top button click handler
    if (backToTopBtn) {
      backToTopBtn.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      });
    }
    
    // Scroll handler
    function handleScroll() {
      const scrolled = window.pageYOffset || document.documentElement.scrollTop;
      
      // Handle navbar shrinking
      if (navbar) {
        if (scrolled > shrinkThreshold) {
          navbar.classList.add('navbar-shrink');
          // Test if sticky is working
          const rect = navbar.getBoundingClientRect();
          console.log('Navbar position test:', {
            position: window.getComputedStyle(navbar).position,
            top: rect.top,
            expectedTop: 41.6 // 2.6rem = 41.6px
          });
        } else {
          navbar.classList.remove('navbar-shrink');
        }
      }
      
      // Handle back-to-top button
      if (backToTopBtn) {
        if (scrolled > scrollThreshold) {
          backToTopBtn.classList.add('show');
        } else {
          backToTopBtn.classList.remove('show');
        }
      }
    }
    
    // Attach scroll listener
    window.addEventListener('scroll', handleScroll, { passive: true });
    
    // Initial check
    handleScroll();
  }
  
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  
})();