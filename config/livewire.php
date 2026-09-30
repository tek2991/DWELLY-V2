<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Class Namespace
    |--------------------------------------------------------------------------
    |
    | This value sets the root class namespace for Livewire component classes in
    | your application. This value will change where component auto-discovery
    | finds components. It's also referenced by the file creation commands.
    |
    */

    'class_namespace' => 'App\\Livewire',

    /*
    |--------------------------------------------------------------------------
    | View Path
    |--------------------------------------------------------------------------
    |
    | This value is used to specify where Livewire component Blade templates are
    | stored when running file creation commands like `artisan make:livewire`.
    | It is also used if you choose to omit a component's render() method.
    |
    */

    'view_path' => resource_path('views/livewire'),

    /*
    |--------------------------------------------------------------------------
    | Payload Guards
    |--------------------------------------------------------------------------
    |
    | These settings protect against malicious or oversized payloads that could
    | cause denial of service. For rich TipTap legal contract ASTs, nesting
    | depth must be raised to prevent MaxNestingDepthExceededException.
    |
    */

    'payload' => [
        'max_size' => 1024 * 1024 * 10,   // 10MB - maximum request payload size in bytes
        'max_nesting_depth' => 100,       // Maximum depth of dot-notation property paths
        'max_calls' => 100,               // Maximum method calls per request
        'max_components' => 300,          // Maximum components per batch request
    ],

];
