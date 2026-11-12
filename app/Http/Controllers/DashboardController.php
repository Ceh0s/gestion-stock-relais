<?php

namespace App\Http\Controllers;

use App\Models\Materiel;
use App\Models\Mouvement;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total' => Materiel::count(),
            'disponible' => Materiel::where('statut', 'disponible')->count(),
            'neuf' => Materiel::where('condition_stock', 'neuf')->count(),
            'attribue' => Materiel::where('statut', 'attribue')->count(),
            'reparation' => Materiel::where('statut', 'reparation')->count(),
            'en_transit' => Materiel::where('statut', 'en_transit')->count(),
            'a_verifier' => Materiel::where('statut', 'a_verifier')->count(),
        ];

        $derniersMateriels = Materiel::with('emplacement.site')->latest()->take(5)->get();
        $derniersMouvements = Mouvement::with(['materiel', 'utilisateurConnecte'])->latest('created_at')->latest('id')->take(10)->get();
        $enReparationLongue = Materiel::where('statut', 'reparation')
            ->whereDate('updated_at', '<=', now()->subDays(14))
            ->with('emplacement.site')
            ->get();

        return view('dashboard', compact('stats', 'derniersMateriels', 'derniersMouvements', 'enReparationLongue'));
    }
}
