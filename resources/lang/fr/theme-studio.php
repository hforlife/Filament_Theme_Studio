<?php

return [
    'navigation' => ['label' => 'Studio de thème', 'group' => 'Apparence'],
    'model' => ['singular' => 'thème', 'plural' => 'thèmes'],
    'sections' => [
        'identity' => 'Identité', 'colors' => 'Couleurs générales', 'sidebar' => 'Barre latérale',
        'dark_mode' => 'Mode sombre', 'typography' => 'Typographie', 'shape' => 'Formes', 'layout' => 'Mise en page',
    ],
    'fields' => [
        'name' => 'Nom', 'slug' => 'Slug', 'panel' => 'Panel', 'status' => 'État', 'versions' => 'Versions',
        'updated_by' => 'Dernière modification par', 'updated_at' => 'Dernière modification', 'created_by' => 'Auteur',
        'created_at' => 'Créée le', 'version' => 'Version', 'change_note' => 'Note de modification',
        'activate_after_create' => 'Activer après la création',
        'primary' => 'Primaire', 'secondary' => 'Secondaire', 'success' => 'Succès', 'warning' => 'Avertissement',
        'danger' => 'Danger', 'background' => 'Arrière-plan', 'surface' => 'Surface', 'text' => 'Texte',
        'sidebar_background' => 'Fond de la barre latérale', 'sidebar_text' => 'Texte de la barre latérale', 'sidebar_width' => 'Largeur de la barre latérale',
        'font_family' => 'Famille de police', 'base_size' => 'Taille de base', 'border_radius' => 'Rayon général',
        'button_radius' => 'Rayon des boutons', 'input_radius' => 'Rayon des champs', 'card_radius' => 'Rayon des cartes',
        'content_max_width' => 'Largeur maximale du contenu', 'density' => 'Densité',
    ],
    'actions' => [
        'activate' => 'Activer', 'deactivate' => 'Désactiver', 'duplicate' => 'Dupliquer', 'delete' => 'Supprimer',
        'snapshot' => 'Créer un snapshot', 'restore' => 'Restaurer',
    ],
    'filters' => ['active' => 'État actif'],
    'badges' => ['active' => 'Actif', 'inactive' => 'Inactif'],
    'options' => ['full' => 'Pleine largeur', 'compact' => 'Compacte', 'comfortable' => 'Confortable', 'spacious' => 'Spacieuse'],
    'confirmations' => [
        'delete' => 'Ce thème et tout son historique de versions seront définitivement supprimés.',
        'restore' => 'L’état actuel sera automatiquement sauvegardé avant la restauration de cette version.',
    ],
    'notifications' => [
        'activated' => 'Thème activé', 'deactivated' => 'Thème désactivé', 'duplicated' => 'Thème dupliqué',
        'deleted' => 'Thème supprimé', 'snapshot_created' => 'Snapshot créé', 'restored' => 'Version restaurée',
    ],
    'errors' => ['duplicate_slug' => 'Ce slug est déjà utilisé par un autre thème de ce panel.'],
    'empty' => ['heading' => 'Aucun thème', 'description' => 'Créez un thème pour commencer à configurer ce panel.'],
    'versions' => ['heading' => 'Historique des versions'],
    'version_notes' => ['before_update' => 'Snapshot automatique avant modification'],
];
