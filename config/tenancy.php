<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domaines centraux (plateforme)
    |--------------------------------------------------------------------------
    |
    | Requêtes servies sur ces domaines = espace plateforme / exploitant
    | (aucun tenant résolu). Toute autre entrée est traitée comme un
    | sous-domaine d'organisation : « caserne » dans « caserne.vulcain.app ».
    |
    | Plusieurs domaines possibles, séparés par des virgules dans .env.
    |
    */

    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('APP_CENTRAL_DOMAIN', 'localhost'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Domaines de la vitrine publique
    |--------------------------------------------------------------------------
    |
    | Hôtes centraux servant le site vitrine (marketing) plutôt que le Desk.
    | En production : vulkain.eu, www.vulkain.eu. Doivent aussi figurer dans
    | central_domains (ce sont des hôtes sans tenant).
    |
    */

    'vitrine_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('APP_VITRINE_DOMAIN', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Domaines de l'application (entrée tenant)
    |--------------------------------------------------------------------------
    |
    | Hôtes servant l'entrée applicative (login / inscription). En production :
    | app.vulkain.eu. Sert notamment à construire les liens de la vitrine.
    |
    */

    'app_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('APP_APP_DOMAIN', ''))
    ))),

];
