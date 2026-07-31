<?php

/**
 * @file
 * Form hooks for Helperbox module.
 */

use Drupal\helperbox\Helper\FormField;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

// /**
//  * Implements hook_webform_submission_insert().
//  */
// function helperbox_webform_submission_insert(\Drupal\webform\Entity\WebformSubmission $webform_submission) {
// }

/**
 * Implements hook_form_alter().
 * 
 * @param array $form
 *   An associative array containing the structure of the form.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   An object containing the current state of the form.
 * @param string $form_id
 *   The unique ID of the form.
 * 
 * This hook is used to alter the menu edit form to add a new column that displays the URL of each menu link. It checks if the form being altered is the 'menu_edit_form' and if the 'links' table exists in the form. If so, it adds a new header for the URL column and iterates through each menu link row to extract and display the URL in a new cell. The URL is displayed as a small, escaped string to ensure it is safe for output. Additionally, it calls a helper function to apply more complex field rules based on the form ID.
 * 
 */
function helperbox_form_alter(&$form, FormStateInterface $form_state, $form_id) {

    // Call the function to add the menu location column to the menu edit form.
    add_menu_location_at_menu_edit_form($form, $form_state, $form_id);

    // Call helper to apply more complex field rules.
    FormField::get_instance()->applyFormFieldCondition($form, $form_state, $form_id);
}


/**
 * Implements hook_form_node_form_alter().
 *
 * @param array $form
 *   An associative array containing the structure of the form.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   An object containing the current state of the form.
 * @param string $form_id
 *   The unique ID of the form.
 * 
 */
function helperbox_form_node_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();

    // === Trainings dependent fields ===
    if ($form_id === 'node_trainings_edit_form' || $form_id === 'node_trainings_form') {
        $parent_training_wrapper_id = 'field-parent-training-item-wrapper';

        // Add AJAX to the category field.
        if (isset($form['field_trainings_category']['widget'])) {
            $form['field_trainings_category']['widget']['#ajax'] = [
                'callback' => 'helperbox_form_node__field_trainings_category__ajax_callback',
                'wrapper' => $parent_training_wrapper_id,
                'event' => 'change',
                'progress' => ['type' => 'throbber', 'message' => t('Updating ...')],
            ];
        }

        // Wrap the dependent field.
        $form['field_parent_training']['#prefix'] = '<div id="' . $parent_training_wrapper_id . '">';
        $form['field_parent_training']['#suffix'] = '</div>';

        // Set initial options on page load.
        $category_id = $form_state->getValue(['field_trainings_category', 0, 'target_id']) ?? $node->get('field_trainings_category')->target_id ?? NULL;
        if ($category_id) {
            $field_definition = $node->getFieldDefinition('field_parent_training');
            $options = helperbox_form_node__field_parent_training__options($field_definition, $category_id);
            if (!empty($options)) {
                $form['field_parent_training']['widget']['#options'] = $options;
            }
        }
    }

    // Move to sidebar (meta region).
    if (isset($form['field_featured_team_member'])) {
        $form['field_featured_team_member']['#weight'] = 100;
        $form['meta']['field_featured_team_member'] = $form['field_featured_team_member'];
        unset($form['field_featured_team_member']);
    }

    if (isset($form['field_featured_content'])) {
        $form['field_featured_content']['#weight'] = 100;
        $form['meta']['field_featured_content'] = $form['field_featured_content'];
        unset($form['field_featured_content']);
    }
}

/**
 * AJAX callback for field_trainings_category.
 */
/**
 * Ajax callback.
 */
function helperbox_form_node__field_trainings_category__ajax_callback(array &$form, FormStateInterface $form_state) {
    $category_id = $form_state->getValue(['field_trainings_category', 0, 'target_id']) ?? NULL;

    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();
    $field_definition = $node->getFieldDefinition('field_parent_training');

    $options = helperbox_form_node__field_parent_training__options($field_definition, $category_id);

    // Update the options.
    $form['field_parent_training']['widget']['#options'] = $options ?: [];

    // === CRITICAL FIXES ===
    // 1. Completely remove the old value
    $form_state->unsetValue('field_parent_training');

    // 2. Tell Drupal to ignore validation for this field during AJAX
    // $form_state->setLimitValidationErrors([]);

    $form_state->setRebuild(TRUE);

    return $form['field_parent_training'];
}


/**
 * Builds the option list for field_parent_training based on a category ID.
 *
 * Executes the entity_reference_content view (entity_reference_1 display)
 * with the given category as a contextual filter argument, and returns
 * an options array suitable for a select widget's #options.
 *
 * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
 *   The field definition for field_parent_training.
 * @param int|string|null $category_id
 *   The selected training category term/node ID.
 *
 * @return array
 *   An array of entity id => label, or an empty array if none found /
 *   no category given / view could not be loaded.
 */
function helperbox_form_node__field_parent_training__options($field_definition, $category_id) {
    $options = [];

    if (empty($category_id)) {
        return $options;
    }

    $handler_settings = $field_definition->getSetting('handler_settings');
    if (empty($handler_settings['view']['view_name']) || empty($handler_settings['view']['display_name'])) {
        return $options;
    }

    $view_name = $handler_settings['view']['view_name'] ?? '';
    $display_name = $handler_settings['view']['display_name'] ?? '';
    // Override contextual filter argument.
    $arguments = $handler_settings['view']['arguments'] ?? [];
    $arguments[0] = (string) $category_id; // Usually the first argument is the category.


    /** @var \Drupal\views\ViewExecutable $view */
    $view = Views::getView($view_name);
    if (!$view) {
        return $options;
    }

    $view->setDisplay($display_name);
    $view->preExecute($arguments);
    $view->execute();

    $options[0] = '- None -';
    foreach ($view->result as $row) {
        $entity = $row->_entity ?? NULL;
        if ($entity) {
            $options[$entity->id()] = $entity->label();
        }
    }

    $view->destroy(); // Important for performance / static cache.

    return $options;
}

/**
 * Implements hook_form_alter().
 *
 * @param array $form
 *   The form array (passed by reference).
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state object.
 * @param string $form_id
 *   The ID of the form being altered.
 */
function add_menu_location_at_menu_edit_form(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {

    if ($form_id !== 'menu_edit_form') {
        return;
    }

    if (!isset($form['links']['links'])) {
        return;
    }

    // Get current menu.
    $menu = \Drupal::routeMatch()->getParameter('menu');
    $menu_id = $menu ? $menu->id() : '';
    if (!$menu || $menu_id != 'main') {
        return;
    }

    // Insert "Menu Location" header as the second column.
    if (isset($form['links']['links']['#header']) && is_array($form['links']['links']['#header'])) {
        $header = $form['links']['links']['#header'];
        array_splice($header, 1, 0, [t('Menu Location')]);
        $form['links']['links']['#header'] = $header;
    }

    /**
     * -------------------------
     * Loop rows
     * -------------------------
     */
    foreach ($form['links']['links'] as $key => &$row) {

        // Skip table metadata.
        if (!is_array($row) || !isset($row['#item'])) {
            continue;
        }

        $menu_location = '';

        try {
            $link = $row['#item']->link;
            $menu_item_type = $link->getProvider() ?: '';

            if ($menu_item_type === 'menu_link_content') {
                $entity = \Drupal::service('menu_item_extras.menu_link_tree_handler')
                    ->getMenuLinkItemEntity($link);

                if (
                    $entity &&
                    $entity->hasField('field_menu_location') &&
                    !$entity->get('field_menu_location')->isEmpty()
                ) {
                    $field = $entity->get('field_menu_location');

                    // $field_menu_locations = array_column(
                    //     $field->getValue(),
                    //     'value'
                    // );

                    // if (!empty($field_menu_locations) && is_array($field_menu_locations)) {
                    //     $menu_location = '<ul><li>' .
                    //         implode('</li><li>', array_map('htmlspecialchars', $field_menu_locations)) .
                    //         '</li></ul>';
                    // }

                    // Get allowed values (value => label)
                    $allowed_values = $field->getFieldDefinition()
                        ->getSetting('allowed_values');
                    $stored_values = $field->getValue();
                    $labels = [];

                    foreach ($stored_values as $item) {
                        $value = $item['value'] ?? NULL;

                        if ($value !== NULL && isset($allowed_values[$value])) {
                            $labels[] = $allowed_values[$value];
                        }
                    }

                    if (!empty($labels)) {
                        $menu_location = '<ul><li>' .
                            implode('</li><li>', array_map('htmlspecialchars', $labels)) .
                            '</li></ul>';
                    }
                }
            }
        } catch (\Exception $e) {
            $menu_location = '';
        }

        $location_column = [
            '#type' => 'item',
            '#markup' => $menu_location,
        ];

        // Rebuild row and insert Menu Location after title column.
        $new_row = [];

        foreach ($row as $name => $element) {
            $new_row[$name] = $element;

            if ($name === 'title') {
                $new_row['menu_location'] = $location_column;
            }
        }

        $row = $new_row;
    }
}

/**
 * Implements hook_form_FORM_ID_alter() for menu_link_content_form.
 *
 * @param array $form
 *   The menu link content form render array, passed by reference.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 * @param string $form_id
 *   The form ID (e.g. 'menu_link_content_menu_link_content_form').
 *
 * @see \Drupal\menu_link_content\MenuLinkContentForm
 */
function helperbox_form_menu_link_content_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
    // Container for the fields we want grouped together in the sidebar/main
    // form area. Rendered as a collapsible details element, open by default.
    $form['helperbox'] = [
        '#type' => 'details',
        '#title' => t('Other settings'),
        '#open' => TRUE,
        '#weight' => 0,
        '#group' => 'advanced', // advanced, meta, sidebar, display
    ];

    //   Move enable field into custom group
    if (isset($form['enabled'])) {
        $form['helperbox']['enabled'] = $form['enabled'];
        unset($form['enabled']);
    }

    // Move "Menu location" field into the custom group, if it exists on
    // this form. It may be absent on bundles/entities where the field
    // isn't attached.
    if (isset($form['field_menu_location'])) {
        $form['helperbox']['field_menu_location'] = $form['field_menu_location'];
        unset($form['field_menu_location']);
    }

    // Move "Roles able to see the menu link" (Menu Per Role) into the
    // custom group, if the Menu Per Role module has added it to the form.
    if (isset($form['menu_per_role__show_role'])) {
        $form['helperbox']['menu_per_role__show_role'] = $form['menu_per_role__show_role'];
        unset($form['menu_per_role__show_role']);
    }

    // Move "Roles not able to see the menu link" (Menu Per Role) into the
    // custom group, if the Menu Per Role module has added it to the form.
    if (isset($form['menu_per_role__hide_role'])) {
        $form['helperbox']['menu_per_role__hide_role'] = $form['menu_per_role__hide_role'];
        unset($form['menu_per_role__hide_role']);
    }

    // Show "Description" field only when the selected parent link belongs to the Main navigation menu.
    if (isset($form['field_description'])) {
        $form['field_description']['#states'] = [
            'visible' => [
                ':input[name="menu_parent"]' => ['value' => 'main:'],
            ],
        ];
    }

    // Show "CTA" field only when the selected parent link belongs to the Main navigation menu. 
    if (isset($form['field_cta'])) {
        $form['field_cta']['#states'] = [
            'visible' => [
                ':input[name="menu_parent"]' => ['value' => 'main:'],
            ],
        ];
    }
}
