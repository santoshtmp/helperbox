<?php

namespace Drupal\helperbox\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Plugin implementation of the 'helperbox_date_time_widget' widget.
 *
 */
#[FieldWidget(
  id: 'helperbox_date_time_widget',
  label: new TranslatableMarkup('Helperbox - Date Time Widget'),
  field_types: ['datetime'],
)]
// DateTimeHelperboxWidget
class DateTimeHelperboxWidget extends DateTimeWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'increment' => '1',
      'date_order' => 'YMD',
      'time_type' => '12',
      'time_optional' => FALSE,
      'value_only_time' => FALSE,
      'start_year_range' => '10',
      'end_year_range' => '10',
      'custom_start_year_range' => 2020,
      'custom_end_year_range' => 2050,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // Wrap all of the select elements with a fieldset.
    $element['#theme_wrappers'][] = 'fieldset';
    //
    $date_order = $this->getSetting('date_order');
    if ($this->getFieldSetting('datetime_type') == 'datetime') {
      $time_type = $this->getSetting('time_type');
      $increment = $this->getSetting('increment');
      $value_only_time = $this->getSetting('value_only_time');
    } else {
      $time_type = '';
      $increment = '';
      $value_only_time = '';
    }
    // 
    // Set up the date part order array.
    $date_part_order = match ($date_order) {
      'YMD' => ['year', 'month', 'day'],
      'MDY' => ['month', 'day', 'year'],
      'DMY' => ['day', 'month', 'year'],
      'YM' => ['year', 'month'],
      'MY' => ['month', 'year'],
      'Y' => ['year'],
      'M' => ['month'],
      'YMopt' => ['year', 'month'],
    };
    if ($value_only_time) {
      $date_part_order = [];
    }
    $date_part_order = match ($time_type) {
      '24' => array_merge($date_part_order, ['hour', 'minute']),
      '12' => array_merge($date_part_order, ['hour', 'minute', 'ampm']),
      default => $date_part_order,
    };


    // Build the datelist element.
    $element['value'] = [
      '#type' => 'datelist',
      '#date_increment' => $increment,
      '#date_part_order' => $date_part_order,
      '#date_year_range' => $this->getdateyearrange(),
      '#required' => FALSE,
    ] + $element['value'];

    // add extra js liberary
    $element['#attached']['library'][] = 'helperbox/helperbox_datetimewidget';
    $element['#attributes']['class'][] = 'fieldset-helperbox-date-time-widget';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['date_order'] = [
      '#type' => 'select',
      '#title' => $this->t('Date part order'),
      '#default_value' => $this->getSetting('date_order'),
      '#options' => [
        'MDY' => $this->t('Month/Day/Year'),
        'DMY' => $this->t('Day/Month/Year'),
        'YMD' => $this->t('Year/Month/Day'),
        'YM' => $this->t('Year/Month'),
        'MY' => $this->t('Month/Year'),
        'Y' => $this->t('Year'),
        'M' => $this->t('Month'),
        'YMopt' => $this->t('Year/Month (optional)'),
      ],
    ];


    $element['start_year_range'] = [
      '#type' => 'select',
      '#title' => $this->t('Start year range'),
      '#default_value' => $this->getSetting('start_year_range'),
      '#options' => [
        'default' => $this->t('Default (1900)'),
        '10' => $this->t('10 years in the past'),
        '20' => $this->t('20 years in the past'),
        '30' => $this->t('30 years in the past'),
        '40' => $this->t('40 years in the past'),
        '50' => $this->t('50 years in the past'),
        '60' => $this->t('60 years in the past'),
        '70' => $this->t('70 years in the past'),
        '80' => $this->t('80 years in the past'),
        '90' => $this->t('90 years in the past'),
        '100' => $this->t('100 years in the past'),
        'custom' => $this->t('Enter custom value'),
      ],
    ];

    $element['custom_start_year_range'] = [
      '#type' => 'number',
      '#title' => $this->t('Enter custom start year range'),
      '#default_value' => $this->getSetting('custom_start_year_range'),
      '#min' => 1,
      '#step' => 1,
      '#states' => [
        'visible' => [
          'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][start_year_range]"]' => ['value' => 'custom'],
        ],
      ],
    ];


    $element['end_year_range'] = [
      '#type' => 'select',
      '#title' => $this->t('End year range'),
      '#default_value' => $this->getSetting('end_year_range'),
      '#options' => [
        'current_year' => $this->t('Current year'),
        '10' => $this->t('10 years in the future'),
        '20' => $this->t('20 years in the future'),
        '30' => $this->t('30 years in the future'),
        '40' => $this->t('40 years in the future'),
        '50' => $this->t('50 years in the future'),
        '60' => $this->t('60 years in the future'),
        '70' => $this->t('70 years in the future'),
        '80' => $this->t('80 years in the future'),
        '90' => $this->t('90 years in the future'),
        '100' => $this->t('100 years in the future'),
        'custom' => $this->t('Enter custom value'),
      ],
    ];

    $element['custom_end_year_range'] = [
      '#type' => 'number',
      '#title' => $this->t('Enter custom end year range'),
      '#default_value' => $this->getSetting('custom_end_year_range'),
      '#min' => 1,
      '#step' => 1,
      '#states' => [
        'visible' => [
          'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][end_year_range]"]' => ['value' => 'custom'],
        ],
      ],
    ];


    if ($this->getFieldSetting('datetime_type') == 'datetime') {
      $element['time_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Time type'),
        '#default_value' => $this->getSetting('time_type'),
        '#options' => [
          'none' => $this->t('- No time -'),
          '24' => $this->t('24 hour time'),
          '12' => $this->t('12 hour time')
        ],
      ];

      $element['increment'] = [
        '#type' => 'select',
        '#title' => $this->t('Time increments'),
        '#default_value' => $this->getSetting('increment'),
        '#options' => [
          1 => $this->t('1 minute'),
          5 => $this->t('@count minutes', ['@count' => 5]),
          10 => $this->t('@count minutes', ['@count' => 10]),
          15 => $this->t('@count minutes', ['@count' => 15]),
          30 => $this->t('@count minutes', ['@count' => 30]),
        ],
        '#states' => [
          'disabled' => [
            ':input[name="settings[time_type]"]' => ['value' => 'none'],
          ],
          'invisible' => [
            ':input[name="settings[time_type]"]' => ['value' => 'none'],
          ],
        ],
      ];

      // $element['time_optional'] = [
      //   '#type' => 'checkbox',
      //   '#title' => $this->t('Make time optional'),
      //   '#default_value' => $this->getSetting('time_optional'),
      //   '#description' => $this->t(''),
      // ];

      // $element['value_only_time'] = [
      //   '#type' => 'checkbox',
      //   '#title' => $this->t('Disable date selection and show time only'),
      //   '#default_value' => $this->getSetting('value_only_time'),
      //   '#description' => $this->t(''),
      // ];

    } else {
      $element['time_type'] = [
        '#type' => 'hidden',
        '#value' => 'none',
      ];

      $element['increment'] = [
        '#type' => 'hidden',
        '#value' => $this->getSetting('increment'),
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Date part order: @order', ['@order' => $this->getSetting('date_order')]);
    $summary[] = $this->t('Date year range: @order', ['@order' => $this->getdateyearrange()]);
    if ($this->getFieldSetting('datetime_type') == 'datetime') {
      $summary[] = $this->t('Time type: @time_type', ['@time_type' => $this->getSetting('time_type')]);
      $summary[] = $this->t('Time increments: @increment', ['@increment' => $this->getSetting('increment')]);
      // $summary[] = $this->t('Make time optional: @increment', ['@increment' => $this->getSetting('time_optional') ? 'True' : 'False']);
      // $summary[] = $this->t('Make Date disable and show time only: @increment', ['@increment' => $this->getSetting('value_only_time') ? 'True' : 'False']);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    $datetime_type = $this->getFieldSetting('datetime_type');
    if ($datetime_type === DateTimeItem::DATETIME_TYPE_DATE) {
      $storage_format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
    } else {
      $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    }
    // 
    $time_optional = $this->getSetting('time_optional');
    $date_order = $this->getSetting('date_order');
    if ($date_order === 'YMopt') {
      foreach ($values as &$value) {
        $date = $value['value'] ?? NULL;

        // If already a DrupalDateTime object.
        if ($date instanceof DrupalDateTime) {
          $value['value'] = $date->format($storage_format);
          $month_not_selected = 0;
        } else {
          $month = isset($date['month']) ? (int) $date['month'] : 0;
          $year = isset($date['year']) ? (int) $date['year'] : 0;

          if ($date_order === 'YMopt' && empty($month)) {
            $form_state->clearErrors();
            $month_not_selected = 1;
            $month = 1; // Default for date creation
          } else {
            $month_not_selected = 0;
          }
          // 
          $month = $month < 1 ? 1 : ($month > 12 ? 12 : $month);
          $year = isset($date['year']) ? (int) $date['year'] : NULL;

          if ($year && $month) {
            try {
              $dt = new DrupalDateTime();
              $dt->setDate($year, $month, 1)->setTime(0, 0);
              $value['value'] = $dt->format($storage_format);  // MUST be a string
            } catch (\Exception $e) {
              // Invalid date fallback
              $value['value'] = NULL;
            }
          } else {
            $value['value'] = NULL;
          }
        }
        $value['month_not_selected'] = $month_not_selected;
      }
    }

    $values = parent::massageFormValues($values, $form, $form_state);

    return $values;
  }

  // get date year range
  function getdateyearrange() {
    $current_year = (int) date('Y');
    $start_year_range = $this->getSetting('start_year_range');
    $end_year_range = $this->getSetting('end_year_range');
    // 
    $start_year = 0;
    $end_year = 0;
    if ($start_year_range === 'custom') {
      $start_year = (int) $this->getSetting('custom_start_year_range');
    } elseif ($start_year_range == 'default') {
      $start_year = 1900;
    } else {
      $start_year = $current_year - (int)$start_year_range;
    }

    if ($end_year_range === 'custom') {
      $end_year = (int) $this->getSetting('custom_end_year_range');
    } elseif ($end_year_range === 'current_year') {
      $end_year = $current_year;
    } else {
      $end_year = $current_year + (int)$end_year_range;
    }

    $year_range = $start_year . ':' . $end_year;

    return $year_range;
  }

  // ==== END OF CODE ====
}
