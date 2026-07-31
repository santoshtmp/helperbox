<?php

namespace Drupal\helperbox\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Views;

/**
 * Provides a Views filter for entity reference fields using a View as source.
 *
 * @ViewsFilter("helperbox_view_entity_reference_field")
 */
class ViewEntityReferenceField extends FilterPluginBase {
    /**
     * Views data registration
     *
     * Registers this filter for entity reference fields
     * using the Views reference method.
     */
    public static function views_data_alter(array &$data, $group = '') {
        $group = $group ?: t('HelperBox');

        $endswith = function ($haystack, $needle) {
            return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
        };

        foreach ($data as $table_name => $table_info) {

            // Get entity type safely
            $parts = explode('__', $table_name);
            $entity_type = $parts[0] ?? NULL;
            if (!$entity_type) {
                continue;
            }

            foreach ($table_info as $field_name => $field_info) {

                // Ignore translated, format and delta.
                if ($endswith($field_name, '_i18n') || $endswith($field_name, '_format') || $field_name === 'delta') {
                    continue;
                }

                // Load field storage.
                $field_storage = FieldStorageConfig::loadByName($entity_type, $field_name);
                if (!$field_storage) {
                    continue;
                }

                $field_type = $field_storage->getType();
                // Only entity reference fields
                if ($field_type !== 'entity_reference') {
                    continue;
                }

                $reference_type = $field_storage->getSetting('target_type');
                $bundles = $field_storage->getBundles();
                if (empty($bundles)) {
                    continue;
                }

                foreach ($bundles as $bundle) {
                    $config = FieldConfig::loadByName($entity_type, $bundle, $field_name);
                    if (!$config) {
                        continue;
                    }

                    $settings = $config->getSettings();
                    $handler = $settings['handler'] ?? 'default';

                    // Only process Views-based reference fields
                    if ($handler !== 'views' && !str_starts_with((string) $handler, 'views:')) {
                        continue;
                    }

                    $handler_settings = $settings['handler_settings'] ?? [];
                    $view_name = $handler_settings['view']['view_name'] ?? NULL;
                    $display_id = $handler_settings['view']['display_name'] ?? NULL;

                    $selective = $field_info;
                    if (!empty($field_info['filter']['title'])) {
                        $title = $field_info['filter']['title'];
                    } elseif (!empty($field_info['title'])) {
                        $title = $field_info['title'];
                    } else {
                        $title = "View Entity Reference Field";
                    }

                    $selective['title'] = t('@title (@field) [Reference method - entity refernce view]', ['@title' => $title, '@field' => $field_name]);
                    $selective['filter']['id'] = 'helperbox_view_entity_reference_field';
                    $selective['filter']['field'] = $field_name;
                    $selective['filter']['view_name']   = $view_name;
                    $selective['filter']['display_id']  = $display_id;
                    $selective['filter']['target_type'] = $reference_type;

                    unset(
                        $selective['argument'],
                        $selective['field'],
                        $selective['relationship'],
                        $selective['sort'],
                        $selective['filter']['title']
                    );
                    $selective['group'] = $group;

                    $data[$table_name][$field_name . '_view_entity_reference'] = $selective;
                }
            }
        }
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function canExpose() {
        return TRUE;
    }

    /**
     * {@inheritdoc}
     */
    protected function defineOptions() {
        $options = parent::defineOptions();

        $options['expose']['contains']['multiple'] = ['default' => FALSE];

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultExposeOptions() {
        parent::defaultExposeOptions();

        $field = $this->definition['field'] ?? $this->options['id'];

        $this->options['expose']['identifier'] = $field . '_view_entity_reference';
    }

    /**
     * Build expose configuration form.
     * {@inheritdoc}
     */
    public function buildExposeForm(&$form, FormStateInterface $form_state) {
        parent::buildExposeForm($form, $form_state);

        $form['expose']['multiple'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Allow multiple selections'),
            '#default_value' => $this->options['expose']['multiple'] ?? FALSE,
        ];
    }

    /**
     * {@inheritdoc}
     * 
     * FIX: Override this to prevent the base class from rendering an empty 
     * <div class="views-left-30"></div> which breaks the Views UI modal layout.
     */
    public function showOperatorForm(&$form, FormStateInterface $form_state) {
        // Do nothing. We have no operators, so we don't want the left column wrapper.
    }

    /**
     * {@inheritdoc}
     * 
     * FIX: Override this to prevent wrapping the value form in 'views-right-70'.
     * Since we removed the left column, the value form should take 100% width.
     */
    protected function showValueForm(&$form, FormStateInterface $form_state) {
        $this->valueForm($form, $form_state);
        // We intentionally do NOT add the 'views-right-70' wrapper here.
    }

    /**
     * {@inheritdoc}
     */
    public function valueForm(&$form, FormStateInterface $form_state) {
        parent::valueForm($form, $form_state);

        $field = $this->definition['field'] ?? NULL;
        $view_name = $this->definition['view_name'] ?? NULL;
        $display_id = $this->definition['display_id'] ?? NULL;
        $options = [];
        $options['All'] = $this->t('- Any -');
        if ($view_name && $display_id) {
            $view = Views::getView($view_name);
            if ($view && $view->setDisplay($display_id)) {
                $view->preExecute();
                $view->execute();
                foreach ($view->result as $row) {
                    $entity = $row->_entity ?? NULL;
                    if ($entity) {
                        $options[$entity->id()] = $entity->label();
                    }
                }
            }
        }

        $value = is_array($this->value) ? reset($this->value) : ($this->value ?? '');
        $element_id = implode('--', array_filter([
            $field,
            $view_name,
            $display_id,
        ]));

        $form['value'] = [
            '#type' => 'select',
            '#title' => $this->options['expose']['label'] ?? $this->adminLabel(),
            '#options' => $options,
            '#default_value' => $value,
            '#id' => $element_id,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function query() {

        if (empty($this->value)) {
            return;
        }

        $this->ensureMyTable();

        $field = $this->definition['field'] ?? $this->realField;
        $column = "{$this->tableAlias}.{$field}_target_id";
        $value = $this->value;

        if (is_array($value)) {
            $value = array_filter($value);
            if (empty($value)) {
                return;
            }
            $this->query->addWhere(
                $this->options['group'],
                $column,
                $value,
                'IN'
            );
        } else {
            $this->query->addWhere(
                $this->options['group'],
                $column,
                $value,
                '='
            );
        }
    }
}
