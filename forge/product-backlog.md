# Product Backlog — RssBase

> Carte des capacités fonctionnelles et backlog priorisé dérivé de `forge/vision.md`.

_Document vivant — enrichi/édité au fil du cycle de vie, refondu lors d'un pivot. Date de dernière mise à jour : 2026-11-22._

## Changelog

Historique des évolutions structurantes (création, enrichissements, éditions ciblées, pivots). Lecture chronologique. Détails fins dans `git log`.

| Date | Nature | Éléments | Motif |
|------|--------|----------|-------|
| 2026-11-21 | Création | — | Backlog initial dérivé de la vision |
| 2026-11-21 | Éditer | Justifications vision, notes | North Star de la vision reformulée : abonnements et favoris dans le PDS via AT Protocol, état de lecture non obligatoire ; audience ouverte à tout compte AT Protocol |
| 2026-11-22 | Éditer | `connexion-atproto`, note `creation-compte-fournisseur` | La ligne couvre aussi le choix d'un fournisseur pour qui n'a pas de compte (C1.4, P2), livré avec la connexion ; liste des fournisseurs et fournisseur mis en avant tranchés au cadrage, sync post-livraison de la story 002-f-connexion-atproto |

## Domaines fonctionnels

| # | Domaine | Résumé en une ligne |
|---|---------|---------------------|
| D1 | Compte & connexion | Entrer dans l'espace réservé avec son compte AT Protocol, y rester, en sortir |
| D2 | Abonnements | Constituer et entretenir la liste des flux suivis |
| D3 | Lecture | Parcourir les articles des flux suivis et tenir son état de lecture |
| D4 | Favoris | Mettre des articles de côté et les retrouver |
| D5 | Social | Découvrir des flux par les abonnements publics des comptes suivis |

AT Protocol n'est pas un domaine : c'est une règle transverse qui s'applique aux cinq (voir « Règles métier transverses »).

## Capacités

### D1 — Compte & connexion

- **C1.1** — Un visiteur peut se connecter avec son compte AT Protocol en saisissant son handle, sans jamais confier son mot de passe à RssBase.
- **C1.2** — Un visiteur peut retrouver son compte parmi des suggestions (nom, avatar) pendant la saisie du handle.
- **C1.3** — Un visiteur peut confirmer que le compte trouvé est le sien avant de partir chez son fournisseur.
- **C1.4** — Un visiteur sans compte peut choisir un fournisseur d'hébergement, filtré par région, pour créer son compte.
- **C1.5** — Un utilisateur connecté peut accéder à l'espace réservé ; un visiteur non connecté est ramené à la connexion.
- **C1.6** — Un utilisateur peut rester connecté d'une visite à l'autre.
- **C1.7** — Un utilisateur peut voir quel compte est connecté (avatar, handle) et se déconnecter.

### D2 — Abonnements

- **C2.1** — Un utilisateur peut s'abonner à un flux par son adresse.
- **C2.2** — Un utilisateur peut importer ses abonnements depuis un fichier OPML, pour reprendre ceux d'un autre lecteur.
- **C2.3** — Un utilisateur peut consulter la liste de ses abonnements.
- **C2.4** — Un utilisateur peut se désabonner d'un flux.
- **C2.5** — Un utilisateur peut basculer un abonnement entre public et privé.

### D3 — Lecture

- **C3.1** — Le système peut récupérer les nouveaux articles des flux suivis.
- **C3.2** — Un utilisateur peut parcourir les articles par ordre chronologique, tous flux confondus ou flux par flux.
- **C3.3** — Un utilisateur peut lire un article.
- **C3.4** — Un utilisateur peut distinguer, flux par flux, les articles parus depuis sa dernière lecture.
- **C3.5** — Un utilisateur peut marquer un flux comme vu jusqu'à un article donné.

### D4 — Favoris

- **C4.1** — Un utilisateur peut mettre un article en favori.
- **C4.2** — Un utilisateur peut consulter ses favoris.
- **C4.3** — Un utilisateur peut retirer un favori.

### D5 — Social

- **C5.1** — Un utilisateur peut parcourir les abonnements publics d'un compte qu'il suit.
- **C5.2** — Un utilisateur peut s'abonner à un flux découvert chez un autre compte.

## Parcours utilisateurs principaux

### P1 — Connexion avec un compte existant

- **Acteur** : visiteur qui a déjà un compte AT Protocol.
- **Déclencheur** : arrivée sur RssBase sans être connecté.
- **Étapes** : C1.1 → C1.2 → C1.3 → C1.5.
- **État final** : le visiteur est connecté et se trouve dans l'espace réservé.
- **Fréquence** : à chaque nouvel appareil ou fin de session.

### P2 — Création de compte

- **Acteur** : visiteur sans compte AT Protocol.
- **Déclencheur** : le visiteur veut utiliser RssBase et n'a pas de compte.
- **Étapes** : C1.4 → création du compte chez le fournisseur choisi → C1.5.
- **État final** : le visiteur a un compte chez un fournisseur et est connecté à RssBase.
- **Fréquence** : une fois par nouvel utilisateur.

### P3 — Reprise depuis un autre lecteur

- **Acteur** : utilisateur qui quitte FreshRSS ou Miniflux.
- **Déclencheur** : première session après la connexion.
- **Étapes** : C2.2 → C2.3 → C3.1 → C3.2.
- **État final** : les abonnements sont repris et leurs articles s'affichent.
- **Fréquence** : une fois.

### P4 — Lecture quotidienne

- **Acteur** : utilisateur connecté.
- **Déclencheur** : ouverture de RssBase pour lire.
- **Étapes** : C1.6 → C3.4 → C3.2 → C3.3 → C3.5 → C4.1.
- **État final** : les curseurs de lecture ont avancé, les articles à garder sont en favoris.
- **Fréquence** : tous les jours.

### P5 — Entretien des abonnements

- **Acteur** : utilisateur connecté.
- **Déclencheur** : découverte d'un flux, ou flux devenu sans intérêt.
- **Étapes** : C2.1 → C2.5 → C2.4.
- **État final** : la liste d'abonnements est à jour, chaque flux avec la visibilité voulue.
- **Fréquence** : quelques fois par mois.

### P6 — Découverte par les comptes suivis

- **Acteur** : utilisateur connecté.
- **Déclencheur** : curiosité pour ce que lit un compte suivi.
- **Étapes** : C5.1 → C5.2.
- **État final** : de nouveaux abonnements issus d'un autre compte.
- **Fréquence** : occasionnelle (horizon 3 ans).

## Règles métier transverses

### Permissions et rôles

- Tout compte AT Protocol peut se connecter et utiliser RssBase. Aucun rôle : un seul type d'utilisateur.
- Sans connexion, seules la page d'accueil et la page de connexion sont visibles. Tout le reste est réservé aux utilisateurs connectés.
- Un utilisateur ne voit et ne modifie que ses propres données.
- RssBase ne reçoit jamais le mot de passe : l'utilisateur approuve RssBase chez son fournisseur, et c'est là qu'il révoque l'accès.

### Workflows et états

- Un abonnement naît public et se bascule en privé un par un.
- L'état de lecture est un curseur par flux (date du dernier article vu), jamais un marqueur par article.
- L'état de lecture et les favoris sont privés.

### Contraintes de gestion

- Toute donnée utilisateur (abonnements, état de lecture, favoris) vit dans le compte AT Protocol de l'utilisateur. Un stockage dans RssBase n'est toléré que temporaire, exportable, avec une migration prévue.
- Le lexicon est un contrat public, documenté et versionné : un changement cassant demande une nouvelle version.
- Toute donnée écrite reste relisible par un autre client sans passer par RssBase.
- Les articles s'affichent par ordre chronologique. Aucun tri que l'utilisateur ne peut pas expliquer.

### Exigences réglementaires

- Aucune identifiée à ce stade.

### Conventions transverses

- Deux sites, français et anglais.
- Un utilisateur est désigné par son handle.

## Backlog priorisé

> Les cases sont **dérivées** de `forge/story/*/metadata.json` (champ `backlog` + `delivery`) — ne les coche pas à la main, la prochaine passe les recalculerait. Une case est cochée quand la story est **livrée** (`delivery` présent) ; les états intermédiaires se lisent sur la ligne « Story ».

### MVP — Lancement initial · `0/3 livrées`

- [ ] `connexion-atproto` — Permettre à un visiteur de se connecter par son handle, ou de créer son compte chez un fournisseur proposé, de rester connecté et de se déconnecter, pour accéder à l'espace réservé sans confier son mot de passe à RssBase.
  - Story `002-f-connexion-atproto` · **clôture en cours**
  - C1.1, C1.4, C1.5, C1.6, C1.7 · P1, P2, P4 · dép. — · Vision : North Star (sans connexion AT Protocol, aucune écriture dans le PDS) / note « Login AT Protocol »
- [ ] `abonnement-flux` — Permettre à un utilisateur de s'abonner à un flux, de lister ses abonnements et de se désabonner, pour constituer sa liste de lecture dans son propre compte.
  - Pas encore cadrée
  - C2.1, C2.3, C2.4 · P5 · dép. `connexion-atproto` · Vision : North Star (abonnements dans le PDS) / problème central / principe 1
- [ ] `lecture-chronologique` — Permettre à un utilisateur de parcourir et de lire les articles de ses flux par ordre chronologique, pour lire au quotidien dans RssBase.
  - Pas encore cadrée
  - C3.1, C3.2, C3.3 · P3, P4 · dép. `abonnement-flux` · Vision : bénéfice utilisateur (lire au quotidien dans RssBase) / principe 4

### V2 — Court terme post-lancement · `0/6 livrées`

- [ ] `curseur-lecture` — Permettre à un utilisateur de distinguer les articles parus depuis sa dernière lecture et de marquer un flux comme vu, pour reprendre là où il s'est arrêté.
  - Pas encore cadrée
  - C3.4, C3.5 · P4 · dép. `lecture-chronologique` · Vision : problème central (état de lecture) / hypothèse 4
- [ ] `import-opml` — Permettre à un utilisateur d'importer un fichier OPML, pour reprendre d'un coup les abonnements de son ancien lecteur.
  - Pas encore cadrée
  - C2.2 · P3 · dép. `abonnement-flux` · Vision : métrique d'activation
- [ ] `connexion-guidee` — Permettre à un visiteur de retrouver son compte par suggestions et de le confirmer avant de partir chez son fournisseur, pour se connecter sans erreur de handle.
  - Pas encore cadrée
  - C1.2, C1.3 · P1 · dép. `connexion-atproto` · Vision : note « Login AT Protocol »
- [ ] `creation-compte-fournisseur` — Permettre à un visiteur sans compte de choisir un fournisseur d'hébergement et d'y créer son compte, pour entrer dans RssBase sans connaître AT Protocol.
  - Pas encore cadrée
  - C1.4 · P2 · dép. `connexion-atproto` · Vision : utilisateurs secondaires (personnes sans compte AT Protocol)
- [ ] `bascule-public-prive` — Permettre à un utilisateur de rendre un abonnement privé ou public, pour choisir ce que les autres voient de ses lectures.
  - Pas encore cadrée
  - C2.5 · P5 · dép. `abonnement-flux` · Vision : principe 3
- [ ] `favoris` — Permettre à un utilisateur de mettre un article de côté, de retrouver ses favoris et d'en retirer, pour garder ce que le curseur de lecture laisse passer.
  - Pas encore cadrée
  - C4.1, C4.2, C4.3 · P4 · dép. `lecture-chronologique` · Vision : North Star (favoris dans le PDS) / problème central / hypothèse 4

### V3 — Long terme · `0/2 livrées`

- [ ] `abonnements-comptes-suivis` — Permettre à un utilisateur de parcourir les abonnements publics des comptes qu'il suit, pour découvrir des flux.
  - Pas encore cadrée
  - C5.1 · P6 · dép. `bascule-public-prive` · Vision : horizon 3 ans / hypothèse 3
- [ ] `abonnement-depuis-compte` — Permettre à un utilisateur de s'abonner à un flux vu chez un autre compte, pour le suivre sans recopier son adresse.
  - Pas encore cadrée
  - C5.2 · P6 · dép. `abonnements-comptes-suivis` · Vision : horizon 3 ans / hypothèse 3

## Couverture

_(dérivé — recalculé à chaque passe, ne pas maintenir à la main)_

### Capacités par horizon

- **MVP** — livrées : — · planifiées : C1.1, C1.4, C1.5, C1.6, C1.7, C2.1, C2.3, C2.4, C3.1, C3.2, C3.3
- **V2** — livrées : — · planifiées : C1.2, C1.3, C1.4, C2.2, C2.5, C3.4, C3.5, C4.1, C4.2, C4.3
- **V3** — livrées : — · planifiées : C5.1, C5.2

### Capacités non couvertes (à challenger)

- Aucune.

### Parcours supportés

- **P1** : partiellement supporté en MVP (C1.2 et C1.3 reportées en V2 : connexion par le seul champ handle).
- **P2** : supporté en MVP (C1.4 portée par `connexion-atproto`).
- **P3** : partiellement supporté en MVP (C2.2 reportée en V2 : les flux s'ajoutent un par un).
- **P4** : partiellement supporté en MVP (C3.4, C3.5 et C4.1 reportées en V2 : lecture sans état de lecture ni favoris).
- **P5** : partiellement supporté en MVP (C2.5 reportée en V2 : tous les abonnements sont publics).
- **P6** : non supporté avant la V3.

## Notes pour `/feature-pitch`

- **North Star atteinte par étapes** : `abonnement-flux` (MVP) la couvre pour les abonnements, `favoris` (V2) pour les favoris. L'état de lecture n'en fait pas partie : `curseur-lecture` peut le garder hors du PDS. Si une de ces features passe par un stockage temporaire (plan B de H1), la migration vers les spaces devient une ligne à ajouter par un Enrichir. La preuve d'interop par un second client reste à l'horizon 1 an de la vision, sans ligne de backlog.
- **État de lecture hors MVP** : le MVP n'écrit que des abonnements. Le cœur du problème de la vision (état de lecture portable) n'arrive qu'avec `curseur-lecture`, première ligne de la V2.
- **Références d'écrans pour D1** (captures fournies le 2026-11-21, parcours d'inscription de Colibri et modale d'une autre application) : saisie du handle avec suggestions de comptes ; écran « Est-ce vous ? » avec avatar, nom et handle ; choix d'un fournisseur avec filtre Tous / UE / US, étiquettes « recommandé » et « le plus populaire », liens « tous les fournisseurs » et « héberger le sien » ; variante compacte à un seul champ. L'écran d'annonce du départ chez le fournisseur (« Taking you to... ») a été écarté.
- **Hypothèses de la vision à lever au cadrage** : H2 (lexicon communautaire existant pour les flux) avant `abonnement-flux` ; H1 (spaces disponibles) avant `curseur-lecture`, `favoris` et `bascule-public-prive`, qui écrivent des données privées.
- **`creation-compte-fournisseur`** : qui choisit la liste des fournisseurs et celui mis en avant reste à décider. → tranché dans `002-f-connexion-atproto` : liste éditoriale de RssBase, Eurosky mis en avant ; la capacité est livrée avec `connexion-atproto`.
