<?php

declare(strict_types=1);

namespace Drupal\helperbox\Helper;

use Drupal\Core\Config\ImmutableConfig;

/**
 * Helperbox Config Settings class.
 *
 * Provides cached, type-safe access to the `helperbox.settings`
 * configuration object, including field access rules for nodes,
 * forms, and content-type limits.
 *
 * @package Drupal\helperbox\Helper
 */
class HelperboxSettings {

    /**
     * Singleton instance of the class.
     *
     * @var static|null
     */
    protected static ?HelperboxSettings $instance = null;

    /**
     * Cached immutable config object, loaded lazily on first access.
     *
     * @var \Drupal\Core\Config\ImmutableConfig|null
     */
    protected ?ImmutableConfig $config = null;

    /**
     * Protected constructor to prevent direct instantiation.
     */
    protected function __construct() {
    }

    /**
     * Get singleton instance of the class.
     *
     * Uses late static binding so subclasses get their own instance.
     *
     * @return static
     *   The singleton instance.
     */
    public static function get_instance(): static {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Prevent cloning of the instance.
     */
    final protected function __clone() {
    }

    /**
     * Prevent unserializing of the instance.
     *
     * @throws \Exception
     *   Always, since the singleton must not be unserialized.
     */
    final public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Get the helperbox config object, or a single field from it.
     *
     * The config object is loaded once per request and cached on the
     * instance to avoid repeated calls into the config system.
     *
     * @param string $field_name
     *   Optional dotted config key. If empty, the full config object
     *   is returned.
     *
     * @return \Drupal\Core\Config\ImmutableConfig|mixed|false
     *   The config object, the requested value, or FALSE on failure.
     */
    public function get_config(string $field_name = '') {
        try {
            if (null === $this->config) {
                $this->config = \Drupal::config('helperbox.settings');
            }

            if ($field_name === '') {
                return $this->config;
            }

            return $this->config->get($field_name);
        } catch (\Throwable $th) {
            \Drupal::logger('helperbox')->error('Failed to load helperbox.settings config: @message', [
                '@message' => $th->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clear the cached config object.
     *
     * Call this after config has been programmatically updated within
     * the same request, so subsequent reads pick up the new values.
     *
     * @return void
     */
    public function resetConfigCache(): void {
        $this->config = null;
    }

    /**
     * Get field rules from configuration.
     *
     * Rules for field access based on entity type and bundle.
     *
     * Example:
     * [
     *   'entity_type_...' => [
     *     'bundle_...' => [
     *       'field_access_check' => [
     *         'field_...' => true|false,
     *       ],
     *     ],
     *   ],
     * ]
     *
     * @return array
     *   The field rules configuration.
     */
    public function getFieldAllRules(): array {
        $config_rules = $this->get_config('field_all_rules');
        return is_array($config_rules) ? $config_rules : [];
        // $allfieldrules = [
        //     'node' => [
        //         'article' => [
        //             'field_access_check' => [
        //                 'field_related_countries' => false,
        //             ]
        //         ],
        //     ],
        //     'paragraph' => [
        //         'content_item' => [
        //             'field_access_check' => [
        //                 'field_list_items' => false,
        //                 'field_highlight_text' => false,
        //                 'field_file_upload' => false,
        //             ]
        //         ],
        //         'list_item' => [
        //             'field_access_check' => [
        //                 'field_description_2' => false,
        //                 'field_featured_image' => false,
        //                 'field_link' => false,
        //             ]
        //         ],
        //     ],
        // ];
    }

    /**
     * Get node field rules from configuration.
     *
     * Field rules for specific content type and node ID.
     *
     * Example:
     * [
     *   'content_type_...' => [
     *     'node_id_...' => [
     *       'field_...',
     *       'group_...',
     *       [
     *         'field_...' => true|false,
     *       ],
     *       'referenceField' => [
     *         'field_...' => true|false,
     *         'field_...' => [
     *           'field_...' => true|false,
     *           'referenceField' => [
     *             'field_...' => true|false,
     *           ],
     *         ],
     *       ],
     *     ],
     *   ],
     * ]
     *
     * @return array
     *   The node field rules configuration.
     */
    public function getFieldRulesNode(): array {
        $config_rules = $this->get_config('field_rules_node');
        return is_array($config_rules) ? $config_rules : [];
        // $nodefieldrules = [
        //     'article' => [
        //         16 => [
        //             'group_general_section',
        //             [
        //                 'field_cta_action' => true
        //             ],
        //             'referenceField' => [
        //                 'field_content_section' => [
        //                     'field_list_items' => false,
        //                 ]
        //             ]
        //         ]
        //     ],
        //     'page' => [
        //         15 => [
        //             'referenceField' => [
        //                 'field_content_section' => [
        //                     'field_highlight_text' => true,
        //                 ]
        //             ]
        //         ],
        //     ]
        // ];
    }

    /**
     * Get form field rules from configuration.
     *
     * Example:
     * [
     *   'form_id_...' => [
     *     'field_...' => true|false,
     *   ],
     * ]
     *
     * @return array
     *   The form field rules configuration.
     */
    public function getFieldRulesForm(): array {
        $config_rules = $this->get_config('field_rules_form');
        return is_array($config_rules) ? $config_rules : [];
        // $formIdFieldsrules = [
        //     'search_form' => [
        //         'advanced' => false,
        //     ]
        // ];
    }

    /**
     * Get maximum content nodes from configuration.
     *
     * Maximum allowed nodes per content type.
     *
     * Example:
     * [
     *   'content_type_...' => Number,
     * ]
     *
     * @return array
     *   The maximum content nodes configuration.
     */
    public function getFieldRulesMaxContent(): array {
        $config_rules = $this->get_config('field_rules_max_content');
        return is_array($config_rules) ? $config_rules : [];
        // $maxContentNodes = [
        //     'article' => 4
        // ];
    }

    /**
     * Check if unique node/item per content bundle is enabled.
     *
     * @return bool
     *   TRUE if enabled, FALSE otherwise.
     */
    public function isUniqueNodePerBundleEnabled(): bool {
        return (bool) $this->get_config('enable_unique_node_per_bundle');
    }

    /**
     * Get the content types configured for unique-node enforcement.
     *
     * @return array
     *   List of content type machine names.
     */
    public function get_unique_node_content_type(): array {
        $types = $this->get_config('unique_node_content_type');
        return is_array($types) ? $types : [];
    }
}
