<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Import Excel / CSV') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-md p-4">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-md p-4">
                    <p class="font-medium mb-2">{{ $errors->first() }}</p>
                    @if (session('erreursImport'))
                        <ul class="list-disc list-inside space-y-1">
                            @foreach (session('erreursImport') as $erreur)
                                <li>{{ $erreur }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <p class="text-sm text-gray-600 mb-4">
                    Le fichier doit contenir au minimum les colonnes : <code>reference, type_materiel, marque, modele, condition_stock, statut, site, emplacement</code>.
                    Colonnes facultatives : <code>numero_serie, date_acquisition, date_fin_garantie, commentaire</code>.
                    En cas d'erreur sur une seule ligne, l'import complet est rejete (mode strict).
                </p>

                <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="file" name="fichier" accept=".xlsx,.xls,.csv" required class="block w-full text-sm">
                    <x-input-error :messages="$errors->get('fichier')" />
                    <x-primary-button>{{ __('Importer') }}</x-primary-button>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Derniers imports</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="py-2 pr-4">Fichier</th>
                            <th class="py-2 pr-4">Lignes</th>
                            <th class="py-2 pr-4">Importees</th>
                            <th class="py-2 pr-4">Erreurs</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 pr-4">Par</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($derniersImports as $import)
                            <tr>
                                <td class="py-2 pr-4">{{ $import->nom_fichier }}</td>
                                <td class="py-2 pr-4">{{ $import->nombre_lignes }}</td>
                                <td class="py-2 pr-4">{{ $import->nombre_importees }}</td>
                                <td class="py-2 pr-4">{{ $import->nombre_erreurs }}</td>
                                <td class="py-2 pr-4">{{ $import->statut }}</td>
                                <td class="py-2 pr-4">{{ $import->utilisateur?->nomComplet() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-center text-gray-500">Aucun import.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
