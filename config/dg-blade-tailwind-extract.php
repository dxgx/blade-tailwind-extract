<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CSS Output Path
    |--------------------------------------------------------------------------
    |
    | The path where the extracted CSS file will be generated. This file
    | contains all the @apply rules for your extracted Tailwind classes.
    |
    */
    'css_output_path' => resource_path('css/tw-extracted.css'),

    /*
    |--------------------------------------------------------------------------
    | Class Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used for generated CSS class names. Default is "TW".
    | Generated classes follow the format: {prefix}-{hash}-{name}
    | Example: TW-a40f-card-wrapper
    |
    */
    'class_prefix' => 'TW',

    /*
    |--------------------------------------------------------------------------
    | Hash Length
    |--------------------------------------------------------------------------
    |
    | The number of characters to use for the file hash in class names.
    | This hash helps avoid conflicts between files. Default is 4.
    |
    */
    'hash_length' => 4,

    /*
    |--------------------------------------------------------------------------
    | Default Search Path
    |--------------------------------------------------------------------------
    |
    | The default directory to search for Blade files when no specific
    | path is provided to the command.
    |
    */
    'search_path' => resource_path('views'),

    /*
    |--------------------------------------------------------------------------
    | Ignored Directories
    |--------------------------------------------------------------------------
    |
    | List of directories to skip during file scanning. These paths are
    | relative to the project root.
    |
    */
    'ignored_directories' => [
        './vendor/',
        'vendor/',
        './node_modules/',
        'node_modules/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved Classes
    |--------------------------------------------------------------------------
    |
    | Tailwind classes that should never be extracted because they use
    | parent-child selectors. Classes containing these will be skipped
    | with a warning.
    |
    */
    'reserved_classes' => [
        'group',
        'peer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Iterations
    |--------------------------------------------------------------------------
    |
    | Safety limit for extraction loops to prevent infinite loops when
    | processing nested class patterns.
    |
    */
    'max_iterations' => 10,
];
