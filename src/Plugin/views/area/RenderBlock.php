<?php

namespace Drupal\helperbox\Plugin\views\area;

use Drupal\views\Plugin\views\area\AreaPluginBase;
use Drupal\views\Annotation\ViewsArea;
use Drupal\helperbox\Helper\GetBlock;



/**
 * Renders a block or views block in Views header/footer.
 *
 * @ViewsArea("helperbox_renderblock")
 */
#[ViewsArea("helperbox_renderblock")]
class RenderBlock extends AreaPluginBase {

    /**
     * {@inheritdoc}
     */
    public function defineOptions() {
        $options = parent::defineOptions();
        $options['block_type'] = ['default' => 'view_block'];
        $options['block_plugin_id'] = ['default' => ''];
        $options['view_id'] = ['default' => ''];
        $options['display_id'] = ['default' => ''];
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {

        $form['block_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Render Type'),
            '#default_value' => $this->options['block_type'],
            '#options' => [
                'plugin_block' => $this->t('Plugin Block'),
                'view_block' => $this->t('Views Block'),
            ],
            '#required' => TRUE,
        ];

        $form['block_plugin_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Block Plugin ID'),
            '#default_value' => $this->options['block_plugin_id'],
            '#description' => $this->t('Example: system_breadcrumb_block, social_sharing_block'),
            '#states' => [
                'visible' => [
                    ':input[name="options[block_type]"]' => ['value' => 'plugin_block'],
                ],
            ],
        ];

        $form['view_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('View machine name'),
            '#default_value' => $this->options['view_id'],
            '#description' => $this->t('e.g. content_listing'),
            '#states' => [
                'visible' => [
                    ':input[name="options[block_type]"]' => ['value' => 'view_block'],
                ],
            ],
        ];

        $form['display_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Display ID'),
            '#default_value' => $this->options['display_id'],
            '#description' => $this->t('e.g. block_1, block_2'),
            '#states' => [
                'visible' => [
                    ':input[name="options[block_type]"]' => ['value' => 'view_block'],
                ],
            ],
        ];


        parent::buildOptionsForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function render($empty = FALSE) {

        $block_type = $this->options['block_type'];
        if (!$block_type) {
            return "Block type not selected.";
        }

        /*  Initialize variables */
        $adminlinks = [];
        $attached = [];
        $node = \Drupal::routeMatch()->getParameter('node');
        $destination = '';
        if ($node) {
            $destination = "?destination=/node/" . $node->id();
        }
        $current_user = \Drupal::currentUser();
        $editaccess = false;
        $rendered_block = null;
        $view_id = '';
        $display_id = '';
        $block_plugin_id = '';
        $dataView = '';

        /**
         * 1) Plugin Block Rendering
         */
        if ($block_type === 'plugin_block') {
            $block_plugin_id = trim($this->options['block_plugin_id']);
            if ($block_plugin_id) {
                // $additionalClass = 
                $rendered_block = GetBlock::render_block($block_plugin_id);
                $additionalClass = 'innerblock-' . $block_plugin_id;
                $dataView = 'block-' . $block_plugin_id;
            }
        }

        /**
         * 2) Views Block Rendering
         */
        if ($block_type === 'view_block') {
            $view_id = $this->sanitizeValue($this->options['view_id']);
            $display_id = $this->sanitizeValue($this->options['display_id']);
            if ($view_id && $display_id) {
                $rendered_block = GetBlock::get_rendered_views_block($view_id, $display_id);
                $additionalClass = 'innerblock-' . str_replace('_', '-', $view_id) . '-' . str_replace('_', '-', $display_id);
                $dataView = $view_id . '-' . $display_id;
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
            }
        }

        /*  Render the block if available */
        if ($rendered_block) {
            // 
            return [
                '#theme' => 'helperbox_renderblock',
                '#content' => $rendered_block,
                '#view_id' => $view_id ?? '',
                '#display_id' => $display_id ?? '',
                '#block_type' => $block_type,
                '#block_plugin_id' => $block_plugin_id,
                '#adminlinks' => $adminlinks,
                '#attributes' => [
                    'class' => [
                        'field-render-content',
                        $additionalClass,
                        $editaccess ? 'edit-field-helperbox-renderblock contextual-region' : '',
                    ],
                    'data-view' =>  $dataView,
                ],
                '#attached' => $attached,
            ];
        }

        /*  Return empty if no block rendered */
        return "";
    }
}
