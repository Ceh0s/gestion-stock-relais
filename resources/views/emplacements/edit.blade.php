<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifier l'emplacement {{ $emplacement->nom }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('emplacements.update', $emplacement) }}" class="space-y-6">
                    @csrf
                    @method('put')
                    <div>
                        <x-input-label for="site_id" value="Site" />
                        <select id="site_id" name="site_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected(old('site_id', $emplacement->site_id) == $site->id)>{{ $site->nom }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('site_id')" />
                    </div>
                    <div>
                        <x-input-label for="nom" value="Nom de l'emplacement" />
                        <x-text-input id="nom" name="nom" class="mt-1 block w-full" value="{{ old('nom', $emplacement->nom) }}" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nom')" />
                    </div>
                    <div>
                        <x-input-label for="description" value="Description" />
                        <x-text-input id="description" name="description" class="mt-1 block w-full" value="{{ old('description', $emplacement->description) }}" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="actif" name="actif" value="1" @checked(old('actif', $emplacement->actif)) class="rounded border-gray-300">
                        <x-input-label for="actif" value="Emplacement actif" />
                    </div>
                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
                        <a href="{{ route('emplacements.index') }}" class="text-sm text-gray-500 hover:underline">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
