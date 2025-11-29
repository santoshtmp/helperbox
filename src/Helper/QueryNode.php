<?php

/**
 * Reference::
 * https://drupalize.me/tutorial/concept-entity-queries
 * https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module/pagination 
 */


namespace Drupal\helperbox\Helper;


use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

use Drupal\helperbox\Helper\UtilHelper;

/**
 * Query Node class
 */
class QueryNode {


    /**
     * Load nodes.
     *
     * @param int $per_page
     *   Number of nodes to load per page.
     * @param string|null $ids
     *   Comma-separated list of node IDs, e.g., "22,44,77,45".
     *
     * @return array 
     *   Array of loaded node.
     */
    public static function get_content_block_services_info($per_page = 0, $ids = '') {
        $nodes_content = [];
        try {
            // $query = \Drupal::entityQuery('node')
            //     ->accessCheck(TRUE)
            //     ->condition('type', 'services') // type
            //     ->condition('status', 1) // status
            //     ->sort('changed', 'DESC') //created, changed
            //     ->pager($per_page) // per page
            //     ->execute();
            // $nodes = Node::loadMultiple($query);

            $query = \Drupal::entityQuery('node')
                ->accessCheck(TRUE)
                ->condition('type', 'services') // type
                ->condition('status', 1) // status
                ->pager($per_page); // per page
            if (!empty($ids)) {
                $nid_array = array_map('intval', explode(',', $ids));
                $query->condition('nid', $nid_array, 'IN');
                $ordered_nids = $nid_array;
            } else {
                $query->sort('changed', 'DESC'); //created, changed
            }
            $nids = $query->execute();
            $nodes = Node::loadMultiple($nids);
            $current_user = \Drupal::currentUser();
            $current_path = \Drupal::service('path.current')->getPath();
            foreach ($nodes as $node) {
                $node_content = [];
                // Node ID and Title
                $nid = $node_content['id'] = $node->id();
                $node_content['title'] = $node->getTitle();
                // Get display settings
                $display = EntityViewDisplay::load('node.' . $node->bundle() . '.default');
                // Generate Node URL
                $node_content['url'] = Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE])->toString();
                // Body Content
                $node_content['body'] = $node->get('body')->value; // Full body content
                $node_content['summary'] = $node->get('body')->summary; // Summary if available
                // 
                if (!$node->get('field_icon')->isEmpty()) {
                    $field_icon_id = $node->get('field_icon')->entity->id();
                    $field_icon_style = MediaHelper::get_component_image_style($display, 'field_icon');
                    $node_content['field_icon'] = MediaHelper::get_media_library_info($field_icon_id, $field_icon_style)[0];
                }
                // 
                // if (!$node->get('field_featured_image')->isEmpty()) {
                //     $field_featured_image_id = $node->get('field_featured_image')->entity->id();
                //     $node_content['field_featured_image'] = MediaHelper::get_media_library_info($field_featured_image_id);
                // }
                // 
                // field_lottie_file
                if ($node->hasField('field_lottie_file') && !$node->get('field_lottie_file')->isEmpty()) {
                    $json_lottie_file_id = $node->get('field_lottie_file')->entity->id();
                    if ($json_lottie_file_id) {
                        $json_lottie_file  = MediaHelper::get_media_library_info($json_lottie_file_id);
                        if (isset($json_lottie_file[0]['file_url']))
                            $node_content['lottie_file'] = $json_lottie_file[0];
                    }
                }
                // 
                $key_offerings = $node->get('field_key_offerings');
                $node_content['field_key_offerings'] = [];
                foreach ($key_offerings as $key => $key_offering) {
                    $paragraph = $key_offering->entity;
                    $node_content['field_key_offerings'][$key]['field_title'] = $paragraph->get('field_title')->value;
                    $node_content['field_key_offerings'][$key]['field_description'] = $paragraph->get('field_description')->value;
                }
                // 
                if ($node->access('update', $current_user)) {
                    $node_content['can_update'] = true;
                    $node_content['update_url'] = Url::fromRoute('entity.node.edit_form', ['node' => $nid,], ['query' => ['destination' => $current_path],])->toString();
                } else {
                    $node_content['can_update'] = false;
                }
                // 
                $nodes_content[] = $node_content;
            }
            // Reorder nodes based on original ID order
            if (isset($ordered_nids) && !empty($ordered_nids)) {
                usort($nodes_content, function ($a, $b) use ($ordered_nids) {
                    $pos_a = array_search((int)$a['id'], $ordered_nids);
                    $pos_b = array_search((int)$b['id'], $ordered_nids);
                    return $pos_a <=> $pos_b;
                });
            }
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
        return $nodes_content;
    }

    /**
     * Load content nodes.
     *
     * @param int $per_page
     *   Number of nodes to load per page.
     * @param string|null $ids
     *   Comma-separated list of node IDs, e.g., "22,44,77,45".
     *
     * @return array 
     *   Array of loaded node.
     */
    public static function get_content_block_project_info($per_page = 0, $ids = '') {
        $nodes_content = [];
        try {
            $query = \Drupal::entityQuery('node')
                ->accessCheck(TRUE)
                ->condition('type', 'projects') // type
                ->condition('status', 1) // status
                ->pager($per_page); // per page
            if (!empty($ids)) {
                $nid_array = array_map('intval', explode(',', $ids));
                $query->condition('nid', $nid_array, 'IN');
                $ordered_nids = $nid_array;
            } else {
                $query->sort('changed', 'DESC'); //created, changed
            }
            $nids = $query->execute();

            $nodes = Node::loadMultiple($nids);
            $current_user = \Drupal::currentUser();
            $current_path = \Drupal::service('path.current')->getPath();
            foreach ($nodes as $node) {
                $node_content = [];
                // Node ID and Title
                $nid = $node_content['id'] = $node->id();
                $node_content['title'] = $node->getTitle();
                // Generate Node URL
                $node_content['url'] = Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE])->toString();
                // Body Content
                // $node_content['body'] = $node->get('body')->value; // Full body content
                // $node_content['summary'] = $node->get('body')->summary; // Summary if available
                // 
                // 
                if ($node->hasField('field_upload_video') && !$node->get('field_upload_video')->isEmpty()) {
                    $upload_video_id = $node->get('field_upload_video')->entity->id();
                    if ($upload_video_id) {
                        $node_content['upload_video'] = MediaHelper::get_media_library_info($upload_video_id);
                    }
                }
                // 
                if ($node->access('update', $current_user)) {
                    $node_content['can_update'] = true;
                    $node_content['update_url'] = Url::fromRoute('entity.node.edit_form', ['node' => $nid,], ['query' => ['destination' => $current_path],])->toString();
                } else {
                    $node_content['can_update'] = false;
                }
                // 
                $nodes_content[] = $node_content;
            }
            // Reorder nodes based on original ID order
            if (isset($ordered_nids) && !empty($ordered_nids)) {
                usort($nodes_content, function ($a, $b) use ($ordered_nids) {
                    $pos_a = array_search((int)$a['id'], $ordered_nids);
                    $pos_b = array_search((int)$b['id'], $ordered_nids);
                    return $pos_a <=> $pos_b;
                });
            }
        } catch (\Throwable $th) {
            UtilHelper::helperbox_error_log($th);
        }
        return $nodes_content;
    }


    /**
     * @param string $entity_type
     * @param int $items_per_page
     */
    public static function get_content_items($entity_type, $items_per_page = 2) {

        // Get the file URL generator service
        $file_url_generator = \Drupal::service('file_url_generator');

        // Get the current page number from the request
        $request = \Drupal::request();
        $page = $request->query->get('page', 0);

        $query = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('type', $entity_type)
            ->condition('status', 1) // 1 for published, 0 for unpublished
            ->sort('created', 'DESC')
            ->pager($items_per_page) // Apply pagination
            ->execute();

        $nodes = Node::loadMultiple($query);
        $node_contents = [];
        foreach ($nodes as $node) {
            $node_content = [];
            // Node ID and Title
            $nid = $node_content['id'] = $node->id();
            $title = $node_content['title'] = $node->getTitle();

            // Generate Node URL
            $node_url = $node_content['url'] = Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE])->toString();

            // Body Content
            $body =  $node_content['body'] = $node->get('body')->value; // Full body content
            $summary =  $node_content['summary'] = $node->get('body')->summary; // Summary if available

            // Featured Image
            $image_url = 'No Image';
            $node_content['featured_image'] = '';
            if (!$node->get('field_featured_image')->isEmpty()) {
                $image_file = $node->get('field_featured_image')->entity;
                $node_content['featured_image']['name'] = '';
                $node_content['featured_image']['url'] = '';
                if ($image_file instanceof Media) {
                    $node_content['featured_image']['name'] = $image_file->getName();
                    $file = $image_file->get('field_media_image')->entity; // Get the file entity
                    if ($file instanceof File) {
                        // Get Image URL
                        $image_url = $node_content['featured_image']['url'] = $file_url_generator->generateAbsoluteString($file->getFileUri());
                        // Get Image Name
                        $image_name = $file->getFilename();
                        // Get MIME Type
                        $image_mime = $file->getMimeType();
                    }
                }
            }

            // Output
            echo "<h2>$title</h2>";
            echo "<div><strong>URL:</strong> <a href='$node_url'>$node_url</a></div>"; // Show URL
            echo "<div><strong>ID:</strong> $nid</div>";
            echo "<div><strong>Content:</strong> $body</div>";
            echo "<div><strong>Summary:</strong> $summary</div>";
            echo "<div><strong>Featured Image:</strong> <img src='$image_url' width='200'></div>";
            echo "<hr>";
        }
        // Render pagination links
        // echo theme('pager');
        // $query = \Drupal::entityQuery('node');
        // $newest_articles = $query
        //     ->accessCheck(TRUE)
        //     ->condition('type', 'projects')
        //     ->condition('status', 1)
        //     ->condition('field_drupal_version', '9', '>=')
        //     ->sort('created', 'DESC')
        //     ->execute();

    }

    /**
     *  ------------- END ------------- 
     */
}
