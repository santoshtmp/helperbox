<?php

/**
 * @file
 * Entity hooks for Helperbox module.
 */

use Drupal\helperbox\Helper\HelperboxSettings;
use Drupal\helperbox\Helper\MediaHelper;

/**
 * Update the media thumbnail if field "field_custom_thumbnail" is present and not empty
 * Implements hook_entity_presave().
 * https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21entity.api.php/function/hook_entity_presave/11.x
 */
function helperbox_entity_presave(\Drupal\Core\Entity\EntityInterface $entity) {
    // Only process actual content entities (media, node, etc.)
    if (!$entity instanceof \Drupal\Core\Entity\ContentEntityInterface) {
        return;
    }
    //  check for media entity
    if ($entity->getEntityTypeId() == 'media') {
        // check setting
        $enable_media_custom_thumbnail = HelperboxSettings::get_instance()->get_config('enable_media_custom_thumbnail');
        if ($enable_media_custom_thumbnail) {
            if ($entity->hasField('field_custom_thumbnail') && !$entity->get('field_custom_thumbnail')->isEmpty()) {
                $custom_thumbnail = $entity->field_custom_thumbnail->entity;
                if ($custom_thumbnail) {
                    $thumbnail_info = MediaHelper::get_media_library_info($custom_thumbnail->id())[0] ?? '';
                    if ($thumbnail_info) {
                        // Force update thumbnail
                        $entity->set('thumbnail', [
                            'target_id' => $thumbnail_info['file_id'],
                            'alt'       => $thumbnail_info['alt_text'],
                            'title'     => $thumbnail_info['title_text'],
                        ]);

                        // Optional: Mark as changed to force save
                        $entity->setNewRevision();
                        $entity->set('changed', \Drupal::time()->getRequestTime());
                    }
                }
            }
        }
    }
}
