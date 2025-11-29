<?php

namespace Drupal\helperbox\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helperbox\Helper\MediaHelper;

/**
 * Plugin implementation of the 'helperbox url to text' formatter.
 *
 * @FieldFormatter(
 *   id = "helperbox url to text",
 *   label = @Translation("Helperbox URL to text"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   constraints = {
 *     "AllowedEntityTypes" = {"media"}
 *   }
 * )
 */
class URLTextEntityReferenceFormatter extends EntityReferenceFormatterBase {

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
            'showdownloadlink' => FALSE,
            'downloadlinklabel' => '',
        ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state) {
        $elements = [];


        $elements['showdownloadlink'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Show as download link URL'),
            '#default_value' => $this->getSetting('showdownloadlink'),
        ];

        $elements['downloadlinklabel'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Download link label'),
            '#default_value' => $this->getSetting('downloadlinklabel'),
            '#description' => $this->t('This text will be used as the link label, If empty file name will be used as label'),
            '#states' => [
                'visible' => [
                    ':input[name="options[settings][showdownloadlink]"]' => ['checked' => TRUE],
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

        $showdownloadlink =  $settings['showdownloadlink'] ?? false;
        $downloadlinklabel =  $settings['downloadlinklabel'] ?? '';
        // 
        $elements = [];
        foreach ($items as $delta => $item) {
            $item_id = $item->entity->id();
            $media = MediaHelper::get_media_library_info($item_id);
            if ($showdownloadlink) {
                $downloadlinklabel = $downloadlinklabel ? $downloadlinklabel : $media[0]['file_name'];
                $elements[$delta] = [
                    '#markup' => "<a href='" . $media[0]['file_path'] . "' download>$downloadlinklabel . $langcode</a>",
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
