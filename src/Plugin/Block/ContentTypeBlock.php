<?php

namespace Drupal\helperbox\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helperbox\Helper\MediaHelper;
use Drupal\helperbox\Helper\QueryNode;

/**
 * Provides a 'Helperbox Content Block' Block.
 *
 */
#[Block(
  id: "helperbox_content_type_block",
  admin_label: new TranslatableMarkup("Helperbox Content Type Block"),
  category: new TranslatableMarkup("Helperbox"),
)]
class ContentTypeBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    // 
    $content = '';
    $other_meta_info = '';
    $block_layout = isset($config['block_layout']) ? $config['block_layout'] : 'home_our_services';
    $custom_content_node = isset($config['custom_content_node']) ? $config['custom_content_node'] : '';
    if (isset($custom_content_node['enable']) && $custom_content_node['enable']) {
      $custom_selected_node = isset($custom_content_node['select_node']) ? $custom_content_node['select_node'] : '';
    } else {
      $custom_selected_node = '';
    }
    if ($block_layout === 'home_our_services' || $block_layout === 'about_our_services_2' || $block_layout === 'about_our_services') {
      $content = QueryNode::get_content_block_services_info(0, $custom_selected_node);
    } else if ($block_layout === 'sc_studio_our_work') {
      $content = QueryNode::get_content_block_project_info(3, $custom_selected_node);
      $other_meta_info = isset($config['other_meta_info']) ? $config['other_meta_info'] : [];
      $background_image = isset($other_meta_info['background_image']) ? $other_meta_info['background_image'] : '';
      if ($background_image) {
        $background_image_style = isset($other_meta_info['background_image_style']) ? $other_meta_info['background_image_style'] : '';
        $other_meta_info['background_image'] = ($background_image) ? MediaHelper::get_media_library_info($background_image, $background_image_style) : [];
      }
    } else {
      $content = [];
    }
    $show_edit_btn = isset($config['show_edit_btn']) ? $config['show_edit_btn'] : 0;
    // 
    return [
      '#theme' => 'helperbox_content',
      '#section_title' => isset($config['section_title']) ? $config['section_title'] : '',
      '#section_sub_title' => isset($config['section_sub_title']) ? $config['section_sub_title'] : '',
      '#block_layout' => $block_layout,
      '#content' => $content,
      '#show_edit_btn' => $show_edit_btn,
      '#node_view_label' => isset($config['node_view_label']) ? $config['node_view_label'] : '',
      '#view_all_cta' => isset($config['view_all_cta']) ? $config['view_all_cta'] : [],
      '#other_meta_info' => $other_meta_info,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'section_title' => '',
      'section_sub_title' => '',
      'block_layout' => '',
      'content' => [],
      'show_edit_btn' => 0,
      'node_view_label' => '',
      'view_all_cta' => '',
      'other_meta_info' => '',
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['#attached']['library'][] = 'helperbox/helperbox_styles';
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
     * selected_content_type
     */
    // $selected_content_type = isset($config['selected_content_type']) ? $config['selected_content_type'] : 'services';
    // $content_type_options = [];
    // $content_types = NodeType::loadMultiple();
    // foreach ($content_types as $machine_name => $content_type) {
    //   $content_type_options[$machine_name] = $content_type->label();
    // }
    // $form['selected_content_type'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Select content type'),
    //   '#options' => $content_type_options,
    //   '#default_value' => $selected_content_type,
    // ];

    /**
     * block_layout
     */
    $block_layout_selected = isset($config['block_layout']) ? $config['block_layout'] : 'home_hero_banner';
    $options = [
      '' => 'Select Layout Type',
    ];
    $options['home_our_services'] = "Home - Our Services";
    $options['about_our_services'] = "About - Our Services";
    $options['about_our_services_2'] = "About - Our Services with summary";
    $options['sc_studio_our_work'] = "SC Studio - Our Work Projects";

    $form['block_layout'] = array(
      '#type' => 'select',
      '#title' => $this->t('Block Layout Style:'),
      '#options' => $options,
      '#default_value' => $block_layout_selected,
      '#required' => TRUE
    );

    // show_edit_btn
    $form['show_edit_btn'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Content Edit Button'),
      '#default_value' => isset($config['show_edit_btn']) ? $config['show_edit_btn'] : 0,
    ];

    // enable_selected_node
    $custom_content_node = isset($config['custom_content_node']) ? $config['custom_content_node'] : [];
    $form['custom_content_node'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom Content node'),
      'enable' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable to select custom content node'),
        '#default_value' => isset($custom_content_node['enable']) ? $custom_content_node['enable'] : 0,
      ],
      'select_node' => [
        '#type' => 'textfield',
        '#title' => $this->t('Enter each content node ids:'),
        '#default_value' => isset($custom_content_node['select_node']) ? $custom_content_node['select_node'] : '',
        '#placeholder' => $this->t('12,44,73'),
        '#description' => $this->t('Enter the content node ids like: 12,44,73'),
        '#states' => [
          'visible' => [
            [':input[name="settings[custom_content_node][enable]"]' => ['checked' => TRUE]],
          ],
        ],
      ]
    ];

    // node_view_label
    $form['node_view_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Each Content Node View Label:'),
      '#default_value' => isset($config['node_view_label']) ? $config['node_view_label'] : 'Learn more',
      '#placeholder' => $this->t('Learn more'),
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'home_our_services']],
        ],
      ],
    ];

    // view all cta
    $view_all_cta = isset($config['view_all_cta']) ? $config['view_all_cta'] : [];
    $form['view_all_cta'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('View All CTA Buttons'),
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'sc_studio_our_work']],
        ],
      ],
    ];
    $form['view_all_cta']['cta'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),
        $this->t('URL')
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
    $form['view_all_cta']['cta'][0]['label'] = [
      '#type' => 'textfield',
      '#default_value' => isset($view_all_cta[0]['label']) ? $view_all_cta[0]['label'] : '',
      '#placeholder' => $this->t('view more'),

    ];
    $form['view_all_cta']['cta'][0]['url'] = [
      '#type' => 'textfield',
      '#default_value' => isset($view_all_cta[0]['url']) ? $view_all_cta[0]['url'] : '',
    ];

    // other_meta_info
    $other_meta_info = isset($config['other_meta_info']) ? $config['other_meta_info'] : [];
    $form['other_meta_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Other Data for the block'),
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'sc_studio_our_work']],
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
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Save our custom settings when the form is submitted.
    $block_layout = ($form_state->getValue('block_layout')) ?: '';
    $this->setConfigurationValue('block_layout', $block_layout);
    $this->setConfigurationValue('section_title', $form_state->getValue('section_title'));
    $this->setConfigurationValue('section_sub_title', $form_state->getValue('section_sub_title'));
    $this->setConfigurationValue('show_edit_btn', $form_state->getValue('show_edit_btn'));
    $this->setConfigurationValue('custom_content_node', $form_state->getValue('custom_content_node'));
    //
    if ($block_layout == 'home_our_services') {
      $this->setConfigurationValue('node_view_label', $form_state->getValue('node_view_label'));
    } else if ($block_layout == 'sc_studio_our_work') {
      $this->setConfigurationValue('other_meta_info', $form_state->getValue('other_meta_info'));
      $view_all_cta = $form_state->getValue('view_all_cta');
      $cta = isset($view_all_cta['cta']) ? $view_all_cta['cta'] : [];
      $view_all_cta_links = [];
      if (is_array($cta) || is_object($cta)) {
        foreach ($cta as $key => $value) {
          if (trim($value['label'])) {
            $view_all_cta_links[] = $value;
          }
        }
      }
      $this->configuration['view_all_cta'] = $view_all_cta_links;
    } else {
      $this->setConfigurationValue('node_view_label', '');
      $this->setConfigurationValue('view_all_cta', '');
      $this->setConfigurationValue('other_meta_info', '');
    }

    // $this->setConfigurationValue('selected_content_type', ($form_state->getValue('selected_content_type')) ?: 'services');


  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0; // Disables caching completely
  }

  /**
   * 
   */
}
