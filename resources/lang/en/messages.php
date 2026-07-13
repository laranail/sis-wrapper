<?php

declare(strict_types=1);

return [
    'notifications' => [
        'serial_capacity' => [
            'subject' => 'SIS: a serial space is nearing exhaustion',
            'usage' => ':where is :percent% through its serial space.',
            'advice' => 'Widen the serial width — widening is always safe; narrowing is forbidden (SIM-STD-0001:2026 §2, §10).',
        ],
    ],

    'problem' => [
        '403' => 'Forbidden',
        '409' => 'Conflict',
        '422' => 'Unprocessable Entity',
        '500' => 'Register Integrity Failure',
        '507' => 'Insufficient Storage',
        'default' => 'Bad Request',
    ],

    'commands' => [
        'install' => [
            'installing' => 'Installing the Simtabi Identifier System...',
        ],

        'doctor' => [
            'schema_present' => 'schema present',
            'schema_missing' => 'schema missing tables: :tables',
            'triggers_supported' => "storage-layer triggers supported on driver ':driver'",
            'triggers_unsupported' => "[WARN] storage-layer immutability is NOT enforced on driver ':driver' — not for production",
            'sample_clean' => 'no check-character failures in the sample',
            'sample_corrupt' => 'corrupt identifiers: :identifiers',
            'aliases_resolve' => 'every stored subject alias resolves',
            'aliases_unknown' => 'unknown morph aliases in the register: :aliases',
            'outbox_drained' => 'outbox drained',
            'outbox_pending' => '[WARN] :count outbox message(s) pending relay',
            'capacity_headroom' => 'capacity headroom across all spaces',
            'capacity_nearing' => '[WARN] :class:scope is :percent% through its serial space',
            'headless' => 'headless — no admin panel detected (JSON API and facade are the integration surface)',
            'panels' => 'admin panel(s) available for the register presenter: :panels',
        ],

        'permissions' => [
            'resolver' => 'Resolver: <info>:resolver</info>',
            'ability' => '  :ability <comment>(:label)</comment>',
            'ability_description' => '      :description',
            'ability_actor' => '  :decision :ability <comment>(:label)</comment>',
        ],
    ],
];
