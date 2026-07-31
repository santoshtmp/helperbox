<?php

namespace Drupal\helperbox\Helper;


use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\views\Views;

use function PHPSTORM_META\type;

/**
 * Reference:
 * https://www.drupal.org/documentation
 * https://drupal.stackexchange.com/questions/223406/how-do-i-programmatically-load-a-custom-block
 * https://www.drupal.org/forum/support/module-development-and-code-questions/2018-04-15/solved-render-block_content
 * https://api.drupal.org/api/drupal/core%21modules%21block%21block.api.php/group/block_api/11.x
 * https://api.drupal.org/api/drupal/modules%21block%21block.module/function/block_load/7.x
 */

/**
 * Class to get particular block data.
 *
 * Usage examples:
 *   GetBlock::block('creative_sitebranding');           // machine name → full themed output
 *   GetBlock::block('system_branding_block');           // plugin ID    → raw inner content
 *   GetBlock::block('views_block:my_view-block_1');    // plugin ID    → raw inner content
 *   GetBlock::block('creative_sitebranding', [], true); // returns HTML string
 *   GetBlock::content_block_data(7);
 *   GetBlock::settings_data('fimi_footerdescription');
 */
class GetBlock {

    /**
     * Load a block by its machine name and return its settings/data.
     *
     * @param string $block_machine_id  The placed block machine name (e.g. 'creative_sitebranding').
     *
     * @return array
     */
    public static function settings_data($block_machine_id) {
        $settings_data = [];
        try {
            // Load the block by its ID.
            $block = Block::load($block_machine_id);
            if ($block) {
                $settings = $block->get('settings');
                $settings['block_id'] = $block->id();
                $settings['region']    = $block->getRegion();
                $settings['plugin_id'] = $block->getPluginId();

                $settings_data = $settings;

                if ($settings['provider'] == 'helperbox') {
                    // Cast plugin_id to string to avoid markup object issues.
                    $plugin_id_str = (string) $settings['plugin_id'];
                    $plugin = \Drupal::service('plugin.manager.block')->createInstance($plugin_id_str, []);
                    // Get the build data from the block plugin.
                    $settings_data = $plugin->build();
                } elseif ($settings['provider'] == 'views') {
                    if (strpos($settings['plugin_id'], 'views_block:') === 0) {
                        [$prefix, $view_info] = explode(':', $settings['plugin_id']);
                        [$view_name, $display_id] = explode('-', $view_info, 2);
                        $settings_data = self::view_block_data($view_name, $display_id);
                    }
                } elseif ($settings['provider'] == 'block_content') {
                    // Reserved for future block_content handling.
                    // $block_manager = \Drupal::service('plugin.manager.block');
                    // $plugin_block = $block_manager->createInstance($settings['plugin_id'], []);
                    // $render_array = $plugin_block->build();
                }
            }
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
        return $settings_data;
    }

    /**
     * Load a custom block content entity and return its field values.
     *
     * @param int $block_id  The block content entity ID (e.g. 7).
     *
     * @return array
     */
    public static function content_block_data($block_id) {
        $content_data = [];
        try {
            // Load the block content by its ID. (Example: 1)
            $block_content = BlockContent::load((int) $block_id);

            if ($block_content) {
                // Get the block ID and type.
                $content_data['block_id'] = $block_content->id();
                $content_data['block_type'] = $block_content->bundle();
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
     * Get raw result rows from a Views block display.
     *
     * @param string $view_machine_name  The view machine name.
     * @param string $display_id         The display ID (e.g. 'block_1').
     *
     * @return array|false
     */
    public static function view_block_data($view_machine_name, $display_id) {
        try {
            // Load the view by its machine name.
            $view = Views::getView($view_machine_name);
            if ($view) {
                if (!$view->access($display_id)) {
                    return [];
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
     *
     * @param string $view_id      The view machine name.
     * @param string $display_id   The display ID (e.g. 'block_1').
     * @param array  $args  Optional arguments to pass to the view.
     *
     * @return array|false
     */
    public static function get_rendered_views_block(string $view_id, string $display_id, $args = [], $return = 'render') {
        try {
            if (empty($view_id) || empty($display_id)) {
                return [];
            }

            $view = Views::getView($view_id);

            if (!$view || !$view->access($display_id)) {
                return [];
            }

            $view->setDisplay($display_id);
            $view->setArguments($args);
            $view->preExecute($args);
            $view->execute();

            if (empty($view->result)) {
                return [];
            }
            if ('view_result' == $return) {
                return $view->result;
            }

            $render = $view->render($display_id);

            switch ($return) {
                case 'header':
                    $output = $render['#header'] ?? [];
                    break;
                case 'more':
                    $output = $render['#more'] ?? [];
                    break;
                case 'rows':
                    $output = $render['#rows'] ?? [];
                    break;
                case 'render':
                default:
                    $output = $render;
                    break;
            }

            // Optional: set arguments, exposed input, etc.
            // $view->setExposedInput(['field_foo' => 'bar']);

            // This builds the full render array exactly as the block would appear
            // $render = $view->buildRenderable($display_id);
            // $render = $view->render($display_id);
            // $output = $view->preview($display_id, $args);

            // $render  = \Drupal::service('renderer')->renderPlain($render);

            return $output;
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
        return false;
    }

    /**
     * Render a block by either its MACHINE NAME or PLUGIN ID.
     *
     * Auto-detects input type:
     *   - If a placed block entity exists with that machine name → full themed render
     *     (with block.html.twig wrapper, CSS block ID, contextual links, theme classes)
     *   - If no entity found → treats as plugin ID → raw inner content render
     *
     * Examples:
     *   GetBlock::render_block('creative_sitebranding');            // machine name → full themed output
     *   GetBlock::render_block('system_branding_block');            // plugin ID    → raw inner content
     *   GetBlock::render_block('views_block:my_view-block_1');     // plugin ID    → raw inner content
     *   GetBlock::render_block('creative_sitebranding', [], true);  // returns HTML string
     *
     * @param string $block_id    Block machine name OR plugin ID.
     * @param array  $config      Optional config (only used in plugin ID mode).
     * @param bool   $renderhtml  If TRUE, returns HTML string; otherwise render array.
     *
     * @return array|string
     */
    public static function render_block($block_id, array $config = [], $renderhtml = false) {
        // Cast to string to guard against ViewsRenderPipelineMarkup or other objects.
        $block_id = (string) $block_id;

        if (empty($block_id)) {
            return [];
        }

        /** @var \Drupal\Core\Render\RendererInterface $renderer */
        $renderer = \Drupal::service('renderer');
        $account  = \Drupal::currentUser();

        // -------------------------------------------------------------------------
        // Auto-detect: try loading as a placed block entity first.
        // -------------------------------------------------------------------------
        try {
            $block_entity = Block::load($block_id);

            if ($block_entity) {
                // Access check.
                if (!$block_entity->access('view', $account)) {
                    return [];
                }

                // Renders with full block.html.twig wrapper, contextual links,
                // block CSS ID, theme classes etc.
                $build = \Drupal::entityTypeManager()
                    ->getViewBuilder('block')
                    ->view($block_entity);

                if (empty($build)) {
                    return [];
                }

                if ($renderhtml) {
                    return (string) $renderer->render($build);
                }

                return $build;
            }
        } catch (\Throwable $th) {
            // Log error but continue — will fall through to plugin ID attempt below.
            \Drupal::logger('helperbox')->error('Block entity render error for @id: @message', [
                '@id'      => $block_id,
                '@message' => $th->getMessage(),
            ]);
        }

        // -------------------------------------------------------------------------
        // No entity found → treat as plugin ID → raw inner content.
        // -------------------------------------------------------------------------
        try {
            /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
            $block_manager = \Drupal::service('plugin.manager.block');

            // Guard against unknown plugin IDs.
            if (!$block_manager->hasDefinition($block_id)) {
                // At this point neither entity nor plugin was found — this is a real problem.
                $message = 'Block not found as entity or plugin: ' . $block_id;
                \Drupal::logger('helperbox')->warning($message);
                \Drupal::messenger()->addWarning($message);
                return [];
            }

            // Instantiate the block plugin.
            $block = $block_manager->createInstance($block_id, $config);

            // Access check — supports both AccessResultInterface and legacy bool.
            $access = $block->access($account);
            if ($access instanceof \Drupal\Core\Access\AccessResultInterface) {
                if (!$access->isAllowed()) {
                    return [];
                }
            } elseif ($access === FALSE) {
                return [];
            }

            // Build the render array.
            $build = $block->build();

            if (empty($build)) {
                return [];
            }

            if ($renderhtml) {
                return (string) $renderer->render($build);
            }

            return $build;
        } catch (\Throwable $th) {
            \Drupal::logger('helperbox')->error('Block plugin render error for @id: @message', [
                '@id'      => $block_id,
                '@message' => $th->getMessage(),
            ]);
        }

        // Both attempts failed — return empty.
        return [];
    }
}
