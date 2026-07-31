<?php

namespace Drupal\helperbox\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helperbox\Helper\MediaHelper;
use Drupal\helperbox\Helper\UtilHelper;

/**
 * Plugin implementation of the 'Media Info' formatter.
 *
 * @FieldFormatter(
 *   id = "helperbox_fieldformat_mediainfo",
 *   label = @Translation("HelperBox - Media Info"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   constraints = {
 *     "AllowedEntityTypes" = {"media"}
 *   }
 * )
 */
class MediaInfoEntityReferenceFormatter extends EntityReferenceFormatterBase {

    /**
     * Restrict this formatter to media entity reference fields only.
     */
    public static function isApplicable(FieldDefinitionInterface $field_definition) {
        return $field_definition->getType() === 'entity_reference'
            && $field_definition->getSetting('target_type') === 'media';
    }


    /**
     * {@inheritdoc}
     */
    public static function defaultSettings() {
        return [
            'display_option' => 'text',
            'filesize_option' => 'default',
            'image_style' => '',
            'cta_settings' => [
                'cta_label' => '',
                'cta_target' => '_self',
            ]
        ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state) {
        $elements = [];

        // Display options
        $elements['display_option'] = [
            '#type' => 'radios',
            '#title' => $this->t('Display media info as:'),
            '#default_value' => $this->getSetting('display_option') ?: 'text',
            '#options' => [
                'text' => $this->t('Show URL as text'),
                'filesize' => $this->t('Show file size'),
                'filetype' => $this->t('Show file type'),
                'filemime' => $this->t('Show file mime'),
                'fileextension' => $this->t('Show file extension'),
                'cta' => $this->t('Show as CTA link'),
                'pdfviewer' => $this->t('Show as PDF viewer'),
                'filename' => $this->t('Show file name'),
                'video_embed_url' => $this->t('Show Remote Embed Video URL'),
            ],
            '#required' => true,
        ];

        // CTA settings
        $cta_settings = $this->getSetting('cta_settings');
        $elements['cta_settings'] = [
            '#type' => 'details',
            '#title' => $this->t('CTA Settings'),
            '#tree' => TRUE,
            '#open' => TRUE,
            '#states' => [
                'visible' => [
                    ':input[name="options[settings][display_option]"]' => [
                        'value' => 'cta',
                    ],
                ],
            ],
        ];

        $elements['cta_settings']['cta_label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('CTA Label'),
            '#default_value' => $cta_settings['cta_label'] ?? '',
            '#description' => $this->t('This text will be used as the link label. If empty, the file name will be used as the label.'),
        ];

        $elements['cta_settings']['cta_target'] = [
            '#type' => 'select',
            '#title' => $this->t('Target'),
            '#default_value' => $cta_settings['cta_target'] ?? '_self',
            '#options' => [
                '_self' => $this->t('Same window'),
                '_blank' => $this->t('New window'),
            ],
        ];

        $elements['cta_settings']['cta_download'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Force download'),
            '#default_value' => $cta_settings['cta_download'] ?? FALSE,
        ];

        $elements['cta_settings']['show_contextual_links'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Show contextual links'),
            '#default_value' => $cta_settings['show_contextual_links'] ?? FALSE,
        ];

        // Filesize option
        $elements['filesize_option'] = [
            '#type' => 'radios',
            '#title' => $this->t('File size options:'),
            '#default_value' => $this->getSetting('filesize_option') ?: 'default',
            '#options' => [
                'default'   => $this->t('Default'),
                'bytes'     => $this->t('Bytes'),
                'kb'        => $this->t('KB'),
                'mb'        => $this->t('MB'),
            ],
            '#states' => [
                'visible' => [
                    ':input[name="options[settings][display_option]"]' => ['value' => 'filesize'],
                ],
            ],
        ];

        // Add image style selection for format
        $image_styles = \Drupal::service('entity_type.manager')->getStorage('image_style')->loadMultiple();
        $style_options = ['' => $this->t('None (original image)')];
        foreach ($image_styles as $style) {
            $style_options[$style->id()] = $style->label();
        }

        $elements['image_style'] = [
            '#type' => 'select',
            '#title' => $this->t('image style'),
            '#default_value' => $this->getSetting('image_style') ?: '',
            '#options' => $style_options,
            '#description' => $this->t('Select an image style to apply to images in format.'),
            '#states' => [
                'visible' => [
                    ':input[name="options[settings][display_option]"]' => ['value' => 'text'],
                ],
            ],
        ];



        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode) {

        $elements = [];
        $settings = $this->getSettings();
        $entities = $this->getEntitiesToView($items, $langcode);

        // Early opt-out if the field is empty.
        if (empty($entities)) {
            return $elements;
        }

        $display_option =  $settings['display_option'] ?? false;
        $image_style =  $settings['image_style'] ?? '';

        // Handle other display options normally
        foreach ($items as $delta => $item) {
            $entity = $item->entity;
            if (empty($entity)) {
                continue;
            }
            $item_id = $entity->id();
            $media = MediaHelper::get_media_library_info($item_id, $image_style);
            if (!$media) {
                continue;
            }

            $render_template = '';
            $markup = '';

            switch ($display_option) {
                case 'filesize':
                    $filesizeOption = $settings['filesize_option'] ?? '';

                    switch ($filesizeOption) {
                        case 'bytes':
                            $markup = $media[0]['file_size'];
                            break;

                        case 'kb':
                            $markup = UtilHelper::bytesToSize($media[0]['file_size'], 'kb');
                            break;

                        case 'mb':
                            $markup = UtilHelper::bytesToSize($media[0]['file_size'], 'mb');
                            break;

                        case 'default':
                        default:
                            $markup = $media[0]['file_sizeunit'];
                            break;
                    }
                    break;

                case 'filetype':
                    $markup = $media[0]['media_type'];
                    break;

                case 'filemime':
                    $markup = $media[0]['file_mime'];
                    break;

                case 'fileextension':
                    $markup = $media[0]['file_extension'];
                    break;

                case 'cta':
                    $cta_info = [];
                    $cta_info['target_id'] = $entity->id();
                    $cta_info['entity_reference'] = $entity->getEntityTypeId();
                    $cta_info['entity_type'] = $entity->bundle();

                    $attributes = [];
                    $attributes['class'] = 'cta-wrapper link-btn';

                    $cta_settings = $settings['cta_settings'] ?? [];
                    // 
                    $source_field = $entity->getSource()->getConfiguration()['source_field'] ?? '';
                    $description = $entity->get($source_field)->description;

                    // cta label
                    $cta_Label = $cta_settings['cta_label'] ?? '';
                    $cta_Label = $cta_Label ?: $description;
                    $cta_Label = $cta_Label ?: $media[0]['file_name'];

                    // cta link
                    $cta_link = $media[0]['file_path'];

                    // cta_target
                    $cta_target = $cta_settings['cta_target'] ?? '';
                    if ($cta_target) {
                        $attributes['target'] = $cta_target;
                    }

                    // cta_download
                    $cta_download = $cta_settings['cta_download'] ?? '';
                    if ($cta_download) {
                        $attributes['download'] = 'download';
                    }


                    // contextual links
                    $show_contextual_links = $cta_settings['show_contextual_links'] ?? false;
                    if ($show_contextual_links) {
                        $links = [];
                        if ($entity->access('update')) {
                            $links[] = [
                                'title' => t('Edit'),
                                'url' => $entity->toUrl('edit-form', ['query' => ['destination' => \Drupal::service('redirect.destination')->get()]]),
                            ];
                        }
                        if ($entity->access('delete')) {
                            $links[] = [
                                'title' => t('Delete'),
                                'url' => $entity->toUrl('delete-form', ['query' => ['destination' => \Drupal::service('redirect.destination')->get()]])
                            ];
                        }
                        if (!empty($links)) {
                            $cta_info['contextual_links'] = [
                                '#theme' => 'links',
                                '#links' => $links,
                                '#attributes' => [
                                    'class' => ['media-contextual-links'],
                                ],
                            ];
                        }
                    }

                    $render_template = [
                        '#theme'       => 'helperbox_component_cta',
                        '#cta_url'     => $cta_link,
                        '#cta_label'   => $cta_Label,
                        '#cta_type'    => 'link',
                        '#cta_target'  => null,
                        '#is_external' => FALSE,
                        '#is_no_link'  => FALSE,
                        '#attributes'  => new \Drupal\Core\Template\Attribute($attributes),
                        '#wrapper_attributes'  => new \Drupal\Core\Template\Attribute([
                            'class' => 'media-cta'
                        ]),
                        '#cta_info' => $cta_info,
                    ];
                    break;

                case 'pdfviewer':
                    $render_template = [];
                    foreach ($media as $key => $file) {
                        if ($file['file_extension'] != 'pdf') {
                            continue;
                        }
                        $render_template[] = [
                            '#theme'       => 'helperbox_component_pdfviewer',
                            '#file_data'   => $file,
                            '#attributes'  => new \Drupal\Core\Template\Attribute(
                                [
                                    'id' => 'pdfviewer-media-id-' . $file['entity_id'],
                                    'media-id' => $file['entity_id']
                                ]
                            ),
                            '#attached' => [
                                'drupalSettings' => [
                                    'pdfviewer' => [$file['entity_id'] => $file['file_url']],
                                ],
                            ],
                        ];
                    }

                    break;
                case 'filename':
                    $markup = $media[0]['file_name'];
                    break;

                case 'video_embed_url':
                    $markup = $media[0]['remote_embed_video']['embed_url'] ?? '';
                    break;

                default:
                    $markup = $media[0]['file_path'];
                    break;
            }

            if ($markup) {
                $elements[$delta] = [
                    '#markup' => $markup,
                ];
            }
            if ($render_template) {
                $elements[$delta] = $render_template;
            }
        }

        return $elements;
    }
}
