<?php

namespace Drupal\helperbox\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helperbox\Helper\MediaHelper;

/**
 * Provides a 'Helperbox Helper Banner' Block.
 */
#[Block(
    id: "helperbox_repeated_content_block",
    admin_label: new TranslatableMarkup("Helperbox Repeated Content Block"),
    category: new TranslatableMarkup("helperbox"),
)]
class RepeatedContentBlock extends BlockBase {

    /**
     * {@inheritdoc}
     */
    public function build() {
        $config = $this->getConfiguration();
        // 
        $content_list = isset($config['content_list']) ? $config['content_list'] : [];
        if ($content_list) {
            foreach ($content_list as $key => $item) {
                $feature_image = isset($item['content']['feature_image']) ? $item['content']['feature_image'] : '';
                $feature_image_style = isset($item['content']['feature_image_style']) ? $item['content']['feature_image_style'] : '';
                $content_list[$key]['content']['feature_image'] = ($feature_image) ? MediaHelper::get_media_library_info($feature_image, $feature_image_style) : [];
            }
        }
        // 

        $block_layout = isset($config['block_layout']) ? $config['block_layout'] : '';
        $other_meta_info = isset($config['other_meta_info']) ? $config['other_meta_info'] : [];
        if ($other_meta_info) {
            $background_image = isset($other_meta_info['background_image']) ? $other_meta_info['background_image'] : '';
            if ($background_image) {
                $background_image_style = isset($other_meta_info['background_image_style']) ? $other_meta_info['background_image_style'] : '';
                $other_meta_info['background_image'] = ($background_image) ? MediaHelper::get_media_library_info($background_image, $background_image_style) : [];
            }
        }
        // 
        return [
            '#theme' => 'helperbox_repeatedcontent',
            '#block_layout' => $block_layout,
            '#section_title' => isset($config['section_title']) ? $config['section_title'] : '',
            '#section_sub_title' => isset($config['section_sub_title']) ? $config['section_sub_title'] : '',
            '#content_list' => $content_list,
            '#other_meta_info' => $other_meta_info,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration() {
        return [
            'block_layout' => '',
            'section_title' => '',
            'section_sub_title' => '',
            'content_list' => [],
            'other_meta_info' => '',
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state) {
        // $form = parent::blockForm($form, $form_state);
        $config = $this->getConfiguration();

        /**
         * block_layout
         */
        $block_layout_selected = isset($config['block_layout']) ? $config['block_layout'] : 'home_hero_banner';
        $options = [
            '' => 'Select Layout Type',
        ];
        $options['repeated_accordion_description'] = "Accordion with image description";
        $options['repeated_title_list'] = "Repeated Title list";

        $form['block_layout'] = array(
            '#type' => 'select',
            '#title' => $this->t('Block Layout Style:'),
            '#options' => $options,
            '#default_value' => $block_layout_selected,
            '#required' => TRUE
        );

        /**
         * section_title
         */
        $form['section_title'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Block Section Title:'),
            '#default_value' => isset($config['section_title']) ? $config['section_title'] : '',
        );
        /**
         * section_sub_title
         */
        $form['section_sub_title'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Block Section Sub Title:'),
            '#default_value' => isset($config['section_sub_title']) ? $config['section_sub_title'] : '',
        );


        /**
         * Repeated field
         */
        $content_list = isset($config['content_list']) ? $config['content_list'] : [];
        if (!$form_state->has('num_items')) {
            $form_state->set('num_items', count($content_list));
        }
        $num_items = $form_state->get('num_items');
        // 
        if (!$form_state->has('removed_fields')) {
            $form_state->set('removed_fields', []);
        }
        $removed_fields = $form_state->get('removed_fields');
        // 
        $form['#tree'] = TRUE;
        $form['repeated_fieldset'] = [
            '#type' => 'details',
            '#open' => TRUE,
            '#title' => $this->t(' Repeated Content'),
            '#prefix' => '<div id="items-fieldset-wrapper">',
            '#suffix' => '</div>',
            'content_item' => [
                '#type' => 'table',
                '#empty' => $this->t('No Content available.'),
                '#tabledrag' => [
                    [
                        'action' => 'order',
                        'relationship' => 'sibling',
                        'group' => 'content_item-weight',
                    ],
                ],
            ],
            'actions' => [
                '#type' => 'actions',
                'add_item' => [
                    '#type' => 'submit',
                    '#value' => $this->t('Add Content'),
                    '#submit' => [[$this, 'addOne']],
                    '#ajax' => [
                        'callback' => [$this, 'addmoreCallback'],
                        'wrapper' => 'items-fieldset-wrapper',
                    ],
                ]
            ]
        ];

        for ($i = 0; $i < $num_items; $i++) {
            if (in_array('content_item-' . $i, $removed_fields)) {
                continue;
            }
            $content_list = array_values($content_list);
            $content = isset($content_list[$i]['content']) ? $content_list[$i]['content'] : [];

            $form['repeated_fieldset']['content_item'][$i]['#attributes']['class'][] = 'draggable';
            $form['repeated_fieldset']['content_item'][$i]['dragdrop']['#attributes']['class'][] = 'draggable';

            $title = isset($content['title']) ? $content['title'] : $this->t('Item @num', ['@num' => $i + 1]);

            $form['repeated_fieldset']['content_item'][$i]['content'] = [
                '#type' => 'details',
                '#title' => $title,
                '#open' => TRUE,
                'title' => [
                    '#type' => 'textfield',
                    '#title' => $this->t('Title'),
                    '#default_value' => isset($content['title']) ? $content['title'] : '',
                ],
                'sub_title' => [
                    '#type' => 'textfield',
                    '#title' => $this->t('Sub Title'),
                    '#default_value' => isset($content['sub_title']) ? $content['sub_title'] : '',
                ],
                'description' => [
                    '#type' => 'text_format',
                    '#title' => $this->t('Description'),
                    '#default_value' => isset($content['description']['value']) ? $content['description']['value'] : '',
                    '#format' => isset($content['description']['format']) ? $content['description']['format'] : 'basic_html',
                ],
                'feature_image' => [
                    '#type' => 'media_library',
                    '#title' => $this->t('Feature images'),
                    '#allowed_bundles' => ['image'],
                    '#cardinality' => 1,
                    '#default_value' => isset($content['feature_image']) ? $content['feature_image'] : NULL,
                    '#description' => $this->t('Select or upload an image.'),
                    '#prefix' => '<div id="feature-image-wrapper">',
                    '#suffix' => '</div>',
                    '#states' => [
                        'visible' => [
                            ':input[name="settings[block_layout]"]' => ['value' => 'repeated_accordion_description'],
                        ],
                    ],
                ],
                'feature_image_style' => [
                    '#type' => 'select',
                    '#title' => $this->t('Feature Image Style:'),
                    '#options' => MediaHelper::get_image_style_options(),
                    '#default_value' => isset($content['feature_image_style']) ? $content['feature_image_style'] : '',
                    '#states' => [
                        'visible' => [
                            ':input[name="settings[block_layout]"]' => ['value' => 'repeated_accordion_description']
                        ],
                    ],
                ],
                'action' => [
                    'remove_content_item' => [
                        '#type' => 'submit',
                        '#name' => 'content_item-' . $i,
                        '#value' => $this->t('Remove'),
                        '#submit' => [[$this, 'removeOne']],
                        '#ajax' => [
                            'callback' => [$this, 'addmoreCallback'],
                            'wrapper' => 'items-fieldset-wrapper',
                        ],
                    ]
                ]
            ];

            $form['repeated_fieldset']['content_item'][$i]['weight'] = [
                '#type' => 'weight',
                '#title' => $this->t('Weight'),
                '#default_value' => $content_list[$i]['weight'] ?? $i,
                '#attributes' => ['class' => ['content_item-weight']],
            ];
        }



        // other_meta_info
        $other_meta_info = isset($config['other_meta_info']) ? $config['other_meta_info'] : [];
        $form['other_meta_info'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Other Data for the block'),
            '#states' => [
                'visible' => [
                    [':input[name="settings[block_layout]"]' => ['value' => 'repeated_title_list']],
                ],
            ],
            'background_image' => [
                '#type' => 'media_library',
                '#title' => $this->t('Background images'),
                '#allowed_bundles' => ['image'],
                '#cardinality' => 1,
                '#default_value' => isset($other_meta_info['background_image']) ? $other_meta_info['background_image'] : NULL,
                '#description' => $this->t('Select or upload an image.'),
                '#prefix' => '<div id="background-image-wrapper">',
                '#suffix' => '</div>',
            ],
            'background_image_style' => [
                '#type' => 'select',
                '#title' => $this->t('Background Image Style:'),
                '#options' => MediaHelper::get_image_style_options(),
                '#default_value' => isset($other_meta_info['background_image_style']) ? $other_meta_info['background_image_style'] : '',
            ]
        ];

        // 
        return $form;
    }


    /**
     * 
     */
    public function removeOne(array &$form, FormStateInterface $form_state) {
        $trigger = $form_state->getTriggeringElement();
        $indexToRemove = $trigger['#name'];
        $removed_fields = $form_state->get('removed_fields');
        $removed_fields[] = $indexToRemove;
        $form_state->set('removed_fields', $removed_fields);
        // Rebuild form_state
        $form_state->setRebuild();
    }


    /**
     * @param array $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     */
    public function addOne(array &$form, FormStateInterface $form_state) {
        $name_field = $form_state->get('num_items');
        $add_button = $name_field + 1;
        $form_state->set('num_items', $add_button);
        $form_state->setRebuild();
    }

    /**
     * @param array $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     * @return mixed
     */
    public function addmoreCallback(array &$form, FormStateInterface $form_state) {
        // The form passed here is the entire form, not the subform that is
        // passed to non-AJAX callback.
        return $form['settings']['repeated_fieldset'];
    }


    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state) {
        // Save our custom settings when the form is submitted.
        $block_layout = ($form_state->getValue('block_layout')) ?: '';
        $this->setConfigurationValue('block_layout', $block_layout);
        $this->setConfigurationValue('section_title', $form_state->getValue('section_title'));
        $this->setConfigurationValue('section_sub_title', $form_state->getValue('section_sub_title'));
        // $this->setConfigurationValue('content_list', $form_state->getValue(['repeated_fieldset', 'content_item']));

        if ($block_layout == 'repeated_accordion_description') {
            $this->setConfigurationValue('other_meta_info', '');
        } else if ($block_layout == 'repeated_title_list') {
            $this->setConfigurationValue('other_meta_info', $form_state->getValue('other_meta_info'));
        } else {
            $this->setConfigurationValue('other_meta_info', '');
        }


        $content_item = $form_state->getValue(['repeated_fieldset', 'content_item']);
        $content_list = [];
        if (is_array($content_item) || is_object($content_item)) {
            foreach ($content_item as $key => $value) {
                if (trim($value['content']['title'])) {
                    $content_list[] = $value;
                }
            }
        }

        $this->configuration['content_list'] = $content_list;
    }
}
