<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du journal des exports Excel (table "exports", Algorithme §12).
 * Nommé JournalExport pour ne pas le confondre avec App\Exports\MaterielsExport, qui génère le fichier.
 */
#[Fillable(['utilisateur_id', 'nom_fichier', 'filtres', 'nombre_lignes'])]
class JournalExport extends Model
{
    protected $table = 'exports';

    protected function casts(): array
    {
        return [
            'filtres' => 'array',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    /**
     * Filtres de l'export en texte lisible, ex. "Statut : En réparation · Site : Site 2".
     */
    public function resumeFiltres(): string
    {
        $parties = [];

        foreach ($this->filtres ?? [] as $critere => $valeur) {
            $parties[] = match ($critere) {
                'terme' => "Recherche : « {$valeur} »",
                'type_materiel' => "Type : {$valeur}",
                'site_id' => 'Site : '.(Site::find($valeur)?->nom ?? "#{$valeur}"),
                'emplacement_id' => 'Emplacement : '.(Emplacement::with('site')->find($valeur)?->libelle() ?? "#{$valeur}"),
                'condition_stock' => 'Stock : '.Materiel::libelleCondition($valeur),
                'statut' => 'Statut : '.Materiel::libelleStatut($valeur),
                'utilisateur_id' => 'Utilisateur : '.(User::find($valeur)?->nomComplet() ?? "#{$valeur}"),
                'tri' => null,
                default => "{$critere} : {$valeur}",
            };
        }

        $parties = array_filter($parties);

        return $parties ? implode(' · ', $parties) : 'Tout l\'inventaire';
    }
}
