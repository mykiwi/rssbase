# Stack technique — RssBase

> Dernière mise à jour : 2026-11-22 — cartographie factuelle de la stack, amorcée par la story `001-t-stack-hello-world`. Chaque entrée est prouvée par un fichier du dépôt (source entre parenthèses) ou marquée _non renseigné_. Contrainte déclarée et version résolue sont distinguées : « contrainte » vient d'un manifeste, « résolu » d'un lockfile ou d'une dérivation Nix.

## Vue d'ensemble

Monolithe Symfony servi par FrankenPHP en mode worker : un lecteur RSS adossé à AT Protocol (login, stockage des données utilisateur dans le PDS). Rendu côté serveur en Twig, base SQLite. Le développement tourne en natif dans un shell Nix ; Docker ne porte qu'une image de runtime. Aucun hébergement de production n'est défini.

| Couche | Techno principale |
|---|---|
| Langage(s) | PHP 8.6.0 (build maison), Node 26 pour l'outillage |
| Backend | Symfony 8.2 (beta), FrankenPHP 1.13.6 en mode worker |
| Frontend | Twig Components, Live Components, AssetMapper, Tailwind 4, UX Toolkit (kit Shadcn) |
| Données | SQLite via Doctrine ORM |
| Ops | Image Docker de runtime construite par le flake, Docker Compose |
| DevOps | _non renseigné_ (pas de CI) |

## Langages & runtimes

- **PHP** `8.6.0` résolu, ZTS, `embed`, extensions limitées à ce que l'application exige (ctype, dom, filter, iconv, intl, mbstring, openssl, pdo, pdo_sqlite, session, simplexml, tokenizer, xmlwriter) — source de 8.6.0 surchargée sur la recette `php86` 8.6.0RC4 tant que l'index multiverse ne connaît pas 8.6.0 (`infra/packages/php.nix`, `flake.nix`). Contraintes applicatives `php >=8.6`, `ext-intl` et `ext-pdo_sqlite` (`app/composer.json`).
- **ICU** `79.1` résolu pour `intl`, au lieu de l'ICU 73 de nixpkgs (`flake.nix`, `infra/packages/php.nix`).
- **FrankenPHP** `1.13.6` résolu, Caddy 2.11.9, compilé contre le PHP maison, point d'entrée amont (Mercure et Vulcain inclus) (`infra/packages/frankenphp.nix`).
- **Node** `26.13.1` résolu, outillage seulement (Playwright, `npx`) (`flake.nix`).

## Backend

- **Framework** : Symfony, contrainte `8.2.*` avec `minimum-stability: beta` et `prefer-stable`, résolu `v8.2.0-BETA2` (`app/composer.json`, `app/composer.lock`).
- **Runtime** : `symfony/runtime` `v8.2.0-BETA2`, `FrankenPhpWorkerRunner` embarqué, aucun runtime tiers (`app/composer.lock`, `infra/frankenphp/Caddyfile`).
- **ORM / données** : Doctrine ORM `3.8.0`, DoctrineBundle `3.4.2`, DoctrineMigrationsBundle `4.0.1` résolus ; migrations dans `app/src/Migrations/` ; une entité (`Account`, trace des connexions) et sa migration, types DBAL `did` et `handle` (`app/composer.lock`, `app/config/packages/doctrine*.yaml`, `app/src/Entity/Account.php`, `app/src/Doctrine/`).
- **Logs** : MonologBundle `v4.1.0` résolu, configuration de la recette (`app/composer.lock`, `app/config/packages/monolog.yaml`).
- **Sécurité** : SecurityBundle `v8.2.0-BETA2` (pare-feu en refus par défaut, authentificateur sur mesure), `symfony/security-csrf` `v8.2.0-BETA1` (jetons sans état) résolus (`app/composer.lock`, `app/config/packages/security.yaml`, `app/config/packages/csrf_protection.yaml`).
- **Client AT Protocol** : maison, dans `app/src/AtProto/` (résolution d'identité, OAuth avec PAR, PKCE et DPoP, client confidentiel ou en boucle locale) ; aucun SDK AT Protocol. S'appuie sur `symfony/http-client` `v8.2.0-BETA2` (client scopé derrière `NoPrivateNetworkHttpClient`), la `DpopProofFactory` de `symfony/security-http` et `web-token/jwt-library` `4.2.7` (assertion du client, clés JWK), résolus (`app/composer.lock`, `app/config/services.yaml`, `app/config/packages/http_client.yaml`). Records et spaces : _non renseigné_, aucune écriture dans le PDS.

## Frontend

- **Composants** : Twig Components `v3.7.0` et Live Components `v3.7.2` résolus, aucun composant interactif (`app/composer.lock`).
- **Bundler / build** : AssetMapper avec importmap, Stimulus `3.2.2` via StimulusBundle `v3.7.0`, sans Node ni bundler JS (`app/importmap.php`, `app/composer.lock`).
- **CSS** : Tailwind `v4.3.5` (binaire épinglé), via `symfonycasts/tailwind-bundle` `v1.0.0` ; sources limitées à `assets`, `templates` et `src` par `@source` (`app/config/packages/symfonycasts_tailwind.yaml`, `app/assets/styles/app.css`).
- **UI kit** : UX Toolkit `v3.7.1` (dev), kit Shadcn ; composants copiés dans `app/templates/components/`, thème `shadcn` `4.23.0` et `tw-animate-css` `1.4.0` par l'importmap, extensions Twig `tales-from-a-dev/twig-tailwind-extra` `v1.5.0` et `twig/html-extra` `v3.29.0` (`app/composer.lock`, `app/importmap.php`).
- **UX CSS** : reporté, non installé (plan de la story 001).
- **TypeScript** : non.

## Données & stockage

- **Base de données** : SQLite, un fichier par environnement, `app/var/data_<environnement>.db` (`app/.env`, `DATABASE_URL`). Hébergera aussi le stockage local temporaire des données privées tant que les spaces AT Protocol ne sont pas utilisables (plan B acté dans l'atelier de vision).
- **Données utilisateur portables** : PDS AT Protocol de l'utilisateur (records publics, spaces pour le privé) — service externe, _source : atelier de vision_ ; lu pour l'identité et le profil, aucune écriture.
- **Sessions** : sessions PHP natives sur fichiers, `app/var/sessions/<environnement>`, 90 jours sans visite ; elles portent les jetons OAuth et la clé DPoP (`app/config/packages/framework.yaml`, `AGENTS.md`). La déconnexion d'une session et de ses copies est mémorisée dans `cache.app` (`app/src/Security/SessionRenewal.php`).

## Ops / Infrastructure

- **Conteneurisation** : image `rssbase-dev`, runtime seul (FrankenPHP, PHP, Caddyfile, certificats racine), construite par `dockerTools.streamLayeredImage` sans Dockerfile ; l'application n'est pas dans l'image (`infra/packages/docker-image-dev.nix`, `.castor/docker.php`).
- **Orchestration** : Docker Compose, service unique qui monte `./app`, tourne sous l'identifiant de l'utilisateur de l'hôte et publie le port sur la boucle locale (`compose.yaml`).
- **Serveur HTTP** : Caddyfile partagé par le shell de dev et l'image, piloté par variables d'environnement ; HTTP seulement, écoute sur la boucle locale (`infra/frankenphp/Caddyfile`).
- **Hébergement de production** : _non renseigné_ (aucun hébergement défini).
- **CDN / reverse proxy** : _non renseigné_
- **Gestion des secrets** : `app/.env` sans secret, secrets en `app/.env.local` non versionné (`app/.env`, `app/.gitignore`) ; clé privée du client OAuth confidentiel `ATPROTO_CLIENT_PRIVATE_JWK`, vide par défaut, à placer dans le coffre de Symfony (`app/.env`, `AGENTS.md`) ; production _non renseigné_.
- **Environnements** : `dev`, `test` et `prod` Symfony en local (`app/.env`, `app/.env.dev`, `app/.env.test`) ; aucun environnement distant.

## DevOps / CI-CD

- **Pipeline CI** : _non renseigné_ (pas de `.github/` ni d'autre CI). `castor qa` reconnaît `CI` : vérification du formatage au lieu de sa correction, et `composer audit` (`.castor/qa.php`).
- **Tests** : PHPUnit `13.4.8` résolu, suites `default` (fonctionnel, intégration) et `e2e` ; navigateur par `playwright-php/playwright` `v1.6.0` et `playwright-php/playwright-symfony` `v0.12.0` dans le noyau de test, Chromium téléchargé par `playwright-install` (`app/composer.lock`, `app/phpunit.dist.xml`).
- **Analyse statique / style** : Mago `1.60.2`, binaire officiel (format, lint, analyse, Twig), zéro signalement jusqu'au niveau note (`infra/packages/mago.nix`, `app/mago.toml`) ; symfony-lsp `0.24.2`, binaire officiel (`infra/packages/symfony-lsp.nix`).
- **DNS** : flarectl `0.119.0`, binaire officiel, pour les enregistrements de la zone Cloudflare `rssbase.io` (`infra/packages/flarectl.nix`, `flake.nix`).
- **Hooks git / automatisation deps** : aucun hook git. Hook Claude Code `PostToolUse` qui passe Mago sur chaque fichier PHP ou Twig édité (`.claude/settings.json`, `.claude/hooks/mago.sh`).
- **Déploiement** : _non renseigné_

## Monitoring / observabilité

- **Erreurs** : _non renseigné_
- **Métriques / traces** : _non renseigné_
- **Logs** : Monolog, fichier par environnement en dev, `php://stderr` en prod, configuration de la recette (`app/config/packages/monolog.yaml`) ; aucune centralisation.

## Outillage de développement local

- **Environnement** : Nix, `flake.nix` avec `devShells.default` ; unique entrée `nixpkgs-multiverse`, jeu de paquets daté `2026-11-19` et pins par version (`flake.nix`, `flake.lock`). Flake limité à `x86_64-linux`.
- **Composer** : `2.11.2` résolu, du PHP maison (`flake.nix`).
- **Tâches** : castor `1.9.1` résolu (`flake.nix`), point d'entrée `castor.php` et un fichier par espace de noms dans `.castor/` (`app`, `qa`, `docker`) ; castor demande à `app/Makefile` les fichiers générés (vendor, importmap, feuille de style, Chromium, base), reconstruits seulement quand une source change (`castor.php`, `.castor/`, `app/Makefile`).
- **Commandes QA / build** : `nix develop -c castor install`, `castor start` (port 8000 ou suivant libre, feuille de style reconstruite par `tailwind:build --watch`), `castor qa`, `castor docker:up` (port 8080 ou suivant libre) (`AGENTS.md`). Le serveur répond aussi sur `*.rssbase.localhost` (session commune aux deux langues, refusée par les navigateurs sur `localhost`) et `127.0.0.1` (retour OAuth en boucle locale) (`castor.php`, `infra/packages/docker-image-dev.nix`). `castor qa` construit la base de test par la cible `test-database` d'`app/Makefile` et limite le contrôle des traductions au domaine `messages` (`.castor/qa.php`).
- **Assistants IA** : Mate `v1.0.0-BETA1` (CLI, plus de serveur MCP), extensions Symfony et Monolog, invoqué par `nix develop -c castor mate` depuis tout dossier du dépôt ; skills générés dans `app/.agents/skills/` et `app/.claude/skills/` (`app/composer.lock`, `app/mate/`). Playwright MCP `@playwright/mcp` `0.0.86` par `nix develop -c npx`, profil en mémoire, sorties dans `.playwright-mcp/` ignoré par git (`.mcp.json`, `.gitignore`).
- **Services de dev** : aucun service externe ; SQLite est un fichier local.

## Contraintes & dette de stack connues

- **Symfony beta** : `minimum-stability: beta` à retirer au passage à Symfony 8.2.0 stable (`app/composer.json`).
- **Surcharges en attente de nixpkgs** : source de PHP 8.6.0 (`infra/packages/php.nix`), ICU de `intl` (`infra/packages/php.nix`), binaire de Mago (`infra/packages/mago.nix`), binaire de flarectl (`infra/packages/flarectl.nix`) ; chacune porte la condition de son retrait.
- **Paquets maison** : PHP ZTS et FrankenPHP absents du cache binaire, recompilés en local à chaque changement d'extension ou de version (environ 3 minutes pour FrankenPHP).
- **Binaires hors Nix** : Tailwind, Chromium et `@playwright/mcp` sont téléchargés par leurs outils ; Chromium n'est pas épinglé par un lockfile (`playwright` `^1.62` installé sans lockfile).
- **Mode worker et E2E** : la suite `e2e` passe par le noyau de test, pas par FrankenPHP ; le mode worker n'est vérifié qu'à la main.
- **Nommage** : projet `rssbase`, dossier du dépôt `symfony-atproto`.
- **Dépendance protocole** : les spaces AT Protocol (données privées) ne sont pas garantis disponibles ni outillés en PHP ; le stockage SQLite temporaire en dépend.

## Changelog

- 2026-11-20 — Création — inventaire d'intention (stack cible, non amorcée)
- 2026-11-20 — Éditer — toutes couches — intentions remplacées par les preuves fichier après l'amorçage de la story 001 : versions résolues, runtime natif Nix, image limitée au runtime, UX CSS reporté, Doctrine et Monolog, Mate en CLI, Playwright MCP
- 2026-11-20 — Éditer — outillage — démarrage de Playwright MCP vérifié, dossier de sorties `.playwright-mcp/` ignoré
- 2026-11-20 — Éditer — outillage — Mate invoqué par `castor mate`, `castor start` reconstruit la feuille de style (correctifs de review)
- 2026-11-20 — Éditer — langages — `ext-intl` et `ext-pdo_sqlite` requis par `app/composer.json`, sync post-livraison de la story 001-t-stack-hello-world
- 2026-11-22 — Enrichir — backend, données, ops, outillage — SecurityBundle, client AT Protocol maison sur HttpClient et `web-token/jwt-library`, première entité, sessions sur fichiers, clé du client OAuth, noms d'hôte `rssbase.localhost` et `127.0.0.1`, sync post-livraison de la story 002-f-connexion-atproto
- 2026-11-22 — Enrichir — DevOps — flarectl 0.119.0 en paquet maison pour le DNS Cloudflare de `rssbase.io`, sync post-livraison de la story 003-f-abonnement-flux
