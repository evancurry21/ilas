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