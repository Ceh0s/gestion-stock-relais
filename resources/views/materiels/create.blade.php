<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Ajouter un materiel') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('materiels.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="reference" value="Numero d'inventaire" />
                            <x-text-input id="reference" name="reference" class="mt-1 block w-full" value="{{ old('reference') }}" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('reference')" />
                        </div>
                        <div>
                            <x-input-label for="type_materiel" value="Type de materiel" />
                            <x-text-input id="type_materiel" name="type_materiel" class="mt-1 block w-full" value="{{ old('type_materiel') }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('type_materiel')" />
                        </div>
                        <div>
                            <x-input-label for="marque" value="Marque" />
                            <x-text-input id="marque" name="marque" class="mt-1 block w-full" value="{{ old('marque') }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('marque')" />
                        </div>
                        <div>
                            <x-input-label for="modele" value="Modele" />
                            <x-text-input id="modele" name="modele" class="mt-1 block w-full" value="{{ old('modele') }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('modele')" />
                        </div>
                        <div>
                            <x-input-label for="numero_serie" value="Numero de serie (optionnel)" />
                            <x-text-input id="numero_serie" name="numero_serie" class="mt-1 block w-full" value="{{ old('numero_serie') }}" />
                            <x-input-error class="mt-2" :messages="$errors->get('numero_serie')" />
                        </div>
                        <div>
                            <x-input-label for="condition_stock" value="Type de stock" />
                            <select id="condition_stock" name="condition_stock" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                @foreach ($conditions as $c)
                                    <option value="{{ $c }}" @selected(old('condition_stock') === $c)>{{ $c }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('condition_stock')" />
                        </div>
                        <div>
                            <x-input-label for="statut" value="Statut" />
                            <select id="statut" name="statut" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                @foreach ($statuts as $s)
                                    <option value="{{ $s }}" @selected(old('statut', 'disponible') === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('statut')" />
                        </div>
                        <div>
                            <x-input-label for="emplacement_id" value="Emplacement" />
                            <select id="emplacement_id" name="emplacement_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                <option value="">-- Selectionner --</option>
                                @foreach ($emplacements as $emplacement)
                                    <option value="{{ $emplacement->id }}" @selected((int) old('emplacement_id') === $emplacement->id)>{{ $emplacement->libelle() }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('emplacement_id')" />
                        </div>
                        <div>
                            <x-input-label for="utilisateur_id" value="Utilisateur attribue (si statut attribue)" />
                            <select id="utilisateur_id" name="utilisateur_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">-- Aucun --</option>
                                @foreach ($utilisateurs as $utilisateur)
                                    <option value="{{ $utilisateur->id }}" @selected((int) old('utilisateur_id') === $utilisateur->id)>{{ $utilisateur->nomComplet() }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('utilisateur_id')" />
                        </div>
                        <div>
                            <x-input-label for="date_acquisition" value="Date d'acquisition" />
                            <x-text-input id="date_acquisition" name="date_acquisition" type="date" class="mt-1 block w-full" value="{{ old('date_acquisition') }}" />
                        </div>
                        <div>
                            <x-input-label for="date_fin_garantie" value="Fin de garantie" />
                            <x-text-input id="date_fin_garantie" name="date_fin_garantie" type="date" class="mt-1 block w-full" value="{{ old('date_fin_garantie') }}" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="commentaire" value="Commentaire (facultatif)" />
                        <textarea id="commentaire" name="commentaire" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('commentaire') }}</textarea>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
                        <a href="{{ route('materiels.index') }}" class="text-sm text-gray-500 hover:underline">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
