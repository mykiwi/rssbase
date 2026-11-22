# S'abonner à des flux depuis RssBase et garder ses abonnements dans son compte AT Protocol

> **But** : figer l'intention métier des abonnements : comment un utilisateur suit un flux, où vit son abonnement, ce que la liste lui montre, et comment il s'en défait.
> **Registre** : fonctionnel
> **Story** : `forge/story/003-f-abonnement-flux/`
> **Amont** : aucun

Un utilisateur connecté s'abonne à un flux en saisissant l'adresse du flux ou celle du site, retrouve ses abonnements dans « Mes flux » et s'en désabonne. Ses abonnements sont écrits dans son compte AT Protocol, et ceux qu'il a déjà dans Skyreader s'affichent avec eux.

## Contexte

La connexion est livrée : un utilisateur entre dans RssBase avec son compte AT Protocol, et son fournisseur autorise RssBase à écrire dans ce compte. Mais « Mes flux » reste une page vide, et rien ne permet d'y ajouter un flux. RssBase n'a donc aucun usage tant qu'on ne peut pas s'abonner, et la lecture des articles, prochaine feature du lancement, n'a rien à lire.

C'est aussi la première fois que RssBase écrit dans le compte de l'utilisateur. La promesse du produit se joue ici : un abonnement doit vivre chez l'utilisateur, dans un format qu'un autre lecteur sait relire, et non dans la base de RssBase. Au cadrage, deux lecteurs publiaient déjà leur format d'abonnement, Skyreader et Glean, mais il n'existait pas de format commun adopté. Un utilisateur de Skyreader a donc déjà des abonnements dans son compte. S'il ne les retrouve pas dans RssBase, changer de lecteur lui coûte autant qu'aujourd'hui.

## Alignement vision

- **Problème adressé** : directement. Les abonnements sont la première donnée de lecture qui quitte la base du lecteur pour le compte de l'utilisateur. Celui qui arrive de Skyreader retrouve les siens sans rien faire.
- **Audience servie** : l'utilisateur principal, au premier usage (P5) et quand il change de lecteur depuis Skyreader.
- **Principes respectés** :
  - Le principe 1 est tenu : RssBase ne garde rien chez lui, pas même la liste des flux masqués.
  - Le principe 3 est tenu : les abonnements naissent publics, la bascule en privé viendra avec `bascule-public-prive`. Le masque d'un flux Skyreader est public lui aussi, et c'est assumé.
  - Le principe 4 est tenu : la liste suit la date d'abonnement, un ordre que l'utilisateur sait expliquer.
  - Le principe 2 est sous tension : un format publié ne change plus de nom sans devenir un nouveau format. Or son nom dépend d'une adresse que RssBase doit posséder, et RssBase n'en a pas encore (voir Questions ouvertes).
- **Hypothèse testée** : H2, en partie levée au cadrage. Aucun format communautaire n'est adopté, mais Skyreader et Glean publient chacun le leur. La vision recommandait de s'aligner sur l'existant. Le choix fait ici s'en écarte en partie : RssBase publie son propre format, pour garder la maîtrise d'un contrat qu'il versionne, et il lit celui de Skyreader, pour que l'utilisateur qui en vient ne perde rien.
- **Impact North Star** : direct pour la moitié qui concerne les abonnements, écrits par RssBase dans le compte de l'utilisateur. Partiel pour l'interopérabilité visée à un an : RssBase lit déjà les abonnements d'un autre lecteur, et la réciproque attend qu'un autre lecteur implémente le format de RssBase.

## Utilisateurs concernés

- **Utilisateur connecté dont le fournisseur accorde l'écriture** : s'abonne à un flux, voit la liste de ses abonnements, s'en désabonne.
- **Utilisateur connecté sans droit d'écriture** : voit ses abonnements existants sans pouvoir les modifier, et peut réapprouver RssBase chez son fournisseur.
- **Utilisateur qui arrive de Skyreader** : retrouve dans « Mes flux » les abonnements qu'il a enregistrés dans Skyreader.
- **Autres lecteurs AT Protocol** : peuvent lire les abonnements écrits par RssBase, qui sont publics.
- **Visiteur non connecté** et **exploitant** : aucun changement.

## User Stories

- En tant qu'**utilisateur connecté**, je veux m'abonner en collant l'adresse d'un site afin de ne pas avoir à chercher son adresse RSS.
- En tant qu'**utilisateur connecté**, je veux choisir quel flux suivre quand un site en propose plusieurs afin de ne pas suivre les commentaires à la place des articles.
- En tant qu'**utilisateur connecté**, je veux comprendre pourquoi une adresse est refusée et retrouver ce que j'avais saisi afin de corriger sans tout retaper.
- En tant qu'**utilisateur connecté**, je veux que mes abonnements soient enregistrés dans mon compte AT Protocol afin de les garder si je quitte RssBase.
- En tant qu'**utilisateur qui arrive de Skyreader**, je veux retrouver mes abonnements Skyreader dans « Mes flux » afin de changer de lecteur sans rien ressaisir.
- En tant qu'**utilisateur connecté**, je veux voir mes flux du plus récent au plus ancien afin de retrouver tout de suite celui que je viens d'ajouter.
- En tant qu'**utilisateur connecté**, je veux ne voir chaque flux qu'une fois, même si je le suis dans RssBase et dans Skyreader.
- En tant qu'**utilisateur connecté**, je veux me désabonner d'un flux en un geste et pouvoir annuler aussitôt afin de corriger un clic de travers.
- En tant qu'**utilisateur qui garde Skyreader**, je ne veux PAS qu'un désabonnement dans RssBase retire le flux de Skyreader.
- En tant qu'**utilisateur sans droit d'écriture**, je veux comprendre pourquoi je ne peux pas m'abonner et réparer cela seul afin de ne pas rester bloqué.

## Règles métier

**S'abonner**

1. Le formulaire d'abonnement est en haut de « Mes flux ». Il a un seul champ, qui accepte l'adresse d'un flux ou celle d'un site.
2. La saisie ignore les espaces autour de l'adresse, et ajoute `https://` quand il manque.
3. RssBase ouvre l'adresse saisie :
   - si c'est un flux RSS ou Atom, l'abonnement porte sur ce flux ;
   - si c'est une page qui annonce un seul flux, l'abonnement porte sur ce flux, sans étape de plus ;
   - si elle en annonce plusieurs, RssBase les liste avec leur titre et l'utilisateur choisit celui qu'il suit.
4. Une adresse injoignable donne un message qui le dit, et une adresse du réseau privé est refusée comme injoignable. Une page joignable mais sans flux, ou un flux illisible, donne un autre message. Dans les deux cas, la saisie reste dans le champ.
5. Un flux déjà suivi, dans RssBase ou dans Skyreader, n'est pas ajouté une seconde fois : un message dit qu'il est déjà dans « Mes flux ». S'abonner à un flux Skyreader masqué lève le masque, et le flux réapparaît.
6. Le titre retenu est celui que le flux annonce au moment de l'abonnement, et à défaut son adresse. Un flux venu de Skyreader garde le titre que Skyreader lui donne.

**Où vivent les abonnements**

7. Chaque abonnement créé dans RssBase est écrit dans le compte AT Protocol de l'utilisateur, au format de RssBase. RssBase n'en garde aucune copie chez lui.
8. Les abonnements sont publics : tout lecteur AT Protocol peut les lire.
9. Le format de RssBase est publié et documenté, pour qu'un autre lecteur puisse le relire et l'écrire. Un changement cassant en fait un nouveau format.
10. RssBase lit aussi les abonnements que Skyreader a écrits dans le compte de l'utilisateur. Il n'en retient que les flux RSS et Atom : les autres entrées de Skyreader sont ignorées.
11. RssBase n'écrit jamais dans les abonnements de Skyreader.

**« Mes flux »**

12. La liste montre une ligne par flux, quel que soit le nombre d'abonnements enregistrés derrière (dans RssBase, dans Skyreader ou les deux).
13. Les flux sont classés du plus récemment ajouté au plus ancien, selon leur date d'abonnement. Un flux suivi dans les deux lecteurs prend la plus ancienne des deux dates.
14. Chaque ligne affiche le titre du flux, l'adresse de son site en lien sortant (à défaut, l'adresse du flux), et la mention « Skyreader » quand le flux y est suivi.
15. La liste est relue à chaque ouverture de « Mes flux » : un abonnement ajouté depuis un autre lecteur apparaît à la visite suivante.
16. La liste est complète, sans pagination.
17. Sans aucun flux, la page garde son état vide, qui invite désormais à s'abonner.

**Se désabonner**

18. « Se désabonner » agit tout de suite, sans confirmation. Le flux quitte la liste, et un message « Désabonné de », suivi du titre du flux, propose « Annuler » pendant quelques secondes.
19. Pour un flux suivi dans RssBase, l'abonnement est supprimé du compte de l'utilisateur.
20. Pour un flux suivi dans Skyreader, RssBase le masque. Le flux reste dans Skyreader, et le message le dit : « Masqué dans RssBase, il reste dans Skyreader ». Le masque est écrit dans le compte de l'utilisateur, au format de RssBase, et il est public comme le reste.
21. Pour un flux suivi dans les deux, l'abonnement RssBase est supprimé et le flux Skyreader est masqué.
22. « Annuler » rétablit le flux à sa place dans la liste, avec sa date d'abonnement d'origine. Passé le délai, ou après avoir quitté la page, le désabonnement est définitif.
23. Un masque ne se lève qu'en se réabonnant au flux (règle 5).

**Droit d'écriture**

24. À la connexion, RssBase ne demande plus l'écriture sur tout le compte, seulement sur ses propres données : ses abonnements et ses masques. C'est ce que le fournisseur présente à l'utilisateur au moment d'approuver.
25. Un compte sans droit d'écriture voit sa liste, flux Skyreader compris, mais sans formulaire d'abonnement ni désabonnement. Un message explique que RssBase ne peut pas enregistrer ses abonnements, avec un bouton qui le renvoie chez son fournisseur pour approuver à nouveau. Il revient ensuite sur « Mes flux ».

## Critères d'acceptation

- [ ] Saisir l'adresse d'un flux RSS ajoute ce flux en tête de « Mes flux », avec son titre.
- [ ] Saisir l'adresse d'un flux Atom l'ajoute de la même façon.
- [ ] Saisir l'adresse d'un site qui annonce un seul flux abonne à ce flux, sans étape de plus.
- [ ] Saisir l'adresse d'un site qui annonce plusieurs flux les liste avec leur titre, et seul le flux choisi est ajouté.
- [ ] Une adresse saisie sans `https://` et entourée d'espaces abonne au même flux que l'adresse complète.
- [ ] Une adresse injoignable ramène au formulaire avec la saisie conservée et un message qui dit que l'adresse est injoignable.
- [ ] Une page sans flux ramène au formulaire avec la saisie conservée et un message distinct de celui de l'adresse injoignable.
- [ ] Une adresse du réseau privé est refusée avec le message de l'adresse injoignable.
- [ ] S'abonner à un flux déjà suivi affiche un message qui le dit, et la liste reste inchangée.
- [ ] Un abonnement créé dans RssBase se lit dans le compte AT Protocol de l'utilisateur avec un outil tiers, sans passer par RssBase.
- [ ] Un abonnement créé dans RssBase apparaît sur un second appareil connecté au même compte, et après une déconnexion puis une reconnexion.
- [ ] Un abonnement Skyreader à un flux RSS apparaît dans « Mes flux » avec la mention « Skyreader » ; une entrée Skyreader qui n'est pas un flux RSS ou Atom n'apparaît pas.
- [ ] Un flux suivi à la fois dans RssBase et dans Skyreader n'apparaît qu'une fois.
- [ ] Les flux sont classés du plus récemment ajouté au plus ancien.
- [ ] Le lien d'une ligne ouvre le site du flux.
- [ ] Se désabonner d'un flux suivi dans RssBase le retire de la liste et du compte de l'utilisateur ; il n'y est plus à la visite suivante.
- [ ] Se désabonner d'un flux Skyreader le retire de la liste avec un message qui dit qu'il reste dans Skyreader ; l'abonnement Skyreader est toujours dans le compte, et le flux ne réapparaît pas dans RssBase à la visite suivante.
- [ ] « Annuler » après un désabonnement rétablit le flux à la même place dans la liste.
- [ ] Se réabonner à un flux Skyreader masqué le fait réapparaître.
- [ ] Sans aucun flux, « Mes flux » affiche un état vide qui invite à s'abonner.
- [ ] À la connexion, le fournisseur demande l'approbation de l'écriture des seules données RssBase, et non de tout le compte.
- [ ] Un compte sans droit d'écriture voit sa liste sans formulaire ni désabonnement, avec un message et un bouton qui le renvoie chez son fournisseur, puis sur « Mes flux ».
- [ ] Le format des abonnements de RssBase est documenté publiquement.
- [ ] Tous les écrans et messages de cette feature existent en français et en anglais.

## Hors scope

- **Abonnement privé et bascule public/privé** : reste dans `bascule-public-prive` (V2). Les masques sont publics jusque-là.
- **Import OPML** : reste dans `import-opml` (V2).
- **Lecture des articles, récupération régulière des flux, page d'un flux, flux devenu injoignable après l'abonnement** : appartiennent à `lecture-chronologique`.
- **Renommer un flux, le ranger dans un dossier, lui poser des étiquettes** : Skyreader le permet. RssBase n'affiche ni ne modifie ces informations.
- **Abonnements d'autres lecteurs que Skyreader** (Glean et les suivants) : non lus.
- **Sources Skyreader qui ne sont pas des flux** (publications et contenus AT Protocol) : ignorées.
- **JSON Feed** : seuls RSS et Atom sont acceptés.
- **Écrire dans les abonnements Skyreader**, y compris pour s'y désabonner : RssBase masque, il ne supprime pas chez un autre lecteur.
- **Écran des flux masqués** : un masque ne se lève qu'en se réabonnant.
- **Recherche, filtre et pagination de la liste** : la liste est complète et classée par date.
- **Export OPML** : absent du backlog. Les abonnements sont déjà dans le compte de l'utilisateur, lisibles par tout lecteur.

## Impacts transverses

- **Traduction / langues** : oui. Formulaire, choix entre plusieurs flux, messages d'échec et de doublon, liste, mention « Skyreader », désabonnement et « Annuler », message et bouton du compte sans droit d'écriture. Les titres des flux restent dans leur langue.
- **Droits d'accès** : « Mes flux » reste réservée aux utilisateurs connectés. Nouvelle distinction entre un compte qui accorde l'écriture et un compte qui ne l'accorde pas.
- **Cloisonnement des données** : chaque utilisateur ne voit que les abonnements de son propre compte.
- **Apparence / déclinaisons** : non. Mêmes écrans sur les deux sites, seule la langue change.
- **Exposition à des tiers** : oui. Les abonnements et les masques sont publics dans le compte de l'utilisateur, lisibles par tout lecteur AT Protocol, Skyreader compris. Le site dont l'adresse est saisie reçoit une visite de RssBase. Le fournisseur présente à l'utilisateur les données RssBase qu'il l'autorise à écrire.
- **Emails / notifications** : non.
- **Données existantes** : oui. Les abonnements Skyreader déjà présents dans le compte apparaissent dès la première visite. Les comptes connectés avant cette feature ont accordé l'écriture sur tout leur compte (voir Questions ouvertes).
- **Comportement par défaut** : un utilisateur qui ne s'abonne à rien voit l'état vide et le formulaire. S'il a des abonnements Skyreader, il les voit.

## Questions ouvertes

- **Nom du format de RssBase** : un format publié porte un nom tiré d'une adresse que RssBase possède, et ce nom ne change plus sans devenir un nouveau format (règle 9). RssBase n'a pas encore d'adresse de production. Options : (a) choisir et réserver l'adresse définitive avant de publier le format, (b) publier sous une adresse provisoire, puis passer à un nouveau format quand l'adresse définitive existera. Recommandé : (a). Avec (b), chaque utilisateur aurait ses abonnements dans un format abandonné dès la première version. À trancher par l'utilisateur avant le plan.
- **Comptes connectés avant cette feature** : leur approbation couvre l'écriture sur tout le compte. Options : (a) la garder jusqu'à leur prochaine connexion, qui demandera l'écriture limitée, (b) leur demander d'approuver à nouveau dès leur prochaine visite. Recommandé : (a). L'approbation existante couvre déjà les données RssBase, et la redemander coûte un aller-retour chez le fournisseur sans bénéfice visible.
- **Fournisseur qui ne sait pas accorder une écriture limitée** : la règle 24 suppose que le fournisseur sait limiter l'écriture aux données RssBase. Options : (a) le compte est traité comme un compte sans droit d'écriture (règle 25), (b) RssBase demande à ce fournisseur l'écriture sur tout le compte. Recommandé : (a) au lancement. Le plan vérifie si l'un des quatre fournisseurs proposés est concerné, et la question revient seulement si c'est le cas.
- **Même flux, deux adresses** : deux adresses peuvent désigner le même flux (`http` et `https`, barre finale, redirection). La règle 12 en dépend. Options : (a) deux adresses qui mènent au même flux sont le même flux, (b) seule l'adresse exacte compte. Recommandé : (a), sinon le même flux s'affiche deux fois. Le plan décide ce qui identifie un flux.

---

## Annexe — Pistes pour le plan

- Schémas relevés le 2026-11-22, résolus par leur enregistrement DNS `_lexicon` puis lus dans le dépôt de leur auteur (`com.atproto.lexicon.schema`) :
  - `app.skyreader.feed.subscription` (`_lexicon.feed.skyreader.app` → `did:plc:ra4jsemddo2ii4pn5jaf6x4v`). Clé `tid`, seul `createdAt` est requis. Champs : `feedUrl`, `siteUrl`, `title`, `customTitle`, `category`, `tags`, `source` (`opml`, `manual`, `leaflet`), `sourceType` (absent = RSS ; `atproto.shares`, `atproto.documents`, `atproto.collection`), `subjectDid`, `externalRef`, `customIconUrl`, `collectionNsid`, `updatedAt`. Morgenblau l'utiliserait aussi, d'après l'auteur de Skyreader (non vérifié).
  - `at.glean.subscription` (`_lexicon.glean.at` → `did:plc:7fx3svzgnlvzighbczz3mmsd`). Clé `tid`, `feedUrl` requis, `title`, `category`, `createdAt`.
  - Proposition de format commun : <https://discourse.atmosphere.community/t/proposal-a-community-lexicon-for-rss-subscriptions/1143> (août 2026), non adoptée.
- Lecture Skyreader : records publics, `com.atproto.repo.listRecords` sur le PDS de l'utilisateur, sans permission. Ne garder que `sourceType` absent ou `rss`. Titre affiché : `customTitle`, puis `title`, puis `feedUrl`.
- Format de RssBase : un record d'abonnement et un record de masque. Reprendre les noms de champs de Skyreader (`feedUrl`, `siteUrl`, `title`, `createdAt`) facilite la lecture par d'autres lecteurs. Le masque désigne un flux, pas un record Skyreader : une clé sur l'adresse du flux survit à un réabonnement dans Skyreader. Une clé de record dérivée de l'adresse du flux empêcherait les doublons à l'écriture.
- Permission : `app/config/services.yaml:12` demande `atproto repo:*`. La remplacer par une permission `repo:` nommant chaque collection de RssBase, ou par un `permission-set` si la spécification et les fournisseurs le permettent (à vérifier dans la documentation à jour). `Scope::grantsWrite()` (`app/src/AtProto/OAuth/Scope.php`) accepte tout `repo:` : à adapter aux collections demandées.
- Écritures dans le PDS : premières requêtes authentifiées (`com.atproto.repo.createRecord`, `deleteRecord`) avec les jetons et la clé DPoP de la session ; le rafraîchissement passe par `PostponedRefresh`. Avec « Annuler », l'abonnement recréé garde son `createdAt` d'origine, et c'est `createdAt`, pas la clé, qui porte l'ordre de la liste.
- Ouverture des adresses saisies : réutiliser `NoPrivateNetworkHttpClient` et `BoundedHttpClient` (`app/src/AtProto/`) pour la règle 4 et les limites de taille et de temps. Découverte par `<link rel="alternate" type="application/rss+xml|application/atom+xml">`. Aucune bibliothèque de flux dans `app/composer.json`, et PHP a `dom` et `simplexml` (`infra/packages/php.nix`). Ajouter une bibliothèque est un ajout de dépendance, à faire valider.
- « Mes flux » : `app/templates/feeds/index.html.twig` et `app/src/Controller/FeedsController.php`, libellés `feeds.*` dans `app/translations/`. Deux lectures (RssBase et Skyreader) à chaque visite : les paralléliser. Mesurer le temps de réponse d'un PDS sur une liste de quelques centaines de flux, le volume qu'un import OPML amènera.
- « Annuler » sans rechargement de page : Stimulus ou Live Component. Les HIG d'Apple sont imposées par `AGENTS.md`.
- Réapprobation (règle 25) : relancer le parcours de connexion (`app/src/Security/AuthorizationFlow.php`) avec le compte courant, retour sur « Mes flux ».
- Tests sans réseau : `app/tests/Fake/FakeAtProtoNetwork.php` doit répondre à `listRecords`, `createRecord`, `deleteRecord` et servir de faux sites et de faux flux.
- Aucune table locale prévue : pas d'`EXPLAIN` à produire, sauf si le plan en ajoute une.
- Décision candidate à un ADR : format propre à RssBase avec lecture du format Skyreader, plutôt qu'un alignement complet sur Skyreader (hypothèse H2).
