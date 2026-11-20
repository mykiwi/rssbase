# Review — Amorcer la stack technique avec un hello world

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `forge/story/001-t-stack-hello-world/`
> **Amont** : `plan.md`
> **Diff examiné** : branche `001-t-stack-hello-world` contre `main` (`main...HEAD`), 30 commits, ~100 fichiers écrits à la main (~3 300 lignes), hors fichiers générés (`composer.lock`, `symfony.lock`, `config/reference.php`, `config/schema.json`, `.castor.stub.php`)

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 1
- **Mineurs restants** : 1 / 10
- **Statut** : **PRÊT À COMMITER**

`/forge:sync`, qui reprendra le mineur ouvert (racine de GC Nix) en dette, puis `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

- [x] **[ROBUSTESSE] Une migration en échec fait supprimer la base de dev par make** — `app/Makefile:50-52` — `.DELETE_ON_ERROR` s'appliquait à `var/data_dev.db`, un état vivant : une migration qui écrit puis échoue, ou un CTRL+C pendant la recette, effaçait la base. Corrigé : `.PRECIOUS: var/data_dev.db` ; sur un Makefile minimal, make supprimait la cible sans la ligne et la garde avec.

## Mineurs

- [x] **[ROBUSTESSE] `castor qa` formate après le make partagé** — `.castor/qa.php:21-27` — un fichier reformaté dans `src/` ou `templates/` rendait la feuille de style périmée, et les jobs `phpunit` et `e2e` lançaient chacun `tailwind:build`. Corrigé : `format()` passe avant `\make(...)`.
- [x] **[BUG] Le hook passe Mago sur des fichiers que `castor qa` ne vérifie pas** — `.claude/hooks/mago.sh:15-20` — `"$app"/*.php` prenait tout PHP sous `app/`. Corrigé : motifs limités aux chemins `[source]` de `app/mago.toml` ; `importmap.php` passe (code 2 avant), un fichier fautif de `src/` est toujours rejeté.
- [x] **[ROBUSTESSE] Un second `castor start` prend le socket d'admin du premier** — `castor.php:24-27` — le second serveur s'emparait du socket et `castor stop` ne pouvait plus arrêter le premier. Corrigé : `castor start` refuse de démarrer quand le socket répond ; un socket laissé par un crash refuse la connexion et ne bloque pas.
- [x] **[UX] `docker:build` masque toute la sortie de la construction Nix** — `.castor/docker.php:18-27` — `capture()` taisait stderr et `docker load` tournait en silencieux. Corrigé : `nix build` par `run()`, `docker load` visible ; seule la liste des couches du script de diffusion est coupée, et un démon injoignable donne son message.
- [ ] **[PLAN] Rien ne protège PHP et FrankenPHP maison du ramasse-miettes Nix** — `flake.nix:46` — le plan dit ces paquets « compilés une fois puis gardés dans le store », mais seuls les shells ouverts les retiennent : un `nix store gc` sans shell ouvert impose une recompilation complète au prochain `nix develop`. Laissé en dette par l'utilisateur. Action : poser une racine de GC (`nix develop --profile`, nix-direnv, ou `--out-link`).
- [x] **[UX] Tailwind n'est pas reconstruit pendant `castor start`** — `castor.php:34-46` — une classe ajoutée dans un gabarit restait sans style jusqu'à la relance. Corrigé : `castor start` lance `tailwind:build --watch` à côté de FrankenPHP et l'arrête quand FrankenPHP s'arrête (`castor stop`, CTRL+C ou crash, vérifiés pour les deux premiers).
- [x] **[TEST] Le test de connexion lit une API interne de DBAL** — `app/tests/Integration/DatabaseConnectionTest.php:17-20` — `Connection::getParams()` est `@internal` et ne dit que le chemin configuré. Corrigé : le test lit `pragma_database_list`, et échoue quand la connexion ouvre un autre fichier.
- [x] **[CONV] `composer.json` ne déclare ni `ext-pdo_sqlite` ni `ext-intl`** — `app/composer.json:10-13` — `check-platform-reqs` ne protégeait pas des extensions dont l'application dépend. Corrigé : les deux extensions sont requises, et le hash du verrou est rafraîchi.
- [x] **[DOC] `AGENTS.md` dit toutes les versions épinglées par multiverse** — `AGENTS.md:8-13` — faux pour les paquets de `infra/packages/`, et « Twig with Twig and Live Components » était maladroit. Corrigé, avec les commandes de Mate et de `castor start` mises à jour.
- [x] **[DOC] L'invocation de Mate ne marche que depuis la racine du dépôt** — `app/mate/config.php:12` — `nix develop -c app/vendor/bin/mate` échouait depuis `app/`. Corrigé : tâche `castor mate`, que castor trouve depuis tout dossier du dépôt ; `mate.invocation` vaut `nix develop -c castor mate` et `mate discover` a régénéré les instructions.

## Points positifs

- **Exposition réseau** : tout écoute sur la boucle locale, y compris le port publié par Compose, et le profiler n'existe qu'en dev (`debug:router` vérifié en prod et en test, `/_profiler` en 404 en test).
- **Graphe du `Makefile`** : le couple `cmp` et `mv` empêche bien la réinstallation de Chromium à chaque mise à jour de paquet (testé), et `--jobs` n'a pas d'autre course.
- **Closures Nix cohérentes** : malgré deux révisions de nixpkgs, FrankenPHP et l'image n'embarquent qu'une glibc 2.44, un GCC 16.2 et une ICU 79.1.
- **Tests qui échouent pour de vrai** : le contrôle du `border-radius` de la carte échoue sans feuille de style, et `HomePageTest` vérifie le balisage du kit plutôt que le seul texte.
- **Hook Mago** : la garde de récursion et la lecture de stdin survivent au passage par `nix develop` (testé).
