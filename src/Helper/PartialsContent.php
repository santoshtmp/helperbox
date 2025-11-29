<?php

namespace Drupal\helperbox\Helper;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;



/**
 * Custom class to handle Partials Content
 * PartialsContent
 * version 1.0.0
 * time 2025110500
 */
class PartialsContent {

    /**
     * Get Countries In Focus Content.
     *
     * @return array
     */
    public static function getCountriesInFocus() {
        $data = [
            'field_mapboxgl_access_token' => '',
            'countries' => [],
            'total_countries' => 0,
            'total_cso' => 0,
            'total_resources' => 0,
        ];
        try {
            // Get Mapbox Access Token from FIMI Settings
            $config_pages_loader = new \Drupal\config_pages\ConfigPagesLoaderService();
            $fimi_settings = $config_pages_loader->load('fimi_settings');
            if ($fimi_settings) {
                // field_mapboxgl_access_token
                if ($fimi_settings->hasField('field_mapboxgl_access_token') && !$fimi_settings->get('field_mapboxgl_access_token')->isEmpty()) {
                    $data['field_mapboxgl_access_token'] = $fimi_settings->get('field_mapboxgl_access_token')->value;
                }
            }

            // ---- Get total collaborator CSO's ----
            $term_cso_id = 9;
            $cos_query = \Drupal::entityQuery('node')
                ->accessCheck(TRUE)
                ->condition('type', 'collaborator')
                ->condition('status', 1)
                ->condition('field_collaborator_type.target_id', $term_cso_id);

            $data['total_cso'] = $cos_query->count()->execute();

            // ---- Get total Resources ----
            $resource_query = \Drupal::entityQuery('node')
                ->accessCheck(TRUE)
                ->condition('type', 'resources')
                ->condition('status', 1);
            $data['total_resources'] = $resource_query->count()->execute();

            // Build query: get all published country nodes except node ID 12
            $query = \Drupal::entityQuery('node')
                ->accessCheck(TRUE)
                ->condition('type', 'country')
                ->condition('status', 1)
                ->condition('nid', 12, '<>'); // Exclude node ID 12

            $nids = $query->execute();
            if (empty($nids)) {
                return $data;
            }

            // Set total count.
            $data['total_country'] = count($nids);

            // Load nodes
            $nodes = Node::loadMultiple($nids);

            foreach ($nodes as $node) {
                $node_content = [];
                $nid = $node->id();

                $node_content['id'] = $nid;
                $node_content['title'] = $node->getTitle();

                // Generate node URL
                $node_content['url'] = Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE])->toString();

                // Body and summary
                $node_content['body'] = $node->get('body')->value ?? '';
                $node_content['summary'] = $node->get('body')->summary ?? '';

                // Count related resources
                $related_resource_query = \Drupal::entityQuery('node')
                    ->accessCheck(TRUE)
                    ->condition('type', 'resources')
                    ->condition('status', 1)
                    ->condition('field_related_countries', $nid);
                $node_content['number_of_resources'] = $related_resource_query->count()->execute();

                // count related collaborator CSO's
                $related_cos_query = \Drupal::entityQuery('node')
                    ->accessCheck(TRUE)
                    ->condition('type', 'collaborator')
                    ->condition('status', 1)
                    ->condition('field_collaborator_type.target_id', $term_cso_id)
                    ->condition('field_related_countries', $nid);
                $node_content['number_of_csos'] = $related_cos_query->count()->execute();

                // field_country_code_3digit
                $field_country_code_3digit = '';
                if ($node->hasField('field_country_code_3digit') && !$node->get('field_country_code_3digit')->isEmpty()) {
                    $field_country_code_3digit = $node->get('field_country_code_3digit')->value;
                    $node_content['field_country_code_3digit'] =  $field_country_code_3digit;
                }
                if (!$field_country_code_3digit) {
                    continue;
                }

                $data['countries'][$field_country_code_3digit] = $node_content;
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
        return $data;
    }
}
