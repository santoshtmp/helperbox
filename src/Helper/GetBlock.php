<?php

namespace Drupal\helperbox\Helper;


use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\views\Views;

/**
 * Reference:
 * https://www.drupal.org/documentation
 * https://drupal.stackexchange.com/questions/223406/how-do-i-programmatically-load-a-custom-block
 * https://www.drupal.org/forum/support/module-development-and-code-questions/2018-04-15/solved-render-block_content
 * https://api.drupal.org/api/drupal/core%21modules%21block%21block.api.php/group/block_api/11.x 
 * https://api.drupal.org/api/drupal/modules%21block%21block.module/function/block_load/7.x
 */

/**
 * Class to get particular block data
 */
// GetBlock::content_block_data(7);
// GetBlock::content_block_data(8);
// GetBlock::settings_data('fimi_yiplcontenttypeblock');
// GetBlock::settings_data('fimi_footerdescription');
// GetBlock::settings_data('fimi_views_block__home_block_collection_block_1');
class GetBlock {


    /**
     * @param string $block_machine_id Load a block by its machine name (ID). Example: fimi_yiplcontenttypeblock
     */
    public static function settings_data($block_machine_id) {
        $settings_data = [];
        try {
            // Load the block by its ID.
            $block = Block::load($block_machine_id);
            if ($block) {
                $settings = $block->get('settings');
                $settings['block_id'] = $block->id();
                $settings['region'] = $block->getRegion();
                $settings['plugin_id'] = $block->getPluginId();
                // 
                $settings_data =  $settings;
                // 
                if ($settings['provider'] == 'helperbox') {
                    // $plugin_id = $block->get('plugin');
                    $plugin = \Drupal::service('plugin.manager.block')->createInstance($settings['plugin_id'], []);
                    // Get the build data from the block plugin.
                    $settings_data = $plugin->build();
                    // // Optionally, render the block
                    // // $rendered_output = \Drupal::service('renderer')->render($build);
                } elseif ($settings['provider'] == 'views') {
                    if (strpos($settings['plugin_id'], 'views_block:') === 0) {
                        [$prefix, $view_info] = explode(':', $settings['plugin_id']);
                        [$view_name, $display_id] = explode('-', $view_info, 2);
                        $settings_data = self::view_block_data($view_name, $display_id);
                    }
                } elseif ($settings['provider'] == 'block_content') {
                    // $block_manager = \Drupal::service('plugin.manager.block');
                    // $plugin_block = $block_manager->createInstance($settings['plugin_id'], []);
                    // $render_array = $plugin_block->build();
                }
                // 
            }
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
        return $settings_data;
    }

    /**
     * @param int $block_id
     */
    public static function content_block_data($block_id) {
        $content_data = [];
        try {
            // Load the block content by its ID. (Example: 1)
            $block_content = BlockContent::load($block_id);

            if ($block_content) {
                // Get the block ID and type.
                // $content_data['block_id'] = $block_content->id();
                // $content_data['block_type'] = $block_content->bundle();
                // $content_data['body'] = $block_content->get('body')->value ?? '';
                $fields = $block_content->getFields();
                foreach ($fields as $field_name => $field_item) {
                    $value = $field_item->getValue();
                    if (isset($value[0]['value'])) {
                        $content_data[$field_name] = $value[0]['value'];
                    } elseif (isset($value[0]['target_id'])) {
                        $content_data[$field_name] = $value[0]['target_id'];
                    } else {
                        $content_data[$field_name] = $value;
                    }
                }
            }
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
        return $content_data;
    }


    /**
     * 
     */
    public static function view_block_data($view_machine_name, $display_id) {
        try {
            // Load the view by its machine name.
            $view = Views::getView($view_machine_name);
            if ($view) {
                if (!$view || !$view->access($display_id)) {
                    return []; // or throw exception / return empty array
                }
                // Set the display ID to the block display.
                $view->setDisplay($display_id);
                // Build and render the view block.
                // $view->preExecute();
                $view->execute();
                return $view->result;
            }
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
        return false;
    }

    /**
     * Get the fully rendered output of a Views block display.
     */
    public static function get_rendered_views_block(string $view_id, string $display_id) {
        try {
            $view = Views::getView($view_id);

            if (!$view || !$view->access($display_id)) {
                return []; // or throw exception / return empty array
            }

            // Set the display (block_11, page_1, etc.)
            // $view->setDisplay($display_id);
            // $view->execute($display_id);

            // Optional: set arguments, exposed input, etc.
            // $view->setArguments(['arg1']);
            // $view->setExposedInput(['field_foo' => 'bar']);

            // This builds the full render array exactly as the block would appear
            // $render = $view->buildRenderable($display_id);
            // $render = $view->render($display_id);
            $render = $view->preview($display_id, []);

            // $render  = \Drupal::service('renderer')->renderPlain($render);

            return $render;
        } catch (\Throwable $th) {
            //throw $th;
        }
        return false;
    }
}
