<?php

namespace App\Http\Controllers;

use App\Exports\MaterielsExport;
use App\Models\JournalExport;
use App\Models\Site;
use App\Services\MaterielService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function materiels(Request $request, MaterielService $materiels): BinaryFileResponse
    {
        $filtres = array_filter(
            $request->only(MaterielService::CRITERES_RECHERCHE),
            fn ($valeur) => $valeur !== null && $valeur !== ''
        );

        // Algorithme §12 : fichier nommé avec la date, et le site s'il est filtré
        // (ex. inventaire_site_1_2026-11-08.xlsx).
        $site = isset($filtres['site_id']) ? Site::find($filtres['site_id']) : null;
        $nomFichier = 'inventaire_'.($site ? Str::slug($site->nom, '_').'_' : '').now()->format('Y-m-d').'.xlsx';

        // Algorithme §12 : "Enregistrer l'export dans le journal".
        JournalExport::create([
            'utilisateur_id' => $request->user()->id,
            'nom_fichier' => $nomFichier,
            'filtres' => $filtres ?: null,
            'nombre_lignes' => $materiels->requeteRecherche($filtres)->count(),
        ]);

        return Excel::download(new MaterielsExport($filtres), $nomFichier);
    }
}
