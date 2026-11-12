<?php

namespace Database\Seeders;

use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'prenom' => 'Alice', 'nom' => 'Martin', 'email' => 'admin@gestion-stock.local',
            'service' => 'Direction', 'role' => 'administrateur', 'password' => Hash::make('password'),
        ]);
        User::create([
            'prenom' => 'Bruno', 'nom' => 'Lefèvre', 'email' => 'gestionnaire@gestion-stock.local',
            'service' => 'Informatique', 'role' => 'gestionnaire_stock', 'password' => Hash::make('password'),
        ]);
        User::create([
            'prenom' => 'Chloé', 'nom' => 'Dubois', 'email' => 'technicien@gestion-stock.local',
            'service' => 'Informatique', 'role' => 'technicien', 'password' => Hash::make('password'),
        ]);
        User::create([
            'prenom' => 'David', 'nom' => 'Roux', 'email' => 'consultation@gestion-stock.local',
            'service' => 'Comptabilité', 'role' => 'consultation', 'password' => Hash::make('password'),
        ]);
        User::create([
            'prenom' => 'Jean', 'nom' => 'Dupont', 'email' => 'jean.dupont@gestion-stock.local',
            'service' => 'Comptabilité', 'role' => 'consultation', 'password' => Hash::make('password'),
        ]);
        $auditeur = User::create([
            'prenom' => 'Élise', 'nom' => 'Perrin', 'email' => 'auditeur@gestion-stock.local',
            'service' => 'Direction', 'role' => 'auditeur', 'password' => Hash::make('password'),
        ]);

        $site1 = Site::create(['nom' => 'Site 1', 'adresse' => 'Bâtiment administratif', 'responsable' => 'Alice Martin']);
        $site2 = Site::create(['nom' => 'Site 2', 'adresse' => 'Entrepôt logistique', 'responsable' => 'Bruno Lefèvre']);
        $site3 = Site::create(['nom' => 'Site 3', 'adresse' => 'Agence commerciale', 'responsable' => 'Chloé Dubois']);

        $e1 = Emplacement::create(['site_id' => $site1->id, 'nom' => 'Réserve informatique - Armoire A - Étagère 2']);
        $e2 = Emplacement::create(['site_id' => $site1->id, 'nom' => 'Bureau du responsable']);
        $e3 = Emplacement::create(['site_id' => $site2->id, 'nom' => 'Réserve - Armoire C']);
        $e4 = Emplacement::create(['site_id' => $site2->id, 'nom' => 'Atelier de réparation']);
        $e5 = Emplacement::create(['site_id' => $site3->id, 'nom' => 'Réserve informatique']);

        Materiel::create([
            'reference' => 'PC-000245', 'type_materiel' => 'Ordinateur portable', 'marque' => 'Dell', 'modele' => 'Latitude 5420',
            'numero_serie' => 'ABC123456', 'condition_stock' => 'neuf', 'statut' => 'disponible',
            'emplacement_id' => $e1->id, 'date_acquisition' => '2025-03-15', 'date_fin_garantie' => '2028-03-15',
            'commentaire' => 'Chargeur manquant',
        ]);
        Materiel::create([
            'reference' => 'PC-000312', 'type_materiel' => 'Ordinateur portable', 'marque' => 'Dell', 'modele' => 'Latitude 5420',
            'numero_serie' => 'ABC123457', 'condition_stock' => 'occasion', 'statut' => 'attribue',
            'emplacement_id' => $e3->id, 'utilisateur_id' => $auditeur->id,
        ]);
        Materiel::create([
            'reference' => 'PC-000418', 'type_materiel' => 'Ordinateur portable', 'marque' => 'Dell', 'modele' => 'Latitude 5420',
            'numero_serie' => 'ABC123458', 'condition_stock' => 'retour', 'statut' => 'reparation',
            'emplacement_id' => $e4->id,
        ]);
        Materiel::create([
            'reference' => 'MON-000118', 'type_materiel' => 'Écran', 'marque' => 'HP', 'modele' => 'E243',
            'numero_serie' => 'DEF456', 'condition_stock' => 'occasion', 'statut' => 'disponible',
            'emplacement_id' => $e5->id,
        ]);
        Materiel::create([
            'reference' => 'TEL-000050', 'type_materiel' => 'Téléphone', 'marque' => 'Samsung', 'modele' => 'Galaxy A54',
            'condition_stock' => 'neuf', 'statut' => 'disponible', 'emplacement_id' => $e2->id,
        ]);
    }
}
