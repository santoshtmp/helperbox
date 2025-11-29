<?php

namespace Drupal\helperbox\Plugin\views\field;

use Drupal\field\Entity\FieldConfig;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Attribute\ViewsField;
use Drupal\helperbox\Helper\UtilHelper;

/**
 * 
 * A handler to provide for helperbox_count_node.
 * */
#[ViewsField("helperbox_count_node")]
class CountNode extends FieldPluginBase {

    /**
     * {@inheritdoc}
     */
    public function query() {
        // Do nothing -- to override the parent query.
    }

    /**
     * {@inheritdoc}
     */
    public function defineOptions() {
        $options = parent::defineOptions();
        $options['node_content_type'] = ['default' => ''];
        $options['condition_count'] = ['default' => 0];
        $options['set_condition'] = ['default' => []];
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {

        // Get all configurable fields for a node bundle
        $nodeContentType = $form_state->getValue('node_content_type') ?? $this->options['node_content_type'] ?? '';
        $conditionCount = $form_state->getValue('condition_count') ?? $this->options['condition_count'] ?? 0;

        /** @var \Drupal\field\Entity\FieldConfig[] $fields */
        $fields = \Drupal::entityTypeManager()
            ->getStorage('field_config')
            ->loadByProperties([
                'entity_type' => 'node',
                'bundle' => $nodeContentType,
            ]);

        $field_options = [];
        foreach ($fields as $field_name => $field) {
            $field_type = $field->getType();
            if (in_array($field_type, ['entity_reference'])) {
                $target_type = $field->getSetting('target_type') ?? '';
                if (in_array($target_type, ['node', 'taxonomy_term'])) {
                    $field_options[$field->getName()] = $field->getLabel();
                }
            }
        }

        $form['node_content_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Content Type'),
            '#default_value' =>  $nodeContentType,
            '#options' => UtilHelper::get_all_node_content_type(),
            '#required' => TRUE,
        ];

        $form['condition_count'] = [
            '#type' => 'number',
            '#title' => $this->t('Define total condition set'),
            '#default_value' => $this->options['condition_count'],
            '#min' => 0,
            '#step' => 1,
        ];

        $form['set_condition'] = [
            '#type' => 'table',
            '#tree' => TRUE,
            '#prefix' => '<div id="set-condition-wrapper">',
            '#suffix' => '</div>',
        ];

        for ($i = 0; $i < $conditionCount; $i++) {

            $form['set_condition'][$i]['node_field'] = [
                '#type' => 'select',
                '#title' => $this->t('Select condition field'),
                '#options' => $field_options,
                '#prefix' => '<div id="field-wrapper-' . $i . '">',
                '#suffix' => '</div>',
            ];
            $form['set_condition'][$i]['node_value'] = [
                '#type' => 'select',
                '#title' => $this->t('Field value'),
                '#default_value' => '',
                '#options' => [
                    'current_node_id' => $this->t('Current Content ID'),
                    'custom_value' => $this->t('Custom Content ID value')
                ],
            ];
            $form['set_condition'][$i]['custom_value'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Custom field value'),
                '#default_value' => '',
                '#states' => [
                    'visible' => [
                        ':input[name="options[set_condition][' . $i . '][node_value]"]' => ['value' => 'custom_value'],
                    ],
                ],
            ];
        }

        parent::buildOptionsForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function render(ResultRow $values) {
        if (!$this->sanitizeValue($this->options['node_content_type'])) {
            return '';
        }
        $nodeContentType = $this->sanitizeValue($this->options['node_content_type']);
        $conditionCount = $this->options['condition_count'] ?? 0;
        $setCondition = $this->options['set_condition'] ?? [];
        // 
        $nodequery = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('type', $nodeContentType)
            ->condition('status', 1);
        // 
        if (!$conditionCount) {
            return $nodequery->count()->execute();
        }
        foreach ($setCondition as $key => $condition) {
            $node_field = $condition['node_field'];
            $node_value = $condition['node_value'];
            $target_id = '';
            if ($node_value == 'current_node_id') {
                $node = \Drupal::routeMatch()->getParameter('node');
                $target_id = $node ? $node->id() : NULL;
            }
            if ($node_value == 'custom_value') {
                $target_id = (int)$condition['custom_value'];
            }
            if ($target_id) {
                $nodequery =  $nodequery->condition($node_field . '.target_id', $target_id);
            }
        }
        return $nodequery->count()->execute();

    }
}
