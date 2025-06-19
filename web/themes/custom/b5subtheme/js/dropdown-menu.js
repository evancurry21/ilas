/**
 * @file
 * Enhanced dropdown menu behavior that works with Bootstrap 5
 * Provides hover functionality on desktop while maintaining Bootstrap's mobile behavior
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.dropdownMenu = {
    attach: function (context, settings) {
      
      // Use once() correctly for Drupal 9/10
      const elements = once('dropdown-menu-init', 'body', context);
      
      if (elements.length) {
        
        // Force dropdown functionality with multiple approaches
        function forceDropdownHover() {
          if (window.innerWidth < 1200) return;
          
          // Method 1: Direct CSS manipulation
          const style = document.createElement('style');
          style.textContent = `
            @media (min-width: 1200px) {
              .centered-logo-navbar .unified-menu .nav-item.dropdown:hover > .dropdown-menu {
                display: block !important;
                opacity: 1 !important;
                visibility: visible !important;
              }
              .centered-logo-navbar .unified-menu .nav-item.dropdown:hover {
                background: transparent;
              }
            }
          `;
          document.head.appendChild(style);
          
          // Method 2: Pure JavaScript hover (no jQuery)
          const dropdowns = document.querySelectorAll('.centered-logo-navbar .unified-menu .nav-item.dropdown');
          
          dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (!toggle || !menu) return;
            
            dropdown.addEventListener('mouseenter', function() {
              dropdown.classList.add('show');
              menu.classList.add('show');
              menu.style.display = 'block';
              toggle.setAttribute('aria-expanded', 'true');
            });
            
            dropdown.addEventListener('mouseleave', function() {
              dropdown.classList.remove('show');
              menu.classList.remove('show');
              menu.style.display = '';
              toggle.setAttribute('aria-expanded', 'false');
            });
            
            // Prevent click navigation on desktop
            toggle.addEventListener('click', function(e) {
              if (window.innerWidth >= 1200) {
                const href = this.getAttribute('href');
                if (href && href !== '#') {
                  window.location.href = href;
                }
                e.preventDefault();
              }
            });
          });
          
          // Method 3: jQuery with aggressive binding
          setTimeout(function() {
            
            // Remove ALL possible interference
            $('.dropdown-toggle').off();
            $('.dropdown').off();
            $(document).off('click.bs.dropdown');
            $(document).off('keydown.bs.dropdown');
            
            // Simple jQuery hover
            $('.centered-logo-navbar .unified-menu .nav-item.dropdown').each(function() {
              const $item = $(this);
              const $menu = $item.find('> .dropdown-menu');
              const $toggle = $item.find('> .dropdown-toggle');
              
              $item.hover(
                function() {
                  $menu.stop(true, true).fadeIn(200);
                  $item.addClass('show');
                  $menu.addClass('show');
                },
                function() {
                  $menu.stop(true, true).fadeOut(200);
                  $item.removeClass('show');
                  $menu.removeClass('show');
                }
              );
            });
            
          }, 1000);
        }
        
        // Run immediately and after delays to catch any late-loading scripts
        forceDropdownHover();
        setTimeout(forceDropdownHover, 2000);
        setTimeout(forceDropdownHover, 3000);
        
        // Also run on window load
        $(window).on('load', forceDropdownHover);
        
        // Handle resize
        $(window).on('resize', function() {
          if (window.innerWidth >= 1200) {
            forceDropdownHover();
          }
        });
        
      } // end if elements.length
    } // end attach
  }; // end behavior

})(jQuery, Drupal, once);