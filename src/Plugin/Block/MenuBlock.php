<?php

/**
 * core\modules\system\src\Plugin\Block\SystemMenuBlock.php
 * https://www.drupal.org/docs/drupal-apis/menu-api/providing-module-defined-contextual-links
 * */

namespace Drupal\helperbox\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helperbox\Helper\MediaHelper;
use Drupal\helperbox\Helper\MenuHelper;

/**
 * Provides a generic Menu block.
 */
#[Block(
  id: "helperbox_menu_block",
  admin_label: new TranslatableMarkup("Helperbox Menu Block"),
  category: new TranslatableMarkup("Helperbox"),
)]
class MenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $menu_layout = isset($config['menu_layout']) ? $config['menu_layout'] : 'default';
    $menu_type = isset($config['menu_type']) ? $config['menu_type'] : 'menu';
    $menu_levels = isset($config['menu_levels']) ? (int)$config['menu_levels'] : 1;

    $background_image = isset($config['background_image']) ? $config['background_image'] : '';
    $background_image_style = isset($config['background_image_style']) ? $config['background_image_style'] : '';
    $background_image = ($background_image) ? MediaHelper::get_media_library_info($background_image, $background_image_style) : [];

    $build_context =  [
      '#theme' => 'helperbox_menu',  // This links to the template defined in hook_theme().
      '#menu_layout' => $menu_layout,
      '#menu_type' => $menu_type,
      '#menu_title' => MenuHelper::get_menu_title($menu_type),
      '#menu_items' => MenuHelper::get_menu_items($menu_type, $menu_levels),
      '#social_link_title' => isset($config['social_link_title']) ? $config['social_link_title'] : '',
      '#social_links' => '', //($menu_layout == 'default') ? '' : theme_get_setting('social_links'),
      '#background_image' => $background_image,
      '#contextual_links' => [
        // This should match the group in helperbox.links.contextual.yml
        'helperbox_block_menu' => [
          'route_parameters' => ['menu' => $menu_type],
          'query' => [],
        ],
      ],
    ];

    return $build_context;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'menu_layout' => '',
      'menu_title' => '',
      'menu_type' => '',
      'menu_items' => [],
      'background_image' => ''
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
    $menu_layout_selected = isset($config['menu_layout']) ? $config['menu_layout'] : 'default';
    $options = [
      'default' => "Default Layout",
      'mobile' => "Responsive Mobile Layout",
    ];
    $form['menu_layout'] = array(
      '#type' => 'select',
      '#title' => $this->t('Menu Layout Style:'),
      '#options' => $options,
      '#default_value' => $menu_layout_selected,
      '#required' => TRUE,
    );

    /**
     * menu_type
     */
    $menu_type_selected = isset($config['menu_type']) ? $config['menu_type'] : 'home_hero_banner';
    $options = array_merge(['' => 'Select Menu Type'], MenuHelper::get_all_menus());
    $form['menu_type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Menu Type:'),
      '#options' => $options,
      '#default_value' => $menu_type_selected,
      '#required' => TRUE,
    );

    /**
     * 
     */
    $options = range(0, 5);
    unset($options[0]);
    $options[0] = $this->t('Unlimited');
    $form['menu_levels'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of levels to display'),
      '#default_value' => isset($config['menu_levels']) ? $config['menu_levels'] : 1,
      '#options' => $options,
      '#description' => $this->t('This maximum number includes the initial level.'),
      '#required' => TRUE,
    ];

    // /**
    //  * background_image
    //  */
    // // $cardinality = -1;
    // $form['background_image'] = [
    //   '#type' => 'media_library',
    //   '#title' => $this->t('Background images'),
    //   '#allowed_bundles' => ['image'],
    //   '#cardinality' => '1',
    //   '#default_value' => isset($config['background_image']) ? $config['background_image'] : NULL,
    //   '#description' => $this->t('Select or upload an image.'),
    //   '#prefix' => '<div id="background-image-wrapper">',
    //   '#suffix' => '</div>',
    //   '#states' => [
    //     'visible' => [':input[name="settings[menu_layout]"]' => ['value' => 'expanded']],
    //   ],
    // ];

    // /**
    //  * background_image_style
    //  */
    // $styles_optionlist = \Drupal\image\Entity\ImageStyle::loadMultiple(); // $styles_optionlist = \Drupal::entityTypeManager()->getStorage('image_style')->loadMultiple();
    // $image_style_options = [];
    // $image_style_options[''] = "None (original)";
    // foreach ($styles_optionlist as $style) {
    //   $image_style_options[$style->id()] = $style->label();
    // }
    // $form['background_image_style'] = array(
    //   '#type' => 'select',
    //   '#title' => $this->t('Background Image Style:'),
    //   '#options' => $image_style_options,
    //   '#default_value' => isset($config['background_image_style']) ? $config['background_image_style'] : '',
    //   '#states' => [
    //     'visible' => [':input[name="settings[menu_layout]"]' => ['value' => 'expanded']],
    //   ],
    // );

    // /**
    //  * 
    //  */
    // $social_link_title = isset($config['social_link_title']) ? $config['social_link_title'] : '';
    // $form['social_link_title'] = array(
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Social Link Title'),
    //   '#default_value' => isset($social_link_title) ? $social_link_title : '',
    //   '#description' => $this->t('Social links are managed through theme settings <a href="/admin/appearance/settings/fimi#social-link-items-wrapper">social links</a>.'),
    //   '#states' => [
    //     'visible' => [':input[name="settings[menu_layout]"]' => ['value' => 'expanded']],
    //   ],
    // );

    // 
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('menu_layout', $form_state->getValue('menu_layout'));
    $this->setConfigurationValue('menu_type', $form_state->getValue('menu_type'));
    $this->setConfigurationValue('menu_levels', $form_state->getValue('menu_levels'));
    // $this->setConfigurationValue('social_link_title', $form_state->getValue('social_link_title'));
    // $this->setConfigurationValue('background_image', $form_state->getValue('background_image'));
    // $this->setConfigurationValue('background_image_style', $form_state->getValue('background_image_style'));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $config = $this->getConfiguration();
    $menu_name = isset($config['menu_type']) ? $config['menu_type'] : 'menu';
    $cache_tags[] = 'config:system.menu.' .  $menu_name;
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $config = $this->getConfiguration();
    $menu_name = isset($config['menu_type']) ? $config['menu_type'] : 'menu';
    return Cache::mergeContexts(parent::getCacheContexts(), ['route.menu_active_trails:' . $menu_name]);
  }

  // /**
  //  * {@inheritdoc}
  //  */
  // public function getCacheMaxAge() {
  //   return 0; // Disables caching completely
  // }


  /**
   * {@inheritdoc}
   */
  public function getContextualLinks() {
    $links = [];

    // Example: If the menu type is linked to a node edit form.
    $config = $this->getConfiguration();
    if (!empty($config['menu_type'])) {
      $menu_type = $config['menu_type'];

      // Generate the URL for editing content (modify as per your needs).
      $url = Url::fromRoute('entity.node.edit_form', ['node' => $menu_type]);

      $links['edit_content'] = [
        'title' => $this->t('Edit Content'),
        'url' => $url,
      ];
    }

    return $links;
  }

  /**
   * 
   */
}
