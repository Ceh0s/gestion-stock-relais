<?php

namespace App\Imports;

use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Site;
use App\Models\User;
use App\Services\MaterielService;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;

/**
 * Import Excel / CSV de l'inventaire (Algorithme §10), en mode strict :
 * toutes les lignes sont vérifiées d'abord, et rien n'est importé s'il y a la moindre erreur.
 */
class MaterielsImport implements ToCollection, WithHeadingRow
{
    public const COLONNES_OBLIGATOIRES = [
        'reference', 'type_materiel', 'marque', 'modele', 'condition_stock', 'statut', 'site', 'emplacement',
    ];

    public const COLONNES_FACULTATIVES = [
        'numero_serie', 'reference_constructeur', 'utilisateur', 'date_acquisition', 'date_fin_garantie', 'commentaire',
    ];

    public array $erreurs = [];

    public int $nombreLignes = 0;

    public int $nombreImportees = 0;

    public int $nombreDoublons = 0;

    /**
     * @param  User|null  $acteur  utilisateur qui importe, enregistré comme auteur de la création dans l'historique
     * @param  string|null  $nomFichier  repris dans le commentaire de l'événement de création
     */
    public function __construct(
        private readonly ?User $acteur = null,
        private readonly ?string $nomFichier = null,
    ) {}

    public function collection(BaseCollection $rows): void
    {
        // Les lignes entièrement vides (fréquentes en fin de fichier Excel) sont ignorées.
        $rows = $rows->reject(fn ($row) => collect($row)->every(fn ($cellule) => $this->texte($cellule) === null));
        $this->nombreLignes = $rows->count();

        if ($rows->isEmpty()) {
            $this->erreurs[] = 'Le fichier ne contient aucune ligne de matériel.';

            return;
        }

        // Algorithme §10.2 : si une colonne obligatoire manque, on rejette le fichier entier
        // avec un seul message, au lieu d'une erreur par ligne.
        $colonnes = array_keys(collect($rows->first())->all());
        foreach (array_diff(self::COLONNES_OBLIGATOIRES, $colonnes) as $manquante) {
            $this->erreurs[] = "Le fichier Excel ne contient pas la colonne \"{$manquante}\".";
        }
        if ($this->erreurs) {
            return;
        }

        $referencesVues = [];
        $numerosSerieVus = [];
        $lignesValides = [];

        foreach ($rows as $index => $row) {
            $numeroLigne = $index + 2; // +1 pour l'en-tête, +1 car Excel numérote à partir de 1
            [$ligne, $erreursLigne] = $this->validerLigne($row, $numeroLigne, $referencesVues, $numerosSerieVus);

            if ($erreursLigne) {
                $this->erreurs = [...$this->erreurs, ...$erreursLigne];
            } else {
                $lignesValides[] = $ligne;
            }
        }

        if ($this->erreurs) {
            return;
        }

        // Harmonise la casse de la marque, du modèle et du type avec l'inventaire
        // et avec les lignes précédentes du fichier ("dell" devient "Dell").
        $service = app(MaterielService::class);
        $connues = [];
        foreach ($lignesValides as $i => $ligne) {
            $lignesValides[$i] = $ligne = $service->harmoniserLibelles($ligne, $connues);
            foreach (MaterielService::CHAMPS_SUGGERES as $champ) {
                $connues[$champ][] = $ligne[$champ];
            }
        }

        $commentaire = $this->nomFichier ? "Import du fichier {$this->nomFichier}" : 'Import Excel';

        DB::transaction(function () use ($lignesValides, $service, $commentaire) {
            foreach ($lignesValides as $ligne) {
                $materiel = Materiel::create($ligne);
                // Algorithme §10.2 : "Ajouter un événement de création".
                $service->tracerCreation($materiel, $this->acteur, $commentaire);
                $this->nombreImportees++;
            }
        });
    }

    /**
     * Vérifie une ligne et la convertit en données de matériel.
     *
     * @return array{0: array, 1: list<string>} [données normalisées, erreurs]
     */
    private function validerLigne($row, int $numeroLigne, array &$referencesVues, array &$numerosSerieVus): array
    {
        $erreurs = [];
        $l = "Ligne {$numeroLigne} :";

        foreach (self::COLONNES_OBLIGATOIRES as $colonne) {
            if ($this->texte($row[$colonne] ?? null) === null) {
                $erreurs[] = "{$l} la cellule \"{$colonne}\" est vide.";
            }
        }
        if ($erreurs) {
            return [[], $erreurs];
        }

        $reference = strtoupper($this->texte($row['reference']));
        $numeroSerie = $this->texte($row['numero_serie'] ?? null);
        $site = $this->texte($row['site']);
        $nomEmplacement = $this->texte($row['emplacement']);

        // Accepte la valeur technique ("a_verifier") comme le libellé affiché ("À vérifier"),
        // quelle que soit la casse : le cahier des charges §9 donne "Disponible;Neuf" en exemple.
        $condition = $this->valeurConnue($row['condition_stock'], Materiel::LIBELLES_CONDITIONS);
        if ($condition === null) {
            $erreurs[] = "{$l} type de stock \"{$this->texte($row['condition_stock'])}\" invalide (valeurs possibles : ".implode(', ', Materiel::CONDITIONS).').';
        }

        $statut = $this->valeurConnue($row['statut'], Materiel::LIBELLES_STATUTS);
        if ($statut === null) {
            $erreurs[] = "{$l} statut \"{$this->texte($row['statut'])}\" invalide (valeurs possibles : ".implode(', ', Materiel::STATUTS).').';
        }

        // Colonne facultative "utilisateur" = email du compte (Algorithme §10.1).
        // Elle n'a de sens que pour un matériel attribué, et y est alors obligatoire.
        $utilisateurId = null;
        $email = $this->texte($row['utilisateur'] ?? null);
        if ($email !== null) {
            $utilisateur = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
            if (! $utilisateur) {
                $erreurs[] = "{$l} aucun compte utilisateur avec l'email \"{$email}\".";
            } elseif (! $utilisateur->actif) {
                $erreurs[] = "{$l} le compte \"{$email}\" est désactivé.";
            } else {
                $utilisateurId = $utilisateur->id;
            }

            if ($statut !== null && $statut !== 'attribue') {
                $erreurs[] = "{$l} un utilisateur n'est accepté que pour un matériel au statut \"attribue\".";
            }
        } elseif ($statut === 'attribue') {
            $erreurs[] = "{$l} le statut \"attribue\" nécessite la colonne \"utilisateur\" (email du compte).";
        }

        $emplacement = null;
        if (! Site::where('nom', $site)->exists()) {
            $erreurs[] = "{$l} le site \"{$site}\" n'existe pas.";
        } else {
            $emplacement = Emplacement::whereHas('site', fn ($q) => $q->where('nom', $site))->where('nom', $nomEmplacement)->first();
            if (! $emplacement) {
                $erreurs[] = "{$l} l'emplacement \"{$nomEmplacement}\" n'existe pas pour le site \"{$site}\".";
            }
        }

        $dates = [];
        foreach (['date_acquisition', 'date_fin_garantie'] as $colonne) {
            $dates[$colonne] = $this->date($row[$colonne] ?? null);
            if ($dates[$colonne] === false) {
                $erreurs[] = "{$l} la date \"{$this->texte($row[$colonne])}\" ({$colonne}) n'est pas reconnue (formats acceptés : JJ/MM/AAAA ou AAAA-MM-JJ).";
            }
        }

        // Cahier des charges (Algorithme.pdf §10.2 et §11) : une référence ou un numéro de série
        // déjà présent dans la base, ou en double dans le fichier, rejette l'import.
        if (isset($referencesVues[$reference])) {
            $erreurs[] = "{$l} la référence \"{$reference}\" apparaît plusieurs fois dans le fichier (déjà vue ligne {$referencesVues[$reference]}).";
            $this->nombreDoublons++;
        } elseif (Materiel::where('reference', $reference)->exists()) {
            $erreurs[] = "{$l} la référence \"{$reference}\" existe déjà dans l'inventaire.";
            $this->nombreDoublons++;
        } else {
            $referencesVues[$reference] = $numeroLigne;
        }

        if ($numeroSerie) {
            if (isset($numerosSerieVus[$numeroSerie])) {
                $erreurs[] = "{$l} le numéro de série \"{$numeroSerie}\" apparaît plusieurs fois dans le fichier (déjà vu ligne {$numerosSerieVus[$numeroSerie]}).";
                $this->nombreDoublons++;
            } elseif ($existant = Materiel::where('numero_serie', $numeroSerie)->first()) {
                $erreurs[] = "{$l} le numéro de série \"{$numeroSerie}\" est déjà utilisé par le matériel \"{$existant->reference}\" de l'inventaire.";
                $this->nombreDoublons++;
            } else {
                $numerosSerieVus[$numeroSerie] = $numeroLigne;
            }
        }

        if ($erreurs) {
            return [[], $erreurs];
        }

        return [[
            'reference' => $reference,
            'type_materiel' => $this->texte($row['type_materiel']),
            'marque' => $this->texte($row['marque']),
            'modele' => $this->texte($row['modele']),
            'numero_serie' => $numeroSerie,
            'reference_constructeur' => $this->texte($row['reference_constructeur'] ?? null),
            'condition_stock' => $condition,
            'statut' => $statut,
            'emplacement_id' => $emplacement->id,
            'utilisateur_id' => $utilisateurId,
            'date_acquisition' => $dates['date_acquisition'],
            'date_fin_garantie' => $dates['date_fin_garantie'],
            'commentaire' => $this->texte($row['commentaire'] ?? null),
        ], []];
    }

    /**
     * Contenu d'une cellule nettoyé (espaces retirés), ou null si la cellule est vide.
     * Une cellule numérique (ex. référence 621) est convertie en texte ; "0" n'est pas considéré comme vide.
     */
    private function texte(mixed $cellule): ?string
    {
        $texte = trim((string) $cellule);

        return $texte === '' ? null : $texte;
    }

    /**
     * Retrouve la valeur technique à partir de la valeur technique ou du libellé affiché, sans tenir compte de la casse.
     *
     * @param  array<string, string>  $libelles  valeur technique => libellé
     */
    private function valeurConnue(mixed $cellule, array $libelles): ?string
    {
        $saisie = mb_strtolower($this->texte($cellule) ?? '');

        foreach ($libelles as $valeur => $libelle) {
            if ($saisie === $valeur || $saisie === mb_strtolower($libelle)) {
                return $valeur;
            }
        }

        return null;
    }

    /**
     * Date d'une cellule au format AAAA-MM-JJ, null si la cellule est vide, false si elle est illisible.
     * Une cellule Excel au format date arrive sous forme de nombre de jours (ex. 45731), d'où la conversion.
     */
    private function date(mixed $cellule): string|false|null
    {
        $texte = $this->texte($cellule);
        if ($texte === null) {
            return null;
        }

        if (is_numeric($texte)) {
            return DateExcel::excelToDateTimeObject((float) $texte)->format('Y-m-d');
        }

        // "j/n/Y" accepte aussi 5/3/2025. On rejette les dates impossibles (31/02/2025)
        // que PHP corrigerait sinon silencieusement en 03/03/2025.
        foreach (['j/n/Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $texte);
            $avertissements = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($avertissements === false || $avertissements['warning_count'] === 0)) {
                return $date->format('Y-m-d');
            }
        }

        return false;
    }
}
