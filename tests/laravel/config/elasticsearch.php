<?php

return [
    'indices' => [
        'mappings' => [
            'default' => [
                'properties' => [
                    'created_at' => [
                        'type' => 'date',
                    ],
                    'updated_at' => [
                        'type' => 'date',
                    ],
                    'deleted_at' => [
                        'type' => 'date',
                    ],
                ],
            ],
        ],
        'settings' => [
            'default' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ],
    ],
];
