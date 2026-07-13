<?php

use Spatie\Prometheus\Actions\RenderCollectorsAction;
use Spatie\Prometheus\Http\Middleware\AllowIps;

return [
    'enabled' => true,

    'urls' => [
        'default' => 'prometheus',
    ],

    'allowed_ips' => [],

    'default_namespace' => 'mediscan',

    'middleware' => [
        AllowIps::class,
    ],

    'actions' => [
        'render_collectors' => RenderCollectorsAction::class,
    ],

    'wipe_storage_after_rendering' => false,

    'cache' => null,
];
