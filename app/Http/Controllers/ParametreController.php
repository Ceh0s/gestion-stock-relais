<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParametreController extends Controller
{
    public function edit(): View
    {
        return view('parametres.edit', ['seuilStockFaible' => Parametre::seuilStockFaible()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'seuil_stock_faible' => ['required', 'integer', 'min:0', 'max:1000'],
        ], [], ['seuil_stock_faible' => 'seuil de stock faible']);

        Parametre::definir(Parametre::SEUIL_STOCK_FAIBLE, (int) $data['seuil_stock_faible']);

        return redirect()->route('parametres.edit')->with('status', 'Paramètres enregistrés.');
    }
}
