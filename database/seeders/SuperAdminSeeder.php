<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Compte Super-Admin plateforme
        User::updateOrCreate(
            ['email' => 'superadmin@wakati.test'],
            [
                'name' => 'Super Administrateur',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'role' => User::ROLE_SUPER_ADMIN,
                'company_id' => null, // Pas d'entreprise attachée : vision globale
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Assurer un abonnement actif et un paiement pour la compagnie pilote
        $pilotCompany = Company::first();
        $proPlan = Plan::where('slug', 'business-pro')->first();

        if ($pilotCompany && $proPlan) {
            $subscription = Subscription::firstOrCreate(
                [
                    'company_id' => $pilotCompany->id,
                    'status' => Subscription::STATUS_ACTIVE,
                ],
                [
                    'plan_id' => $proPlan->id,
                    'start_date' => now()->subDays(10),
                    'end_date' => now()->addDays(20),
                    'is_trial' => false,
                    'price_paid' => $proPlan->price,
                    'currency' => $proPlan->currency,
                    'notes' => 'Abonnement initial Entreprise Pilote',
                ]
            );

            Payment::firstOrCreate(
                [
                    'transaction_reference' => 'PAY-PILOTE-001',
                ],
                [
                    'company_id' => $pilotCompany->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $proPlan->price,
                    'currency' => $proPlan->currency,
                    'payment_method' => 'MTN Mobile Money',
                    'status' => Payment::STATUS_COMPLETED,
                    'payment_date' => now()->subDays(10),
                    'notes' => 'Paiement initial forfait Business Pro',
                ]
            );

            $pilotCompany->recordHistory(
                'initiation',
                'Création de l\'entreprise pilote et activation du forfait Business Pro.'
            );
        }
    }
}
