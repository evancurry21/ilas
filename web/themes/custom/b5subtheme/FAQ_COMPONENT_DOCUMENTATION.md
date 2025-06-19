# Smart FAQ Component Documentation

## Overview
The Smart FAQ component is a fully-featured, accessible, and SEO-optimized FAQ system built for Drupal with Bootstrap 5. It includes search functionality, category filtering, keyboard navigation, and structured data for search engines.

## Features
- ✅ **Security**: XSS protection with proper sanitization
- ✅ **Accessibility**: Full keyboard navigation, ARIA labels, screen reader support
- ✅ **SEO**: Schema.org FAQ structured data (JSON-LD + Microdata)
- ✅ **Search**: Real-time search with debouncing and highlighting
- ✅ **Filtering**: Category-based filtering system
- ✅ **Performance**: Loading states, smooth animations, debounced search
- ✅ **Mobile**: Fully responsive with mobile-specific optimizations
- ✅ **UX**: No results messaging, auto-save search state, smooth scrolling

## Components

### 1. Paragraph Types
- **FAQ Smart Section** (`paragraph--faq-smart-section`)
  - Container for the entire FAQ system
  - Fields: `field_faq_section_title`, `field_faq_items`, `field_search_placeholder`
  
- **FAQ Item** (`paragraph--faq-item`)
  - Individual FAQ question/answer pairs
  - Fields: `field_faq_question`, `field_faq_answer`, `field_faq_category`

### 2. Templates
```
templates/paragraph/
├── paragraph--faq-smart-section.html.twig  # Main container
└── paragraph--faq-item.html.twig          # Individual FAQ
```

### 3. Styles
```
scss/_smart-faq.scss  # All FAQ-specific styles
```

### 4. JavaScript
```
js/smart-faq-enhanced.js  # Enhanced functionality
js/smart-faq.js          # Original basic functionality
```

### 5. Library Definition
```yaml
# In b5subtheme.libraries.yml
smart-faq:
  version: 1.0
  js:
    js/smart-faq-enhanced.js: {}
  dependencies:
    - core/jquery
    - core/drupal
    - core/once
```

## Usage

### Basic Implementation
1. Create a new paragraph of type "FAQ Smart Section"
2. Add "FAQ Item" paragraphs as children
3. The component will automatically render with all features

### Example Structure
```html
<div class="faq-smart-section">
  <!-- Search Bar -->
  <div class="faq-search-container">
    <input type="text" class="faq-search" placeholder="Search FAQs...">
  </div>
  
  <!-- Filters -->
  <div class="faq-filters">
    <button data-filter="all">All</button>
    <button data-filter="services">Services</button>
    <button data-filter="resources">Resources</button>
  </div>
  
  <!-- FAQ Accordion -->
  <div class="accordion">
    <div class="accordion-item" data-category="services">
      <h2 class="accordion-header">
        <button class="accordion-button">Question text?</button>
      </h2>
      <div class="accordion-collapse">
        <div class="accordion-body">Answer text.</div>
      </div>
    </div>
  </div>
</div>
```

### Customization

#### Add New Categories
1. Add a taxonomy reference field `field_faq_category` to the FAQ Item paragraph type
2. Update the filter buttons in the template
3. Categories are automatically detected by the JavaScript

#### Modify Search Behavior
```javascript
// In smart-faq-enhanced.js, adjust these constants:
const CONFIG = {
  SEARCH_DEBOUNCE_MS: 300,    // Delay before search executes
  MIN_SEARCH_LENGTH: 2,        // Minimum characters to search
  ANIMATION_DURATION: 300      // Animation speed
};
```

#### Style Customization
All styles use theme variables for easy customization:
```scss
// Override these variables before importing _smart-faq.scss
$faq-bg-color: $color-gray-bg;
$faq-border-color: $color-gray-border;
$faq-text-muted: lighten($color-gray-border, 20%);
$faq-focus-color: $color-primary;
```

## Accessibility Features

### Keyboard Navigation
- **Tab**: Navigate through FAQ items
- **Enter/Space**: Expand/collapse FAQ items
- **Arrow Up/Down**: Move between FAQ items
- **Home**: Jump to first FAQ
- **End**: Jump to last FAQ

### Screen Reader Support
- ARIA live regions for search results
- Proper heading hierarchy
- Descriptive button labels
- Status announcements for filtering

## SEO Implementation

### Structured Data
The component automatically generates Schema.org FAQ structured data:
```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [{
    "@type": "Question",
    "name": "Question text",
    "acceptedAnswer": {
      "@type": "Answer",
      "text": "Answer text"
    }
  }]
}
```

### Testing Structured Data
1. Use Google's Rich Results Test: https://search.google.com/test/rich-results
2. Check for FAQ rich snippets eligibility

## Performance Considerations

### Search Optimization
- Debounced search (300ms delay)
- Client-side filtering for instant results
- Highlighted search terms
- Loading states for better UX

### Mobile Performance
- Reduced font sizes on mobile
- Simplified animations
- Touch-optimized tap targets
- Responsive padding/margins

## Troubleshooting

### FAQs Not Expanding
- Ensure Bootstrap 5 JavaScript is loaded
- Check for JavaScript errors in console
- Verify `data-bs-toggle="collapse"` attributes

### Search Not Working
- Verify jQuery is loaded before the FAQ script
- Check that Drupal behaviors are properly attached
- Ensure proper HTML structure

### Styling Issues
- Compile SCSS after changes: `npm run prod`
- Clear Drupal cache: `ddev drush cr`
- Check for CSS specificity conflicts

## Browser Support
- Chrome/Edge: Full support
- Firefox: Full support
- Safari: Full support
- IE11: Not supported (use fallback styles)

## Future Enhancements
- Analytics tracking for popular FAQs
- Admin interface for reordering
- AJAX loading for large FAQ sets
- Multi-language support
- Print-friendly version