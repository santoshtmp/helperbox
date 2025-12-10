<?php

namespace Drupal\helperbox\Helper;

/**
 * Yi Form Field Control class
 *
 * @package Drupal\helperbox\Helper
 */
class FormField {

    /**
     * Applies conditional #access rules on a Node form.
     *
     * @param array &$form
     *   The form render array (passed by reference).
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     * @param $form_id
     * 
     * @return void
     *
     * @throws \InvalidArgumentException
     *   If $form is not an array or $nid is invalid.
     */
    public static function applyFormFieldCondition(&$form, $form_state, $form_id) {
        try {

            if (!is_array($form)) {
                return;
            }

            // Run generic field checks (works on any form).
            self::checkFieldsByFormId($form, $form_state, $form_id);

            // Optional: admin only.
            $admin_context = \Drupal::service('router.admin_context');
            if (!$admin_context->isAdminRoute()) {
                return;
            }

            // Apply node/block specific checks.
            self::applyNodeFormFieldCondition($form, $form_state, $form_id);
            self::applyBlockFormFieldCondition($form, $form_state, $form_id);
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
    }

    /**
     * Applies alterations specific to node add/edit forms.
     *
     * @param array &$form
     *   The form structure.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @param string $form_id
     *   The form ID (e.g., node_article_form).
     */
    public static function applyNodeFormFieldCondition(&$form, $form_state, $form_id) {
        try {
            // Only node add/edit forms.
            if (!str_starts_with($form_id, 'node_') || !str_ends_with($form_id, '_form')) {
                return;
            }

            // Apply validation
            $form['#validate'][] = ['\\Drupal\\helperbox\\Helper\\FormField', 'validateNodeForm'];

            // Get current node safely
            $node = self::getCurrentNodeFromFormState($form_state);
            if (!$node) {
                return;
            }
            $nid    = $node->id();
            $bundle = $node->bundle();
            // Add form classes.
            if ($nid) {
                $form['#attributes']['class'][] = 'node-' . $nid . '-form';
            }
            if ($bundle) {
                $form['#attributes']['class'][] = 'node-type-' . $bundle;
                $form['ntype'] = [
                    '#type' => 'hidden',
                    '#value' => $bundle,
                    '#weight' => -100,
                    '#attributes' => [
                        'id' => 'edit-helperbox-ntype-hidden',
                        'class' => ['helperbox-ntype-tracker'],
                    ],
                ];
            }

            // Attach your JS/CSS library (optional).
            $form['#attached']['library'][] = 'helperbox/node_form_conditional_fields';

            // Add hidden NID.
            if (!isset($form['nid'])) {
                $form['nid'] = [
                    '#type' => 'hidden',
                    '#value' => $nid,
                    '#weight' => -100,
                    '#attributes' => [
                        'id' => 'edit-helperbox-nid-hidden',
                        'class' => ['helperbox-nid-tracker'],
                    ],
                ];
            }
            //
            self::checkAllFields($form, $form_state, $node);
            self::checkNodeFields($form, $form_state, $node);

            // // Optional: pass to JS (if you need it client-side).
            // $form['#attached']['drupalSettings']['nid'] = $nid ?? 0;
            // $form['#attached']['drupalSettings']['bundle'] = $bundle;

            // // Detect if "Add more" was clicked
            // $trigger = $form_state->getTriggeringElement();
            // $is_add_more = $trigger &&
            //     isset($trigger['#ajax']) &&
            //     str_ends_with($trigger['#name'], 'add_more');
            // if ($is_add_more) {
            //     \Drupal::messenger()->addStatus('message');
            // }
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
    }

    /**
     * Applies alterations specific to custom block (block_content) forms.
     *
     * @param array &$form
     *   The form structure.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @param string $form_id
     *   The form ID.
     */
    public static function applyBlockFormFieldCondition(&$form, $form_state, $form_id) {
        try {
            if (!str_starts_with($form_id, 'block_content_') || !str_ends_with($form_id, '_form')) {
                return;
            }

            $entity = '';
            $form_object = $form_state->getFormObject();
            if ($form_object instanceof \Drupal\Core\Entity\EntityFormInterface) {
                $entity = $form_object->getEntity();
            }
            if (!$entity instanceof \Drupal\block_content\BlockContentInterface) {
                return;
            }

            $entity_id    = $entity->id();
            $entity_bundle = $entity->bundle();
            // Add form classes.
            if ($entity_id) {
                $form['#attributes']['class'][] = 'block-' . $entity_id . '-form';
            }
            if ($entity_bundle) {
                $form['#attributes']['class'][] = 'block-type-' . $entity_bundle;
                $form['blocktype'] = [
                    '#type' => 'hidden',
                    '#value' => $entity_bundle,
                    '#weight' => -100,
                    '#attributes' => [
                        'id' => 'edit-helperbox-blocktype-hidden',
                        'class' => ['helperbox-blocktype-tracker'],
                    ],
                ];
            }

            // Add hidden blockid.
            if (!isset($form['id'])) {
                $form['id'] = [
                    '#type' => 'hidden',
                    '#value' => $entity_id,
                    '#weight' => -100,
                    '#attributes' => [
                        'id' => 'edit-helperbox-blockid-hidden',
                        'class' => ['helperbox-blockid-tracker'],
                    ],
                ];
            }
        } catch (\Throwable $th) {
            //throw $th;
            UtilHelper::helperbox_error_log($th);
        }
    }

    /**
     * Get the current node entity from the form state.
     *
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     * @return \Drupal\node\NodeInterface|null
     */
    private static function getCurrentNodeFromFormState(\Drupal\Core\Form\FormStateInterface $form_state) {
        $form_object = $form_state->getFormObject();

        // Check if it's an EntityForm (most forms)
        if ($form_object instanceof \Drupal\Core\Entity\EntityFormInterface) {
            $entity = $form_object->getEntity();
            if ($entity instanceof \Drupal\node\NodeInterface) {
                return $entity;
            }
        }

        // Fallback: use route match (works on add/edit)
        $route_match = \Drupal::routeMatch();
        $node = $route_match->getParameter('node');

        // On add forms, 'node' might be NULL, but 'node_type' exists
        if (!$node && $route_match->getParameter('node_type') instanceof \Drupal\node\Entity\NodeType) {
            $node_type = $route_match->getParameter('node_type');
            $node = self::getTargetEntity('node', $node_type->id());
        }

        return $node instanceof \Drupal\node\NodeInterface ? $node : NULL;
    }

    /**
     * Creates an unsaved entity object for a given entity type and bundle.
     * 
     * @param string $target_type
     *   The entity type ID (e.g., 'node', 'paragraph', 'media').
     *
     * @param string $target_bundles
     *   The bundle/machine name of the entity (e.g., 'article', 'paragraph_type').
     *
     * @return \Drupal\Core\Entity\EntityInterface
     *   A newly created but unsaved entity of the specified type and bundle.
     */
    private static function getTargetEntity($target_type, $target_bundles) {
        return \Drupal::entityTypeManager()
            ->getStorage($target_type)
            ->create(['type' => $target_bundles]);
    }

    /**
     * Applies conditional #access for fields based on Node ID.
     *
     * @param array $form
     *   Form render array.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     * @param \Drupal\node\NodeInterface $node
     *   Current node being edited/created.
     *
     * @return void
     */
    private static function checkNodeFields(array &$form, $form_state, $node,) {
        // Get the defined field rules
        $rules = ConfigSettings::$nodefieldrules;
        $nid    = $node->id();
        $bundle = $node->bundle();

        // Skip if no rules for this bundle
        if (!isset($rules[$bundle])) {
            return;
        }

        foreach ($rules[$bundle] as $nodeId => $fieldConditions) {
            // For all node id
            if ($nodeId == '-1' || $nodeId === 0 || $nodeId < 0) {
                if (is_array($fieldConditions)) {
                    foreach ($fieldConditions as $fieldType => $fieldValue) {
                        if ($fieldType == 'referenceField' && is_array($fieldValue)) {
                            self::checkNodeReferenceField($form, $fieldValue, true);
                        } else {
                            if (is_string($fieldValue)) {
                                $form[$fieldValue]['#access'] = false;
                            }
                            if (is_array($fieldValue)) {
                                foreach ($fieldValue as $keyfield => $condition) {
                                    $form[$keyfield]['#access'] = $condition;
                                }
                            }
                        }
                    }
                }
            }

            // For specific node id
            $thisNodeShow = ($nodeId == $nid) ? true : false;
            if (is_array($fieldConditions)) {
                foreach ($fieldConditions as $fieldType => $fieldValue) {
                    if ($thisNodeShow && $fieldType == 'referenceField' && is_array($fieldValue)) {
                        self::checkNodeReferenceField($form, $fieldValue, $thisNodeShow);
                    } else {
                        if (is_string($fieldValue)) {
                            $form[$fieldValue]['#access'] = $thisNodeShow;
                        }
                        if ($thisNodeShow && is_array($fieldValue)) {
                            foreach ($fieldValue as $keyfield => $condition) {
                                $form[$keyfield]['#access'] = $condition;
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     * Applies nested field rules inside referenceField subforms.
     *
     * @param array $form
     *   Current form (or paragraph subform).
     * @param array $fields
     *   Rules for this reference field level.
     * @param bool $thisNodeShow
     *   Whether rules apply for this node ID.
     *
     * @return void
     */
    private static function checkNodeReferenceField(&$form, $fields, $thisNodeShow) {
        foreach ($fields as $field_name => $fields_access_check) {
            if (is_bool($fields_access_check)) {
                $form[$field_name]['#access'] = $fields_access_check;
            }
            if (is_array($fields_access_check)) {
                if (!isset($form[$field_name]['widget'])) {
                    continue;
                }
                $widget = &$form[$field_name]['widget'];
                foreach (\Drupal\Core\Render\Element::children($widget) as $delta) {
                    if (!isset($widget[$delta]['subform'])) {
                        continue;
                    }
                    $subform = &$widget[$delta]['subform'];
                    foreach ($fields_access_check as $field => $check) {
                        if (is_bool($check)) {
                            $subform[$field]['#access'] = $check;
                        }
                        if ($thisNodeShow && $field == 'referenceField' && is_array($check)) {
                            self::checkNodeReferenceField($subform, $check, $thisNodeShow);
                        }
                    }
                }
            }
        }
    }

    /**
     * Applies global field access rules based on entity type and bundle.
     *
     * @param array &$form
     *   The form render array (passed by reference).
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   Current entity (node, paragraph, etc.) or temporary referenced entity.

     * @return void
     */
    private static function checkAllFields(array &$form, $form_state, $entity) {
        // Get the defined field rules
        $fieldrules = ConfigSettings::$allfieldrules;

        // 
        $entity_type = $entity->getEntityTypeId();         // Get Entity type
        $entity_bundle = $entity->bundle();         // Get Entity bundle

        $field_access_check = [];
        if (isset($fieldrules[$entity_type][$entity_bundle]['field_access_check'])) {
            $field_access_check = $fieldrules[$entity_type][$entity_bundle]['field_access_check'];
        }

        // Iterate over *all* form elements that look like a field widget.
        foreach (\Drupal\Core\Render\Element::children($form) as $field_name) {

            // Skip non-widget elements.
            if (!isset($form[$field_name]['widget'])) {
                continue;
            }

            // Skip if node doesn't have this field
            if (!$entity->hasField($field_name)) {
                continue;
            }

            $field = $entity->get($field_name);
            $field_def = $field->getFieldDefinition();
            $field_type = $field_def->getType();
            // $handler = $field_def->getSetting('handler') ?? '';
            $target_type = $field_def->getSetting('target_type') ?? ''; // This can be entity : node, paragraph, media, user ... 
            $target_bundles = $field_def->getSetting('handler_settings')['target_bundles'] ?? []; // This is content type : resource, team, category ...

            // 
            if (isset($field_access_check[$field_name])) {
                $form[$field_name]['#access'] = $field_access_check[$field_name];
            }

            // Handle entity reference fields
            $is_reference = in_array($field_type, ['entity_reference', 'entity_reference_revisions'], TRUE);
            if ($is_reference) { //&& $field->count() > 0
                $widget = &$form[$field_name]['widget'];
                foreach (\Drupal\Core\Render\Element::children($widget) as $delta) {
                    if (!isset($widget[$delta]['subform'])) {
                        continue;
                    }

                    $subform = &$widget[$delta]['subform'];
                    $referenced_entity = $field->get($delta)->entity ?? NULL;
                    if (!$referenced_entity) {
                        $referenced_entity = self::getTargetEntity($target_type, $target_bundles);
                    }

                    if ($referenced_entity) {
                        self::checkAllFields($subform, $form_state, $referenced_entity);
                    }
                }
            }
        }
    }

    /**
     * Applies check field for the form.
     *
     * @param array &$form
     *   The form render array (passed by reference).
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     * @param $form_id
     * 
     * @return void
     *
     * @throws \InvalidArgumentException
     *   If $form is not an array or $nid is invalid.
     */
    private static function checkFieldsByFormId(&$form, $form_state, $form_id) {
        $formIdFieldsrules = ConfigSettings::$formIdFieldsrules;
        if (isset($formIdFieldsrules[$form_id]) && is_array($formIdFieldsrules[$form_id])) {
            foreach ($formIdFieldsrules[$form_id] as $field_name => $check) {
                if (is_bool($check)) {
                    $form[$field_name]['#access'] = $check;
                }
            }
        }
    }
    /**
     * Recursively filters an array, removing empty or null items.
     *
     * @param array $data
     *   The raw data (may contain [] or false).
     *
     * @return array
     *   Cleaned data.
     */
    private static function filterArrayData(array $data): array {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                $clean = self::filterArrayData($value);   // recurse
                return !empty($clean);                // keep only if something survived
            }
            // Keep everything *except* [] and NULL
            return $value !== [] && $value !== null;
        });
    }

    /** 
     * Form validation handler 
     * 
     * @param array &$form
     *   The form render array (passed by reference).
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     *
     * @return void
     */
    public static function validateNodeForm(array &$form,  \Drupal\Core\Form\FormStateInterface $form_state) {

        // Get current node safely
        $node = self::getCurrentNodeFromFormState($form_state);
        if (!$node) {
            return;
        }

        $type = $node->bundle();
        // Maximum nodes per content type
        if ($node->isNew()) {
            $maxNode = self::maxNodeValidate($type);
            if ($maxNode) {
                $type_label = \Drupal\node\Entity\NodeType::load($type)->label();

                $form_state->setErrorByName(
                    'nid',
                    t('You cannot create a new "@value" node — the maximum number has been reached.', ['@value' => $type_label])
                );
                return;
            }
        }


        // -----------------------------
        // 1. Validate title (unique per type)
        // -----------------------------
        $title_raw = $form_state->getValue('title');
        $title = is_array($title_raw) ? ($title_raw[0]['value'] ?? '') : $title_raw;
        $title = trim($title);

        if ($title !== '') {
            $query = \Drupal::entityQuery('node')
                ->condition('type', $type)
                ->condition('title', $title)
                ->accessCheck(TRUE);

            if (!$node->isNew()) {
                $query->condition('nid', $node->id(), '!=');
            }

            $count = (int) $query->count()->execute();

            if ($count > 0) {
                $form_state->setErrorByName(
                    'title',
                    t('A node with the title "@value" already exists.', ['@value' => $title])
                );
            }
        }

        // -----------------------------
        // 2. Validate field_country_code_3digit (unique per type)
        // -----------------------------
        $code_raw = $form_state->getValue('field_country_code_3digit');
        $code = is_array($code_raw) ? ($code_raw[0]['value'] ?? '') : $code_raw;
        $code = trim($code);

        if ($code !== '') {
            $query = \Drupal::entityQuery('node')
                ->condition('type', $type)
                ->condition('field_country_code_3digit', $code)
                ->accessCheck(TRUE);

            if (! $node->isNew()) {
                $query->condition('nid', $node->id(), '!=');
            }

            $count = (int) $query->count()->execute();

            if ($count > 0) {
                $form_state->setErrorByName(
                    'field_country_code_3digit',
                    t('A node with the Country Code "@value" already exists.', ['@value' => $code])
                );
            }
        }
    }

    /**
     * Maximum nodes per content type
     * 
     * @param string $type
     *   The machine name of the content type (e.g., 'article', 'page').
     *
     * @return bool
     */
    public static function maxNodeValidate($type) {

        $max_nodes = ConfigSettings::$maxContentNodes;

        if (isset($max_nodes[$type]) && $max_nodes[$type] > 0) {
            $existing_count = (int) \Drupal::entityQuery('node')
                ->condition('type', $type)
                ->accessCheck(TRUE)
                ->count()
                ->execute();

            if ($existing_count >= $max_nodes[$type]) {
                return true;
            }
        }

        return false;
    }



    // }

    // END
}
