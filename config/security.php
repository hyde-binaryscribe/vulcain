<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connexion : blocage temporaire après échecs
    |--------------------------------------------------------------------------
    */
    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('LOGIN_DECAY_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Réinitialisation de mot de passe
    |--------------------------------------------------------------------------
    */
    'password_reset' => [
        'expires_minutes' => (int) env('PASSWORD_RESET_EXPIRES', 60),
        'throttle_seconds' => (int) env('PASSWORD_RESET_THROTTLE', 60),
    ],

];
