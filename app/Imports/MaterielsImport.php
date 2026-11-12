<?php

namespace App\Imports;

use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Site;
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

        DB::transaction(function () use ($lignesValides) {
            foreach ($lignesValides as $ligne) {
                Materiel::create($ligne);
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

        $reference = strtoupper(trim($row['reference']));
        $numeroSerie = trim((string) ($row['numero_serie'] ?? '')) ?: null;

        if (! in_array($row['condition_stock'], Materiel::CONDITIONS, true)) {
            $erreurs[] = "Ligne {$numeroLigne} : condition_stock \"{$row['condition_stock']}\" invalide.";
        }

        if (! in_array($row['statut'], Materiel::STATUTS, true)) {
            $erreurs[] = "Ligne {$numeroLigne} : statut \"{$row['statut']}\" invalide.";
        }

        if (! Site::where('nom', $row['site'])->exists()) {
            $erreurs[] = "Ligne {$numeroLigne} : site \"{$row['site']}\" introuvable.";
        } elseif (! Emplacement::whereHas('site', fn ($q) => $q->where('nom', $row['site']))->where('nom', $row['emplacement'])->exists()) {
            $erreurs[] = "Ligne {$numeroLigne} : emplacement \"{$row['emplacement']}\" introuvable pour le site \"{$row['site']}\".";
        }

        if (Materiel::where('reference', $reference)->exists() || isset($referencesVues[$reference])) {
            $erreurs[] = "Ligne {$numeroLigne} : référence \"{$reference}\" en doublon.";
        }
        $referencesVues[$reference] = true;

        if ($numeroSerie) {
            if (Materiel::where('numero_serie', $numeroSerie)->exists() || isset($numerosSerieVus[$numeroSerie])) {
                $erreurs[] = "Ligne {$numeroLigne} : numéro de série \"{$numeroSerie}\" en doublon.";
            }
            $numerosSerieVus[$numeroSerie] = true;
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
            'condition_stock' => $row['condition_stock'],
            'statut' => $row['statut'],
            'emplacement_id' => $emplacement->id,
            'date_acquisition' => $row['date_acquisition'] ?? null,
            'date_fin_garantie' => $row['date_fin_garantie'] ?? null,
            'commentaire' => $row['commentaire'] ?? null,
        ];
    }
}
