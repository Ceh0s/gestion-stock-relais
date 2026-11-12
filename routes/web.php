<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmplacementController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MaterielController;
use App\Http\Controllers\ParametreController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Consultation libre pour tous les roles authentifies
    Route::get('/materiels', [MaterielController::class, 'index'])->name('materiels.index');

    Route::middleware('role:administrateur,gestionnaire_stock,auditeur')->group(function () {
        Route::get('/export/materiels', [ExportController::class, 'materiels'])->name('export.materiels');
    });

    Route::middleware('role:administrateur,gestionnaire_stock')->group(function () {
        // Routes statiques : doivent rester avant /materiels/{materiel} pour ne pas etre capturees par elle.
        Route::get('/materiels/creer', [MaterielController::class, 'create'])->name('materiels.create');
        Route::post('/materiels', [MaterielController::class, 'store'])->name('materiels.store');

        Route::resource('sites', SiteController::class)->except(['show', 'destroy']);
        Route::resource('emplacements', EmplacementController::class)->except(['show', 'destroy']);

        Route::get('/imports', [ImportController::class, 'create'])->name('imports.create');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    });

    Route::get('/materiels/{materiel}', [MaterielController::class, 'show'])->name('materiels.show');

    Route::middleware('role:administrateur,gestionnaire_stock')->group(function () {
        Route::get('/materiels/{materiel}/modifier', [MaterielController::class, 'edit'])->name('materiels.edit');
        Route::put('/materiels/{materiel}', [MaterielController::class, 'update'])->name('materiels.update');
        Route::post('/materiels/{materiel}/attribuer', [MaterielController::class, 'attribuer'])->name('materiels.attribuer');
    });

    Route::middleware('role:administrateur,gestionnaire_stock,technicien')->group(function () {
        Route::post('/materiels/{materiel}/deplacer', [MaterielController::class, 'deplacer'])->name('materiels.deplacer');
        Route::post('/materiels/{materiel}/retour-stock', [MaterielController::class, 'retournerEnStock'])->name('materiels.retourner');
        Route::post('/materiels/{materiel}/reparation', [MaterielController::class, 'mettreEnReparation'])->name('materiels.reparation');
    });

    Route::middleware('role:administrateur')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('/parametres', [ParametreController::class, 'edit'])->name('parametres.edit');
        Route::put('/parametres', [ParametreController::class, 'update'])->name('parametres.update');

        // Suppression définitive : uniquement ce qui n'a jamais servi (sinon on désactive).
        Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
        Route::delete('/emplacements/{emplacement}', [EmplacementController::class, 'destroy'])->name('emplacements.destroy');
        Route::delete('/materiels/{materiel}', [MaterielController::class, 'destroy'])->name('materiels.destroy');
    });
});

require __DIR__.'/auth.php';
