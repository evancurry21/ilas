# B5 Subtheme Standardization Guide

## Overview

This guide documents the standardization effort completed across the B5 subtheme to consolidate common patterns, improve maintainability, and ensure consistent styling across all node templates.

## Key Changes

### 1. Centralized Layout Components

All common layout patterns have been consolidated into `_layout-components.scss`:

- **Curved Page Headers**: Standard gray header with curved bottom border
- **Blue Curve Sections**: Standard blue footer with curved top
- **Content Sections**: Reusable sections with different background colors
- **Info Cards**: Standardized card components for office information
- **Apply Schedule Section**: Table formatting for legal advice schedules

### 2. Page Template Standardization

The `page.html.twig` template now handles standard headers and footers for most content types:

```twig
{% set custom_types = ['home_page', 'donate'] %}
```

Only the home page and donate page use custom implementations. All other node types automatically receive:
- Standard curved gray header with title
- Standard blue curved footer

### 3. Node Template Updates

#### Standardized Templates (using page.html.twig layout):
- **apply-for-help**: Uses standard layout, custom subtitle field
- **office-locations**: Uses standard layout, custom map styling
- **office-information**: Uses standard layout, info-card components
- **employment**: Uses standard layout, content sections

#### Custom Templates (bypass standard layout):
- **home-page**: Full custom design with unique hero section
- **donate**: Custom header/footer with donation form

### 4. SCSS File Organization

#### Core Component Files:
- `_layout-components.scss`: All reusable layout components
- `_mixins.scss`: Curved section mixins and utilities
- `_variables_theme.scss`: Theme variables and colors

#### Page-Specific Files (minimal custom styles):
- `_apply-for-help.scss`: Only subtitle styling
- `_office-locations.scss`: Only map border styling
- `_office-information.scss`: Minimal overrides
- `_donate.scss`: Custom donation form styles
- `_home-page.scss`: Full custom implementation

### 5. JavaScript Updates

- **down-arrow-accordion.js**: Fixed memory leaks with WeakMap
- Added proper error handling and accessibility features

## Usage Guidelines

### Adding a New Content Type

1. **For standard pages**: 
   - Create node template without header/footer sections
   - Page.html.twig will automatically apply standard layout

2. **For custom pages**:
   - Add node type to `custom_types` array in page.html.twig
   - Create full custom template with `disable_standard_layout = true`

### Using Layout Components

#### Content Sections:
```scss
<section class="content-section content-section--primary">
  <!-- Blue background section -->
</section>

<section class="content-section content-section--gray">
  <!-- Gray background section -->
</section>
```

#### Info Cards:
```html
<div class="info-card">
  <h3 class="info-card__title">Title</h3>
  <div class="info-card__content">
    <!-- Card content -->
  </div>
</div>
```

### Modifying Curved Sections

All curved sections use centralized mixins:
```scss
// For custom curved sections
@include curved-section-base($padding);
@include curved-bottom($bg-color, $curve-height-desktop, $curve-height-mobile, $z-index, $border);
```

## Benefits

1. **Consistency**: All pages share the same header/footer design
2. **Maintainability**: Changes to common elements only require updates in one place
3. **Performance**: Reduced CSS file size by eliminating duplicated styles
4. **Flexibility**: Easy to add new content types with standard or custom layouts

## Migration Notes

When updating existing templates:
1. Remove custom header/footer implementations
2. Check if node type should be in `custom_types` array
3. Move reusable styles to `_layout-components.scss`
4. Keep only page-specific customizations in dedicated SCSS files