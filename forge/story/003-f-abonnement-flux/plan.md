# Plan technique — S'abonner à des flux depuis RssBase et garder ses abonnements dans son compte AT Protocol

> **But** : figer comment RssBase écrit et lit les abonnements dans le PDS de l'utilisateur, lit ceux de Skyreader, ouvre les adresses saisies et publie son lexicon.
> **Registre** : technique
> **Story** : `forge/story/003-f-abonnement-flux/`
> **Amont** : `pitch.md`

## Approche retenue

Aucune donnée d'abonnement ne passe par la base de RssBase. « Mes flux » relit à chaque rendu trois collections publiques du PDS de l'utilisateur, par
`com.atproto.repo.listRecords` lancées en parallèle, avec les curseurs suivis jusqu'au bout :

- `io.rssbase.feed.subscription`, les abonnements de RssBase ;
- `io.rssbase.feed.hidden`, les masques posés sur des flux Skyreader ;
- `app.skyreader.feed.subscription`, les abonnements de Skyreader, dont seuls ceux sans `sourceType` ou en `rss` sont gardés.

Ces lectures sont anonymes. Une liste fusionnée en tire une ligne par flux, identifiée par l'adresse normalisée du flux. Les flux masqués sont retirés,
et la liste est classée par `createdAt` décroissant, avec la plus ancienne date quand un flux est suivi des deux côtés. Les écritures (`createRecord`,
`deleteRecord`) passent par un client du dépôt qui porte les jetons de la session, une preuve DPoP liée au jeton d'accès (`ath`) et le nonce propre au
PDS, distinct de celui du serveur d'autorisation.

L'identité d'un flux est un contrat du lexicon. L'adresse retenue est celle obtenue après les redirections. Sa forme normalisée ignore le schéma, met
l'hôte en minuscules, retire le port par défaut, le fragment et la barre finale du chemin, et garde la requête telle quelle. La clé de record (type
`any`) est le SHA-256 de cette forme, en base32 minuscule sans remplissage, soit 52 caractères. La même clé sert à l'abonnement et au masque d'un flux :
`createRecord` refuse un doublon concurrent, « Annuler » recrée le même record avec son `createdAt` d'origine, et masquer ou démasquer ne demande pas
de recherche. Les lecteurs n'ont pas à se fier à la clé : RssBase dédoublonne à la lecture.

S'abonner suit le modèle de la connexion : un formulaire POST classique avec jeton CSRF, une réponse 422 qui garde la saisie en cas d'échec, et une page
de choix quand un site annonce plusieurs flux. Le flux choisi est rouvert avant d'être enregistré. Les adresses saisies passent par un client HTTP
à part (`feed.client`). Il reprend le montage de `atproto.client` (`BoundedHttpClient` devant `NoPrivateNetworkHttpClient`), avec des limites
propres aux flux : 2 Mio, 5 redirections, 5 s d'inactivité et 10 s au total, http et https. La lecture d'un flux se limite à RSS 2.0 et Atom 1.0 par
l'extension `dom`, sans bibliothèque : titre, lien du site, et découverte par `<link rel="alternate">` dans une page HTML. La liste, « Se désabonner »
et « Annuler » sont un Live Component. Le flux retiré est une LiveProp non modifiable, que la somme de contrôle du composant protège. Un contrôleur
Stimulus retire le message d'annulation après quelques secondes.

La permission demandée devient `atproto repo?collection=io.rssbase.feed.subscription&collection=io.rssbase.feed.hidden`. `Scope::grantsWrite()`
vérifie ces deux collections, et `repo:*` ou `transition:generic` comptent toujours, ce qui garde l'écriture des comptes connectés avant cette
story. Les quatre fournisseurs proposés n'annoncent que `atproto` et `transition:*` dans `scopes_supported`, et aucune connexion n'a encore été
essayée contre un vrai fournisseur. La première étape vérifie donc avec un vrai compte que la permission restreinte est accordée, avant d'écrire le
reste.

Le lexicon vit à la racine du dépôt (`lexicons/io/rssbase/feed/`), avec sa documentation. Une tâche castor le publie en records
`com.atproto.lexicon.schema` dans le compte AT Protocol de RssBase, que l'enregistrement `_lexicon.feed.rssbase.io` désigne. Le compte et
l'enregistrement DNS se créent à la main, hors du dépôt.

### Mécanismes mobilisés

- **Live Component** (`#[AsLiveComponent]`, `#[LiveAction]`, `#[LiveProp]`) : retire la ligne et propose « Annuler » sans rechargement, comme décidé
  au cadrage. C'est le premier du projet.
- **Contrôleur Stimulus** : masque le message d'annulation après le délai, sur le modèle de `app/assets/controllers/provider_filter_controller.js`.
- **Client HTTP scopé + décorateurs** (`NoPrivateNetworkHttpClient`, `BoundedHttpClient`) : le montage éprouvé de `atproto.client`, réutilisé pour
  `feed.client` avec des limites passées au constructeur au lieu des constantes actuelles.
- **`HttpClientInterface::stream()`** : lance les trois `listRecords` en même temps, pour qu'une visite attende le plus lent et non leur somme.
- **`DpopProofFactory::createProof()`** : son troisième argument (`$accessToken`) produit déjà la claim `ath` qu'exige un serveur de ressources.
- **Formulaire POST + CSRF + 422** : le modèle de `app/src/Controller/LoginController.php`, pour l'abonnement et la page de choix.
- **`AuthorizationFlow::start()`** : relancé pour le compte courant par la réapprobation, l'authentificateur existant remplaçant l'utilisateur de
  la session au retour.
- **`access_control` en refus par défaut** (`app/config/packages/security.yaml`) : les nouvelles routes et celle des composants sont réservées
  sans ligne de plus.
- **Tâche castor** : la publication du lexicon, à côté de `.castor/qa.php` et `.castor/docker.php`.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Adopter `app.skyreader.feed.subscription` comme format de RssBase | Tranché au pitch : RssBase garde la maîtrise d'un contrat qu'il versionne, et lit Skyreader en plus. |
| Clé `tid`, comme Skyreader et Glean | Deux appareils pourraient créer le même abonnement, et masquer, désabonner ou annuler demanderaient de retrouver le record dans la liste. |
| Permission set `include:io.rssbase.feed.*` | Il doit être résolvable au démarrage de chaque connexion, sinon la connexion échoue : il dépendrait du compte et du DNS du lexicon, pas encore créés. À reconsidérer une fois la publication faite. |
| Bibliothèque de flux (SimplePie, laminas-feed) | Trois champs ne justifient pas une dépendance. Le choix revient à `lecture-chronologique`, qui connaîtra ses besoins. |
| Formulaires et message flash pour « Annuler » | Fonctionnerait sans JavaScript, mais le cadrage a retenu le Live Component pour retirer la ligne sans rechargement. |
| Copie des abonnements dans SQLite | Contraire au principe 1 et sans besoin : la liste se relit en trois requêtes parallèles. |

## Modèle de données

Aucune table, aucune migration. La structure persistante est le lexicon publié, dans le PDS de l'utilisateur.

### Nouveau record `io.rssbase.feed.subscription`

`lexicons/io/rssbase/feed/subscription.json`, clé `any` (empreinte de l'adresse normalisée, voir §Approche retenue) :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `feedUrl` | `string`, format `uri` | non | adresse du flux après redirections, 2048 caractères au plus |
| `siteUrl` | `string`, format `uri` | oui | lien du site annoncé par le flux, 2048 au plus |
| `title` | `string` | oui | titre annoncé à l'abonnement, 512 au plus |
| `createdAt` | `string`, format `datetime` | non | date d'abonnement, gardée par « Annuler » |

### Nouveau record `io.rssbase.feed.hidden`

`lexicons/io/rssbase/feed/hidden.json`, même clé que l'abonnement du flux :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `feedUrl` | `string`, format `uri` | non | adresse du flux masqué, telle que Skyreader l'écrit |
| `createdAt` | `string`, format `datetime` | non | date du masque |

Les noms des champs sont ceux de Skyreader, pour qu'un lecteur qui lit l'un lise l'autre. Les deux records sont publics, comme tout record du dépôt,
jusqu'à `bascule-public-prive`. Un changement cassant donne une nouvelle NSID (principe 2). L'ajout d'un champ optionnel n'en est pas un.

### Modification de l'utilisateur en session (`AtProtoUser`)

`app/src/Security/AtProtoUser.php` : ajout de `?Pds $pds`, renseigné à la connexion depuis `Identity`. Une session ouverte avant cette story ne le
porte pas : `__unserialize()` le laisse à `null`, et l'adresse est alors retrouvée depuis le DID (`IdentityResolver::resolveDid()`), mise en cache
dans `cache.app` par DID.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `lexicons/io/rssbase/feed/subscription.json` | Schéma du record d'abonnement. |
| `lexicons/io/rssbase/feed/hidden.json` | Schéma du record de masque. |
| `lexicons/README.md` | Documentation publique du format : records, identité d'un flux, clé, compatibilité avec Skyreader, versionnage. |
| `.castor/lexicon.php` | Tâche `lexicon:publish` : publie les schémas dans le compte de RssBase, avec un mot de passe d'application lu dans l'environnement. |
| `infra/packages/flarectl.nix` | flarectl 0.119.0, binaire officiel épinglé (l'index multiverse s'arrête à 0.116.0) : crée l'enregistrement `_lexicon.feed.rssbase.io` dans la zone Cloudflare. |
| `app/src/AtProto/Repo/RepoClient.php` | Lecture anonyme (`listRecords` paginé, en parallèle) et écriture authentifiée (`createRecord`, `deleteRecord`) sur le PDS, DPoP avec `ath` et nonce du PDS. |
| `app/src/AtProto/Repo/Record.php` | Un record lu : clé, valeur (`JsonObject`). |
| `app/src/AtProto/Repo/Exception/WriteRefused.php` | Le PDS refuse l'écriture (401 hors nonce, 403) : le compte est traité comme sans droit d'écriture. |
| `app/src/Feed/FeedAddress.php` | Objet valeur : adresse d'un flux, forme normalisée, clé de record. |
| `app/src/Feed/FeedDocument.php` | Lit un flux RSS 2.0 ou Atom 1.0 : titre, lien du site. Refuse un document illisible. |
| `app/src/Feed/FeedLocator.php` | Ouvre une adresse saisie : flux direct, page à un flux, page à plusieurs flux, page sans flux, injoignable. |
| `app/src/Feed/LocatedFeeds.php` | Résultat de la découverte : un flux prêt, ou des candidats avec leur titre. |
| `app/src/Feed/Exception/FeedUnreachable.php` | Adresse injoignable, réseau privé compris. |
| `app/src/Feed/Exception/NoFeedFound.php` | Page sans flux, ou flux illisible. |
| `app/src/Subscription/Subscription.php` | Une ligne de « Mes flux » : flux, titre, site, date, suivi dans RssBase et dans Skyreader. |
| `app/src/Subscription/SubscriptionList.php` | Fusion des trois collections : filtre Skyreader, dédoublonnage, masques, ordre. |
| `app/src/Subscription/Subscriptions.php` | Lire la liste, s'abonner (démasque compris), se désabonner (supprime et masque), annuler. |
| `app/src/Subscription/AccountPds.php` | L'adresse du PDS de l'utilisateur : celle de la session, ou celle du DID pour une session plus ancienne, en cache. |
| `app/src/Twig/Components/FeedList.php` | Live Component : liste, « Se désabonner », « Annuler ». |
| `app/templates/components/FeedList.html.twig` | Rendu de la liste, de la mention Skyreader, du message d'annulation et de l'état vide. |
| `app/templates/feeds/choose.html.twig` | Choix entre plusieurs flux d'un site. |
| `app/assets/controllers/dismiss_controller.js` | Retire le message d'annulation après le délai. |
| `app/tests/Fake/FakeRepository.php` | Dépôts des comptes du faux PDS : `listRecords` paginé, `createRecord` (refus d'une clé existante), `deleteRecord`, DPoP vérifiée avec `ath` et nonce. |
| `app/tests/Fake/FakeWeb.php` | Faux sites et faux flux : RSS, Atom, page à un ou plusieurs flux, page sans flux, redirections. |
| `app/tests/Unit/Feed/FeedAddressTest.php` | Normalisation et clé. |
| `app/tests/Unit/Feed/FeedDocumentTest.php` | RSS, Atom, sans titre, illisible, entités externes refusées. |
| `app/tests/Unit/Feed/FeedLocatorTest.php` | Les cinq issues de la découverte, liens relatifs, redirections, réseau privé. |
| `app/tests/Unit/Subscription/SubscriptionListTest.php` | Filtre Skyreader, dédoublonnage, masque, ordre, date la plus ancienne. |
| `app/tests/Unit/AtProto/Repo/RepoClientTest.php` | `ath`, relance sur `use_dpop_nonce`, pagination, 401 et 403 en `WriteRefused`, 5xx injoignable. |
| `app/tests/Unit/AtProto/OAuth/ScopeTest.php` | `grantsWrite()` : collections exactes, `repo:*`, `transition:generic`, une seule collection, identité seule. |
| `app/tests/Functional/SubscribeTest.php` | Abonnement par le formulaire et la page de choix, échecs, doublon, démasque, compte sans écriture, PDS injoignable. |
| `app/tests/Functional/ReauthorizeTest.php` | Réapprobation : départ chez le fournisseur, retour sur « Mes flux » avec l'écriture. |
| `app/tests/E2E/FeedsBrowserTest.php` | Abonnement, désabonnement et « Annuler » dans un vrai navigateur, flux Skyreader masqué. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `app/config/services.yaml` | `app.atproto.scope` restreint aux deux collections. Services `feed.client` et `feed.public_network_client`. `BoundedHttpClient` reçoit ses limites en arguments. En test, `feed.public_network_client` pointe sur le faux réseau. |
| `app/config/packages/http_client.yaml` | Client scopé `feed.http_client` (`^https?://`), et sa réponse simulée en test. |
| `app/src/AtProto/BoundedHttpClient.php` | Limites passées au constructeur, avec les valeurs actuelles par défaut. |
| `app/src/AtProto/OAuth/Scope.php` | `grantsWrite()` vérifie les collections demandées, et garde `repo:*` et `transition:generic`. |
| `app/src/AtProto/OAuth/DpopKey.php` | Preuve avec jeton d'accès (`ath`), et nonce passé par l'appelant pour un serveur de ressources. |
| `app/src/Security/AuthorizationFlow.php` | Garde le nonce DPoP du PDS en session, à côté des jetons. |
| `app/src/Security/AtProtoUser.php` | Ajout de `?Pds $pds` et `__unserialize()`. |
| `app/src/Security/AtProtoAuthenticator.php` | Passe le PDS de l'identité à l'utilisateur. |
| `app/src/Controller/FeedsController.php` | Page « Mes flux » (formulaire et composant), POST d'abonnement, POST de choix, POST de réapprobation. |
| `app/templates/feeds/index.html.twig` | Formulaire en tête, message du compte sans écriture et bouton de réapprobation, message de liste indisponible, composant. |
| `app/translations/messages+intl-icu.fr.yaml` | Libellés `feeds.*` : formulaire, choix, échecs, doublon, mention Skyreader, désabonnement, annulation, sans écriture, indisponible, état vide. |
| `app/translations/messages+intl-icu.en.yaml` | Les mêmes en anglais. |
| `app/tests/Fake/FakeAtProtoNetwork.php` | Délègue les requêtes du dépôt à `FakeRepository` et celles du web à `FakeWeb`, remet les deux à zéro. |
| `app/tests/Functional/ClientMetadataTest.php` | Scope attendu restreint. |
| `app/tests/Functional/AccountDisplayTest.php` | « Mes flux » vide affiche désormais le formulaire. |
| `flake.nix` | flarectl dans `devShells.default`, `packages` et `checks`. |
| `AGENTS.md` | Le lexicon (`lexicons/`), sa publication, le nonce DPoP du PDS dans la session, flarectl parmi les paquets de `infra/packages/`. |
| `forge/stack.md` | Records écrits dans le PDS, client des flux. Passe de `/forge:sync`, pas de l'implémentation. |

## Hors scope

- **Permission set** : reporté après la publication du lexicon (voir §Alternatives écartées).
- **Lecture des articles d'un flux** (dates, contenus, encodages) : `lecture-chronologique`, avec le choix éventuel d'une bibliothèque.
- **Cache de la liste** : relue à chaque rendu. Une mesure à quelques centaines de flux décidera si `import-opml` en a besoin.
- **Validation des records par le PDS** (`validate: true`) : le PDS ne connaît pas un lexicon tant qu'il n'est pas publié, et la validation reste
  côté RssBase.
- **Création du compte AT Protocol de RssBase et de l'enregistrement DNS** : manuelles, hors du dépôt.
- **Révocation des anciennes approbations `repo:*`** : elles restent valables jusqu'à la prochaine connexion (pitch, question ouverte tranchée par
  sa recommandation).

## Impacts transverses

- **Cloisonnement des données** : toutes les lectures et écritures visent le dépôt du DID de la session. Aucun paramètre de requête ne désigne un
  compte. La LiveProp du flux retiré est non modifiable et contrôlée par la somme de contrôle du composant. « Annuler » ne réécrit que dans le dépôt
  de l'utilisateur courant.
- **Déclinaisons / thèmes** : non.
- **Traduction / i18n** : libellés `feeds.*` en français et en anglais, ICU pour le titre dans « Désabonné de {title} ». Les titres des flux ne
  sont pas traduits.
- **API / exposition externe** : le lexicon publié est le contrat public. Les records des deux collections sont lisibles par tout client. Les
  adresses saisies reçoivent une requête de RssBase, avec l'en-tête `User-Agent: RssBase`.
- **Droits d'accès** : routes `/feeds*` et `/_components/*` réservées par le refus par défaut. Le formulaire et les actions du composant vérifient
  `canWrite`, et une écriture refusée par le PDS retombe sur le message du compte sans écriture.
- **Emails / notifications** : non.
- **Migration de données** : aucune en base. Les sessions ouvertes avant cette story retrouvent leur PDS par le DID.
- **Comportement par défaut** : un compte sans abonnement voit le formulaire et l'état vide. Un compte connecté avant cette story garde l'écriture.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `app/src/Feed/FeedAddress.php` | unit | `http`/`https`, casse de l'hôte, port par défaut, fragment, barre finale, requête gardée, clé stable de 52 caractères dans le jeu autorisé. |
| `app/src/Feed/FeedDocument.php` | unit | Titre et lien en RSS 2.0 et Atom 1.0, flux sans titre, XML illisible, document qui n'est pas un flux, entité externe ignorée. |
| `app/src/Feed/FeedLocator.php` | unit | Flux direct, page à un flux, page à plusieurs flux avec titres, page sans flux, injoignable, lien relatif résolu, adresse finale après redirection, `127.0.0.1` refusé par le vrai `NoPrivateNetworkHttpClient`. |
| `app/src/Subscription/SubscriptionList.php` | unit | Entrées Skyreader non RSS écartées, flux suivi des deux côtés en une ligne avec la date la plus ancienne, flux masqué retiré, ordre décroissant, titre Skyreader `customTitle` puis `title` puis adresse. |
| `app/src/AtProto/Repo/RepoClient.php` | unit | Claim `ath`, relance unique sur `use_dpop_nonce`, nonce du PDS gardé, pagination jusqu'au dernier curseur, 401 et 403 en `WriteRefused`, 5xx et délai en injoignable. |
| `app/src/AtProto/OAuth/Scope.php` | unit | Écriture accordée pour les deux collections, `repo:*` et `transition:generic`. Refusée pour une seule collection ou l'identité seule. |
| `app/src/Controller/FeedsController.php` | functional | Abonnement RSS, Atom, par site, page de choix, saisie sans schéma et avec espaces, injoignable, sans flux, réseau privé, doublon RssBase et Skyreader, démasque, compte sans écriture (pas de formulaire, 403 sur le POST), PDS injoignable, record écrit lu dans le faux dépôt. |
| `app/src/Controller/FeedsController.php` (réapprobation) | functional | POST avec CSRF, départ chez le faux fournisseur avec le handle, retour sur « Mes flux » avec l'écriture accordée. |
| `app/src/Twig/Components/FeedList.php` | E2E | Désabonnement d'un flux RssBase (record supprimé), d'un flux Skyreader (masque écrit, message), « Annuler » qui rétablit la ligne à sa place, message retiré après le délai. |
| `app/src/Security/AtProtoUser.php` | functional | Une session sérialisée sans PDS se relit, et le PDS est retrouvé par le DID. |

**Hors scope tests** :

- La publication du lexicon (`.castor/lexicon.php`) : elle écrit dans un vrai compte, et se vérifie à la main en résolvant le schéma publié.
- L'accord de la permission restreinte par un vrai fournisseur : vérifié à la main à l'étape 1, aucun faux ne prouve le comportement d'un PDS réel.
- La performance de la liste sur un vrai PDS : mesurée à la main à l'étape 7, pas en test automatique.
- Les variantes exotiques de flux (RSS 0.9x, RDF, JSON Feed) : hors du périmètre du pitch.

## Ordre d'exécution

1. [ ] **Permission restreinte et essai réel**
   - Objectif : savoir si un vrai fournisseur accorde `repo?collection=…` avant de construire le reste.
   - Fichiers : `app/config/services.yaml`, `app/src/AtProto/OAuth/Scope.php`, `app/tests/Unit/AtProto/OAuth/ScopeTest.php`,
     `app/tests/Functional/ClientMetadataTest.php`.
   - Vérification : `castor qa` vert. Puis connexion avec un vrai compte sur `castor start`, et scope accordé lu dans le profiler (`castor mate`).
     L'utilisateur se connecte avec son compte. Si la permission est refusée, la question ouverte se tranche avant l'étape 2.
   - Commitable seule : oui.

2. [ ] **Lexicon et documentation**
   - Objectif : figer le contrat avant de l'écrire dans un compte.
   - Fichiers : `lexicons/io/rssbase/feed/subscription.json`, `lexicons/io/rssbase/feed/hidden.json`, `lexicons/README.md`,
     `app/src/Feed/FeedAddress.php`, `app/tests/Unit/Feed/FeedAddressTest.php`.
   - Vérification : schémas valides selon la spécification Lexicon (relecture contre `app.skyreader.feed.subscription`), tests de `FeedAddress` verts.
   - Commitable seule : oui.

3. [ ] **Client du dépôt et PDS en session**
   - Objectif : lire et écrire des records dans le PDS de l'utilisateur.
   - Fichiers : `app/src/AtProto/Repo/*`, `app/src/AtProto/OAuth/DpopKey.php`, `app/src/Security/AuthorizationFlow.php`,
     `app/src/Security/AtProtoUser.php`, `app/src/Security/AtProtoAuthenticator.php`, `app/src/Subscription/AccountPds.php`,
     `app/tests/Fake/FakeRepository.php`, `app/tests/Fake/FakeAtProtoNetwork.php`, `app/tests/Unit/AtProto/Repo/RepoClientTest.php`.
   - Vérification : tests unitaires verts. Puis, avec un vrai compte, un record d'essai créé puis supprimé dans `io.rssbase.feed.subscription`.
   - Commitable seule : oui.

4. [ ] **Lecture des flux**
   - Objectif : ouvrir une adresse saisie et en tirer un flux ou des candidats.
   - Fichiers : `app/src/AtProto/BoundedHttpClient.php`, `app/config/services.yaml`, `app/config/packages/http_client.yaml`, `app/src/Feed/*`,
     `app/tests/Fake/FakeWeb.php`, `app/tests/Unit/Feed/*`, `app/tests/Unit/AtProto/BoundedHttpClientTest.php`.
   - Vérification : tests unitaires verts, `BoundedHttpClientTest` inchangé dans ses attentes.
   - Commitable seule : oui.

5. [ ] **Liste fusionnée et cas d'usage**
   - Objectif : lire la liste, s'abonner, se désabonner, annuler, sans interface.
   - Fichiers : `app/src/Subscription/*`, `app/tests/Unit/Subscription/SubscriptionListTest.php`.
   - Vérification : tests unitaires verts.
   - Commitable seule : oui.

6. [ ] **Page « Mes flux » : abonnement, choix, compte sans écriture, réapprobation**
   - Objectif : parcours d'abonnement complet par formulaires.
   - Fichiers : `app/src/Controller/FeedsController.php`, `app/templates/feeds/index.html.twig`, `app/templates/feeds/choose.html.twig`,
     traductions, `app/tests/Functional/SubscribeTest.php`, `app/tests/Functional/ReauthorizeTest.php`,
     `app/tests/Functional/AccountDisplayTest.php`.
   - Vérification : tests fonctionnels verts, `bin/console lint:twig`, `lint:translations`.
   - Commitable seule : oui.

7. [ ] **Live Component : liste, désabonnement, « Annuler »**
   - Objectif : retirer une ligne et la rétablir sans rechargement.
   - Fichiers : `app/src/Twig/Components/FeedList.php`, `app/templates/components/FeedList.html.twig`,
     `app/assets/controllers/dismiss_controller.js`, traductions, `app/tests/E2E/FeedsBrowserTest.php`.
   - Vérification : suite `e2e` verte, rendu vérifié par Playwright MCP en clair et en sombre, sur petit et grand écran, au clavier (HIG). Temps de
     rendu de « Mes flux » mesuré sur un vrai PDS.
   - Commitable seule : oui.

8. [ ] **Publication du lexicon et documentation du projet**
   - Objectif : rendre le format résolvable par un autre lecteur.
   - Fichiers : `.castor/lexicon.php`, `infra/packages/flarectl.nix`, `flake.nix`, `AGENTS.md`.
   - Vérification : enregistrement TXT `_lexicon.feed.rssbase.io` créé par `flarectl dns create`, avec un jeton Cloudflare limité à la zone passé
     par `CF_API_TOKEN` à la seule commande. Puis `castor lexicon:publish` sur le compte de RssBase, `_lexicon.feed.rssbase.io` résolu et le schéma relu par
     `com.atproto.repo.getRecord`, comme pour Skyreader au cadrage. Demande le compte et l'enregistrement DNS créés à la main.
   - Commitable seule : oui.

9. [ ] **QA finale**
   - Objectif : livrer vert.
   - Fichiers : aucun.
   - Vérification : `CI=1 nix develop -c castor qa`.
   - Commitable seule : non.

## Critères de sortie

- [ ] Le scope demandé ne nomme que `io.rssbase.feed.subscription` et `io.rssbase.feed.hidden`, et un vrai fournisseur l'accorde (ou la question
  ouverte est tranchée).
- [ ] Un abonnement créé avec un vrai compte se relit par `com.atproto.repo.getRecord` sans passer par RssBase.
- [ ] Le schéma de `io.rssbase.feed.subscription` se résout par `_lexicon.feed.rssbase.io`.
- [ ] Aucune table ni migration ajoutée, et aucune donnée d'abonnement dans SQLite.
- [ ] Les tests ne quittent jamais la machine : faux PDS et faux sites pour chaque adresse.
- [ ] Une session ouverte avant cette story reste connectée et voit « Mes flux ».
- [ ] Suite de tests du projet verte (`default` et `e2e`), sans nouvelle régression.
- [ ] `CI=1 nix develop -c castor qa` vert : Mago, symfony-lsp, `lint:*`, traductions, `composer audit`.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Un vrai fournisseur n'accorde pas `repo?collection=…` | moyenne | Étape 1 avant tout le reste. La question ouverte prépare les deux issues. |
| Un PDS lent rend « Mes flux » lente, avec trois lectures à chaque rendu | moyenne | Lectures parallèles, limites de `BoundedHttpClient`, mesure à l'étape 7. Le cache reste une option documentée hors scope. |
| Adresse saisie utilisée pour atteindre le réseau interne (SSRF) | moyenne | `NoPrivateNetworkHttpClient` à chaque saut de redirection, testé en unitaire avec le vrai client. Le flux choisi sur la page de choix est rouvert, jamais cru sur parole. |
| XML hostile (entités externes, document énorme) | faible | Plafond de 2 Mio, lecture `dom` sans réseau ni entités externes, testée. |
| Nonce DPoP du PDS confondu avec celui du serveur d'autorisation | moyenne | Deux nonces séparés en session, et relance sur `use_dpop_nonce` testée contre le faux PDS, qui exige le sien. |
| Session ancienne sans PDS qui casse à la désérialisation | moyenne | `__unserialize()` et repli sur le DID, testés avec une session sérialisée avant le changement. |
| Contrat du lexicon figé trop tôt | faible | Champs alignés sur Skyreader, tous optionnels sauf `feedUrl` et `createdAt`. Un ajout optionnel reste compatible. |
| Concurrence entre deux appareils | faible | Clé dérivée de l'adresse : le second `createRecord` échoue en doublon et affiche « déjà dans vos flux ». |
| Premier Live Component : routes, somme de contrôle, état | moyenne | Une seule LiveProp non modifiable, couverte par un test E2E. |

## Questions ouvertes

- **Fournisseur qui n'accorde pas l'écriture limitée** : si l'étape 1 montre qu'un vrai PDS retire `repo?collection=…` et ne renvoie que `atproto`.
  Options : (a) le compte est traité comme sans écriture (règle 25 du pitch), (b) demander `transition:generic` à la place, ou en plus, pour les
  serveurs qui ne listent que les scopes de transition. Recommandé : (a) si au moins Bluesky l'accorde, (b) si aucun des quatre fournisseurs
  ne l'accorde. Avec (b), l'écriture redevient l'accès à tout le compte, et la règle 24 du pitch est à reprendre.
