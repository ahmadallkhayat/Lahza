<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sidebar Navigation
    |--------------------------------------------------------------------------
    |
    | The admin sidebar is built from this array — adding a section is one
    | entry here, not an edit to the layout. `active` is a route pattern
    | passed to Route::is(), so child routes highlight their parent.
    |
    | Icons are heroicon outline names, rendered as SVG. No emoji.
    |
    */

    'nav' => [
        [
            'heading' => null,
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'admin.dashboard',
                    'icon' => 'squares-2x2',
                    'active' => 'admin.dashboard',
                ],
            ],
        ],
        [
            'heading' => 'Studio',
            'items' => [
                [
                    'label' => 'Templates',
                    'route' => 'admin.templates.index',
                    'icon' => 'swatch',
                    'active' => 'admin.templates.*',
                ],
                [
                    'label' => 'Assets',
                    'route' => 'admin.assets.index',
                    'icon' => 'rectangle-stack',
                    'active' => 'admin.assets.*',
                ],
                [
                    'label' => 'Invitations',
                    'route' => 'admin.invitations.index',
                    'icon' => 'envelope-open',
                    'active' => 'admin.invitations.*',
                ],
                [
                    'label' => 'Customers',
                    'route' => 'admin.customers.index',
                    'icon' => 'users',
                    'active' => 'admin.customers.*',
                ],
            ],
        ],
        [
            'heading' => 'Settings',
            'items' => [
                [
                    'label' => 'Site',
                    'route' => 'admin.settings.edit',
                    'icon' => 'globe-alt',
                    'active' => 'admin.settings.*',
                ],
                [
                    'label' => 'Account',
                    'route' => 'admin.account.edit',
                    'icon' => 'user-circle',
                    'active' => 'admin.account.*',
                ],
            ],
        ],
    ],

];
