<?php

namespace App\Exports;

use App\Models\Materiel;
use App\Services\MaterielService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MaterielsExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly array $filtres = []) {}

    public function headings(): array
    {
        return [
            'reference', 'type_materiel', 'marque', 'modele', 'numero_serie', 'reference_constructeur',
            'condition_stock', 'statut', 'site', 'emplacement', 'utilisateur',
            'date_acquisition', 'date_fin_garantie', 'commentaire',
        ];
    }

    public function collection(): Collection
    {
        // Mêmes filtres que la liste des matériels : l'export contient exactement ce qui est affiché.
        $query = app(MaterielService::class)->requeteRecherche($this->filtres);

        return $query->get()->map(fn (Materiel $materiel) => [
            'reference' => $materiel->reference,
            'type_materiel' => $materiel->type_materiel,
            'marque' => $materiel->marque,
            'modele' => $materiel->modele,
            'numero_serie' => $materiel->numero_serie,
            'reference_constructeur' => $materiel->reference_constructeur,
            'condition_stock' => $materiel->condition_stock,
            'statut' => $materiel->statut,
            'site' => $materiel->emplacement?->site?->nom,
            'emplacement' => $materiel->emplacement?->nom,
            'utilisateur' => $materiel->utilisateur?->nomComplet(),
            'date_acquisition' => $materiel->date_acquisition?->toDateString(),
            'date_fin_garantie' => $materiel->date_fin_garantie?->toDateString(),
            'commentaire' => $materiel->commentaire,
        ]);
    }
}
