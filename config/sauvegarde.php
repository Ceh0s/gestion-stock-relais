<?php

/*
 * Sauvegardes de la base (Algorithme §16). Toutes les valeurs viennent du .env :
 * ne jamais appeler env() ailleurs que dans config/, sinon config:cache les ignore.
 */
return [

    // Binaires du client MySQL/MariaDB (chemin complet si absents du PATH du serveur).
    'mysqldump' => env('SAUVEGARDE_MYSQLDUMP', 'mysqldump'),
    'mysql' => env('SAUVEGARDE_MYSQL', 'mysql'),

    // Dossier des sauvegardes, relatif à storage/app/private (jamais accessible depuis le web).
    'dossier' => 'sauvegardes',

    // Sauvegardes plus anciennes supprimées à chaque nouvelle sauvegarde.
    'conservation_jours' => (int) env('SAUVEGARDE_CONSERVATION_JOURS', 30),

    // Disque de config/filesystems.php vers lequel copier chaque sauvegarde (vide = pas de copie externe).
    'disque_externe' => env('SAUVEGARDE_DISQUE_EXTERNE'),

    // Base de test dans laquelle `php artisan sauvegarde:verifier` restaure une sauvegarde.
    // Les valeurs vides reprennent celles de la base principale (hôte, port, utilisateur, mot de passe).
    'base_test' => [
        'host' => env('SAUVEGARDE_TEST_DB_HOST'),
        'port' => env('SAUVEGARDE_TEST_DB_PORT'),
        'database' => env('SAUVEGARDE_TEST_DB_DATABASE'),
        'username' => env('SAUVEGARDE_TEST_DB_USERNAME'),
        'password' => env('SAUVEGARDE_TEST_DB_PASSWORD'),
    ],

];
