<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['nom', 'prenom', 'email', 'telephone', 'service', 'password', 'role', 'actif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    public function estAdministrateur(): bool
    {
        return $this->role === 'administrateur';
    }

    public function peutGerer(): bool
    {
        return in_array($this->role, ['administrateur', 'gestionnaire_stock']);
    }

    public function materielsAffectes(): HasMany
    {
        return $this->hasMany(Materiel::class, 'utilisateur_id');
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(Mouvement::class);
    }
}
