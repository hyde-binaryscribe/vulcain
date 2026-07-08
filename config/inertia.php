<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Emplacement des pages Inertia
    |--------------------------------------------------------------------------
    |
    | Le dossier réel est « Pages » (majuscule) — le défaut du package pointe
    | vers « pages » (minuscule), ce qui casse la résolution sur un système de
    | fichiers sensible à la casse (Linux). On le corrige ici.
    */
    'pages' => [
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue', 'svelte'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vérification de l'existence des pages (tests)
    |--------------------------------------------------------------------------
    */
    'testing' => [
        'ensure_pages_exist' => true,
    ],
];
