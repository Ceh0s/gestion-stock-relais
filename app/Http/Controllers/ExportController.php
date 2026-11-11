<?php

namespace App\Http\Controllers;

use App\Exports\MaterielsExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function materiels(Request $request): BinaryFileResponse
    {
        $filtres = $request->only(['site_id', 'statut', 'condition_stock']);
        $nomFichier = 'materiels_'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new MaterielsExport($filtres), $nomFichier);
    }
}
