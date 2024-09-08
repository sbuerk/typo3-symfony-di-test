<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'POC: Symfony DI pattern tests',
    'description' => 'Provides proof-of-concept implementation for Symfony DI techniques',
    'category' => 'plugin',
    'author' => 'Stefan Bürk',
    'author_email' => 'stefan@buerk.tech',
    'state' => 'stable',
    'version' => '0.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
            'backend' => '13.0.0-13.99.99',
            'frontend' => '13.0.0-13.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
