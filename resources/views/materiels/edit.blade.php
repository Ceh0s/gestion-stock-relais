<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifier {{ $materiel->reference }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('materiels.update', $materiel) }}" class="space-y-6">
                    @csrf
                    @method('put')
                    <input type="hidden" name="version" value="{{ $materiel->version }}">
                    <x-input-error :messages="$errors->get('version')" />

                    @foreach ($suggestions as $champ => $valeurs)
                        <datalist id="suggestions-{{ $champ }}">
                            @foreach ($valeurs as $valeur)
                                <option value="{{ $valeur }}"></option>
                            @endforeach
                        </datalist>
                    @endforeach

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Numéro d'inventaire" />
                            <x-text-input class="mt-1 block w-full bg-gray-100" value="{{ $materiel->reference }}" disabled />
                        </div>
                        <div>
                            <x-input-label for="type_materiel" value="Type de matériel" />
                            <x-text-input id="type_materiel" name="type_materiel" list="suggestions-type_materiel" autocomplete="off" class="mt-1 block w-full" value="{{ old('type_materiel', $materiel->type_materiel) }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('type_materiel')" />
                        </div>
                        <div>
                            <x-input-label for="marque" value="Marque" />
                            <x-text-input id="marque" name="marque" list="suggestions-marque" autocomplete="off" class="mt-1 block w-full" value="{{ old('marque', $materiel->marque) }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('marque')" />
                        </div>
                        <div>
                            <x-input-label for="modele" value="Modèle" />
                            <x-text-input id="modele" name="modele" list="suggestions-modele" autocomplete="off" class="mt-1 block w-full" value="{{ old('modele', $materiel->modele) }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('modele')" />
                        </div>
                        <div>
                            <x-input-label for="numero_serie" value="Numéro de série" />
                            <x-text-input id="numero_serie" name="numero_serie" class="mt-1 block w-full" value="{{ old('numero_serie', $materiel->numero_serie) }}" />
                            <x-input-error class="mt-2" :messages="$errors->get('numero_serie')" />
                        </div>
                        <div>
                            <x-input-label for="reference_constructeur" value="Référence constructeur (facultatif)" />
                            <x-text-input id="reference_constructeur" name="reference_constructeur" class="mt-1 block w-full" value="{{ old('reference_constructeur', $materiel->reference_constructeur) }}" placeholder="DL-LAT-5420" />
                            <x-input-error class="mt-2" :messages="$errors->get('reference_constructeur')" />
                        </div>
                        <div>
                            <x-input-label for="condition_stock" value="Type de stock" />
                            <select id="condition_stock" name="condition_stock" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                @foreach ($conditions as $c)
                                    <option value="{{ $c }}" @selected(old('condition_stock', $materiel->condition_stock) === $c)>{{ \App\Models\Materiel::libelleCondition($c) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="date_acquisition" value="Date d'acquisition" />
                            <x-text-input id="date_acquisition" name="date_acquisition" type="date" class="mt-1 block w-full" value="{{ old('date_acquisition', optional($materiel->date_acquisition)->toDateString()) }}" />
                        </div>
                        <div>
                            <x-input-label for="date_fin_garantie" value="Fin de garantie" />
                            <x-text-input id="date_fin_garantie" name="date_fin_garantie" type="date" class="mt-1 block w-full" value="{{ old('date_fin_garantie', optional($materiel->date_fin_garantie)->toDateString()) }}" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="commentaire" value="Commentaire" />
                        <textarea id="commentaire" name="commentaire" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('commentaire', $materiel->commentaire) }}</textarea>
                    </div>

                    <p class="text-sm text-gray-500">Pour changer l'emplacement, le statut ou l'utilisateur affecté, utilisez les actions dédiées (déplacer / attribuer / retour en stock) depuis la fiche du matériel.</p>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
                        <a href="{{ route('materiels.show', $materiel) }}" class="text-sm text-gray-500 hover:underline">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
