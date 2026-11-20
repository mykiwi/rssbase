# Stack technique — rssbase

> Dernière mise à jour : 2026-11-20 — **stack cible, non encore amorcée**. Le dépôt ne contient aucun manifeste : chaque entrée est une intention déclarée par l'utilisateur (_source : déclaratif utilisateur_) ou marquée _non renseigné_. À recartographier en mode Éditer dès le premier scaffolding, pour remplacer les intentions par des preuves fichier.

## Vue d'ensemble

Monolithe Symfony servi par FrankenPHP : un lecteur RSS adossé à AT Protocol (login, stockage des données utilisateur dans le PDS). Rendu côté serveur en Twig et Live Components, base SQLite, exécution sous Docker.

| Couche | Techno principale |
|---|---|
| Langage(s) | PHP 8.6 |
| Backend | Symfony 8.2, FrankenPHP |
| Frontend | Twig Components, Live Components, UX CSS, UX Toolkit (kit Shadcn) |
| Données | SQLite |
| Ops | Docker, Docker Compose |
| DevOps | _non renseigné_ (pas de CI) |

## Langages & runtimes

- **PHP** `8.6` (cible) — _source : déclaratif utilisateur_
- **FrankenPHP** — serveur d'application PHP — _source : déclaratif utilisateur_

## Backend

- **Framework** : Symfony `8.2` (cible) — _source : déclaratif utilisateur_
- **ORM / données** : _non renseigné_ (Doctrine probable, non décidé)
- **Libs structurantes** : client AT Protocol (OAuth, records, spaces) — _non renseigné_, choix à trancher en `/tech-plan` ou `/adr`

## Frontend

- **Composants** : Symfony UX Twig Components et Live Components — _source : déclaratif utilisateur_
- **CSS** : Symfony UX CSS (https://ux.symfony.com/css) — _source : déclaratif utilisateur_
- **UI kit** : Symfony UX Toolkit, kit Shadcn (https://ux.symfony.com/toolkit/kits/shadcn) — _source : déclaratif utilisateur_
- **Bundler / build** : _non renseigné_
- **TypeScript** : _non renseigné_

## Données & stockage

- **Base de données** : SQLite — _source : déclaratif utilisateur_. Héberge aussi le stockage local temporaire des données privées tant que les spaces AT Protocol ne sont pas utilisables (plan B acté dans l'atelier de vision).
- **Données utilisateur portables** : PDS AT Protocol de l'utilisateur (records publics, spaces pour le privé) — service externe, _source : atelier de vision_

## Ops / Infrastructure

- **Conteneurisation** : Docker — _source : déclaratif utilisateur_
- **Orchestration** : Docker Compose (stack Docker en premier) — _source : déclaratif utilisateur_
- **Hébergement de production** : _non renseigné_
- **CDN / reverse proxy** : _non renseigné_
- **Gestion des secrets** : _non renseigné_
- **Environnements** : _non renseigné_

## DevOps / CI-CD

- **Pipeline CI** : _non renseigné_
- **Tests** : PHPUnit, php-playwright — _source : déclaratif utilisateur_
- **Analyse statique / style** : Mago — _source : déclaratif utilisateur_
- **Hooks git / automatisation deps** : _non renseigné_
- **Déploiement** : _non renseigné_

## Monitoring / observabilité

- **Erreurs** : _non renseigné_
- **Métriques / traces** : _non renseigné_
- **Logs** : _non renseigné_

## Outillage de développement local

- **Environnement** : Nix, `flake.nix` avec `devShells.default` — _source : déclaratif utilisateur, `AGENTS.md`_
- **Tâches** : castor en priorité, Makefile en complément — _source : déclaratif utilisateur, `AGENTS.md`_
- **Assistants IA** : MCP Mate, MCP Playwright — _source : déclaratif utilisateur_
- **Services de dev** : Docker Compose — _source : déclaratif utilisateur_

## Contraintes & dette de stack connues

- **Versions non vérifiées** : PHP 8.6 et Symfony 8.2 sont des cibles déclarées ; à confirmer par `composer.json` et le lockfile au scaffolding.
- **Non amorcée** : aucun template récupéré. Le scaffolding (projet Symfony, `flake.nix`, `Dockerfile`, `compose.yaml`, tâches castor) reste à faire.
- **Nommage** : projet `rssbase`, dossier du dépôt `symfony-atproto`.
- **Dépendance protocole** : les spaces AT Protocol (données privées) ne sont pas garantis disponibles ni outillés en PHP ; le stockage SQLite temporaire en dépend.

## Changelog

- 2026-11-20 — Création — inventaire d'intention (stack cible, non amorcée)
