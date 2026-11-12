<?php

namespace App\Http\Controllers;

use App\Imports\MaterielsImport;
use App\Models\Import;
use App\Models\JournalExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportController extends Controller
{
    public function create(Request $request): View
    {
        return view('imports.create', [
            'derniersImports' => Import::with('utilisateur')->latest()->latest('id')->take(10)->get(),
            // Journal des exports (Algorithme §12), consultable par les administrateurs.
            'derniersExports' => $request->user()->estAdministrateur()
                ? JournalExport::with('utilisateur')->latest()->latest('id')->take(10)->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $fichier = $request->file('fichier');

        // Algorithme §10.2 : l'import est enregistré dans le journal avant d'être traité.
        $enregistrement = Import::create([
            'nom_fichier' => $fichier->getClientOriginalName(),
            'utilisateur_id' => $request->user()->id,
            'statut' => 'en_attente',
        ]);

        $import = new MaterielsImport($request->user(), $fichier->getClientOriginalName());

        try {
            Excel::import($import, $fichier);
        } catch (Throwable $e) {
            // Algorithme §17 : le détail technique va dans les journaux, jamais à l'écran.
            Log::error('Import Excel illisible', ['import_id' => $enregistrement->id, 'exception' => $e]);
            $import->erreurs = ["Le fichier n'a pas pu être lu. Vérifiez qu'il s'agit d'un fichier Excel ou CSV valide."];
        }

        $enregistrement->update([
            'nombre_lignes' => $import->nombreLignes,
            'nombre_importees' => $import->nombreImportees,
            'nombre_erreurs' => count($import->erreurs),
            'statut' => $import->erreurs ? 'rejete' : 'termine',
            'rapport' => $import->erreurs ?: null,
        ]);

        // Résumé demandé par l'Algorithme §10.2 : lignes lues, importées, erreurs, doublons.
        if ($import->erreurs) {
            $resume = 'Import rejeté : '.count($import->erreurs).' erreur(s) détectée(s)';
            $resume .= " sur {$import->nombreLignes} ligne(s) lue(s)";
            $resume .= $import->nombreDoublons ? ", dont {$import->nombreDoublons} doublon(s)." : '.';
            $resume .= ' Aucun matériel importé.';

            return back()->withErrors(['fichier' => $resume])->with('erreursImport', $import->erreurs);
        }

        return redirect()->route('imports.create')
            ->with('status', "{$import->nombreImportees} matériel(s) importé(s) avec succès sur {$import->nombreLignes} ligne(s) lue(s).");
    }
}
