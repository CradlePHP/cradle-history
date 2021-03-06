<?php //-->
return [
    'disable' => 1,
    'singular' => 'History',
    'plural' => 'History',
    'name' => 'history',
    'group' => 'Users',
    'icon' => 'fas fa-history',
    'detail' => 'Generic history designed to log all activities on the system.',
    'fields' => [
        [
            'disable' => 1,
            'label' => 'Activity',
            'name' => 'activity',
            'field' => [
                'type' => 'textarea'
            ],
            'validation' => [
                [
                    'method' => 'required',
                    'message' => 'Activity is Required'
                ],
                [
                    'method' => 'empty',
                    'message' => 'Cannot be empty'
                ]
            ],
            'list' => [
                'format' => 'none'
            ],
            'detail' => [
                'format' => 'none'
            ],
            'default' => '',
            'searchable' => '1',
            'disable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Page',
            'name' => 'page',
            'field' => [
                'type' => 'textarea'
            ],
            'validation' => [
                [
                    'method' => 'required',
                    'message' => 'Page is Required'
                ],
                [
                    'method' => 'empty',
                    'message' => 'Cannot be empty'
                ]
            ],
            'list' => [
                'format' => 'hide'
            ],
            'detail' => [
                'format' => 'hide'
            ],
            'default' => '',
            'searchable' => '1',
            'disable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Path',
            'name' => 'path',
            'field' => [
                'type' => 'text'
            ],
            'list' => [
                'format' => 'hide'
            ],
            'detail' => [
                'format' => 'hide'
            ],
            'default' => '',
            'disable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Type',
            'name' => 'type',
            'field' => [
                'type' => 'text'
            ],
            'list' => [
                'format' => 'hide'
            ],
            'detail' => [
                'format' => 'hide'
            ],
            'default' => '',
            'disable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Table Name',
            'name' => 'table_name',
            'field' => [
                'type' => 'text'
            ],
            'list' => [
                'format' => 'hide'
            ],
            'detail' => [
                'format' => 'lower'
            ],
            'default' => '',
            'filterable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Table ID',
            'name' => 'table_id',
            'field' => [
                'type' => 'text'
            ],
            'list' => [
                'format' => 'hide'
            ],
            'detail' => [
                'format' => 'none'
            ],
            'default' => '',
            'filterable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Flag',
            'name' => 'flag',
            'field' => [
                'type' => 'small'
            ],
            'validation' => [
                [
                    'method' => 'one',
                    'parameters' => [     '1', '0'
                    ],
                    'message' => 'Flag should be specified.'
                ]
            ],
            'list' => [
                'format' => 'hide'
            ],
            'detail' => [
                'format' => 'hide'
            ],
            'default' => '0',
            'disable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Active',
            'name' => 'active',
            'field' => [
                'type' => 'active'
            ],
            'list' => [
                'format' => 'hide'
            ],
            'detail' => [
                'format' => 'hide'
            ],
            'default' => '1',
            'sortable' => '1',
            'disable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Created',
            'name' => 'created',
            'field' => [
                'type' => 'created'
            ],
            'list' => [
                'format' => 'relative'
            ],
            'detail' => [
                'format' => 'relative'
            ],
            'default' => 'NOW()',
            'sortable' => '1',
            'disable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Updated',
            'name' => 'updated',
            'field' => [
                'type' => 'updated'
            ],
            'list' => [
                'format' => 'hide'
            ],
            'detail' => [
                'format' => 'hide'
            ],
            'default' => 'NOW()',
            'sortable' => '1',
            'disable' => '1'
        ],
        [
            'disable' => 1,
            'label' => 'Remote Address',
            'name' => 'remote_address',
            'field' => [
                'type' => 'textarea'
            ],
            'validation' => [
                [
                    'method' => 'required',
                    'message' => 'Remote Address is Required'
                ],
                [
                    'method' => 'empty',
                    'message' => 'Cannot be empty'
                ]
            ],
            'list' => [
                'format' => 'none'
            ],
            'detail' => [
                'format' => 'none'
            ],
            'default' => '',
            'searchable' => '1',
            'disable' => '1'
        ]
    ],
    'relations' => [
        [
            'disable' => 1,
            'many' => '1',
            'name' => 'profile'
        ]
    ],
    'suggestion' => '{{profile_name}}'
];
