<?php

return array(
    'base_paths' => array(
        base_path() . '/workbench',
        base_path() . '/vendor',
        base_path() . '/app/assets',
    ),
    'minify' => true,
    'minify_patterns' => array('-min.', '.min.'),
    'combine' => false,
    'combined_styles' => 'application.css',
    'combined_scripts' => 'application.js',
);
