/**
 * @file
 * Conditional field visibility behaviors for node edit forms.
 *
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  // ---------------------------------------------------------------------------
  // Utility helpers
  // ---------------------------------------------------------------------------

  /**
   * Converts a field group machine name to its corresponding DOM element ID.
   *
   * Drupal renders horizontal tab panes with IDs derived from the group's
   * machine name: underscores become hyphens and the string is prefixed with
   * "edit-".
   *
   * @example
   *   groupToElementId('group_course_objectives')
   *   // → 'edit-group-course-objectives'
   *
   * @param {string} group
   *   Field group machine name (e.g. 'group_course_objectives').
   * @return {string}
   *   DOM element ID without the leading '#'.
   */
  function groupToElementId(group) {
    return 'edit-' + group.replace(/_/g, '-');
  }

  /**
   * Converts a field machine name to its wrapper element DOM ID.
   *
   * Drupal wraps each field widget in a div whose ID follows the pattern
   * "edit-{field-name}-wrapper", with underscores replaced by hyphens.
   *
   * @example
   *   fieldToElementId('field_venue')
   *   // → 'edit-field-venue-wrapper'
   *
   * @param {string} field
   *   Field machine name (e.g. 'field_venue').
   * @return {string}
   *   Wrapper DOM element ID without the leading '#'.
   */
  function fieldToElementId(field) {
    return 'edit-' + field.replace(/_/g, '-') + '-wrapper';
  }

  // ---------------------------------------------------------------------------
  // Group / field show-hide helpers
  // ---------------------------------------------------------------------------

  /**
   * Hides field group panes and their corresponding horizontal tab links.
   *
   * Each horizontal tab group has two DOM representations: the content pane
   * (e.g. #edit-group-gallery) and the tab anchor whose href points to that
   * pane. Both must be hidden so the tab does not remain visible in the tab
   * bar after its content is removed from view.
   *
   * @param {string[]} groups
   *   Field group machine names to hide.
   */
  function hideGroupDetails(groups) {
    groups.forEach(function (group) {
      const id = '#' + groupToElementId(group);
      $(id).hide();
      $('a[href="' + id + '"]').hide();
    });
  }

  /**
   * Shows field group panes and their corresponding horizontal tab links.
   *
   * Reverses the effect of {@link hideGroupDetails} for the given groups.
   *
   * @param {string[]} groups
   *   Field group machine names to show.
   */
  function showGroupDetails(groups) {
    groups.forEach(function (group) {
      const id = '#' + groupToElementId(group);
      $(id).show();
      $('a[href="' + id + '"]').show();
    });
  }

  /**
   * Hides individual field wrapper elements.
   *
   * Targets the outer wrapper div Drupal generates for each field
   * (e.g. #edit-field-venue-wrapper), not the inner widget element.
   *
   * @param {string[]} fields
   *   Field machine names whose wrappers should be hidden.
   */
  function hideFieldWrapper(fields) {
    fields.forEach(function (field) {
      $('#' + fieldToElementId(field)).hide();
    });
  }

  /**
   * Shows individual field wrapper elements.
   *
   * Reverses the effect of {@link hideFieldWrapper} for the given fields.
   *
   * @param {string[]} fields
   *   Field machine names whose wrappers should be shown.
   */
  function showFieldWrapper(fields) {
    fields.forEach(function (field) {
      $('#' + fieldToElementId(field)).show();
    });
  }

  /**
   * Gets a form element's current value(s) as an array of strings.
   *
   * @param {jQuery} $el
   *   Trigger element.
   * @return {string[]}
   *   Current value(s), normalized to an array of strings.
   */
  function fieldstatus_getCurrentValueAsArray($el) {
    const val = $el.val();
    if (val === null || val === undefined) {
      return [];
    }
    return Array.isArray(val) ? val.map(String) : [String(val)];
  }

  /**
   * Evaluates one condition group (AND across selectors, OR within each).
   *
   * @param {Object} group
   *   Condition group keyed by trigger selector.
   * @param {Element|Document} context
   *   DOM context to scope trigger lookups to.
   * @return {boolean}
   *   True if all selectors' conditions are satisfied.
   */
  function fieldstatus_evaluateConditionGroup(group, context) {
    return Object.keys(group).every(function (selector) {
      const $target = $(selector, context);
      if (!$target.length) {
        return false;
      }
      const conditions = group[selector];

      return conditions.some(function (cond) {
        if (Object.prototype.hasOwnProperty.call(cond, 'checked')) {
          return $target.is(':checked') === cond.checked;
        }
        if (Object.prototype.hasOwnProperty.call(cond, 'value')) {
          const current = fieldstatus_getCurrentValueAsArray($target);
          const expected = Array.isArray(cond.value) ? cond.value.map(String) : [String(cond.value)];
          // Containment: true if any current value is in the expected set.
          const matches = current.some(function (v) {
            return expected.includes(v);
          });
          // negate: true means "condition met when there's NO overlap".
          return cond.negate ? !matches : matches;
        }
        return false;
      });
    });
  }

  /**
   * Evaluates a rule (array of condition groups, OR'd together).
   *
   * @param {Object[]} ruleArray
   *   Condition groups to OR together.
   * @param {Element|Document} context
   *   DOM context passed through to group evaluation.
   * @return {boolean}
   *   True if any group is satisfied.
   */
  function fieldstatus_evaluateRule(ruleArray, context) {
    return ruleArray.some(function (group) {
      return fieldstatus_evaluateConditionGroup(group, context);
    });
  }

  /**
   * Shows/hides a wrapper based on a state key ('visible', 'invisible', '!visible').
   *
   * @param {HTMLElement} wrapper
   *   Target element to show/hide.
   * @param {string} stateKey
   *   'visible', 'invisible', or '!visible'.
   * @param {boolean} result
   *   Evaluated rule result.
   * @return {void}
   */
  function fieldstatus_applyState(wrapper, stateKey, result) {
    let show;
    if (stateKey === 'visible') {
      show = result;
    }
    else if (stateKey === 'invisible' || stateKey === '!visible') {
      show = !result;
    }
    else {
      return;
    }
    wrapper.style.display = show ? '' : 'none';
  }

  /**
   * Re-evaluates every target field's rules and applies the result.
   *
   * @param {Object} fieldStatusDependencies
   *   Full config, keyed by target field machine name.
   * @param {Element|Document} context
   *   DOM context to scope element lookups to.
   * @return {void}
   */
  function fieldstatus_evaluateAll(fieldStatusDependencies, context) {
    Object.keys(fieldStatusDependencies).forEach(function (fieldName) {
      const wrapperEl = document.getElementById(fieldToElementId(fieldName));
      if (!wrapperEl) {
        return;
      }
      const fieldConfig = fieldStatusDependencies[fieldName];
      Object.keys(fieldConfig).forEach(function (stateKey) {
        const result = fieldstatus_evaluateRule(fieldConfig[stateKey], context);
        fieldstatus_applyState(wrapperEl, stateKey, result);
      });
    });
  }

  /**
  * Entry point: binds change listeners for all triggers in the config and
  * runs an initial evaluation. Call from a Drupal.behaviors attach().
  *
  * @param {Object} fieldStatusDependencies
  *   Full config, keyed by target field machine name.
  *   Config shape:
  *   {
  *     [targetField]: {
  *       visible: [ { selector: [{value:[...]}, ...], ... }, ... ], // OR'd groups
  *       '!visible': [ ... ] // alias for invisible
  *     }
  *   }
  *   - Keys within a group = AND. Conditions within a selector's array = OR.
  *   - { value: [...] } = containment match. { checked: true } = checkbox state.
  * @param {Element|Document} context
  *   DOM context passed from the Drupal behavior's attach() callback.
  * @return {void}
  */
  function apply_helperbox_fieldstatus_dependencies(fieldStatusDependencies, context) {
    // Collect all unique trigger selectors so each gets exactly one listener.
    const triggerSelectors = new Set();
    Object.values(fieldStatusDependencies).forEach(function (fieldConfig) {
      Object.values(fieldConfig).forEach(function (ruleArray) {
        ruleArray.forEach(function (group) {
          Object.keys(group).forEach(function (selector) {
            triggerSelectors.add(selector);
          });
        });
      });
    });

    // Use jQuery's .on() — Select2 dispatches change via jQuery, not native events.
    triggerSelectors.forEach(function (selector) {
      once('helperbox-status-trigger', selector, context).forEach(function (el) {
        $(el).on('change', () => fieldstatus_evaluateAll(fieldStatusDependencies, context));
      });
    });

    // Set correct initial state before any user interaction.
    fieldstatus_evaluateAll(fieldStatusDependencies, context);
  }

  // ---------------------------------------------------------------------------
  // Drupal behaviors
  // ---------------------------------------------------------------------------

  /**
   * Toggles field groups and fields based on training structure, and controls
   * list item subform field visibility based on the selected list item type.
   *
   * Training structure (field_training_structure):
   *   - "instance" → shows instance-specific groups/fields, hides main ones.
   *   - any other value → shows main groups/fields, hides instance-specific ones.
   *
   *   Main training groups/fields:
   *     groups: group_competency_framework, group_course_objectives,
   *             group_training_methods, group_course_modules_and_classro, group_kyc
   *     fields: field_short_name, field_featured_image, field_contact_person
   *
   *   Instance training groups/fields:
   *     groups: group_gallery, group_participants
   *     fields: field_parent_training, field_venue, field_date_range,
   *             field_duration, field_course_coodinator
   *
   * List item type (field_list_item_type within course module subforms):
   *   - "points"    → title only.
   *   - "accordion" → title + description.
   *   - default     → title + subtitle + description.
   *
   * Uses once() throughout to ensure listeners and initial UI state are only
   * applied once per element, even when Drupal.attachBehaviors() is called
   * multiple times (e.g. after AJAX rebuilds).
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.trainingStructureChange = {
    attach: function (context) {

      /**
       * Applies form field visibility based on the selected training structure.
       *
       * Reads the current value of the training structure select element and
       * toggles two mutually exclusive sets of field groups and fields:
       *
       *  - "instance": show instance-specific groups/fields; hide main ones.
       *  - any other value: show main groups/fields; hide instance-specific ones.
       *
       * After updating visibility, programmatically clicks the "Overview" tab to
       * prevent the user from remaining on a tab that has just been hidden.
       *
       * @param {HTMLElement} selectTrainingStructureElement
       *   The <select> element for field_training_structure.
       * @param {string[]} main_training_groups
       *   Field group machine names shown only for main (non-instance) trainings.
       * @param {string[]} main_training_fields
       *   Field machine names shown only for main (non-instance) trainings.
       * @param {string[]} instance_training_groups
       *   Field group machine names shown only for instance trainings.
       * @param {string[]} instance_training_fields
       *   Field machine names shown only for instance trainings.
       */
      function updateTrainingEditFormUIByTrainingstructure(
        selectTrainingStructureElement,
        main_training_groups,
        main_training_fields,
        instance_training_groups,
        instance_training_fields
      ) {
        const trainingStructureVal = $(selectTrainingStructureElement).val();

        if (trainingStructureVal === 'instance') {
          hideGroupDetails(main_training_groups);
          hideFieldWrapper(main_training_fields);
          showGroupDetails(instance_training_groups);
          showFieldWrapper(instance_training_fields);
        }
        else {
          hideGroupDetails(instance_training_groups);
          hideFieldWrapper(instance_training_fields);
          showGroupDetails(main_training_groups);
          showFieldWrapper(main_training_fields);
        }

        // Return focus to the Overview tab so the user is never left viewing
        // a tab that has just been hidden.
        $('a[href="#edit-group-overview"]')[0]?.click();
      }


      // -----------------------------------------------------------------------
      // Training structure: toggle main vs. instance field groups and fields.
      // -----------------------------------------------------------------------

      once('training-structure-Change', 'select[name="field_training_structure"]', context)
        .forEach(function (selectTrainingStructureElement) {

          // Field groups visible only for instance trainings.
          const instance_training_groups = [
            'group_gallery',
            'group_participants',
          ];

          // Fields visible only for instance trainings.
          const instance_training_fields = [
            'field_parent_training',
            'field_venue',
            'field_date_range',
            'field_duration',
            'field_course_coodinator',
          ];

          // Field groups visible only for main (non-instance) trainings.
          const main_training_groups = [
            'group_competency_framework',
            'group_course_objectives',
            'group_training_methods',
            'group_course_modules_and_classro',
            'group_kyc',
          ];

          // Fields visible only for main (non-instance) trainings.
          const main_training_fields = [
            'field_short_name',
            'field_featured_image',
            'field_contact_person',
          ];

          // Apply the correct visibility state on page load before any user
          // interaction, based on the field's current persisted value.
          updateTrainingEditFormUIByTrainingstructure(
            selectTrainingStructureElement,
            main_training_groups,
            main_training_fields,
            instance_training_groups,
            instance_training_fields
          );

          // Re-evaluate and reapply visibility rules on every subsequent change.
          $(selectTrainingStructureElement).on('change', function () {
            updateTrainingEditFormUIByTrainingstructure(
              this,
              main_training_groups,
              main_training_fields,
              instance_training_groups,
              instance_training_fields
            );
          });

        });

      // -----------------------------------------------------------------------
      // List item type: show/hide subform fields per list item type selection.
      // -----------------------------------------------------------------------

      // Matches the subform wrapper for any list item within a course module.
      const field_list_items_selector = 'div[data-drupal-selector^="edit-field-course-modules-learnings-"][data-drupal-selector*="-subform-field-list-items-"][data-drupal-selector$="-subform"]';

      // Matches the list item type select within those subforms.
      const field_list_item_type_selector = 'select[data-drupal-selector^="edit-field-course-modules-learnings-"][data-drupal-selector$="-subform-field-list-item-type"]';

      /**
       * Shows or hides list item subform fields based on the selected type.
       *
       *  - "points":    show title only; hide subtitle and description.
       *  - "accordion": show title and description; hide subtitle.
       *  - default:     show title, subtitle, and description.
       *
       * @param {string} listItemTypeVal
       *   The selected list item type value.
       * @param {jQuery} $itemWrapper
       *   The subform wrapper element containing the fields to toggle.
       */
      function list_items_control(listItemTypeVal, $itemWrapper) {
        const $fieldTitle = $itemWrapper.find('div[data-drupal-selector$="-subform-field-title-wrapper"]');
        const $fieldSubTitle = $itemWrapper.find('div[data-drupal-selector$="-subform-field-sub-title-wrapper"]');
        const $fieldDescription = $itemWrapper.find('div[data-drupal-selector$="-subform-field-description-wrapper"]');

        if (listItemTypeVal === 'points') {
          $fieldTitle.show();
          $fieldSubTitle.hide();
          $fieldDescription.hide();
        }
        else if (listItemTypeVal === 'accordion') {
          $fieldTitle.show();
          $fieldSubTitle.hide();
          $fieldDescription.show();
        }
        else {
          $fieldTitle.show();
          $fieldSubTitle.show();
          $fieldDescription.show();
        }
      }

      // Apply initial field visibility for each list item subform on page load.
      once('field-list-items-condition', field_list_items_selector, context)
        .forEach(function (element) {
          const $wrapper = $(element);
          const $listItemType = $(field_list_item_type_selector);
          if ($listItemType.length) {
            list_items_control($listItemType.val(), $wrapper);
          }
        });

      // When the list item type changes, update visibility across all subforms.
      once('field-list-items-type-condition', field_list_item_type_selector, context)
        .forEach(function (element) {
          $(element).on('change', function () {
            const listItemTypeVal = $(this).val();
            document.querySelectorAll(field_list_items_selector).forEach(function (itemElement) {
              list_items_control(listItemTypeVal, $(itemElement));
            });
          });
        });

    },
  };

  /**
   * Team content type node form
   */
  Drupal.behaviors.teamFormStructureChange = {
    attach: function (context) {

      // Show/hide the designation field and team category designation field based on the checkbox value.
      once('node-team-form', '.node-type-team', context)
        .forEach(function (element) {
          // --- Config: same shape as Drupal's #states, but array values use "contains any" matching. ---
          const $teamFieldStatusDependencies = {
            "field_team_category_designation": {
              "visible": [
                {
                  // '[name="field_team_category[]"]': [
                  //   { "value": ['240', '301'], negate: true },
                  // ],
                  '[name="field_designation_by_category[value]"]': [
                    { "checked": true }
                  ]
                }
              ]
            },
            "field_speakers_topic_by_conferen": {
              "visible": [
                {
                  '[name="field_team_category[]"]': [
                    { value: ['240'] }
                  ]
                }
              ]
            },
            "field_conference_committee_desig": {
              "visible": [
                {
                  '[name="field_team_category[]"]': [
                    { value: ['301'] }
                  ]
                }
              ]
            },
          };
          // 
          apply_helperbox_fieldstatus_dependencies($teamFieldStatusDependencies, element);
        });

      // Get the current team category designation values from the paragraphs.
      function getCurrentTeamCategoryDesignation() {
        let current_team_category_designation = {};
        $('.paragraph-type--team-category-designation .paragraphs-subform').each(function (index) {
          let category_id = $(this)
            .find('select[name$="[field_team_category]"]')
            .val();
          let current_designation = $(this)
            .find('input[name^="field_team_category_designation"][name*="[field_designation]"][name$="[value]"]')
            .val();
          current_team_category_designation[category_id] = current_designation;
        });
        return current_team_category_designation;
      }

      // Sync the number of paragraphs with the number of selected team categories, and populate the values accordingly.
      function syncParagraphsTeamCategoryDesignation(categoryIds, teamCategoryDesignation) {
        let $paragraphType = $('.paragraph-type--team-category-designation');
        let $paragraphTypeSubform = $paragraphType.find('.paragraphs-subform');
        let currentNum = $paragraphTypeSubform.length;
        let requiredNum = categoryIds.length;

        // Need to add more paragraphs.
        if (currentNum < requiredNum) {
          $(document).one('ajaxComplete', function () {
            syncParagraphsTeamCategoryDesignation(categoryIds, teamCategoryDesignation);
          });

          let $addButton = $('input[name="field_team_category_designation_team_category_designation_add_more"]');
          $addButton.trigger('mousedown').trigger('click');

          return;
        }

        // Need to remove extra paragraphs.
        if (currentNum > requiredNum) {
          $(document).one('ajaxComplete', function () {
            syncParagraphsTeamCategoryDesignation(categoryIds, teamCategoryDesignation);
          });

          let $removeButton = $paragraphType
            .last()
            .find('.paragraphs-actions input[name*="field_team_category_designation_"][name$="_remove"]');
          $removeButton.trigger('mousedown').trigger('click');

          return;
        }

        // Counts match, populate the values.
        $paragraphTypeSubform.each(function (index) {
          const categoryid = categoryIds[index] ?? '';
          const $select_field_team_category = $(this).find('select[name$="[field_team_category]"]');
          $select_field_team_category.val(categoryid).trigger('change');
          makeSelectReadonly($select_field_team_category);

          let designation = (teamCategoryDesignation || {})[categoryid] ?? '';
          const $field_team_category_designation = $(this).find('input[name^="field_team_category_designation"][name*="[field_designation]"][name$="[value]"]');
          $field_team_category_designation.val(designation);

          disableDesignationForCategory(categoryid, $field_team_category_designation);
        });
      }

      /**
        * Disables the designation field for categories where designation is not allowed.
       * 
       * @param {*} categoryid 
       * @param {*} designationElement 
       */
      function disableDesignationForCategory(categoryid, designationElement) {
        const $disableDesignationCategoryIDs = ['240', '301'];
        if ($disableDesignationCategoryIDs.includes(categoryid)) {
          designationElement.val('');
          makeSelectReadonly(designationElement);
          designationElement.after('<div>Designation is not allowed for this team category.</div>');
        }
      }

      // Make a select element readonly by disabling pointer events and blocking keydown events that can change the selection.
      function makeSelectReadonly($select) {
        $select.css({
          'background': 'lightgray',
          'pointer-events': 'none'
        });

        // Remove any existing handler to avoid duplicates.
        $select.off('keydown.readonly');

        $select.on('keydown.readonly', function (e) {
          // Allow Tab and Shift+Tab.
          if (e.key === 'Tab') {
            return;
          }

          // Block keys that can change the selection.
          if (
            e.key === 'ArrowUp' ||
            e.key === 'ArrowDown' ||
            e.key === 'ArrowLeft' ||
            e.key === 'ArrowRight' ||
            e.key === 'Enter' ||
            e.key === ' ' ||
            e.key === 'Spacebar'
          ) {
            e.preventDefault();
            e.stopPropagation();
          }
        });
      }

      // Get the team category select element.
      const $field_team_category = '.field--name-field-team-category select[name="field_team_category[]"]';

      // Show/hide the designation field and team category designation field based on the number of selected team categories.
      once('field--name-field-team-category', $field_team_category, context)
        .forEach(function (element) {
          $(element).on('change', function () {
            let updated_team_category_ids = $(this).val();
            // updateFieldVisibilityByCategory(updated_team_category_ids, $fieldVisibilityByCategory);
            syncParagraphsTeamCategoryDesignation(
              updated_team_category_ids,
              getCurrentTeamCategoryDesignation()
            );

          });

        });

      // Show/hide the designation field and team category designation field based on the number of selected team categories when select2 is used.
      once('field--name-field-team-category-select2', '.field--name-field-team-category .select2-selection__rendered', context)
        .forEach(function (element) {
          $(element).on('update', function () {
            setTimeout(function () {
              let updated_team_category_ids = $($field_team_category).val();
              syncParagraphsTeamCategoryDesignation(
                updated_team_category_ids,
                getCurrentTeamCategoryDesignation()
              );
            }, 100);

          });
        });

      // Default sync the paragraphs with the selected team categories on page load.
      once('team-category-designation', '#edit-field-team-category-designation-wrapper', context)
        .forEach(function (element) {
          let updated_team_category_ids = $($field_team_category).val();
          // updateFieldVisibilityByCategory(updated_team_category_ids, $fieldVisibilityByCategory);

          let length = $(element).find('.paragraph-type--team-category-designation').length;
          if (length == 0) {
            syncParagraphsTeamCategoryDesignation(
              updated_team_category_ids,
              getCurrentTeamCategoryDesignation()
            );
          } else {
            // Paragraphs already exist on load — just lock their category selects as readonly.
            const $select = $(element).find('select[name$="[field_team_category]"]');
            makeSelectReadonly($select);

            $(element).find('.paragraphs-subform').each(function (index) {
              const categoryid = $(this).find('select[name$="[field_team_category]"]').val();
              const $field_team_category_designation = $(this).find('input[name^="field_team_category_designation"][name*="[field_designation]"][name$="[value]"]');
              disableDesignationForCategory(categoryid, $field_team_category_designation);
            });
          }

        });

    }
  };

  /**
   * content type node form
   */
  Drupal.behaviors.nodeFormStructure = {
    attach: function (context) {

      once('faq-category-tabs', '#edit-field-faqs-wrapper', context).forEach(function (wrapper) {

        const $wrapper = $(wrapper);
        const $select = $(wrapper)
          .find('.field--name-field-faq-category select')
          .first();

        if (!$select.length) {
          return;
        }

        // Create tabs.
        let $tabs = $('.faq-category-tabs');
        if (!$tabs.length) {
          $tabs = $('<div class="faq-category-tabs"></div>');

          $select.find('option').each(function () {
            const value = $(this).val();
            let text = $(this).text().trim();

            if (!value) {
              return;
            }

            if (value === '_none') {
              text = 'All';
            }

            $tabs.append(`
            <button
              type="button"
              class="faq-category-tab"
              data-value="${value}">
              ${text}
            </button>
          `);
          });

          // Make the first tab active.
          $tabs.find('.faq-category-tab').first().addClass('active');

          // Insert tabs before the FAQ field.
          $wrapper.before($tabs);

          // Tab click.
          $tabs.on('click', '.faq-category-tab', function () {
            $tabs.find('.faq-category-tab').removeClass('active');
            $(this).addClass('active');

            // filter faq row
            const currentFilter = $('.faq-category-tab.active').data('value') || '_none';
            $('#edit-field-faqs-wrapper tr.paragraph-type--faqs').each(function () {
              const $faq = $(this);
              const currentCategory = $faq
                .find('.field--name-field-faq-category select')
                .val();
              if (currentFilter == '_none' || currentCategory == '_none' || currentFilter == currentCategory) {
                $faq.show();
              } else {
                $faq.hide();
              }
            });
          });
        }

      });

      once('faq-category-option', 'select[data-drupal-selector$="-subform-field-faq-category"]', context)
        .forEach(function (element) {
          const $select = $(element);
          let currentCategory = $select.val();
          const currentFilter = $('.faq-category-tab.active').data('value') || '_none';
          const $faq = $select.closest('tr.paragraph-type--faqs');
          if (currentFilter == '_none' || currentCategory == currentFilter || currentCategory == '_none') {
            $faq.show();
          }
          else {
            $faq.hide();
          }
        });
    }

  };

  /**
  * resources content type node form
  */
  Drupal.behaviors.resourcesContentType = {
    attach: function (context) {
      // for resources form
      once('node-resources-form', '.node-type-resources', context)
        .forEach(function (element) {
          const $resourcesFieldStatusDependencies = {
            // author from team member
            "field_contact_person": {
              "visible": [
                {
                  '[name="field_resources_category"]': [
                    { "value": ['1', '302', '303'] },
                  ],
                }
              ]
            },
            // author external person
            "field_text_3": {
              "visible": [
                {
                  '[name="field_resources_category"]': [
                    { "value": ['1', '302', '303'] },
                  ],
                }
              ]
            },
            // field_keywords
            "field_keywords": {
              "visible": [
                {
                  '[name="field_resources_category"]': [
                    { "value": ['1'] },
                  ],
                }
              ]
            },
            // field_fiscal_year_b_s
            "field_fiscal_year_b_s": {
              "visible": [
                {
                  '[name="field_resources_category"]': [
                    { "value": ['303'] },
                  ],
                }
              ]
            },
          };
          // 
          apply_helperbox_fieldstatus_dependencies($resourcesFieldStatusDependencies, element);
        });
    }
  };

  /**
  * notices content type node form
  */
  Drupal.behaviors.noticesContentType = {
    attach: function (context) {
      // for notices form
      once('node-notices-form', '.node-type-notices', context)
        .forEach(function (element) {
          const $noticesFieldStatusDependencies = {
            // field_deadline
            "field_deadline": {
              "visible": [
                {
                  '[name="field_notices_category"]': [
                    { "value": ['8', '134'] },
                  ],
                }
              ]
            },
            // field_email
            "field_email": {
              "visible": [
                {
                  '[name="field_notices_category"]': [
                    { "value": ['134'] },
                  ],
                }
              ]
            },
          };
          // 
          apply_helperbox_fieldstatus_dependencies($noticesFieldStatusDependencies, element);
        });
    }
  };

})(jQuery, Drupal, drupalSettings, once);


