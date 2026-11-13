#!/bin/bash
# Script de deploiement Plesk (Laravel Toolkit, onglet Deploiement, bouton "Modifier le script").
# Execute a l'etape 6, apres la recuperation du code et l'installation des dependances
# Composer/npm, pendant que le site est en maintenance.
# Ce fichier est la reference : apres toute modification, recopier son contenu dans Plesk.
# ATTENTION : Plesk stocke ce script dans du XML. Ne pas utiliser les caracteres
# "inferieur", "superieur" ni "et commercial" (pas de redirection ni d enchainement), ni d accents.

# Arreter le deploiement a la premiere commande en echec.
set -euo pipefail

# PHP 8.4 de Plesk : le "php" du PATH peut etre une version plus ancienne.
PHP=/opt/plesk/php/8.4/bin/php
if [ ! -x "$PHP" ]; then
    PHP=php
fi
echo "PHP utilise : $($PHP -r 'echo PHP_VERSION;')"

if [ ! -f artisan ]; then
    echo "Fichier artisan introuvable dans $(pwd) : le script doit s executer a la racine de l application."
    exit 1
fi

# 1. Appliquer les nouvelles migrations (--force obligatoire en production).
$PHP artisan migrate --force

# 2. Reconstruire les caches (config, routes, vues, evenements).
#    Les fichiers CSS/JS compiles (public/build/) sont commites : pas de npm run build ici.
$PHP artisan optimize:clear
$PHP artisan optimize

echo "Deploiement termine."
