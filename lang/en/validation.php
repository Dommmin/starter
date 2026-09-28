<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute field must be a valid email address.',
    'min' => [
        'string' => 'The :attribute must be at least :min characters.',
    ],
    'confirmed' => 'The :attribute field confirmation does not match.',
    'attributes' => [
        'name' => 'name',
        'email' => 'email',
        'password' => 'password',
        'admin_locale' => 'language',
    ],
    'navigation' => [
        'max_depth' => 'Menus have at most two levels: the parent must be a first-level item, and an item with children cannot become a child.',
        'parent_scope' => 'The parent must be a first-level item of the same menu and language.',
        'group_top_level' => 'A group heading can only be a first-level item.',
        'new_tab_external' => 'Only external links can open in a new tab.',
        'url_scheme' => 'Enter a full address starting with http:// or https://.',
    ],
];
