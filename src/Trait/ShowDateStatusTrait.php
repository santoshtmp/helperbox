<?php

namespace Drupal\helperbox\Trait;

trait ShowDateStatusTrait {

    /**
     * @param string $datetime_type
     * @param \Drupal\Core\Datetime\DrupalDateTime|\DateTimeInterface|\DateTime|null $start_date
     * @param \Drupal\Core\Datetime\DrupalDateTime|\DateTimeInterface|\DateTime|null $end_date
     * @param bool $start_date_displayed
     * @param bool $end_date_displayed
     */
    protected function checkDateStatus(
        string $datetime_type,
        $start_date,
        $end_date,
        bool $start_date_displayed,
        bool $end_date_displayed
    ): array {
        $now_dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $has_start = $start_date !== NULL;
        $has_end   = $end_date !== NULL;

        $status       = '';
        $status_class = '';

        if ($datetime_type === 'date') {
            // Date-only: compare as Ymd strings to avoid timezone shifts.
            $now_compare   = $now_dt->format('Ymd');
            $start_compare = $has_start ? $start_date->format('Ymd') : NULL;
            $end_compare   = $has_end   ? $end_date->format('Ymd')   : NULL;
        } else {
            // Datetime: compare full Unix timestamps.
            $now_compare   = $now_dt->getTimestamp();
            $start_compare = $has_start ? $start_date->getTimestamp() : NULL;
            $end_compare   = $has_end   ? $end_date->getTimestamp()   : NULL;
        }

        if ($start_date_displayed && $end_date_displayed) {
            // Full range comparison.
            if ($now_compare < $start_compare) {
                $status       = $this->t('Upcoming');
                $status_class = 'upcoming';
            } elseif ($now_compare > $end_compare) {
                $status       = $this->t('Past');
                $status_class = 'past';
            } else {
                $status       = $this->t('Ongoing');
                $status_class = 'ongoing';
            }
        } elseif ($start_date_displayed && $has_start) {
            // Start only.
            if ($now_compare < $start_compare) {
                $status       = $this->t('Upcoming');
                $status_class = 'upcoming';
            } elseif ($now_compare > $start_compare) {
                $status       = $this->t('Past');
                $status_class = 'past';
            } else {
                $status       = $this->t('Ongoing');
                $status_class = 'ongoing';
            }
        } elseif ($end_date_displayed && $has_end) {
            // End only.
            if ($now_compare < $end_compare) {
                $status       = $this->t('Upcoming');
                $status_class = 'upcoming';
            } elseif ($now_compare > $end_compare) {
                $status       = $this->t('Past');
                $status_class = 'past';
            } else {
                $status       = $this->t('Ongoing');
                $status_class = 'ongoing';
            }
        }

        return [
            '#type'       => 'html_tag',
            '#tag'        => 'span',
            '#attributes' => [
                'class' => [
                    'date__status',
                    'date__status--' . $status_class,
                ],
            ],
            '#value'      => $status,
        ];
    }


    /**
     * Determines the date status for an entity's date range field.
     *
     * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
     *   The entity containing the date range field.
     * @param string $field_name
     *   The machine name of the daterange field. Defaults to
     *   'field_date_range'.
     *
     * @return array|null
     *   A render array for the date status, or NULL if the field is
     *   missing or empty.
     */
    public function getFieldDateRangeStatus(\Drupal\Core\Entity\FieldableEntityInterface $entity, string $field_name = 'field_date_range'): ?array {
        // Check out early if the entity has no date range field or it's empty.
        if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
            return NULL;
        }

        // Get the first (and presumably only) value of the date range field.
        $item       = $entity->get($field_name)->first();
        $start_date = $item->start_date;
        $end_date   = $item->end_date;

        // Get datetime_type ('date' or 'datetime') from the field storage
        // settings, defaulting to 'datetime' if not explicitly set.
        $datetime_type = $entity->get($field_name)
            ->getFieldDefinition()
            ->getFieldStorageDefinition()
            ->getSetting('datetime_type') ?? 'datetime';

        // Delegate to the helper that calculates the actual status
        // (e.g. whether the range is upcoming, ongoing, or expired).
        return $this->checkDateStatus(
            $datetime_type,
            $start_date,
            $end_date,
            TRUE,
            TRUE,
        );
    }
}
