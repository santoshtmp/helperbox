<?php

namespace Drupal\helperbox\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helperbox\Helper\PartialsContent;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Attribute\ViewsField;

/**
 *
 * A handler to provide for helperbox_views_field_customfunction.
 * */
#[ViewsField("helperbox_views_field_customfunction")]
class CustomFunction extends FieldPluginBase {

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
        $options['customfunction'] = ['default' => ''];
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state) {

        $form['customfunction'] = [
            '#type' => 'select',
            '#title' => $this->t('Custom function'),
            '#options' => [
                '' => $this->t(' - Select defined function -'),
                'teamdesignationbycategory' => $this->t('Get Team Designation by Category'),
                'speaker_topics_for_current_conference' => $this->t('Get Speaker Topics for the Current Conference'),
                'designation_for_current_conference_committee' => $this->t('Get Team Conference Designation for Current Conference Committee'),
            ],
            '#default_value' => $this->options['customfunction'],
            '#description' => $this->t('Select custom defined function.'),
            '#required' => TRUE
        ];

        parent::buildOptionsForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function render(ResultRow $values) {
        $customfunction = $this->options['customfunction'] ?? '';

        // Get the current entity.
        $entity = $values->_entity ?? NULL;
        if (!$entity) {
            return '';
        }
        if (!$entity instanceof ContentEntityInterface) {
            return '';
        }

        $outputvalue = '';

        switch ($customfunction) {
            case 'teamdesignationbycategory':
                // 
                $team_selected_group = NULL;

                // Get the actual filter handler instance from the view.
                if (isset($this->view->filter['field_team_category_target_id'])) {
                    $filter = $this->view->filter['field_team_category_target_id'];
                    $team_selected_group = $filter->value;

                    // For single-value select filters, value is often an array.
                    if (is_array($team_selected_group)) {
                        $team_selected_group = reset($team_selected_group);
                    }
                }

                $teamcategorydesignation = PartialsContent::getTeamCategoryDesignation($entity, $team_selected_group);
                $final_designation =  $teamcategorydesignation['field_designation'] ?? '';
                if ($teamcategorydesignation['field_designation_by_category'] ?? 0) {
                    $field_team_category_designation =  $teamcategorydesignation['field_team_category_designation'] ?? [];
                    $final_designation =  array_column($field_team_category_designation, 'field_designation');
                }

                if (is_array($final_designation)) {
                    $final_designation = implode(', ', $final_designation);
                }

                $outputvalue = $final_designation;

                break;
            case "speaker_topics_for_current_conference":
                $specific_conference_id = 0;
                // Get the node from the current route (i.e. the page being viewed).
                $current_node = \Drupal::routeMatch()->getParameter('node');
                if ($current_node instanceof NodeInterface) {
                    $specific_conference_id = (int) $current_node->id();
                }
                $field_data = [];
                if ($entity->hasField('field_speakers_topic_by_conferen') && !$entity->get('field_speakers_topic_by_conferen')->isEmpty()) {
                    $field_speakers_topic_by_conferen = $entity->get('field_speakers_topic_by_conferen');
                    foreach ($field_speakers_topic_by_conferen->referencedEntities() as $paragraph) {
                        if (!$paragraph instanceof \Drupal\paragraphs\ParagraphInterface) {
                            continue;
                        }
                        $paragraph_data = [
                            'type' => $paragraph->getEntityTypeId(),
                            'bundle' => $paragraph->bundle(),
                            'id' => $paragraph->id(),
                            'field_conference_node' => [],
                            'field_title' => '',
                        ];

                        if ($paragraph->hasField('field_conference_node') && !$paragraph->get('field_conference_node')->isEmpty()) {
                            $field_conference_node = $paragraph->get('field_conference_node')->entity;
                            if ($field_conference_node) {
                                if ($specific_conference_id && $specific_conference_id != $field_conference_node->id()) {
                                    continue;
                                }
                                $paragraph_data['field_conference_node'] = [
                                    'type'      => $field_conference_node->getEntityTypeId(),
                                    'bundle'    => $field_conference_node->bundle(),
                                    'id'        => $field_conference_node->id(),
                                    'label'     => $field_conference_node->label(),
                                    'url'       => $field_conference_node->toUrl()->toString(),
                                ];
                            }
                        }
                        if ($paragraph->hasField('field_title') && !$paragraph->get('field_title')->isEmpty()) {
                            $paragraph_data['field_title'] = $paragraph->get('field_title')->value;
                        }
                        $field_data[] = $paragraph_data;
                    }
                }

                $titles = array_filter(array_column($field_data, 'field_title'));

                if (!empty($titles)) {
                    $outputvalue = [
                        '#theme' => 'item_list',
                        '#items' => $titles,
                        '#attributes' => [
                            'class' => ['conference-speaker-topic-list'],
                        ],
                    ];
                }
                break;
            case "designation_for_current_conference_committee":
                $specific_conference_id = 0;
                // Get the node from the current route (i.e. the page being viewed).
                $current_node = \Drupal::routeMatch()->getParameter('node');
                if ($current_node instanceof NodeInterface) {
                    $specific_conference_id = (int) $current_node->id();
                }
                $field_data = [];
                if ($entity->hasField('field_conference_committee_desig') && !$entity->get('field_conference_committee_desig')->isEmpty()) {
                    $field_conference_committee_desig = $entity->get('field_conference_committee_desig');
                    foreach ($field_conference_committee_desig->referencedEntities() as $paragraph) {
                        if (!$paragraph instanceof \Drupal\paragraphs\ParagraphInterface) {
                            continue;
                        }
                        $paragraph_data = [
                            'type' => $paragraph->getEntityTypeId(),
                            'bundle' => $paragraph->bundle(),
                            'id' => $paragraph->id(),
                            'field_conference_node' => [],
                            'field_designation' => '',
                        ];

                        if ($paragraph->hasField('field_conference_node') && !$paragraph->get('field_conference_node')->isEmpty()) {
                            $field_conference_node = $paragraph->get('field_conference_node')->entity;
                            if ($field_conference_node) {
                                if ($specific_conference_id && $specific_conference_id != $field_conference_node->id()) {
                                    continue;
                                }
                                $paragraph_data['field_conference_node'] = [
                                    'type'      => $field_conference_node->getEntityTypeId(),
                                    'bundle'    => $field_conference_node->bundle(),
                                    'id'        => $field_conference_node->id(),
                                    'label'     => $field_conference_node->label(),
                                    'url'       => $field_conference_node->toUrl()->toString(),
                                ];
                            }
                        }
                        if ($paragraph->hasField('field_designation') && !$paragraph->get('field_designation')->isEmpty()) {
                            $paragraph_data['field_designation'] = $paragraph->get('field_designation')->value;
                        }
                        $field_data[] = $paragraph_data;
                    }
                }

                $designation = array_filter(array_column($field_data, 'field_designation'));

                if (!empty($designation)) {
                    $outputvalue = [
                        '#theme' => 'item_list',
                        '#items' => $designation,
                        '#attributes' => [
                            'class' => ['conference-committee-designation-list'],
                        ],
                    ];
                }
                break;
            default:
                break;
        }


        return $outputvalue;
    }
}
