<?php

namespace Drupal\helperbox\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\helperbox\Helper\DataImport\FieldMaping;
use Drupal\media\MediaInterface;

class ContentDataImportAPIController extends ControllerBase {

    public function init(Request $request): JsonResponse {

        // -------------------------
        // 1. Read request values
        // -------------------------
        $source_url = $request->request->get('source_url', '');
        $content_type = $request->request->get('content_type', '');
        $node_content = $request->request->get('node_content', '');
        $taxonomy_term_content = $request->request->get('taxonomy_term_content', '');
        $page = $request->request->get('page', 0);

        // -------------------------
        // 2. Default response structure
        // -------------------------
        $data =  [];
        $data['status']         = FALSE;
        $data['source_url']     = $source_url;
        $data['content_type']   = $content_type;
        $data['node_content']   = $node_content;
        $data['taxonomy_term_content'] = $taxonomy_term_content;
        $data['message'] = "Not completed.";

        // -------------------------
        // 3. Basic validation (IMPORTANT)
        // -------------------------
        $validateData =   $this->validateData($source_url, $content_type, $node_content, $taxonomy_term_content);
        if ($validateData['status'] === false) {
            $data = array_merge($data, $validateData);
            $response = new JsonResponse($data);
            $response->setMaxAge(3600);
            $response->setPublic();
            return $response;
        }

        // -------------------------
        // 4. Increase safety for long imports (optional)
        // -------------------------
        @ini_set('max_execution_time', 300);


        // -------------------------
        // 5. Your import logic starts here
        // -------------------------
        if ($content_type == 'taxonomy') {
            $import_data = self::initializeTaxonomyFieldMapping($source_url, $taxonomy_term_content);
            $data = [...$data, ...$import_data];
        } else if ($content_type == 'node') {
            $import_data = self::initializeNodeFieldMapping($source_url, $node_content, $page);
            $data = [...$data, ...$import_data];
        } else {
        }

        $response = new JsonResponse($data);
        $response->setMaxAge(3600);
        $response->setPublic();

        return $response;
    }

    /**
     *
     * Validates that:
     *  - The selected content type is either "node" or "taxonomy".
     *  - A node content type is selected when importing nodes.
     *  - A taxonomy vocabulary is selected when importing taxonomy terms.
     */
    public function validateData($source_url, $content_type, $node_content, $taxonomy_term_content) {

        if (!$source_url) {
            return [
                'status' => false,
                'message' => $this->t('UR is requiredd.')
            ];
        }

        // Ensure the content type is one of the supported values.
        if (!in_array($content_type, ['node', 'taxonomy'])) {
            return [
                'status' => false,
                'message' => $this->t('Content type is not defined.')
            ];
        }

        if ($content_type === 'node') {
            if (!$node_content) {
                return [
                    'status' => false,
                    'message' => $this->t('Please select a Node Content Type.')
                ];
            }
        }

        if ($content_type === 'taxonomy') {
            if (!$taxonomy_term_content) {
                return [
                    'status' => false,
                    'message' => $this->t('Please select a Taxonomy Vocabulary.')
                ];
            }
        }

        return [
            'status' => true
        ];
    }

    /**
     * Fetches taxonomy terms from the remote site and imports them locally.
     *
     * The import strategy for each term is:
     *  1. Look up the term by UUID — update in place if found.
     *  2. Fall back to matching by name + vocabulary — update if found.
     *  3. Create a new term if neither lookup succeeds.
     *
     * Parent terms are resolved by UUID before being assigned. Root-level
     * terms (no parents) are assigned parent ID 0.
     *
     * The local vocabulary machine name is resolved via {@see taxonomy_maping()}
     * so that remote vocabulary names are transparently translated.
     *
     * @param string $baseurl
     *   The base URL of the remote Drupal site (trailing slash will be trimmed).
     * @param string $remote_taxonomy
     *   The local taxonomy vocabulary machine name to import.
     *
     * @return array{status: bool, message: string}
     *   An associative array with:
     *    - 'status': TRUE on success, FALSE on failure.
     *    - 'message': A human-readable result or error message.
     */
    public static function initializeTaxonomyFieldMapping($baseurl, $remote_taxonomy) {

        try {
            $taxonomy_vid = FieldMaping::taxonomy_field_maping();

            // Translate the local vocabulary name to the remote one (if mapped).
            $taxonomy = $taxonomy_vid[$remote_taxonomy];
            if (!$taxonomy) {
                return [
                    'status'        => false,
                    'message'       => 'Undefined remote map taxonomy type "' . $remote_taxonomy . '".'
                ];
            }

            $url = rtrim($baseurl, "/") . "/helperbox/api/taxonomy/" . $remote_taxonomy;

            $response = \Drupal::httpClient()->get($url, [
                'headers' => ['Accept' => 'application/vnd.api+json'],
                'verify' => FALSE,
            ]);

            $data = json_decode($response->getBody()->getContents(), TRUE);
            if ($data['success'] === true) {

                $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

                $created_count = 0;
                $updated_count = 0;
                $created_items = [];
                $updated_items = [];

                foreach ($data['data'] as $termData) {
                    $is_new = FALSE;

                    // Skip incomplete term records that are missing required fields.
                    if (!$termData['uuid'] || !$termData['name'] || !$termData['vid']) {
                        continue;
                    }
                    // Translate the remote vocabulary ID back to the local machine name.
                    $termData['vid'] = $taxonomy;
                    $term = NULL;

                    // 1. Find by UUID.
                    $existing = $storage->loadByProperties([
                        'uuid' => $termData['uuid'],
                    ]);

                    if (!empty($existing)) {
                        /** @var \Drupal\taxonomy\TermInterface $term */
                        $term = reset($existing);
                    }

                    // 2. Find by name + vocabulary.
                    if (!$term) {
                        $existing = $storage->loadByProperties([
                            'name' => $termData['name'],
                            'vid' => $termData['vid'],
                        ]);

                        if (!empty($existing)) {
                            /** @var \Drupal\taxonomy\TermInterface $term */
                            $term = reset($existing);
                        }
                    }

                    // 3. Create if not found.
                    if (!$term) {
                        $is_new = TRUE;

                        /** @var \Drupal\taxonomy\TermInterface $term */
                        $term = $storage->create([
                            'vid' => $termData['vid'],
                            'name' => $termData['name'],
                            'uuid' => $termData['uuid'],
                        ]);
                    }

                    // Update fields.
                    if ($term->hasField('description') && isset($termData['description'])) {
                        $description = $termData['description']['value'] ?? '';
                        $media = self::extractDescriptionMedia($description, $baseurl);
                        $description = $media['html'] ?? $description;
                        $media_list = $media['media'] ?? [];
                        foreach ($media_list as $img) {
                            $file = ContentDataImportAPIController::importRemoteFile($img);
                        }
                        $term->set('description', [
                            'value'  => $description,
                            'format' => $termData['description']['format'] ?? 'basic_html',
                        ]);
                    }

                    // Set the sort weight (defaults to 0 if not specified).
                    if ($term->hasField('weight')) {
                        $term->set('weight', $termData['weight'] ?? 0);
                    }

                    // Resolve parent term IDs from UUIDs and assign them.
                    if ($term->hasField('parent')) {
                        $parents = $termData['parents'] ?? [];
                        $parent_tids = [];

                        foreach ($parents as $parent) {
                            $parent_uuid = $parent['uuid'] ?? NULL;

                            if ($parent_uuid) {
                                $loaded = $storage->loadByProperties([
                                    'uuid' => $parent_uuid,
                                ]);

                                if (!empty($loaded)) {
                                    $parent_term = reset($loaded);
                                    $parent_tids[] = (int) $parent_term->id();
                                }
                            }
                        }

                        // Default to root (tid 0) if no resolvable parents exist.
                        if (empty($parent_tids)) {
                            $parent_tids = [0];
                        }

                        $term->set('parent', $parent_tids);
                    }

                    $term->save();

                    // Track created/updated terms.
                    if ($is_new) {
                        $created_count++;
                        $created_items[] = $term->label();
                    } else {
                        $updated_count++;
                        $updated_items[] = $term->label();
                    }
                }

                return [
                    'status'        => true,
                    'message'       => 'Completed',
                    'created_count' => $created_count,
                    'updated_count' => $updated_count,
                    'created_items' => $created_items,
                    'updated_items' => $updated_items,
                ];
            }

            return [
                'status'        => false,
                'message'       => 'fail to receive data'
            ];
        } catch (\Throwable $th) {
            return [
                'status'  => false,
                'message' => $th->getMessage() . " at \"" . $th->getFile() . "\" : " . $th->getLine(),
            ];
        }
    }


    /**
     * Fetches nodes of the specified type from the remote site and imports
     * them locally, following the field mapping defined in {@see node_maping()}.
     *
     * Pagination is handled automatically: the method follows
     * `pagination.next_page_url` until no further pages remain.
     *
     * For each node the import strategy is:
     *  1. Load an existing local node by UUID — update it if found.
     *  2. Create a new node if no UUID match exists.
     *
     * Supported field types:
     *  - text, string, telephone, integer — scalar value arrays.
     *  - email — uses the 'email' key, falling back to 'value'.
     *  - text_with_summary — preserves value, format, and summary.
     *  - entity_reference -
     *      - entity_type "taxonomy_term" / taxonomy_term_reference — resolves terms by UUID, then name+vid.
     *  - image — downloads remote files, preserves UUID to skip re-downloads
     *    on subsequent runs, and stores the resulting managed file entity.
     *
     * @param string $baseurl
     *   The base URL of the remote Drupal site (trailing slash will be trimmed).
     * @param string $remote_node_type
     *   The local node type machine name to import.
     * @param int $page
     * 
     * 
     * @return array
     *
     * @todo Return a structured result (created/updated counts, errors) instead
     *       of void, and move the implementation to a proper service or batch
     *       processor to avoid HTTP timeout issues on large data sets.
     */
    public static function initializeNodeFieldMapping($baseurl, $remote_node_type, $page) {
        try {
            $node_type_field_maping = FieldMaping::node_field_maping();

            // Translate the local node type to the remote equivalent (if mapped).
            $local_node_type = $node_type_field_maping[$remote_node_type]['local_node_type'];
            if (!$local_node_type) {
                return [
                    'status'        => false,
                    'message'       => 'Undefined remote map content type "' . $remote_node_type . '".'
                ];
            }
            $fields_map = $node_type_field_maping[$remote_node_type]['fields'] ?? [];
            if (empty($fields_map)) {
                return [
                    'status'        => false,
                    'message'       => 'Fields are not mapped for content type "' . $remote_node_type . '".'
                ];
            }
            $limit = 15;
            $url = rtrim($baseurl, "/") . "/helperbox/api/node/" . $remote_node_type . '?limit=' . $limit . '&page=' . $page;

            $response = \Drupal::httpClient()->get($url, [
                'headers' => ['Accept' => 'application/vnd.api+json'],
                'verify' => FALSE,
            ]);

            $data = json_decode($response->getBody()->getContents(), TRUE);
            if ($data['success'] === true) {

                $created_count = 0;
                $updated_count = 0;
                $created_items = [];
                $updated_items = [];

                $storage   = \Drupal::entityTypeManager()->getStorage('node');

                foreach ($data['data'] as $nodeData) {
                    $is_new = FALSE;

                    // Skip records that are missing core required fields.
                    if (!$nodeData['uuid'] || !$nodeData['title'] || !$nodeData['node_type']) {
                        continue;
                    }

                    // Load an existing node by UUID.
                    $existing = $storage->loadByProperties([
                        'uuid' => $nodeData['uuid'],
                    ]);

                    // If not found by UUID, try by content type and title.
                    if (empty($existing)) {
                        $existing = $storage->loadByProperties([
                            'type' => $local_node_type,
                            'title' => $nodeData['title'],
                        ]);
                    }

                    if ($existing) {
                        /** @var \Drupal\node\Entity\Node $node */
                        $node = reset($existing);
                    } else {
                        /** @var \Drupal\node\Entity\Node $node */
                        $node = Node::create([
                            'uuid' => $nodeData['uuid'],
                            'type' => $local_node_type,
                        ]);
                        $is_new = TRUE;
                    }

                    // --- Core node fields ---
                    $node->set('type', $local_node_type);

                    $node->setTitle(!empty($nodeData['title']) ? (string) $nodeData['title'] : 'Untitled');
                    $node->set('status', (bool) ($nodeData['status'] ?? TRUE));

                    if (isset($nodeData['created']) && !empty($nodeData['created'])) {
                        $node->setCreatedTime((int) $nodeData['created']);
                    }
                    if ($nodeData['changed'] && !empty($nodeData['changed'])) {
                        $node->setChangedTime((int) $nodeData['changed']);
                    }
                    $language = $nodeData['language'] ?? 'en';
                    $language = ($language === 'und') ? 'en' : $language;
                    if ($node->hasField('langcode')) {
                        $node->set('langcode', $language);
                    }

                    // --- Mapped custom fields ---
                    $nodeFieldsData = $nodeData['fields'] ?? [];
                    foreach ($fields_map as $remote_field => $current_field_config) {
                        $current_field = $current_field_config;
                        $current_field_type = NULL;
                        if (is_array($current_field_config)) {
                            $current_field = $current_field_config['field_name'] ?? null;
                            $current_field_type = $current_field_config['field_type'] ?? null;
                        }

                        if (!$current_field) {
                            continue;
                        }

                        // Skip if the local field doesn't exist on this node type.
                        if (!$node->hasField($current_field)) {
                            continue;
                        }

                        // Skip if the remote payload doesn't include this field.
                        if (!isset($nodeFieldsData[$remote_field])) {
                            continue;
                        }

                        $remote_field_type = $nodeFieldsData[$remote_field]['type']  ?? '';
                        $fieldValue = $nodeFieldsData[$remote_field]['value'] ?? [];
                        $field_type = $current_field_type ? $current_field_type : $remote_field_type;

                        switch ($field_type) {
                            // Plain scalar field types: extract the 'value' key from each item.
                            case 'text':
                            case 'string':
                            case 'telephone':
                            case 'integer':
                            case 'datetime':
                                $values = array_map(fn($item) => $item['value'] ?? NULL, $fieldValue);
                                $values = array_filter($values, fn($v) => $v !== NULL);
                                $node->set($current_field, array_values($values));
                                break;
                            // Email fields use the 'email' key, falling back to 'value'.
                            case 'email':
                                $values = array_map(fn($item) => $item['email'] ?? $item['value'] ?? NULL, $fieldValue);
                                $values = array_filter($values, fn($v) => $v !== NULL);
                                $node->set($current_field, array_values($values));
                                break;
                            // Date only field type.
                            case 'date_only':
                                $values = [];
                                $site_timezone_name = \Drupal::config('system.date')->get('timezone.default')
                                    ?: date_default_timezone_get();
                                $site_timezone = new \DateTimeZone($site_timezone_name);

                                foreach ($fieldValue as $item) {
                                    if (!empty($item['value'])) {
                                        $remote_date_type = $item['date_type'] ?? '';
                                        $date_value = new \DateTime($item['value']);
                                        if ($remote_date_type == 'datetime') {
                                            $date_value->setTimezone($site_timezone);
                                        }
                                        $values[] = [
                                            'value' => $date_value->format('Y-m-d'),
                                        ];
                                    }
                                }
                                $node->set($current_field, $values);
                                break;
                            // Date Range field type.
                            case 'date_range':
                                $values = [];
                                $time_values = [];

                                $date_only = $current_field_config['date_only'] ?? FALSE;
                                $time_field_name = $current_field_config['time_field_name'] ?? NULL;

                                $site_timezone_name = \Drupal::config('system.date')->get('timezone.default')
                                    ?: date_default_timezone_get();
                                $site_timezone = new \DateTimeZone($site_timezone_name);

                                foreach ($fieldValue as $item) {
                                    if (empty($item['value'])) {
                                        continue;
                                    }
                                    $remote_date_type = $item['date_type'] ?? '';
                                    $start_date = new \DateTime($item['value']);
                                    $end_date = !empty($item['end_value'])
                                        ? new \DateTime($item['end_value'])
                                        : NULL;

                                    // Some APIs provide the end datetime in value2.
                                    if (!empty($item['value2'])) {
                                        $end_date = new \DateTime($item['value2']);
                                    }

                                    if ($remote_date_type == 'datetime') {
                                        $start_date->setTimezone($site_timezone);
                                        if ($end_date) {
                                            $end_date->setTimezone($site_timezone);
                                        }
                                    }
                                    // Date range field.
                                    if ($date_only) {
                                        $values[] = [
                                            'value' => $start_date->format('Y-m-d'),
                                            'end_value' => $end_date
                                                ? $end_date->format('Y-m-d')
                                                : $start_date->format('Y-m-d'),
                                        ];
                                    }

                                    // Associated time field (stored as seconds since midnight).
                                    if ($time_field_name) {
                                        $start_seconds = ((int) $start_date->format('H') * 3600)
                                            + ((int) $start_date->format('i') * 60)
                                            + (int) $start_date->format('s');

                                        $end_seconds = $end_date
                                            ? (((int) $end_date->format('H') * 3600)
                                                + ((int) $end_date->format('i') * 60)
                                                + (int) $end_date->format('s'))
                                            : $start_seconds;

                                        $time_values[] = [
                                            'value' => $start_seconds,
                                        ];

                                        // Avoid duplicate values.
                                        if ($end_seconds !== $start_seconds) {
                                            $time_values[] = [
                                                'value' => $end_seconds,
                                            ];
                                        }
                                    }
                                }

                                if (!empty($values)) {
                                    $node->set($current_field, $values);
                                }

                                if ($time_field_name && !empty($time_values)) {
                                    $node->set($time_field_name, $time_values);
                                }

                                break;
                            // Long text fields that carry an optional summary and text format.
                            case 'text_with_summary':
                                $values = [];
                                foreach ($fieldValue as $item) {
                                    if (empty($item['value'])) {
                                        continue;
                                    }
                                    $media = self::extractDescriptionMedia($item['value'], $baseurl);
                                    $item['value'] = $media['html'] ?? $item['value'];
                                    $media_list = $media['media'] ?? [];
                                    foreach ($media_list as $img) {
                                        $file = self::importRemoteFile($img);
                                    }
                                    $values[] = [
                                        'value'   => $item['value']   ?? '',
                                        'format'  => $item['format']  ?? 'basic_html',
                                        'summary' => $item['summary'] ?? '',
                                    ];
                                }
                                $node->set($current_field, $values);
                                break;

                            // Entity-reference fields pointing to taxonomy terms.
                            case 'taxonomy_term_reference':
                                if (empty($fieldValue)) {
                                    break;
                                }

                                $term_items      = [];
                                $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

                                foreach ($fieldValue as $item) {
                                    $remote_tid = $item['tid']    ?? NULL;
                                    $entity     = $item['entity'] ?? NULL;

                                    // Both the remote term ID and the entity payload are required.
                                    if (!$remote_tid || !$entity) {
                                        continue;
                                    }

                                    $uuid   = $entity['uuid']   ?? NULL;
                                    $bundle = $entity['bundle'] ?? NULL;
                                    $label  = $entity['label']  ?? NULL;

                                    $term = NULL;

                                    // 1. Look up the local term by UUID.
                                    $existing = $taxonomy_storage->loadByProperties(['uuid' => $uuid]);
                                    if (!empty($existing)) {
                                        /** @var \Drupal\taxonomy\TermInterface $term */
                                        $term = reset($existing);
                                    }

                                    // 2. Fall back to name + vocabulary lookup with name translation.
                                    if (!$term && $label && $bundle) {
                                        $taxonomy_maping = FieldMaping::taxonomy_field_maping();
                                        // Translate the remote bundle name to its local equivalent.
                                        $map_taxonomy = $taxonomy_maping[$bundle] ?? ''; //array_search($bundle, $taxonomy_maping);
                                        $bundle = $map_taxonomy ? $map_taxonomy : $bundle;

                                        $existing = $taxonomy_storage->loadByProperties([
                                            'name' => $label,
                                            'vid'  => $bundle,
                                        ]);

                                        if (!empty($existing)) {
                                            /** @var \Drupal\taxonomy\TermInterface $term */
                                            $term = reset($existing);
                                        }
                                    }

                                    if ($term) {
                                        $term_items[] = ['target_id' => $term->id()];
                                    }
                                }

                                if ($term_items) {
                                    $node->set($current_field, $term_items);
                                }
                                break;

                            // Image fields: download remote files and store as managed file entities.
                            case 'image':
                                if (empty($fieldValue)) {
                                    break;
                                }

                                $file_items = [];

                                foreach ($fieldValue as $img) {
                                    $file = self::importRemoteFile($img);
                                    if ($file) {
                                        $file_items[] = [
                                            'target_id' => $file->id(),
                                            'alt'       => $img['alt']   ?? '',
                                            'title'     => $img['title'] ?? '',
                                        ];
                                    }
                                }

                                if ($file_items) {
                                    $node->set($current_field, $file_items);
                                }
                                break;
                            // entity_reference field type
                            case 'entity_reference':
                                if (empty($fieldValue)) {
                                    break;
                                }
                                $current_field_entity_type = $current_field_config['entity_type'] ?? null;
                                // 
                                if ($current_field_entity_type == 'media') {
                                    $media_items = [];
                                    $current_field_bundle = $current_field_config['bundle'] ?? null;
                                    foreach ($fieldValue as $item) {
                                        $media_bundle = $current_field_bundle ? $current_field_bundle : $remote_field_type;
                                        $media_entity = self::importMediaItem(
                                            $item,
                                            ['bundle' => $media_bundle]
                                        );
                                        if ($media_entity) {
                                            $media_items[] = ['target_id' => $media_entity->id()];
                                        }
                                    }
                                    if ($media_items) {
                                        $node->set($current_field, $media_items);
                                    }
                                } elseif ($current_field_entity_type == 'taxonomy_term') {
                                } else {
                                }
                                break;
                            // default
                            default:
                                break;
                        }
                    }

                    // set default field value
                    $default_field_value = $node_type_field_maping[$remote_node_type]['default_field_value'] ?? [];
                    if ($default_field_value && count($default_field_value)) {
                        foreach ($default_field_value as $field => $value) {
                            $node->set($field, $value);
                        }
                    }

                    // save the content
                    $node->save();

                    // Track created/updated terms.
                    if ($is_new) {
                        $created_count++;
                        $created_items[] = $node->label();
                    } else {
                        $updated_count++;
                        $updated_items[] = $node->label();
                    }
                }

                // Advance to the next page, or exit the loop if pagination is exhausted.
                $next_url = $data['pagination']['next_page_url'] ?? NULL;

                return [
                    'status'        => true,
                    'message'       => $next_url ? 'Continue... Loading...' : 'Completed',
                    'created_count' => $created_count,
                    'updated_count' => $updated_count,
                    'created_items' => $created_items,
                    'updated_items' => $updated_items,
                    'next_url'      => $next_url,
                ];
            }

            // 
            return [
                'status'        => false,
                'message'       => 'fail to receive data'
            ];
        } catch (\Throwable $th) {
            return [
                'status'  => false,
                'message' => $th->getMessage() . " at \"" . $th->getFile() . "\" : " . $th->getLine(),
            ];
        }
    }


    /**
     * Extracts media references from HTML, and rewrites the HTML so that
     * /sites/default/files/ paths point at the archive-old-site-files
     * subfolder where imported files are actually stored locally.
     *
     * @return array{html: string, media: array}
     */
    public static function extractDescriptionMedia(string &$html, string $baseurl): array {

        if (empty($html)) {
            return [];
        }

        $baseurl = rtrim($baseurl, '/');

        $dom = new \DOMDocument();
        libxml_use_internal_errors(TRUE);

        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        libxml_clear_errors();

        $media = [];

        // -------------------------
        // Normalize helper
        // -------------------------
        $normalize = function ($src) use ($baseurl) {
            if (empty($src)) {
                return null;
            }

            // remove baseurl if already present
            if (str_starts_with($src, $baseurl)) {
                $src = substr($src, strlen($baseurl));
            }

            $path = parse_url($src, PHP_URL_PATH) ?: $src;

            return $path;
        };

        // -------------------------
        // Extract images
        // -------------------------
        foreach ($dom->getElementsByTagName('img') as $img) {

            $src = $normalize($img->getAttribute('src'));

            if (!empty($src) && str_starts_with($src, '/sites/default/files/')) {

                $relative = substr($src, strlen('/sites/default/files/'));

                $media[] = [
                    'type' => 'image',
                    'url'  => $baseurl . $src,
                    'uri'  => 'public://' . urldecode($relative),
                    'alt'  => $img->getAttribute('alt') ?? '',
                    'title' => $img->getAttribute('title') ?? '',
                    'file_exist' => TRUE,
                ];
            }
        }

        // -------------------------
        // Extract files
        // -------------------------
        foreach ($dom->getElementsByTagName('a') as $a) {

            $href = $normalize($a->getAttribute('href'));

            if (!empty($href) && str_starts_with($href, '/sites/default/files/')) {

                $ext = strtolower(pathinfo($href, PATHINFO_EXTENSION));

                if (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'])) {

                    $relative = substr($href, strlen('/sites/default/files/'));

                    $media[] = [
                        'type' => 'file',
                        'url'  => $baseurl . $href,
                        'uri'  => 'public://' . urldecode($relative),
                        'title' => trim($a->nodeValue ?? ''),
                        'file_exist' => TRUE,
                    ];
                }
            }
        }

        // -------------------------
        // Rewrite ALL occurrences of /sites/default/files/ to point
        // at the local archive-old-site-files subfolder.
        // -------------------------
        $html = str_replace(
            '/sites/default/files/',
            '/sites/default/files/archive-old-site-files/',
            $html
        );

        // Remove all inline style attributes (e.g. style="margin-left:.25in;").
        $html = preg_replace('/\s*style=(["\']).*?\1/ui', '', $html);

        // Replace HTML non-breaking spaces (&nbsp;) and UTF-8 non-breaking space
        // characters (U+00A0) with regular spaces to preserve word spacing.
        $html = str_replace(['&nbsp;', "\xC2\xA0"], ' ', $html);

        return ['html' => $html, 'media' => $media];
    }

    /**
     * Imports a remote file into Drupal and returns a File entity.
     */
    public static function importRemoteFile(array $remote_file): ?\Drupal\file\FileInterface {

        $remote_file_url = $remote_file['url'] ?? NULL;
        $file_exist      = $remote_file['file_exist'] ?? FALSE;

        if (empty($remote_file_url) || !$file_exist) {
            return NULL;
        }

        $remote_uuid = $remote_file['uuid'] ?? NULL;
        $remote_uri  = $remote_file['uri'] ?? NULL;

        $filename = $remote_file['filename']
            ?? basename(parse_url($remote_file_url, PHP_URL_PATH));

        $file_storage = \Drupal::entityTypeManager()->getStorage('file');
        $file_system  = \Drupal::service('file_system');
        $http_client  = \Drupal::httpClient();
        $file = NULL;
        // -------------------------
        // 1. UUID check (fastest dedupe)
        // -------------------------
        if (!empty($remote_uuid)) {
            $existing = $file_storage->loadByProperties(['uuid' => $remote_uuid]);
            if (!empty($existing)) {
                $file = reset($existing);
            }
        }

        // -------------------------
        // 2. Prepare URI
        // -------------------------
        if ($remote_uri) {
            $remote_uri = preg_replace(
                '#^public://(?!archive-old-site-files/)#',
                'public://archive-old-site-files/',
                $remote_uri
            );
        }
        $uri = $remote_uri ?: 'public://archive-old-site-files/' . $filename;

        if (!$file) {
            $existing_by_uri = $file_storage->loadByProperties(['uri' => $uri]);
            if (!empty($existing_by_uri)) {
                $file = reset($existing_by_uri);
            }
        }

        // -------------------------
        // 3. If file exists, verify physical file (resolve stream wrapper first)
        // -------------------------
        if ($file) {
            /** @var \Drupal\file\FileInterface $file */
            $real_path = $file_system->realpath($file->getFileUri());

            if ($real_path && file_exists($real_path)) {
                return $file;
            }
        }

        try {

            // -------------------------
            // 4. Resolve destination + prepare directory BEFORE download
            //    (so we have a real path to stream into)
            // -------------------------
            $destination = $file_system->getDestinationFilename(
                $uri,
                \Drupal\Core\File\FileExists::Replace
            );

            if (!$destination) {
                \Drupal::logger('helperbox_import')->error(
                    'Could not resolve destination filename for @uri',
                    ['@uri' => $uri]
                );
                return NULL;
            }

            $directory = dirname($destination);

            $file_system->prepareDirectory(
                $directory,
                \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY |
                    \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
            );

            $real_destination = $file_system->realpath($destination);
            if (!$real_destination) {
                \Drupal::logger('helperbox_import')->error(
                    'Could not resolve real path for @dest',
                    ['@dest' => $destination]
                );
                return NULL;
            }

            // -------------------------
            // 5. Download directly to disk (streamed, low memory)
            // -------------------------
            $response = $http_client->request('GET', $remote_file_url, [
                'verify' => FALSE,
                'sink'   => $real_destination,
            ]);

            if ($response->getStatusCode() !== 200) {
                \Drupal::logger('helperbox_import')->error(
                    'File download failed @url with status @code',
                    [
                        '@url'  => $remote_file_url,
                        '@code' => $response->getStatusCode(),
                    ]
                );
                if (file_exists($real_destination)) {
                    @unlink($real_destination);
                }
                return NULL;
            }

            // -------------------------
            // 6. Sanity check: file actually landed on disk and isn't empty
            // -------------------------
            if (!file_exists($real_destination) || filesize($real_destination) === 0) {
                \Drupal::logger('helperbox_import')->error(
                    'Downloaded file is missing or empty: @dest',
                    ['@dest' => $real_destination]
                );
                return NULL;
            }

            // -------------------------
            // 7. Create or update file entity
            // -------------------------
            if ($file) {
                $file->set('uri', $destination);
                $file->set('status', 1);
                $file->save();

                return $file;
            }

            $file_values = [
                'uri'    => $destination,
                'status' => 1,
            ];
            if (!empty($remote_uuid)) {
                $file_values['uuid'] = $remote_uuid;
            }
            $file = File::create($file_values);
            $file->save();

            return $file;
        } catch (\Throwable $th) {

            \Drupal::logger('helperbox_import')->error(
                'File import failed @url | @msg | @file:@line',
                [
                    '@url'  => $remote_file_url,
                    '@msg'  => $th->getMessage(),
                    '@file' => $th->getFile(),
                    '@line' => $th->getLine(),
                ]
            );

            return NULL;
        }
    }


    /**
     * Imports a remote media item, creating it if it doesn't already exist.
     *
     * Downloads/imports the referenced remote file first, then looks for an
     * existing media entity of the given bundle that already references that
     * file (matched via the bundle's source field). If none is found, a new
     * media entity is created and saved.
     *
     * @param array $remote_file
     *   Data describing the remote file to import. Passed through to
     *   self::importRemoteFile().
     * @param array $remote_media
     *   Data describing the remote media item. Expected keys:
     *   - bundle: (string) The media type machine name. Required.
     *   - title: (string) Optional title/name and image "title" attribute.
     *   - alt: (string) Optional image "alt" attribute.
     *
     * @return \Drupal\media\MediaInterface|null
     *   The existing or newly created media entity, or NULL if the file
     *   couldn't be imported or no bundle was provided.
     */
    public static function importMediaItem(array $remote_file, array $remote_media): ?MediaInterface {
        $file = self::importRemoteFile($remote_file);
        if (!$file) {
            return NULL;
        }

        $media_bundle = $remote_media['bundle'] ?? '';
        if (!$media_bundle) {
            return NULL;
        }

        /** @var \Drupal\media\MediaTypeInterface|null $media_type */
        $media_type = \Drupal::entityTypeManager()
            ->getStorage('media_type')
            ->load($media_bundle);

        if (!$media_type) {
            return NULL;
        }

        // Correct way to get the source field config — no getSourceConfiguration().
        $source_field = $media_type->getSource()->getConfiguration()['source_field']
            ?? 'field_media_image';

        $media_storage = \Drupal::entityTypeManager()->getStorage('media');

        $existing_media = $media_storage->loadByProperties([
            'bundle' => $media_bundle,
            $source_field => $file->id(),
        ]);
        $media_entity = reset($existing_media) ?: NULL;

        if (!$media_entity) {
            $media_values = [
                'bundle' => $media_bundle,
                'name'   => $remote_media['title'] ?? $file->getFilename(),
                'status' => 1,
            ];
            $media_values[$source_field] = [
                'target_id' => $file->id(),
                'alt'       => $remote_media['alt'] ?? '',
                'title'     => $remote_media['title'] ?? '',
            ];

            $media_entity = \Drupal\media\Entity\Media::create($media_values);
            $media_entity->save();
        }

        return $media_entity;
    }
}
