<?php

namespace Drupal\helperbox\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
// use Drupal\webform\Element\Webform;
use Drupal\webform\Entity\Webform;
use Drupal\helperbox\Helper\MediaHelper;

/**
 * Provides a 'Helperbox Contact' Block.
 *
 */
#[Block(
  id: "helperbox_contact_block",
  admin_label: new TranslatableMarkup("Helperbox Contact Block"),
  category: new TranslatableMarkup("Helperbox"),
)]
class ContactBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();

    // check defult_social_links
    $defult_social_links = isset($config['defult_social_links']) ? $config['defult_social_links'] : TRUE;
    if ($defult_social_links) {
      $social_links = theme_get_setting('social_links');
    } else {
      $social_links = isset($config['social_links']) ? $config['social_links'] : [];
    }
    // check defult_contact_info
    $defult_contact_info = isset($config['defult_contact_info']) ? $config['defult_contact_info'] : TRUE;
    if ($defult_contact_info) {
      $contact_address = theme_get_setting('contact_address');
      $contact_phone_number = theme_get_setting('contact_phone_number');
      $contact_email = theme_get_setting('contact_email');
    } else {
      $contact_info_fieldset = isset($config['contact_info_fieldset']) ? $config['contact_info_fieldset'] : '';
      $contact_address = isset($contact_info_fieldset['address']) ? $contact_info_fieldset['address'] : '';
      $contact_phone_number = isset($contact_info_fieldset['phone_number']) ? $contact_info_fieldset['phone_number'] : '';
      $contact_email = isset($contact_info_fieldset['email']) ? $contact_info_fieldset['email'] : '';
    }
    $feature_image = isset($config['feature_image']) ? $config['feature_image'] : '';
    $feature_image_style = isset($config['feature_image_style']) ? $config['feature_image_style'] : '';
    $feature_media = ($feature_image) ? MediaHelper::get_media_library_info($feature_image, $feature_image_style) : [];

    $selected_webform = isset($config['selected_webform']) ? $config['selected_webform'] : '';
    if ($selected_webform) {
      $webform = Webform::load($selected_webform);
      $webform_view = \Drupal::entityTypeManager()
        ->getViewBuilder('webform')
        ->view($webform);
    }
    return [
      '#theme' => 'helperbox_contact',
      '#block_layout' => isset($config['block_layout']) ? $config['block_layout'] : 'default_contact_layout',
      '#section_title' => isset($config['section_title']) ? $config['section_title'] : '',
      '#section_body' => isset($config['section_body']['value']) ? $config['section_body']['value'] : '',
      '#address' => $contact_address,
      '#phone_number' => $contact_phone_number,
      '#email' => $contact_email,
      '#social_links' => $social_links,
      '#feature_media' =>  $feature_media,
      '#media_type' => '',
      '#selected_webform' => ($selected_webform) ? $webform_view : '',
      '#find_us_title' => isset($config['find_us_title']) ? $config['find_us_title'] : 'Find Us',
      '#contact_us_title' => isset($config['contact_us_title']) ? $config['contact_us_title'] : 'Contact Us'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'block_layout' => '',
      'section_title' => '',
      'email' => '',
      'phone_number' => '',
      'address' => '',
      'social_links' => [],
      'section_body' => '',
      'feature_media' => [],
      'media_type' => '',
      'selected_webform' => '',
      'find_us_title' => '',
      'contact_us_title' => '',
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
    $block_layout_selected = isset($config['block_layout']) ? $config['block_layout'] : 'default';
    $options = [
      'default' => "Default",
      'short_contact_us' => "Short Contact Us",
      'social_link_only' => "Social Link Only",
    ];
    $form['block_layout'] = array(
      '#type' => 'select',
      '#title' => $this->t('Block Layout Style:'),
      '#options' => $options,
      '#default_value' => $block_layout_selected,
    );

    /**
     * 
     */
    $form['defult_contact_info'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Apply Default Contact Information'),
      '#description' => $this->t('Check this box to apply <a href="/admin/appearance/settings/fimi#contact-info-wrapper">default contact information</a> automatically. Uncheck to customize contact information manually.'),
      '#default_value' => isset($config['defult_contact_info']) ? $config['defult_contact_info'] : 1,
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'default'],
          ],
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'short_contact_us'],
          ],
        ],
      ],
    ];
    /**
     * defult_social_links
     */
    $form['defult_social_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Apply Default Social Links'),
      '#description' => $this->t('Check this box to apply <a href="/admin/appearance/settings/fimi#social-link-items-wrapper">default social links</a> automatically. Uncheck to customize social links manually.'),
      '#default_value' => isset($config['defult_social_links']) ? $config['defult_social_links'] : 1,
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'social_link_only'],
          ],
        ],
      ],
    ];

    /**
     * section_title
     */
    $form['section_title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Block Section Title:'),
      '#default_value' => isset($config['section_title']) ? $config['section_title'] : '',
      '#states' => [
        'invisible' => [
          ':input[name="settings[block_layout]"]' => ['value' => 'social_link_only'],
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
      '#states' => [
        'invisible' =>
        [
          [':input[name="settings[block_layout]"]' => ['value' => 'social_link_only']],
          [':input[name="settings[block_layout]"]' => ['value' => 'short_contact_us']],
        ]
      ],
    ];

    /**
     * contact_info wrapper
     */
    $form['contact_info_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact Information'),
      '#prefix' => '<div id="items-social-fieldset-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'default'],
            ':input[name="settings[defult_contact_info]"]' => ['checked' => FALSE],
          ],
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'short_contact_us'],
            ':input[name="settings[defult_contact_info]"]' => ['checked' => FALSE],
          ],
        ],
      ],
    ];
    $contact_info_fieldset = isset($config['contact_info_fieldset']) ? $config['contact_info_fieldset'] : '';
    $contact_address = isset($contact_info_fieldset['address']) ? $contact_info_fieldset['address'] : '';
    $contact_phone_number = isset($contact_info_fieldset['phone_number']) ? $contact_info_fieldset['phone_number'] : '';
    $contact_email = isset($contact_info_fieldset['email']) ? $contact_info_fieldset['email'] : '';

    /**
     * address
     */
    $form['contact_info_fieldset']['address'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Contact Address'),
      '#default_value' => isset($contact_address) ? $contact_address : '',
      '#states' => [
        'invisible' => [
          ':input[name="settings[block_layout]"]' => ['value' => 'social_link_only'],
        ],
      ],
    );

    /**
     * phone_number
     */
    $form['contact_info_fieldset']['phone_number'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Contact Phone number'),
      '#default_value' => isset($contact_phone_number) ? $contact_phone_number : '',
      '#states' => [
        'invisible' => [
          ':input[name="settings[block_layout]"]' => ['value' => 'social_link_only'],
        ],
      ],
    );

    /**
     * email
     */
    $form['contact_info_fieldset']['email'] = array(
      '#type' => 'email',
      '#title' => $this->t('Contact Email:'),
      '#default_value' => isset($contact_email) ? $contact_email : '',
      '#states' => [
        'invisible' => [
          ':input[name="settings[block_layout]"]' => ['value' => 'social_link_only'],
        ],
      ],
    );

    /**
     * Social Link field
     */
    $social_links = isset($config['social_links']) ? $config['social_links'] : [];
    if (!$form_state->has('num_items')) {
      $form_state->set('num_items', count($social_links));
    }
    $num_items = $form_state->get('num_items');
    // 
    if (!$form_state->has('removed_fields')) {
      $form_state->set('removed_fields', []);
    }
    $removed_fields = $form_state->get('removed_fields');
    // 
    $form['social_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact Social Links'),
      '#prefix' => '<div id="items-social-fieldset-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'default'],
            ':input[name="settings[defult_social_links]"]' => ['checked' => FALSE],
          ],
          [
            ':input[name="settings[block_layout]"]' => ['value' => 'social_link_only'],
            ':input[name="settings[defult_social_links]"]' => ['checked' => FALSE],
          ],
        ],
      ],
    ];

    $form['social_fieldset']['social'] = [
      '#type' => 'table',
      '#header' => [
        $this->t(''),
        $this->t('Socail Link Type'),
        $this->t('URL'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No Social Links available.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'social-weight',
        ],
      ],
    ];
    for ($i = 0; $i < $num_items; $i++) {
      if (in_array('social-' . $i, $removed_fields)) {
        continue;
      }
      $social_links = array_values($social_links);

      $form['social_fieldset']['social'][$i]['#attributes']['class'][] = 'draggable';

      $form['social_fieldset']['social'][$i]['dragdrop']['#attributes']['class'][] = 'draggable';

      $form['social_fieldset']['social'][$i]['social_type'] = [
        '#type' => 'select',
        '#title' => $this->t(''),
        '#options' => [
          '' => 'Select Social Link Type',
          'facebook' => 'Facebook',
          'linkedin' => 'Linkedin',
          'twitter_x' => 'Twitter/X',
          'vimeo' => "Vimeo",
        ],
        '#default_value' => isset($social_links[$i]['social_type']) ? $social_links[$i]['social_type'] : '',
      ];


      $form['social_fieldset']['social'][$i]['url'] = [
        '#type' => 'textfield',
        '#default_value' => isset($social_links[$i]['url']) ? $social_links[$i]['url'] : '',
      ];

      $form['social_fieldset']['social'][$i]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => isset($social_links[$i]['weight']) ? $social_links[$i]['weight'] : $i,
        '#attributes' => ['class' => ['social-weight']],
      ];

      $form['social_fieldset']['social'][$i]['action']['remove_social'] = [
        '#type' => 'submit',
        '#name' => 'social-' . $i,
        '#value' => $this->t('Remove'),
        '#submit' => [[$this, 'removeOne']],
        '#ajax' => [
          'callback' => [$this, 'addmoreCallback'],
          'wrapper' => 'items-social-fieldset-wrapper',
        ],
      ];
    }
    $form['social_fieldset']['actions'] = [
      '#type' => 'actions',
    ];
    $form['social_fieldset']['actions']['add_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Social Link'),
      '#submit' => [[$this, 'addOne']],
      '#ajax' => [
        'callback' => [$this, 'addmoreCallback'],
        'wrapper' => 'items-social-fieldset-wrapper',
      ],
    ];



    /**
     * Select webform
     * Load all Webforms
     */
    $webforms = Webform::loadMultiple();
    $options = ['' => 'Select webform'];
    foreach ($webforms as $id => $webform) {
      $options[$id] = $webform->label();
    }
    $form['selected_webform'] = [
      '#type' => 'select',
      '#title' => $this->t('Select a Webform'),
      '#options' => $options,
      '#default_value' => isset($config['selected_webform']) ? $config['selected_webform'] : '',
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'default']],
        ],
      ],
    ];


    /**
     * feature_image
     */
    $form['feature_image'] = [
      '#type' => 'media_library',
      '#title' => $this->t('Feature images'),
      '#allowed_bundles' => ['image'],
      '#cardinality' => 1,
      '#default_value' => isset($config['feature_image']) ? $config['feature_image'] : NULL,
      '#description' => $this->t('Select or upload an image.'),
      '#prefix' => '<div id="feature-image-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          [':input[name="settings[block_layout]"]' => ['value' => 'default']],
        ],
      ],
    ];


    /**
     * find_us_title
     */
    $form['find_us_title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Find Us / Address Title:'),
      '#default_value' => isset($config['find_us_title']) ? $config['find_us_title'] : 'Find Us',
      '#attributes' => [
        'placeholder' => $this->t('Enter address title ... Find Us'),
      ],
      '#states' => [
        'invisible' => [
          ':input[name="settings[block_layout]"]' => ['value' => 'social_link_only'],
        ],
      ],
    );

    /**
     * contact_us_title
     */
    $form['contact_us_title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Contact Us Title:'),
      '#default_value' => isset($config['contact_us_title']) ? $config['contact_us_title'] : 'Contact Us',
      '#attributes' => [
        'placeholder' => $this->t('Enter Contact title ... Contact Us'),
      ],
      '#states' => [
        'invisible' => [
          ':input[name="settings[block_layout]"]' => ['value' => 'social_link_only'],
        ],
      ],
    );

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
    return $form['settings']['social_fieldset'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('block_layout', $form_state->getValue('block_layout'));
    $this->setConfigurationValue('section_title', $form_state->getValue('section_title'));
    $this->setConfigurationValue('section_body', $form_state->getValue('section_body'));
    $this->setConfigurationValue('defult_social_links', $form_state->getValue('defult_social_links'));
    $this->setConfigurationValue('defult_contact_info', $form_state->getValue('defult_contact_info'));
    $this->setConfigurationValue('contact_info_fieldset', $form_state->getValue('contact_info_fieldset'));
    $this->setConfigurationValue('selected_webform', $form_state->getValue('selected_webform'));
    $this->setConfigurationValue('feature_image', $form_state->getValue('feature_image'));
    $this->setConfigurationValue('find_us_title', $form_state->getValue('find_us_title'));
    $this->setConfigurationValue('contact_us_title', $form_state->getValue('contact_us_title'));

    $social_fieldset = $form_state->getValue('social_fieldset');
    $social = isset($social_fieldset['social']) ? $social_fieldset['social'] : [];
    $social_links = [];
    if (is_array($social) || is_object($social)) {
      foreach ($social as $key => $value) {
        if (trim($value['social_type'])) {
          $social_links[] = $value;
        }
      }
    }

    $this->configuration['social_links'] = $social_links;
  }

  /**
   * 
   */
}
