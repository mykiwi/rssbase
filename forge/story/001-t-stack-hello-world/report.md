# Report — Amorcer la stack technique avec un hello world

> **But** : garder la trace de ce qui a dérivé entre l'intention et le livré, et de ce qui reste à faire.
> **Registre** : factuel
> **Story** : `forge/story/001-t-stack-hello-world/`
> **Amont** : `plan.md` · `review.md`

## Synthèse

- **Écarts** : 27 — 15 réalignés dans `plan.md`, 12 laissés tels quels.
- **Critères** : 12 / 12 atteints, cochés dans `plan.md` §Critères de sortie.
- **Non livré** : 2.
- **Dette** : 6 items, dont 0 critique.
- **Métriques** : temps médian de `GET /` (prod, cache chaud, 200 requêtes) : mode classique 11,28 et 11,05 ms → mode worker 1,95 et 3,77 ms (cible : worker strictement inférieur), deux passes sur le commit `46b92d3`.

La stack cible tourne sur les versions visées, mais par plus de surcharges que prévu : PHP 8.6.0, ICU, Mago et symfony-lsp ne viennent pas tels quels de nixpkgs. Le test E2E passe par le noyau de test plutôt que par FrankenPHP. Restent la protection des paquets maison contre le ramasse-miettes Nix et le passage à Symfony 8.2 stable.

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison | Réaligné dans |
|---|---|---|---|
| PHP 8.6 par pin multiverse, ou surcharge de source | Surcharge de la recette `php86` 8.6.0RC4, calquée sur la PR nixpkgs 590480 ; SAPI CGI, FPM et phpdbg retirées | L'index multiverse ne connaît pas 8.6.0 ; les SAPI retirées ne servent pas | `plan.md` §Mécanismes mobilisés |
| Mago fourni par multiverse | Binaire officiel 1.60.2 (`infra/packages/mago.nix`) | Règle utilisateur : dernière version ; 1.60.2 exige rustc 1.100, absent de nixpkgs | `plan.md` §Mécanismes mobilisés, §Périmètre |
| `intl` absente du jeu minimal | `intl` ajoutée, liée à ICU 79.1 au lieu de l'ICU 73 de nixpkgs | Demande utilisateur : Symfony 8.2 la réclame en dev et en prod ; règle utilisateur : dernière version | `plan.md` §Mécanismes mobilisés |
| E2E contre FrankenPHP en mode worker, port 8001 | E2E par playwright-symfony dans le noyau de test, sans serveur | Décision utilisateur : vitesse de la suite | `plan.md` §Stratégie de test, §Ordre d'exécution, §Impacts transverses |
| `infra/packages/image.nix` | `infra/packages/docker-image-dev.nix`, image `rssbase-dev` | Non tracée (nommage) | `plan.md` §Périmètre |
| Compose sur le port 8080 | Premier port libre à partir de 8080, `network_mode: bridge` | 8080 pris sur la machine ; pools d'adresses de Docker épuisés | `plan.md` §Impacts transverses, §Critères de sortie |
| Migrations dans `app/migrations/` | `app/src/Migrations/`, sans `doctrine:database:create` | Recette de DoctrineMigrationsBundle 4 ; DBAL 4 ne liste pas les bases SQLite | `plan.md` §Périmètre |
| `vendor/bin/mate`, appelé directement | `nix develop -c castor mate`, depuis tout dossier du dépôt | Le shell de l'agent n'a pas PHP ; review [DOC] l'invocation de Mate ne marche que depuis la racine du dépôt | `plan.md` §Mécanismes mobilisés, §Critères de sortie |
| `mate init` génère `app/mate/` | Génère aussi `app/CLAUDE.md`, un bloc géré dans `app/AGENTS.md` et 7 skills commités | Comportement de Mate 0.13+ ; l'amont recommande de commiter les skills pour les voir en revue | `plan.md` §Mécanismes mobilisés, §Périmètre |
| Playwright MCP par `npx` | `nix develop -c npx … --isolated`, Chrome de la machine | Node du flake plutôt que le Node global ; profil en mémoire ; `--browser` ne propose plus `chromium` | `plan.md` §Mécanismes mobilisés |
| `castor qa` séquentiel, `composer audit` dans `qa:all` | Jobs en parallèle, alias `ci`, formatage en `--check` et `composer audit` seulement avec `CI` | Demandes utilisateur | `plan.md` §Périmètre |
| Exécutions castor directes | Fichiers générés par `app/Makefile`, appelé avec `--jobs`, `composer install --no-scripts` | Demande utilisateur : ne reconstruire que ce qui a changé | `plan.md` §Approche retenue, §Périmètre |
| Squelette Symfony 8.2 par Flex | Créé depuis 8.1.99 puis passé à la main en `8.2.*` beta, `name` et `description` ajoutés | Pas de tag 8.2 du squelette ; `composer validate --strict` | — |
| Fichiers des recettes tels quels | Retouchés pour zéro signalement Mago ; `config/bundles.php` exclu de Mago (format, lint, analyse) | Seuil `note` ; Flex réécrit `bundles.php` dans son propre format, sans `strict_types` | — |
| Admin Caddy sur `localhost:2019` | Socket Unix dans `app/var/` | `localhost:2019` déjà pris sur la machine | — |
| Surveillance de tous les fichiers | Motifs explicites, `var/` exclu | Symfony réécrit des fichiers PHP dans `var/` | — |
| Kit Shadcn par sa recette | Bundle `twig-tailwind-extra` enregistré à la main, Tailwind v4.3.5 épinglé à la main | Pas de recette Flex ; `tailwind:init` exige un terminal | — |
| Flake multiplateforme implicite | `x86_64-linux` seul | Non tracée | — |
| Banc worker contre classique, worker désactivé | Caddyfile jetable sans le bloc `worker`, hors dépôt | Le Caddyfile du projet n'a pas d'interrupteur | — |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Deux chargements consécutifs dans le test E2E (mitigation du risque d'état conservé en mode worker) | Abandonné : décision utilisateur pendant la review | Aucune |
| Passage à Symfony 8.2.0 stable dans la story (question ouverte, option a) | Reporté : la version stable n'est pas sortie | Dette n°2 |

### Ajouts non prévus

| Élément ajouté | Raison | Réaligné dans |
|---|---|---|
| symfony-lsp 0.24.2, `jq`, `gnumake`, raccourcis `cas`, `mag`, `fra` | Demandes utilisateur | `plan.md` §Mécanismes mobilisés, §Périmètre |
| Hook Claude Code qui passe Mago sur les fichiers édités, limité aux chemins de `app/mago.toml` | Demande utilisateur ; review [BUG] le hook passe Mago sur des fichiers que `castor qa` ne vérifie pas | `plan.md` §Mécanismes mobilisés, §Périmètre |
| Sources de Tailwind limitées à `assets`, `templates` et `src` | Le Markdown de Mate générait des utilitaires parasites | — |
| `castor start` lance `tailwind:build --watch` et refuse un second serveur | Review [UX] Tailwind n'est pas reconstruit pendant `castor start` ; review [ROBUSTESSE] un second `castor start` prend le socket d'admin du premier | — |
| `var/data_dev.db` en `.PRECIOUS` | Review [ROBUSTESSE] une migration en échec fait supprimer la base de dev par make | — |
| `ext-intl` et `ext-pdo_sqlite` requis par `composer.json` | Review [CONV] `composer.json` ne déclare ni `ext-pdo_sqlite` ni `ext-intl` | — |
| Plugin `symfony/ai-mate-composer-plugin` autorisé | Relance `mate discover` après `composer require` ou `update` | — |
| `.playwright-mcp/` ignoré par git | Le serveur MCP y écrit journaux et instantanés | `plan.md` §Périmètre |

## Décisions architecturales

- ADR-0002 — Sélection des versions d'outils par nixpkgs-multiverse (accepted, amendé : repli sur un paquet maison quand l'index n'a pas la dernière version) — `forge/adr/0002-selection-versions-nixpkgs-multiverse.md`

## Dette technique identifiée

1. **[PLAN] Rien ne protège PHP et FrankenPHP maison du ramasse-miettes Nix** — `flake.nix:46` — le plan dit ces paquets « compilés une fois puis gardés dans le store », mais seuls les shells ouverts les retiennent : un `nix store gc` sans shell ouvert impose une recompilation complète au prochain `nix develop`. Laissé en dette par l'utilisateur. Action : poser une racine de GC (`nix develop --profile`, nix-direnv, ou `--out-link`).
2. **Symfony 8.2 en beta** — `app/composer.json` — `composer update` à la sortie de 8.2.0 stable, puis retrait de `minimum-stability: beta`.
3. **Surcharges en attente de nixpkgs** — `infra/packages/php.nix`, `infra/packages/mago.nix` — source de PHP 8.6.0, ICU de `intl` et binaire de Mago à retirer quand multiverse indexe ces versions ; chaque surcharge porte sa condition de retrait.
4. **Chromium non épinglé par un lockfile** — `app/vendor/playwright-php/playwright` — `playwright` `^1.62` s'installe sans lockfile : la version de Chromium suit la dernière version publiée compatible.
5. **Mode worker sans test automatisé** — `app/tests/E2E/HomePageBrowserTest.php` — la suite E2E passe par le noyau de test : le pilotage des threads et la surveillance de fichiers de FrankenPHP ne se vérifient qu'à la main.
6. **SQLite sans clés étrangères ni WAL** — `app/config/packages/doctrine.yaml` — la connexion de test rapporte `foreign_keys = 0` et `journal_mode = delete` : à régler avec la première story qui crée un schéma, avant les premières relations et les écritures concurrentes des workers.

## Leçons apprises

- **Tailwind v4 lit tout fichier non ignoré du projet, Markdown compris** : sans `@source` explicites, une documentation ou des skills générés ajoutent des utilitaires à la feuille.
- **Mago vérifie un chemin donné en argument même hors de `[source].paths`** : un hook qui lui passe des fichiers doit filtrer sur les mêmes chemins que `mago.toml`.
- **`.DELETE_ON_ERROR` ne doit pas couvrir une cible qui porte un état** : une base de données produite par make se déclare `.PRECIOUS`.
- **`prefer-stable` choisit une 0.x stable plutôt qu'une 1.0 beta** : un paquet visé en beta s'exige par une contrainte explicite (`^1.0`).
- **Deux révisions de nixpkgs se mélangent sans risque tant qu'elles partagent glibc et GCC** : à vérifier par les closures avant de lier une bibliothèque de l'autre révision.
