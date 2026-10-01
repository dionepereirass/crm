<?php

use Illuminate\Support\Str;

return [

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'bet_crm'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    'waits' => [
        'redis:webhooks' => 15,
        'redis:events' => 30,
        'redis:messages' => 30,
        'redis:emails' => 45,
        'redis:sms' => 45,
        'redis:campaigns' => 60,
        'redis:campaign_messages' => 60,
        'redis:automations' => 60,
        'redis:analytics' => 90,
        'redis:reports' => 120,
        'redis:default' => 60,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        //
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 128,

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => [
                'webhooks',
                'events',
                'messages',
                'emails',
                'sms',
                'campaigns',
                'campaign_messages',
                'automations',
                'analytics',
                'reports',
                'default',
            ],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 15,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 90,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 20,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
        ],
    ],

];
