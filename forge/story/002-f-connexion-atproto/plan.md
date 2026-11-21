# Plan technique — Se connecter à RssBase avec son compte AT Protocol

> **But** : figer comment RssBase authentifie un compte AT Protocol par OAuth, tient sa session sur les deux sites de langue et trace les connexions.
> **Registre** : technique
> **Story** : `forge/story/002-f-connexion-atproto/`
> **Amont** : `pitch.md`

## Approche retenue

RssBase porte son propre client OAuth AT Protocol, écrit dans l'application, sans bibliothèque AT Protocol tierce. Trois couches se superposent. La couche
identité (`App\AtProto\Identity`) transforme une saisie en compte vérifié : normalisation du handle, résolution handle vers DID (enregistrement DNS
`_atproto` puis `/.well-known/atproto-did`), document DID (`plc.directory` ou `did:web`), vérification bidirectionnelle du handle, puis découverte du
serveur d'autorisation à partir du PDS. La couche OAuth (`App\AtProto\OAuth`) applique le profil AT Protocol : PAR, PKCE S256, DPoP ES256 avec nonce du
serveur, échange et rafraîchissement des jetons. La couche Symfony Security (`App\Security`) transforme un retour OAuth valide en utilisateur connecté,
protège l'espace réservé et gère la déconnexion.

Le client a deux modes, choisis par la configuration. Quand une clé privée est configurée (`ATPROTO_CLIENT_PRIVATE_JWK`), il est **confidentiel** : son
`client_id` est l'adresse https de son document de métadonnées, servi par l'application avec la clé publique, et il s'authentifie par `private_key_jwt`.
Les sessions durent alors jusqu'à 2 ans chez le fournisseur de référence, avec un jeton de rafraîchissement valable 3 mois sans usage. Sans clé, il est
en **boucle locale** : `client_id` de la forme `http://localhost?redirect_uri=...&scope=...`, client public, sessions de 2 semaines. C'est le mode du
développement et des tests.

Tout l'état d'une connexion vit dans la session PHP côté serveur : la demande en cours (vérificateur PKCE, clé DPoP, émetteur attendu, DID attendu),
puis les jetons, la clé DPoP, le dernier nonce et la portée accordée. Un appareil a donc une session et un jeu de jetons. Se déconnecter détruit la
session, sans appel au fournisseur. Le verrou de session de PHP sérialise le rafraîchissement des jetons, qui sont à usage unique. L'utilisateur de
Symfony Security est un objet tenu en session (DID, handle, adresse de l'avatar, écriture accordée ou non) : aucune requête en base par page.

La base ne reçoit que la trace de connexion : une table `account`, clée par le DID, mise à jour par une seule requête d'insertion ou de mise à jour à
chaque connexion réussie, datée par l'horloge de SQLite.

Une seule adresse de retour OAuth existe (`/oauth/callback` sur le site anglais, ou sur `127.0.0.1` en boucle locale, où la spécification interdit
`localhost`). Le paramètre `state` transporte le site de départ, signé avec le secret de l'application. Quand le retour arrive sur un autre hôte que
celui de départ, le contrôleur le renvoie tel quel vers l'hôte de départ, qui seul possède le cookie de session et termine l'échange. Le cookie de
session est posé sur le domaine parent, celui que `LocaleDomainListener` calcule déjà, pour valoir sur les deux langues.

La portée demandée est un paramètre, `atproto repo:*` dans cette story (écriture sur tout le compte dans un premier temps, à resserrer sur les
collections RssBase quand le lexicon existera). La portée réellement accordée se lit dans la réponse du serveur de jetons : un fournisseur qui ne
connaît pas les permissions fines les retire en silence et ne renvoie que `atproto`, ce qui donne le repli de la règle 8 du pitch sans détection
préalable.

Le choix d'un fournisseur réutilise le même flux, démarré depuis l'émetteur du fournisseur au lieu d'un handle, avec `prompt=create` et sans
`login_hint`. Au retour, le DID reçu (`sub`) est résolu et son serveur d'autorisation doit être l'émetteur lié à la demande.

### Mécanismes mobilisés

- **Symfony Security, authentificateur sur mesure** : `AtProtoAuthenticator` ne prend en charge que la route de retour OAuth. Il valide `state` et
  `iss`, échange le code, vérifie `sub`, puis rend un passeport autovalidé. Le pare-feu fournit le point d'entrée (redirection vers la connexion avec
  mémorisation de la page demandée, `TargetPathTrait`), la migration de session à la connexion et la déconnexion.
- **Fournisseur d'utilisateur en session** : `SessionUserProvider` rend l'utilisateur tel qu'il est en session. La trace en base n'est pas une source
  d'authentification.
- **`access_control` en refus par défaut** : tout exige `ROLE_USER`, sauf l'accueil, la connexion, le choix d'un fournisseur, le retour OAuth et le
  document de métadonnées, en `PUBLIC_ACCESS`. C'est la règle 11 du pitch, qui vaut aussi pour les pages à venir. Le routeur passe avant le pare-feu :
  une adresse inconnue reste une 404.
- **`symfony/http-client` avec `NoPrivateNetworkHttpClient`** : les adresses appelées viennent de la saisie du visiteur (domaine du handle, `did:web`,
  PDS du document DID). Le client dédié refuse les réseaux privés, ne suit aucune redirection et a un délai court. Les tests le remplacent par le faux
  fournisseur.
- **`web-token/jwt-library`** : signature ES256 des preuves DPoP et de l'assertion du client, génération et sérialisation des clés en JWK.
  L'application ne vérifie aucun jeton : les jetons d'accès AT Protocol sont opaques pour le client.
- **Session native sur fichiers** : `var/sessions/<environnement>`, cookie de longue durée, durée de vie alignée sur le jeton de rafraîchissement.
  Le domaine du cookie est fixé à chaque requête par `LocaleDomainListener`, avant toute ouverture de session.
- **Écouteur `kernel.request` de rafraîchissement** : pour un utilisateur connecté dont le jeton d'accès a expiré, rafraîchit les jetons. Sur
  `invalid_grant`, déconnecte et renvoie à la connexion avec le message de fin de session (règle 17). Sur une panne réseau, ne déconnecte pas.
- **Jeton CSRF sans état** de Symfony sur les trois formulaires POST (connexion, choix d'un fournisseur, déconnexion) ;
  `assets/controllers/csrf_protection_controller.js` existe déjà.
- **Requête d'insertion ou de mise à jour en DBAL** dans `AccountRepository`, avec `CURRENT_TIMESTAMP` : une seule requête atomique, sans lecture
  préalable, datée par la base (règle « Database queries » d'`AGENTS.md`).
- **Mise en page partagée** : l'en-tête et les couleurs de l'accueil, aujourd'hui écrits dans `home/index.html.twig`, passent dans une mise en page et
  un composant Twig anonyme que les quatre écrans étendent.
- **Filtre des fournisseurs par liens** (`?region=eu`) : rendu côté serveur, sans JavaScript, testable en `WebTestCase`.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| `wecoprod/atproto-oauth-bundle` v0.1.0 | Dépôt tout neuf (67 téléchargements), sans `prompt=create` ni boucle locale : il faudrait le contourner pour deux besoins du pitch. Sert de référence de lecture, pas de dépendance. |
| `gimucco/atproto-php` | Licence GPL-2.0, et extensions `sodium` et `curl` absentes du PHP maison : recompilation de PHP et de FrankenPHP. |
| Client public seulement | Sessions de 2 semaines : le parcours de lecture quotidienne repasserait par le fournisseur deux fois par mois. |
| Jetons dans une table en base | Demande un verrou explicite et laisse jetons et clés DPoP lisibles dans le fichier SQLite. Aucune écriture hors requête utilisateur n'est prévue au MVP. |
| Portée `transition:generic` | Donne aussi l'accès aux appels d'API et aux fichiers du compte ; `repo:*` se limite à l'écriture des enregistrements. |
| Une adresse de retour par langue | Impossible en boucle locale, où l'hôte de retour est imposé (`127.0.0.1`). Le renvoi par `state` signé donne un seul chemin pour les deux modes. |
| Avatar par l'API publique de Bluesky | Écartée au cadrage technique : dépendance à un service de Bluesky. L'avatar se lit sur le PDS du compte. |
| Utilisateur Security chargé depuis la base | Une requête par page pour des données déjà en session, et la trace deviendrait une source d'authentification. |

## Modèle de données

### Nouvelle structure `Account`

`app/src/Entity/Account.php`, table `account` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto-incrémenté | non | clé primaire |
| `did` | chaîne (2048) | non | unique (index `uniq_account_did`) |
| `handle` | chaîne (253) | non | |
| `lastLoginAt` (`last_login_at`) | `datetime_immutable` | non | écrit par `CURRENT_TIMESTAMP` |

La clé métier est le DID : un compte dont le handle change garde sa ligne et prend le nouveau handle (règle 30). Le handle n'est ni unique ni indexé :
aucune requête ne filtre dessus. Pas de date de première connexion : le cadrage demande le handle et la dernière connexion. La structure n'a aucun
cloisonnement, c'est une trace d'exploitation lue par `bin/console dbal:run-sql`. Première migration du projet, générée par
`doctrine:migrations:diff`, jamais écrite à la main.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `app/src/AtProto/Identity/Handle.php` | Objet valeur : normalise la saisie (espaces, `@`, casse, complétion `.bsky.social`) et valide la syntaxe. |
| `app/src/AtProto/Identity/HandleResolver.php` | Handle vers DID par DNS `_atproto` puis `/.well-known/atproto-did`. |
| `app/src/AtProto/Identity/DidResolver.php` | Document DID (`did:plc`, `did:web`) : PDS et handle déclaré. |
| `app/src/AtProto/Identity/IdentityResolver.php` | Assemble les deux résolutions et la vérification bidirectionnelle ; rend une identité (DID, handle, PDS). |
| `app/src/AtProto/Identity/Exception/*.php` | Compte introuvable, fournisseur injoignable : les deux causes d'échec que l'interface distingue. |
| `app/src/AtProto/OAuth/AuthorizationServerLocator.php` | PDS vers métadonnées de ressource protégée, puis métadonnées du serveur d'autorisation, avec contrôle de l'émetteur. |
| `app/src/AtProto/OAuth/ClientIdentity.php` | Mode du client (confidentiel ou boucle locale), `client_id`, adresse de retour, document de métadonnées, assertion du client. |
| `app/src/AtProto/OAuth/DpopProofFactory.php` | Clé DPoP par session et preuve signée par requête (`htm`, `htu`, `jti`, `iat`, `nonce`, `ath`). |
| `app/src/AtProto/OAuth/OAuthClient.php` | PAR, adresse d'autorisation, échange du code, rafraîchissement, reprise sur `use_dpop_nonce`. |
| `app/src/AtProto/OAuth/PendingAuthorization.php` | État d'une demande en cours, tenu en session. |
| `app/src/AtProto/OAuth/TokenSet.php` | Jetons, échéance, portée accordée, émetteur, clé DPoP, nonce, tenus en session. |
| `app/src/AtProto/OAuth/SignedState.php` | Fabrique et vérifie le `state` qui transporte le site de départ. |
| `app/src/AtProto/Profile/AvatarLocator.php` | Lit `app.bsky.actor.profile` sur le PDS et rend l'adresse du fichier de l'avatar, ou rien. |
| `app/src/AtProto/Provider/Provider.php`, `ProviderCatalog.php` | Les fournisseurs proposés (nom, émetteur, région, étiquette) lus dans un paramètre. |
| `app/src/Security/AtProtoUser.php` | Utilisateur en session : DID, handle, avatar, écriture accordée. |
| `app/src/Security/SessionUserProvider.php` | Rend l'utilisateur de la session sans le recharger. |
| `app/src/Security/AtProtoAuthenticator.php` | Authentifie sur la route de retour OAuth, trace la connexion, redirige vers la page demandée. |
| `app/src/Security/LoginEntryPoint.php` | Renvoie un visiteur vers la connexion (si le point d'entrée par défaut ne suffit pas). |
| `app/src/EventListener/TokenRefreshListener.php` | Rafraîchit les jetons expirés ; ferme la session quand le fournisseur a retiré l'accès. |
| `app/src/Controller/LoginController.php` | `GET /login` (formulaire) et `POST /login` (départ depuis un handle). |
| `app/src/Controller/ProviderController.php` | `GET /providers` (liste filtrée) et `POST /providers/{provider}` (départ avec `prompt=create`). |
| `app/src/Controller/OAuthCallbackController.php` | `GET /oauth/callback` : renvoi vers le site de départ quand l'hôte diffère. |
| `app/src/Controller/ClientMetadataController.php` | `GET /oauth-client-metadata.json` en mode confidentiel, 404 sinon. |
| `app/src/Controller/FeedsController.php` | `GET /feeds` : « Mes flux », état vide. |
| `app/src/Command/GenerateClientKeyCommand.php` | Génère la clé ES256 du client au format JWK, à placer dans les secrets. |
| `app/src/Entity/Account.php` | Trace de connexion. |
| `app/src/Repository/AccountRepository.php` | `recordLogin(did, handle)` : insertion ou mise à jour datée par la base. |
| `app/src/Migrations/Version*.php` | Création de la table `account`, générée. |
| `app/config/packages/security.yaml` | Pare-feu, fournisseur d'utilisateur, déconnexion, `access_control`. |
| `app/config/packages/http_client.yaml` | Client dédié AT Protocol (délai, pas de redirection) ; fabrique de réponses du faux fournisseur en test. |
| `app/templates/site.html.twig` | Mise en page commune : couleurs, police, en-tête. |
| `app/templates/components/Site/Header.html.twig` | En-tête : logo, menu des langues, liens, compte connecté et déconnexion. |
| `app/templates/login/index.html.twig` | Champ handle, message d'échec, phrase sur le mot de passe, lien vers les fournisseurs. |
| `app/templates/provider/index.html.twig` | Fournisseurs, filtre Tous / UE / US, liens sortants, retour à la connexion. |
| `app/templates/feeds/index.html.twig` | État vide de « Mes flux ». |
| `app/tests/Fake/FakeAtProtoNetwork.php` | Faux réseau AT Protocol : résolution, métadonnées, PAR, jetons, profil. Vérifie PKCE, DPoP et l'assertion du client. |
| `app/tests/Fake/FakeAuthorizationController.php` | Page d'approbation du faux fournisseur, servie par le noyau de test. |
| `app/tests/Unit/AtProto/Identity/HandleTest.php` | Normalisation et complétion du handle. |
| `app/tests/Unit/AtProto/OAuth/*Test.php` | Preuve DPoP, assertion du client, `state` signé, `client_id` en boucle locale. |
| `app/tests/Functional/LoginTest.php` | Connexion par handle, échecs, page demandée retrouvée, redirections d'un utilisateur connecté. |
| `app/tests/Functional/ProviderTest.php` | Liste, filtre, départ chez un fournisseur et retour connecté. |
| `app/tests/Functional/SessionTest.php` | Déconnexion, second appareil, fin de session chez le fournisseur, compte sans écriture. |
| `app/tests/Functional/ClientMetadataTest.php` | Document de métadonnées en mode confidentiel, 404 en boucle locale. |
| `app/tests/Integration/AccountRepositoryTest.php` | Première connexion, reconnexion, changement de handle sans doublon. |
| `app/tests/E2E/LoginBrowserTest.php` | Parcours complet dans Chromium, session commune aux deux langues, réouverture du navigateur. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `app/composer.json`, `app/composer.lock`, `app/symfony.lock`, `app/config/bundles.php` | Ajouter `symfony/security-bundle`, `symfony/security-csrf`, `symfony/http-client` (déjà présent par transitivité) et `web-token/jwt-library`. |
| `app/config/packages/framework.yaml` | Session : stockage sur fichiers dans `var/sessions`, durée du cookie et de la session ; CSRF sans état pour les trois formulaires. |
| `app/config/services.yaml` | Paramètres `app.atproto.scope` (`atproto repo:*`) et `app.atproto.providers` (les quatre fournisseurs et les deux liens sortants). |
| `app/.env`, `app/.env.test` | `ATPROTO_CLIENT_PRIVATE_JWK` vide par défaut (boucle locale). |
| `app/src/EventListener/LocaleDomainListener.php` | Fixer aussi le domaine du cookie de session sur le domaine calculé, sauf adresse IP ou nom à un seul label. |
| `app/src/Controller/HomeController.php` | Rediriger un utilisateur connecté vers « Mes flux ». |
| `app/templates/home/index.html.twig` | Étendre la mise en page commune ; « Commencer » mène à la connexion ; l'en-tête et les couleurs sortent du gabarit. |
| `app/translations/messages+intl-icu.en.yaml`, `.fr.yaml` | Clés `login.*`, `providers.*`, `feeds.*`, `account.*`. |
| `app/tests/Functional/HomePageTest.php`, `app/tests/E2E/HomePageBrowserTest.php` | Suivre le nouveau lien « Commencer » et la mise en page partagée. |
| `castor.php`, `.castor/docker.php` | Servir aussi `127.0.0.1:<port>` (retour OAuth en boucle locale) et, si le prototype le confirme, `*.rssbase.localhost`. |
| `infra/frankenphp/Caddyfile` | Seulement si les nouveaux noms d'hôte l'exigent. |
| `app/.gitignore` | Ignorer `var/sessions` s'il ne l'est pas déjà. |
| `AGENTS.md` | Adresse de retour en `127.0.0.1`, clé du client dans les secrets, commande de génération de la clé, lecture de la trace. |

## Hors scope

- **Rotation de la clé du client** : un seul `kid` publié. La rotation arrive avec la story d'hébergement.
- **Limitation du débit des tentatives de connexion** : chaque tentative déclenche des appels sortants. À traiter avant la mise en production, pas ici.
- **Révocation des jetons à la déconnexion** : écartée au pitch (règle 22).
- **Écriture dans le PDS, lexicon, resserrement de la portée** : `abonnement-flux`.
- **Purge des fichiers de session et des traces anciennes** : le ramasse-miettes de PHP suffit à ce stade.
- **`forge/stack.md`** : à recartographier par `/forge:stack` après livraison (client AT Protocol, Security, sessions), pas à la main.
- **`forge/product-backlog.md`** : la ligne `creation-compte-fournisseur`, absorbée par cette story, se retire par `/forge:product-backlog`.
- **Mise en cache du profil** : l'avatar se lit à la connexion et vit le temps de la session.

## Impacts transverses

- **Cloisonnement des données** : aucune donnée partagée entre comptes. L'utilisateur vient de sa propre session ; la table `account` n'est lue par
  aucun écran.
- **Déclinaisons / thèmes** : non. Une mise en page commune, reprise de l'accueil.
- **Traduction / i18n** : toutes les chaînes dans `messages+intl-icu.en.yaml` et `.fr.yaml`, une clé complète par ligne, apostrophe typographique.
  Les descriptions des fournisseurs sont des clés de traduction. Les routes gardent les mêmes chemins sur les deux hôtes.
- **API / exposition externe** : deux points d'entrée publics nouveaux, `GET /oauth-client-metadata.json` (lu par les serveurs d'autorisation) et
  `GET /oauth/callback`. Appels sortants vers DNS, `plc.directory`, le domaine du handle, le PDS et le serveur d'autorisation du compte.
- **Droits d'accès** : premier pare-feu du projet. `ROLE_USER` pour tout sauf cinq routes publiques ; aucun voter.
- **Emails / notifications** : non.
- **Migration de données** : création de la table `account`. Aucune reprise.
- **Comportement par défaut** : un visiteur non connecté ne voit qu'un changement, la destination de « Commencer ». Aucune session n'est ouverte
  tant qu'il ne soumet pas un formulaire.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `app/src/AtProto/Identity/Handle.php` | unit | `@`, espaces, casse ; nom sans point complété par `.bsky.social` ; syntaxe invalide refusée. |
| `app/src/AtProto/OAuth/DpopProofFactory.php` | unit | En-tête (`typ`, `alg`, `jwk` public seulement), revendications, `ath` pour un jeton d'accès, signature vérifiable avec la clé publique. |
| `app/src/AtProto/OAuth/ClientIdentity.php` | unit | `client_id` et adresse de retour dans les deux modes ; assertion (`iss`, `sub`, `aud`, `jti`, `iat`) ; contenu du document de métadonnées. |
| `app/src/AtProto/OAuth/SignedState.php` | unit | Aller-retour ; signature altérée ou site étranger refusés. |
| `app/src/AtProto/Identity/IdentityResolver.php` | unit (faux réseau) | DNS puis well-known ; handle non confirmé par le document DID refusé ; `did:web` ; adresse de réseau privé refusée. |
| `app/src/Controller/LoginController.php`, `AtProtoAuthenticator.php` | functional | Connexion complète contre le faux fournisseur ; handle introuvable, fournisseur injoignable, approbation refusée, chacun avec son message et le handle conservé ; `state`, `iss` ou `sub` faux refusés ; reprise sur `use_dpop_nonce`. |
| `app/config/packages/security.yaml` | functional | `/feeds` anonyme renvoie à la connexion puis revient à `/feeds` ; adresse inconnue toujours en 404 ; utilisateur connecté redirigé depuis l'accueil, la connexion et les fournisseurs. |
| `app/src/EventListener/TokenRefreshListener.php` | functional | Jeton expiré rafraîchi sans rien montrer ; `invalid_grant` ramène à la connexion avec le message de fin de session ; panne réseau ne déconnecte pas. |
| `app/src/Controller/ProviderController.php` | functional | Quatre fournisseurs et leurs étiquettes ; filtres UE et US ; liens sortants ; départ avec `prompt=create` sans `login_hint` ; retour connecté ; émetteur différent de celui du compte refusé. |
| Portée accordée | functional | Faux fournisseur qui ne rend que `atproto` : connexion réussie, `AtProtoUser` sans écriture, même page « Mes flux ». |
| `app/src/Repository/AccountRepository.php` | integration (SQLite) | Insertion, reconnexion qui avance la date, nouveau handle sur le même DID sans seconde ligne. |
| `app/src/Controller/ClientMetadataController.php` | functional | JSON conforme et clé publique seule en mode confidentiel ; 404 en boucle locale. |
| Parcours complet | E2E | Accueil, « Commencer », handle, approbation, « Mes flux » avec avatar ou image neutre ; l'autre site de langue sans reconnexion ; déconnexion ; aucune erreur de console. |

**Hors scope tests** :

- Aucun test contre un vrai fournisseur : le sous-agent n'a pas de compte, et la suite doit tourner sans réseau. La vérification en boucle locale
  contre `bsky.social` reste manuelle, après livraison.
- Le mode confidentiel n'est vérifié que contre le faux fournisseur, qui contrôle l'assertion du client. Une vérification réelle demande une adresse
  https publique, donc l'hébergement.
- Pas de test du mode worker : la suite `e2e` passe par le noyau de test (dette connue de `forge/stack.md`).
- Pas de test du rendu de l'avatar depuis un vrai PDS : seule l'adresse construite est vérifiée.

## Ordre d'exécution

1. [x] **Mise en page partagée**
   - Objectif : l'accueil étend une mise en page commune qui porte l'en-tête et les couleurs, sans changement visible.
   - Fichiers : `templates/site.html.twig`, `templates/components/Site/Header.html.twig`, `templates/home/index.html.twig`.
   - Vérification : `castor qa:phpunit` et `castor qa:e2e` verts sans modifier les tests de l'accueil.
   - Commitable seule : oui.

2. [x] **Pare-feu et espace réservé**
   - Objectif : `/feeds` existe, vide, et renvoie un visiteur vers `/login` ; « Commencer » mène à `/login`.
   - Fichiers : dépendances, `security.yaml`, `AtProtoUser`, `SessionUserProvider`, `FeedsController`, `LoginController` (GET), gabarits, traductions.
   - Vérification : tests fonctionnels du pare-feu, 404 préservée, `loginUser()` donne accès à `/feeds`.
   - Commitable seule : oui.

3. [x] **Faux réseau AT Protocol et identité**
   - Objectif : une saisie devient une identité vérifiée (DID, handle, PDS), contre le faux réseau.
   - Fichiers : `tests/Fake/*`, `AtProto/Identity/*`, `http_client.yaml`.
   - Vérification : tests unitaires de `Handle` et d'`IdentityResolver`.
   - Commitable seule : oui.

4. [x] **Client OAuth**
   - Objectif : PAR, autorisation, échange et rafraîchissement, dans les deux modes, avec DPoP et reprise sur nonce.
   - Fichiers : `AtProto/OAuth/*`, `ClientMetadataController`, `GenerateClientKeyCommand`, `.env`.
   - Vérification : tests unitaires OAuth, `ClientMetadataTest`. Vérifier à l'installation que `web-token/jwt-library` signe en ES256 sans `gmp` ni `bcmath`.
   - Commitable seule : oui.

5. [x] **Connexion par handle**
   - Objectif : parcours complet par handle, échecs avec message et handle conservé, page demandée retrouvée, déconnexion, redirections d'un utilisateur connecté.
   - Fichiers : `LoginController` (POST), `OAuthCallbackController`, `SignedState`, `AtProtoAuthenticator`, `HomeController`, gabarits, traductions.
   - Vérification : `LoginTest`.
   - Commitable seule : oui.

6. [x] **Trace de connexion**
   - Objectif : chaque connexion réussie écrit ou met à jour la ligne du compte.
   - Fichiers : `Account`, `AccountRepository`, migration générée, appel depuis l'authentificateur.
   - Vérification : `AccountRepositoryTest` ; `EXPLAIN QUERY PLAN` de la requête, sur une table de quelques milliers de lignes, consigné dans le message de commit ; `doctrine:schema:validate`.
   - Commitable seule : oui.

7. [x] **Durée et portée de la session**
   - Objectif : session longue sur fichiers, cookie valable sur les deux langues, rafraîchissement des jetons, fin de session expliquée, compte sans écriture.
   - Fichiers : `framework.yaml`, `LocaleDomainListener`, `TokenRefreshListener`, `castor.php`, `.castor/docker.php`.
   - Vérification : `SessionTest`. Commencer par le prototype de la première question ouverte, dans Chromium.
   - Commitable seule : oui.

8. [x] **Choix d'un fournisseur**
   - Objectif : écran des fournisseurs avec filtre et liens ; départ direct et retour connecté.
   - Fichiers : `Provider`, `ProviderCatalog`, `ProviderController`, gabarit, `services.yaml`, traductions.
   - Vérification : `ProviderTest`.
   - Commitable seule : oui.

9. [x] **Compte affiché**
   - Objectif : avatar ou image neutre et handle dans l'en-tête des pages réservées.
   - Fichiers : `AvatarLocator`, `Site/Header.html.twig`, authentificateur.
   - Vérification : tests fonctionnels avec et sans enregistrement de profil.
   - Commitable seule : oui.

10. [ ] **Parcours navigateur, documentation, QA**
    - Objectif : parcours E2E, `AGENTS.md` à jour, contrôle complet.
    - Fichiers : `tests/E2E/LoginBrowserTest.php`, `AGENTS.md`.
    - Vérification : `CI=1 nix develop -c castor qa` vert.
    - Commitable seule : oui.

## Critères de sortie

- [ ] `CI=1 nix develop -c castor qa` est vert : Mago, symfony-lsp, `lint:*`, traductions, suites `default` et `e2e`, `composer audit`.
- [ ] Chaque critère d'acceptation du pitch est couvert par un test fonctionnel ou E2E nommé, hors vérification contre un vrai fournisseur.
- [ ] La suite de tests n'émet aucune requête réseau : le client HTTP de test n'a que le faux réseau.
- [ ] Le faux fournisseur refuse une demande sans PKCE S256, sans preuve DPoP valide ou, en mode confidentiel, sans assertion valide.
- [ ] `doctrine:schema:validate` est vert et la migration s'applique sur une base vide.
- [ ] Le plan d'exécution de la requête de trace utilise l'index unique sur `did`, preuve consignée.
- [ ] Aucun jeton, clé privée ou code d'autorisation n'apparaît dans les journaux ni dans une réponse HTTP.
- [ ] En boucle locale, le départ vers un vrai fournisseur produit une demande PAR acceptée (vérification manuelle, consignée ou signalée non faite).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Un navigateur refuse un cookie posé sur le domaine `localhost` : la session commune aux deux langues ne marche pas en développement ni en E2E | élevée | Prototype en début d'étape 7 ; repli sur `rssbase.localhost` et `fr.rssbase.localhost` (première question ouverte). |
| Écart avec la spécification OAuth AT Protocol, invisible contre un faux fournisseur écrit par la même main | moyenne | Faux fournisseur écrit depuis `atproto.com/specs/oauth` et le code de référence ; lecture de `wecoprod/atproto-oauth-bundle` en contrôle ; vérification manuelle contre `bsky.social`. |
| Requêtes vers le réseau interne à partir d'un handle ou d'un `did:web` forgé | moyenne | `NoPrivateNetworkHttpClient`, aucune redirection suivie, délai court, test dédié. |
| `web-token/jwt-library` exige `gmp` ou `bcmath`, absents du PHP maison | faible | Vérifier à l'étape 4 ; sinon ajouter `bcmath` dans `infra/packages/php.nix` (recompilation). |
| Blacksky ou NPMX n'accordent pas `repo:*` (versions dérivées) | moyenne | Comportement prévu : connexion en identité seule, lue dans la portée accordée. |
| Le PDS sert l'avatar avec des en-têtes qui empêchent son affichage dans une page tierce | moyenne | Vérifier à l'étape 9 ; repli sur une route de l'application qui relaie l'image (deuxième question ouverte). |
| État conservé entre requêtes en mode worker (nonce, clé, utilisateur) | faible | Aucun service ne garde d'état de requête : tout passe par la session. |
| Deux requêtes simultanées rafraîchissent le même jeton à usage unique | faible | Verrou de session de PHP ; le rafraîchissement se fait session ouverte. |
| Mode confidentiel faux en production faute de vérification réelle | moyenne | Assertion contrôlée par le faux fournisseur ; vérification réelle inscrite à la story d'hébergement. |

## Questions ouvertes

- **Cookie de session sur `localhost`** : à prototyper dans Chromium en début d'étape 7. Un cookie posé avec `Domain=localhost` depuis `fr.localhost`
  est-il conservé et renvoyé à `localhost` ? Options : (a) oui, rien à changer, (b) non, le développement et les tests E2E de session passent par
  `rssbase.localhost` et `fr.rssbase.localhost`, servis par `castor start` en plus de `localhost`. Recommandé : (b) si le prototype échoue, c'est le
  plus petit changement qui tient la règle 18 ; `localhost` reste utilisable pour une seule langue.
- **Affichage de l'avatar depuis le PDS** : `com.atproto.sync.getBlob` répond avec `content-disposition: attachment` et une politique de contenu
  restrictive. Options : (a) balise d'image pointant sur le PDS, (b) route de l'application qui relaie l'image avec un cache. Recommandé : (a), et (b)
  seulement si un navigateur refuse l'image.
- **Permissions fines chez Blacksky et NPMX** : non vérifiable sans compte. Rien à trancher, le repli est prévu ; à constater à l'usage.
- **Resserrement de la portée** : `repo:*` vaut « dans un premier temps ». Options : (a) `repo:<collection>` par collection RssBase, (b) un ensemble
  de permissions publié avec le lexicon. Recommandé : à trancher dans `abonnement-flux`, avec le lexicon ; les comptes connectés approuveront à nouveau.
