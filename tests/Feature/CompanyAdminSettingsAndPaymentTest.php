<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAdminSettingsAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_update_branding_and_settings(): void
    {
        $company = Company::create([
            'name' => 'Wakati Corp',
            'slug' => 'wakati-corp',
            'logo' => null,
            'secondary_logo' => null,
            'primary_color' => '#795FFC',
            'secondary_color' => '#0F172A',
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_COMPANY_ADMIN,
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson('/api/company-settings', [
            'company_name' => 'Wakati Corporation SARL',
            'primary_logo' => 'data:image/png;base64,primarylogo123',
            'secondary_logo' => 'data:image/png;base64,secondarylogo456',
            'primary_color' => '#3B82F6',
            'secondary_color' => '#1E293B',
            'late_tolerance_minutes' => 15,
        ]);

        $response->assertOk();
        $this->assertEquals('Wakati Corporation SARL', $company->fresh()->name);
        $this->assertEquals('data:image/png;base64,primarylogo123', $company->fresh()->logo);
        $this->assertEquals('data:image/png;base64,secondarylogo456', $company->fresh()->secondary_logo);
        $this->assertEquals('#3B82F6', $company->fresh()->primary_color);
        $this->assertEquals('#1E293B', $company->fresh()->secondary_color);
    }

    public function test_directeur_can_update_operational_settings_but_cannot_update_branding(): void
    {
        $company = Company::create([
            'name' => 'Original Name',
            'slug' => 'original-name',
            'logo' => 'data:image/png;base64,preservedprimary',
            'secondary_logo' => 'data:image/png;base64,preservedsecondary',
            'primary_color' => '#795FFC',
            'secondary_color' => '#0F172A',
        ]);

        $directeur = User::factory()->create([
            'role' => User::ROLE_DIRECTEUR,
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($directeur, 'sanctum')->putJson('/api/company-settings', [
            'company_name' => 'Directeur Updated Name',
            'primary_logo' => 'data:image/png;base64,hackedlogo',
            'secondary_logo' => 'data:image/png;base64,hackedsecondary',
            'primary_color' => '#FF0000',
            'secondary_color' => '#00FF00',
            'late_tolerance_minutes' => 20,
        ]);

        $response->assertOk();
        // The company name and operational settings can be updated
        $this->assertEquals('Directeur Updated Name', $company->fresh()->name);
        // BUT the branding (logos & colors) MUST remain unchanged
        $this->assertEquals('data:image/png;base64,preservedprimary', $company->fresh()->logo);
        $this->assertEquals('data:image/png;base64,preservedsecondary', $company->fresh()->secondary_logo);
        $this->assertEquals('#795FFC', $company->fresh()->primary_color);
        $this->assertEquals('#0F172A', $company->fresh()->secondary_color);
    }

    public function test_company_admin_can_pay_subscription_with_fedapay_and_fexpay(): void
    {
        $company = Company::create([
            'name' => 'Test Company Pay',
            'slug' => 'test-company-pay',
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_COMPANY_ADMIN,
            'company_id' => $company->id,
        ]);

        $plan = Plan::create([
            'name' => 'Pack Entreprise',
            'slug' => 'pack-entreprise',
            'price' => 50000,
            'currency' => 'FCFA',
            'duration' => 1,
            'duration_unit' => 'month',
            'duration_type' => 'recurring',
            'is_active' => true,
        ]);

        // Test paiement FedaPay
        $resFeda = $this->actingAs($admin, 'sanctum')->postJson('/api/company/subscription/pay', [
            'plan_id' => $plan->id,
            'gateway' => 'fedapay',
            'phone' => '+229 97 00 00 01',
            'notes' => 'Paiement test FedaPay MoMo',
        ]);

        $resFeda->assertOk();
        $this->assertTrue($company->fresh()->hasValidAccess());
        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'amount' => 50000,
            'status' => 'completed',
        ]);

        // Test paiement FexPay
        $resFex = $this->actingAs($admin, 'sanctum')->postJson('/api/company/subscription/pay', [
            'plan_id' => $plan->id,
            'gateway' => 'fexpay',
            'phone' => '+229 95 00 00 02',
            'notes' => 'Paiement test FexPay',
        ]);

        $resFex->assertOk();
        $this->assertTrue($company->fresh()->hasValidAccess());
    }

    public function test_super_admin_can_view_and_update_platform_settings(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $getRes = $this->actingAs($superAdmin, 'sanctum')->getJson('/api/super-admin/settings');
        $getRes->assertOk();
        $getRes->assertJsonStructure([
            'fedapay_mode',
            'fedapay_public_key',
            'fedapay_secret_key',
            'fexpay_mode',
            'fexpay_api_key',
            'fexpay_token',
            'default_trial_days',
            'default_currency',
            'support_email',
            'platform_name',
            'allow_self_registration',
        ]);

        $putRes = $this->actingAs($superAdmin, 'sanctum')->putJson('/api/super-admin/settings', [
            'fedapay_mode' => 'live',
            'fedapay_public_key' => 'pk_live_fedapay_123',
            'fedapay_secret_key' => 'sk_live_fedapay_456',
            'fexpay_mode' => 'live',
            'fexpay_api_key' => 'fex_api_789',
            'fexpay_token' => 'fex_tok_abc',
            'default_trial_days' => 30,
            'default_currency' => 'FCFA',
            'platform_name' => 'Wakati Enterprise SaaS',
        ]);

        $putRes->assertOk();
        $putRes->assertJsonFragment([
            'fedapay_mode' => 'live',
            'fedapay_public_key' => 'pk_live_fedapay_123',
            'fexpay_api_key' => 'fex_api_789',
            'default_trial_days' => 30,
            'platform_name' => 'Wakati Enterprise SaaS',
        ]);
    }
}
