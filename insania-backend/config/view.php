<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */

    // Each uploaded package has its own BUILD id, so pages saved by an older upload are never reused
    // (unzipping in File Manager can keep old file dates, which would hide a fix).
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views'.(is_file(base_path('BUILD')) ? '/'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) file_get_contents(base_path('BUILD'))) : ''))
    ),

];
