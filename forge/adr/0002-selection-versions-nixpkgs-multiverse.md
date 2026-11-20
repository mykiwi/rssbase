# ADR-0002 — Sélection des versions d'outils par nixpkgs-multiverse

- **Statut** : accepted
- **Date** : 2026-11-20
- **Déciders** : auteur du projet
- **Story liée** : `forge/story/001-t-stack-hello-world/`

## Contexte

L'ADR-0001 fait du shell Nix l'environnement de développement. Le flake doit fournir plusieurs outils dont les versions utiles ne coïncident pas dans
une même révision de nixpkgs : PHP 8.6, Mago, Node, Go pour compiler FrankenPHP. Dans un flake classique, la version d'un outil est une conséquence
du commit de nixpkgs épinglé dans `flake.lock`, et en changer une seule revient à déplacer tout le jeu de paquets ou à ajouter une seconde entrée. Il
faut décider comment le dépôt exprime « telle version de tel outil ».

La story 001 a montré que l'index ne suffit pas toujours à obtenir la dernière version publiée :

- PHP 8.6.0 n'est pas indexé : `infra/packages/php.nix` surcharge la source de la recette `php86` 8.6.0RC4.
- Mago 1.60.2 n'est pas indexé (1.59.0 l'est), et le compiler exige rustc 1.100, absent de nixpkgs : `infra/packages/mago.nix` épingle le binaire
  officiel.
- symfony-lsp 0.24.2 vient du binaire officiel : `infra/packages/symfony-lsp.nix`.
- ICU 79.1 est dans le jeu daté, mais la recette nixpkgs de `intl` impose ICU 73 : `infra/packages/php.nix` lui passe ICU 79.1.

Il faut donc aussi décider quand on quitte l'index.

## Decision drivers

- **Dernière version publiée** — chaque outil est à sa dernière version publiée, toutes lignes confondues (LTS comprises), sans attendre le
  calendrier de nixpkgs.
- **Versions lisibles** — la version de chaque outil se lit et se change dans `flake.nix` ou dans le paquet de `infra/packages/` qui la porte, sans
  chercher quel commit de nixpkgs la fournit.
- **Indépendance des outils** — monter la version de PHP ne force pas à monter celle de Mago ou de Node.
- **Une seule entrée de flake** — un seul verrou à suivre et à mettre à jour.

## Options considérées

### Option A — `nixpkgs-multiverse`

Le flake a pour unique entrée `github:fzakaria/nixpkgs-multiverse`, qui indexe chaque version de chaque paquet livrée par `nixos-unstable` et la rend
adressable par son numéro. Un jeu de paquets de base est choisi par date, les outils sensibles par version.

- Aligne avec **Dernière version publiée** : partiel. L'index suit `nixos-unstable` avec retard, et certains paquets n'y figurent pas.
- Aligne avec **Versions lisibles** : oui. `php86 = "8.6.0"` se lit tel quel.
- Aligne avec **Indépendance des outils** : oui. Chaque pin se résout dans la révision qui porte cette version.
- Aligne avec **Une seule entrée de flake** : oui.
- Coût / trade-off : constatés le 2026-11-20.
  - PHP 8.6.0 et Mago 1.60.2 manquent à l'index.
  - Les jeux imbriqués comme `phpPackages` ne sont pas indexés, castor et Composer suivent donc la révision de PHP.
  - Chaque révision distincte sollicitée coûte un téléchargement et une évaluation de nixpkgs. Le jeu daté et la révision de `php86` partagent
    glibc 2.44 et GCC 16.2 : les mélanger ne double aucune bibliothèque système.
  - Le projet date d'août 2026.

### Option B — Plusieurs entrées `nixpkgs` épinglées par commit

Une entrée par besoin de version (`nixpkgs`, `nixpkgs-php`, `nixpkgs-mago`…), chacune verrouillée sur le commit qui fournit la version voulue.

- Aligne avec **Dernière version publiée** : partiel. Même limite que A : une version absente de nixpkgs n'a pas de commit à épingler.
- Aligne avec **Versions lisibles** : non. `flake.nix` montre des noms d'entrées et `flake.lock` des hashes, il faut évaluer pour connaître la version.
- Aligne avec **Indépendance des outils** : oui.
- Aligne avec **Une seule entrée de flake** : non.
- Coût / trade-off : aucune dépendance à un projet tiers, mais trouver le bon commit pour une version donnée est une recherche manuelle.

### Option C — Une entrée `nixos-unstable` et des surcharges locales

Une seule entrée nixpkgs classique. Toute version qui ne convient pas est obtenue par une surcharge de source dans `infra/packages/`.

- Aligne avec **Dernière version publiée** : oui, au prix d'une surcharge par outil en retard.
- Aligne avec **Versions lisibles** : partiel. Les versions surchargées sont lisibles, les autres suivent le verrou.
- Aligne avec **Indépendance des outils** : partiel. Chaque outil à désolidariser devient une dérivation maison avec son hash.
- Aligne avec **Une seule entrée de flake** : oui.
- Coût / trade-off : aucune dépendance tierce et cache binaire maximal, mais le nombre de paquets maison grandit avec chaque écart de version.

## Décision

**Option retenue : A, avec repli sur un paquet maison quand l'index n'a pas la dernière version**

La règle, pour chaque outil :

1. Si `nixpkgs-multiverse` propose la dernière version publiée, le flake la prend : pin par version dans `solvePins`, ou paquet du jeu daté.
2. Sinon, un paquet maison dans `infra/packages/` la fournit, sous la forme la plus légère qui marche :
   - une surcharge de source sur la recette indexée la plus proche (PHP 8.6.0 sur `php86` 8.6.0RC4) ;
   - le binaire officiel épinglé par URL et hash, quand le construire exige ce que nixpkgs n'a pas (Mago, symfony-lsp) ;
   - une dépendance du jeu daté à la place de celle que la recette nixpkgs impose (ICU 79.1 pour `intl`).
3. Chaque paquet maison dit en commentaire la condition de son retrait : le jour où l'index propose la version, il redevient un pin.

A reste le seul choix qui tient **Versions lisibles**, **Indépendance des outils** et **Une seule entrée de flake**. Aucune option ne tient seule
**Dernière version publiée** sans surcharges : C les rend systématiques, alors que A les limite aux outils que l'index n'a pas encore rattrapés.

## Conséquences

**Positives**

- Monter un outil indexé revient à changer une chaîne de version dans `flake.nix`.
- Aucun outil n'attend le calendrier de nixpkgs.
- Le dépôt n'a qu'une entrée de flake.
- La liste des versions disponibles se consulte sans rien télécharger.

**Négatives / coûts assumés**

- Dépendance à un projet récent, maintenu par une personne. Le repli se limite à `flake.nix` : revenir à une entrée `nixpkgs` épinglée.
- Chaque paquet maison a des hashes à rafraîchir à chaque version (source, modules Go, binaire).
- Un binaire officiel n'est pas reconstruit depuis ses sources : on fait confiance à l'amont pour son contenu.
- Les paquets maison compilés (PHP, FrankenPHP) ne sont pas dans le cache binaire public : chaque changement les recompile en local.
- castor et Composer ne se choisissent pas par version, ils suivent la révision qui fournit PHP. Le shell de dev coupe la vérification de version
  de castor (`CASTOR_DISABLE_VERSION_CHECK`), qui ne pourrait que suggérer un `self-update` hors du flake.
- `flake.lock` continue de porter un hash, celui de l'entrée multiverse.

**Suites obligatoires**

- [x] Vérifier à l'étape 1 du plan 001 que les pins se résolvent dans le moins de révisions possible, puis passer cet ADR en `accepted`.
- [ ] Retirer la surcharge de source de `infra/packages/php.nix` dès que `php86` 8.6.0 est indexé.
- [ ] Remplacer `infra/packages/mago.nix` par un pin dès que l'index propose la dernière version de Mago.
- [ ] Retirer la surcharge d'ICU de `intl` dès que la recette nixpkgs utilise la dernière ICU.
- [x] Vérifier Node contre sa dernière version publiée : 26.13.1 l'est au 2026-11-20, Node 27 n'existe qu'en alpha.
- [x] Vérifier castor et Composer, qui suivent la révision de PHP, contre leur dernière version publiée : 1.9.1 et 2.11.2 le sont au 2026-11-20.

## Links

- Artifact source : `forge/story/001-t-stack-hello-world/plan.md`
- ADR liés : ADR-0001 (runtime natif Nix et paquets maison), dont celui-ci dépend
- Références externes : https://github.com/fzakaria/nixpkgs-multiverse,
  https://fzakaria.com/2026/08/09/nixpkgs-multiverse-every-version-that-ever-existed
