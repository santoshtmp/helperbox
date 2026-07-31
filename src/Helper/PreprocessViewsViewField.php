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

/**
 * 
 */
class PreprocessViewsViewField {

    public static function process_views_view_field(&$variables) {
        $field = $variables['field'];
        $view = $variables['view'];
        $row = $variables['row'];
        // 
        $view_id = $view->id();
        $current_display =  $view->current_display;
        // 
    }

    /**
     * Preprocess the "nothing" custom text field in content_detail block_1.
     *
     * Replaces the placeholder [replace_field_file_upload] with a proper
     * download link using the file from the media field.
     *
     * @param array $variables
     *   Variables passed to the views_view_field theme hook.
     */
    public static function content_detail_block_1_nothing_field_test(&$variables) {
        // $field = $variables['field'];
        // $view = $variables['view'];
        $row = $variables['row'];
        // 
        $entity = $row->_entity ?? NULL;
        if (!$entity || $entity->getEntityTypeId() !== 'node') {
            return;
        }
        $replaceFieldFileUploadContent = "";

        if ($entity->hasField('field_file_upload') && !$entity->get('field_file_upload')->isEmpty()) {
            $field_file_upload_id = $entity->get('field_file_upload')->entity->id();
            $media = MediaHelper::get_media_library_info($field_file_upload_id);
            if (isset($media[0]['file_path'])) {
                $replaceFieldFileUploadContent .= '<a href="' . $media[0]['file_path'] . '" download>' . t('Download Report') . '</a>';
            }
        }

        $placeholder = '[replace_field_file_upload]';
        if (strpos($variables['output'], $placeholder) !== FALSE) {
            $variables['output'] = [
                '#markup' => str_replace($placeholder, $replaceFieldFileUploadContent, $variables['output']),
            ];
        }
    }






    /**
     * ------------ END ------------  
     */
}
