# Gestion Stock Relais

Application web de gestion d'inventaire et de stock multi-sites (materiel informatique) : suivi des materiels, emplacements, mouvements, attribution aux utilisateurs, import/export Excel.

Stack : Laravel 13 (PHP 8.4), MySQL/MariaDB, Blade + Tailwind, Laravel Excel.

## Developpement local (Docker)

PHP/Composer ne sont pas requis sur la machine : tout tourne dans Docker. Seul Node.js est utilise en local pour compiler les assets front (Tailwind/Vite).

```bash
# Construire l'image et demarrer MariaDB + l'app
docker compose up -d --build

# Installer les dependances PHP (premiere fois ou apres changement de composer.json)
docker compose run --rm app composer install

# Copier la config et generer la cle d'application (premiere fois uniquement)
cp .env.example .env
docker compose run --rm app php artisan key:generate

# Migrations + donnees de demo
docker compose run --rm app php artisan migrate --seed

# Assets front (en local, hors Docker)
npm install
npm run build      # ou `npm run dev` pour le rechargement a chaud
```

L'application est servie sur http://localhost:8000.

### Comptes de demonstration (mot de passe : `password`)

| Role | Email |
|---|---|
| Administrateur | admin@gestion-stock.local |
| Gestionnaire de stock | gestionnaire@gestion-stock.local |
| Technicien | technicien@gestion-stock.local |
| Consultation | consultation@gestion-stock.local |
| Auditeur | auditeur@gestion-stock.local |

**Penser a changer ou desactiver ces comptes avant toute mise en production.**

## Deploiement sur Plesk (sans acces SSH)

Le depot GitHub est connecte directement a Plesk via l'extension **Laravel Toolkit** et l'onglet **Git** du domaine ; aucun acces SSH n'est necessaire.

1. **Extension Laravel Toolkit** : installer/activer l'extension sur le domaine concerne, avec **PHP 8.4** selectionne comme version d'interpreteur.
2. **Racine applicative** : pointer le "Document Root" vers le dossier `public/` du depot (Laravel Toolkit le fait automatiquement une fois le projet detecte).
3. **Git** : dans l'onglet *Git* de Plesk, renseigner l'URL du depot GitHub (`git@github.com:<compte>/gestion-stock-relais.git` ou l'URL HTTPS avec un token d'acces personnel en remote `https://<token>@github.com/<compte>/gestion-stock-relais.git`), la branche de production (ex. `main`), puis activer le **deploiement automatique** a chaque push.
4. **Actions de deploiement additionnelles** (executees automatiquement par Plesk apres chaque `git pull`, sans shell manuel) :
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. **Variables d'environnement** : dans Laravel Toolkit, renseigner les valeurs de production directement dans l'UI Plesk (qui genere le fichier `.env` sur le serveur) en se basant sur `.env.example`. Ne jamais committer de `.env` reel.
   - `APP_KEY` : generer une fois avec `php artisan key:generate --show` (en local) et coller la valeur, ou utiliser le bouton dedie de Laravel Toolkit.
   - `DB_*` : renseigner avec la base MySQL/MariaDB creee dans Plesk.
   - `APP_ENV=production`, `APP_DEBUG=false`.
6. **Premier deploiement** : apres le premier pull, executer manuellement une fois (bouton "Deploy" de Laravel Toolkit, qui relance les actions ci-dessus) pour s'assurer que les migrations et le cache sont a jour.

A chaque nouveau commit pousse sur la branche de production, Plesk re-synchronise automatiquement le code et relance les actions de deploiement ci-dessus — aucune intervention SSH n'est requise.

### Configuration actuelle du serveur (mode degrade)

Le fonctionnement decrit ci-dessus est la cible du projet, mais il n'est pas actif sur le serveur actuel :

- **Mode de deploiement : Manuel.** Le code n'est mis en ligne qu'au clic sur **Deployer** (onglet *Deploiement* de Laravel Toolkit).
- **Etape 6 « Executer le script de deploiement » desactivee** (pas de droits pour l'activer, et le script n'est pas modifiable sans acces SSH). Plesk recupere le code et installe les dependances Composer/npm, mais **n'execute aucune commande `php artisan`**, donc ni `migrate` ni les caches. `npm run build` n'est pas lance non plus : les fichiers compiles de `public/build/` sont donc commites dans le depot, et il faut lancer `npm run build` en local puis commiter avant chaque deploiement qui modifie le CSS ou le JS.

Apres chaque deploiement qui ajoute une migration (nouveau fichier dans `database/migrations/`), lancer a la main dans l'onglet **Artisan** de Laravel Toolkit :

```bash
migrate --force
```

puis verifier avec `migrate:status` que toutes les migrations sont en « Ran ». Sans cela, les pages qui lisent les nouvelles tables renvoient une erreur 500 (`Base table or view not found` dans `storage/logs/laravel.log`).

En cas d'erreur 500 en production (`APP_DEBUG=false`, donc sans detail a l'ecran), recuperer `storage/logs/laravel.log` via le *Gestionnaire de fichiers* de Plesk ou le lien **Journaux** de Laravel Toolkit.

## Structure du modele de donnees

- `sites` / `emplacements` : hierarchie Site > Emplacement.
- `materiels` : un enregistrement par equipement physique (reference, type, marque, modele, numero de serie, condition_stock, statut, emplacement, utilisateur attribue, colonne `version` pour la detection de modifications concurrentes).
- `mouvements` : historique immuable de tous les changements (deplacement, attribution, retour en stock, mise en reparation).
- `imports` : journal des imports Excel/CSV (mode strict : tout ou rien).
- `users` : comptes applicatifs avec role (`administrateur`, `gestionnaire_stock`, `technicien`, `consultation`, `auditeur`).
