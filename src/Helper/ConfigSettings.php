<?php

namespace Drupal\helperbox\Helper;

/**
 * Config Settings class
 *
 * @package Drupal\helperbox\Helper
 */
class ConfigSettings {

    /**
     * Rules for field access based on entity type and bundle.
     *
     * Example:
     * [
     *      'entity_type_...' => [
     *          'bundle_...' => [
     *              'field_access_check' => [
     *                  'field_...' => true|false,
     *              ],
     *          ],
     *      ]
     *  ]
     *
     * @var array<string, array<string, array{
     *     field_access_check?: array<string, bool>
     * }>>
     * 
     */
    public static $allfieldrules = [
        'node' => [
            'understanding_fimi' => [
                'field_access_check' => [
                    'field_related_countries' => false,
                ]
            ],
        ],
        'paragraph' => [
            'content_item' => [
                'field_access_check' => [
                    'field_list_items' => false,
                    'field_highlight_text' => false,
                    'field_file_upload' => false,
                ]
            ],
            'list_item' => [
                'field_access_check' => [
                    'field_description_2' => false,
                    'field_featured_image' => false,
                    'field_link' => false,
                ]
            ],
        ],

    ];

    /**
     * Field rules for specific content type and node ID.
     *
     * Example:
     * [
     *      'content_type_...' => [
     *          'node_id_...' => [
     *              'field_...', 
     *              'group_...', 
     *              [
     *                  'field_...' => true|false
     *              ],
     *              'referenceField' => [
     *                  'field_...' => true|false,
     *                  'field_...' => [
     *                      'field_...' => true|false,
     *                      'referenceField' => [
     *                          'field_...'=> true|false
     *                      ],
     *                  ],
     *              ],
     *          ],
     *      ]
     * ]
     *
     * @var array<string, array<int, array<string, mixed>>>
     * 
     */
    public static $nodefieldrules = [
        'understanding_fimi' => [
            16 => [
                'group_fimi_vs_disinformation',
                'referenceField' => [
                    'field_content_section' => [
                        'field_list_items' => false,
                    ]
                ]
            ],
            17 => [
                'group_rights_and_gender',
                'referenceField' => [
                    'field_content_section' => [
                        'field_description' => false,
                        'field_list_items' => true,
                        'referenceField' => [
                            'field_list_items' => [
                                'field_description' => true,
                            ]
                        ]
                    ],
                    'field_rights_and_gender_section' => [
                        'field_highlight_text' => true,
                    ]
                ]
            ],
            18 => [
                'group_enables_fimi',
                'group_incentivises_fimi',
                'group_block_understanding_fimi',
                [
                    'field_content_section' => false
                ],
                'referenceField' => [
                    'field_list_item' => [
                        'field_description_2' => true,
                        'field_featured_image' => true,
                    ],
                    'field_list_item_1' => [
                        'field_description_2' => true,
                        'field_featured_image' => true,
                    ]
                ]
            ],
            // Add more node IDs here…
        ],
        'country' => [
            '-1' => [
                'referenceField' => [
                    'field_list_item' => [
                        'field_featured_image' => true,
                    ],
                    'field_list_item_1' => [
                        'field_featured_image' => true,
                        'field_description' => false,
                    ],
                    'field_content_section' => [
                        'field_file_upload' => true,
                    ]
                ]
            ],
            // '35' => [
            //     'referenceField' => [
            //         'field_list_item' => [
            //             'field_featured_image' => true,
            //         ]
            //     ]
            // ]

        ]
        // Add more content types here…
    ];

    /**
     * 
     * Example:
     * [
     *  'form_id_...'=>[
     *      'field_...'=>true|false
     *  ]
     * ]
     */
    public static $formIdFieldsrules = [
        'search_form' => [
            'advanced' => false,
        ]
    ];

    /**
     * Maximum allowed nodes per content type.
     *
     * Example:
     * [
     *      'content_type_...' => Number,
     * ]
     * @var array<string, int>
     */
    public static $maxContentNodes = [
        'understanding_fimi' => 3,
        // 'article' => 4
    ];


    // END
}
