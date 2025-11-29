<?php

/**
 * https://www.drupal.org/docs/creating-modules/creating-custom-blocks/create-a-custom-block-plugin
 * 
 * core\modules\navigation\src\Plugin\Block\NavigationMenuBlock.php
 */

namespace Drupal\helperbox\Helper;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Template\Attribute;

/**
 * MenuHelper
 * version 1.0.0
 * time 2025091500
 */
class MenuHelper {

    /**
     * Get a list of all menus.
     */
    public static function get_all_menus() {
        $menus = \Drupal::entityTypeManager()->getStorage('menu')->loadMultiple();
        $menu_list = [];
        foreach ($menus as $menu) {
            $menu_list[$menu->id()] = $menu->label();
        }
        return $menu_list;
    }

    /**
     * Gets the title of a menu by its machine name.
     *
     * @param string $menu_name
     *   The machine name of the menu.
     *
     * @return string|null
     *   The title of the menu or NULL if not found.
     */
    public static function get_menu_title($menu_name = 'main') {
        $menu_storage = \Drupal::entityTypeManager()->getStorage('menu');
        $menu = $menu_storage->load($menu_name);
        return $menu ? $menu->label() : NULL;
    }

    /**
     * https://www.drupal.org/project/menu_item_extras/
     * https://lembergsolutions.com/blog/get-fieldable-drupal-menu-menu-item-extras-overview
     * core\modules\navigation\src\Plugin\Block\NavigationMenuBlock.php
     * @param string $menu_name Default menu name in Drupal
     */
    public static function get_menu_items($menu_name = 'main', $max_menu_levels = 1) {
        $parameters = new MenuTreeParameters();
        $parameters->onlyEnabledLinks();
        $menu_tree = \Drupal::menuTree();
        $tree = $menu_tree->load($menu_name, $parameters);
        // Process tree (apply permissions, sorting)
        $manipulators = [
            ['callable' => 'menu.default_tree_manipulators:checkAccess'],
            ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
        ];
        $tree = $menu_tree->transform($tree, $manipulators);
        // Convert tree to custom array
        return self::build_menu_array($tree,  $max_menu_levels);
    }

    /**
     * Recursively build a custom array from menu tree items.
     */
    public static function build_menu_array(array $tree, $max_menu_levels,  $menu_level = 1) {
        $menu_items = [];
        $current_path = \Drupal::service('path.current')->getPath();

        foreach ($tree as $element) {
            if ($max_menu_levels != 0 && $menu_level > $max_menu_levels) {
                break;
            }
            /** @var \Drupal\Core\Menu\MenuLinkTreeElement $element */
            $link = $element->link; // Get menu link object
            $subtree = $element->subtree; // Check if it has children
            // Get the menu item's URL
            $url_object = $link->getUrlObject();
            $is_external = $url_object->isExternal();
            // Get the menu item's URL
            $is_active = ($url_object->toString() === $current_path) ? true : false;
            // 
            $menu_item_type = ($link->getProvider()) ?: '';

            // Create menu item array
            $menu_item = [
                'id' => $link->getPluginId(),
                'title' => $link->getTitle(),
                'url' => $url_object,
                'is_external' => $is_external,
                'weight' => $link->getWeight(),
                'menu_level' => $menu_level,
                'menu_item_type' => $menu_item_type,
                'is_active' => $is_active,
                'in_active_trail' => $element->inActiveTrail,
                'attributes' => new Attribute(),
            ];

            // 
            try {
                if ($menu_item_type === 'menu_link_content') {
                    $entity = \Drupal::service('menu_item_extras.menu_link_tree_handler')->getMenuLinkItemEntity($link);
                    // field_description
                    if ($entity->hasField('field_description') && !$entity->get('field_description')->isEmpty()) {
                        if (!$entity->get('field_description')->isEmpty()) {
                            $menu_item['field_description'] = $entity->get('field_description')->value;
                        }
                    }
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
                }
            } catch (\Throwable $th) {
                //throw $th;
            }

            // If the menu item has below - children, process them recursively
            if (!empty($subtree)) {
                $menu_item['below'] = self::build_menu_array($subtree, $max_menu_levels, $menu_level + 1);
            }

            // Add to menu items array
            $menu_items[] = $menu_item;
        }
        return $menu_items;
    }
}
