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
        $set_condition = $form_state->getValue('set_condition') ?? $this->options['set_condition'] ?? [];

        /** @var \Drupal\field\Entity\FieldConfig[] $fields */
        $fields = \Drupal::entityTypeManager()
            ->getStorage('field_config')
            ->loadByProperties([
                'entity_type' => 'node',
                'bundle' => $nodeContentType,
            ]);

        $field_options = [
            'helperbox_other_fields' => $this->t('Other field'),
        ];
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
                '#default_value' => isset($set_condition[$i]['node_field']) ? $set_condition[$i]['node_field'] : '',
                '#options' => $field_options,
                '#prefix' => '<div id="field-wrapper-' . $i . '">',
                '#suffix' => '</div>',
            ];
            $form['set_condition'][$i]['helperbox_other_fields'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Field machine name'),
                '#default_value' => isset($set_condition[$i]['helperbox_other_fields']) ? $set_condition[$i]['helperbox_other_fields'] : '',
                '#states' => [
                    'visible' => [
                        ':input[name="options[set_condition][' . $i . '][node_field]"]' => ['value' => 'helperbox_other_fields'],
                    ],
                ],
            ];
            $form['set_condition'][$i]['helperbox_operation'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Condition operation'),
                '#default_value' => isset($set_condition[$i]['helperbox_operation']) ? $set_condition[$i]['helperbox_operation'] : '',
                '#description' => $this->t('Example: IN, NOT IN, etc.'),
                '#states' => [
                    'visible' => [
                        ':input[name="options[set_condition][' . $i . '][node_field]"]' => ['value' => 'helperbox_other_fields'],
                    ],
                ],
            ];

            $form['set_condition'][$i]['node_value'] = [
                '#type' => 'select',
                '#title' => $this->t('Field value'),
                '#default_value' => isset($set_condition[$i]['node_value']) ? $set_condition[$i]['node_value'] : '',
                '#options' => [
                    'current_node_id' => $this->t('Current Content Node ID'),
                    'current_row_node_id' => $this->t('Current row node ID'),
                    'custom_value' => $this->t('Custom field value')
                ],
            ];
            $form['set_condition'][$i]['custom_value'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Custom field value'),
                '#default_value' => isset($set_condition[$i]['custom_value']) ? $set_condition[$i]['custom_value'] : '',
                '#description' => $this->t('Provide single custom field value to match.'),
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
            
            // Check for other field
            $node_value_type = $condition['node_value'];
            $target_id = '';
            if ($node_value_type == 'current_node_id') {
                $node = \Drupal::routeMatch()->getParameter('node');
                if ($node instanceof \Drupal\node\NodeInterface) {
                    $target_id = $node->id();
                }
                // $target_id = $node ? $node->id() : NULL;
            } elseif ($node_value_type === 'current_row_node_id') {
                if (isset($values->_entity) && $values->_entity instanceof \Drupal\node\NodeInterface) {
                    $target_id = $values->_entity->id();
                } elseif (isset($values->nid)) {
                    $target_id = $values->nid;
                }
            }
            if ($node_value_type == 'custom_value') {
                $target_id = $condition['custom_value'];
            }

            // Add condition
            if ($target_id && $node_field) {
                if ($node_field == 'helperbox_other_fields') {
                    $node_field = $condition['helperbox_other_fields'] ?? '';
                    $operation = $condition['helperbox_operation'] ?? '';

                    if ($node_field && $operation) {
                        $nodequery =  $nodequery->condition($node_field, [$target_id], $operation);
                    }
                } else {
                    $nodequery =  $nodequery->condition($node_field . '.target_id', $target_id);
                }
            }
        }
        return $nodequery->count()->execute();
    }
}
