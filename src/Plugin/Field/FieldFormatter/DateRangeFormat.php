<?php

namespace Drupal\helperbox\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime_range\DateTimeRangeDisplayOptions;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangeCustomFormatter;

/**
 * Plugin implementation of the 'Custom' formatter for 'daterange' fields.
 *
 * This formatter renders the data range as plain text, with a fully
 * configurable date format using the PHP date syntax and separator.
 */
#[FieldFormatter(
    id: 'helperbox_fieldformat_daterange',
    label: new TranslatableMarkup('HelperBox - Date Range'),
    field_types: [
        'daterange',
    ],
)]
class DateRangeFormat extends DateRangeCustomFormatter {

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings() {
        $make = [
            'helperbox_formatter' => false,
        ];
        return $make + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode) {
        // @todo Evaluate removing this method in
        // https://www.drupal.org/node/2793143 to determine if the behavior and
        // markup in the base class implementation can be used instead.
        $elements = [];
        $separator = $this->getSetting('separator');
        // $helperbox_formatter = $this->getSetting('helperbox_formatter');

        foreach ($items as $delta => $item) {
            if (!empty($item->start_date) && !empty($item->end_date)) {
                /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
                $start_date = $item->start_date;
                /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
                $end_date = $item->end_date;

                if ($start_date->getTimestamp() !== $end_date->getTimestamp()) {
                    // $elements[$delta] = $this->renderStartEnd($start_date, $separator, $end_date);

                    $element = [];
                    if ($this->startDateIsDisplayed()) {
                        $element[DateTimeRangeDisplayOptions::StartDate->value] = $this->buildDate($start_date);
                    }
                    if ($this->startDateIsDisplayed() && $this->endDateIsDisplayed()) {
                        $element['separator'] = ['#plain_text' => ' ' . $separator . ' '];
                    }
                    if ($this->endDateIsDisplayed()) {
                        $element[DateTimeRangeDisplayOptions::EndDate->value] = $this->buildDate($end_date);
                    }

                    $elements[$delta] = $element;
                } else {
                    $elements[$delta] = $this->buildDate($start_date);
                }
            }
        }

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state) {
        $form = parent::settingsForm($form, $form_state);

        // $form['helperbox_formatter'] = [
        //     '#type' => 'checkbox',
        //     '#title' => $this->t('Helperbox Formatter'),
        //     '#default_value' => $this->getSetting('helperbox_formatter'),
        //     '#description' => $this->t('Format as "1 Feb – 21 Apr, 2022" or "1 Feb – 21 Apr, 2022 12:30 PM" or "1 Feb – 21 Apr, 2022 12:30 PM - 02:10 PM"'),
        // ];

        return $form;
    }
}
