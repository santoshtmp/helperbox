# HelperBox Drupal Module

HelperBox is a comprehensive custom Drupal module that delivers a broad collection of features to support and extend site development. It includes helper functions, custom blocks, Views fields, field formatters, date/time widgets, APIs, theme template suggestions, preprocess hooks, form field access controls, and various utility hooks.

**Maintainer:** santoshtmp7@gmail.com  
**Version:** 11.3.1  
**Drupal Compatibility:** Drupal 11+  
**Package:** Custom

## Module Structure

```
helperbox/
├── config/
│   ├── install/
│   │   └── helperbox.settings.yml       # Default module configuration
│   └── schema/
│       └── helperbox.schema.yml         # Configuration schema definitions
├── css/
│   ├── helperbox.css                    # Frontend styles
│   ├── helperbox_admin.css              # Admin interface styles
│   └── helperbox_datetimewidget.css     # Date/time widget styles
├── includes/
│   ├── helperbox.preprocess.inc         # Preprocess hooks (node, paragraph, views)
│   ├── helperbox.entity.inc             # Entity hooks
│   ├── helperbox.form.inc               # Form hooks and alterations
│   ├── helperbox.menu.inc               # Menu hooks
│   ├── helperbox.page.inc               # Page hooks
│   ├── helperbox.theme.inc              # Theme hooks and template suggestions
│   └── helperbox.views.inc              # Views hooks
├── js/
│   ├── helperbox.js                     # Frontend JavaScript
│   ├── helperbox_admin.js               # Admin JavaScript
│   ├── helperbox_datetimewidget.js      # Date/time widget JavaScript
│   └── node_form_conditional_fields.js  # Conditional field logic
├── src/
│   ├── Controller/
│   │   └── TestApiController.php        # API endpoint controller
│   ├── EventSubscriber/
│   │   └── LoaderEventSubscriber.php    # Page load event subscriber
│   ├── Form/
│   │   └── HelperboxSettingsForm.php    # Module settings form
│   ├── Helper/
│   │   ├── FormField.php                # Form field utilities
│   │   ├── GetBlock.php                 # Block retrieval helpers
│   │   ├── HelperboxSettings.php        # Settings helper class
│   │   ├── MediaHelper.php              # Media handling utilities
│   │   ├── MenuHelper.php               # Menu building helpers
│   │   ├── PartialsContent.php          # Content partial helpers
│   │   ├── PreprocessNode.php           # Node preprocess utilities
│   │   ├── PreprocessViewsViewField.php # Views field preprocess
│   │   ├── QueryNode.php                # Node query helpers
│   │   └── UtilHelper.php               # General utilities
│   └── Plugin/
│       ├── Block/
│       │   ├── BannerBlock.php          # Banner block plugin
│       │   ├── ContactBlock.php         # Contact block plugin
│       │   ├── ContentTypeBlock.php     # Content type block plugin
│       │   ├── MenuBlock.php            # Menu block plugin
│       │   └── RepeatedContentBlock.php # Repeated content block plugin
│       ├── Field/
│       │   ├── FieldFormatter/
│       │   │   ├── DateRangeFormat.php         # Date range formatter
│       │   │   ├── MediaInfoEntityReferenceFormatter.php  # Media info formatter
│       │   │   └── TelPhoneNumFormatter.php      # Phone number formatter
│       │   └── FieldWidget/
│       │       └── DateTimeHelperboxWidget.php   # Custom date/time widget
│       └── views/
│           ├── field/
│           │   ├── AddCTA.php             # CTA button field
│           │   ├── AddMedia.php           # Media adder field
│           │   ├── CountNode.php          # Node counter field
│           │   ├── CustomText.php         # Custom HTML text field
│           │   └── RenderBlock.php        # Block renderer field
│           └── area/
│               └── RenderBlock.php        # Block renderer area handler
├── templates/
│   ├── helperbox-add-cta.html.twig        # CTA button template
│   ├── helperbox-banner.html.twig         # Banner block template
│   ├── helperbox-contact.html.twig        # Contact block template
│   ├── helperbox-content.html.twig        # Content block template
│   ├── helperbox-date-time-widget.html.twig  # Date/time widget template
│   ├── helperbox-menu.html.twig           # Menu block template
│   ├── helperbox-renderblock.html.twig    # Render block template
│   ├── helperbox-repeatedcontent.html.twig   # Repeated content template
│   └── macro/
│       ├── menuitems.html.twig            # Menu items macro
│       ├── render_field_content_nodes.html.twig  # Field render macro
│       ├── render_field_taxonomy_terms.html.twig # Taxonomy terms macro
│       └── social_links.html.twig         # Social links macro
├── helperbox.info.yml                     # Module info file
├── helperbox.install                      # Install/uninstall hooks
├── helperbox.libraries.yml                # Asset libraries definition
├── helperbox.links.contextual.yml         # Contextual links
├── helperbox.links.menu.yml               # Menu links
├── helperbox.module                       # Main module file
├── helperbox.routing.yml                  # Route definitions
├── helperbox.services.yml                 # Service definitions
├── images/
│   └── Thumbnail.webp                     # Default thumbnail image
└── readme.md                              # This file
```

## Features Overview

### 1. Template Suggestions System
- Provides template suggestions for forms, paragraphs, fields, colorbox_view_mode_formatter, and other components
- Advanced suggestions for specific content types, nodes, and view contexts
- Custom theme implementations for various components

### 2. Custom Views Fields
- **Node Counter (`helperbox_views_field_countnode`)**: Counts nodes based on content type and conditions
- **Block Renderer (`helperbox_renderblock`)**: Renders blocks and views blocks within Views
- **Custom HTML Text (`helperbox_custom_text`)**: Displays full HTML content with token support
- **Media Adder (`helperbox_views_field_addmedia`)**: Adds media to views with advanced options
- **CTA Button (`helperbox_views_field_cta`)**: Configurable call-to-action buttons with support for:
  - Internal paths, external URLs, and entity references
  - Primary/secondary button styles
  - Dynamic query parameters with placeholders
  - Link field integration

### 3. Custom Field Formatters
- **Phone Number Formatter (`helperbox_fieldformat_telphone`)**: Formats text fields as clickable phone links
- **Media Information Formatter (`helperbox_fieldformat_mediainfo`)**: Displays media file information (size, type, extension, download links)
- **Date Range Formatter (`helperbox_fieldformat_daterange`)**: Custom date range formatting options

### 4. Advanced Date/Time Widgets
- **Custom Date Time Widget (`helperbox_date_time_widget`)**: Enhanced date/time input with flexible configurations
  - Multiple date order options (YMD, MDY, DMY, YM, MY, Y, M, YMopt)
  - Customizable year ranges (past/future)
  - Time type options (12/24 hour)
  - Optional month selection for year/month combinations

### 5. Custom Blocks
- **Banner Block (`helperbox_banner_block`)**: Feature-rich banner with multiple layout options, media support, CTAs, and highlights
- **Menu Block (`helperbox_menu_block`)**: Custom menu rendering with social links
- **Repeated Content Block (`helperbox_repeated_content_block`)**: Reusable content sections

### 6. Media Handling Features
- Custom media thumbnail updates via `field_custom_thumbnail`
- Media library information helper with file details
- Image style integration
- File size conversion utilities

### 7. Form Field Access Controls
- Configurable field access based on content type and bundle
- Node-specific field access rules
- Form ID-based field access controls
- Maximum node limits per content type
- Field validation (title uniqueness, country code uniqueness)

### 8. Theme System Enhancements
- Custom theme implementations for various components
- Template suggestions for paragraphs based on context
- Field template suggestions
- View mode formatter suggestions

### 9. Utility Functions
- Error logging with backtrace
- Byte-to-size conversion utilities
- Content type retrieval helpers
- Block rendering utilities
- Media information extraction

### 10. API Integration
- Custom API endpoint at `/api/testhelperbox`
- JSON response capabilities
- Integration with Config Pages module

### 11. Preprocessing Hooks
- Node preprocessing with content-type-specific methods
- Paragraph preprocessing with view context
- Views field preprocessing
- Language block preprocessing

### 12. Configuration Management
- Settings form at `/admin/config/development/customhelperbox`
- Enable media custom thumbnail settings
- Custom field access rules configuration
- Node validation rules

## Installation

1. Place the helperbox module in your Drupal installation's modules directory:
   ```bash
   # Module should be at: web/modules/custom/helperbox
   ```

2. Enable the module via admin interface or Drush:
   ```bash
   drush en helperbox -y
   ```

3. Configure settings at `/admin/config/development/customhelperbox`

4. Clear caches:
   ```bash
   drush cr
   ```

## Dependencies

### Drupal Core Modules
- `system`
- `block`
- `field`
- `datetime`

### Contributed Modules
- `select2` - Enhanced select form elements
- `media_library_form_element` - Media library form widget
- `menu_item_extras` - Extended menu item features
- `config_pages` - Configurable page entities
- `conditional_fields` - Conditional form field logic

## Configuration

The module provides extensive configuration options accessible at `/admin/config/development/customhelperbox`:

- **Enable Helperbox**: Toggle module features
- **Enable custom media thumbnail**: Allow custom media thumbnails via `field_custom_thumbnail`
- **CDN Configuration**: Enable/disable CDN for Select2 and lightGallery libraries
- **Field Access Rules**: Configure field access based on content type and bundle
- **Maximum Node Limits**: Set node count limits per content type
- **Validation Rules**: Configure title and country code uniqueness validation

## Routes and Paths

| Route Name | Path | Description |
|------------|------|-------------|
| `helperbox.settings` | `/admin/config/development/customhelperbox` | Module settings form |
| `helperbox.api_test` | `/api/testhelperbox` | Test API endpoint (JSON) |

## Services

| Service ID | Class | Description |
|------------|-------|-------------|
| `helperbox.loader_event_subscriber` | `Drupal\helperbox\EventSubscriber\LoaderEventSubscriber` | Event subscriber for page load events |

## Usage Examples

### Using Custom Views Fields

1. Create a new View or edit an existing one
2. Add a field and select from HelperBox custom fields:
   - **CTA Button**: Add configurable call-to-action buttons
   - **Node Counter**: Display node counts with conditions
   - **Block Renderer**: Render blocks within Views
   - **Custom HTML Text**: Display formatted HTML content
   - **Media Adder**: Add media elements to Views
3. Configure the field options as needed

### Using Custom Blocks

1. Navigate to **Structure > Block Layout**
2. Click **Place Block** in your desired region
3. Select a HelperBox block under the **Custom** category
4. Configure the block settings:
   - Banner Block: Add titles, body, CTAs, media, and highlights
   - Contact Block: Configure contact info, social links, and webforms
   - Content Block: Select content types and display options
   - Menu Block: Configure menu layout and social links
   - Repeated Content Block: Add reusable content sections
5. Save and place the block

### Using Custom Field Formatters

1. Navigate to **Structure > Content Types** and edit a content type
2. Go to the **Manage Display** tab
3. For a field, select a HelperBox formatter:
   - **Phone Number Formatter**: For clickable phone links
   - **Media Information Formatter**: For file details display
   - **Date Range Formatter**: For custom date formatting
4. Adjust formatter settings as needed
5. Save the display settings

### Using the Date/Time Widget

1. Create or edit a datetime field on a content type
2. In **Manage Form Display**, select **Helperbox Date Time Widget**
3. Configure widget settings:
   - Date order (YMD, MDY, DMY, etc.)
   - Time type (12/24 hour)
   - Year range
   - Optional time selection
4. Save the form display settings

## Development Notes

This module implements numerous Drupal hooks and follows Drupal coding standards. It includes extensive error handling and logging capabilities.

### Key Hooks Implemented

- `hook_theme()` - Custom theme implementations
- `hook_preprocess_*()` - Template preprocessing
- `hook_form_*_alter()` - Form alterations
- `hook_entity_*()` - Entity hooks
- `hook_views_*()` - Views hooks
- `hook_menu_link_alter()` - Menu alterations

### Coding Standards

- Follows Drupal 11 coding standards
- Uses PHP 8+ features where appropriate
- Implements proper type hinting and return types
- Uses Drupal's dependency injection where applicable

## Troubleshooting

### Schema Errors

If you encounter schema errors after updating the module:

```bash
# Clear caches
drush cr

# Re-import configuration
drush config-import -y
```

### Configuration Issues

To reset module settings:

```bash
# Delete module configuration
drush config-delete helperbox.settings -y

# Re-import from install directory
drush config-import -y
```

## References

1. https://www.drupal.org/documentation
2. https://drupalize.me/
3. https://www.drupal.org/docs/creating-modules/creating-custom-blocks/create-a-custom-block-plugin
4. https://www.drupal.org/docs/user_guide/en/block-create-custom.html
5. https://www.drupal.org/project/media_library_form_element
6. https://drupal.stackexchange.com/questions/267317/how-can-i-use-a-media-field-in-a-custom-form
7. https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
8. https://www.drupal.org/docs/develop/drupal-apis/javascript-api/ajax-forms
9. https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21theme.api.php/function/hook_preprocess_HOOK/10
10. https://www.youtube.com/@WebWash
11. https://drupalize.me/tutorial/concept-entity-queries

## Changelog

### Version 11.3.1
- Added `helperbox_views_field_cta` Views field plugin
- Updated schema definitions for all Views fields
- Improved CTA button with dynamic query parameters
- Added entity autocomplete for CTA links
- Fixed configuration schema warnings
