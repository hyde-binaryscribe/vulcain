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

];
