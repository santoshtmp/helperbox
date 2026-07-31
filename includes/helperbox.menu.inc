<?php

/**
 * @file
 * Menu hooks for Helperbox module.
 */

use Drupal\helperbox\Helper\MediaHelper;

/**
 * Implements hook_helperbox_menu_item_alter().
 *
 * @param array $menu_item
 *   The menu item array being built (passed by reference).
 * @param \Drupal\Core\Menu\MenuLinkInterface $link
 *   The menu link plugin object.
 * @param string $menu_region_location
 *   The location of the menu region.
 */
function helperbox_helperbox_menu_item_alter(&$menu_item, $link, $menu_region_location) {
    try {
        $menu_item_type = ($link->getProvider()) ?: '';
        $is_external = $menu_item['is_external'] ?? false;
        if ($menu_item_type === 'menu_link_content') {
            $entity = \Drupal::service('menu_item_extras.menu_link_tree_handler')->getMenuLinkItemEntity($link);
            // field_icon
            if ($entity->hasField('field_icon') && !$entity->get('field_icon')->isEmpty()) {
                $field_icon_id = $entity->get('field_icon')->entity->id();
                if ($field_icon_id) {
                    $menu_item['field_icon'] = MediaHelper::get_media_library_info($field_icon_id);
                }
            }
            // field_override_external_link
            if ($entity->hasField('field_override_external_link') && !$entity->get('field_override_external_link')->isEmpty() && $is_external) {
                $override_link = $entity->get('field_override_external_link')->first()->getUrl();
                if ($override_link) {
                    $menu_item['url'] = $override_link;
                }
            }

            // field_description
            if ($entity->hasField('field_description') && !$entity->get('field_description')->isEmpty()) {
                $menu_item['field_description'] = $entity->get('field_description')->value;
            }

            // field_cta
            if ($entity->hasField('field_cta') && !$entity->get('field_cta')->isEmpty()) {
                $cta_field = $entity->get('field_cta')->first();

                // Link field returns uri and title
                $uri   = $cta_field->uri;
                $label = $cta_field->title;

                // Convert uri to Url object
                $url = \Drupal\Core\Url::fromUri($uri);

                $attributes = [];
                $attributes['class'] = 'cta-wrapper link-btn';
                $menu_item['field_cta'] = [
                    '#theme'       => 'helperbox_component_cta',
                    '#cta_url'     => $url->toString(),
                    '#cta_label'   => !empty($label) ? $label : $url->toString(),
                    '#cta_type'    => 'link',
                    '#cta_target'  => NULL,
                    '#is_external' => $url->isExternal(),
                    '#is_no_link'  => FALSE,
                    '#attributes'  => new \Drupal\Core\Template\Attribute($attributes),
                ];
            }

            // field_menu_location
            if ($entity->hasField('field_menu_location') && !$entity->get('field_menu_location')->isEmpty()) {
                $field_menu_locations = array_column(
                    $entity->get('field_menu_location')->getValue(),
                    'value'
                );
                if (!empty($field_menu_locations) && is_array($field_menu_locations)) {
                    $menu_item['field_menu_location'] = $field_menu_locations;
                }
                // Check if current menu_region_location matches any of the stored values.
                $is_default        = ($menu_region_location === 'default');
                $is_location_match = in_array($menu_region_location, $field_menu_locations);

                if ($is_default || $is_location_match) {
                    // Store all location values in the menu item.
                    $menu_item['field_menu_location'] = $field_menu_locations;
                } else {
                    // No match found — exclude this item from the menu.
                    $menu_item = NULL;
                }
            }
        }
    } catch (\Throwable $th) {
        //throw $th;
    }
}


/**
 * Implements hook_helperbox_menu_other_variables_alter().
 *
 * @param array $other_variables
 *   An array of other variables to be passed to the template (passed by reference).
 * @param array $config
 *   The block configuration array.
 */
function helperbox_helperbox_menu_other_variables_alter(&$other_variables, $config) {
    try {
        // Implementation for altering other variables
    } catch (\Throwable $th) {
        //throw $th;
    }
}
