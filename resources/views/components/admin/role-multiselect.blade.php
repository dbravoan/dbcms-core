@props([
    'name' => 'roles',
    'selected' => [],
    'label' => null,
    'asArray' => false,
    'help' => null,
    'roles' => null,
])

@php
    $label = $label ?? __('admin.roles.label');
    $roles = $roles ?? ($allRoles ?? collect());

    $roleMetadata = [
        'admin' => [
            'label' => __('admin.roles.admin_label'),
            'desc' => __('admin.roles.admin_desc'),
            'icon' => 'fa-solid fa-shield-halved',
            'badge' => 'ADMIN',
        ],
        'editor' => [
            'label' => __('admin.roles.editor_label'),
            'desc' => __('admin.roles.editor_desc'),
            'icon' => 'fa-solid fa-pen-to-square',
            'badge' => 'EDITOR',
        ],
        'author' => [
            'label' => __('admin.roles.author_label'),
            'desc' => __('admin.roles.author_desc'),
            'icon' => 'fa-solid fa-user-pen',
            'badge' => 'AUTHOR',
        ],
        'marketing' => [
            'label' => __('admin.roles.marketing_label'),
            'desc' => __('admin.roles.marketing_desc'),
            'icon' => 'fa-solid fa-bullhorn',
            'badge' => 'MARKETING',
        ],
        'viewer' => [
            'label' => __('admin.roles.viewer_label'),
            'desc' => __('admin.roles.viewer_desc'),
            'icon' => 'fa-solid fa-eye',
            'badge' => 'VIEWER',
        ],
    ];

    $options = $roles->map(function ($role) use ($roleMetadata) {
        $meta = $roleMetadata[$role->slug] ?? [
            'label' => $role->name ?: ucfirst($role->slug),
            'desc' => __('admin.roles.fallback_desc', ['slug' => $role->slug]),
            'icon' => 'fa-solid fa-id-badge',
            'badge' => strtoupper($role->slug),
        ];

        return [
            'value' => $role->slug,
            'label' => $meta['label'],
            'description' => $meta['desc'],
            'icon' => $meta['icon'],
            'badge' => $meta['badge'],
        ];
    })->values()->all();
@endphp

<x-admin.multiselect
    :name="$name"
    :options="$options"
    :selected="$selected"
    :label="$label"
    :asArray="$asArray"
    :help="$help"
    placeholder="{{ __('admin.roles.placeholder') }}"
/>
