@props([
    'name' => 'entities',
    'selected' => [],
    'label' => null,
    'asArray' => false,
    'help' => null,
])

@php
    $label = $label ?? __('admin.entities.label');

    $entities = [
        [
            'value' => 'posts',
            'label' => __('admin.entities.posts_label'),
            'description' => __('admin.entities.posts_desc'),
            'icon' => 'fa-solid fa-newspaper',
            'badge' => 'POSTS',
        ],
        [
            'value' => 'pages',
            'label' => __('admin.entities.pages_label'),
            'description' => __('admin.entities.pages_desc'),
            'icon' => 'fa-solid fa-file-lines',
            'badge' => 'PAGES',
        ],
        [
            'value' => 'products',
            'label' => __('admin.entities.products_label'),
            'description' => __('admin.entities.products_desc'),
            'icon' => 'fa-solid fa-bag-shopping',
            'badge' => 'SHOP',
        ],
        [
            'value' => 'forms',
            'label' => __('admin.entities.forms_label'),
            'description' => __('admin.entities.forms_desc'),
            'icon' => 'fa-solid fa-clipboard-list',
            'badge' => 'FORMS',
        ],
        [
            'value' => 'galleries',
            'label' => __('admin.entities.galleries_label'),
            'description' => __('admin.entities.galleries_desc'),
            'icon' => 'fa-solid fa-images',
            'badge' => 'GALLERY',
        ],
    ];
@endphp

<x-admin.multiselect
    :name="$name"
    :options="$entities"
    :selected="$selected"
    :label="$label"
    :asArray="$asArray"
    :help="$help"
    placeholder="{{ __('admin.entities.placeholder') }}"
/>
