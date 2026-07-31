<?php

declare(strict_types=1);

namespace Drupal\helperbox\Helper;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\block_content\BlockContentInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * Form Field Control class.
 *
 * Applies conditional #access rules to node and block_content forms
 * based on configuration supplied by HelperboxSettings.
 *
 * @package Drupal\helperbox\Helper
 */
class FormField {

    /**
     * Maximum depth allowed when recursing into nested reference fields.
     *
     * Prevents runaway recursion / stack overflows on self-referencing
     * entity reference fields (e.g. paragraphs that reference themselves).
     */
    private const MAX_RECURSION_DEPTH = 10;

    /**
     * Singleton instance of the class.
     *
     * @var static|null
     */
    protected static ?FormField $instance = null;

    /**
     * All field rules loaded from configuration.
     *
     * @var array
     */
    public array $fieldallrules = [];

    /**
     * Node field rules loaded from configuration.
     *
     * @var array
     */
    public array $fieldnoderules = [];

    /**
     * Get singleton instance of the class.
     *
     * Uses late static binding to ensure the correct class
     * instance is returned when extending this class.
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
     * Protected constructor to prevent direct instantiation.
     *
     * Loads the field rules from HelperboxSettings once, on construction.
     */
    protected function __construct() {
        $settings = HelperboxSettings::get_instance();
        $this->fieldallrules = $settings->getFieldAllRules();
        $this->fieldnoderules = $settings->getFieldRulesNode();
    }

    /**
     * Applies conditional #access rules on a Node form.
     *
     * @param array $form
     *   The form render array (passed by reference).
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     * @param string $form_id
     *   The form ID.
     *
     * @return void
     */
    public function applyFormFieldCondition(array &$form, FormStateInterface $form_state, string $form_id): void {
        try {

            if (!is_array($form)) {
                return;
            }

            // Run generic field checks (works on any form).
            $this->checkFieldsByFormId($form, $form_state, $form_id);

            // Optional: admin only.
            $admin_context = \Drupal::service('router.admin_context');
            if (!$admin_context->isAdminRoute()) {
                return;
            }

            // Apply node/block specific checks.
            $this->applyNodeFormFieldCondition($form, $form_state, $form_id);
            $this->applyBlockFormFieldCondition($form, $form_state, $form_id);
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
    }

    /**
     * Applies alterations specific to node add/edit forms.
     *
     * @param array $form
     *   The form structure.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @param string $form_id
     *   The form ID (e.g., node_article_form).
     *
     * @return void
     */
    public function applyNodeFormFieldCondition(array &$form, FormStateInterface $form_state, string $form_id): void {
        try {
            // Only node add/edit forms.
            if (!str_starts_with($form_id, 'node_') || !str_ends_with($form_id, '_form')) {
                return;
            }

            // Apply validation.
            // $form['#validate'][] = ['\\Drupal\\helperbox\\Helper\\FormField', 'validateNodeForm'];
            $form['#validate'][] = [static::class, 'validateNodeForm'];

            // Get current node safely.
            $node = self::getCurrentNodeFromFormState($form_state);
            if (!$node) {
                return;
            }

            $nid = $node->id();
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

            // Attach JS/CSS library.
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

            $this->checkAllFields($form, $form_state, $node);
            $this->checkNodeFields($form, $form_state, $node);

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
     * @param array $form
     *   The form structure.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @param string $form_id
     *   The form ID.
     *
     * @return void
     */
    public function applyBlockFormFieldCondition(array &$form, FormStateInterface $form_state, string $form_id): void {
        try {
            if (!str_starts_with($form_id, 'block_content_') || !str_ends_with($form_id, '_form')) {
                return;
            }

            $entity = null;
            $form_object = $form_state->getFormObject();
            if ($form_object instanceof EntityFormInterface) {
                $entity = $form_object->getEntity();
            }
            if (!$entity instanceof BlockContentInterface) {
                return;
            }

            $entity_id = $entity->id();
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

            // Add hidden block id.
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

            $this->checkAllFields($form, $form_state, $entity);
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
    }

    /**
     * Get the current node entity from the form state.
     *
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     *
     * @return \Drupal\node\NodeInterface|null
     *   The current node, or NULL if none could be determined.
     */
    private static function getCurrentNodeFromFormState(FormStateInterface $form_state): ?NodeInterface {
        $form_object = $form_state->getFormObject();

        // Check if it's an EntityForm (most forms).
        if ($form_object instanceof EntityFormInterface) {
            $entity = $form_object->getEntity();
            if ($entity instanceof NodeInterface) {
                return $entity;
            }
        }

        // Fallback: use route match (works on add/edit).
        $route_match = \Drupal::routeMatch();
        $node = $route_match->getParameter('node');

        // On add forms, 'node' might be NULL, but 'node_type' exists.
        $node_type = $route_match->getParameter('node_type');
        if (!$node && $node_type instanceof NodeType) {
            $node = self::getTargetEntity('node', $node_type->id());
        }

        return $node instanceof NodeInterface ? $node : null;
    }

    /**
     * Creates an unsaved entity object for a given entity type and bundle.
     *
     * @param string $target_type
     *   The entity type ID (e.g., 'node', 'paragraph', 'media').
     * @param string $target_bundle
     *   The bundle/machine name of the entity (e.g., 'article').
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     *   A newly created but unsaved entity, or NULL on failure.
     */
    private static function getTargetEntity(string $target_type, string $target_bundle): ?EntityInterface {
        try {
            return \Drupal::entityTypeManager()
                ->getStorage($target_type)
                ->create(['type' => $target_bundle]);
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
            return null;
        }
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
    private function checkNodeFields(array &$form, FormStateInterface $form_state, NodeInterface $node): void {
        $nid = $node->id();
        $bundle = $node->bundle();

        // Skip if no rules for this bundle.
        if (!isset($this->fieldnoderules[$bundle]) || !is_array($this->fieldnoderules[$bundle])) {
            return;
        }

        foreach ($this->fieldnoderules[$bundle] as $node_id_rule => $field_conditions) {
            if (!is_array($field_conditions)) {
                continue;
            }

            // Rules that apply to *all* nodes of this bundle.
            $applies_to_all = ($node_id_rule === '-1' || $node_id_rule === 0 || (is_numeric($node_id_rule) && (int) $node_id_rule < 0));
            if ($applies_to_all) {
                $this->applyFieldConditions($form, $field_conditions, true);
            }

            // Rules that apply to this specific node.
            $matches_this_node = ((string) $node_id_rule === (string) $nid);
            if ($matches_this_node) {
                $this->applyFieldConditions($form, $field_conditions, true);
            }
        }
    }

    /**
     * Applies a single set of field conditions to the given form.
     *
     * @param array $form
     *   The form (or subform) to apply conditions to.
     * @param array $field_conditions
     *   The field condition rules to apply.
     * @param bool $show
     *   Whether these conditions are currently active for the node in scope.
     *
     * @return void
     */
    private function applyFieldConditions(array &$form, array $field_conditions, bool $show): void {
        foreach ($field_conditions as $field_type => $field_value) {
            if ($field_type === 'referenceField' && is_array($field_value)) {
                $this->checkNodeReferenceField($form, $field_value, $show);
                continue;
            }

            if (is_string($field_value)) {
                $form[$field_value]['#access'] = false;
                continue;
            }

            if (is_bool($field_value)) {
                $form[$field_type]['#access'] = $field_value;
                continue;
            }

            if (is_array($field_value)) {
                foreach ($field_value as $field_name => $condition) {
                    $form[$field_name]['#access'] = $condition;
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
     * @param bool $this_node_show
     *   Whether rules apply for this node ID.
     * @param int $depth
     *   Current recursion depth, used to guard against infinite recursion.
     *
     * @return void
     */
    private function checkNodeReferenceField(array &$form, array $fields, bool $this_node_show, int $depth = 0): void {
        if ($depth > self::MAX_RECURSION_DEPTH) {
            return;
        }

        foreach ($fields as $field_name => $fields_access_check) {
            if (is_bool($fields_access_check)) {
                $form[$field_name]['#access'] = $fields_access_check;
                continue;
            }

            if (!is_array($fields_access_check) || !isset($form[$field_name]['widget'])) {
                continue;
            }

            $widget = &$form[$field_name]['widget'];
            foreach (Element::children($widget) as $delta) {
                if (!isset($widget[$delta]['subform'])) {
                    continue;
                }

                $subform = &$widget[$delta]['subform'];
                foreach ($fields_access_check as $field => $check) {
                    if (is_bool($check)) {
                        $subform[$field]['#access'] = $check;
                    }
                    if ($this_node_show && $field === 'referenceField' && is_array($check)) {
                        $this->checkNodeReferenceField($subform, $check, $this_node_show, $depth + 1);
                    }
                }
            }
        }
    }

    /**
     * Applies global field access rules based on entity type and bundle.
     *
     * @param array $form
     *   The form render array (passed by reference).
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   Current entity (node, paragraph, etc.) or temporary referenced entity.
     * @param array $field_access_check
     *   Inherited field access rules from a parent reference field, if any.
     * @param int $depth
     *   Current recursion depth, used to guard against infinite recursion.
     *
     * @return void
     */
    private function checkAllFields(array &$form, FormStateInterface $form_state, ContentEntityInterface $entity, array $field_access_check = [], int $depth = 0): void {
        if ($depth > self::MAX_RECURSION_DEPTH) {
            return;
        }

        $entity_type = $entity->getEntityTypeId();
        $entity_bundle = $entity->bundle();

        $rules = $this->fieldallrules[$entity_type][$entity_bundle]['field_access_check'] ?? [];

        if (empty($field_access_check)) {
            $field_access_check = $rules;
        } else {
            $field_access_check = array_merge($field_access_check, $rules);
        }

        if (empty($field_access_check)) {
            return;
        }

        // Iterate over all form elements that look like a field widget.
        foreach (Element::children($form) as $field_name) {
            // Skip non-widget elements.
            if (!isset($form[$field_name]['widget'])) {
                continue;
            }

            // Skip if entity doesn't have this field.
            if (!$entity->hasField($field_name)) {
                continue;
            }

            if (isset($field_access_check[$field_name]) && is_bool($field_access_check[$field_name])) {
                $form[$field_name]['#access'] = $field_access_check[$field_name];
            }

            $field = $entity->get($field_name);
            $field_def = $field->getFieldDefinition();
            $field_type = $field_def->getType();
            $target_type = $field_def->getSetting('target_type') ?? '';
            $target_bundles = $field_def->getSetting('handler_settings')['target_bundles'] ?? [];
            $target_bundle = is_array($target_bundles) ? (reset($target_bundles) ?: '') : '';

            // Handle entity reference fields.
            $is_reference = in_array($field_type, ['entity_reference', 'entity_reference_revisions'], true);
            if ($is_reference && isset($field_access_check[$field_name]) && is_array($field_access_check[$field_name])) {
                $widget = &$form[$field_name]['widget'];
                foreach (Element::children($widget) as $delta) {
                    if (!isset($widget[$delta]['subform'])) {
                        continue;
                    }

                    $subform = &$widget[$delta]['subform'];
                    $referenced_entity = $field->get($delta)->entity ?? null;
                    if (!$referenced_entity && $target_type && $target_bundle) {
                        $referenced_entity = $this->getTargetEntity($target_type, $target_bundle);
                    }

                    if ($referenced_entity instanceof ContentEntityInterface) {
                        $this->checkAllFields($subform, $form_state, $referenced_entity, $field_access_check[$field_name], $depth + 1);
                    }
                }
            }
        }
    }

    /**
     * Applies #access rules keyed directly by form ID.
     *
     * @param array $form
     *   The form render array (passed by reference).
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     * @param string $form_id
     *   The form ID.
     *
     * @return void
     */
    private function checkFieldsByFormId(array &$form, FormStateInterface $form_state, string $form_id): void {
        $form_id_field_rules = HelperboxSettings::get_instance()->getFieldRulesForm();
        if (isset($form_id_field_rules[$form_id]) && is_array($form_id_field_rules[$form_id])) {
            foreach ($form_id_field_rules[$form_id] as $field_name => $check) {
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
     *   The raw data (may contain [] or null).
     *
     * @return array
     *   Cleaned data.
     */
    private function filterArrayData(array $data): array {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                $clean = $this->filterArrayData($value);
                return !empty($clean);
            }
            return $value !== [] && $value !== null;
        });
    }

    /**
     * Form validation handler.
     *
     * @param array $form
     *   The form render array (passed by reference).
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state.
     *
     * @return void
     */
    public static function validateNodeForm(array &$form, FormStateInterface $form_state): void {
        // Get current node safely.
        $node = self::getCurrentNodeFromFormState($form_state);
        if (!$node) {
            return;
        }

        $type = $node->bundle();

        // Maximum nodes per content type.
        if ($node->isNew() && self::maxNodeValidate($type)) {
            $type_label = NodeType::load($type)?->label() ?? $type;

            $form_state->setErrorByName(
                'nid',
                \Drupal::translation()->translate(
                    'You cannot create a new "@value" node — the maximum number has been reached.',
                    ['@value' => $type_label]
                )
            );
        }

        // -----------------------------
        // 1. Validate title (unique per type).
        // -----------------------------
        $settings = HelperboxSettings::get_instance();
        $title_raw = $form_state->getValue('title');
        if ($settings->isUniqueNodePerBundleEnabled() && $title_raw) {
            $unique_node_content_type = $settings->get_unique_node_content_type();

            // If specific bundles are configured, only enforce uniqueness
            // for those bundles. If the list is empty, fall back to
            // enforcing it for all bundles.
            $applies_to_this_bundle = empty($unique_node_content_type)
                || in_array($type, $unique_node_content_type, true);

            if ($applies_to_this_bundle) {
                $title = is_array($title_raw) ? ($title_raw[0]['value'] ?? '') : $title_raw;
                $title = trim((string) $title);

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
                            \Drupal::translation()->translate(
                                'A node with the title "@value" already exists at content type "@type".',
                                ['@value' => $title, '@type' => $type]
                            )
                        );
                    }
                }
            }
        }

        // -----------------------------
        // 2. Validate field_country_code_3digit (unique per type).
        // -----------------------------
        $code_raw = $form_state->getValue('field_country_code_3digit');
        if ($code_raw) {
            $code = is_array($code_raw) ? ($code_raw[0]['value'] ?? '') : $code_raw;
            $code = trim((string) $code);

            if ($code !== '') {
                $query = \Drupal::entityQuery('node')
                    ->condition('type', $type)
                    ->condition('field_country_code_3digit', $code)
                    ->accessCheck(TRUE);

                if (!$node->isNew()) {
                    $query->condition('nid', $node->id(), '!=');
                }

                $count = (int) $query->count()->execute();

                if ($count > 0) {
                    $form_state->setErrorByName(
                        'field_country_code_3digit',
                        \Drupal::translation()->translate(
                            'A node with the Country Code "@value" already exists.',
                            ['@value' => $code]
                        )
                    );
                }
            }
        }
    }

    /**
     * Checks whether the maximum number of nodes for a content type has been
     * reached.
     *
     * @param string $type
     *   The machine name of the content type (e.g., 'article', 'page').
     *
     * @return bool
     *   TRUE if the maximum has been reached, FALSE otherwise.
     */
    public static function maxNodeValidate(string $type): bool {
        $max_nodes = HelperboxSettings::get_instance()->getFieldRulesMaxContent();

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
}
