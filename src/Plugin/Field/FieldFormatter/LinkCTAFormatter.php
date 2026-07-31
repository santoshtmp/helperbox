<?php

namespace Drupal\helperbox\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Template\Attribute;
use Drupal\helperbox\Trait\FieldCTATrait;

/**
 * Plugin implementation of the 'helperbox_fieldformat_cta_link' formatter.
 */
#[FieldFormatter(
    id: 'helperbox_fieldformat_cta_link',
    label: new TranslatableMarkup('HelperBox - CTA Link'),
    field_types: ['link'],
)]
class LinkCTAFormatter extends FormatterBase {

    use FieldCTATrait;

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings() {
        return [
            'cta_type' => 'primary',
        ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state) {
        $elements = parent::settingsForm($form, $form_state);

        $elements['cta_type'] = [
            '#type'          => 'select',
            '#title'         => $this->t('CTA Type'),
            '#default_value' => $this->getSetting('cta_type'),
            '#options'       => $this->ctaTypeOptions(),
            '#description'   => $this->t('Select the button style.'),
        ];

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function settingsSummary() {
        $summary = parent::settingsSummary();

        $summary[] = $this->t('CTA Type: @type', [
            '@type' => ucfirst($this->getSetting('cta_type')),
        ]);

        return $summary;
    }

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode) {
        $elements = [];
        $cta_type = $this->getSetting('cta_type');

        foreach ($items as $delta => $item) {
            if ($item->isEmpty()) {
                continue;
            }

            // Guard against malformed or unsupported URIs.
            try {
                $url = Url::fromUri($item->uri, $item->options ?? []);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $options     = $item->options ?? [];
            $target      = $options['attributes']['target'] ?? NULL;
            $is_external = $url->isExternal();

            // Default external links to opening in a new tab when no target is set.
            if (!$target && $is_external) {
                $target = '_blank';
            }
            $attributes = [];
            $attributes['class'] = 'cta-wrapper ' . $cta_type . '-btn';

            $elements[$delta] = [
                '#theme'       => 'helperbox_component_cta',
                '#cta_url'     => $url->toString(),
                '#cta_label'   => $item->title ?? $url->toString(),
                '#cta_type'    => $cta_type,
                '#cta_target'  => $target,
                '#is_external' => $is_external,
                '#is_no_link'  => FALSE,
                '#attributes'  => new \Drupal\Core\Template\Attribute($attributes),
                '#wrapper_attributes'  => new \Drupal\Core\Template\Attribute([]),
                '#cta_info' => [],
            ];
        }

        return $elements;
    }
}
