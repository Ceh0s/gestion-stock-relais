<?php

namespace App\Exports;

use App\Models\Materiel;
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
        $query = Materiel::query()->with(['emplacement.site', 'utilisateur']);

        if (! empty($this->filtres['site_id'])) {
            $query->whereHas('emplacement', fn ($q) => $q->where('site_id', $this->filtres['site_id']));
        }
        if (! empty($this->filtres['statut'])) {
            $query->where('statut', $this->filtres['statut']);
        }
        if (! empty($this->filtres['condition_stock'])) {
            $query->where('condition_stock', $this->filtres['condition_stock']);
        }

        return $query->orderBy('reference')->get()->map(fn (Materiel $materiel) => [
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
