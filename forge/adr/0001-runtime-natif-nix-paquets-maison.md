# ADR-0001 — Runtime de développement natif Nix avec PHP et FrankenPHP en paquets maison

- **Statut** : proposed
- **Date** : 2026-11-20
- **Déciders** : auteur du projet
- **Story liée** : `forge/story/001-t-stack-hello-world/`

## Contexte

Le dépôt doit être amorcé sur PHP 8.6 et FrankenPHP en mode worker, cibles déclarées dans `forge/stack.md`. Le 2026-11-20, au lendemain de la sortie de
PHP 8.6.0, aucun canal ne fournit ce couple prêt à l'emploi : aucune image `dunglas/frankenphp` ne porte de tag `php8.6`, nixpkgs compile `frankenphp`
1.13.5 contre PHP 8.5.12, et `php86` 8.6.0 est fusionné sur `master` sans avoir atteint `nixos-unstable`. `forge/stack.md` prévoyait une exécution dans
Docker (« stack Docker en premier ») et réservait Nix à l'outillage local. Il faut décider où tourne PHP pendant le développement et qui construit le
runtime, avant d'écrire le premier fichier applicatif.

Le statut reste `proposed` tant que l'étape 1 du plan n'a pas prouvé que PHP 8.6.0 ZTS et FrankenPHP 1.13.6 se compilent et servent une page.

## Decision drivers

- **Versions à jour sans attendre un tiers** — PHP 8.6.0 et la dernière release de FrankenPHP dès maintenant, sans dépendre du calendrier d'une image
  ou d'un canal.
- **Un seul runtime PHP** — la console, les tests et le serveur web utilisent le même binaire et les mêmes extensions.
- **Le strict nécessaire** — le runtime ne contient que les extensions que l'application exige.
- **Versions lisibles** — une version se lit et se change dans le dépôt, de préférence par son numéro plutôt que par un hash.

## Options considérées

### Option A — Shell Nix natif et paquets maison

PHP et FrankenPHP sont deux dérivations du dépôt, `infra/packages/php.nix` (PHP 8.6.0 ZTS, `embed`, extensions minimales) et
`infra/packages/frankenphp.nix` (FrankenPHP 1.13.6 compilé contre ce PHP). `nix develop` les fournit avec Composer, castor, Mago et Node. Le serveur
tourne sur l'hôte. Docker se réduit à une image de runtime produite par le flake, dans laquelle `compose.yaml` monte `./app`.

- Aligne avec **Versions à jour** : oui. Le dépôt fixe lui-même la version de PHP et celle de FrankenPHP, y compris quand nixpkgs est en retard.
- Aligne avec **Un seul runtime PHP** : oui. Le même paquet sert à la CLI, à Composer, à castor, au serveur et à l'image.
- Aligne avec **Le strict nécessaire** : oui. La liste d'extensions est déclarée dans `php.nix`.
- Aligne avec **Versions lisibles** : partiel. Les numéros sont dans le dépôt, mais une dérivation compilée depuis les sources impose deux hashes
  pour FrankenPHP (source, modules Go) et un pour PHP tant que nixpkgs ne fournit pas la 8.6.0.
- Coût / trade-off : PHP ZTS et FrankenPHP ne sont pas dans le cache binaire, ils se compilent localement à la première entrée dans le shell et à
  chaque montée de version. Deux dérivations à maintenir.

### Option B — Développement dans Docker avec une image amont

Tout tourne dans un conteneur `dunglas/frankenphp`, le flake ne fournit que castor et le client Docker. C'est le modèle des dépôts de référence
(`jolicode/docker-starter`) et celui que `forge/stack.md` annonçait.

- Aligne avec **Versions à jour** : non. Aucun tag `php8.6` n'existe, il faut attendre sa publication ou partir en PHP 8.5.
- Aligne avec **Un seul runtime PHP** : oui, celui du conteneur, à condition de lancer aussi la console et les tests dedans.
- Aligne avec **Le strict nécessaire** : non. L'image amont embarque son propre jeu d'extensions et de modules.
- Aligne avec **Versions lisibles** : oui, par le tag de l'image.
- Coût / trade-off : aucune compilation, gabarits existants réutilisables tels quels, mais boucle de développement à travers des montages et un
  runtime dont le projet ne maîtrise pas le contenu.

### Option C — Dockerfile compilant FrankenPHP sur `php:8.6.0-zts`

Le projet écrit son propre Dockerfile à partir de l'image PHP officielle, déjà publiée en 8.6.0, et y compile FrankenPHP. Le développement se fait
dans ce conteneur.

- Aligne avec **Versions à jour** : oui.
- Aligne avec **Un seul runtime PHP** : partiel. Le shell Nix exigé par `AGENTS.md` pour les outils locaux apporterait un second PHP dès qu'un outil
  s'exécute hors conteneur.
- Aligne avec **Le strict nécessaire** : partiel. Les extensions sont choisies, mais sur la base de l'image officielle.
- Aligne avec **Versions lisibles** : oui.
- Coût / trade-off : un Dockerfile de compilation à maintenir, et deux définitions d'environnement (flake et Dockerfile) à garder alignées.

### Option D — `frankenphp` de nixpkgs tel quel

Le shell Nix prend le paquet `frankenphp` de nixpkgs sans dérivation maison.

- Aligne avec **Versions à jour** : non. Version 1.13.5 compilée contre PHP 8.5.12.
- Aligne avec **Un seul runtime PHP** : oui.
- Aligne avec **Le strict nécessaire** : non. Jeu d'extensions par défaut de nixpkgs.
- Aligne avec **Versions lisibles** : oui.
- Coût / trade-off : aucun code à maintenir et cache binaire disponible, au prix des deux premiers drivers.

## Décision

**Option retenue : A**

C'est la seule option qui satisfait à la fois **Versions à jour** et **Le strict nécessaire** : B et D attendent un tiers pour PHP 8.6, C y arrive
mais au prix d'une seconde définition du runtime, ce qui affaiblit **Un seul runtime PHP**. L'entorse à **Versions lisibles** (des hashes pour les
dérivations maison) est acceptée parce que ce driver est le dernier de la liste et qu'elle se limite à deux fichiers.

## Conséquences

**Positives**

- Le projet choisit ses versions de PHP et de FrankenPHP le jour de leur sortie.
- La console, les tests, le serveur et l'image partagent un binaire et une liste d'extensions.
- Pas de montage ni de conteneur dans la boucle de développement.
- L'arborescence sépare le code (`app/`) de la définition du runtime (`infra/packages/`).

**Négatives / coûts assumés**

- Compilation locale de PHP ZTS et de FrankenPHP à la première entrée dans le shell et à chaque montée de version, faute de cache binaire.
- Des hashes à rafraîchir à chaque release de FrankenPHP, et pour PHP tant que nixpkgs ne fournit pas la version voulue.
- Les gabarits Docker existants ne s'appliquent plus tels quels : les tâches castor exécutent en direct ce que les dépôts de référence lancent dans
  un conteneur.
- L'image produite ne contient pas l'application. Elle prouve le runtime conteneurisé, elle n'est pas déployable.
- Les binaires que certains outils téléchargent eux-mêmes (Tailwind, Chromium, `npx`) restent hors du périmètre de Nix.
- `forge/stack.md` ne décrit plus l'exécution réelle tant qu'il n'est pas recartographié.

**Suites obligatoires**

- [ ] Prouver la compilation et le service d'une page avec PHP 8.6.0 ZTS et FrankenPHP 1.13.6, puis passer cet ADR en `accepted` (étape 1 du plan 001).
- [ ] Recartographier `forge/stack.md` par `/forge:stack` (étape 10 du plan 001).
- [ ] Décider comment se construit l'image autonome déployable. Aucune story ne le porte : à cadrer avec le déploiement.

## Links

- Artifact source : `forge/story/001-t-stack-hello-world/plan.md`
- ADR liés : ADR-0002 (sélection des versions par `nixpkgs-multiverse`)
- Références externes : nixpkgs PR 590480 (`php86: 8.6.0RC4 -> 8.6.0`), `pkgs/by-name/fr/frankenphp/package.nix` de nixpkgs,
  https://github.com/jolicode/docker-starter
