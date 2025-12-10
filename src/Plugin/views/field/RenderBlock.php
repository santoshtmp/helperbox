<?php

namespace Drupal\helperbox\Plugin\views\field;

use Drupal\Core\Template\Attribute;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Attribute\ViewsField;
use Drupal\helperbox\Helper\GetBlock;

/**
 * 
 * A handler to provide for helperbox_renderblock.
 * */
#[ViewsField("helperbox_renderblock")]
class RenderBlock extends FieldPluginBase {

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
        $options['view_id'] = ['default' => ''];
        $options['display_id'] = ['default' => ''];
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {

        $form['view_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('View machine name'),
            '#default_value' => $this->options['view_id'],
            '#description' => $this->t('e.g. content_listing'),
            '#required' => TRUE,
        ];

        $form['display_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Display ID'),
            '#default_value' => $this->options['display_id'],
            '#description' => $this->t('e.g. block_11, page_1, embed_1'),
            '#required' => TRUE,
        ];
        parent::buildOptionsForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function render(ResultRow $values) {
        $view_id = $this->sanitizeValue($this->options['view_id']);
        $display_id = $this->sanitizeValue($this->options['display_id']);
        $block = GetBlock::get_rendered_views_block($view_id, $display_id);

        if ($block) {
            // 
            $adminlinks = [];
            $attached = [];
            $node = \Drupal::routeMatch()->getParameter('node');
            $destination = '';
            if ($node) {
                $destination = "?destination=/node/" . $node->id();
            }
            $current_user = \Drupal::currentUser();
            $editaccess = false;
            if (
                $current_user->hasPermission('administer views') ||
                $current_user->hasPermission('edit views')
            ) {
                $editaccess = true;
                // Edit view link
                $adminlinks[] = [
                    'title' => t('Edit view'),
                    'link' =>  '/admin/structure/views/view/' . $view_id . '/edit/' . $display_id . $destination,
                ];

                // Add translate link if config_translation module exists
                if (\Drupal::moduleHandler()->moduleExists('config_translation')) {
                    $adminlinks[] = [
                        'title' => t('Translate view'),
                        'link' => '/admin/structure/views/view/' . $view_id . '/translate' . $destination,
                    ];
                }

                // Attach JS/CSS
                $attached = [
                    'library' => [
                        'helperbox/helperbox_admin_styles',
                    ],
                    'drupalSettings' => [
                        'helperbox_renderblock' => [
                            'view_id' => $view_id,
                            'display_id' => $display_id,
                        ],
                    ],
                ];
            }
            // 
            return [
                '#theme' => 'helperbox_renderblock',
                '#content' => $block,
                '#view_id' => $view_id,
                '#display_id' => $display_id,
                '#adminlinks' => $adminlinks,
                '#attributes' => [
                    'class' => [
                        'field-render-content',
                        'render-' . str_replace('_', '-', $view_id) . '-' . str_replace('_', '-', $display_id),
                        $editaccess ? 'edit-field-helperbox-renderblock contextual-region' : '',
                    ],
                    'data-view' =>  $view_id . '-' . $display_id,
                ],
                '#attached' => $attached,
            ];
        }



        return "";
    }
}
