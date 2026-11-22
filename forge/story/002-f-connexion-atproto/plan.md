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
puis les jetons, la clé DPoP, le dernier nonce et la portée accordée. Un appareil a donc une session et un jeu de jetons. Le verrou de session de PHP
sérialise le rafraîchissement des jetons, qui sont à usage unique. L'utilisateur de Symfony Security est un objet tenu en session (DID, handle, adresse
de l'avatar, écriture accordée ou non) : aucune requête en base par page.

Chaque rafraîchissement donne un nouvel identifiant à la session, pour renouveler l'échéance de son cookie (`SessionRenewal`). La copie laissée sous
l'ancien identifiant sert les requêtes déjà parties, ne rafraîchit jamais les jetons et est refusée après cinq minutes. Les copies d'une session
partagent un nom de famille : se déconnecter sur l'une les termine toutes à leur requête suivante (`SignOutListener`, mémoire des familles
déconnectées dans `cache.app` pour 90 jours), sans appel au fournisseur. Le rafraîchissement suit trois issues : `invalid_grant` ou `invalid_client`
terminent la session ; toute autre erreur OAuth est redemandée après une minute, et la cinquième d'affilée termine la session (`PostponedRefresh`) ;
une panne, un `429` ou une erreur 5xx sont redemandés après une minute, sans limite.

La base ne reçoit que la trace de connexion : une table `account`, clée par le DID, mise à jour par une seule requête d'insertion ou de mise à jour à
chaque connexion réussie, datée par l'horloge de SQLite.

Une seule adresse de retour OAuth existe (`/oauth/callback` sur le site anglais, ou sur `127.0.0.1` en boucle locale, où la spécification interdit
`localhost`). Le paramètre `state` transporte le site de départ, signé avec le secret de l'application. Quand le retour arrive sur un autre hôte que
celui de départ, le contrôleur le renvoie tel quel vers l'hôte de départ, qui seul possède le cookie de session et termine l'échange, à condition que
ce site soit un site de RssBase : l'autre langue de son propre domaine, ou un nom local (`localhost`, `*.localhost`) quand un client en boucle locale
reçoit le retour sur `127.0.0.1`. Le cookie de session est posé sur le domaine parent, celui que `LocaleDomainListener` calcule déjà, pour valoir sur
les deux langues, sauf pour une adresse IP ou un nom à un seul label que les navigateurs refusent : `localhost` ne partage donc pas la session entre
langues, et le développement passe par `rssbase.localhost` et `fr.rssbase.localhost`.

Les appels sortants passent par un client unique (`atproto.client`) : `BoundedHttpClient` pose sur chaque requête un délai d'inactivité de 5 s, une
durée maximale de 10 s, aucune redirection et un plafond de 256 Kio, devant `NoPrivateNetworkHttpClient`, qui refuse les réseaux privés à chaque
saut. Les limites sont posées par requête parce que `NoPrivateNetworkHttpClient` remplace les options configurées du client scopé par ses propres
valeurs par défaut. Seule l'adresse well-known d'un handle suit jusqu'à trois redirections, que la spécification du handle autorise.

Les notions du protocole circulent en objets valeur (`Did`, `Handle`, `Pds`, `Issuer`, `HttpsUrl`, `Scope`, `State`, `Site`, `TokenPair`,
`FormBody`), qui se valident à la construction. L'entité `Account` porte un `Did` et un `Handle` par deux types DBAL.

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
- **`symfony/http-client` avec `NoPrivateNetworkHttpClient`, derrière `BoundedHttpClient`** : les adresses appelées viennent de la saisie du
  visiteur (domaine du handle, `did:web`, PDS du document DID). La chaîne refuse les réseaux privés à chaque saut et borne le temps, les redirections
  et la taille de chaque réponse. Les tests retirent `NoPrivateNetworkHttpClient`, qui résout chaque hôte par le vrai DNS, et répondent par le faux
  réseau (`mock_response_factory`).
- **`DpopProofFactory` de Symfony Security 8.2** (`Symfony\Component\Security\Http\OAuth2\Dpop`) : preuves DPoP signées en ES256 ; `DpopKey`
  tient la clé de la session et le dernier nonce.
- **`web-token/jwt-library`** : assertion du client confidentiel, génération et sérialisation des clés en JWK. L'application ne vérifie aucun jeton :
  les jetons d'accès AT Protocol sont opaques pour le client.
- **Session native sur fichiers** : `var/sessions/<environnement>`, cookie et session de 90 jours sans visite, la durée de vie d'un jeton de
  rafraîchissement inutilisé chez le fournisseur de référence. Le domaine du cookie est fixé à chaque requête principale par `LocaleDomainListener`,
  avant toute ouverture de session ; les sous-requêtes, rendues session ouverte, n'y touchent pas.
- **Écouteur `kernel.request` de rafraîchissement** (`TokenRefreshListener`) : pour un utilisateur connecté dont le jeton d'accès a expiré,
  rafraîchit les jetons puis renouvelle l'identifiant de session. Il refuse une copie de session périmée ou dont la famille s'est déconnectée, et
  applique les trois issues décrites plus haut (règles 16 et 17).
- **Écouteur de `LogoutEvent`** (`SignOutListener`, sur le répartiteur du pare-feu `main`, avant le vidage de la session) : mémorise la déconnexion
  de la famille de la session.
- **Processeur Monolog** (`RedactAuthorizationCode`) : masque le code d'autorisation dans l'adresse que le canal `request` journalise.
- **Jeton CSRF sans état** de Symfony sur les trois formulaires POST (connexion, choix d'un fournisseur, déconnexion) ;
  `assets/controllers/csrf_protection_controller.js` existe déjà.
- **Requête d'insertion ou de mise à jour en DBAL** dans `AccountRepository`, avec `CURRENT_TIMESTAMP` : une seule requête atomique, sans lecture
  préalable, datée par la base (règle « Database queries » d'`AGENTS.md`).
- **Mise en page partagée** : l'en-tête et les couleurs de l'accueil, aujourd'hui écrits dans `home/index.html.twig`, passent dans une mise en page et
  un composant Twig anonyme que les quatre écrans étendent.
- **Filtre des fournisseurs par liens** (`?region=eu`) : rendu côté serveur, sans JavaScript, testable en `WebTestCase`. La région se lit par
  `#[MapQueryParameter]` sur l'enum `Region`.
- **Types DBAL** (`DidType`, `HandleType`, déclarés dans `config/packages/doctrine.yaml`) : l'entité manipule des objets valeur sur des colonnes texte.

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
| `did` | `Did`, type DBAL `did` (texte, 2048) | non | unique (index `uniq_account_did`) |
| `handle` | `Handle`, type DBAL `handle` (texte, 253) | non | |
| `lastLoginAt` (`last_login_at`) | `datetime_immutable` | non | écrit par `CURRENT_TIMESTAMP` |

La clé métier est le DID : un compte dont le handle change garde sa ligne et prend le nouveau handle (règle 30). Le handle n'est ni unique ni indexé :
aucune requête ne filtre dessus. Pas de date de première connexion : le cadrage demande le handle et la dernière connexion. La structure n'a aucun
cloisonnement, c'est une trace d'exploitation lue par `bin/console dbal:run-sql`. Première migration du projet, générée par
`doctrine:migrations:diff`, jamais écrite à la main.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `app/src/AtProto/HttpsUrl.php` | Objet valeur : adresse https, test « hôte seul », ajout d'une requête. |
| `app/src/AtProto/JsonObject.php` | Lecture typée d'un document JSON reçu du réseau. |
| `app/src/AtProto/BoundedHttpClient.php` | Délai, durée maximale, redirections et taille de chaque requête sortante. |
| `app/src/AtProto/Identity/Handle.php` | Objet valeur : normalise la saisie (espaces, `@`, casse, complétion `.bsky.social`) et valide la syntaxe. |
| `app/src/AtProto/Identity/Did.php`, `Pds.php`, `Identity.php` | Objets valeur : DID, PDS (hôte https seul), identité vérifiée (DID, handle, PDS). |
| `app/src/AtProto/Identity/HandleResolver.php` | Handle vers DID par DNS `_atproto` puis `/.well-known/atproto-did` (trois redirections au plus). |
| `app/src/AtProto/Identity/DnsTxtRecords.php`, `SystemDnsTxtRecords.php` | Lecture des enregistrements TXT, remplaçable en test. |
| `app/src/AtProto/Identity/DidResolver.php`, `DidDocument.php` | Document DID (`did:plc`, `did:web`) : PDS et handle déclaré. |
| `app/src/AtProto/Identity/IdentityResolver.php` | Assemble les deux résolutions et la vérification bidirectionnelle. |
| `app/src/AtProto/Identity/Exception/AccountNotFound.php`, `ProviderUnreachable.php` | Les deux causes d'échec que l'interface distingue. |
| `app/src/AtProto/OAuth/AuthorizationServerLocator.php`, `AuthorizationServer.php`, `Issuer.php` | PDS vers métadonnées de ressource protégée, puis du serveur d'autorisation, avec contrôle de l'émetteur et des points de terminaison https. |
| `app/src/AtProto/OAuth/ClientIdentity.php` | Mode du client (confidentiel ou boucle locale), `client_id`, adresse de retour, document de métadonnées, assertion du client. |
| `app/src/AtProto/OAuth/DpopKey.php`, `DpopResponse.php` | Clé DPoP de la session et dernier nonce ; réponse d'un serveur à une requête DPoP. |
| `app/src/AtProto/OAuth/OAuthClient.php`, `FormBody.php`, `PushedAuthorization.php` | PAR, adresse d'autorisation, échange du code, rafraîchissement, reprise sur `use_dpop_nonce`, classement des erreurs. |
| `app/src/AtProto/OAuth/Exception/OAuthError.php` | Refus OAuth, et ceux qui terminent l'accès (`invalid_grant`, `invalid_client`). |
| `app/src/AtProto/OAuth/PendingAuthorization.php`, `State.php` | État d'une demande en cours, tenu en session. |
| `app/src/AtProto/OAuth/TokenSet.php`, `TokenPair.php`, `Scope.php` | Jetons, échéance, portée accordée, émetteur, clé DPoP, tenus en session. |
| `app/src/AtProto/OAuth/SignedState.php`, `Site.php` | Fabrique et vérifie le `state` qui transporte le site de départ. |
| `app/src/AtProto/OAuth/Base64Url.php` | Encodage base64url des vérificateurs et signatures. |
| `app/src/AtProto/Profile/AvatarLocator.php` | Lit `app.bsky.actor.profile` sur le PDS et rend l'adresse du fichier de l'avatar, ou rien. |
| `app/src/AtProto/Provider/Provider.php`, `ProviderCatalog.php`, `Region.php`, `ProviderLabel.php` | Les fournisseurs proposés (nom, émetteur, région, étiquette) lus dans un paramètre. |
| `app/src/Security/AtProtoUser.php` | Utilisateur en session : DID, handle, avatar, écriture accordée. |
| `app/src/Security/SessionUserProvider.php` | Rend l'utilisateur de la session sans le recharger. |
| `app/src/Security/AtProtoAuthenticator.php` | Authentifie sur la route de retour OAuth, trace la connexion, redirige vers la page demandée. |
| `app/src/Security/AuthorizationFlow.php` | Demande en cours et jetons de la session, report du rafraîchissement. |
| `app/src/Security/LoginEntryPoint.php` | Renvoie un visiteur vers la connexion. |
| `app/src/Security/LoginFailure.php` | Cause d'un échec, sous forme de clé de traduction. |
| `app/src/Security/SessionRenewal.php`, `PostponedRefresh.php` | Renouvellement d'identifiant de session, famille et déconnexion commune ; refus consécutifs d'un rafraîchissement. |
| `app/src/EventListener/TokenRefreshListener.php` | Rafraîchit les jetons expirés ; ferme la session quand le fournisseur a retiré l'accès. |
| `app/src/EventListener/SignOutListener.php` | Mémorise la déconnexion de la famille de la session. |
| `app/src/Log/RedactAuthorizationCode.php` | Masque le code d'autorisation dans le journal `request`. |
| `app/src/Controller/LoginController.php` | `GET /login` (formulaire) et `POST /login` (départ depuis un handle). |
| `app/src/Controller/ProviderController.php` | `GET /providers` (liste filtrée) et `POST /providers/{id}` (départ avec `prompt=create`). |
| `app/src/Controller/OAuthCallbackController.php` | `GET /oauth/callback` : renvoi vers le site de départ, s'il est un site de RssBase, quand l'hôte diffère. |
| `app/src/Controller/ClientMetadataController.php` | `GET /oauth-client-metadata.json` en mode confidentiel, 404 sinon. |
| `app/src/Controller/FeedsController.php` | `GET /feeds` : « Mes flux », état vide. |
| `app/src/Command/GenerateClientKeyCommand.php` | Génère la clé ES256 du client au format JWK, à placer dans les secrets. |
| `app/src/Doctrine/DidType.php`, `HandleType.php` | Types DBAL des colonnes `did` et `handle`. |
| `app/src/Entity/Account.php` | Trace de connexion. |
| `app/src/Repository/AccountRepository.php` | `recordLogin(Did, Handle)` : insertion ou mise à jour datée par la base. |
| `app/src/Migrations/Version20261121221813.php` | Création de la table `account`, générée. |
| `app/config/packages/security.yaml` | Pare-feu, fournisseur d'utilisateur, déconnexion, `access_control`. |
| `app/config/packages/csrf_protection.yaml` | Jetons CSRF sans état `login`, `provider`, `logout`. |
| `app/config/packages/http_client.yaml` | Client scopé AT Protocol (`User-Agent`) ; fabrique de réponses du faux réseau en test. |
| `app/config/routes/security.yaml` | Routes du pare-feu (déconnexion). |
| `app/templates/site.html.twig` | Mise en page commune : couleurs, police, en-tête. |
| `app/templates/components/Site/Header.html.twig` | En-tête : logo, menu des langues, liens, compte connecté et déconnexion. |
| `app/templates/login/index.html.twig` | Champ handle, message d'échec, phrase sur le mot de passe, lien vers les fournisseurs. |
| `app/templates/provider/index.html.twig` | Fournisseurs, filtre Tous / UE / US, liens sortants, retour à la connexion. |
| `app/templates/feeds/index.html.twig` | État vide de « Mes flux ». |
| `app/tests/Fake/FakeAtProtoNetwork.php` | Faux réseau AT Protocol : DNS, documents DID, PDS, profil, pannes et refus simulés. |
| `app/tests/Fake/FakeAuthorizationServer.php`, `FakeOAuthEndpoints.php`, `FakeOAuthStore.php`, `FakeTokenGrants.php`, `FakeClientVerifier.php`, `FakeDpopVerifier.php`, `FakeJwt.php`, `FakeOAuthError.php`, `FakeField.php` | Faux serveur d'autorisation : PAR, jetons, vérification de PKCE, de DPoP et de l'assertion du client. |
| `app/tests/Fake/FakeProviderRoute.php` | Page d'approbation du faux fournisseur, servie au navigateur par une route Playwright. |
| `app/tests/Unit/AtProto/Identity/HandleTest.php`, `PdsTest.php`, `HandleResolverTest.php`, `IdentityResolverTest.php` | Normalisation du handle, PDS, résolution. |
| `app/tests/Unit/AtProto/OAuth/*Test.php` | Client, assertion, `state` signé, localisation du serveur, exigences du faux serveur. |
| `app/tests/Unit/AtProto/BoundedHttpClientTest.php`, `HttpsUrlTest.php`, `Profile/AvatarLocatorTest.php` | Limites des requêtes, adresses, avatar. |
| `app/tests/Functional/LoginTest.php`, `LoginFailureTest.php`, `SignsInThroughTheFakeProvider.php` | Connexion par handle, échecs, page demandée retrouvée, redirections d'un utilisateur connecté. |
| `app/tests/Functional/ProviderTest.php` | Liste, filtre, départ chez un fournisseur et retour connecté. |
| `app/tests/Functional/SessionTest.php`, `RefreshFailureTest.php` | Deux langues, durée du cookie, déconnexion, second appareil, rafraîchissement et ses échecs. |
| `app/tests/Functional/OAuthCallbackTest.php`, `ClientMetadataTest.php`, `AccountDisplayTest.php` | Renvoi du retour OAuth, document de métadonnées, compte affiché. |
| `app/tests/Integration/AccountRepositoryTest.php`, `RedactAuthorizationCodeTest.php` | Trace de connexion, journal sans code. |
| `app/tests/Integration/SessionKernelTestCase.php`, `SessionRenewalTest.php`, `TokenRefreshListenerTest.php`, `LocaleDomainListenerTest.php` | Écouteurs et renouvellement sur les sessions natives de PHP. |
| `app/tests/E2E/LoginBrowserTest.php` | Parcours complet dans Chromium, session commune aux deux langues, réouverture du navigateur. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `app/composer.json`, `app/composer.lock`, `app/symfony.lock`, `app/config/bundles.php` | Ajouter `symfony/security-bundle`, `symfony/security-csrf`, `symfony/http-client` et `web-token/jwt-library`. |
| `app/config/reference.php`, `app/config/schema.json` | Régénérés avec le conteneur. |
| `app/config/packages/framework.yaml` | Session : stockage sur fichiers dans `var/sessions/<environnement>`, cookie et session de 90 jours. |
| `app/config/packages/doctrine.yaml` | Déclarer les types DBAL `did` et `handle`. |
| `app/config/services.yaml` | Paramètres `app.atproto.scope` (`atproto repo:*`), `app.atproto.providers` et les deux liens sortants ; chaîne `atproto.client` ; faux réseau en test. |
| `app/.env` | `ATPROTO_CLIENT_PRIVATE_JWK` vide par défaut (boucle locale). |
| `app/Makefile` | Cible `test-database` : base de test migrée. |
| `app/src/EventListener/LocaleDomainListener.php` | Fixer aussi le domaine du cookie de session sur le domaine calculé, sauf adresse IP ou nom à un seul label ; ignorer les sous-requêtes. |
| `app/src/Controller/HomeController.php` | Rediriger un utilisateur connecté vers « Mes flux ». |
| `app/templates/home/index.html.twig` | Étendre la mise en page commune ; « Commencer » mène à la connexion ; l'en-tête et les couleurs sortent du gabarit. |
| `app/translations/messages+intl-icu.en.yaml`, `.fr.yaml` | Clés `login.*`, `providers.*`, `feeds.*`, `account.*`. |
| `castor.php`, `infra/packages/docker-image-dev.nix` | Servir aussi `*.rssbase.localhost` et `127.0.0.1` (retour OAuth en boucle locale). |
| `.castor/qa.php` | Construire la base de test avant les suites ; contrôle des traductions limité au domaine `messages`. |
| `AGENTS.md` | Section « Sign-in » : boucle locale et retour sur `127.0.0.1`, clé du client dans les secrets, sessions, lecture de la trace, faux réseau ; adresses `rssbase.localhost`. |

## Hors scope

- **Rotation de la clé du client** : un seul `kid` publié. La rotation arrive avec la story d'hébergement.
- **Limitation du débit des tentatives de connexion** : chaque tentative déclenche des appels sortants. À traiter avant la mise en production, pas ici.
- **Révocation des jetons à la déconnexion** : écartée au pitch (règle 22).
- **Écriture dans le PDS, lexicon, resserrement de la portée** : `abonnement-flux`.
- **Purge des fichiers de session et des traces anciennes** : le ramasse-miettes de PHP suffit à ce stade.
- **`forge/stack.md`** : pas de retouche à la main pendant l'implémentation ; réaligné par `/forge:sync` à la clôture.
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
| `app/src/AtProto/Identity/Handle.php`, `Pds.php` | unit | `@`, espaces, casse ; nom sans point complété par `.bsky.social` ; syntaxe invalide refusée ; PDS réduit à un hôte https. |
| `app/src/AtProto/Identity/HandleResolver.php`, `IdentityResolver.php` | unit (faux réseau) | DNS puis well-known, redirections permises ; handle non confirmé par le document DID refusé ; `did:web` ; adresse de réseau privé refusée. |
| `app/src/AtProto/BoundedHttpClient.php`, `HttpsUrl.php` | unit | Plafond de 256 Kio ; délai et durée maximale reçus par le transport derrière `NoPrivateNetworkHttpClient` ; redirection suivie seulement si la requête le permet ; requête ajoutée à une adresse qui en a une. |
| `app/src/AtProto/OAuth/OAuthClient.php`, `ClientIdentity.php`, `AuthorizationServerLocator.php` | unit (faux serveur) | PAR, échange, rafraîchissement à usage unique, reprise sur nonce, preuve DPoP et assertion vérifiées par le faux serveur ; `client_id` et adresse de retour des deux modes ; émetteur ou point de terminaison hors https refusés ; 429 et 5xx ne sont pas des refus. |
| `app/src/AtProto/OAuth/SignedState.php` | unit | Aller-retour ; signature altérée ou site qui n'est pas une origine refusés. |
| `app/src/Controller/LoginController.php`, `AtProtoAuthenticator.php` | functional | Connexion complète contre le faux fournisseur ; handle introuvable, fournisseur injoignable, approbation refusée, chacun avec son message et le handle conservé ; `state`, `iss` ou `sub` faux refusés ; réponse utilisée une seule fois. |
| `app/src/Controller/OAuthCallbackController.php` | functional | Renvoi vers l'autre langue du même domaine ; refus d'un domaine étranger ; en boucle locale, renvoi seulement vers un nom local. |
| `app/config/packages/security.yaml` | functional | `/feeds` anonyme renvoie à la connexion puis revient à `/feeds` ; adresse inconnue toujours en 404 ; utilisateur connecté redirigé depuis l'accueil, la connexion et les fournisseurs. |
| `app/src/EventListener/TokenRefreshListener.php` | functional + integration (sessions natives) | Jeton expiré rafraîchi sans rien montrer ; `invalid_grant` et clé du client inconnue ramènent à la connexion avec le message de fin de session ; panne et 429 ne déconnectent pas et ne sont pas redemandés aussitôt ; cinq refus d'affilée terminent la session ; ancien identifiant refusé après le délai de grâce, jamais rafraîchi ; déconnexion sur l'ancien identifiant termine le nouveau. |
| `app/src/Security/SessionRenewal.php`, `app/src/EventListener/LocaleDomainListener.php` | integration (sessions natives) | Marque de l'ancien identifiant et son refus ; déconnexion de la famille ; sous-requête sans réglage de session. |
| `app/src/Controller/ProviderController.php` | functional | Quatre fournisseurs et leurs étiquettes ; filtres UE et US ; liens sortants ; départ avec `prompt=create` sans `login_hint` ; retour connecté ; émetteur différent de celui du compte refusé. |
| Portée accordée | functional | Faux fournisseur qui ne rend que `atproto` : connexion réussie, `AtProtoUser` sans écriture, même page « Mes flux ». |
| `app/src/Repository/AccountRepository.php`, `app/src/Log/RedactAuthorizationCode.php` | integration (SQLite) | Insertion, reconnexion qui avance la date, nouveau handle sur le même DID sans seconde ligne ; code masqué dans le journal. |
| `app/src/Controller/ClientMetadataController.php` | functional | JSON conforme et clé publique seule en mode confidentiel ; 404 en boucle locale. |
| Parcours complet | E2E | Accueil, « Commencer », handle, approbation sur la page du faux fournisseur servie par une route Playwright, « Mes flux » avec avatar ; l'autre site de langue sans reconnexion ; réouverture du navigateur ; déconnexion ; aucune erreur de console. |

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

10. [x] **Parcours navigateur, documentation, QA**
    - Objectif : parcours E2E, `AGENTS.md` à jour, contrôle complet.
    - Fichiers : `tests/E2E/LoginBrowserTest.php`, `AGENTS.md`.
    - Vérification : `CI=1 nix develop -c castor qa` vert.
    - Commitable seule : oui.

## Critères de sortie

- [x] `CI=1 nix develop -c castor qa` est vert : Mago, symfony-lsp, `lint:*`, traductions, suites `default` et `e2e`, `composer audit`.
- [x] Chaque critère d'acceptation du pitch est couvert par un test fonctionnel ou E2E nommé, hors vérification contre un vrai fournisseur.
- [ ] La suite de tests n'émet aucune requête réseau : le client HTTP de test n'a que le faux réseau.
- [x] Le faux fournisseur refuse une demande sans PKCE S256, sans preuve DPoP valide ou, en mode confidentiel, sans assertion valide.
- [x] `doctrine:schema:validate` est vert et la migration s'applique sur une base vide.
- [x] Le plan d'exécution de la requête de trace utilise l'index unique sur `did`, preuve consignée.
- [ ] Aucun jeton, clé privée ou code d'autorisation n'apparaît dans les journaux ni dans une réponse HTTP.
- [ ] En boucle locale, le départ vers un vrai fournisseur produit une demande PAR acceptée (vérification manuelle, consignée ou signalée non faite).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Un navigateur refuse un cookie posé sur le domaine `localhost` : la session commune aux deux langues ne marche pas en développement ni en E2E | élevée | Survenu (Chromium 157) ; repli appliqué : `rssbase.localhost` et `fr.rssbase.localhost`, servis par `castor start` et l'image de développement. |
| Écart avec la spécification OAuth AT Protocol, invisible contre un faux fournisseur écrit par la même main | moyenne | Faux fournisseur écrit depuis `atproto.com/specs/oauth` et le code de référence ; lecture de `wecoprod/atproto-oauth-bundle` en contrôle ; vérification manuelle contre `bsky.social`. |
| Requêtes vers le réseau interne à partir d'un handle ou d'un `did:web` forgé | moyenne | `NoPrivateNetworkHttpClient` à chaque saut, limites de temps, de redirection et de taille posées par `BoundedHttpClient`, tests dédiés. |
| `web-token/jwt-library` exige `gmp` ou `bcmath`, absents du PHP maison | faible | Non survenu : ES256 signe avec OpenSSL seul, aucune extension ajoutée. |
| Blacksky ou NPMX n'accordent pas `repo:*` (versions dérivées) | moyenne | Comportement prévu : connexion en identité seule, lue dans la portée accordée. |
| Le PDS sert l'avatar avec des en-têtes qui empêchent son affichage dans une page tierce | moyenne | Non survenu : avec les en-têtes du PDS de référence, Chromium affiche l'image ; pas de relais. |
| État conservé entre requêtes en mode worker (nonce, clé, utilisateur) | faible | Aucun service ne garde d'état de requête : tout passe par la session. |
| Deux requêtes simultanées rafraîchissent le même jeton à usage unique | faible | Verrou de session de PHP ; le rafraîchissement se fait session ouverte. |
| Mode confidentiel faux en production faute de vérification réelle | moyenne | Assertion contrôlée par le faux fournisseur ; vérification réelle inscrite à la story d'hébergement. |

## Questions ouvertes

- **Cookie de session sur `localhost`** : à prototyper dans Chromium en début d'étape 7. Un cookie posé avec `Domain=localhost` depuis `fr.localhost`
  est-il conservé et renvoyé à `localhost` ? Options : (a) oui, rien à changer, (b) non, le développement et les tests E2E de session passent par
  `rssbase.localhost` et `fr.rssbase.localhost`, servis par `castor start` en plus de `localhost`. Recommandé : (b) si le prototype échoue, c'est le
  plus petit changement qui tient la règle 18 ; `localhost` reste utilisable pour une seule langue. → tranché : (b), le prototype a échoué
  (Chromium refuse `Domain=localhost` posé depuis `fr.localhost`).
- **Affichage de l'avatar depuis le PDS** : `com.atproto.sync.getBlob` répond avec `content-disposition: attachment` et une politique de contenu
  restrictive. Options : (a) balise d'image pointant sur le PDS, (b) route de l'application qui relaie l'image avec un cache. Recommandé : (a), et (b)
  seulement si un navigateur refuse l'image. → tranché : (a), l'image s'affiche dans Chromium.
- **Permissions fines chez Blacksky et NPMX** : non vérifiable sans compte. Rien à trancher, le repli est prévu ; à constater à l'usage.
- **Resserrement de la portée** : `repo:*` vaut « dans un premier temps ». Options : (a) `repo:<collection>` par collection RssBase, (b) un ensemble
  de permissions publié avec le lexicon. Recommandé : à trancher dans `abonnement-flux`, avec le lexicon ; les comptes connectés approuveront à nouveau.
