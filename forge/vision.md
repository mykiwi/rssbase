# Vision — RssBase

> Pitch en une phrase : RssBase est un lecteur RSS ouvert à tout compte AT Protocol, qui résout la captivité des données de lecture en stockant abonnements, état de lecture et favoris dans le PDS AT Protocol de l'utilisateur, via un lexicon ouvert.

_Document vivant — enrichi au fil du cycle de vie, refondu lors d'un pivot stratégique. Date de dernière mise à jour : 2026-11-21._

## Changelog

| Date | Nature | Axe | Motif |
|------|--------|-----|-------|
| 2026-11-20 | Création | — | Vision initiale |
| 2026-11-20 | Éditer | Hypothèses | H4 : état de lecture = date du dernier article vu par flux, pas un marqueur par article |
| 2026-11-21 | Éditer | Métriques | North Star réduite à une condition : abonnements et favoris sont dans le PDS via AT Protocol, l'état de lecture n'y est pas obligatoire. Les conditions d'usage (30 jours) et de second client sont retirées |
| 2026-11-21 | Éditer | Audience | Plus de limite à un utilisateur en V1 : tout titulaire d'un compte AT Protocol peut utiliser RssBase. Seuils et horizons réalignés |

## Le problème

Les abonnements, l'état lu/non-lu et les favoris d'un lecteur RSS vivent dans la base du lecteur. Changer de lecteur, ou le voir fermer, fait perdre l'historique ; seul l'OPML des abonnements survit, et encore quand l'export existe.

**Comment c'est résolu aujourd'hui** : FreshRSS ou Miniflux en self-hosted, ou un service hébergé (Feedly, Inoreader).
**Pourquoi c'est insuffisant** : même en self-hosted, la donnée reste prisonnière du schéma d'une application. Aucun autre client ne peut relire l'état de lecture sans passer par l'API propriétaire de ce lecteur.
**Ampleur** : lecture quotidienne ; le coût se paie d'un coup, à chaque changement de lecteur.

## L'audience

### Utilisateur principal

- **Persona** : toute personne qui a un compte AT Protocol (Bluesky ou un autre fournisseur) et lit des flux RSS tous les jours, aujourd'hui dans FreshRSS, Miniflux ou un service hébergé. L'auteur du projet est le premier d'entre eux.
- **Volume cible** : non défini. Aucune limite au nombre d'utilisateurs.
- **Ce qui la bloque aujourd'hui** : ses données de lecture ne sont pas portables.

### Utilisateurs secondaires

Les personnes sans compte AT Protocol : elles en créent un chez un fournisseur avant d'entrer.

### Hors cible explicite

Qui veut se connecter autrement que par AT Protocol : RssBase n'a pas de compte maison.

## La proposition de valeur

### Bénéfice utilisateur

Changer de lecteur sans rien perdre : abonnements, état de lecture et favoris restent dans le PDS de l'utilisateur, relisibles par n'importe quel client qui implémente le lexicon.

### Pourquoi nous, plutôt qu'eux

FreshRSS, Miniflux et Feedly stockent la donnée chez eux. RssBase la stocke chez l'utilisateur.

### Unfair advantage

Un lexicon AT Protocol ouvert et documenté pour les données d'un lecteur RSS. Un concurrent ne peut pas copier cette portabilité sans changer son modèle de stockage.

## Métriques de succès

### North Star

Les abonnements et les favoris de l'utilisateur sont dans son PDS, écrits par RssBase via AT Protocol. Aucun ne vit seulement dans la base de RssBase. L'état de lecture peut y être aussi, sans que ce soit obligatoire.

### Métriques secondaires

- **Acquisition** : nombre de comptes AT Protocol qui se sont connectés au moins une fois.
- **Activation** : import complet de l'OPML existant et première session de lecture dans RssBase.
- **Rétention** : nombre de jours consécutifs de lecture dans RssBase.
- **Monétisation** : sans objet.

### Seuils

- À 6 mois : North Star atteinte pour les abonnements et les favoris.
- À 1 an : preuve d'interop par un second client.
- À 3 ans : non défini.

### Signal d'arrêt

L'ancien lecteur est rouvert pour lire au quotidien.

## Principes produit

1. **La donnée appartient au PDS** — une feature qui stocke de la donnée utilisateur ailleurs que dans le PDS ou un space doit avoir un export et une migration prévue, sinon elle ne passe pas.
2. **Le lexicon est un contrat public** — il est documenté et versionné. Un changement cassant demande une nouvelle version, jamais une modification silencieuse.
3. **Public ou privé se choisit donnée par donnée** — les abonnements sont publics par défaut, l'utilisateur peut basculer chacun en privé ; l'état de lecture et les favoris sont privés, dans les spaces AT Protocol.
4. **Chronologique, pas de tri caché** — aucun ordre d'affichage que l'utilisateur ne peut pas expliquer.

## Anti-objectifs

- **Pas d'algorithme de tri** — pas de feed « recommandé pour toi » opaque ; l'ordre est chronologique.
- **Pas de donnée captive** — aucune donnée utilisateur ne peut rester bloquée dans la base de l'app. La base locale n'est tolérée que comme stockage temporaire, exportable, avec une migration vers le PDS ou les spaces prévue.

## Hypothèses critiques

| # | Hypothèse | Comment l'invalider | Statut |
|---|-----------|---------------------|--------|
| 1 | Les spaces AT Protocol (données privées) sont disponibles et utilisables depuis PHP dans le délai de la V1 | Vérifier l'état de la spec et des implémentations PDS/SDK avant le premier plan technique. Plan B acté : stockage local temporaire, puis migration | À tester |
| 2 | Un lexicon communautaire pour les flux RSS existe et peut être réutilisé | Rechercher les lexicons existants pendant le cadrage technique ; s'aligner dessus plutôt qu'en inventer un | À tester |
| 3 | Le « social » du second temps apporte une valeur réelle. Contenu non défini ; recommandation : parcourir et suivre les abonnements publics des comptes suivis | Définir le contenu après la V1, puis le prototyper | À tester |
| 4 | L'état de lecture se réduit à un curseur par flux : la date du dernier article vu, au lieu d'un marqueur par article. Une écriture par flux lu garde le volume d'écritures dans le PDS ou le space négligeable. Contrepartie : impossible de garder un article plus ancien en non-lu, les favoris couvrent ce besoin | Pendant la V1, noter chaque fois qu'un article plus ancien que le curseur aurait dû rester non-lu ; si ça arrive régulièrement, revoir le modèle | À tester |

## Risques externes

- **Calendrier des spaces AT Protocol** : la V1 dépend d'une brique du protocole qui n'est pas garantie. Mitigation : stockage local temporaire et migration (principe 1).
- **Évolution du protocole AT** : changements de spec OAuth ou de format des records. Mitigation : lexicon versionné (principe 2).

## Horizons

### 3-6 mois

V1 ouverte à tout compte AT Protocol, qui remplace FreshRSS ou Miniflux au quotidien. Lexicon publié et abonnements publics dans le PDS.

### 1 an

Preuve d'interop par un second client.

### 3 ans

Couche sociale, au contenu à définir (hypothèse 3).

## Notes pour les features à venir

- Login AT Protocol (OAuth).
- Import OPML depuis FreshRSS ou Miniflux.
- Lexicon des abonnements, de l'état de lecture (curseur « dernier article vu » par flux) et des favoris.
- Bascule public/privé par abonnement.
- Migration du stockage local temporaire vers les spaces.
- Script ou client de preuve d'interop.
- Social : parcourir et suivre les abonnements publics des comptes suivis (à confirmer).
