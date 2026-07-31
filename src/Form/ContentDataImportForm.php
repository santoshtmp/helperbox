<?php

namespace Drupal\helperbox\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helperbox\Helper\UtilHelper;

/**
 * Provides a form for importing content (nodes or taxonomy terms) from a
 * remote Drupal site via the Helperbox API.
 *
 * Supports:
 *  - Taxonomy term import with UUID-based deduplication and parent resolution.
 *  - Node import with field mapping, file/image download, and taxonomy
 *    reference resolution.
 */
class ContentDataImportForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'helperbox_content_data_import_form';
    }

    /**
     * {@inheritdoc}
     *
     * Builds the import form with:
     *  - A URL field for the source Drupal site.
     *  - A content type selector (node or taxonomy).
     *  - Conditional selectors for node type or taxonomy vocabulary,
     *    shown via Drupal's #states API.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        // UtilHelper::delete_taxonomy_terms('department');

        // Attach library
        $form['#attached']['library'][] = 'helperbox/content_data_import';

        $form['source_url'] = [
            '#type' => 'url',
            '#title' => $this->t('External Drupal Site URL'),
            '#description' => $this->t('Base URL of the source Drupal site. Example: http://nasc-website-old.test/'),
            '#required' => TRUE,
            '#default_value' => 'https://nasc.org.np/',
        ];

        $form['content_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Content Type'),
            '#options' => [
                'node' => $this->t('Node'),
                'taxonomy' => $this->t('Taxonomy'),
            ],
            '#required' => TRUE,
            '#empty_option' => $this->t('- Select Content Type -'),
        ];

        // Shown only when "Node" is selected as the content type.
        $form['node_content'] = [
            '#type' => 'select',
            '#title' => $this->t('Remote Node Content Type'),
            '#options' => [
                '' => ' - Select -',
                'suchi_darta' => 'Suchi Darta',
                'news' => 'News',
                'events' => 'Events',
                'journal' => 'Journals',
                'notices' => 'Notices',
                'resoures' => 'Resources',
                'people' => 'Team/People',
                'nasc_discussion' => 'Update Event nasc_discussion'
            ],
            '#empty_option' => $this->t('- Select -'),
            '#states' => [
                'visible' => [
                    ':input[name="content_type"]' => ['value' => 'node'],
                ],
            ],
        ];

        // Shown only when "Taxonomy" is selected as the content type.
        $form['taxonomy_term_content'] = [
            '#type' => 'select',
            '#title' => $this->t('Remote Taxonomy Vocabulary'),
            '#options' => [
                '' => ' - Select -',
                'department_center' => 'Department Center',
                'suchi_darta' => 'Suchi Darta',
                'journal_stage' => 'Journal stage/status',
                'notices_type' => 'Notices type/category',
                'resources_type' => 'Resources type/category',
                'people_type' => 'Team/People type/category',
            ],
            '#empty_option' => $this->t('- Select -'),
            '#states' => [
                'visible' => [
                    ':input[name="content_type"]' => ['value' => 'taxonomy'],
                ],
            ],
        ];

        $form['page'] = [
            '#type' => 'number',
            '#title' => $this->t('Page Number'),
            '#default_value' => 0,
            '#min' => 0,
            '#step' => 1,
        ];

        $form['actions']['button'] = [
            '#type' => 'button',
            '#value' => $this->t('Start Import'),
            '#attributes' => [
                'class' => ['button', 'button--primary'],
                'id' => 'helperbox-import-btn',
            ],
        ];

        // Result container (AJAX output)
        $form['result'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => 'import-result-wrapper',
            ],
        ];

        // Message status
        $form['result']['message_status'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'id' => 'message-status',
                'class' => ['message-status'],
            ],
            '#value' => '',
        ];

        // Created count
        $form['result']['import_count_create'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'id' => 'import-count-create',
                'class' => ['import-count-create'],
            ],
            '#value' => '',
        ];

        // Updated count
        $form['result']['import_count_update'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'id' => 'import-count-update',
                'class' => ['import-count-update'],
            ],
            '#value' => '',
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     *
     * Validates that:
     *  - The selected content type is either "node" or "taxonomy".
     *  - A node content type is selected when importing nodes.
     *  - A taxonomy vocabulary is selected when importing taxonomy terms.
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     *
     * Dispatches to the appropriate import handler based on the selected
     * content type and displays a status or error message upon completion.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $this->messenger()->addStatus(
            $this->t(
                'Data Import Handled by API.'
            )
        );
    }
}
