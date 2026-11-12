<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Utilisateurs') }}</h2>
            <a href="{{ route('users.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                Ajouter un utilisateur
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-md p-4 mb-6">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rôle</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actif</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium">{{ $user->nomComplet() }}</td>
                                <td class="px-4 py-2 text-sm">{{ $user->email }}</td>
                                <td class="px-4 py-2 text-sm">{{ $user->service ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm">{{ \App\Models\User::libelleRole($user->role) }}</td>
                                <td class="px-4 py-2 text-sm">{{ $user->actif ? 'Oui' : 'Non' }}</td>
                                <td class="px-4 py-2 text-sm text-right">
                                    <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 hover:underline">Modifier</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
