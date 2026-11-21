# Se connecter à RssBase avec son compte AT Protocol

> **But** : figer l'intention métier de la connexion : qui entre dans RssBase, comment, pour combien de temps, et ce que voit celui qui n'a pas encore de compte.
> **Registre** : fonctionnel
> **Story** : `forge/story/002-f-connexion-atproto/`
> **Amont** : aucun

Un visiteur entre dans RssBase avec le compte AT Protocol qu'il a déjà, ou en crée un chez un fournisseur proposé, sans jamais confier son mot de passe à RssBase. Il reste connecté d'une visite à l'autre et peut se déconnecter.

## Contexte

RssBase n'a aujourd'hui qu'une page d'accueil. Son bouton « Commencer » renvoie vers le code source, faute de connexion : personne ne peut entrer, et aucune des fonctions prévues (abonnements, lecture, favoris) n'a d'utilisateur à qui rattacher quoi que ce soit.

La connexion est la première marche du lancement. Les deux autres features du premier lot, s'abonner à un flux et lire ses articles, en dépendent directement. Sans elle, RssBase ne peut rien écrire dans le compte de l'utilisateur, donc ne peut pas tenir sa promesse : des données de lecture qui appartiennent à celui qui lit.

RssBase n'a pas de compte maison. L'identité vient du fournisseur AT Protocol de l'utilisateur (Bluesky ou un autre), qui garde le mot de passe et auprès de qui l'accès se révoque. Un visiteur sans compte doit donc en créer un ailleurs avant d'entrer : s'il ne trouve pas où, il repart.

## Alignement vision

- **Problème adressé** : indirectement. La connexion ne rend aucune donnée portable par elle-même, mais elle désigne le compte dans lequel abonnements et favoris seront écrits.
- **Audience servie** : l'utilisateur principal (titulaire d'un compte AT Protocol, Bluesky ou autre) par la saisie du handle ; les utilisateurs secondaires (sans compte) par le choix d'un fournisseur où en créer un.
- **Principes respectés** : le principe 1 (« la donnée appartient au PDS ») est mis sous tension par la trace de connexion (handle et date de la dernière connexion) que RssBase conserve chez lui. C'est assumé : il s'agit d'une trace d'exploitation qui alimente la métrique d'acquisition, pas d'une donnée de lecture, et le handle est public. Aucune donnée de lecture n'est créée par cette feature.
- **Hypothèse testée** : aucune directement. La demande d'un droit d'écriture limité aux données RssBase bute sur l'hypothèse 2 (lexicon existant ou à créer), encore à lever.
- **Impact North Star** : indirect. Prérequis à toute écriture dans le PDS. Cette feature alimente aussi la métrique d'acquisition (comptes connectés au moins une fois).

## Utilisateurs concernés

- **Visiteur titulaire d'un compte AT Protocol** (non connecté) : peut entrer en saisissant son handle et en approuvant RssBase chez son fournisseur.
- **Visiteur sans compte AT Protocol** : trouve sur RssBase une liste de fournisseurs où créer son compte, puis revient connecté.
- **Utilisateur connecté** : accède à l'espace réservé, y voit quel compte est connecté et peut se déconnecter.
- **Exploitant de RssBase** : peut savoir quels comptes se sont connectés et quand pour la dernière fois, sans écran dédié.

## User Stories

- En tant que **visiteur titulaire d'un compte**, je veux me connecter en saisissant mon handle afin d'entrer dans RssBase sans créer un compte de plus ni donner mon mot de passe.
- En tant que **visiteur titulaire d'un compte**, je veux qu'une saisie approximative de mon handle (un @ devant, des majuscules, un nom Bluesky sans son suffixe) soit comprise afin de ne pas échouer pour un détail de frappe.
- En tant que **visiteur titulaire d'un compte**, je veux comprendre pourquoi une tentative a échoué et retrouver ce que j'avais saisi afin de corriger sans tout retaper.
- En tant que **visiteur sans compte**, je veux choisir un fournisseur parmi quelques-uns présentés clairement, avec le lieu où mes données seront hébergées, afin de créer mon compte et d'entrer dans RssBase dans la foulée.
- En tant que **visiteur non connecté**, je veux être ramené à la connexion quand j'ouvre une page réservée, puis arriver sur cette page une fois connecté, afin de ne pas perdre ma destination.
- En tant qu'**utilisateur connecté**, je veux rester connecté d'une visite à l'autre et arriver directement dans l'espace réservé quand j'ouvre RssBase afin de lire sans étape inutile.
- En tant qu'**utilisateur connecté**, je veux voir l'avatar et le handle du compte connecté et pouvoir me déconnecter afin de savoir sous quelle identité j'agis et de quitter un appareil qui n'est pas le mien.
- En tant qu'**utilisateur connecté**, je veux changer de langue sans être déconnecté afin de ne pas repasser par mon fournisseur pour une préférence d'affichage.
- En tant qu'**exploitant**, je veux connaître les comptes qui se sont connectés et la date de leur dernière connexion afin de mesurer l'acquisition.

## Règles métier

**Entrée**

1. Tout compte AT Protocol peut se connecter. Aucune liste d'autorisation, aucun rôle.
2. RssBase ne demande ni ne reçoit jamais de mot de passe. L'utilisateur approuve RssBase chez son fournisseur.
3. La page de connexion propose un seul champ, le handle. Pas de suggestions de comptes pendant la saisie, pas d'écran de confirmation du compte.
4. La saisie tolère un @ initial, les espaces autour et les majuscules.
5. Un nom saisi sans domaine (par exemple `romain`) est complété par `.bsky.social`.
6. Le bouton « Commencer » de l'accueil mène à la page de connexion.

**Autorisation demandée au fournisseur**

7. RssBase demande à l'utilisateur de confirmer son identité et de l'autoriser à écrire dans son compte. Dans un premier temps l'écriture porte sur tout le compte ; elle sera limitée aux données RssBase quand leur lexicon existera.
8. Si le fournisseur n'accorde pas l'écriture, RssBase n'obtient que l'identité. L'utilisateur entre quand même.
9. RssBase sait, pour chaque compte connecté, si l'écriture lui a été accordée. Cette feature n'affiche rien à ce sujet : le message appartient à la première feature qui écrit.

**Échecs**

10. Handle introuvable, fournisseur injoignable ou approbation refusée : le visiteur revient à la page de connexion, son handle est conservé dans le champ, et un message dit laquelle de ces causes s'applique.

**Espace réservé**

11. Sans connexion, seules l'accueil, la page de connexion et le choix d'un fournisseur sont visibles. Toute autre page ramène à la connexion.
12. Après connexion, l'utilisateur arrive sur la page réservée qu'il avait demandée, ou à défaut sur « Mes flux ».
13. « Mes flux » est une page vide à ce stade : elle dit qu'aucun flux n'est suivi, montre l'avatar et le handle du compte connecté, et propose la déconnexion.
14. Un compte sans avatar est représenté par une image neutre.
15. Un utilisateur connecté qui ouvre l'accueil, la page de connexion ou le choix d'un fournisseur est redirigé vers l'espace réservé.

**Durée et portée de la session**

16. L'utilisateur reste connecté tant que son fournisseur l'autorise. Pas de case « rester connecté », pas d'expiration propre à RssBase.
17. Quand le fournisseur met fin à l'accès (révocation par l'utilisateur, expiration), l'utilisateur est ramené à la connexion à sa visite suivante, avec un message qui l'explique.
18. Une connexion vaut pour les deux sites, français et anglais. L'utilisateur revient de son fournisseur sur le site de la langue d'où il est parti.
19. Un seul compte à la fois par navigateur. Pour en changer, il faut se déconnecter.
20. Plusieurs appareils peuvent être connectés au même compte, indépendamment les uns des autres.

**Déconnexion**

21. « Se déconnecter » met fin à la session de l'appareil et ramène à l'accueil. Les autres appareils restent connectés.
22. La déconnexion ne signale rien au fournisseur : l'approbation donnée à RssBase y reste valable, et c'est chez lui qu'elle se révoque.

**Visiteur sans compte**

23. La page de connexion mène à un écran « choisir un fournisseur », qui explique en une phrase que le fournisseur héberge le compte et ses données, et qu'on peut en changer plus tard sans rien perdre.
24. Quatre fournisseurs y sont proposés, chacun avec son nom, une phrase de description et son lieu d'hébergement : Eurosky (UE, étiqueté « recommandé »), Bluesky (US, étiqueté « le plus populaire »), Blacksky (US), NPMX (UE).
25. Un filtre Tous / UE / US restreint la liste par lieu d'hébergement.
26. Deux liens sortants complètent la liste : « tous les fournisseurs » et « héberger le sien ».
27. Un lien « Déjà un compte ? Se connecter » ramène au champ handle.
28. Choisir un fournisseur envoie directement chez lui, sans écran intermédiaire. Le visiteur y crée son compte, approuve RssBase et revient connecté dans l'espace réservé, sans saisir son handle.

**Trace de connexion**

29. RssBase retient, pour chaque compte qui se connecte, son handle et la date de sa dernière connexion. Chaque nouvelle connexion met la date à jour.
30. Un compte reste le même quand son handle change : la trace suit le compte et prend le nouveau handle, sans créer un second compte.
31. La trace survit à la déconnexion : elle sert à compter les comptes connectés au moins une fois.
32. Seul l'exploitant peut la consulter. Aucun écran ne l'expose.

## Critères d'acceptation

- [ ] Depuis l'accueil, « Commencer » ouvre la page de connexion, qui ne contient aucun champ de mot de passe.
- [ ] Un visiteur qui saisit un handle valide part chez son fournisseur, approuve RssBase, et revient sur « Mes flux », connecté, sur le site de la langue d'où il est parti.
- [ ] `@Nom.Bsky.Social` entouré d'espaces connecte le même compte que `nom.bsky.social`.
- [ ] Un nom saisi sans domaine connecte le compte du même nom en `.bsky.social`.
- [ ] Un handle introuvable ramène à la page de connexion avec le handle saisi dans le champ et un message qui dit que le compte est introuvable.
- [ ] Un refus d'approbation chez le fournisseur ramène à la page de connexion avec un message qui le dit, distinct de celui du handle introuvable et de celui du fournisseur injoignable.
- [ ] Un visiteur non connecté qui ouvre « Mes flux » est ramené à la page de connexion, puis arrive sur « Mes flux » une fois connecté.
- [ ] « Mes flux » affiche un état vide, l'avatar et le handle du compte connecté, et un bouton de déconnexion.
- [ ] Un compte sans avatar s'affiche avec une image neutre.
- [ ] Un utilisateur connecté qui ouvre l'accueil ou la page de connexion se retrouve sur « Mes flux ».
- [ ] Après fermeture et réouverture du navigateur, l'utilisateur est toujours connecté.
- [ ] Un utilisateur connecté sur le site français qui ouvre le site anglais y voit « Mes flux » sans repasser par la connexion, et inversement.
- [ ] Après « Se déconnecter », l'utilisateur est sur l'accueil et « Mes flux » le ramène à la connexion ; un second appareil connecté au même compte le reste.
- [ ] Après révocation de l'accès chez le fournisseur, la visite suivante ramène à la page de connexion avec un message qui explique que la session a pris fin.
- [ ] Un compte dont le fournisseur n'accorde pas l'écriture se connecte quand même et voit le même « Mes flux ».
- [ ] La page de connexion mène à l'écran « choisir un fournisseur », qui liste Eurosky (« recommandé »), Bluesky (« le plus populaire »), Blacksky et NPMX avec leur lieu d'hébergement.
- [ ] Le filtre UE ne laisse que Eurosky et NPMX, le filtre US que Bluesky et Blacksky, le filtre Tous les quatre.
- [ ] L'écran des fournisseurs propose les liens « tous les fournisseurs », « héberger le sien » et « Déjà un compte ? Se connecter ».
- [ ] Choisir un fournisseur ouvre directement son site, sans écran intermédiaire ; le compte créé et RssBase approuvé, le visiteur arrive connecté sur « Mes flux ».
- [ ] Après une connexion, l'exploitant retrouve le handle du compte et la date du jour comme dernière connexion ; une reconnexion ultérieure met la date à jour sans créer de doublon.
- [ ] Tous les écrans et messages de cette feature existent en français et en anglais.

## Hors scope

- **Suggestions de comptes pendant la saisie et écran « Est-ce vous ? »** : restent dans `connexion-guidee`, après le lancement.
- **Écran d'annonce du départ chez le fournisseur** (« Taking you to... ») : écarté au backlog.
- **Plusieurs comptes connectés en même temps, bascule d'un compte à l'autre** : un seul compte par navigateur.
- **Révocation de l'accès chez le fournisseur à la déconnexion, déconnexion de tous les appareils** : la déconnexion ne concerne que l'appareil.
- **Message sur l'absence de droit d'écriture** : porté par `abonnement-flux`, première feature qui écrit.
- **Toute écriture dans le compte de l'utilisateur et la définition du lexicon** : cette feature demande le droit, elle ne s'en sert pas.
- **Page « Mon compte », réglages, suppression de sa trace par l'utilisateur** : rien à régler à ce stade.
- **Écran de statistiques pour l'exploitant** : la trace se consulte sans interface.
- **Compte maison, connexion par e-mail ou par un autre réseau** : hors cible de la vision.
- **Choix de la liste des fournisseurs par l'utilisateur ou mise à jour automatique de cette liste** : la liste est un choix éditorial de RssBase, fixé ici.

## Impacts transverses

- **Traduction / langues** : oui. Page de connexion, écran des fournisseurs (descriptions comprises), « Mes flux », messages d'échec et de fin de session, en français et en anglais.
- **Droits d'accès** : premier espace réservé du produit. Deux états seulement, visiteur et utilisateur connecté ; aucun niveau d'autorisation supplémentaire.
- **Cloisonnement des données** : chaque utilisateur ne voit que son propre compte. La trace de connexion n'est visible que de l'exploitant.
- **Apparence / déclinaisons** : non. Mêmes écrans sur les deux sites, seule la langue change.
- **Exposition à des tiers** : le fournisseur de l'utilisateur présente RssBase par son nom sur son écran d'approbation et sait que l'utilisateur l'a approuvé. La trace de connexion n'est exposée à personne.
- **Emails / notifications** : non.
- **Données existantes** : non, aucune donnée à reprendre.
- **Comportement par défaut** : un visiteur qui ne se connecte pas voit l'accueil comme aujourd'hui, à ceci près que « Commencer » mène à la connexion et non plus au code source.

## Questions ouvertes

- **Périmètre exact de l'écriture demandée** : le lexicon des données RssBase n'est pas fixé (hypothèse 2 de la vision), or l'autorisation doit nommer ce qu'elle couvre. Options : (a) réserver dès maintenant l'espace de noms des données RssBase et demander l'écriture sur lui seul, (b) ne demander que l'identité jusqu'à `abonnement-flux`. Recommandé : (a), c'est le choix fait au cadrage ; si l'hypothèse 2 conduit ensuite à réutiliser un lexicon communautaire, les comptes déjà connectés devront approuver à nouveau. → tranché : ni l'un ni l'autre. Une permission d'écriture doit nommer la collection exacte, sans joker par espace de noms ; RssBase demande l'écriture sur tout le compte dans un premier temps (règle 7 corrigée), à resserrer dans `abonnement-flux`.
- **Fournisseur qui ne permet pas de créer un compte pendant l'approbation** : la règle 28 suppose que chacun des quatre fournisseurs sait enchaîner création de compte et approbation de RssBase. Options si l'un ne le sait pas : (a) ouvrir sa page d'inscription, puis le visiteur revient saisir son handle, (b) le retirer de la liste. Recommandé : (a), la liste reste complète au prix d'un aller-retour pour ce fournisseur. → tranché : sans objet, les quatre fournisseurs annoncent la création de compte pendant l'approbation.
- **Destination des liens « tous les fournisseurs » et « héberger le sien »** : Options : (a) les mêmes destinations que l'écran de référence, à relever au plan, (b) des pages choisies par RssBase. Recommandé : (a), aucune raison de s'en écarter. → tranché : (a), `https://atmosphereaccount.com/hosts` et `https://atproto.com/guides/self-hosting`.
- **Durée réelle de la session** : « tant que le fournisseur l'autorise » dépend de limites fixées par le protocole et par chaque fournisseur. À mesurer au plan : si la limite se compte en jours, la promesse « rester connecté d'une visite à l'autre » est à reformuler. Options : (a) accepter la limite telle quelle, (b) chercher le mode de connexion qui donne la durée la plus longue. Recommandé : (b), le parcours de lecture quotidienne ne doit pas repasser par le fournisseur chaque semaine. → tranché : (b). En production la session dure jusqu'à 2 ans, et s'éteint après 3 mois sans visite ; en développement local elle est limitée à 2 semaines.

---

## Annexe — Pistes pour le plan

- Écran de référence relevé le 2026-11-21 : `https://colibri.social/app/login#provider`. Quatre cartes de fournisseurs, onglets Tous / UE / US, boutons « Browse all providers » et « Run your own », lien « Already have an account? Sign in ». L'étape 2 (`#continue`, « Taking you to bsky.social ») est celle que `forge/product-backlog.md:221` écarte.
- Cette story absorbe C1.4 et le parcours P2, que le backlog range en V2 sous `creation-compte-fournisseur`. La ligne V2 est à retirer ou à réduire par `/forge:product-backlog` ; le `metadata.json` de la story ne porte que `connexion-atproto`.
- Aucune brique de sécurité ni client AT Protocol dans `app/composer.json`. `forge/stack.md` note le client AT Protocol comme « à trancher avec la première story fonctionnelle » : c'est ici, et le choix est candidat à un ADR.
- OAuth AT Protocol : périmètre `atproto` pour l'identité, permissions fines par collection pour l'écriture limitée, accès générique `transition:generic` écarté par le cadrage. L'état de la spécification et sa prise en charge par chaque fournisseur sont à vérifier dans la documentation à jour, pas de mémoire. La règle 8 demande de détecter l'absence de permissions fines avant de partir chez le fournisseur, ou de la constater au retour.
- Création de compte pendant l'approbation (règle 28) : à vérifier fournisseur par fournisseur, y compris le paramètre qui ouvre directement l'inscription.
- Durée de session : elle dépend du type de client OAuth déclaré (public ou confidentiel) et de la durée de vie des jetons de rafraîchissement. À chiffrer avant de choisir.
- Trace de connexion : première table et première migration du projet (`app/src/Migrations/` est vide). Clé sur l'identifiant stable du compte (DID), handle et date de dernière connexion en colonnes ; la règle 30 interdit une clé sur le handle. `AGENTS.md` exige un `EXPLAIN` sur les requêtes ajoutées.
- Session commune aux deux sites : les langues vivent sur `fr.{domain}` et `{domain}` (`app/config/routes.yaml`, `app/src/EventListener/LocaleDomainListener.php`). Le cookie de session doit couvrir les deux hôtes et l'adresse de retour du fournisseur doit ramener sur l'hôte de départ.
- Développement local : `castor start` prend le port 8000 ou le suivant libre, `castor docker:up` le 8080 ou le suivant. Les règles OAuth pour un client en boucle locale et un port variable sont à vérifier.
- Tests : la suite `e2e` passe par le noyau de test, sans FrankenPHP. Un fournisseur AT Protocol de test est nécessaire pour dérouler la connexion sans réseau.
- Mode worker : aucun état d'utilisateur ne doit survivre d'une requête à l'autre dans le processus.
- Accueil : le bouton est à `app/templates/home/index.html.twig:147`. L'en-tête (menu des langues compris) est écrit dans ce gabarit ; « Mes flux » et la page de connexion en auront besoin aussi.
- Avatar : il vient du profil public du compte. Un compte AT Protocol sans profil Bluesky n'en a pas, d'où la règle 14.
- `AGENTS.md` impose les Human Interface Guidelines d'Apple pour l'interface : alignement, espacement, accessibilité, tailles d'écran.
