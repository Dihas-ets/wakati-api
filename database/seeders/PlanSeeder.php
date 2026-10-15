<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Pack Découverte',
                'slug' => 'pack-decouverte',
                'description' => "Période d'essai gratuite complète pour tester le pointage et la gestion RH en conditions réelles.",
                'price' => 0,
                'currency' => 'FCFA',
                'duration' => 14,
                'duration_unit' => Plan::DURATION_UNIT_DAY,
                'duration_type' => Plan::DURATION_TYPE_TRIAL,
                'is_trial' => true,
                'trial_period_days' => 14,
                'features' => [
                    "Jusqu'à 10 employés",
                    "Pointage QR Code & Mobile",
                    "Suivi des présences et retards",
                    "Gestion des demandes de congés",
                    "Exports CSV et rapports basiques",
                    "Support standard",
                ],
                'max_employees' => 10,
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Starter PME',
                'slug' => 'starter-pme',
                'description' => "Pour les petites structures et startups cherchant à digitaliser le temps de travail simplement.",
                'price' => 25000,
                'currency' => 'FCFA',
                'duration' => 1,
                'duration_unit' => Plan::DURATION_UNIT_MONTH,
                'duration_type' => Plan::DURATION_TYPE_RECURRING,
                'is_trial' => false,
                'trial_period_days' => 7,
                'features' => [
                    "Jusqu'à 30 employés",
                    "Pointage par QR Code dynamique",
                    "Gestion des horaires et plannings",
                    "Calcul automatique des heures sup & retards",
                    "Exports paie (CSV, Excel)",
                    "Support par email 5j/7",
                ],
                'max_employees' => 30,
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Business Pro',
                'slug' => 'business-pro',
                'description' => "Idéal pour les entreprises établies : pointage RFID physique, multi-postes et contrôle avancé.",
                'price' => 65000,
                'currency' => 'FCFA',
                'duration' => 1,
                'duration_unit' => Plan::DURATION_UNIT_MONTH,
                'duration_type' => Plan::DURATION_TYPE_RECURRING,
                'is_trial' => false,
                'trial_period_days' => 14,
                'features' => [
                    "Jusqu'à 100 employés",
                    "Pointage RFID, Badge NFC & Borne hors-ligne",
                    "Géolocalisation & filtrage IP sécurisé",
                    "Workflow de validation des congés",
                    "Tableaux de bord RH & Comptabilité",
                    "Support prioritaire WhatsApp & Téléphone",
                ],
                'max_employees' => 100,
                'is_popular' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise Ultime',
                'slug' => 'enterprise-ultime',
                'description' => "La puissance maximale de Wakati pour les grandes organisations et structures multi-sites.",
                'price' => 150000,
                'currency' => 'FCFA',
                'duration' => 1,
                'duration_unit' => Plan::DURATION_UNIT_MONTH,
                'duration_type' => Plan::DURATION_TYPE_RECURRING,
                'is_trial' => false,
                'trial_period_days' => 30,
                'features' => [
                    "Employés illimités",
                    "Multi-sites & bornes autonomes illimitées",
                    "Synchronisation hors-ligne bidirectionnelle",
                    "Rapports personnalisés & exports comptables sur-mesure",
                    "Gestion des accès par rôles avancés",
                    "Account Manager dédié & SLA garanti 99.9%",
                ],
                'max_employees' => null,
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
