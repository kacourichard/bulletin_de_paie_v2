# Bulletin de paie v2

Refonte de l'application de paie, utilisable dans n'importe quel pays.

- **Symfony 7.4 LTS** · **PHP 8.3+** · Doctrine ORM 3 · MySQL (InnoDB)
- Interface : Bootstrap 5.3 + Bootstrap Icons via AssetMapper (pas de Node.js nécessaire)
- Listes volumineuses en **DataTables 3** (recherche, tri, pagination, en français) : ajouter
  `data-controller="datatable"` sur le `<table>` (`assets/controllers/datatable_controller.js`) ;
  `data-order` sur les cellules de dates / montants, `data-dt-tri="non"` sur les en-têtes d'actions ;
  les lignes de détail `tr.paie-detail` deviennent des lignes dépliables.

## Installation (WAMP)

```bash
composer install
# .env.local : DATABASE_URL="mysql://root:@127.0.0.1:3306/bulletin_paie_v2?serverVersion=9.1.0&charset=utf8mb4"
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load      # données de démonstration (efface la base !)
```

URL : http://localhost/bulletin-de-paie%20EPSA/bulletin-de-paie-v2/public/

## Comptes de démonstration

Mot de passe commun : `Demo@2026!`

| E-mail | Profil |
|---|---|
| superadmin@bulletin-paie.local | Super-administrateur de la plateforme |
| admin@epsa-demo.local | Administratrice de la succursale « Groupe EPSA » (Côte d'Ivoire + Sénégal) |
| rh@ / comptable@ / tresorier@epsa-demo.local | RH, Comptable, Trésorier de EPSA Côte d'Ivoire |
| rh.sn@ / comptable.sn@ / tresorier.sn@epsa-demo.local | RH, Comptable, Trésorier de EPSA Sénégal |
| admin@atlas-demo.local | Administrateur de « Atlas Group » (Maroc + France), cloisonnée du Groupe EPSA |

Chaque société de démo a son personnel, payé dans la devise de son pays :

| Société | Devise | En poste | Particularités |
|---|---|---|---|
| EPSA Côte d'Ivoire | XOF | 5 (+2 sortis) | revalorisations, avantages arrêtés, CDD, stagiaire, une démission |
| EPSA Sénégal | XOF | 4 | dont Moussa Diallo, transféré depuis la Côte d'Ivoire |
| Atlas Maroc | MAD | 4 | |
| Atlas France | EUR | 4 | montants avec centimes, apprentie |

## Processus de paie (Côte d'Ivoire)

De la configuration au paiement des salaires. Les noms entre « » sont ceux des menus.

### 1. Configurer le pays — super-admin, une seule fois

Menu « Pays », ligne Côte d'Ivoire (tout est déjà chargé avec les règles de la v1, à vérifier ou ajuster) :

1. **Paramètres de paie** : ancienneté, jours de base (30), barème ITS et réductions par parts, grille des parts,
   exonération forfaitaire de 10 %, arrondi du net au 5 ou à la dizaine.
2. **Cotisations** : CNPS retraite, CMU, prestations familiales, accident du travail, impôt employeur
   (1,2 % Ivoiriens, 12 % non-Ivoiriens), FDFP.
3. **Secteurs** : les 29 secteurs d'activité de la v1 et leur taux de risque accident du travail (2 à 5 %).
4. **Rubriques** : primes, indemnités et retenues, avec leur traitement (imposable, exonérée 10 %, non imposable).
5. **Indemnité de transport** : barème par ville de résidence (table v1 `prime_transport`) — Abidjan 30 000,
   Bouaké 24 000, autres villes 20 000, hors zone 0, sur la rubrique 645 (non imposable).
   Chargement : `php bin/console app:transport:charger CI`.

### 2. Créer la société — super-admin ou administrateur de succursale

Menu « Succursales » (ou « Mes succursales ») › la succursale › **Nouvelle société** :
pays, raison sociale, n° CNPS, compte contribuable, logo et **secteur d'activité** (il fixe le taux accident du travail).
**Paie appliquée (législation)** : obligatoire à la création, c'est le pays dont la paie s'applique — pas forcément
celui de la société. Exemple : EPSA Côte d'Ivoire et EPSA Sénégal toutes deux en paie ivoirienne. Paramètres,
cotisations, barème ITS, rubriques et secteurs sont alors ceux de ce pays ; la nationalité des salariés est comparée
à ce pays (impôt employeur 12 % pour les non-Ivoiriens). L'administrateur de la succursale peut la changer ensuite
(« Ma société » › Modifier) : les avantages des salariés passent sur la rubrique de même code du nouveau pays,
ceux sans équivalent sont signalés et à reprendre sur la fiche du salarié.
Ajouter ensuite les utilisateurs de la société (RH, Comptable, Trésorier) dans « Utilisateurs ».

### 3. Enregistrer les salariés — RH, une fois puis à chaque changement

Menu « Salariés » › **Nouveau** :

- **nationalité** (ivoirienne ou non : taux de l'impôt employeur) ;
- **situation familiale et nombre d'enfants** (parts ITS, CMU) ;
- **date d'entrée** (prime d'ancienneté) ;
- **rémunération** : salaire catégoriel et sursalaire ;
- **ville de résidence** (zone de transport) : fixe l'indemnité de transport, versée au prorata des jours travaillés
  (montant de la zone × jours / 30, comme en v1). Elle remplace un avantage saisi sur la rubrique du transport ;
  un salarié sans zone est signalé dans les contrôles de la paie ;
- **avantages fixes** (logement, responsabilité…), chacun lié à une rubrique ;
- **mode de paiement** (virement avec banque et compte, espèces…).

Ensuite, depuis la fiche : revaloriser, changer de poste, transférer, enregistrer une sortie.

### 4. Préparer la paie du mois — RH, chaque mois

Menu « Paie du mois » :

1. **Ouvrir** le mois.
2. Saisir les **jours travaillés** si le mois n'est pas complet (prorata sur 30 jours).
3. Saisir les **éléments variables** (heures supplémentaires, primes ponctuelles, acomptes, ajustement d'un avantage),
   ou **importer le rapport de travail Excel** (modèle téléchargeable dans l'écran).
   Un élément variable n'est **jamais proratisé** : le montant saisi est repris tel quel. Montant laissé vide :
   base × jours / 30 (le taux est le nombre de jours, comme sur le bulletin : base 30 000, taux 30 → 30 000) ;
   heures supplémentaires : heures × taux horaire majoré.
   Les **éléments fixes** du salarié (salaire, sursalaire, avantages, transport) sont au prorata des jours travaillés,
   sauf les rubriques marquées « sans prorata » (case « Élément fixe au prorata des jours travaillés » décochée),
   comme la **cotisation syndicale** (750, retenue fixe à attribuer sur la fiche du salarié).
   Les listes longues (rubriques, salariés) se filtrent en tapant un code ou une partie du libellé.
4. Contrôler chaque salarié : brut, brut imposable, ITS, cotisations, net à payer.
5. **Valider la paie**.

### Départ en cours de mois : solde de tout compte — RH

1. Fiche du salarié › **Sortie** : date et motif (fin de contrat, démission, licenciement…).
2. « Paie du mois » › le salarié (badge « Solde de tout compte à arrêter ») : vérifier les éléments de sortie
   (jours jusqu'à la date de sortie, indemnités, retenues), puis **Arrêter le solde de tout compte**
   (permission `paie.valider`).
3. Le bulletin est alors **définitif sans attendre la validation du mois** : imprimable, PDF, envoi par e-mail ;
   il porte la mention **SOLDE DE TOUT COMPTE** (date et motif de sortie). Les éléments du salarié sont verrouillés
   (« Annuler l'arrêté » pour corriger, tant que le solde n'est pas payé et le mois pas validé).
4. À la validation du mois, ce bulletin est repris avec tous les autres, toujours avec la mention.

Un transfert vers une autre société du groupe n'est pas un solde de tout compte.

### 5. Bulletins — RH

Menu « Bulletins de paie » : voir, PDF, **envoyer par e-mail** à un salarié ou à tous.

### 6. Paiements — Trésorier

Menu « Paiements », après validation : **payer** un salarié, tous, ou par mode de paiement ;
**ordre de virement** Excel ; **état d'émargement** ; annulation d'un paiement avec motif (jamais supprimé).

### Heures supplémentaires — RH / comptable (`paie.voir`, import et report : `paie.preparer`)

Module repris du projet **heures-sup** (`C:\wamp64\www\heures-sup-main`), moteur de calcul porté à l'identique dans
`src/HeuresSup/` (vérifié sur 20 000 cas aléatoires contre l'original : aucun écart).

1. Menu « Heures supplémentaires » › **Importer un relevé** pour un mois de paie. Formats acceptés :
   l'export du logiciel de pointage utilisé par heures-sup (en-têtes ligne 14, colonnes A à U : B identifiant, C date,
   I horaire « …(40h) / (42h) », J-K entrée / sortie, M heures du jour), ou le **modèle simple** téléchargeable
   (Matricule, Nom, Date, Heures, Entrée, Sortie, Horaire). Les deux **fichiers d'exemple** se téléchargent sur l'écran
   (pré-remplis avec des salariés de la société). Sont **refusés** : autre type ou format de fichier, date ou heures
   invalides, plus de 24 h dans une journée, pointage sans aucun jour dans le mois de paie choisi ; un fichier dont
   aucun salarié n'est reconnu est signalé.
2. Contrôle par salarié et jour par jour : quota (173 h / 182 h), heures à 15 / 50 / 75 / 100 %, paniers, fériés.
   Un identifiant de pointage inconnu se **rattache** à un salarié (mémorisé : `Affectation.identifiantPointage`).
3. **Reporter dans la paie** : rubriques 0014 / 0015 / 0016 / 0017 (15 / 50 / 75 / 100 %) en heures (taux horaire du salarié) et 2073 (panier) = jours × montant
   du panier. Un nouveau report remplace le précédent ; les heures saisies à la main ne sont pas touchées.

Règles : 6 premières heures au-delà du quota à 15 %, puis 50 % (75 % dimanche et férié non chômé) ; en 42 h la nuit
75 % (100 % dimanche / férié). Férié chômé et payé : hors quota, au-delà de 8 h (8 h 30 en 42 h) à 75 % (100 % la nuit
en 42 h) ; veille et lendemain possibles (Fête nationale). Panier : jour ≥ 11 h.
La majoration du férié simple dès la journée normale reste **inactive**, comme dans heures-sup.

**Jours fériés** (bouton dans le module) : nationaux, tenus par le super-administrateur, et propres à la société.
Calendrier Côte d'Ivoire : `php bin/console app:jours-feries:charger CI 2026` (Pâques calculé ; fêtes musulmanes
estimées, marquées « à confirmer »).

### Rubriques & barèmes de la société — consultation : `paie.voir` ; choix des rubriques : `parametre.gerer`

Menu « Rubriques & barèmes » : la législation du pays de paie telle qu'elle s'applique à la société.
Le référentiel reste tenu par la plateforme (une modification vaut pour toutes les sociétés du pays).

- **Rubriques** : code, traitement social et fiscal, usage (avantage fixe, variable du mois) et utilisation dans la société
  (salariés concernés, saisies). Le comptable peut **masquer** une rubrique que la société n'utilise pas : elle n'est plus
  proposée (avantages, paie du mois, import du rapport de travail) ; l'existant est conservé.
- **Barèmes et cotisations** : jours et heures, arrondis, composition du brut, prime d'ancienneté, grille des parts,
  barème de l'impôt et réductions, cotisations aux **taux effectifs de la société** (accident du travail du secteur,
  taux non-nationaux).
- **Simulateur** : bulletin complet d'un mois (brut, impôt, cotisations, net, charges, coût employeur) pour un salarié
  fictif, avec les rubriques de la société. Rien n'est enregistré.

### 7. États de paie — Comptable, RH, Trésorier (permission `etat.voir`, Excel : `etat.exporter`)

Menu « États de paie » (repris de la v1, calculés à partir des bulletins, donc mêmes chiffres) ;
chaque état s'imprime (navigateur → PDF) ou s'exporte en Excel. Les mois non validés sont marqués **provisoire**.

- **Livre de paie** (du mois … au mois …) : une ligne par salarié et par mois — jours, salaire de base, sursalaire,
  ancienneté, primes imposables / exonérées (leur somme = brut), brut imposable, gains hors brut (transport…),
  impôt et chaque cotisation salariale du pays, autres retenues, arrondi, **net à payer**, chaque charge patronale,
  coût employeur ; sous-totaux par mois, total général, effectif. Format paysage.
- **État des rubriques** : total de chaque rubrique (gain, retenue, part patronale) sur la période,
  pour tous les salariés, les nationaux ou les non-nationaux (expatriés) ; total gains − total retenues = net à payer.
- **Bordereau des salaires** : nets à payer par banque (un bordereau par banque, puis chèque / espèces),
  avec n° de compte, total **arrêté en lettres** dans la devise et visa du directeur.
- **Fiche individuelle** : les rubriques d'un salarié de janvier à décembre avec cumuls annuels
  (aussi accessible depuis la fiche du salarié).

### Calcul d'un bulletin (règles de la v1, `SocieteFicheController`)

1. Salaire catégoriel, sursalaire et avantages × jours travaillés ÷ 30.
2. **Prime d'ancienneté** : dès 2 ans, 1 % par année d'ancienneté du salaire catégoriel, 25 % maximum.
3. **Brut** = catégoriel + sursalaire + ancienneté + primes soumises.
4. **Brut imposable** = brut × 0,9 si le salarié a une indemnité « exonérée 10 % », sinon le brut.
5. **ITS** = barème sur le brut imposable − réduction selon les parts.
6. **Retenues salariales** : CNPS 6,3 % du brut (plafond 3 375 000), CMU 500 F par personne couverte
   (salarié, conjoint marié, enfants).
7. **Salaire net** = brut − ITS − CNPS − CMU.
8. **Net à payer** = salaire net + éléments non soumis (transport…) − retenues (acomptes…),
   arrondi au 5 ou à la dizaine (unités 0 et 5 gardées, 1-4 vers le bas, 6-9 vers le haut ; ligne « Arrondi » sur le bulletin).
9. **Charges patronales** : CNPS 7,7 % (même plafond), prestations familiales 5,75 % et accident du travail
   au taux du secteur (plafond 75 000), CMU 500 F par personne, impôt employeur 1,2 % / 12 % du brut imposable,
   FDFP 0,4 % + 0,6 % du brut imposable.

> **Limite actuelle** : une paie validée n'est pas figée. Si un paramètre change après la validation, ses bulletins
> sont recalculés à l'affichage ; seuls les montants des paiements enregistrés restent fixes.

## Organisation des utilisateurs

```
Super-admin (plateforme) ── voit toutes les succursales et tous leurs administrateurs
   └─ Succursale ── administrateurs : tous les droits sur ses sociétés, créent sociétés et équipes
         └─ Société (pays au choix) ── Membre = utilisateur + rôle (RH, Comptable, Trésorier, Consultant…)
                                          └─ Rôle ── Permissions
```

- Un **pays** porte la devise, la langue, le fuseau horaire et l'organisme social.
- Une **succursale** regroupe des sociétés d'un ou plusieurs pays. Elle garde toujours au moins un administrateur actif.
- Une **société** appartient à une succursale et à un pays (sa devise peut être personnalisée).
- Un **utilisateur** peut être membre de plusieurs sociétés, avec **un rôle par société** (entité `Membre`).
- Chaque société reçoit à sa création les rôles Responsable RH, Comptable, Trésorier et Consultant ;
  l'administrateur de succursale peut ajuster leurs permissions et créer des rôles sur mesure.
- Les **transferts de salariés** ne sont possibles qu'entre sociétés d'une même succursale.
- Le **super-administrateur** crée les succursales et leur premier administrateur, gère les pays et voit tous les comptes
  (filtre « Administrateurs de succursale » dans Plateforme › Comptes).

### Ajouter une permission

1. Déclarer la constante et son libellé dans `src/Security/PermissionCatalogue.php`
   (et l'ajouter aux rôles par défaut concernés).
2. `php bin/console app:permissions:sync --roles`
3. Protéger la page : `#[IsGranted(PermissionCatalogue::MA_PERMISSION)]`
   ou dans Twig : `{% if is_granted('module.action') %}`.

Le contrôle tient compte de la société courante (`App\Security\Voter\PermissionVoter`).

## Rubriques de paie

Référentiel **par pays** (Plateforme › Pays › Rubriques) : code, libellé, gain / retenue, position sur le bulletin
(1 soumis, 2 cotisations, 3 non soumis), traitement social, traitement fiscal (dont « exonéré dans la limite de X % du brut »),
attribuable ou non comme avantage.

```bash
php bin/console app:rubriques:charger CI   # ajoute les rubriques par défaut manquantes d'un pays
```

- **CI** : reprise des 28 rubriques de la v1 (`src/Referentiel/RubriquesParDefaut.php`). Codes à faire vérifier :
  `086B` (Prime informatique) et `090B` (Indemnité de communication) étaient en double dans la v1 ; `V21` à `V28` n'avaient pas de code.
- **SN, MA, FR** : jeux illustratifs pour la démo, **à valider par un spécialiste de la paie** de chaque pays.
- Lors d'un transfert vers un autre pays, un avantage est repris sur la rubrique de **même code** du pays d'arrivée ;
  sinon il est signalé comme non repris.

## Paie du mois

Menu **Paie du mois** (permissions `paie.voir`, `paie.preparer`, `paie.cloturer`).

1. **Ouvrir** la paie d'un mois : les éléments fixes de chaque salarié présent sont repris automatiquement
   (salaire catégoriel, sursalaire, avantages en vigueur — en cas de révision en cours de mois, le montant le plus récent).
2. Saisir les **éléments variables** du mois, par salarié :
   - **Ajustement d'un avantage** : montant pour ce mois seulement (absence, prorata ; 0 = non versé). La fiche du salarié ne change pas.
   - **Élément ponctuel** : toute rubrique marquée « variable » (heures supplémentaires, primes, rappels, acomptes, retenues…),
     avec montant direct ou **base × taux** (ex. 10 h × 2 875).
3. **Clôturer** : plus aucune modification (réouverture possible avec la même permission).

Le récapitulatif donne gains, retenues (y compris impôt et cotisations salariales), **net à payer** et charges patronales.

**Valider la paie** (l'import Excel est facultatif) : page de contrôle avec le récapitulatif de tous les salariés
et les anomalies (bloquantes : pas de rémunération, montant négatif ; à vérifier : parts introuvables, 0 jour…),
puis validation (permission `paie.valider`) qui verrouille le mois ; réouverture avec `paie.cloturer`.
**Rapport individuel** imprimable (A4, en-tête société + Côte d'Ivoire Paie) pour chaque salarié, ou tous les rapports
du mois en un document (un salarié par page), « document provisoire » tant que la paie n'est pas validée.

**Import Excel du rapport de travail** (bouton sur la paie du mois) : télécharger le modèle pré-rempli (matricule, nom,
jours, une colonne par rubrique variable), le remplir — heures pour les heures supplémentaires, montants sinon —,
l'envoyer : aperçu et contrôles ligne par ligne, rien n'est enregistré avant validation ; un réimport remplace l'import précédent.

## Paramètres de paie par pays (super-administrateur)

Plateforme › Pays › **Paramètres de paie** — première partie du bulletin, avec simulateur :

| Bloc | Réglages | Côte d'Ivoire (modèle, repris de la v1) |
|---|---|---|
| Temps de travail | jours de base, prorata des éléments fixes, heures mensuelles | 30 jours, prorata, 173,33 h |
| Prime d'ancienneté | années minimum, taux initial, taux par an, taux max, base | 2 ans, 2 %, +1 %/an, 25 %, salaire catégoriel |
| Nombre de parts | grille situation × enfants | table `nombre_part` de la v1 |
| Salaire brut | sursalaire, ancienneté, gains soumis / non soumis | catégoriel + sursalaire + ancienneté + gains soumis |
| Brut imposable | exonération plafonnée (min(indemnités, X % du brut)) ou forfaitaire (v1) | forfaitaire : brut × 0,9 |
| Net à payer | arrondi : aucun, ou au 5 / à la dizaine (v1) | au 5 / à la dizaine |

Heures supplémentaires : coefficient sur la rubrique (0014 à 15 % = 1,15 … 0017 à 100 % = 2) ; taux = (catégoriel + sursalaire) / heures mensuelles × coefficient.
Deuxième partie, sur la même page et sur Plateforme › Pays › **Cotisations** :

- **Impôt sur le salaire** (ITS en Côte d'Ivoire) : barème progressif mensuel par tranches, assiette (brut imposable),
  puis réduction selon le nombre de parts.
- **Cotisations** : libellé, codes salarial / patronal, assiette (brut, brut imposable ou forfait × personnes couvertes),
  plafond, taux salarial et patronal, catégorie (charge fiscale ou sociale de l'employeur).
  Option **taux patronal non-nationaux** (impôt employeur CI : 12 % au lieu de 1,2 %) et option
  **taux de la société** (accident du travail : taux du secteur d'activité de la société).
- **Secteurs d'activité** (Plateforme › Pays › **Secteurs**) : taux de risque accident du travail par secteur
  (29 secteurs CI de la v1, `src/Referentiel/SecteursActiviteParDefaut.php`) ; chaque société choisit le sien.

Le modèle de bulletin ivoirien est reproduit à l'identique (24 jours : brut 91 120, ITS 2 579, CNPS 5 741, CMU 500,
salaire net 82 300, transport 24 000, **net à payer 106 300**, charges patronales 15 333).
Les valeurs ivoiriennes chargées (barème ITS, réductions, taux) sont **à valider** avant la mise en production.

## Bulletins de paie

Menu **Bulletins de paie** (permissions `bulletin.imprimer`, `bulletin.envoyer` ; RH et Comptable par défaut) :

- Liste du mois : brut, retenues, net à payer, statut d'envoi de chaque salarié.
- **Voir** (écran) ou **PDF** pour un salarié, ou **tous les bulletins** du mois en un seul PDF (un par page) pour l'impression.
- Tant que la paie n'est pas validée, les bulletins portent le filigrane « PROVISOIRE » et ne peuvent pas être envoyés.
- **Envoyer** / **Envoyer à tous** : e-mail au salarié avec son bulletin PDF en pièce jointe (réponse vers l'e-mail de la société).
  Chaque envoi (réussi ou en échec) est historisé ; les salariés sans adresse e-mail sont signalés.

## Paiement des salaires (trésorerie)

Menu **Paiements** (permissions `paiement.voir` : Trésorier, Comptable, Consultant ; `paiement.executer` : Trésorier) :

- Disponible une fois la paie **validée** : net à payer de chaque salarié, payé, reste à payer, avancement.
- **Payer** un salarié (moyen de paiement, date, référence : n° de chèque, de virement…) ou **Payer…** tous les salariés
  restants, ou seulement ceux d'un moyen de paiement (ex. tous les virements une fois l'ordre exécuté par la banque).
  Le montant est le net à payer calculé, jamais saisi ; banque et compte sont figés au moment du paiement.
- **Annuler** un paiement (motif obligatoire) : il reste dans l'historique et le salarié repasse « à payer ».
- **Ordre de virement** (Excel) : une feuille par moyen de paiement, groupé par banque avec sous-totaux, cases de visa.
- **État d'émargement** imprimable (signature des salariés payés en espèces ou par chèque).
- Une paie dont des salaires sont payés ne peut plus être rouverte (annuler d'abord les paiements).

Configuration de l'envoi des bulletins par e-mail dans `.env.local` :

```
MAILER_DSN=smtp://utilisateur:motdepasse@smtp.exemple.ci:587
MAILER_FROM=no-reply@cotedivoirepaie.ci
```

## Logos

- **Plateforme** : l'application est éditée par **Côte d'Ivoire Paie** (CIPaie). Son logo (`assets/images/cipaie-logo.jpg`,
  icône `cipaie-icone.png`) figure dans la barre latérale, sur la page de connexion et en pied de chaque bulletin.
- **Sociétés** : chaque société téléverse son logo (fiche société › Modifier). JPG, PNG ou WebP, 2 Mo max, 80 px de large min ;
  il est réduit à 600×300 px max et stocké dans `public/uploads/logos/` (hors git). Il est imprimé en en-tête
  de chaque bulletin de paie (`templates/bulletin/_entete.html.twig`, aperçu sur la page « Ma société »).

## Salariés

```
Salarié (la personne : identité, état civil, banque)
   └─* Affectation = un passage dans une société (matricule, poste, contrat, entrée / sortie)
          ├─* Rémunération : historique daté (salaire de base + sursalaire, motif)
          └─* Avantage     : montant mensuel, du … au …, imposable / en nature
```

- **Embauche** : identité, poste, salaire de départ et avantages fixes en un seul formulaire ; matricule attribué automatiquement (M0001…) si vide.
- **Revaloriser** ajoute une ligne datée à l'historique (jamais antérieure à la dernière).
  **Corriger** rectifie une erreur de saisie sur la dernière ligne uniquement, avec trace de l'auteur.
- **Avantages** (éléments de salaire fixes) : chacun est rattaché à une **rubrique** du pays, qui porte son code et son
  traitement social / fiscal. Ajoutés à tout moment depuis la fiche ; **Modifier le montant** clôt l'ancienne ligne la veille
  et en ouvre une nouvelle ; on les **arrête** (date de fin) plutôt que de les supprimer.
- **Changer de poste** : nouvelle fonction (+ métier, catégorie, échelon), rémunération et avantages revus à la même date
  (montant à la hausse ou à la baisse, 0 = arrêt, nouveaux avantages). Historique des postes sur la fiche.
- **Sortie** : clôture l'affectation, arrête les avantages, retire ce qui était prévu après la date. Le dossier passe en lecture seule.
- **Transfert** : clôture l'affectation la veille (motif « Transfert ») et en ouvre une nouvelle dans l'autre société
  (nouveau matricule, salaire dans sa devise, reprise des avantages en option). Le parcours complet reste visible.
  Il faut le droit `salarie.creer` dans la société de destination.
- Les montants sont stockés en `DECIMAL(15,2)` et calculés avec bcmath (pas d'erreurs d'arrondi).

Permissions : `salarie.voir`, `salarie.creer`, `salarie.modifier`, `salarie.supprimer` (sortie),
`salarie.remuneration` (voir/modifier salaires et avantages — confidentiel), `salarie.transferer`.

## Garde-fous

- Une société garde toujours au moins un administrateur actif.
- Le rôle Administrateur conserve toujours la gestion des utilisateurs et des rôles.
- Un compte créé reçoit un mot de passe provisoire, à changer à la première connexion.
- Connexion limitée à 5 tentatives (protection contre la force brute).

## Feuille de route

- [x] Socle Symfony 7.4 / PHP 8.3, nouveau thème d'administration
- [x] Pays, sociétés, utilisateurs, rôles et permissions
- [x] Salariés : embauche, historique des salaires, avantages, sortie, transfert entre sociétés
- [x] Rubriques et barèmes de paie par pays (Côte d'Ivoire ; autres pays à paramétrer)
- [x] Calcul de la paie, bulletins PDF, envoi par e-mail
- [x] États : livre de paie, état des rubriques, bordereau des salaires, fiche individuelle
- [ ] Déclarations sociales et fiscales
- [x] Trésorerie (paiement des salaires)
- [ ] Traduction de l'interface (anglais…)
