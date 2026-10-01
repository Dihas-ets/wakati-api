<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    public function run(): void
    {
        CompanySetting::updateOrCreate(
            ['key' => 'allowed_ips'],
            [
            'value' => ['127.0.0.1', '::1', '196.28.240.15'], // Met l'IP publique de ton Wi-Fi d'entreprise ici
            'description' => 'Liste des adresses IP autorisées pour le pointage',
            ]
        );

        CompanySetting::updateOrCreate(
            ['key' => 'late_tolerance_minutes'],
            [
            'value' => 10,
            'description' => 'Marge de tolérance en minutes pour les retards',
            ]
        );

        CompanySetting::updateOrCreate(
            ['key' => 'absence_delay_minutes'],
            [
            'value' => 60,
            'description' => 'Délai en minutes avant de valider une absence automatique',
            ]
        );

        CompanySetting::updateOrCreate(
            ['key' => 'timezone'],
            [
            'value' => 'Africa/Porto-Novo',
            'description' => "Fuseau horaire utilisé pour calculer retards, absences et jours ouvrés (identifiant IANA, ex: Africa/Porto-Novo)",
            ]
        );
    }
}
