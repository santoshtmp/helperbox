<?php

/**
 * @file
 * Theme-related hooks for Helperbox module.
 */

/**
 * Implements hook_theme().
 */
function helperbox_theme() {
    $theme = [];

    $theme['helperbox_menu'] = [
        'variables' => [
            'menu_layout' => '',
            'menu_region_location' => '',
            'menu_layout_location' => '',
            'menu_layout_subtype' => '',
            'mobile_menu_panel_title' => '',
            'menu_type' => '',
            'menu_title' => '',
            'menu_items' => [],
            'social_link_title' => '',
            'other_variables' => [],
            'background_image' => '',
            'attributes' => [],
        ],
        'template' => 'helperbox-menu',
    ];
    $theme['helperbox_banner'] = [
        'variables' => [
            'section_title' => '',
            'section_sub_title' => '',
            'section_body' => '',
            'feature_video' => [],
            'feature_image' => [],
            'cta_links' => [],
            'video_links' => [],
            'block_layout' => '',
            'section_highlight' => '',
            'section_highlight_enable' => '',
            'background_image' => '',
            'secondary_body_enable' => '',
            'section_body_title_2' => '',
            'section_body_desc_2' => '',
            'lottie_file' => '',
        ],
        'template' => 'helperbox-banner',
    ];

    $theme['helperbox_repeatedcontent'] = [
        'variables' => [
            'block_layout' => '',
            'section_title' => '',
            'section_sub_title' => '',
            'content_list' => [],
            'other_meta_info' => '',
        ],
        'template' => 'helperbox-repeatedcontent',
    ];
    $theme['helperbox_block_herobanner'] = [
        'variables' => [
            'page_type' => '',
            'entity_type' => '',
            'entity_bundle' => '',
            'banner_data' => [],
        ],
        'template' => 'helperbox-block-herobanner',
    ];
    $theme['helperbox_renderblock'] = [
        'variables' => [
            'content' => '',
            'block_type' => '',
            'block_plugin_id' => '',
            'view_id' => '',
            'display_id' => '',
            'adminlinks' => [],
            'attributes' => [],
        ],
        'template' => 'helperbox-renderblock',
    ];

    // CTA button field template for Views. 
    $theme['helperbox_component_cta'] = [
        'variables' => [
            'cta_url' => '',
            'cta_label' => '',
            'cta_type' => 'primary',
            'cta_target' => '',
            'attributes' => [],
            'url_type' => 'internal',
            'is_external' => FALSE,
            'is_no_link' => FALSE,
            'wrapper_attributes' => [],
            'cta_info' => [],
        ],
        'template' => 'helperbox-component-cta',
    ];

    // pdf viewer template. 
    $theme['helperbox_component_pdfviewer'] = [
        'variables' => [
            'file_data' => [],
            'attributes' => [],
        ],
        'template' => 'helperbox-component-pdfviewer',
    ];

    $theme['views_view_helperbox_slider_swiper'] = [
        'variables' => [],
        'base hook' => 'views_view_unformatted',
    ];

    return $theme;
}

/**
 * Implements template suggestion for helperbox_menu
 */
function helperbox_theme_suggestions_helperbox_menu(array $variables) {
    $suggestions = [];
    $template_layout_suggestion = 'helperbox_menu__layout_' . $variables['menu_layout'];
    $suggestions[] = $template_layout_suggestion;

    // Add menu region location suggestion if available.
    $menu_region_location = $variables['menu_region_location'] ?? '';
    if ($menu_region_location) {
        $template_layout_region_suggestion = $template_layout_suggestion . '__region_' . str_replace([' ', '-'], '_', $menu_region_location);
        $suggestions[] = $template_layout_region_suggestion;

        // Add menu type suggestion if menu region location is available.
        $menu_type = $variables['menu_type'] ?? '';
        if ($menu_type) {
            $template_layout_region_type_suggestion = $template_layout_region_suggestion . '__menu_' . str_replace([' ', '-'], '_', $menu_type);
            $suggestions[] = $template_layout_region_type_suggestion;
        }
    }

    // Add menu type suggestion without region location as well.
    $suggestions[] = 'helperbox_menu__menu_' . str_replace([' ', '-'], '_', $variables['menu_type']);
    $suggestions[] = $template_layout_suggestion . '__menu_' . str_replace([' ', '-'], '_', $variables['menu_type']);

    return $suggestions;
}

/**
 * Implements template suggestion for helperbox_banner
 */
function helperbox_theme_suggestions_helperbox_banner(array $variables) {

    $suggestions = [];
    if (isset($variables['block_layout'])) {
        $suggestions[] = 'helperbox_banner__layout_' . $variables['block_layout'];
    }
    return $suggestions;
}

/**
 * Implements template suggestion for helperbox_repeatedcontent
 */
function helperbox_theme_suggestions_helperbox_repeatedcontent(array $variables) {

    $suggestions = [];
    if (isset($variables['block_layout'])) {
        $suggestions[] = 'helperbox_repeatedcontent__layout_' . $variables['block_layout'];
    }

    return $suggestions;
}

/**
 * Implements template suggestion for helperbox_block_herobanner
 */
function helperbox_theme_suggestions_helperbox_block_herobanner(array $variables) {
    $suggestions = [];
    $entity_type = $variables['entity_type'] ?? '';
    if ($entity_type) {
        $suggestions[] = 'helperbox_block_herobanner__' . $entity_type;
    }
    $entity_bundle = $variables['entity_bundle'] ?? '';
    if ($entity_type && $entity_bundle) {
        $suggestions[] = 'helperbox_block_herobanner__' . $entity_type . '__' . $entity_bundle;
    }
    return $suggestions;
}

// /**
//  * Implements hook_theme_suggestions_page_alter().
//  */
// function helperbox_theme_suggestions_page_alter(&$suggestions, $variables, $hook) {
//     if (\Drupal::routeMatch()->getRouteName() == 'entity.node.canonical') {
//         $node = \Drupal::routeMatch()->getParameter('node');
//         $content_type = $node->bundle();
//         $suggestions[] = 'page__type__' . $content_type;
//     }
// }

// Implements hook_theme_suggestions_HOOK_alter() for form templates.
function helperbox_theme_suggestions_form_alter(array &$suggestions, array $variables) {
    $element = $variables['element'];
    $form_id = $element['#id'] ?? NULL;
    // Add suggestion for search block form
    if ($form_id) {
        // This will allow a template named form--search-block-form.html.twig
        $suggestions[] = 'form__' . str_replace('-', '_', $form_id);
    }
}

/**
 * Implements hook_theme_suggestions_views_view_field_alter().
 */
function helperbox_theme_suggestions_views_view_field_alter(array &$suggestions, array $variables) {

    $view = $variables['view'];
    $field = $variables['field'];
    $row = $variables['row'];
    //
    $view_id = $view->id();
    $display_id = $view->current_display;
    $field_name = $field->field;
    $entity = $row->_entity ?? NULL;

    if ($entity instanceof \Drupal\Core\Entity\ContentEntityInterface) {
        if (!$entity->hasField($field_name)) {
            return;
        }
        /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field */
        $entity_field = $entity->get($field_name);
        if ($entity_field instanceof \Drupal\Core\Field\EntityReferenceFieldItemListInterface) {

            $field_def = $entity_field->getFieldDefinition();
            $field_type = $field_def->getType();
            $target_type = $field_def->getSetting('target_type') ?? '';
            if ($target_type == 'paragraph' && $field_type == 'entity_reference_revisions') {
                $counter = 1;
                foreach ($entity_field->referencedEntities() as $paragraph) {
                    $field_name_id = $field->options['id'];
                    // Attach view context to the paragraph entity.
                    $paragraph->_helperbox_view_context = [
                        'counter' => $counter++,
                        'view_id' => $view_id,
                        'display_id' => $display_id,
                        'field_name' => $field_name,
                        'field_name_id' => $field_name_id,
                        'entity_type' => $entity->getEntityTypeId(),
                        'entity_id' => $entity->id(),
                        'entity_bundle' => $entity->bundle(),
                    ];
                }
            }
        }
    }
    // Add suggestions based on view, display, and field name.
    $suggestions[] = "views_view_field__" . $field_name;
    $suggestions[] = "views_view_field__" . $field_name . "__" . $view_id;
    $suggestions[] = "views_view_field__" . $field_name . "__" . $view_id . "__" . $display_id;
    // Remove duplicates
    $suggestions = array_unique($suggestions);
}

// Implements hook_theme_suggestions_paragraph_alter().
function helperbox_theme_suggestions_paragraph_alter(array &$suggestions, array $variables) {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['elements']['#paragraph'];

    if (!$paragraph instanceof \Drupal\paragraphs\Entity\Paragraph) {
        return;
    }

    // Get parent entity (node)
    $parent = $paragraph->getParentEntity();
    $current_path = \Drupal::service('path.current')->getPath();
    // // $alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path);
    // $isFrontPage = \Drupal::service('path.matcher')->isFrontPage() ? 'frontpage' : '';
    $current_path = str_replace('/', '', trim($current_path, '/'));

    // Add suggestions
    $suggestions[] = 'paragraph__' . $paragraph->bundle() . '__path_' . $current_path;
    // check paragraph parent node
    if ($parent instanceof \Drupal\Core\Entity\EntityInterface && $parent->getEntityTypeId() === 'node') {
        $node = $parent;
        $node_id = $node->id();
        $field_name = $paragraph->get('parent_field_name')->value ?? NULL;

        // Add suggestions
        $suggestions[] = 'paragraph__' . $paragraph->bundle() . '__' . $node->bundle();
        $suggestions[] = 'paragraph__' . $paragraph->bundle() . '__' . $node->bundle() . '__' . $field_name;
        $suggestions[] = 'paragraph__' . $paragraph->bundle() . '__node_' . $node_id;
        $suggestions[] = 'paragraph__' . $paragraph->bundle() . '__node_' . $node_id . '__' . $field_name;
        $suggestions[] = 'paragraph__' . $paragraph->bundle() . '__node_' . $node_id . '__path_' . $current_path;
    }

    // Suggest template based on view context (Views preview)
    if (isset($paragraph->_helperbox_view_context)) {
        $view_context = $paragraph->_helperbox_view_context;
        if (isset($view_context['view_id']) && isset($view_context['display_id'])) {
            $suggestions[] = 'paragraph__' . $paragraph->bundle() . '__view_' . $view_context['view_id'] . '_' . $view_context['display_id'];
            $suggestions[] = 'paragraph__' . $paragraph->bundle() . '__view_' . $view_context['view_id'] . '_' . $view_context['display_id'] . '__fieldid_' . $view_context['field_name_id'];
        }
    }
}

// Implements hook_theme_suggestions_field_alter().
function helperbox_theme_suggestions_field_alter(array &$suggestions, array $variables) {
    $field_name = $variables['element']['#field_name'] ?? '';
    if (!$field_name) {
        return;
    }
    // Get the entity that owns the field (paragraph or node)
    $entity = $variables['element']['#object'] ?? NULL;
    if (!$entity) {
        return;
    }
    //
    $base_field = 'field__' . $entity->getEntityTypeId() . '__' . str_replace('-', '_', $field_name) . '__' . $entity->bundle();
    if ($entity->getEntityTypeId() == 'paragraph') {
        $nid = $entity->get('parent_id')->value;
        // $node = \Drupal\node\Entity\Node::load($nid);
        $suggestions[] = $base_field . '__nid_' . $nid;
    }
    if ($entity->getEntityTypeId() == 'node') {
        $suggestions[] = $base_field . '__nid_' . $entity->id();
    }
}

// Implements hook_theme_suggestions_HOOK_alter().
function helperbox_theme_suggestions_colorbox_view_mode_formatter_alter(array &$suggestions, array $variables) {

    if (isset($variables['field_name'])) {
        $suggestions[] = 'colorbox_view_mode_formatter__field_' . $variables['field_name'];
    }

    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof \Drupal\node\NodeInterface) {
        $suggestions[] = 'colorbox_view_mode_formatter__node_' . $node->bundle();
        $suggestions[] = 'colorbox_view_mode_formatter__node_' . $node->bundle() . '_' . $node->id();
    }
}

// Implements hook_theme_suggestions_HOOK_alter().
// <!-- THEME HOOK: 'photoswipe_image_formatter' -->
function helperbox_theme_suggestions_photoswipe_image_formatter_alter(array &$suggestions, array $variables) {

    if (!empty($variables['item'])) {
        $field_name = $variables['item']->getFieldDefinition()->getName();
        $suggestions[] = 'photoswipe_image_formatter__field__' . $field_name;
    }

    $entity = $variables['entity'];
    if ($entity instanceof \Drupal\Core\Entity\EntityInterface) {
        $suggestions[] = 'photoswipe_image_formatter__' . $entity->getEntityTypeId() . '__' . $entity->bundle();

        if ($entity->id()) {
            $suggestions[] = 'photoswipe_image_formatter__' . $entity->getEntityTypeId() . '__' . $entity->bundle() . '__' . $entity->id();
        }
    }
}

/**
 * Implements hook_config_schema_info_alter().
 *
 * Adds the missing 'description' key to core's views.field.date schema
 * to account for stray markup Drupal core persists into config when a
 * Date field's format is set to "Time ago" / "Time hence".
 *
 * @see https://www.drupal.org/project/drupal/issues/... (core bug)
 */
function helperbox_config_schema_info_alter(array &$definitions) {
    if (isset($definitions['views.field.date']['mapping']['time_diff']['mapping'])) {
        $definitions['views.field.date']['mapping']['time_diff']['mapping']['description'] = [
            'type' => 'string',
            'label' => 'Description',
        ];
    }
}
