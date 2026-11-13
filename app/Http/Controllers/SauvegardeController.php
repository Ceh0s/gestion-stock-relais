<?php

namespace App\Http\Controllers;

use App\Models\Sauvegarde;
use App\Services\SauvegardeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sauvegardes de la base (Algorithme §16), réservées aux administrateurs.
 */
class SauvegardeController extends Controller
{
    public function index(): View
    {
        return view('sauvegardes.index', [
            'sauvegardes' => Sauvegarde::with('utilisateur')->latest()->latest('id')->take(30)->get(),
            'conservationJours' => config('sauvegarde.conservation_jours'),
            'copieExterne' => (bool) config('sauvegarde.disque_externe'),
        ]);
    }

    public function store(Request $request, SauvegardeService $service): RedirectResponse
    {
        $sauvegarde = $service->creer($request->user());

        if (! $sauvegarde->estReussie()) {
            return redirect()->route('sauvegardes.index')->withErrors(['sauvegarde' => $sauvegarde->message]);
        }

        return redirect()->route('sauvegardes.index')
            ->with('status', "Sauvegarde {$sauvegarde->nom_fichier} créée ({$sauvegarde->tailleLisible()}).");
    }

    public function download(Sauvegarde $sauvegarde): StreamedResponse|RedirectResponse
    {
        if (! $sauvegarde->estReussie() || ! Storage::disk('local')->exists($sauvegarde->chemin())) {
            return redirect()->route('sauvegardes.index')
                ->withErrors(['sauvegarde' => "Le fichier {$sauvegarde->nom_fichier} n'est pas disponible."]);
        }

        return Storage::disk('local')->download($sauvegarde->chemin(), $sauvegarde->nom_fichier);
    }
}
