<?php

namespace Drupal\helperbox\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Plugin implementation of the 'helperbox_date_month_widget' widget.
 *
 * @FieldWidget(
 *   id = "helperbox_date_month_widget",
 *   module = "helperbox",
 *   label = @Translation("Helperbox - Date month widget"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class DateMonthWidget extends DateTimeWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'date_order' => 'YD',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#theme_wrappers'][] = 'fieldset';

    //
    $date_order = $this->getSetting('date_order');
    switch ($date_order) {
      default:
      case 'YMD':
        $date_part_order = ['year', 'month', 'day'];
        break;
      case 'YM':
        $date_part_order = ['year', 'month'];
        break;
      case 'MY':
        $date_part_order = ['month', 'year'];
        break;
      case 'Y':
        $date_part_order = ['year'];
        break;
      case 'M':
        $date_part_order = ['month'];
        break;
      case 'YMopt':
        $date_part_order = ['year', 'month'];
        break;
    }

    // Extract date
    // $default_value = $items[$delta]->getValue();
    // $month_not_selected = isset($default_value['month_not_selected']) ? $default_value['month_not_selected'] : FALSE;

    $element['value'] = [
      '#type' => 'datelist',
      '#date_part_order' => $date_part_order,
      '#required' => FALSE,  // Make month optional
    ] + $element['value'];


    // if ($date_order === 'YMopt') {
    //   $month_not_selected = isset($items[$delta]->month_not_selected) ? $items[$delta]->month_not_selected : 0;
    //   $element['month_not_selected'] = [
    //     '#type' => 'checkbox',
    //     '#title' => $this->t('Month not selected (debug)'),
    //     '#default_value' => $month_not_selected,
    //   ];
    // }

    // add extra js liberary
    $element['#attached']['library'][] = 'helperbox/date_month_widget';
    $element['#attributes']['class'][] = 'fieldset-helperbox-date-month-widget';
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
        'YMD' => $this->t('Year/Month/Day'),
        'YM' => $this->t('Year/Month'),
        'MY' => $this->t('Month/Year'),
        'Y' => $this->t('Year'),
        'M' => $this->t('Month'),
        'YMopt' => $this->t('Year/Month (optional)'),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Date part order: @order', ['@order' => $this->getSetting('date_order')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $date_order = $this->getSetting('date_order');
    foreach ($values as &$value) {
      $date = $value['value'] ?? NULL;

      // If already a DrupalDateTime object.
      if ($date instanceof DrupalDateTime) {
        $value['value'] = $date->format('Y-m-d');
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
            $value['value'] = $dt->format('Y-m-d');  // MUST be a string
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
    // var_dump($values); die;
    return $values;
  }



  // ==== END OF CODE ====
}
