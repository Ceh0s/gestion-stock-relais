<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Réglage de l'application (table "parametres") : une ligne par clé.
 */
#[Fillable(['cle', 'valeur'])]
class Parametre extends Model
{
    public const SEUIL_STOCK_FAIBLE = 'seuil_stock_faible';

    /** Valeur du réglage, ou $defaut si la ligne n'existe pas. */
    public static function valeur(string $cle, mixed $defaut = null): mixed
    {
        return static::where('cle', $cle)->value('valeur') ?? $defaut;
    }

    public static function definir(string $cle, mixed $valeur): void
    {
        static::updateOrCreate(['cle' => $cle], ['valeur' => (string) $valeur]);
    }

    /** Nombre d'exemplaires disponibles en dessous duquel un type de matériel est en alerte (0 = pas d'alerte). */
    public static function seuilStockFaible(): int
    {
        return (int) static::valeur(self::SEUIL_STOCK_FAIBLE, 2);
    }
}
