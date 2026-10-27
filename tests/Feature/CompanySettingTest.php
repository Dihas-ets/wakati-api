<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingDocxGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_or_directeur_can_view_and_update_company_name(): void
    {
        $directeur = User::factory()->create(['role' => 'directeur']);

        Sanctum::actingAs($directeur);

        // 1. Consultation initiale : doit renvoyer la valeur par défaut ou actuelle
        $response = $this->getJson('/api/company-settings');
        $response->assertStatus(200)
            ->assertJsonPath('company_name', 'Dihas Group');

        // 2. Modification du nom de l'entreprise
        $updateResponse = $this->putJson('/api/company-settings', [
            'company_name' => 'Acme Corporation International',
            'late_tolerance_minutes' => 15,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('company_name', 'Acme Corporation International')
            ->assertJsonPath('late_tolerance_minutes', 15);

        // 3. Vérification en base de données
        $this->assertEquals('Acme Corporation International', CompanySetting::get('company_name'));

        // 4. Vérification lors de la consultation suivante
        $getAgain = $this->getJson('/api/company-settings');
        $getAgain->assertStatus(200)
            ->assertJsonPath('company_name', 'Acme Corporation International');
    }

    public function test_simple_employee_cannot_update_company_settings(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        Sanctum::actingAs($employee);

        $response = $this->putJson('/api/company-settings', [
            'company_name' => 'Hacker Corp',
        ]);

        $response->assertStatus(403);
    }

    public function test_docx_generation_uses_customized_company_name(): void
    {
        CompanySetting::create([
            'key' => 'company_name',
            'value' => 'Wakati Tech Group',
        ]);

        $director = User::factory()->create(['role' => 'directeur']);
        $meeting = Meeting::create([
            'title' => 'Session Validation Nom Entreprise',
            'date' => Carbon::today()->toDateString(),
            'time' => '10:00',
            'location' => 'Salle Principale',
            'redactor_id' => $director->id,
            'agenda' => 'Ordre du jour test',
            'status' => 'ongoing',
        ]);

        $service = app(MeetingDocxGeneratorService::class);
        $docxPath = $service->generate($meeting);

        $this->assertTrue(File::exists($docxPath));

        // Vérifier dans le document généré que le nom dynamique a été injecté
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($docxPath) === true);
        $docXml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('WAKATI TECH GROUP', $docXml);
    }

    public function test_rh_or_directeur_can_update_mail_settings(): void
    {
        $directeur = User::factory()->create(['role' => 'directeur']);
        Sanctum::actingAs($directeur);

        $updateResponse = $this->putJson('/api/company-settings', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.gmail.com',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'wakati.admin@gmail.com',
            'mail_password' => 'secret-app-password',
            'mail_from_address' => 'noreply@monentreprise.com',
            'mail_from_name' => 'Wakati Notification',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('mail_mailer', 'smtp')
            ->assertJsonPath('mail_host', 'smtp.gmail.com')
            ->assertJsonPath('mail_port', 587)
            ->assertJsonPath('mail_encryption', 'tls')
            ->assertJsonPath('mail_username', 'wakati.admin@gmail.com')
            ->assertJsonPath('mail_password', 'secret-app-password')
            ->assertJsonPath('mail_from_address', 'noreply@monentreprise.com')
            ->assertJsonPath('mail_from_name', 'Wakati Notification');

        $this->assertEquals('smtp.gmail.com', CompanySetting::get('mail_host'));
        $this->assertEquals('wakati.admin@gmail.com', CompanySetting::get('mail_username'));
    }

    public function test_rh_can_test_email_sending_with_log_driver(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        Sanctum::actingAs($rh);

        $response = $this->postJson('/api/company-settings/test-email', [
            'test_email' => 'admin@monentreprise.com',
            'mail_mailer' => 'log',
            'mail_from_address' => 'noreply@monentreprise.com',
            'mail_from_name' => 'Wakati Test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Simple employee cannot test email settings
        $employee = User::factory()->create(['role' => 'employe']);
        Sanctum::actingAs($employee);

        $unauthorizedResponse = $this->postJson('/api/company-settings/test-email', [
            'test_email' => 'test@test.com',
        ]);
        $unauthorizedResponse->assertStatus(403);
    }

    public function test_rh_can_test_email_sending_with_smtp_and_tls_encryption(): void
    {
        Mail::fake();

        $rh = User::factory()->create(['role' => 'rh']);
        Sanctum::actingAs($rh);

        $response = $this->postJson('/api/company-settings/test-email', [
            'test_email' => 'admin@monentreprise.com',
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.gmail.com',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'test@gmail.com',
            'mail_password' => 'secret',
            'mail_from_address' => 'noreply@monentreprise.com',
            'mail_from_name' => 'Wakati Test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertTrue(config('mail.mailers.smtp.auto_tls'));
    }
}
