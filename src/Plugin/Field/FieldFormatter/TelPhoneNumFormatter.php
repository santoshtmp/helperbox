<?php

namespace Drupal\helperbox\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'Text to phone number' formatter.
 */
#[FieldFormatter(
    id: 'helperbox_fieldformat_tel_phone_number',
    label: new TranslatableMarkup('HelperBox - Tel Phone Number'),
    field_types: [
        "string",
    ],
)]
class TelPhoneNumFormatter extends FormatterBase {
    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode) {

        $elements = [];

        foreach ($items as $delta => $item) {

            // Remove spaces & non-numeric chars except '+'.
            $sanitized = preg_replace('/[^0-9+]/', '', $item->value);

            $elements[$delta] = [
                '#type' => 'link',
                '#title' => $sanitized,
                '#url' => Url::fromUri('tel:' . $sanitized),
                '#attributes' => [
                    'class' => ['tel-link'],
                ],
            ];
        }

        return $elements;
    }
}
