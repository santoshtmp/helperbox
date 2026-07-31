<?php

namespace Drupal\helperbox\Plugin\Block;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Utility\Html;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helperbox\Helper\GetBlock;
use Drupal\helperbox\Helper\MediaHelper;
use Drupal\helperbox\Helper\PartialsContent;
use Drupal\helperbox\Trait\ShowDateStatusTrait;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Hero Banner block for all page.
 *
 * 1. Displays the current detail page banner.
 * 2. Displays the current taxonomy term's name, description, and banner image
 *    when placed on a taxonomy term page (/taxonomy/term/{id}).
 * 3. Displays view associated block info banner.
 */
#[Block(
    id: "helperbox_herobannerblock",
    admin_label: new TranslatableMarkup("Hero Banner Block by Helperbox"),
    category: new TranslatableMarkup("helperbox"),
)]
class HeroBannerBlock extends BlockBase implements ContainerFactoryPluginInterface {

    use ShowDateStatusTrait;

    /**
     * The current route match service.
     */
    protected RouteMatchInterface $routeMatch;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        RouteMatchInterface $route_match
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->routeMatch   = $route_match;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(
        ContainerInterface $container,
        array $configuration,
        $plugin_id,
        $plugin_definition
    ): static {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('current_route_match')
        );
    }

    /**
     * {@inheritdoc}
     *
     * No configurable defaults needed for this block — all data is pulled
     * dynamically from the current route's taxonomy term.
     */
    public function defaultConfiguration(): array {
        return parent::defaultConfiguration() + [
            'enable_breadcrumb'     => TRUE,
            'breadcrumb_conditions' => [
                'excluded' => [
                    'id'            => [],
                    'entity_bundle' => [],
                    'path'          => [],
                ],
            ],
            'enable_social_share'     => TRUE,
            'social_share_conditions' => [
                'excluded' => [
                    'id'            => [],
                    'entity_bundle' => [],
                    'path'          => [],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state): array {
        $form = parent::blockForm($form, $form_state);

        // --- Header Title Description ---
        $header_conditions = $this->configuration['header_conditions'] ?? [
            'excluded' => ['id' => [], 'entity_bundle' => []],
        ];

        $form['header_conditions'] = [
            '#type'        => 'details',
            '#title'       => $this->t('Header Title Description'),
            '#description' => $this->t('Control where header title description are suppressed.'),
            '#open' => TRUE,
        ];

        $form['header_conditions']['excluded_ids'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Excluded Page IDs'),
            '#description'   => $this->t('Comma-separated page IDs to exclude. Example: 116, 122'),
            '#default_value' => implode(', ', $header_conditions['excluded']['id'] ?? []),
        ];

        $form['header_conditions']['excluded_bundles'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Excluded Content Types'),
            '#description'   => $this->t('Comma-separated entity bundle machine names to exclude. Example: page, article'),
            '#default_value' => implode(', ', $header_conditions['excluded']['entity_bundle'] ?? []),
        ];

        $form['header_conditions']['excluded_paths'] = [
            '#type'          => 'textarea',
            '#title'         => $this->t('Excluded Paths'),
            '#description'   => $this->t('One path per line. Supports wildcards. Example: /about, /news/*'),
            '#rows'          => 4,
            '#default_value' => implode("\n", $header_conditions['excluded']['path'] ?? []),
        ];

        // --- Breadcrumb ---
        $breadcrumb_conditions = $this->configuration['breadcrumb_conditions'] ?? [
            'excluded' => ['id' => [], 'entity_bundle' => []],
        ];

        $form['enable_breadcrumb'] = [
            '#type'          => 'checkbox',
            '#title'         => $this->t('Enable Breadcrumb'),
            '#description'   => $this->t('Display the breadcrumb navigation above the block content.'),
            '#default_value' => $this->configuration['enable_breadcrumb'] ?? TRUE,
        ];

        $form['breadcrumb_conditions'] = [
            '#type'        => 'details',
            '#title'       => $this->t('Breadcrumb Conditions'),
            '#description' => $this->t('Control where the breadcrumb is suppressed.'),
            '#states'      => [
                'visible' => [
                    ':input[name="settings[enable_breadcrumb]"]' => ['checked' => TRUE],
                ],
            ],
            '#open' => FALSE,
        ];

        $form['breadcrumb_conditions']['excluded_ids'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Excluded Page IDs'),
            '#description'   => $this->t('Comma-separated page IDs to exclude. Example: 116, 122'),
            '#default_value' => implode(', ', $breadcrumb_conditions['excluded']['id'] ?? []),
        ];

        $form['breadcrumb_conditions']['excluded_bundles'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Excluded Content Types'),
            '#description'   => $this->t('Comma-separated entity bundle machine names to exclude. Example: page, article'),
            '#default_value' => implode(', ', $breadcrumb_conditions['excluded']['entity_bundle'] ?? []),
        ];

        $form['breadcrumb_conditions']['excluded_paths'] = [
            '#type'          => 'textarea',
            '#title'         => $this->t('Excluded Paths'),
            '#description'   => $this->t('One path per line. Supports wildcards. Example: /about, /news/*'),
            '#rows'          => 4,
            '#default_value' => implode("\n", $breadcrumb_conditions['excluded']['path'] ?? []),
        ];

        // --- Social Share ---
        $social_conditions = $this->configuration['social_share_conditions'] ?? [
            'excluded' => ['id' => [], 'entity_bundle' => []],
        ];

        $form['enable_social_share'] = [
            '#type'          => 'checkbox',
            '#title'         => $this->t('Enable Social Share'),
            '#description'   => $this->t('Display social sharing buttons below the block content.'),
            '#default_value' => $this->configuration['enable_social_share'] ?? TRUE,
        ];

        $form['social_share_conditions'] = [
            '#type'        => 'details',
            '#title'       => $this->t('Social Share Conditions'),
            '#description' => $this->t('Control where social sharing buttons are suppressed.'),
            '#states'      => [
                'visible' => [
                    ':input[name="settings[enable_social_share]"]' => ['checked' => TRUE],
                ],
            ],
            '#open' => FALSE,
        ];

        $form['social_share_conditions']['excluded_ids'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Excluded Page IDs'),
            '#description'   => $this->t('Comma-separated page IDs to exclude. Example: 116, 122'),
            '#default_value' => implode(', ', $social_conditions['excluded']['id'] ?? []),
        ];

        $form['social_share_conditions']['excluded_bundles'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Excluded Content Types'),
            '#description'   => $this->t('Comma-separated entity bundle machine names to exclude. Example: page, article'),
            '#default_value' => implode(', ', $social_conditions['excluded']['entity_bundle'] ?? []),
        ];

        $form['social_share_conditions']['excluded_paths'] = [
            '#type'          => 'textarea',
            '#title'         => $this->t('Excluded Paths'),
            '#description'   => $this->t('One path per line. Supports wildcards. Example: /about, /news/*'),
            '#rows'          => 4,
            '#default_value' => implode("\n", $social_conditions['excluded']['path'] ?? []),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state): void {
        parent::blockSubmit($form, $form_state);

        $this->configuration['enable_breadcrumb']   = (bool) $form_state->getValue('enable_breadcrumb');
        $this->configuration['enable_social_share'] = (bool) $form_state->getValue('enable_social_share');

        // --- Breadcrumb conditions ---
        $bc = $form_state->getValue('breadcrumb_conditions');
        $this->configuration['breadcrumb_conditions'] = [
            'excluded' => [
                'id'            => self::parseCommaSeparated($bc['excluded_ids']     ?? ''),
                'entity_bundle' => self::parseCommaSeparated($bc['excluded_bundles'] ?? ''),
                'path'          => self::parseNewlineSeparated($bc['excluded_paths'] ?? ''),
            ],
        ];

        // --- Social share conditions ---
        $sc = $form_state->getValue('social_share_conditions');
        $this->configuration['social_share_conditions'] = [
            'excluded' => [
                'id'            => self::parseCommaSeparated($sc['excluded_ids']     ?? ''),
                'entity_bundle' => self::parseCommaSeparated($sc['excluded_bundles'] ?? ''),
                'path'          => self::parseNewlineSeparated($sc['excluded_paths'] ?? ''),
            ],
        ];

        // --- header conditions ---
        $hc = $form_state->getValue('header_conditions');
        $this->configuration['header_conditions'] = [
            'excluded' => [
                'id'            => self::parseCommaSeparated($hc['excluded_ids']     ?? ''),
                'entity_bundle' => self::parseCommaSeparated($hc['excluded_bundles'] ?? ''),
                'path'          => self::parseNewlineSeparated($hc['excluded_paths'] ?? ''),
            ],
        ];
    }

    /**
     * Parses a comma-separated string into a clean array of trimmed strings.
     *
     * @param string $value  Raw textfield value, e.g. "116, 122 , 130".
     * @return string[]      e.g. ['116', '122', '130']
     */
    private static function parseCommaSeparated(string $value): array {
        return array_values(array_filter(
            array_map('trim', explode(',', $value))
        ));
    }

    /**
     * Parses a newline-separated textarea value into a clean array of paths.
     *
     * Trims whitespace, strips empty lines, and normalises each path to
     * start with a leading slash.
     *
     * @param string $value  Raw textarea value, one path per line.
     * @return string[]      e.g. ['/about', '/news/*']
     */
    private static function parseNewlineSeparated(string $value): array {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $value))
        ));

        // Ensure every path starts with /.
        return array_map(function (string $path): string {
            return '/' . ltrim($path, '/');
        }, $lines);
    }

    /**
     * {@inheritdoc}
     */
    public function build(): array {
        // 
        $route_name     = $this->routeMatch->getRouteName(); // \Drupal::routeMatch()->getRouteName();
        $page_type      = 'other';
        $entity_id      = '';
        $entity_type    = '';
        $entity_bundle  = '';
        $banner_data    = [];
        $cache_tags     = ['rendered'];
        $cache_contexts = ['route'];
        $current_path = \Drupal::service('path.current')->getPath();
        $current_paths = [
            $current_path,
            \Drupal::service('path_alias.manager')->getAliasByPath($current_path),
        ];
        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

        // --- Detect the primary entity for this page ---
        $entity = $this->resolveCurrentEntity();

        // --- Resolve common meta for all entity types in one place ---
        if ($entity instanceof ContentEntityInterface) {
            [
                'page_type'     => $page_type,
                'entity_id'     => $entity_id,
                'entity_type'   => $entity_type,
                'entity_bundle' => $entity_bundle,
                'cache_tags'    => $cache_tags,
            ] = $this->resolveEntityMeta($entity);
        }

        // --- Build banner data based on entity type ---
        if ($entity instanceof NodeInterface) {
            $banner_data = $banner_data + $this->resolveBaseData($entity);

            // 
            switch ($entity_bundle) {
                case 'trainings':
                    $field_training_structure = '';
                    if ($entity->hasField('field_training_structure') && !$entity->get('field_training_structure')->isEmpty()) {
                        $field_training_structure = $entity->get('field_training_structure')->value;
                    }

                    $banner_data['terms'] = $this->resolveNodeTerms($entity, ['trainings_category']);
                    $banner_data['date_status'] = $field_training_structure == 'instance' ?  $this->getFieldDateRangeStatus($entity) : '';

                    if (
                        $entity->hasField('field_parent_training') &&
                        !$entity->get('field_parent_training')->isEmpty() &&
                        $field_training_structure == 'instance'
                    ) {
                        $parent_entity = $entity->get('field_parent_training')->entity;
                        if ($parent_entity instanceof \Drupal\node\NodeInterface) {
                            $urltostring = $parent_entity->toUrl()->toString();
                            $short_name = $parent_entity->hasField('field_short_name') && !$parent_entity->get('field_short_name')->isEmpty()
                                ? $parent_entity->get('field_short_name')->value
                                : $parent_entity->getTitle();

                            $attributes = [];
                            $attributes['class'] = 'cta-wrapper link-btn cta-link';

                            $banner_data['cta'] = [
                                '#theme'       => 'helperbox_component_cta',
                                '#cta_url'     => $urltostring,
                                '#cta_label' => $this->t('Learn about @name', ['@name' => $short_name]),
                                '#cta_type'    => 'link',
                                '#cta_target'  => null,
                                '#is_external' => false,
                                '#is_no_link'  => FALSE,
                                '#attributes'  => new \Drupal\Core\Template\Attribute($attributes),
                                '#wrapper_attributes'     => new \Drupal\Core\Template\Attribute([
                                    'class' => 'parent-training',
                                ])
                            ];
                        }
                    }
                    break;

                case 'hall':
                    $banner_data['terms'] = $this->resolveNodeTerms($entity);
                    $attributes = [];
                    $attributes['class'] = 'cta-wrapper link-btn';
                    $banner_data['cta'] = [
                        '#theme'       => 'helperbox_component_cta',
                        '#cta_url'     => '/book-hall?select_hall=' . $entity_id,
                        '#cta_label'   =>  $this->t("Book Hall"),
                        '#cta_type'    => 'link',
                        '#cta_target'  => null,
                        '#is_external' => false,
                        '#is_no_link'  => FALSE,
                        '#attributes'  => new \Drupal\Core\Template\Attribute($attributes),
                        '#wrapper_attributes'     => new \Drupal\Core\Template\Attribute([
                            'class' => 'cta-link',
                        ])
                    ];
                    break;

                case 'team':
                    // $banner_data['terms'] = $this->resolveNodeTerms($entity, ['team_category']);
                    // $data = PartialsContent::getTeamCategoryDesignation($entity);
                    // if ($entity->hasField('field_designation') && !$entity->get('field_designation')->isEmpty()) {
                    //     $banner_data['field']['field_designation'] = $entity->get('field_designation')->value;
                    // }

                    if ($entity->hasField('field_additional_role') && !$entity->get('field_additional_role')->isEmpty()) {
                        $banner_data['field']['field_additional_role'] = $entity->get('field_additional_role')->value;
                    }

                    $banner_data['summary'] = '';
                    break;
                case 'journals':
                    $banner_data['terms'] = [
                        [
                            'type' => $entity_type,
                            'bundle' => $entity_bundle,
                            'label' => $this->getBundleLabel($entity)
                        ]
                    ];
                    break;
                case 'conferences':
                    $banner_data['terms'] = [
                        [
                            'type' => $entity_type,
                            'bundle' => $entity_bundle,
                            'label' => $this->getBundleLabel($entity)
                        ]
                    ];
                    // $banner_data['date_status'] = $this->getFieldDateRangeStatus($entity);
                    break;
                case 'resources':
                    $banner_data['terms'] = $this->resolveNodeTerms($entity, ['resources_category']);
                    break;
                default:
                    $banner_data['terms'] = $this->resolveNodeTerms($entity);
                    break;
            }
        } elseif ($entity instanceof TermInterface) {
            $banner_data = $this->resolveBaseData($entity) + [
                'parent_ids' => \Drupal::entityTypeManager()
                    ->getStorage('taxonomy_term')
                    ->loadParents($entity->id()),
            ];
        } elseif ($entity instanceof UserInterface) {
            $banner_data = $banner_data + $this->resolveBaseData($entity) + [
                'display_name' => $entity->getDisplayName(),
            ];
        } elseif (str_starts_with($route_name ?? '', 'view.')) {
            // Views pages carry no content entity on the route.
            $page_type  = 'view';
            $view_id    = $this->routeMatch->getParameter('view_id');
            $display_id = $this->routeMatch->getParameter('display_id');

            $banner_data = [
                'view_id'    => $view_id,
                'display_id' => $display_id,
                'title'      => '',
            ];

            // 1. Get the View executable
            $view = \Drupal\views\Views::getView($view_id);
            if (!$view) {
                return [];
            }

            /**
             * -------------------------
             * HelperBox ID extraction
             * -------------------------
             */
            $content_block_id = NULL;
            // 2. Set the specific display (page_1, default, etc.)
            $view->setDisplay($display_id);

            // 3. Get the display extenders
            $extenders = $view->display_handler->getExtenders();

            // 4. Safely retrieve the option
            if (
                isset($extenders['helperbox_display_extender']) &&
                $extenders['helperbox_display_extender'] instanceof \Drupal\helperbox\Plugin\views\display_extender\HelperBoxDisplayExtender
            ) {
                $enable_default_banner = $extenders['helperbox_display_extender']->options['helperbox_enable_default_banner'] ?? false;
                $content_block_id = $extenders['helperbox_display_extender']->options['helperbox_banner_block_content_id'] ?? NULL;
            }

            if (!$enable_default_banner && !$content_block_id) {
                return [];
            }

            // Target the news view and its specific display page.
            if ($route_name == 'view.content_update_listing.page_2') {
                $args = \Drupal::routeMatch()->getRawParameters()->all();
                $title = $view->getTitle();
                $year = $args['arg_0'] ?? NULL;
                // Scenario 2: /news/2026/all (year set, month is 'all' or empty).
                if ($year && $year != 'all') {
                    $view->setTitle(t($title . ' @year', ['@year' => $year ?? '']));
                }
                // Scenario 3: /news/2025/03 (both year and numeric month present).
                $month_numeric = $args['arg_1'] ?? NULL;
                if ($month_numeric && $month_numeric != 'all') {
                    $date_obj = \DateTime::createFromFormat('!m', $month_numeric);
                    $month_name = $date_obj ? $date_obj->format('F') : $month_numeric;
                    $view->setTitle(t($title . ' @year @month', [
                        '@year' => $year ?? '',
                        '@month' => $month_name ?? '',
                    ]));
                }
            }

            // 
            $banner_data['title'] = $view->getTitle(); //$view->display_handler->getOption('title');
            $entity_type          = 'view';
            $entity_bundle        = $view_id;
            $cache_tags           = $view->getCacheTags();
            // Optional: Destroy the view if you aren't going to render it 
            // to free up memory.
            $view->destroy();

            /**
             * -------------------------
             * Load Block Content
             * -------------------------
             */
            if (!$enable_default_banner && $content_block_id) {
                $block_entity = BlockContent::load((int) $content_block_id);
                if ($block_entity && $block_entity->hasTranslation($language)) {
                    $block_entity = $block_entity->getTranslation($language);
                }
                // field_heading
                $field_heading = $block_entity->label();
                if ($block_entity->hasField('field_heading') && !$block_entity->get('field_heading')->isEmpty()) {
                    $field_heading =  $block_entity->get('field_heading')->value;
                }
                // field_cta
                if ($block_entity->hasField('field_cta') && !$block_entity->get('field_cta')->isEmpty()) {
                    $cta_items = $block_entity->get('field_cta');
                    foreach ($cta_items as $item) {
                        $url_object = $item->getUrl();
                        $attributes = [];
                        $attributes['class'] = 'cta-wrapper link-btn';
                        $banner_data['cta'] = [
                            '#theme'       => 'helperbox_component_cta',
                            '#cta_url'     => $url_object->toString(),
                            '#cta_label'   =>  $item->title ?: $url_object->toString(),
                            '#cta_type'    => 'link',
                            '#cta_target'  => null,
                            '#is_external' => false,
                            '#is_no_link'  => FALSE,
                            '#attributes'  => new \Drupal\Core\Template\Attribute($attributes),
                            '#wrapper_attributes'     => new \Drupal\Core\Template\Attribute([
                                'class' => 'cta-link',
                            ])
                        ];
                    }
                }

                // title and summary
                $banner_data['title'] = $field_heading ? $field_heading : $banner_data['title'];
                $banner_data['summary'] = $this->resolveSummaryDescription($block_entity);

                // Edit btn check 
                $current_user = \Drupal::currentUser();
                $banner_data['edit_content'] = NULL;
                if ($block_entity->access('update', $current_user)) {
                    $banner_data['edit_content'] = Url::fromRoute(
                        'entity.block_content.edit_form',
                        ['block_content' => $content_block_id,],
                        ['query' => ['destination' => $current_path],]
                    )->toString();
                }
            }

            // 
        } elseif (str_starts_with($route_name ?? '', 'search.')) {
            $page_type = 'search';
            $entity_type = 'search';
            $entity_bundle = 'search';

            $request = \Drupal::request();
            $search_for = $request->query->get('keys') ?? '';

            $banner_data['title'] = $search_for ? $this->t('Results for "' . $search_for . '"') : $this->t('Search');
            $banner_data['search_form'] = \Drupal::formBuilder()->getForm(
                \Drupal\helperbox\Form\SearchForm::class
                // \Drupal\search\Form\SearchBlockForm::class
            );
        } else {
            return [];
        }

        // Breadcrumb condition
        $bc_excluded_ids = [];
        $bc_excluded_bundles = [];
        $bc_excluded_paths = [];
        if ((bool) ($this->configuration['enable_breadcrumb'] ?? TRUE)) {
            $bc = $this->configuration['breadcrumb_conditions'] ?? [];
            $bc_excluded_ids     = $bc['excluded']['id']            ?? [];
            $bc_excluded_bundles = $bc['excluded']['entity_bundle'] ?? [];
            $bc_excluded_paths   = $bc['excluded']['path']          ?? [];
        }

        // Social share condition
        $sc_excluded_ids = [];
        $sc_excluded_bundles = [];
        $sc_excluded_paths = [];
        if ((bool) ($this->configuration['enable_social_share'] ?? FALSE)) {
            $sc               = $this->configuration['social_share_conditions'] ?? [];
            $sc_excluded_ids     = $sc['excluded']['id']            ?? [];
            $sc_excluded_bundles = $sc['excluded']['entity_bundle'] ?? [];
            $sc_excluded_paths   = $sc['excluded']['path']          ?? [];
        }

        // header condition
        $hc               = $this->configuration['header_conditions'] ?? [];
        $hc_excluded_ids     = $hc['excluded']['id']            ?? [];
        $hc_excluded_bundles = $hc['excluded']['entity_bundle'] ?? [];
        $hc_excluded_paths   = $hc['excluded']['path']          ?? [];

        // banner condition check
        $banner_data['show_breadcrumb'] = !in_array((string) $entity_id, $bc_excluded_ids, TRUE)
            && !in_array($entity_bundle, $bc_excluded_bundles, TRUE)
            && !self::matchesExcludedPath($current_paths, $bc_excluded_paths);

        $banner_data['show_socialshare'] = !in_array((string) $entity_id, $sc_excluded_ids, TRUE)
            && !in_array($entity_bundle, $sc_excluded_bundles, TRUE)
            && !self::matchesExcludedPath($current_paths, $sc_excluded_paths);

        $banner_data['show_header'] = !in_array((string) $entity_id, $hc_excluded_ids, TRUE)
            && !in_array($entity_bundle, $hc_excluded_bundles, TRUE)
            && !self::matchesExcludedPath($current_paths, $hc_excluded_paths);

        // Return build
        return [
            '#theme'         => 'helperbox_block_herobanner',
            '#page_type'     => $page_type,
            '#entity_type'   => $entity_type,
            '#entity_bundle' => $entity_bundle,
            '#banner_data'   => $banner_data,
            // '#cache'         => [
            //     'contexts' => $cache_contexts,
            //     'tags'     => $cache_tags,
            //     'max-age'  => 0,
            // ],
        ];
    }

    /**
     * Detects and returns the primary content entity for the current route.
     *
     * Strategy:
     *   1. Use _entity_type_id route option — the authoritative declaration
     *      of what entity the page belongs to.
     *   2. Fall back to walking all route parameters for custom routes
     *      that don't follow the standard entity routing convention.
     *   3. Return NULL for unsaved entities (create forms) and config entities.
     */
    private function resolveCurrentEntity(): ?ContentEntityInterface {
        $entity = NULL;

        $route          = $this->routeMatch->getRouteObject();
        $entity_type_id = $route?->getOption('_entity_type_id')
            ?? $route?->getDefault('entity_type_id');

        if ($entity_type_id) {
            // Route explicitly declares what entity this page belongs to.
            $param = $this->routeMatch->getParameter($entity_type_id);

            if ($param instanceof ContentEntityInterface) {
                $entity = $param;
            } elseif ($param instanceof EntityFormInterface) {
                $entity = $param->getEntity();
            }
        }

        // Fallback: walk parameters when route has no _entity_type_id.
        if (!$entity) {
            foreach ($this->routeMatch->getParameters() as $param) {
                if ($param instanceof ContentEntityInterface) {
                    $entity = $param;
                    break;
                }
                if ($param instanceof EntityFormInterface) {
                    $entity = $param->getEntity();
                    break;
                }
            }
        }

        // Drop config entities and unsaved entities (e.g. node/add/article).
        if ($entity && (!$entity instanceof ContentEntityInterface || !$entity->id())) {
            return NULL;
        }

        return $entity;
    }

    /**
     * Returns common metadata shared across all entity type branches.
     *
     * @return array{page_type: string, entity_type: string, entity_bundle: string, cache_tags: array}
     */
    private function resolveEntityMeta(ContentEntityInterface $entity): array {
        return [
            'page_type'     => $entity->getEntityTypeId(),
            'entity_id'     => $entity->id(),
            'entity_type'   => $entity->getEntityTypeId(),
            'entity_bundle' => $entity->bundle(),
            'cache_tags'    => $entity->getCacheTags(),
        ];
    }

    /**
     * Gets the human-readable label for an entity bundle.
     *
     * For example:
     * - Node bundle "article" => "Article"
     * - Node bundle "page" => "Basic page"
     * - Taxonomy bundle "tags" => "Tags"
     *
     * Falls back to the bundle machine name if no label is found.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity whose bundle label should be retrieved.
     *
     * @return string
     *   The bundle label, or the bundle machine name if no label is available.
     */
    private function getBundleLabel(EntityInterface $entity): string {
        return \Drupal::service('entity_type.bundle.info')
            ->getBundleInfo($entity->getEntityTypeId())[$entity->bundle()]['label'] ?? $entity->bundle();
    }


    /**
     * Returns TRUE if any path in $current_paths matches any entry in
     * $excluded_paths.
     *
     * @param string[] $current_paths   Both system path and alias, e.g.
     *                                  ['/node/36', '/training/37th-advanced-...']
     * @param string[] $excluded_paths  Patterns configured in the block form,
     *                                  e.g. ['/training/*', '/node/36']
     */
    private static function matchesExcludedPath(
        array $current_paths,
        array $excluded_paths
    ): bool {
        foreach ($excluded_paths as $pattern) {
            foreach ($current_paths as $path) {
                // Exact match.
                if ($pattern === $path) {
                    return TRUE;
                }

                // Wildcard match.
                if (str_contains($pattern, '*')) {
                    $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
                    if (preg_match($regex, $path)) {
                        return TRUE;
                    }
                }
            }
        }

        return FALSE;
    }

    /**
     * Builds the common base banner data shared across all entity types.
     *
     * @return array{id: mixed, title: string, bundle: string, url: string, alias: string, description: string, banner_image: array|null}
     */
    private function resolveBaseData(ContentEntityInterface $entity): array {
        $raw_url = $entity->hasLinkTemplate('canonical')
            ? $entity->toUrl()->toString()
            : '';

        return [
            'type'         => $entity->getEntityTypeId(),
            'bundle'       => $entity->bundle(),
            'id'           => $entity->id(),
            'title'        => $entity->label(),
            'url'          => $raw_url,
            'summary'  => $this->resolveSummaryDescription($entity),
            'banner_image' => $this->resolveBannerImage($entity),
        ];
    }

    /**
     * Resolves all taxonomy terms referenced by the node, keyed by vocabulary.
     *
     * Example return value:
     * [
     *   'tags'     => [['id' => 1, 'name' => 'Drupal',], ...],
     *   'category' => [['id' => 5, 'name' => 'News',  ], ...],
     * ]
     */
    private function resolveNodeTerms(NodeInterface $node, array $allow_taxonomy = []): array {
        $terms = [];

        foreach ($node->getFieldDefinitions() as $field_name => $field_definition) {
            // Only entity reference fields targeting taxonomy terms.
            if ($field_definition->getType() !== 'entity_reference') {
                continue;
            }

            if ($field_definition->getSetting('target_type') !== 'taxonomy_term') {
                continue;
            }

            $handler = $field_definition->getSetting('handler');

            // Skip fields without configured vocabularies (except views handlers).
            if (!in_array($handler, ['views', 'views_select_list'], true)) {
                $target_bundles = $field_definition
                    ->getSetting('handler_settings')['target_bundles'] ?? [];

                if (empty($target_bundles)) {
                    continue;
                }
            }

            if ($node->get($field_name)->isEmpty()) {
                continue;
            }

            foreach ($node->get($field_name)->referencedEntities() as $term) {

                if (!$term instanceof TermInterface) {
                    continue;
                }

                // Filter by allowed vocabularies if provided
                if (count($allow_taxonomy) && !in_array($term->bundle(), $allow_taxonomy, true)) {
                    continue;
                }

                $terms[] = [
                    'type'      => $term->getEntityTypeId(),
                    'bundle'    => $term->bundle(),
                    'id'        => $term->id(),
                    'label'     => $term->label(),
                    'url'       => $term->toUrl(),
                ];
            }
        }

        return $terms;
    }

    /**
     * Resolves the banner description from common fields.
     *
     * Priority:
     *   1. field_highlight_text
     *   2. description (taxonomy terms)
     *   3. body summary (nodes)
     */
    private function resolveSummaryDescription(ContentEntityInterface $entity): string {
        // Highlight text takes highest priority.
        if ($entity->hasField('field_highlight_text') && !$entity->get('field_highlight_text')->isEmpty()) {
            return $entity->get('field_highlight_text')->value;
        }

        $length = 300;
        // Description field.
        if ($entity->hasField('description') && !$entity->get('description')->isEmpty()) {
            $description = $entity->get('description');
            if (!empty($description->summary)) {
                return $description->summary;
            }
            $plain = Html::decodeEntities(strip_tags(\check_markup($description->value, $description->format)));
            $plain = trim(preg_replace('/\s+/u', ' ', $plain));
            return mb_substr($plain, 0, $length, 'UTF-8');
        }

        // Body field.
        if ($entity->hasField('body') && !$entity->get('body')->isEmpty()) {
            $body = $entity->get('body');
            if (!empty($body->summary)) {
                return $body->summary;
            }

            $plain = Html::decodeEntities(strip_tags(\check_markup($body->value, $body->format)));
            $plain = trim(preg_replace('/\s+/u', ' ', $plain));
            return mb_substr($plain, 0, $length, 'UTF-8');
        }

        return '';
    }

    /**
     * Resolves the banner image from field_img if present.
     *
     * Returns NULL if the field is empty or the referenced media was deleted.
     */
    private function resolveBannerImage(ContentEntityInterface $entity): ?array {
        if ($entity->hasField('field_img') && !$entity->get('field_img')->isEmpty()) {
            $media_id = $entity->get('field_img')->entity?->id();
            if ($media_id) {
                return MediaHelper::get_media_library_info($media_id);
            }
        }

        return NULL;
    }
}
