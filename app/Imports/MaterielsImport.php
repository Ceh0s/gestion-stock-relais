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

class MaterielsImport implements ToCollection, WithHeadingRow
{
    private const CHAMPS_OBLIGATOIRES = [
        'reference', 'type_materiel', 'marque', 'modele', 'condition_stock', 'statut', 'site', 'emplacement',
    ];

    public array $erreurs = [];

    public int $nombreLignes = 0;

    public int $nombreImportees = 0;

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
        $this->nombreLignes = $rows->count();

        $referencesVues = [];
        $numerosSerieVus = [];
        $lignesValides = [];

        foreach ($rows as $index => $row) {
            $numeroLigne = $index + 2;
            $erreursLigne = $this->validerLigne($row, $numeroLigne, $referencesVues, $numerosSerieVus);

            if ($erreursLigne) {
                $this->erreurs = [...$this->erreurs, ...$erreursLigne];

                continue;
            }

            $lignesValides[] = $this->normaliserLigne($row);
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

    private function validerLigne($row, int $numeroLigne, array &$referencesVues, array &$numerosSerieVus): array
    {
        $erreurs = [];

        foreach (self::CHAMPS_OBLIGATOIRES as $champ) {
            if (empty(trim((string) ($row[$champ] ?? '')))) {
                $erreurs[] = "Ligne {$numeroLigne} : le champ \"{$champ}\" est obligatoire.";
            }
        }

        if ($erreurs) {
            return $erreurs;
        }

        $reference = strtoupper(trim((string) $row['reference']));
        $numeroSerie = trim((string) ($row['numero_serie'] ?? '')) ?: null;

        if (! in_array($row['condition_stock'], Materiel::CONDITIONS, true)) {
            $erreurs[] = "Ligne {$numeroLigne} : condition_stock \"{$row['condition_stock']}\" invalide.";
        }

        if (! in_array($row['statut'], Materiel::STATUTS, true)) {
            $erreurs[] = "Ligne {$numeroLigne} : statut \"{$row['statut']}\" invalide.";
        } elseif ($row['statut'] === 'attribue') {
            // Le fichier ne précise pas à qui le matériel est attribué : l'attribution
            // doit passer par l'action "Attribuer" de la fiche (qui l'enregistre dans l'historique).
            $erreurs[] = "Ligne {$numeroLigne} : le statut \"attribue\" n'est pas accepté à l'import, car le fichier ne précise pas l'utilisateur. Importez le matériel avec un autre statut, puis attribuez-le depuis sa fiche.";
        }

        if (! Site::where('nom', $row['site'])->exists()) {
            $erreurs[] = "Ligne {$numeroLigne} : site \"{$row['site']}\" introuvable.";
        } elseif (! Emplacement::whereHas('site', fn ($q) => $q->where('nom', $row['site']))->where('nom', $row['emplacement'])->exists()) {
            $erreurs[] = "Ligne {$numeroLigne} : emplacement \"{$row['emplacement']}\" introuvable pour le site \"{$row['site']}\".";
        }

        // Cahier des charges (Algorithme.pdf §10.2 et §11) : une référence ou un numéro de série
        // déjà présent dans la base, ou en double dans le fichier, rejette l'import.
        if (isset($referencesVues[$reference])) {
            $erreurs[] = "Ligne {$numeroLigne} : la référence \"{$reference}\" apparaît plusieurs fois dans le fichier (déjà vue ligne {$referencesVues[$reference]}).";
        } elseif (Materiel::where('reference', $reference)->exists()) {
            $erreurs[] = "Ligne {$numeroLigne} : la référence \"{$reference}\" existe déjà dans l'inventaire.";
        } else {
            $referencesVues[$reference] = $numeroLigne;
        }

        if ($numeroSerie) {
            if (isset($numerosSerieVus[$numeroSerie])) {
                $erreurs[] = "Ligne {$numeroLigne} : le numéro de série \"{$numeroSerie}\" apparaît plusieurs fois dans le fichier (déjà vu ligne {$numerosSerieVus[$numeroSerie]}).";
            } elseif ($existant = Materiel::where('numero_serie', $numeroSerie)->first()) {
                $erreurs[] = "Ligne {$numeroLigne} : le numéro de série \"{$numeroSerie}\" est déjà utilisé par le matériel \"{$existant->reference}\" de l'inventaire.";
            } else {
                $numerosSerieVus[$numeroSerie] = $numeroLigne;
            }
        }

        return $erreurs;
    }

    private function normaliserLigne($row): array
    {
        $emplacement = Emplacement::whereHas('site', fn ($q) => $q->where('nom', $row['site']))
            ->where('nom', $row['emplacement'])
            ->first();

        return [
            'reference' => strtoupper(trim($row['reference'])),
            'type_materiel' => trim($row['type_materiel']),
            'marque' => trim($row['marque']),
            'modele' => trim($row['modele']),
            'numero_serie' => trim((string) ($row['numero_serie'] ?? '')) ?: null,
            'reference_constructeur' => trim((string) ($row['reference_constructeur'] ?? '')) ?: null,
            'condition_stock' => $row['condition_stock'],
            'statut' => $row['statut'],
            'emplacement_id' => $emplacement->id,
            'date_acquisition' => $row['date_acquisition'] ?? null,
            'date_fin_garantie' => $row['date_fin_garantie'] ?? null,
            'commentaire' => $row['commentaire'] ?? null,
        ];
    }
}
