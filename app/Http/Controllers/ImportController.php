<?php

namespace App\Http\Controllers;

use App\Imports\MaterielsImport;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function create(): View
    {
        $derniersImports = Import::with('utilisateur')->latest()->take(10)->get();

        return view('imports.create', compact('derniersImports'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $fichier = $request->file('fichier');
        $import = new MaterielsImport;

        Excel::import($import, $fichier);

        $enregistrement = Import::create([
            'nom_fichier' => $fichier->getClientOriginalName(),
            'utilisateur_id' => $request->user()->id,
            'nombre_lignes' => $import->nombreLignes,
            'nombre_importees' => $import->nombreImportees,
            'nombre_erreurs' => count($import->erreurs),
            'statut' => $import->erreurs ? 'rejete' : 'termine',
            'rapport' => $import->erreurs ?: null,
        ]);

        if ($import->erreurs) {
            return back()->withErrors(['fichier' => 'Import rejete : '.count($import->erreurs).' erreur(s) detectee(s).'])
                ->with('erreursImport', $import->erreurs);
        }

        return redirect()->route('imports.create')->with('status', "{$import->nombreImportees} materiel(s) importe(s) avec succes.");
    }
}
