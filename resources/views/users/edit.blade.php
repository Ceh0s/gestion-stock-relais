<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifier {{ $user->nomComplet() }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-6">
                    @csrf
                    @method('put')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="prenom" value="Prenom" />
                            <x-text-input id="prenom" name="prenom" class="mt-1 block w-full" value="{{ old('prenom', $user->prenom) }}" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('prenom')" />
                        </div>
                        <div>
                            <x-input-label for="nom" value="Nom" />
                            <x-text-input id="nom" name="nom" class="mt-1 block w-full" value="{{ old('nom', $user->nom) }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('nom')" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $user->email) }}" required />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="telephone" value="Telephone" />
                            <x-text-input id="telephone" name="telephone" class="mt-1 block w-full" value="{{ old('telephone', $user->telephone) }}" />
                        </div>
                        <div>
                            <x-input-label for="service" value="Service" />
                            <x-text-input id="service" name="service" class="mt-1 block w-full" value="{{ old('service', $user->service) }}" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="role" value="Role" />
                        <select id="role" name="role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('role')" />
                    </div>
                    <div>
                        <x-input-label for="password" value="Nouveau mot de passe (laisser vide pour ne pas changer)" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('password')" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="actif" name="actif" value="1" @checked(old('actif', $user->actif)) class="rounded border-gray-300">
                        <x-input-label for="actif" value="Compte actif" />
                    </div>
                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
                        <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:underline">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
