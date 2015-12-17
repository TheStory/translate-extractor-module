<?php

/**
 * Generated by ZF2ModuleCreator
 */

namespace TranslateExtractor;

return [
    'controllers' => [
        'invokables' => [
            __NAMESPACE__ . '\Controller\Index' => __NAMESPACE__ . '\Controller\IndexController',
        ],
    ],

    'service_manager' => [

    ],

    'console' => [
        'router' => [
            'routes' => [
                'extract_translations' => [
                    'options' => [
                        'route' => 'translations extract',
                        'defaults' => [
                            'controller' => __NAMESPACE__ . '\Controller\Index',
                            'action' => 'extract',
                        ],
                    ],
                ],
                'update_translations' => [
                    'options' => [
                        'route' => 'translations update <lang> <locale>',
                        'defaults' => [
                            'controller' => __NAMESPACE__ . '\Controller\Index',
                            'action' => 'update',
                        ],
                    ],
                ],
            ],
        ],
    ],

    'translate_extractor' => [
        'translations_path' => 'data/languages',
        'poeditor' => [
            'token' => '',
            'project_id' => '',
        ],
    ],
];