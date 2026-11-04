<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;

/**
 * Whitelist registry for the reusable AJAX searchable select.
 *
 * Never accept raw table names from the browser: only the keys declared here
 * can be queried. Add a source to make every `<x-admin.search-select>` able
 * to search it system-wide.
 *
 * Keys per source:
 *  - model          Eloquent model class.
 *  - value          Candidate attribute paths for the submitted value (first non-empty wins).
 *  - label          Candidate attribute paths for the visible label.
 *  - search         Attribute paths matched against the query (supports `translation.<field>`).
 *  - allowed_values Extra value paths a caller may request via `value-key` (security allow-list).
 *  - filters        Column => value conditions.
 *  - scope          Optional Eloquent scope name (e.g. `published`, `active`).
 *  - order          Order column (defaults to primary key).
 *  - icon / badge   UI metadata returned with every option.
 */
return [
    'per_page' => 20,
    'max_per_page' => 50,

    'sources' => [
        'markets' => [
            'permission' => 'access:admin',
            'model' => Market::class,
            'value' => ['code'],
            'allowed_values' => ['code', 'id'],
            'label' => ['name'],
            'search' => ['name', 'code'],
            'filters' => ['is_active' => true],
            'order' => 'name',
            'icon' => 'fa-solid fa-globe',
            'badge' => 'MARKET',
        ],

        'languages' => [
            'permission' => 'access:admin',
            'model' => Language::class,
            'value' => ['code'],
            'label' => ['name'],
            'search' => ['name', 'code', 'native_name'],
            'filters' => ['is_active' => true],
            'order' => 'name',
            'icon' => 'fa-solid fa-language',
            'badge' => 'LANG',
        ],

        'users' => [
            'permission' => 'manage:users',
            'model' => User::class,
            'value' => ['id'],
            'label' => ['name'],
            'search' => ['name', 'email'],
            'order' => 'name',
            'icon' => 'fa-solid fa-user',
            'badge' => 'USER',
        ],

        'roles' => [
            'permission' => 'manage:users',
            'model' => Role::class,
            'value' => ['id'],
            'label' => ['name'],
            'search' => ['name', 'slug'],
            'order' => 'name',
            'icon' => 'fa-solid fa-user-shield',
            'badge' => 'ROLE',
        ],

        'pages' => [
            'permission' => 'manage:pages',
            'model' => Page::class,
            'value' => ['id'],
            'label' => ['translation.title'],
            'search' => ['translation.title', 'translation.slug'],
            'market_scoped' => true,
            'order' => 'id',
            'icon' => 'fa-solid fa-file-lines',
            'badge' => 'PAGE',
        ],

    ],
];
