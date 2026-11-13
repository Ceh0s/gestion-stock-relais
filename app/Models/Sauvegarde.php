<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du journal des sauvegardes de la base (Algorithme §16).
 * Le fichier lui-même est dans storage/app/private/sauvegardes/.
 */
#[Fillable([
    'nom_fichier', 'statut', 'taille', 'empreinte', 'copie_externe', 'message',
    'nombre_materiels', 'nombre_mouvements', 'nombre_utilisateurs', 'utilisateur_id',
])]
class Sauvegarde extends Model
{
    public const LIBELLES_STATUTS = [
        'reussie' => 'Réussie',
        'echec' => 'Échec',
    ];

    protected function casts(): array
    {
        return [
            'copie_externe' => 'boolean',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function estReussie(): bool
    {
        return $this->statut === 'reussie';
    }

    public function libelleStatut(): string
    {
        return self::LIBELLES_STATUTS[$this->statut] ?? $this->statut;
    }

    /** Chemin du fichier sur le disque "local" (storage/app/private). */
    public function chemin(): string
    {
        return config('sauvegarde.dossier').'/'.$this->nom_fichier;
    }

    /** Taille lisible, ex. "1,4 Mo". */
    public function tailleLisible(): string
    {
        if ($this->taille === null) {
            return '—';
        }

        $unites = ['o', 'Ko', 'Mo', 'Go'];
        $taille = (float) $this->taille;
        $i = 0;
        while ($taille >= 1024 && $i < count($unites) - 1) {
            $taille /= 1024;
            $i++;
        }

        return number_format($taille, $i === 0 ? 0 : 1, ',', ' ').' '.$unites[$i];
    }
}
