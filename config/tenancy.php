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

    /*
    |--------------------------------------------------------------------------
    | Domaines du Desk (management plateforme)
    |--------------------------------------------------------------------------
    |
    | Hôtes servant EXCLUSIVEMENT l'espace exploitant (Desk). Un client n'y a
    | jamais accès. En production : desk.vulkain.eu.
    |
    | Repli automatique si APP_DESK_DOMAIN n'est pas défini : les hôtes centraux
    | qui ne sont ni la vitrine ni l'application (évite de verrouiller le Desk
    | par erreur tant que la variable n'est pas renseignée).
    |
    */

    'desk_domains' => (static function (): array {
        $explicit = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('APP_DESK_DOMAIN', ''))
        )));

        if ($explicit !== []) {
            return $explicit;
        }

        $central = array_filter(array_map('trim', explode(',', (string) env('APP_CENTRAL_DOMAIN', 'localhost'))));
        $vitrine = array_filter(array_map('trim', explode(',', (string) env('APP_VITRINE_DOMAIN', ''))));
        $app = array_filter(array_map('trim', explode(',', (string) env('APP_APP_DOMAIN', ''))));

        return array_values(array_diff($central, $vitrine, $app));
    })(),

];
