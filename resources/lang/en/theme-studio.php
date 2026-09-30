<?php

return [
    'navigation' => ['label' => 'Theme Studio', 'group' => 'Appearance'],
    'model' => ['singular' => 'theme', 'plural' => 'themes'],
    'sections' => [
        'identity' => 'Identity', 'colors' => 'General colors', 'sidebar' => 'Sidebar',
        'dark_mode' => 'Dark mode', 'typography' => 'Typography', 'shape' => 'Shapes', 'layout' => 'Layout',
    ],
    'fields' => [
        'name' => 'Name', 'slug' => 'Slug', 'panel' => 'Panel', 'status' => 'Status', 'versions' => 'Versions',
        'updated_by' => 'Last updated by', 'updated_at' => 'Last updated', 'created_by' => 'Author',
        'created_at' => 'Created at', 'version' => 'Version', 'change_note' => 'Change note',
        'activate_after_create' => 'Activate after creation',
        'primary' => 'Primary', 'secondary' => 'Secondary', 'success' => 'Success', 'warning' => 'Warning',
        'danger' => 'Danger', 'background' => 'Background', 'surface' => 'Surface', 'text' => 'Text',
        'sidebar_background' => 'Sidebar background', 'sidebar_text' => 'Sidebar text', 'sidebar_width' => 'Sidebar width',
        'font_family' => 'Font family', 'base_size' => 'Base size', 'border_radius' => 'General radius',
        'button_radius' => 'Button radius', 'input_radius' => 'Input radius', 'card_radius' => 'Card radius',
        'content_max_width' => 'Maximum content width', 'density' => 'Density',
    ],
    'actions' => [
        'activate' => 'Activate', 'deactivate' => 'Deactivate', 'duplicate' => 'Duplicate', 'delete' => 'Delete',
        'snapshot' => 'Create snapshot', 'restore' => 'Restore',
    ],
    'filters' => ['active' => 'Active status'],
    'badges' => ['active' => 'Active', 'inactive' => 'Inactive'],
    'options' => ['full' => 'Full width', 'compact' => 'Compact', 'comfortable' => 'Comfortable', 'spacious' => 'Spacious'],
    'confirmations' => [
        'delete' => 'This theme and its entire version history will be permanently deleted.',
        'restore' => 'The current state will be saved automatically before this version is restored.',
    ],
    'notifications' => [
        'activated' => 'Theme activated', 'deactivated' => 'Theme deactivated', 'duplicated' => 'Theme duplicated',
        'deleted' => 'Theme deleted', 'snapshot_created' => 'Snapshot created', 'restored' => 'Version restored',
    ],
    'errors' => ['duplicate_slug' => 'This slug is already used by another theme in this panel.'],
    'empty' => ['heading' => 'No themes yet', 'description' => 'Create a theme to begin configuring this panel.'],
    'versions' => ['heading' => 'Version history'],
    'version_notes' => ['before_update' => 'Automatic snapshot before update'],
];
