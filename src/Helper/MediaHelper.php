<?php

namespace Drupal\helperbox\Helper;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\image\Entity\ImageStyle;

/**
 * custom class to handle Media helper
 * MediaHelper
 * version 1.0.0
 * time 2025081500
 */
class MediaHelper {

    /**
     * @param Drupal\Core\Entity\Entity\EntityViewDisplay $display
     * @param string $field_name
     */
    public static function get_component_image_style($display, $field_name, $all_settings = false) {
        try {
            if ($display) {
                // Get the field component settings
                $component = $display->getComponent($field_name);
                if ($all_settings) {
                    return $component['settings'];
                }
                if (!empty($component['settings']['image_style'])) {
                    return $component['settings']['image_style'];
                }
            }
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
        return '';
    }

    /**
     * 
     */
    public static function get_media_field_name($media_type) {
        $field_name = 'field_media_file';
        switch ($media_type) {
            case 'image':
                $field_name = 'field_media_image';
                break;
            case 'video':
                $field_name = 'field_media_video_file';
                break;
            case 'audio':
                $field_name = 'field_media_audio_file';
                break;
            case 'remote_video':
                $field_name = 'field_media_oembed_video';
                break;
            case 'document':
                $field_name = 'field_media_document';
                break;
            default:
                $field_name = 'field_media_file';
        }
        return $field_name;
    }

    /**
     * @param string $media_ids
     * @param string $image_style
     * @return array
     */
    public static function get_media_library_info($media_ids, $image_style = '', $image_loading = '', $get_thumbnail = false) {
        $media_infos = [];
        try {

            if (is_string($media_ids) || is_int($media_ids)) {
                $media_ids = explode(',', $media_ids);
            }

            foreach ($media_ids as $key => $media_id) {
                if ($media_id) {
                    $media = Media::load($media_id);
                    if ($media) {
                        $media_type = $media->bundle();
                        $field_name = self::get_media_field_name($media_type);
                        if ($media && $media->hasField($field_name) && !$media->get($field_name)->isEmpty()) {
                            $media_entity = $media->get($field_name)->entity;
                            if ($media_entity instanceof File) {
                                $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($media_entity->getFileUri());
                                $file_path = \Drupal::service('file_url_generator')->generateString($media_entity->getFileUri());

                                if ($image_style && $media_type == 'image') {
                                    $image_uri = $media->get('field_media_image')->entity->uri->value;
                                    $file_url = ImageStyle::load($image_style)->buildUrl($image_uri);
                                    $file_url = ImageStyle::load($image_style)->buildUrl($image_uri);
                                    $file_path = \Drupal::service('file_url_generator')->generateString($file_url);
                                }

                                $thumbnail_url = '';
                                if ($get_thumbnail) {
                                    if ($media->hasField('field_thumbnail') && !$media->get('field_thumbnail')->isEmpty()) {
                                        $thumbnail_id = [];
                                        foreach ($media->get('field_thumbnail')->getValue() as $item) {
                                            $thumbnail_id[] = $item['target_id'];
                                        }
                                        if ($thumbnail_id) {
                                            $thumbnail_url = self::get_media_library_info($thumbnail_id);
                                        }
                                    } else {
                                        // Try to get the default image set in field settings
                                        $field_thumbnail = $media->getFieldDefinition('field_thumbnail');
                                        if ($field_thumbnail) {
                                            $default_value = $field_thumbnail->getDefaultValueLiteral();
                                            $uuid = isset($default_value[0]['target_uuid']) ? $default_value[0]['target_uuid'] : '';
                                            // Load the media entity via UUID.
                                            $entity_media_uuid = \Drupal::entityTypeManager()
                                                ->getStorage('media')
                                                ->loadByProperties(['uuid' => $uuid]);
                                            if (!empty($entity_media_uuid)) {
                                                /** @var \Drupal\media\Entity\Media $media_thumbnail */
                                                $media_thumbnail = reset($entity_media_uuid);
                                                $thumbnail_url = self::get_media_library_info($media_thumbnail->id());
                                            }
                                        }
                                    }
                                }
                                // 
                                $media_info = [];
                                $media_info['media_id'] = $media_id;
                                $media_info['media_type'] = $media_type;
                                $media_info['file_url'] = $file_url;
                                $media_info['file_path'] = $file_path;
                                $media_info['file_name'] = $media_entity->getFilename();
                                $media_info['file_size'] = ($media_entity->getSize());
                                $media_info['file_mime'] = $media_entity->getMimeType();
                                $media_info['created_time'] = \Drupal::service('date.formatter')->format($media_entity->getCreatedTime(), 'custom', 'Y-m-d H:i:s');
                                $media_info['thumbnail'] = $thumbnail_url;
                                $media_info['image_style'] = $image_style;
                                $media_info['image_loading'] = $image_loading;

                                //   
                                $media_infos[] = $media_info;
                            } else if ($media_type === 'remote_video') {
                                // 
                                $oembed_url = $media->get($field_name)->value;
                                $embed_html = $media->get($field_name)->view(['type' => 'oembed', 'label' => 'hidden']);
                                $thumbnail = [];
                                if ($media->hasField('thumbnail') && !$media->get('thumbnail')->isEmpty()) {
                                    $thumbnail_entity = $media->get('thumbnail')->entity;
                                    $thumbnail['file_url'] = \Drupal::service('file_url_generator')->generateAbsoluteString($thumbnail_entity->getFileUri());
                                    $thumbnail['file_path'] = \Drupal::service('file_url_generator')->generateString($thumbnail_entity->getFileUri());
                                    $thumbnail['file_name'] =  $thumbnail_entity->getFilename();
                                }
                                //
                                $media_info = [];
                                $media_info['media_type'] = $media_type;
                                $media_info['file_url'] = $oembed_url;
                                $media_info['file_name'] = $media->label();
                                $media_info['render_embed_html'] = \Drupal::service('renderer')->renderPlain($embed_html);
                                $media_info['thumbnail'] =  $thumbnail;
                                //   
                                $media_infos[] = $media_info;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
        return  $media_infos;
    }


    /**
     * 
     */
    public static function get_image_style_options() {
        $styles_optionlist = \Drupal\image\Entity\ImageStyle::loadMultiple(); // $styles_optionlist = \Drupal::entityTypeManager()->getStorage('image_style')->loadMultiple();
        $image_style_options = [];
        $image_style_options[''] = "None (original)";
        foreach ($styles_optionlist as $style) {
            $image_style_options[$style->id()] = $style->label();
        }
        return $image_style_options;
    }

    /**
     * 
     * Implements hook_entity_view().
     * https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21entity.api.php/function/hook_entity_view/10
     * 
     */
    public static function media_attached_style(array &$build, \Drupal\Core\Entity\EntityInterface $entity, $view_mode, $langcode) {
        // Only target media entities of type 'video' in 'media_library' view mode.
        try {
            if (
                $entity->getEntityTypeId() === 'media' &&
                in_array($entity->bundle(), ['audio', 'document', 'video', 'image'])
            ) {
                $build['#attached']['html_head'][] = [
                    [
                        '#tag' => 'style',
                        '#value' => '.field--name-thumbnail.field--type-image {min-height: 180px;}',
                    ],
                ];
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
    /**
     * ------------ END ------------  
     */
}
