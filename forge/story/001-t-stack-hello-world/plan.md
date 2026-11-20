# Plan technique — Amorcer la stack technique avec un hello world

> **But** : figer comment le dépôt passe de zéro manifeste à une application qui démarre, se teste et se contrôle en une commande, sur les versions cibles.
> **Registre** : technique
> **Story** : `forge/story/001-t-stack-hello-world/`
> **Amont** : aucun
> **ADR** : `forge/adr/0001-runtime-natif-nix-paquets-maison.md`
> **ADR** : `forge/adr/0002-selection-versions-nixpkgs-multiverse.md`

## Motivation

Le dépôt ne contient que `AGENTS.md`, `CLAUDE.md` et `forge/`. Il n'y a ni `composer.json`, ni `flake.nix`, ni tâche castor, et aucun des outils de la stack
(`php`, `composer`, `castor`, `mago`) n'est sur le PATH de la machine. Aucune des features listées dans `forge/vision.md` ne peut démarrer, et `forge/stack.md`
ne décrit que des intentions, sans une seule preuve fichier.

Les versions cibles ne sont pas disponibles prêtes à l'emploi, ce qui interdit de se contenter d'un gabarit existant :

- PHP 8.6.0 est sorti le 2026-11-19. Aucune image `dunglas/frankenphp` ne porte de tag `php8.6` (dernier : `1.13.6-php8.5`).
- nixpkgs compile `frankenphp` 1.13.5 contre son PHP par défaut (8.5.12). La montée `php86` 8.6.0RC4 vers 8.6.0 est fusionnée sur `master`
  (PR 590480) mais pas encore sur `nixos-unstable`.
- Symfony 8.2 est en `v8.2.0-BETA2` (2026-11-13), stable attendue fin novembre 2026.

**Pourquoi maintenant** : c'est le prérequis de toute story fonctionnelle. Amorcer directement sur PHP 8.6 et Symfony 8.2 évite une montée de version
dans les jours qui suivent le premier commit applicatif.

## Approche retenue

Le développement tourne en natif dans un shell Nix. `flake.nix` a une seule source de paquets, `nixpkgs-multiverse`, qui sélectionne chaque outil par
numéro de version lisible dans le fichier plutôt que par commit de nixpkgs. Deux paquets maison, rangés dans `infra/packages/`, fournissent le runtime :
un PHP 8.6 ZTS embarquable limité aux extensions que l'application exige, et FrankenPHP 1.13.6 compilé contre ce PHP. Ce même PHP sert à la CLI, à
Composer et à castor, pour que la console et le serveur web se comportent pareil. FrankenPHP tourne en mode worker partout, avec rechargement sur
modification de fichier en développement.

Le code Symfony vit entièrement dans `app/`. La page d'accueil est statique : une carte du kit Shadcn du UX Toolkit affichant « Hello world », stylée par
Tailwind via AssetMapper. Twig Components et Live Components sont installés, sans composant interactif. Doctrine ORM et Migrations sont configurés sur un
fichier SQLite, sans aucun schéma. Les tâches castor reprennent l'ossature de `jolicode/docker-starter` (point d'entrée mince, un fichier par espace de
noms dans `.castor/`, mêmes noms de tâches), avec des exécutions directes dans `app/` à la place des `docker compose run`. Les fichiers générés
(vendor, importmap, feuille de style, Chromium, base) passent par `app/Makefile`, que castor appelle avec `--jobs` pour ne reconstruire que ce qui a changé.

Docker ne sert pas au développement. Le flake produit une image qui ne contient que le runtime (FrankenPHP, PHP 8.6, Caddyfile), et `compose.yaml` y monte
`./app` déjà installé par castor, pour prouver le runtime conteneurisé.

Les outils qui savent télécharger leur binaire le font eux-mêmes : Tailwind via son bundle, Chromium via `playwright-install`, Playwright MCP via `npx`.
Leurs versions sont épinglées dans la configuration du projet, pas dans le flake.

**Impact sur les clients existants** :

- Aucun client : le dépôt n'a ni code ni utilisateur.
- `AGENTS.md` change de statut : la section Stack et la section Pitfalls décrivent aujourd'hui un dépôt non amorcé et doivent citer les commandes réelles.
- `forge/stack.md` s'écarte du livré sur trois points (UX CSS reporté, runtime natif Nix, image limitée au runtime) et doit être recartographié.

### Mécanismes mobilisés

- **Pattern** : environnement de développement déclaratif (flake avec `devShells.default`), runtime applicatif en processus long (mode worker),
  tâches de développement orchestrées par un lanceur unique.
- **Sélection des versions** : `nixpkgs-multiverse` comme unique entrée du flake. Un jeu de paquets de base daté, et des pins par version pour PHP,
  Mago et Node. `flake.lock` continue de figer l'entrée elle-même.
- **Paquets maison** : `infra/packages/php.nix` (PHP 8.6 ZTS, `embed`, extensions minimales dont `intl` liée à ICU 79.1, SAPI CGI, FPM et phpdbg
  retirées) et `infra/packages/frankenphp.nix` (FrankenPHP 1.13.6, dérivation reprise de celle de nixpkgs et réduite au nécessaire). Mago et
  symfony-lsp sont les binaires officiels, épinglés par URL et hash (`infra/packages/mago.nix`, `infra/packages/symfony-lsp.nix`). PHP est en 8.6.0 finale, jamais en version candidate : tant que l'index multiverse
  ne connaît pas `php86` 8.6.0, `php.nix` surcharge la source de `php86` avec l'archive 8.6.0 officielle. castor vient de `phpPackages.castor` (1.9.1) de la révision sélectionnée, Composer
  de `phpPackages.composer`.
- **Mode worker** : `FrankenPhpWorkerRunner`, embarqué dans `symfony/runtime` 8.2. Aucun paquet de runtime tiers. Le Caddyfile déclare le worker et la
  surveillance de fichiers, paramétrés par variables d'environnement pour servir à la fois au shell de dev et à l'image.
- **Nouvelles dépendances Composer** (dans `app/composer.json`) : squelette Symfony 8.2 (`framework-bundle`, `runtime`, `console`, `dotenv`, `flex`,
  `yaml`, `twig-bundle`), `symfony/asset-mapper`, `symfony/stimulus-bundle`, `symfony/ux-twig-component`, `symfony/ux-live-component`,
  `symfonycasts/tailwind-bundle`, `doctrine/orm`, `doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle`, `symfony/monolog-bundle`. En dev :
  `symfony/ux-toolkit`, `phpunit/phpunit` 13, `symfony/browser-kit`, `symfony/css-selector`, `symfony/web-profiler-bundle`, `playwright-php/playwright`,
  `playwright-php/playwright-symfony`, `symfony/ai-mate` (`v1.0.0-BETA1`), `symfony/ai-symfony-mate-extension`, `symfony/ai-monolog-mate-extension`.
  Les dépendances du kit Shadcn (`shadcn`, `tw-animate-css`, et les extensions Twig que ses composants appellent) suivent son guide d'installation.
- **Mécanisme d'extension** : recettes Flex et configuration des bundles uniquement. Aucune modification de dépendance tierce. Les composants du kit
  sont copiés dans `app/templates/components/` par la commande d'installation du Toolkit, ils deviennent du code du projet.
- **Qualité** : Mago 1.60.2 (format, lint, analyse) fourni par le flake et configuré dans `app/mago.toml`. Il remplace le trio PHP-CS-Fixer, PHPStan et
  Twig-CS-Fixer des dépôts de référence. symfony-lsp 0.24.2 vérifie routes, services et gabarits. Un hook Claude Code passe Mago sur chaque fichier
  PHP ou Twig édité (`.claude/hooks/mago.sh`).
- **Assistants** : Mate est, dans sa beta 1.0, une CLI que l'agent appelle par `nix develop -c castor mate`, depuis tout dossier du dépôt. `mate init`
  génère `app/mate/`, `app/CLAUDE.md` et les skills de `app/.agents/skills/`, et `AGENTS.md` renvoie vers `app/mate/AGENT_INSTRUCTIONS.md`.
  Playwright MCP (`@playwright/mcp` 0.0.86) est déclaré dans `.mcp.json` à la racine, lancé par `nix develop -c npx` avec un profil en mémoire.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Développement dans Docker avec une image `dunglas/frankenphp` | Aucun tag `php8.6` publié, et le runtime natif Nix a été préféré pour la boucle de développement. |
| `frankenphp` de nixpkgs tel quel | Version 1.13.5 compilée contre PHP 8.5 avec le jeu d'extensions complet, alors que la cible est 1.13.6, PHP 8.6 et le strict nécessaire. |
| Plusieurs entrées `nixpkgs` épinglées par commit | Les versions obtenues ne se lisent pas dans `flake.nix`. Multiverse les sélectionne par numéro avec une seule entrée. |
| Image autonome 100 % Nix | Un build Nix n'a pas de réseau : il faudrait un hash des dépendances Composer à rafraîchir à chaque mise à jour, et fournir Tailwind et l'importmap par Nix. |
| Dockerfile compilant FrankenPHP sur `php:8.6.0-zts` | Seconde définition du runtime à garder alignée avec le flake. |
| Binaire Tailwind fourni par Nix | Le bundle télécharge et épingle son propre binaire, ce qui suffit sur la machine de développement. |
| UX CSS en plus de Tailwind | Deux systèmes CSS à faire cohabiter, dont un expérimental. UX CSS est reporté. |
| PHP 8.5 et Symfony 8.1 stables | Imposerait une montée de version dès la sortie de Symfony 8.2, attendue sous deux semaines. |
| castor en dépendance Composer ou en phar | Castor lance l'installation de l'application, il doit exister avant `composer install`. nixpkgs le fournit en 1.9.1. |

## Modèle de données

Aucun impact modèle. Doctrine est configuré sur `app/var/data_<environnement>.db`, sans entité ni migration. Les seules requêtes exécutées sont
`SELECT sqlite_version()` et une lecture de `pragma_database_list` dans un test, sans table, donc sans plan d'exécution à analyser.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `flake.nix` | Entrée `nixpkgs-multiverse`, pins par version, `devShells.default`, sorties `packages` (PHP, FrankenPHP, image) et `checks`. |
| `flake.lock` | Verrou de l'entrée du flake. |
| `infra/packages/php.nix` | PHP 8.6.0 ZTS avec `embed` et les seules extensions exigées par l'application et ses outils. |
| `infra/packages/frankenphp.nix` | FrankenPHP 1.13.6 compilé contre le PHP maison. |
| `infra/packages/docker-image-dev.nix` | Image OCI `rssbase-dev` limitée au runtime : FrankenPHP, PHP, Caddyfile, certificats racine. |
| `infra/packages/mago.nix`, `infra/packages/symfony-lsp.nix` | Binaires officiels de Mago et de symfony-lsp. |
| `infra/frankenphp/Caddyfile` | Serveur HTTP, racine `public/`, worker Symfony, surveillance de fichiers, le tout piloté par variables d'environnement. |
| `castor.php` | Point d'entrée : version minimale de castor, import de `.castor/`, tâches `start`, `stop`, `mate`, `app:install`, `app:cache-clear`, `app:db:migrate`. |
| `app/Makefile` | Fichiers générés (vendor, importmap, assets des bundles, feuille de style, Chromium, base), reconstruits seulement quand une source change. |
| `.castor/context.php` | Variables du projet (répertoires, ports, identifiant utilisateur) et contextes `default` et `test`. |
| `.castor/qa.php` | Espace `qa` : `qa:all` (alias `qa` et `ci`, en parallèle), `qa:format`, `qa:lint`, `qa:analyze`, `qa:symfony-lsp`, `qa:phpunit`, `qa:e2e`, `qa:security-audit`, avec codes de sortie agrégés. |
| `.castor/docker.php` | Espace `docker` : construire et charger l'image depuis le flake, démarrer et arrêter `compose.yaml`. |
| `compose.yaml` | Service unique sur l'image de runtime, montage de `./app`, cache hors montage, port publié sur la boucle locale. |
| `.mcp.json` | Déclaration du serveur Playwright MCP en version épinglée. |
| `.gitignore` | Artefacts de build Nix, stub de castor et sorties de Playwright MCP (`.playwright-mcp/`). |
| `.claude/settings.json`, `.claude/hooks/mago.sh` | Hook Claude Code qui passe Mago sur les fichiers PHP et Twig édités. |
| `app/composer.json`, `app/composer.lock`, `app/symfony.lock` | Manifeste applicatif : `php >=8.6`, Symfony `8.2.*`, `minimum-stability: beta` avec `prefer-stable`. |
| `app/bin/`, `app/public/index.php`, `app/src/Kernel.php`, `app/config/`, `app/.env`, `app/.env.test`, `app/.gitignore` | Squelette Symfony généré par les recettes Flex. |
| `app/src/Controller/HomeController.php` | Route `/` qui rend la page d'accueil. |
| `app/templates/base.html.twig`, `app/templates/home/index.html.twig` | Gabarit de base et page « Hello world » utilisant la carte du kit. |
| `app/templates/components/` | Composant carte du kit Shadcn, copié par le Toolkit. |
| `app/importmap.php`, `app/assets/app.js`, `app/assets/styles/app.css` | Point d'entrée AssetMapper et feuille Tailwind avec le thème du kit. |
| `app/config/packages/doctrine.yaml`, `app/config/packages/doctrine_migrations.yaml`, `app/src/Migrations/` | Connexion SQLite et répertoire de migrations, vide. |
| `app/config/packages/monolog.yaml` | Configuration de la recette de MonologBundle. |
| `app/mago.toml` | Règles de format, de lint et d'analyse, ciblant PHP 8.6. |
| `app/phpunit.dist.xml`, `app/tests/bootstrap.php` | Deux suites : `default` (fonctionnel et intégration) et `e2e`. |
| `app/tests/Functional/HomePageTest.php` | Réponse de `/` et d'une URL inconnue. |
| `app/tests/Integration/DatabaseConnectionTest.php` | Connexion SQLite réelle. |
| `app/tests/E2E/HomePageBrowserTest.php` | Page d'accueil dans Chromium, servie par FrankenPHP en mode worker. |
| `app/mate/`, `app/CLAUDE.md`, `app/.agents/skills/`, `app/.claude/skills/` | Configuration, instructions et skills générés par `mate init` et `mate discover`. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `AGENTS.md` | Remplacer « Target stack, not scaffolded yet » et le piège « no `composer.json`, castor task » par l'état réel : arborescence `app/` et `infra/`, commandes castor, renvoi vers `app/mate/AGENT_INSTRUCTIONS.md`. |
| `forge/stack.md` | Recartographié par `/forge:stack` en mode Éditer, pas à la main : versions prouvées par les manifestes, UX CSS reporté, runtime natif Nix, image limitée au runtime. |

## Hors scope

- **Client AT Protocol, OAuth, lexicon** : première story fonctionnelle.
- **Entités, migrations, schéma** : cadrés avec la première donnée persistée.
- **UX CSS** : reporté, le kit Shadcn et Tailwind couvrent le hello world.
- **Composant Live interactif** : le bundle est installé, aucun composant n'est écrit.
- **Image autonome déployable, hébergement, déploiement** : aucun hébergement n'est défini.
- **HTTPS local** : à décider avec le login OAuth.
- **CI** : `forge/stack.md` n'en prévoit pas encore.
- **Observabilité** : le bundle de logs est installé avec sa configuration par défaut, rien de plus.
- **Internationalisation** : le texte de la page est en dur.
- **Outillage de release et contrôle automatique des messages de commit** : la convention est dans `AGENTS.md`, sans automatisme.
- **Analyse Mago des fichiers castor** : castor est un binaire Nix, ses fonctions ne sont pas visibles depuis `app/vendor`.

## Impacts transverses

- **Modules clients impactés** : aucun.
- **Migration de données** : aucune.
- **Impacts prod / infra** : aucun en production. En local : ports 8000 (développement) et 8080 (Compose), ou le premier port libre au-delà, tous liés à
  la boucle locale. Le démon Docker n'est requis que pour l'étape Compose.
- **Droits d'accès** : inchangé, il n'y a pas d'authentification.
- **Sécurité** :
  - Le serveur écoute en HTTP sur `localhost` uniquement, y compris le port publié par Compose.
  - `app/.env` ne contient que des valeurs par défaut non secrètes, les secrets vont dans `app/.env.local`, non versionné.
  - Trois binaires viennent de téléchargements hors Nix (Tailwind, Chromium, `@playwright/mcp`). Leurs versions sont épinglées dans la configuration du
    bundle, `app/composer.lock` et `.mcp.json`.
  - `composer audit` fait partie de `qa:all`.

## Rollback et kill switch

- **Kill switch** : aucun, et il n'en faut pas. Rien n'est en production et aucun chemin existant n'est modifié. Le retour arrière est un revert des
  commits de la story, chaque étape étant commitée seule.
- **Comportement en panne de dépendance** : aucune dépendance d'infrastructure. SQLite est un fichier local, il n'y a ni service ni réseau à l'exécution.
- **Cohabitation ancienne/nouvelle version** : sans objet, il n'existe pas de version précédente.
- **Sessions / cookies** : sans objet.

## Métriques (baseline → cible)

Le dépôt est vide : il n'existe aucune valeur de référence à mesurer avant le changement. Les cibles sont donc binaires, sauf le gain du mode worker,
qui se mesure contre le mode classique sur le même commit.

| Métrique | Baseline | Cible | Méthode de mesure |
|---|---|---|---|
| Démarrage depuis un clone propre | impossible, aucun manifeste (commit `8a85f3f`) | **`GET /` répond 200 avec « Hello world »** | `nix develop -c castor install`, `castor start`, puis `curl -i http://localhost:8000/` |
| Version de PHP dans le shell et dans FrankenPHP | n/a | **8.6.0** | `php -v` et `frankenphp version` |
| Version de FrankenPHP | n/a | **1.13.6** | `frankenphp version` |
| Version de Symfony | n/a | **8.2.x** | `bin/console --version` |
| Tests automatisés | 0 | **3 fichiers de test verts, 2 suites** | `castor qa:phpunit` et `castor qa:e2e` |
| Signalements Mago | n/a | **0** | `castor qa:format`, `castor qa:lint`, `castor qa:analyze` |
| Avis de sécurité Composer | n/a | **0** | `castor qa:security-audit` |
| Temps médian de `GET /`, `APP_ENV=prod`, cache chaud, 200 requêtes séquentielles | mode classique : 11,28 ms puis 11,05 ms (deux passes, commit `46b92d3`) ; worker : 1,95 ms puis 3,77 ms | **mode worker strictement inférieur**, les deux valeurs consignées | boucle `curl -w '%{time_total}'`, worker désactivé puis activé |
| Page servie par l'image | n/a | **`GET /` répond 200** | `castor docker:up`, puis `curl -i http://localhost:8080/` |

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `app/tests/Functional/HomePageTest.php` | functional | `GET /` répond 200, contient « Hello world » et le balisage de la carte du kit. Une URL inconnue répond 404. |
| `app/tests/Integration/DatabaseConnectionTest.php` | functional | La connexion Doctrine réelle de l'environnement de test ouvre `var/data_test.db`, exécute `SELECT sqlite_version()` et renvoie une version. |
| `app/tests/E2E/HomePageBrowserTest.php` | E2E | Dans Chromium, requêtes traitées par le noyau de test (playwright-symfony), sans FrankenPHP : le titre est visible, la feuille de style est chargée et appliquée à la carte, la console du navigateur ne signale aucune erreur. |

**Hors scope tests** :

- Les paquets Nix ne se testent pas par PHPUnit : ils sont vérifiés par leur construction, par les commandes de version et par `nix flake check`.
- Les tâches castor n'ont pas de tests propres, elles sont exercées par les vérifications de chaque étape.
- L'image et `compose.yaml` demandent un démon Docker : vérification manuelle par `castor docker:up` et `curl`.
- Mate et Playwright MCP se vérifient à la main : liste des outils de Mate, navigation vers la page d'accueil par le serveur MCP.
- Live Components n'a aucun composant à tester.
- La comparaison worker contre classique est une mesure ponctuelle, pas un test : un seuil de temps dans la suite serait instable.
- Le mode worker lui-même se vérifie à la main (`castor start`, image) : la suite E2E ne passe pas par FrankenPHP.

## Ordre d'exécution

Chaque étape donne au moins un commit atomique, en anglais, au format de `AGENTS.md` (emoji, espace, type Conventional Commits).

1. [x] **Shell Nix et paquets du runtime**
   - Objectif : `nix develop` fournit PHP 8.6.0, Composer, castor, FrankenPHP 1.13.6, Mago et Node, avec les versions lisibles dans `flake.nix`.
     Si l'index multiverse connaît `php86` 8.6.0, le pinner par version. Sinon, surcharger la source dans `infra/packages/php.nix`.
   - Fichiers : `flake.nix`, `flake.lock`, `infra/packages/php.nix`, `infra/packages/frankenphp.nix`, `.gitignore`.
   - Vérification : `php -v`, `php -m`, `frankenphp version`, `castor --version`, `mago --version`, `nix flake check`.
   - Commitable seule : oui.

2. [x] **Squelette Symfony dans `app/`**
   - Objectif : une application Symfony 8.2 minimale, sans page, dont la console démarre.
   - Fichiers : `app/composer.json`, `app/composer.lock`, `app/symfony.lock`, squelette généré par Flex.
   - Vérification : `composer validate --strict`, `composer check-platform-reqs`, `bin/console --version`.
   - Commitable seule : oui.

3. [x] **Tâches castor et FrankenPHP en mode worker**
   - Objectif : `castor install` installe l'application, `castor start` la sert au premier plan sur `http://localhost:8000` en mode worker, avec
     rechargement sur modification de fichier.
   - Fichiers : `castor.php`, `.castor/context.php`, `infra/frankenphp/Caddyfile`.
   - Vérification : la page par défaut de Symfony répond. Une modification d'un fichier PHP est visible à la requête suivante sans relancer le serveur.
     Le journal de FrankenPHP annonce le worker.
   - Commitable seule : oui.

4. [x] **Outillage QA et page hello world**
   - Objectif : la route `/` rend « Hello world » en Twig brut, couverte par un test fonctionnel. Mago et PHPUnit passent par castor.
   - Fichiers : `.castor/qa.php`, `app/mago.toml`, `app/phpunit.dist.xml`, `app/tests/bootstrap.php`, `app/src/Controller/HomeController.php`,
     `app/templates/`, `app/tests/Functional/HomePageTest.php`.
   - Vérification : `castor qa:phpunit`, `castor qa:format`, `castor qa:lint`, `castor qa:analyze`, `castor qa:security-audit`.
   - Commitable seule : oui.

5. [x] **Chaîne front : Tailwind, Twig Components, Live Components, kit Shadcn**
   - Objectif : la page affiche « Hello world » dans la carte du kit, stylée par Tailwind. `castor install` construit les assets.
   - Fichiers : `app/importmap.php`, `app/assets/`, `app/templates/components/`, `app/templates/base.html.twig`, `app/templates/home/index.html.twig`,
     configuration des bundles, test fonctionnel complété.
   - Vérification : construction Tailwind sans erreur, feuille de style servie en 200, `castor qa:phpunit` vert.
   - Commitable seule : oui.

6. [x] **SQLite et Doctrine**
   - Objectif : connexion Doctrine sur un fichier SQLite par environnement, tâche `app:db:migrate` qui tolère l'absence de migration.
   - Fichiers : `app/config/packages/doctrine.yaml`, `app/config/packages/doctrine_migrations.yaml`, `app/migrations/`, `app/.env`, `app/.env.test`,
     `castor.php`, `app/tests/Integration/DatabaseConnectionTest.php`.
   - Vérification : `castor migrate` sans erreur, `castor qa:phpunit` vert.
   - Commitable seule : oui.

7. [x] **Test navigateur Playwright**
   - Objectif : `castor qa:e2e` lance la suite `e2e` dans Chromium, requêtes traitées par le noyau de test, sans serveur à démarrer.
     `castor install` installe Chromium.
   - Fichiers : `app/composer.json`, `app/phpunit.dist.xml`, `app/tests/E2E/HomePageBrowserTest.php`, `.castor/qa.php`, `castor.php`.
   - Vérification : `castor qa:e2e` vert. `castor qa` enchaîne tout.
   - Commitable seule : oui.

8. [x] **Image de runtime et Compose**
   - Objectif : `castor docker:up` construit l'image depuis le flake, la charge et sert `./app` monté, en mode worker, sur `http://localhost:8080`.
   - Fichiers : `infra/packages/image.nix`, `flake.nix`, `compose.yaml`, `.castor/docker.php`.
   - Vérification : `curl -i http://localhost:8080/` répond 200. Les fichiers créés dans `app/var/` appartiennent à l'utilisateur de l'hôte.
     `castor docker:down` arrête tout.
   - Commitable seule : oui.

9. [x] **Assistants : Mate et Playwright MCP**
   - Objectif : Mate initialisé dans `app/`, Playwright MCP déclaré à la racine.
   - Fichiers : `app/composer.json`, `app/mate/`, configuration du bundle de logs, `.mcp.json`.
   - Vérification : `vendor/bin/mate tools:list` liste les outils Symfony et Monolog. Le serveur Playwright MCP démarre et ouvre la page d'accueil.
   - Commitable seule : oui.

10. [x] **Mesure du mode worker et documentation**
    - Objectif : consigner le temps médian de `GET /` en mode classique puis en mode worker. Aligner `AGENTS.md`, puis recartographier `forge/stack.md`
      par `/forge:stack`.
    - Fichiers : `AGENTS.md`, `forge/stack.md`.
    - Vérification : les deux valeurs figurent dans l'entrée de changelog de l'implémentation. Les commandes citées dans `AGENTS.md` s'exécutent.
    - Commitable seule : oui.

## Critères de sortie

- [x] Depuis un clone propre, `nix develop -c castor install` puis `castor start` : `GET http://localhost:8000/` répond 200 avec « Hello world ».
- [x] `php -v` et `frankenphp version` annoncent PHP 8.6.0, `frankenphp version` annonce 1.13.6, `bin/console --version` annonce Symfony 8.2.
- [x] FrankenPHP sert l'application en mode worker en développement et dans l'image.
- [x] Le temps médian de `GET /` en mode worker est strictement inférieur à celui du mode classique, les deux valeurs sont consignées.
- [x] `castor qa` est vert : suites `default` et `e2e`, Mago sans signalement, `composer audit` sans avis.
- [x] `composer validate --strict` et `composer check-platform-reqs` passent sans option d'exemption de plateforme.
- [x] `nix flake check` passe.
- [x] `castor docker:up` sert la page sur `http://localhost:8080/`, ou le premier port libre au-delà, depuis l'image produite par le flake.
- [x] `nix develop -c castor mate tools:list` répond et le serveur Playwright MCP déclaré dans `.mcp.json` démarre.
- [x] Tout le code Symfony est sous `app/`, les paquets Nix maison sous `infra/packages/`.
- [x] Kill switch : sans objet, justifié dans §Rollback et kill switch.
- [x] `AGENTS.md` et `forge/stack.md` décrivent l'état livré.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| `php86` 8.6.0 absent de l'index multiverse le jour de l'implémentation (encore le cas le 2026-11-20 : seules `8.6.0RC3` et `8.6.0RC4` sont indexées) | moyenne | `infra/packages/php.nix` surcharge la source de `php86` avec l'archive 8.6.0, au prix d'un hash. Surcharge retirée au profit du pin par version dès que `nixos-unstable` avance. |
| Un correctif nixpkgs propre à la version candidate ne s'applique plus sur la source 8.6.0 | faible | Reprendre la liste de correctifs de la PR nixpkgs 590480, qui fait cette montée de version. |
| PHP ZTS et FrankenPHP maison absents du cache binaire : compilation locale à la première entrée dans le shell et à chaque montée de version | élevée | Deux paquets seulement, compilés une fois puis gardés dans le store. Un cache binaire est une question ouverte. |
| Une dépendance refuse PHP 8.6 au lendemain de sa sortie (contrainte Composer, extension, analyse Mago de la syntaxe 8.6) | moyenne | `composer check-platform-reqs` sans exemption. En cas de blocage, s'arrêter et demander avant tout repli sur PHP 8.5. |
| Rupture entre Symfony `8.2.0-BETA2` et la version stable | faible | `composer update` à la sortie stable, puis retrait de `minimum-stability: beta`. |
| Jeu d'extensions PHP trop réduit pour Composer, castor ou PHPUnit | moyenne | Partir de `composer check-platform-reqs` et des prérequis Symfony, ajouter une extension seulement sur échec constaté. Si castor ne se construit pas avec le PHP minimal, il garde le `php86` standard comme interpréteur : c'est un outil, pas le runtime. |
| FrankenPHP 1.13.6 exige une version de Go plus récente que celle de la révision nixpkgs sélectionnée | faible | Sélectionner Go par version via multiverse dans `infra/packages/frankenphp.nix`. |
| Paquet FrankenPHP maison : deux hashes (source, modules Go) à rafraîchir à chaque release | élevée | Coût accepté. Supprimer le paquet maison le jour où nixpkgs offre la même version contre PHP 8.6 avec une surcharge suffisante. |
| Mode worker : code obsolète servi en développement si la surveillance de fichiers ne redémarre pas le worker | moyenne | Vérification explicite à l'étape 3, sur un fichier PHP et sur un gabarit. |
| Mode worker : état conservé entre deux requêtes | faible | Aucun service à état dans cette story. Le test E2E charge la page deux fois de suite. |
| Binaires téléchargés hors Nix (Tailwind, Chromium, `npx`) inutilisables sur NixOS ou dans un build isolé | faible | Limite assumée pour une machine unique. Repli possible : option de binaire du bundle Tailwind et navigateurs de nixpkgs. |
| Cache Symfony partagé entre l'hôte et le conteneur avec des chemins absolus différents | moyenne | Le conteneur écrit son cache hors du montage. |
| Fichiers de `app/var/` créés par le conteneur sous un autre utilisateur | moyenne | Le service Compose tourne avec l'identifiant de l'utilisateur de l'hôte, fourni par le contexte castor. |
| Serveur E2E laissé en arrière-plan après un échec | moyenne | Arrêt systématique dans la tâche, vérifié à l'étape 7 sur un échec provoqué. |
| `symfony/ai-mate` en beta, et CLI plutôt que serveur MCP | moyenne | Version épinglée dans `app/composer.lock`. Outil de développement seulement, sans effet sur l'application. |
| `nixpkgs-multiverse` est un projet récent (août 2026) | faible | Le repli se limite à `flake.nix` : une entrée `nixpkgs` classique épinglée. |
| Playwright MCP lancé hors du shell Nix ne trouve pas Node | moyenne | Vérifier le démarrage à l'étape 9 depuis la session réelle de l'agent, et envelopper la commande par `nix develop -c` si nécessaire. |

## Questions ouvertes

- **Modules Caddy du binaire FrankenPHP** : le point d'entrée standard embarque Mercure et Vulcain. Options : (a) garder le point d'entrée amont,
  (b) écrire un point d'entrée maison sans ces modules. Recommandé : (a), il suit le `go.sum` amont et évite un fichier Go à maintenir. (b) seulement si
  « juste ce qu'il faut » s'applique aussi aux modules Caddy. **Tranché : (a)**, Mercure et Vulcain sont dans le binaire.
- **Mate comme serveur MCP** : la beta 1.0 se présente comme une CLI. Options : (a) s'en tenir à la CLI et à `app/mate/AGENT_INSTRUCTIONS.md`,
  (b) l'ajouter aussi à `.mcp.json` si un mode serveur existe encore. Recommandé : (a), c'est l'usage documenté par l'amont. **Tranché : (a)**, Mate n'a plus de serveur MCP depuis la 0.13.
- **Bundle de logs** : `symfony/monolog-bundle` est ajouté pour que l'extension Monolog de Mate ait des journaux à lire. Options : (a) le garder avec
  sa configuration par défaut, (b) le retirer avec l'extension. Recommandé : (a), coût nul et utile dès le premier bug. **Tranché : (a)**.
- **Passage à Symfony 8.2.0 stable** : options : (a) le faire dans cette story si la version sort avant sa clôture, (b) une story `-t-` dédiée.
  Recommandé : (a), c'est un `composer update` et le retrait d'une ligne.
- **Cache binaire pour les paquets maison** : options : (a) aucun, (b) un cache distant. Recommandé : (a) tant qu'il n'y a qu'une machine et pas de CI.
- **HTTPS local** : délégué à la story de login OAuth, qui dira si le client de développement AT Protocol accepte une URL de boucle locale en HTTP.
- **Internationalisation** : le texte est en dur alors que la référence Symfony demande des libellés traduits. Options : (a) en dur pour le hello world,
  (b) composant de traduction dès maintenant. Recommandé : (a), la langue de l'interface n'est pas décidée. À trancher avec la première feature.
- **Image autonome déployable** : déléguée à la story de déploiement, qui choisira entre une dérivation Nix complète et un Dockerfile au-dessus de
  l'image de runtime.
