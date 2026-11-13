#!/bin/bash
# Script de déploiement Plesk (Laravel Toolkit → Déploiement → « Modifier le script »).
# Exécuté à l'étape 6, après la récupération du code (étapes 2-3) et l'installation
# des dépendances Composer/npm (étapes 4-5), pendant que le site est en maintenance.
# Ce fichier est la référence : après toute modification, recopier son contenu dans Plesk.

# Arrêter le déploiement à la première commande en échec (au lieu de mettre en cache
# une application dont la base n'est pas à jour).
set -euo pipefail

# PHP 8.4 de Plesk : le "php" du PATH peut être une autre version, plus ancienne.
PHP=/opt/plesk/php/8.4/bin/php
if [ ! -x "$PHP" ]; then
    PHP=php
fi
echo "PHP utilisé : $($PHP -r 'echo PHP_VERSION;')"

if [ ! -f artisan ]; then
    echo "Fichier artisan introuvable dans $(pwd) : le script doit s'exécuter à la racine de l'application." >&2
    exit 1
fi

# 1. Appliquer les nouvelles migrations (--force : obligatoire en production, sinon Laravel demande confirmation).
$PHP artisan migrate --force

# 2. Reconstruire les caches (config, routes, vues, événements) à partir du nouveau code et du .env.
#    Les fichiers CSS/JS compilés (public/build/) sont commités : pas de npm run build ici.
$PHP artisan optimize:clear
$PHP artisan optimize

echo "Déploiement terminé."
