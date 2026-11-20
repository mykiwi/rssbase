# ADR-0002 — Sélection des versions d'outils par nixpkgs-multiverse

- **Statut** : proposed
- **Date** : 2026-11-20
- **Déciders** : auteur du projet
- **Story liée** : `forge/story/001-t-stack-hello-world/`

## Contexte

L'ADR-0001 fait du shell Nix l'environnement de développement. Le flake doit fournir plusieurs outils dont les versions utiles ne coïncident pas dans
une même révision de nixpkgs : PHP 8.6, Mago, Node, Go pour compiler FrankenPHP. Dans un flake classique, la version d'un outil est une conséquence
du commit de nixpkgs épinglé dans `flake.lock`, et en changer une seule revient à déplacer tout le jeu de paquets ou à ajouter une seconde entrée. Il
faut décider comment le dépôt exprime « telle version de tel outil ».

Le statut reste `proposed` tant que l'étape 1 du plan n'a pas montré que les pins par version se combinent avec les dérivations maison.

## Decision drivers

- **Versions lisibles** — la version de chaque outil se lit et se change dans `flake.nix`, sans chercher quel commit de nixpkgs la fournit.
- **Indépendance des outils** — monter la version de PHP ne force pas à monter celle de Mago ou de Node.
- **Une seule entrée de flake** — un seul verrou à suivre et à mettre à jour.

## Options considérées

### Option A — `nixpkgs-multiverse`

Le flake a pour unique entrée `github:fzakaria/nixpkgs-multiverse`, qui indexe chaque version de chaque paquet livrée par `nixos-unstable` et la rend
adressable par son numéro. Un jeu de paquets de base est choisi par date, les outils sensibles par version.

- Aligne avec **Versions lisibles** : oui. `php86 = "8.6.0"` se lit tel quel.
- Aligne avec **Indépendance des outils** : oui. Chaque pin se résout dans la révision qui porte cette version.
- Aligne avec **Une seule entrée de flake** : oui.
- Coût / trade-off : constatés le 2026-11-20.
  - L'index suit `nixos-unstable` avec retard : PHP 8.6.0, fusionné sur `master`, n'y figure pas encore.
  - Les jeux imbriqués comme `phpPackages` ne sont pas indexés, castor et Composer suivent donc la révision de PHP.
  - Chaque révision distincte sollicitée coûte un téléchargement et une évaluation de nixpkgs.
  - Le projet date d'août 2026.

### Option B — Plusieurs entrées `nixpkgs` épinglées par commit

Une entrée par besoin de version (`nixpkgs`, `nixpkgs-php`, `nixpkgs-mago`…), chacune verrouillée sur le commit qui fournit la version voulue.

- Aligne avec **Versions lisibles** : non. `flake.nix` montre des noms d'entrées et `flake.lock` des hashes, il faut évaluer pour connaître la version.
- Aligne avec **Indépendance des outils** : oui.
- Aligne avec **Une seule entrée de flake** : non.
- Coût / trade-off : aucune dépendance à un projet tiers, mais trouver le bon commit pour une version donnée est une recherche manuelle.

### Option C — Une entrée `nixos-unstable` et des surcharges locales

Une seule entrée nixpkgs classique. Toute version qui ne convient pas est obtenue par une surcharge de source dans `infra/packages/`.

- Aligne avec **Versions lisibles** : partiel. Les versions surchargées sont lisibles, les autres suivent le verrou.
- Aligne avec **Indépendance des outils** : partiel. Chaque outil à désolidariser devient une dérivation maison avec son hash.
- Aligne avec **Une seule entrée de flake** : oui.
- Coût / trade-off : aucune dépendance tierce et cache binaire maximal, mais le nombre de paquets maison grandit avec chaque écart de version.

## Décision

**Option retenue : A**

C'est la seule option qui satisfait les trois drivers. B abandonne **Versions lisibles** et **Une seule entrée de flake**, C ne tient
**Indépendance des outils** qu'en multipliant les dérivations maison. Le retard de l'index est accepté parce que l'ADR-0001 prévoit déjà des paquets
maison pour PHP et FrankenPHP, qui absorbent le cas où une version n'est pas encore indexée.

## Conséquences

**Positives**

- Monter un outil revient à changer une chaîne de version dans `flake.nix`.
- Le dépôt n'a qu'une entrée de flake.
- La liste des versions disponibles se consulte sans rien télécharger.

**Négatives / coûts assumés**

- Dépendance à un projet récent, maintenu par une personne. Le repli se limite à `flake.nix` : revenir à une entrée `nixpkgs` épinglée.
- Une version absente de `nixos-unstable` n'est pas sélectionnable, il faut alors une surcharge locale et son hash.
- castor et Composer ne se choisissent pas par version, ils suivent la révision qui fournit PHP.
- Des pins qui ne cohabitent dans aucune révision multiplient les téléchargements de nixpkgs et les bibliothèques système en double dans le store.
- `flake.lock` continue de porter un hash, celui de l'entrée multiverse.

**Suites obligatoires**

- [ ] Vérifier à l'étape 1 du plan 001 que les pins se résolvent dans le moins de révisions possible, puis passer cet ADR en `accepted`.
- [ ] Retirer la surcharge de source de `infra/packages/php.nix` dès que `php86` 8.6.0 est indexé, si elle a été nécessaire.

## Links

- Artifact source : `forge/story/001-t-stack-hello-world/plan.md`
- ADR liés : ADR-0001 (runtime natif Nix et paquets maison), dont celui-ci dépend
- Références externes : https://github.com/fzakaria/nixpkgs-multiverse,
  https://fzakaria.com/2026/08/09/nixpkgs-multiverse-every-version-that-ever-existed
