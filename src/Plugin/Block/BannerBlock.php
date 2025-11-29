<?php

/**
 * Reference::
 * https://drupalize.me/
 * https://www.drupal.org/docs/creating-modules/creating-custom-blocks/create-a-custom-block-plugin?utm_source=chatgpt.com
 * https://www.drupal.org/docs/user_guide/en/block-create-custom.html?utm_source=chatgpt.com
 * https://www.drupal.org/project/media_library_form_element
 * https://drupal.stackexchange.com/questions/267317/how-can-i-use-a-media-field-in-a-custom-form
 * https://www.drupal.org/forum/support/post-installation/2018-04-23/blockform-add-more-field-with-autocomplete
 * https://api.drupal.org/api/examples/ajax_example!ajax_example_graceful_degradation.inc/function/ajax_example_add_more/7.x-1.x
 * https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
 * https://www.drupal.org/docs/develop/drupal-apis/javascript-api/ajax-forms
 *
 */

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
  id: "helperbox_banner_block",
  admin_label: new TranslatableMarkup("Helperbox Banner Block"),
  category: new TranslatableMarkup("Helperbox"),
)]
class BannerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    //
    $block_layout = isset($config['block_layout']) ? $config['block_layout'] : '';
    // feature_video
    $feature_video = isset($config['feature_video']) ? $config['feature_video'] : '';
    $feature_video = ($feature_video) ? MediaHelper::get_media_library_info($feature_video) : [];
    // feature_image
    $feature_image = isset($config['feature_image']) ? $config['feature_image'] : '';
    $feature_image_style = isset($config['feature_image_style']) ? $config['feature_image_style'] : '';
    $feature_image = ($feature_image) ? MediaHelper::get_media_library_info($feature_image, $feature_image_style) : [];
    // secondary_body
    $secondary_body_enable = isset($config['secondary_body_enable']) ? $config['secondary_body_enable'] : '';
    $section_body_title_2 = $section_body_desc_2 = '';
    if ($secondary_body_enable) {
      $section_body_title_2 = isset($config['section_body_title_2']) ? $config['section_body_title_2'] : '';
      $section_body_desc_2 = isset($config['section_body_desc_2']['value']) ? $config['section_body_desc_2']['value'] : '';
    }
    //
    $section_highlight_enable = isset($config['section_highlight_enable']) ? $config['section_highlight_enable'] : '';
    $section_highlight = '';
    if ($section_highlight_enable) {
      $section_highlight = isset($config['section_highlight']['value']) ? $config['section_highlight']['value'] : '';
    }
    //
    $background_image = isset($config['background_image']) ? $config['background_image'] : '';
    if ($background_image) {
      $background_image_style = isset($config['background_image_style']) ? $config['background_image_style'] : '';
      $background_image = ($background_image) ? MediaHelper::get_media_library_info($background_image, $background_image_style) : [];
    }
    //
    $lottie_file = isset($config['lottie_file']) ? $config['lottie_file'] : '';
    if ($lottie_file) {
      $lottie_file = MediaHelper::get_media_library_info($lottie_file);
    }
    //
    return [
      '#theme' => 'helperbox_banner',
      '#section_title' => isset($config['section_title']) ? $config['section_title'] : '',
      '#section_sub_title' => isset($config['section_sub_title']) ? $config['section_sub_title'] : '',
      '#section_body' => isset($config['section_body']['value']) ? $config['section_body']['value'] : '',
      '#feature_video' =>  $feature_video,
      '#feature_image' =>  $feature_image,
      '#cta_links' => isset($config['cta_links']) ? $config['cta_links'] : [],
      '#video_links' => isset($config['video_links']) ? $config['video_links'] : [],
      '#block_layout' => $block_layout,
      '#section_highlight_enable' => $section_highlight_enable,
      '#section_highlight' => $section_highlight,
      '#background_image' =>  $background_image,
      '#secondary_body_enable' => $secondary_body_enable,
      '#section_body_title_2' => $section_body_title_2,
      '#section_body_desc_2' => $section_body_desc_2,
      '#lottie_file' => $lottie_file,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'section_title' => '',
      'section_sub_title' => '',
      'section_body' => '',
      'feature_video' => [],
      'feature_image' => [],
      'cta_links' => [],
      'video_links' => [],
      'block_layout' => '',
      'section_highlight' => '',
      'section_highlight_enable' => '',
      'background_image' => '',
      'secondary_body_enable' => '',
      'section_body_title_2' => '',
      'section_body_desc_2' => '',
      'lottie_file' => '',
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['#attached']['library'][] = 'helperbox/helperbox_admin_styles';
    /**
     * block_layout
     */
    $block_layout_selected = isset($config['block_layout']) ? $config['block_layout'] : 'home_hero_banner';
    $options = [
      'default' => 'Default',
      'home_hero_banner' => "Home Hero Banner",
      'layout_image_right' => "Layout Image Right - 1 Block",
      'layout_image_left' => "Layout Image Left - 1 Block",
    ];
    $form['block_layout'] = array(
      '#type' => 'select',
      '#title' => $this->t('Block Layout Style:'),
      '#options' => $options,
      '#default_value' => $block_layout_selected,
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
      '#prefix' => '<div id="section-sub-title-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'default_layout']],
        ],
      ],
    );
    /**
     *
     */
    $form['feature_video'] = [
      '#type' => 'media_library',
      '#title' => $this->t('Feature Video'),
      '#allowed_bundles' => ['video', 'remote_video'],
      '#cardinality' => 4,
      '#default_value' => isset($config['feature_video']) ? $config['feature_video'] : NULL,
      '#description' => $this->t('Select or upload an video.'),
      '#prefix' => '<div id="feature-video-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'default_layout']],
        ],
      ],
    ];
    //
    $form = $this->associated_video_links_fields($form, $form_state, $config);

    /**
     * feature_image
     */
    $cardinality = 3;
    // $cardinality = -1;
    $form['feature_image'] = [
      '#type' => 'media_library',
      '#title' => $this->t('Feature images'),
      '#allowed_bundles' => ['image'],
      '#cardinality' => $cardinality,
      '#default_value' => isset($config['feature_image']) ? $config['feature_image'] : NULL,
      '#description' => $this->t('Select or upload an image.'),
      '#prefix' => '<div id="feature-image-wrapper">',
      '#suffix' => '</div>',
    ];

    /**
     * feature_image_style
     */
    $form['feature_image_style'] = array(
      '#type' => 'select',
      '#title' => $this->t('Feature Image Style:'),
      '#options' => MediaHelper::get_image_style_options(),
      '#default_value' => isset($config['feature_image_style']) ? $config['feature_image_style'] : '',
    );

    /**
     * lottie_file
     */
    $form['lottie_file'] = [
      '#type' => 'media_library',
      '#title' => $this->t('Lottie File'),
      '#prefix' => '<div id="lottie-file-wrapper">',
      '#suffix' => '</div>',
      '#allowed_bundles' => ['json'],
      '#cardinality' => 3,
      '#default_value' => isset($config['lottie_file']) ? $config['lottie_file'] : NULL,
      '#description' => $this->t('Select or upload an lottie file. This will be used for animation in the background and replace feature image.'),
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'home_hero_banner']]
        ],
      ],
    ];

    /**
     * background_image
     */
    $form['background_image'] = [
      '#type' => 'media_library',
      '#title' => $this->t('Background images'),
      '#allowed_bundles' => ['image'],
      '#cardinality' => 1,
      '#default_value' => isset($config['background_image']) ? $config['background_image'] : NULL,
      '#description' => $this->t('Select or upload an image.'),
      '#prefix' => '<div id="background-image-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'default_layout']],
        ],
      ],
    ];

    /**
     * background_image_style
     */
    $form['background_image_style'] = array(
      '#type' => 'select',
      '#title' => $this->t('Background Image Style:'),
      '#options' => MediaHelper::get_image_style_options(),
      '#default_value' => isset($config['background_image_style']) ? $config['background_image_style'] : '',
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'default_layout']],
        ],
      ],
    );

    /**
     * body field
     */
    $form['section_body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Section Body Description'),
      '#default_value' => isset($config['section_body']['value']) ? $config['section_body']['value'] : '',
      '#format' => isset($config['section_body']['format']) ? $config['section_body']['format'] : 'basic_html',
    ];

    // Secondary Body
    /**
     *
     */
    $form['secondary_body_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Secondary Body'),
      '#description' => $this->t(''),
      '#default_value' => isset($config['secondary_body_enable']) ? $config['secondary_body_enable'] : 0,
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'default_layout']],
          [':input[name="settings[block_layout]"]' => ['value' => 'layout_image_right']],
          [':input[name="settings[block_layout]"]' => ['value' => 'layout_image_left']],
        ],
      ],
    ];

    /**
     * section_body_title_2
     */
    $form['section_body_title_2'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Section Secondary Body Title:'),
      '#default_value' => isset($config['section_body_title_2']) ? $config['section_body_title_2'] : '',
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'layout_image_right'],
            ':input[name="settings[secondary_body_enable]"]' => ['checked' => TRUE]
          ],
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'layout_image_left'],
            ':input[name="settings[secondary_body_enable]"]' => ['checked' => TRUE]
          ],
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'default_layout'],
            ':input[name="settings[secondary_body_enable]"]' => ['checked' => TRUE]
          ],
        ],
      ],
    );

    /**
     * section_highlight body field
     */
    $form['section_body_desc_2'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Section Secondary Body Description'),
      '#default_value' => isset($config['section_body_desc_2']['value']) ? $config['section_body_desc_2']['value'] : '',
      '#format' => isset($config['section_body_desc_2']['format']) ? $config['section_body_desc_2']['format'] : 'basic_html',
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'layout_image_right'],
            ':input[name="settings[secondary_body_enable]"]' => ['checked' => TRUE]
          ],
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'layout_image_left'],
            ':input[name="settings[secondary_body_enable]"]' => ['checked' => TRUE]
          ],
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'default_layout'],
            ':input[name="settings[secondary_body_enable]"]' => ['checked' => TRUE]
          ],
        ],
      ],
    ];

    /**
     *
     */
    $form['section_highlight_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable section highlight '),
      '#description' => $this->t(''),
      '#default_value' => isset($config['section_highlight_enable']) ? $config['section_highlight_enable'] : 1,
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'default_layout']],
          [':input[name="settings[block_layout]"]' => ['value' => 'layout_image_right']],
          [':input[name="settings[block_layout]"]' => ['value' => 'layout_image_left']]
        ],
      ],
    ];

    /**
     * section_highlight
     */
    $form['section_highlight'] = array(
      '#type' => 'text_format', //'textarea',
      '#title' => $this->t('Section Highlight'),
      '#default_value' => isset($config['section_highlight']['value']) ? $config['section_highlight']['value'] : '',
      '#format' => isset($config['section_highlight']['format']) ? $config['section_highlight']['format'] : 'basic_html',
      '#prefix' => '<div id="highlight-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'layout_image_right'],
            ':input[name="settings[section_highlight_enable]"]' => ['checked' => TRUE],
          ],
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'layout_image_left'],
            ':input[name="settings[section_highlight_enable]"]' => ['checked' => TRUE],
          ],
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'default_layout'],
            ':input[name="settings[section_highlight_enable]"]' => ['checked' => TRUE]
          ],
        ],
      ],
    );

    //
    $form = $this->cta_fields($form, $form_state, $config);

    //
    return $form;
  }


  /**
   * Generic function to add one item to a fieldset.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addOneGeneric(array &$form, FormStateInterface $form_state) {
    $type = $form_state->getTriggeringElement()['#custom_type'] ?? '';
    $count_key = $type . '_num_items';

    $current_count = $form_state->get($count_key) ?? 0;
    $form_state->set($count_key, $current_count + 1);
    $form_state->setRebuild();
  }

  /**
   * Generic function to remove one item from a fieldset.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function removeOneGeneric(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $indexToRemove = $trigger['#name'];

    $type = $trigger['#custom_type'] ?? '';
    $removed_key = 'removed_' . $type . '_fields';

    $removed_fields = $form_state->get($removed_key) ?? [];
    $removed_fields[] = $indexToRemove;

    $form_state->set($removed_key, $removed_fields);
    $form_state->setRebuild();
  }

  /**
   * Generic callback for fieldset.
   *
   * This function is used to return the fieldset content when an AJAX
   * request is made.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @return array
   */
  public function fieldsetCallbackGeneric(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $fieldset_key = $trigger['#callback_fieldset_key'] ?? '';
    return $form['settings'][$fieldset_key] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('section_title', $form_state->getValue('section_title'));
    $this->setConfigurationValue('section_sub_title', $form_state->getValue('section_sub_title'));
    $this->setConfigurationValue('section_body', $form_state->getValue('section_body'));
    $this->setConfigurationValue('feature_video', $form_state->getValue('feature_video'));
    $this->setConfigurationValue('feature_image', $form_state->getValue('feature_image'));
    $this->configuration['feature_image_style'] = $form_state->getValue('feature_image_style');
    $this->setConfigurationValue('lottie_file', $form_state->getValue('lottie_file'));
    $this->setConfigurationValue('block_layout', $form_state->getValue('block_layout'));
    $this->setConfigurationValue('background_image', $form_state->getValue('background_image'));
    $this->setConfigurationValue('background_image_style', $form_state->getValue('background_image_style'));
    $this->setConfigurationValue('section_highlight', $form_state->getValue('section_highlight'));
    $this->setConfigurationValue('section_highlight_enable', $form_state->getValue('section_highlight_enable'));
    //
    $this->setConfigurationValue('secondary_body_enable', $form_state->getValue('secondary_body_enable'));
    $this->setConfigurationValue('section_body_title_2', $form_state->getValue('section_body_title_2'));
    $this->setConfigurationValue('section_body_desc_2', $form_state->getValue('section_body_desc_2'));
    //
    $cta_fieldset = $form_state->getValue('cta_fieldset');
    $cta = isset($cta_fieldset['cta']) ? $cta_fieldset['cta'] : [];
    $cta_links = [];
    if (is_array($cta) || is_object($cta)) {
      foreach ($cta as $key => $value) {
        if (trim($value['label'])) {
          $cta_links[] = $value;
        }
      }
    }
    $this->configuration['cta_links'] = $cta_links;
    //
    $video_links_fieldset = $form_state->getValue('video_links_fieldset');
    $links = isset($video_links_fieldset['links']) ? $video_links_fieldset['links'] : [];
    $video_links = [];
    if (is_array($links) || is_object($links)) {
      foreach ($links as $key => $value) {
        $video_links[] = $value;
      }
    }
    $this->configuration['video_links'] = $video_links;
  }


  /**
   * Function to handle CTA fields.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $config
   * @return array
   */
  function cta_fields($form, FormStateInterface $form_state, $config) {
    /**
     * CTA field
     */
    $cta_links = isset($config['cta_links']) ? $config['cta_links'] : [];
    $cta_count_key = 'cta_num_items';
    if (!$form_state->has($cta_count_key)) {
      $form_state->set($cta_count_key, count($cta_links));
    }
    $cta_num_items = $form_state->get($cta_count_key);
    // removed_video_links_fields
    if (!$form_state->has('removed_cta_fields')) {
      $form_state->set('removed_cta_fields', []);
    }
    $removed_cta_fields = $form_state->get('removed_cta_fields');
    //
    $form['#tree'] = TRUE;
    $cta_file_wrapper = 'cta-items-fieldset-wrapper';
    $form['cta_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CTA Buttons'),
      '#prefix' => '<div id="' . $cta_file_wrapper . '">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'default_layout']],
          [':input[name="settings[block_layout]"]' => ['value' => 'home_hero_banner']],
        ],
      ],
    ];

    $form['cta_fieldset']['cta'] = [
      '#type' => 'table',
      '#header' => [
        $this->t(''),
        $this->t('Label'),
        $this->t('URL'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No CTAs available.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'cta-weight',
        ],
      ],
    ];
    for ($i = 0; $i < $cta_num_items; $i++) {
      if (in_array('cta-' . $i, $removed_cta_fields)) {
        continue;
      }
      $cta_links = array_values($cta_links);

      $form['cta_fieldset']['cta'][$i]['#attributes']['class'][] = 'draggable';

      $form['cta_fieldset']['cta'][$i]['dragdrop']['#attributes']['class'][] = 'draggable';

      $form['cta_fieldset']['cta'][$i]['label'] = [
        '#type' => 'textfield',
        '#default_value' => isset($cta_links[$i]['label']) ? $cta_links[$i]['label'] : '',
        // '#required' => TRUE,
      ];

      $form['cta_fieldset']['cta'][$i]['url'] = [
        '#type' => 'textfield',
        '#default_value' => isset($cta_links[$i]['url']) ? $cta_links[$i]['url'] : '',
      ];

      $form['cta_fieldset']['cta'][$i]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => isset($cta_links[$i]['weight']) ? $cta_links[$i]['weight'] : $i,
        '#attributes' => ['class' => ['cta-weight']],
      ];

      $form['cta_fieldset']['cta'][$i]['action']['remove_cta'] = [
        '#type' => 'submit',
        '#name' => 'cta-' . $i,
        '#value' => $this->t('Remove'),
        '#custom_type' => 'cta',
        '#callback_fieldset_key' => 'cta_fieldset',
        '#submit' => [[$this, 'removeOneGeneric']],
        '#ajax' => [
          'callback' => [$this, 'fieldsetCallbackGeneric'],
          'wrapper' => $cta_file_wrapper,
        ],
      ];
    }
    $form['cta_fieldset']['actions'] = [
      '#type' => 'actions',
    ];
    $form['cta_fieldset']['actions']['add_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add CTA'),
      '#custom_type' => 'cta',
      '#callback_fieldset_key' => 'cta_fieldset',
      '#submit' => [[$this, 'addOneGeneric']],
      '#ajax' => [
        'callback' => [$this, 'fieldsetCallbackGeneric'],
        'wrapper' => $cta_file_wrapper,
      ],
    ];
    return $form;
  }

  /**
   * Function to handle associated video links.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $config
   */
  public function associated_video_links_fields($form, FormStateInterface $form_state, $config) {
    /**
     * 
     */
    $video_links = isset($config['video_links']) ? $config['video_links'] : [];
    $video_link_count_key = 'video_links_num_items';
    if (!$form_state->has($video_link_count_key)) {
      $form_state->set($video_link_count_key, count($video_links));
    }
    $video_links_num_items = $form_state->get($video_link_count_key);
    //
    if (!$form_state->has('removed_video_links_fields')) {
      $form_state->set('removed_video_links_fields', []);
    }
    $removed_video_links_fields = $form_state->get('removed_video_links_fields');
    //
    $form['#tree'] = TRUE;
    $video_field_wrapper = 'video-links-fieldset-wrapper';
    $form['video_links_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Associated Video links'),
      '#prefix' => '<div id="' . $video_field_wrapper . '">',
      '#suffix' => '</div>',
    ];
    $form['video_links_fieldset']['links'] = [
      '#type' => 'table',
      '#header' => [
        $this->t(''),
        $this->t('URL'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No Video Links Available.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'video-links-weight',
        ],
      ],
    ];
    for ($i = 0; $i < $video_links_num_items; $i++) {
      if (in_array('links-' . $i, $removed_video_links_fields)) {
        continue;
      }
      $video_links = array_values($video_links);

      $form['video_links_fieldset']['links'][$i]['#attributes']['class'][] = 'draggable';

      $form['video_links_fieldset']['links'][$i]['dragdrop']['#attributes']['class'][] = 'draggable';

      $form['video_links_fieldset']['links'][$i]['url'] = [
        '#type' => 'textfield',
        '#default_value' => isset($video_links[$i]['url']) ? $video_links[$i]['url'] : '',
      ];

      $form['video_links_fieldset']['links'][$i]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => isset($video_links[$i]['weight']) ? $video_links[$i]['weight'] : $i,
        '#attributes' => ['class' => ['video-links-weight']],
      ];

      $form['video_links_fieldset']['links'][$i]['action']['remove_links'] = [
        '#type' => 'submit',
        '#name' => 'links-' . $i,
        '#value' => $this->t('Remove'),
        '#custom_type' => 'video_links',
        '#callback_fieldset_key' => 'video_links_fieldset',
        '#submit' => [[$this, 'removeOneGeneric']],
        '#ajax' => [
          'callback' => [$this, 'fieldsetCallbackGeneric'],
          'wrapper' => $video_field_wrapper,
        ],
      ];
    }
    $form['video_links_fieldset']['actions'] = [
      '#type' => 'actions',
    ];
    $form['video_links_fieldset']['actions']['add_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add links'),
      '#custom_type' => 'video_links',
      '#callback_fieldset_key' => 'video_links_fieldset',
      '#submit' => [[$this, 'addOneGeneric']],
      '#ajax' => [
        'callback' => [$this, 'fieldsetCallbackGeneric'],
        'wrapper' => $video_field_wrapper,
      ],
    ];

    return $form;
  }

  /**
   *
   */
}
