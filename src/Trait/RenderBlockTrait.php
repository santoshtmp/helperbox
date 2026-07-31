<?php

namespace Drupal\helperbox\Trait;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\helperbox\Helper\GetBlock;

/**
 * Shared rendering logic for helperbox RenderBlock field and area plugins.
 *
 * Any class using this trait must provide:
 *  - $this->options              (from PluginBase)
 *  - $this->currentUser          (AccountProxyInterface)
 *  - $this->moduleHandler        (ModuleHandlerInterface)
 *  - $this->routeMatch           (RouteMatchInterface)
 *  - $this->sanitizeValue()      (from FieldPluginBase / AreaPluginBase)
 *  - $this->t()                  (from StringTranslationTrait)
 */
trait RenderBlockTrait {

    /**
     * Merges block options into the parent options array.
     */
    protected function defineBlockOptions(array $options): array {
        $options['block_type']      = ['default' => 'view_block'];
        $options['block_plugin_id'] = ['default' => ''];
        $options['view_id']         = ['default' => ''];
        $options['display_id']      = ['default' => ''];
        return $options;
    }

    /**
     * Appends shared block form elements to the options form.
     */
    protected function buildBlockOptionsForm(array &$form): void {

        $form['block_type'] = [
            '#type'          => 'select',
            '#title'         => $this->t('Render Type'),
            '#default_value' => $this->options['block_type'],
            '#options'       => [
                'plugin_block' => $this->t('Plugin Block'),
                'view_block'   => $this->t('Views Block'),
            ],
            '#required' => TRUE,
        ];

        $form['block_plugin_id'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Block Plugin ID'),
            '#default_value' => $this->options['block_plugin_id'],
            '#description'   => $this->t('Example: system_breadcrumb_block or system_branding_block or creative_sitebranding or social_sharing_block'),
            '#states'        => [
                'visible' => [
                    ':input[name="options[block_type]"]' => ['value' => 'plugin_block'],
                ],
            ],
        ];

        $form['view_id'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('View machine name'),
            '#default_value' => $this->options['view_id'],
            '#description'   => $this->t('e.g. content_listing'),
            '#states'        => [
                'visible' => [
                    ':input[name="options[block_type]"]' => ['value' => 'view_block'],
                ],
            ],
        ];

        $form['display_id'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Display ID'),
            '#default_value' => $this->options['display_id'],
            '#description'   => $this->t('e.g. block_1, block_2'),
            '#states'        => [
                'visible' => [
                    ':input[name="options[block_type]"]' => ['value' => 'view_block'],
                ],
            ],
        ];
    }

    /**
     * Builds and returns the full render array (or empty string).
     *
     * @param array $view_args
     *   Arguments from the parent view (field plugin only).
     *
     * @return array|string
     */
    protected function buildBlockRenderArray(array $view_args = []): array|string {

        $request = \Drupal::request();
        $is_reset = $request->query->get('op') === 'Reset' || $request->request->get('op') === 'Reset';
        if ($is_reset) {
            $clean_url = $request->getPathInfo(); // e.g. /resources/category/journals
            $response  = new \Symfony\Component\HttpFoundation\RedirectResponse($clean_url);
            $response->send();
            exit();
        }

        $block_type = $this->options['block_type'] ?? '';
        if (empty($block_type)) {
            return '';
        }

        $rendered_block  = NULL;
        $adminlinks      = [];
        $attached        = [];
        $dataView        = '';
        $view_id         = '';
        $display_id      = '';
        $block_plugin_id = '';
        $editaccess      = FALSE;
        $cache_tags      = [];

        // Destination query param for admin links (node pages only).
        $node         = \Drupal::routeMatch()->getParameter('node');
        $edit_options = $node ? ['query' => ['destination' => '/node/' . $node->id()]] : [];

        // ------------------------------------------------------------------ //
        // Plugin Block                                                         //
        // ------------------------------------------------------------------ //
        if ($block_type === 'plugin_block') {
            $block_plugin_id = $this->sanitizeValue(trim((string) ($this->options['block_plugin_id'] ?? '')));

            if ($block_plugin_id !== '') {
                $rendered_block  = GetBlock::render_block($block_plugin_id);
                $dataView        = 'block-' . $block_plugin_id;
                $cache_tags[]    = 'config:block.block.' . $block_plugin_id;
            }
        }

        // ------------------------------------------------------------------ //
        // Views Block                                                          //
        // ------------------------------------------------------------------ //
        if ($block_type === 'view_block') {
            $view_id    = $this->sanitizeValue((string) ($this->options['view_id'] ?? ''));
            $display_id = $this->sanitizeValue((string) ($this->options['display_id'] ?? ''));

            if ($view_id !== '' && $display_id !== '') {
                $rendered_block  = GetBlock::get_rendered_views_block($view_id, $display_id, $view_args);
                $dataView        = str_replace('_', '-', $view_id) . '-' . str_replace('_', '-', $display_id);
                $cache_tags[]    = 'config:views.view.' . $view_id;

                $current_user = \Drupal::currentUser();
                if ($current_user->hasPermission('administer views') || $current_user->hasPermission('edit views')) {
                    $editaccess = TRUE;

                    // Edit view link.
                    $adminlinks[] = [
                        'title' => $this->t('Edit view'),
                        'link'  => Url::fromRoute(
                            'entity.view.edit_display_form',
                            ['view' => $view_id, 'display_id' => $display_id],
                            $edit_options
                        )->toString(),
                    ];

                    // Translate view link — route dynamically registered by
                    // config_translation at /admin/structure/views/view/{view}/translate.
                    if (\Drupal::moduleHandler()->moduleExists('config_translation')) {
                        $adminlinks[] = [
                            'title' => $this->t('Translate view'),
                            'link'  => Url::fromRoute(
                                'entity.view.config_translation_overview',
                                ['view' => $view_id],
                                $edit_options
                            )->toString(),
                        ];
                    }

                    // Admin assets only loaded for users who see the links.
                    $attached = [
                        'library'        => ['helperbox/helperbox_admin_styles'],
                        'drupalSettings' => [
                            'helperbox_renderblock' => [
                                'view_id'    => $view_id,
                                'display_id' => $display_id,
                            ],
                        ],
                    ];
                }
            }
        }

        if (empty($rendered_block)) {
            return '';
        }

        return [
            '#theme'           => 'helperbox_renderblock',
            '#content'         => $rendered_block,
            '#view_id'         => $view_id,
            '#display_id'      => $display_id,
            '#block_type'      => $block_type,
            '#block_plugin_id' => $block_plugin_id,
            '#adminlinks'      => $adminlinks,
            '#attributes'      => [
                'id'        => 'block-' . $dataView,
                'class'     => array_filter([
                    'field-render-content',
                    'innerblock-wrapper',
                    $dataView,
                    $editaccess ? 'edit-field-helperbox-renderblock contextual-region' : '',
                ]),
                'data-view' => $dataView,
            ],
            '#attached' => $attached,
            // '#cache'    => [
            // 'tags'     => Cache::mergeTags($cache_tags, ['rendered']),
            // 'contexts' => ['user.permissions'],
            // 'max-age'  => 0,
            // ],
        ];
    }
}
