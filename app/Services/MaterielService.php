<?php

namespace App\Services;

use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Mouvement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterielService
{
    public function ajouter(array $data, User $acteur): Materiel
    {
        $data['reference'] = strtoupper(trim($data['reference']));

        if (Materiel::where('reference', $data['reference'])->exists()) {
            throw ValidationException::withMessages(['reference' => 'Cette référence existe déjà.']);
        }

        if (! empty($data['numero_serie'])) {
            $data['numero_serie'] = trim($data['numero_serie']);

            if (Materiel::where('numero_serie', $data['numero_serie'])->exists()) {
                throw ValidationException::withMessages(['numero_serie' => 'Ce numéro de série existe déjà.']);
            }
        }

        $emplacement = Emplacement::where('id', $data['emplacement_id'])->where('actif', true)->first();
        if (! $emplacement) {
            throw ValidationException::withMessages(['emplacement_id' => "L'emplacement sélectionné n'existe pas ou est inactif."]);
        }

        if (($data['statut'] ?? 'disponible') === 'attribue' && empty($data['utilisateur_id'])) {
            throw ValidationException::withMessages(['utilisateur_id' => 'Un utilisateur doit être sélectionné pour un matériel attribué.']);
        }

        return DB::transaction(function () use ($data) {
            return Materiel::create($data);
        });
    }

    public function deplacer(Materiel $materiel, int $nouvelEmplacementId, ?string $motif, ?string $commentaire, User $acteur): Materiel
    {
        $nouvelEmplacement = Emplacement::where('id', $nouvelEmplacementId)->where('actif', true)->first();
        if (! $nouvelEmplacement) {
            throw ValidationException::withMessages(['emplacement_id' => "Le nouvel emplacement n'existe pas ou est inactif."]);
        }

        if ($materiel->emplacement_id === $nouvelEmplacement->id) {
            throw ValidationException::withMessages(['emplacement_id' => 'Le matériel est déjà à cet emplacement.']);
        }

        return DB::transaction(function () use ($materiel, $nouvelEmplacement, $motif, $commentaire, $acteur) {
            $materiel = Materiel::whereKey($materiel->id)->lockForUpdate()->firstOrFail();

            $ancienEmplacementId = $materiel->emplacement_id;

            $materiel->update([
                'emplacement_id' => $nouvelEmplacement->id,
                'version' => $materiel->version + 1,
            ]);

            Mouvement::create([
                'materiel_id' => $materiel->id,
                'ancien_emplacement_id' => $ancienEmplacementId,
                'nouvel_emplacement_id' => $nouvelEmplacement->id,
                'ancien_statut' => $materiel->statut,
                'nouveau_statut' => $materiel->statut,
                'user_id' => $acteur->id,
                'motif' => $motif,
                'commentaire' => $commentaire,
            ]);

            return $materiel;
        });
    }

    public function attribuer(Materiel $materiel, int $nouvelUtilisateurId, ?string $commentaire, User $acteur): Materiel
    {
        $destinataire = User::where('id', $nouvelUtilisateurId)->where('actif', true)->first();
        if (! $destinataire) {
            throw ValidationException::withMessages(['utilisateur_id' => "L'utilisateur destinataire n'existe pas ou est inactif."]);
        }

        return DB::transaction(function () use ($materiel, $destinataire, $commentaire, $acteur) {
            $materiel = Materiel::whereKey($materiel->id)->lockForUpdate()->firstOrFail();

            $ancienUtilisateurId = $materiel->utilisateur_id;
            $ancienStatut = $materiel->statut;

            $materiel->update([
                'utilisateur_id' => $destinataire->id,
                'statut' => 'attribue',
                'version' => $materiel->version + 1,
            ]);

            Mouvement::create([
                'materiel_id' => $materiel->id,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => 'attribue',
                'ancien_utilisateur_id' => $ancienUtilisateurId,
                'nouvel_utilisateur_id' => $destinataire->id,
                'user_id' => $acteur->id,
                'motif' => 'attribution',
                'commentaire' => $commentaire,
            ]);

            return $materiel;
        });
    }

    public function retournerEnStock(Materiel $materiel, int $emplacementId, string $nouveauStatut, ?string $commentaire, User $acteur): Materiel
    {
        if (! in_array($nouveauStatut, ['disponible', 'reparation', 'reforme'], true)) {
            throw ValidationException::withMessages(['statut' => 'Statut de retour invalide.']);
        }

        $emplacement = Emplacement::where('id', $emplacementId)->where('actif', true)->first();
        if (! $emplacement) {
            throw ValidationException::withMessages(['emplacement_id' => "L'emplacement de retour n'existe pas ou est inactif."]);
        }

        return DB::transaction(function () use ($materiel, $emplacement, $nouveauStatut, $commentaire, $acteur) {
            $materiel = Materiel::whereKey($materiel->id)->lockForUpdate()->firstOrFail();

            $ancienUtilisateurId = $materiel->utilisateur_id;
            $ancienStatut = $materiel->statut;
            $ancienEmplacementId = $materiel->emplacement_id;

            $materiel->update([
                'emplacement_id' => $emplacement->id,
                'statut' => $nouveauStatut,
                'utilisateur_id' => null,
                'version' => $materiel->version + 1,
            ]);

            Mouvement::create([
                'materiel_id' => $materiel->id,
                'ancien_emplacement_id' => $ancienEmplacementId,
                'nouvel_emplacement_id' => $emplacement->id,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => $nouveauStatut,
                'ancien_utilisateur_id' => $ancienUtilisateurId,
                'nouvel_utilisateur_id' => null,
                'user_id' => $acteur->id,
                'motif' => 'retour_en_stock',
                'commentaire' => $commentaire,
            ]);

            return $materiel;
        });
    }

    public function mettreEnReparation(Materiel $materiel, ?string $commentaire, User $acteur): Materiel
    {
        return DB::transaction(function () use ($materiel, $commentaire, $acteur) {
            $materiel = Materiel::whereKey($materiel->id)->lockForUpdate()->firstOrFail();

            $ancienStatut = $materiel->statut;

            $materiel->update([
                'statut' => 'reparation',
                'version' => $materiel->version + 1,
            ]);

            Mouvement::create([
                'materiel_id' => $materiel->id,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => 'reparation',
                'user_id' => $acteur->id,
                'motif' => 'mise_en_reparation',
                'commentaire' => $commentaire,
            ]);

            return $materiel;
        });
    }

    public function rechercher(array $criteres)
    {
        $query = Materiel::query()->with(['emplacement.site', 'utilisateur']);

        if (! empty($criteres['terme'])) {
            $terme = $criteres['terme'];
            $query->where(function ($q) use ($terme) {
                $q->where('reference', 'like', "%{$terme}%")
                    ->orWhere('numero_serie', 'like', "%{$terme}%")
                    ->orWhere('type_materiel', 'like', "%{$terme}%")
                    ->orWhere('marque', 'like', "%{$terme}%")
                    ->orWhere('modele', 'like', "%{$terme}%")
                    ->orWhere('commentaire', 'like', "%{$terme}%");
            });
        }

        if (! empty($criteres['site_id'])) {
            $query->whereHas('emplacement', fn ($q) => $q->where('site_id', $criteres['site_id']));
        }

        if (! empty($criteres['condition_stock'])) {
            $query->where('condition_stock', $criteres['condition_stock']);
        }

        if (! empty($criteres['statut'])) {
            $query->where('statut', $criteres['statut']);
        }

        return $query->orderBy('reference')->paginate(20)->withQueryString();
    }
}
