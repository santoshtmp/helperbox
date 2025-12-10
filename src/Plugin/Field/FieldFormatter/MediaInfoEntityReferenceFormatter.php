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
            'downloadlinklabel' => '',
        ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state) {
        $elements = [];


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
                'download' => $this->t('Show as download link URL'),
                'filename' => $this->t('Show file name'),
            ],
            '#required' => true,
        ];

        $elements['downloadlinklabel'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Download link label'),
            '#default_value' => $this->getSetting('downloadlinklabel'),
            '#description' => $this->t('This text will be used as the link label. If empty, the file name will be used as the label.'),
            '#states' => [
                'visible' => [
                    ':input[name="options[settings][display_option]"]' => ['value' => 'download'],
                ],
            ],
        ];

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



        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode) {

        $elements = [];
        $settings = $this->getSettings();
        $entities = $this->getEntitiesToView($items, $langcode);
        $target_type = $this->getFieldSetting('target_type');

        // Early opt-out if the field is empty.
        if (empty($entities)) {
            return $elements;
        }

        $display_option =  $settings['display_option'] ?? false;
        // 
        foreach ($items as $delta => $item) {
            $item_id = $item->entity->id();
            $media = MediaHelper::get_media_library_info($item_id);
            if ($display_option == 'download') {
                $elements[$delta] = [
                    '#markup' => $media[0]['file_path'],
                ];
            } else if ($display_option == 'filesize') {
                $filesize_option =  $settings['filesize_option'] ?? '';
                if ($filesize_option == 'default') {
                    $filesize =  $media[0]['file_sizeunit'];
                } else  if ($filesize_option == 'bytes') {
                    $filesize =  $media[0]['file_size'];
                } else  if ($filesize_option == 'kb') {
                    $filesize = UtilHelper::bytesToSize($media[0]['file_size'], 'kb');
                } else  if ($filesize_option == 'mb') {
                    $filesize = UtilHelper::bytesToSize($media[0]['file_size'], 'mb');
                }
                $elements[$delta] = [
                    '#markup' => $filesize,
                ];
            } else if ($display_option == 'filetype') {
                $elements[$delta] = [
                    '#markup' => $media[0]['media_type'],
                ];
            } else if ($display_option == 'filemime') {
                $elements[$delta] = [
                    '#markup' => $media[0]['file_mime'],
                ];
            } else if ($display_option == 'fileextension') {
                $elements[$delta] = [
                    '#markup' => $media[0]['file_extension'],
                ];
            } else if ($display_option == 'download') {
                $downloadlinklabel =  $settings['downloadlinklabel'] ?? '';
                $downloadlinklabel = $downloadlinklabel ? $downloadlinklabel : $media[0]['file_name'];
                $elements[$delta] = [
                    '#markup' => "<a href='" . $media[0]['file_path'] . "' download>$downloadlinklabel . $langcode</a>",
                ];
            } else if ($display_option == 'filename') {
                $elements[$delta] = [
                    '#markup' => $media[0]['file_name'],
                ];
            } else {
                $elements[$delta] = [
                    '#markup' => $media[0]['file_path'],
                ];
            }
        }
        return $elements;
    }
}
