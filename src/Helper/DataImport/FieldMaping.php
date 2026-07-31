<?php

namespace Drupal\helperbox\Helper\DataImport;

class FieldMaping {



    /**
     * Returns the taxonomy vocabulary machine name mapping between the local
     * site and the remote (source) site.
     *
     * Keys are local vocabulary machine names; values are the corresponding
     * remote vocabulary machine names used in the source Drupal site.
     *
     * @return array<string, string>
     *   Associative array of local => remote taxonomy vocabulary machine names.
     */
    public static function taxonomy_field_maping() {
        // remote taxo machine name ==> current tax machine name. 
        $taxonomy_vid = [
            'department_center' => 'department',
            'suchi_darta' => 'suchi_darta_category',
            'journal_stage' => 'journal_status',
            'notices_type' => 'notices_category',
            'resources_type' => 'resources_category',
            'people_type' => 'team_category',
        ];

        return $taxonomy_vid;
    }


    /**
     * Returns the node type and field mapping configuration between the local
     * site and the remote (source) site.
     *
     * Each top-level key is a local node type machine name. Each entry
     * contains:
     *  - 'local_node_type': The corresponding node type machine name on the
     *    remote site.
     *  - 'fields': An associative array mapping local field machine names to
     *    their remote equivalents.
     *
     * @return array<string, array{remote_node_type: string, fields: array<string, string>}>
     *   Nested mapping of local node types and their field definitions.
     */
    public static function node_field_maping() {
        // remote machine name ==> current machine name. 
        $node_type_field_maping = [
            'suchi_darta' => [
                'local_node_type' => 'suchi_darta',
                'fields' => [
                    'field_org_name_np'                 => 'field_text_1',
                    'field_registered_addr'             => 'field_text_2',
                    'field_mailing_addr'                => 'field_mailing_address',
                    'field_org_owner_name'              => 'field_text_3',
                    'field_phone_number'                => 'field_phone_number',
                    'field_org_mobile'                  => 'field_mobile_number',
                    'field_org_email'                   => 'field_email',
                    'field_category_to_register'        => 'field_suchi_darta_category',
                    'field_company_regis_certificate'   => 'field_company_regis_certif',
                    'field_tax_regis_certificate'       => 'field_tax_regis_certificate',
                    'field_tax_clearance_certificate'   => 'field_tax_clearance_certificate',
                    'field_tax_clearance_certif_extt'   => 'field_letter_of_tax_deadline',
                    'field_license_copies'              => 'field_license_copies',
                    'field_auth_dealers_certificate'    => 'field_auth_dealers_certificate',
                    'field_if_other_docs'               => 'field_if_other_docs',
                ],
            ],
            'news' => [
                'local_node_type' => 'update',
                'fields' => [
                    'body' => 'body',
                    'field_cover_image' => [
                        'field_type' => 'entity_reference',
                        'field_name' => 'field_featured_image',
                        'entity_type' => 'media',
                        "bundle" => "image"
                    ]
                ],
                'default_field_value' => [
                    'field_update_category' => [
                        ['target_id' => '15']
                    ]
                ]
            ],
            'events' => [
                'local_node_type' => 'update',
                'fields' => [
                    'body' => 'body',
                    'field_cover_image' => [
                        'field_type' => 'entity_reference',
                        'field_name' => 'field_featured_image',
                        'entity_type' => 'media',
                        "bundle" => "image"
                    ],
                    'field_event_date' => [
                        'field_type' => 'date_range',
                        'date_only' => true,
                        'date_time_both' => false,
                        'field_name' => 'field_date_range',
                        'time_field_name' => 'field_time'
                    ]
                ],
                'default_field_value' => [
                    'field_update_category' => [
                        ['target_id' => '314']
                    ]
                ]
            ],
            'nasc_discussion' => [
                'local_node_type' => 'update',
                'default_field_value' => [
                    'field_update_category' => [
                        ['target_id' => '314']
                    ],
                    'field_update_events_category' => [
                        ['target_id' => '316']
                    ]
                ],
                'fields' => [
                    'body' => 'body',
                    'field_image' => [
                        'field_type' => 'entity_reference',
                        'field_name' => 'field_featured_image',
                        'entity_type' => 'media',
                        "bundle" => "image"
                    ]
                ],

            ],
            'journal' => [
                'local_node_type' => 'journals',
                'fields' => [
                    'body' => 'body',
                    'field_upload_file' => [
                        'field_type' => 'entity_reference',
                        'field_name' => 'field_file_upload',
                        'entity_type' => 'media',
                        "bundle" => "document"
                    ],
                    'field_published_date' => [
                        'field_type' => 'date_only',
                        'field_name' => 'field_date',
                    ],
                    'field_volume' => 'field_text_1',
                    'field_pages' => 'field_text_2',
                    'field_journal_status' => 'field_journal_status',
                ],
            ],
            'notices' => [
                'local_node_type' => 'notices',
                'fields' => [
                    'body' => 'body',
                    'field_notice_type' => 'field_notices_category',
                    'field_upload_file' => [
                        'field_type' => 'entity_reference',
                        'field_name' => 'field_file_upload',
                        'entity_type' => 'media',
                        "bundle" => "document"
                    ],
                ],
            ],
            'resoures' => [
                'local_node_type' => 'resources',
                'fields' => [
                    'body' => 'body',
                    'field_cover_image' => [
                        'field_name' => 'field_featured_image',
                        'field_type' => 'entity_reference',
                        'entity_type' => 'media',
                        "bundle" => "image"
                    ],
                    'field_upload_file' => [
                        'field_type' => 'entity_reference',
                        'field_name' => 'field_file_upload',
                        'entity_type' => 'media',
                        "bundle" => "document"
                    ],
                    'field_resources_type' => 'field_resources_category'
                ],
            ],
            'people' => [
                'local_node_type' => 'team',
                'fields' => [
                    'body' => 'body',
                    'field_profile_picture' => [
                        'field_name' => 'field_featured_image',
                        'field_type' => 'entity_reference',
                        'entity_type' => 'media',
                        "bundle" => "image"
                    ],
                    'field_designation' => 'field_designation',
                    'field_associated_organization' => 'field_associated_organization',
                    'field_area_of_specialization' => 'field_area_of_specialization',
                    'field_education' => 'field_education',
                    'field_department' => 'field_department',
                    'field_people_type' => 'field_team_category',
                ],
            ]
        ];

        return $node_type_field_maping;
    }
}
