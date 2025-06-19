/**
 * @file
 * Mobile slide-out menu functionality.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.mobileMenu = {
    attach: function (context, settings) {
      // Check if we're in the main document context (not an AJAX fragment)
      if (context === document || context.contains(document.body)) {
        // Only run once per full page load, not per element
        if (document.body.hasAttribute('data-mobile-menu-initialized')) {
          return;
        }
        document.body.setAttribute('data-mobile-menu-initialized', 'true');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
        const mobileMenuPanel = document.getElementById('mobileMenuPanel');
        const mobileMenuClose = document.getElementById('mobileMenuClose');
        const mobileMenuBackdrop = document.getElementById('mobileMenuBackdrop');
        const hamburgerButton = document.querySelector('.navbar-toggler');

        if (!mobileMenuOverlay || !hamburgerButton) {
          return;
        }

        // Open mobile menu
        function openMobileMenu() {
          mobileMenuOverlay.classList.add('active');
          mobileMenuOverlay.setAttribute('aria-hidden', 'false');
          hamburgerButton.setAttribute('aria-expanded', 'true');
          
          // Prevent body scrolling
          document.body.style.overflow = 'hidden';
          document.body.classList.add('mobile-menu-open');
          
          // Focus management
          mobileMenuClose.focus();
        }

        // Close mobile menu
        function closeMobileMenu() {
          mobileMenuOverlay.classList.remove('active');
          mobileMenuOverlay.setAttribute('aria-hidden', 'true');
          hamburgerButton.setAttribute('aria-expanded', 'false');
          
          // Restore body scrolling
          document.body.style.overflow = '';
          document.body.classList.remove('mobile-menu-open');
          
          // Return focus to hamburger button
          hamburgerButton.focus();
        }

        // Hamburger button click
        hamburgerButton.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          if (mobileMenuOverlay.classList.contains('active')) {
            closeMobileMenu();
          } else {
            openMobileMenu();
          }
        });

        // Close button click
        mobileMenuClose.addEventListener('click', function(e) {
          e.preventDefault();
          closeMobileMenu();
        });

        // Backdrop click to close
        mobileMenuBackdrop.addEventListener('click', function(e) {
          if (e.target === mobileMenuBackdrop) {
            closeMobileMenu();
          }
        });

        // Escape key to close
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && mobileMenuOverlay.classList.contains('active')) {
            closeMobileMenu();
          }
        });

        // Handle submenu toggles (+ button clicks)
        const submenuToggles = document.querySelectorAll('.menu-toggle[aria-expanded]');
        submenuToggles.forEach(function(toggle) {
          toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const submenu = toggle.parentNode.parentNode.querySelector('.mobile-submenu');
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            
            if (submenu) {
              if (isExpanded) {
                // Close submenu
                toggle.setAttribute('aria-expanded', 'false');
                submenu.classList.remove('show');
              } else {
                // Close other submenus first
                submenuToggles.forEach(function(otherToggle) {
                  if (otherToggle !== toggle) {
                    otherToggle.setAttribute('aria-expanded', 'false');
                    const otherSubmenu = otherToggle.parentNode.parentNode.querySelector('.mobile-submenu');
                    if (otherSubmenu) {
                      otherSubmenu.classList.remove('show');
                    }
                  }
                });
                
                // Open this submenu
                toggle.setAttribute('aria-expanded', 'true');
                submenu.classList.add('show');
              }
            }
          });
        });

        // Handle main menu text clicks (navigation links)
        const menuTextLinks = document.querySelectorAll('.menu-text-link');
        menuTextLinks.forEach(function(link) {
          link.addEventListener('click', function(e) {
            // Only close menu if it's a real navigation (not #)
            if (link.getAttribute('href') && link.getAttribute('href') !== '#') {
              setTimeout(function() {
                closeMobileMenu();
              }, 150);
            }
          });
        });

        // Handle standalone menu items (no submenus)
        const standaloneMenuLinks = document.querySelectorAll('.mobile-nav-link.no-submenu');
        standaloneMenuLinks.forEach(function(link) {
          link.addEventListener('click', function() {
            // Close menu when navigating
            setTimeout(function() {
              closeMobileMenu();
            }, 150);
          });
        });

        // Handle submenu item clicks
        const submenuLinks = document.querySelectorAll('.mobile-submenu-link');
        submenuLinks.forEach(function(link) {
          link.addEventListener('click', function() {
            // Close menu when navigating
            setTimeout(function() {
              closeMobileMenu();
            }, 150);
          });
        });
      }
    }
  };

})(Drupal, once);