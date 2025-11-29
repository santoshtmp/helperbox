<?php

/**
 * Reference::
 * https://drupalize.me/
 * https://drupal.stackexchange.com/questions/157295/preprocess-node-in-module
 * https://api.drupal.org/api/drupal/core%21modules%21node%21node.module/function/template_preprocess_node/9
 * https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21theme.api.php/function/hook_preprocess_HOOK/10
 * 
 * preprocess node variables before they are passed to the node.html.twig template.
 * preprocess_node must be call in .theme file with function themename_preprocess_node(array &$variables){}
 * 
 */

namespace Drupal\helperbox\Helper;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\node\Entity\NodeType;

/**
 * 
 */
class PreprocessNode {


    /**
     * preprocess node for contenttype1 content type
     */
    public static function contenttype1_preprocess_node(array &$variables) {
        try {
            // get node
            $node = $variables['node'];
            // Get display settings
            $display = EntityViewDisplay::load('node.' . $node->bundle() . '.default');
            // field_icon
            if ($node->hasField('field_icon') && !$node->get('field_icon')->isEmpty()) {
                $field_icon_id = $node->get('field_icon')->entity->id();
                if ($field_icon_id) {
                    $field_icon_style = MediaHelper::get_component_image_style($display, 'field_icon');
                    $icon = MediaHelper::get_media_library_info($field_icon_id, $field_icon_style);
                }
            }
            // field_background_image
            if ($node->hasField('field_background_image') && !$node->get('field_background_image')->isEmpty()) {
                $field_background_image_id = $node->get('field_background_image')->entity->id();
                if ($field_background_image_id) {
                    $field_background_image_style = MediaHelper::get_component_image_style($display, 'field_background_image');
                    $background_image = MediaHelper::get_media_library_info($field_background_image_id, $field_background_image_style);
                }
            }
            // field_featured_image 
            if ($node->hasField('field_featured_image')) {
                $field_featured_image_ids = [];
                foreach ($node->get('field_featured_image')->getValue() as $item) {
                    $field_featured_image_ids[] = $item['target_id'];
                }
                $field_featured_image_style = MediaHelper::get_component_image_style($display, 'field_featured_image');
                $featured_image = MediaHelper::get_media_library_info($field_featured_image_ids, $field_featured_image_style);
            }
            // field_featured_image_collection 
            if ($node->hasField('field_featured_image_collection')) {
                if (!$node->get('field_featured_image_collection')->isEmpty()) {
                    $field_featured_image_collection_ids = [];
                    foreach ($node->get('field_featured_image_collection')->getValue() as $item) {
                        $field_featured_image_collection_ids[] = $item['target_id'];
                    }
                    $field_featured_image_collection_style = MediaHelper::get_component_image_style($display, 'field_featured_image_collection');
                    $featured_image_collection = MediaHelper::get_media_library_info($field_featured_image_collection_ids, $field_featured_image_collection_style);
                }
            }
            // field_photo_gallery 
            if ($node->hasField('field_photo_gallery')) {
                if (!$node->get('field_photo_gallery')->isEmpty()) {
                    $field_photo_gallery_ids = [];
                    foreach ($node->get('field_photo_gallery')->getValue() as $item) {
                        $field_photo_gallery_ids[] = $item['target_id'];
                    }
                    $field_photo_gallery_style = MediaHelper::get_component_image_style($display, 'field_photo_gallery');
                    $photo_gallery = MediaHelper::get_media_library_info($field_photo_gallery_ids, $field_photo_gallery_style);
                }
            }
            // field_lottie_file
            if ($node->hasField('field_lottie_file') && !$node->get('field_lottie_file')->isEmpty()) {
                $json_lottie_file_id = $node->get('field_lottie_file')->entity->id();
                if ($json_lottie_file_id) {
                    $json_lottie_file = MediaHelper::get_media_library_info($json_lottie_file_id);
                }
            }

            // Re-arrange the content data 
            $variables['content']['node_type'] = $node->bundle();
            if ($node_type = NodeType::load($node->bundle())) {
                $variables['content']['node_type_label'] = $node_type->label();
            }
            $variables['content']['title'] = $node->getTitle();
            $variables['content']['icon'] = isset($icon) ? $icon : '';
            $variables['content']['background_image'] = isset($background_image) ? $background_image : '';
            $variables['content']['featured_image'] = isset($featured_image) ? $featured_image : '';
            $variables['content']['featured_image_collection'] = isset($featured_image_collection) ? $featured_image_collection : '';
            $variables['content']['photo_gallery'] = isset($photo_gallery) ? $photo_gallery : '';
            $variables['content']['lottie_file'] = isset($json_lottie_file) ? $json_lottie_file[0] : '';
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
    }


    /**
     * preprocess node for contenttype2 content type
     */
    public static function contenttype2_preprocess_node(array &$variables) {
        try {
            // get node
            $node = $variables['node'];
            // Get display settings
            $display = EntityViewDisplay::load('node.' . $node->bundle() . '.default');

            // field_featured_image 
            if ($node->hasField('field_featured_image') && !$node->get('field_featured_image')->isEmpty()) {
                $field_featured_image_ids = [];
                foreach ($node->get('field_featured_image')->getValue() as $item) {
                    $field_featured_image_ids[] = $item['target_id'];
                }
                $field_featured_image_style = MediaHelper::get_component_image_style($display, 'field_featured_image');
                $featured_image = MediaHelper::get_media_library_info($field_featured_image_ids, $field_featured_image_style);
            }
            // field_photo_gallery 
            if ($node->hasField('field_photo_gallery') && !$node->get('field_photo_gallery')->isEmpty()) {
                $field_photo_gallery_ids = [];
                foreach ($node->get('field_photo_gallery')->getValue() as $item) {
                    $field_photo_gallery_ids[] = $item['target_id'];
                }
                $field_photo_gallery_style = MediaHelper::get_component_image_style($display, 'field_photo_gallery');
                $photo_gallery = MediaHelper::get_media_library_info($field_photo_gallery_ids, $field_photo_gallery_style);
            }
            // // field_related_topic
            // $content_related_topic = [];
            // if ($node->hasField('field_related_topic')) {
            //     $get_field_related_topic = $node->get('field_related_topic');
            //     foreach ($get_field_related_topic as $key => $related_topic) {
            //         $paragraph = $related_topic->entity;
            //         // $icon = [];
            //         // foreach ($paragraph->get('field_icon')->getValue() as $item) {
            //         //     $icon[] = $item['target_id'];
            //         // }
            //         // $icon_style = MediaHelper::get_component_image_style($display, 'field_icon');
            //         // $content_related_topic[$key]['field_icon'] = MediaHelper::get_media_library_info($icon, $icon_style);
            //         if (!$paragraph->get('field_title')->isEmpty()) {
            //             $content_related_topic[$key]['field_title'] = $paragraph->get('field_title')->value;
            //         }
            //     }
            // }
            // Re-arrange the content data 
            $variables['content']['node_type'] = $node->bundle();
            if ($node_type = NodeType::load($node->bundle())) {
                $variables['content']['node_type_label'] = $node_type->label();
            }
            $variables['content']['title'] = $node->getTitle();
            $variables['content']['featured_image'] = isset($featured_image) ? $featured_image : '';
            $variables['content']['photo_gallery'] = isset($photo_gallery) ? $photo_gallery : '';
            $variables['content']['related_topic'] = isset($content_related_topic) ? $content_related_topic : '';
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
    }

    /**
     * ------------ END ------------  
     */
}
