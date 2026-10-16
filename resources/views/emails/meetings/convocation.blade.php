<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Convocation à une réunion</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .header { background-color: #795FFC; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0 0 8px 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 0; opacity: 0.9; font-size: 14px; }
        .content { padding: 24px; line-height: 1.6; }
        .info-grid { background: #f1f5f9; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .info-row { display: flex; margin-bottom: 8px; font-size: 14px; }
        .info-label { font-weight: 600; width: 120px; color: #475569; }
        .info-value { font-weight: 500; color: #0f172a; }
        .agenda-box { border-left: 4px solid #795FFC; background: #faf5ff; padding: 16px; border-radius: 0 8px 8px 0; margin: 16px 0; font-size: 14px; }
        .badge-notice { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px; border-radius: 8px; font-size: 13px; margin-top: 16px; }
        .footer { padding: 16px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <p>{{ \App\Models\CompanySetting::get('company_name', 'Wakati') }} — Système de Gestion Interne</p>
            <h1>Convocation Officielle à une Réunion</h1>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $participant->name }}</strong>,</p>
            <p>Vous êtes formellement convoqué(e) à participer à la réunion suivante :</p>

            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Réunion :</span>
                    <span class="info-value">{{ $meeting->title }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date :</span>
                    <span class="info-value">{{ \Carbon\Carbon::parse($meeting->date)->translatedFormat('l d F Y') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Heure :</span>
                    <span class="info-value">{{ \Carbon\Carbon::parse($meeting->time)->format('H:i') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Lieu :</span>
                    <span class="info-value">{{ $meeting->location }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Organisateur :</span>
                    <span class="info-value">{{ $meeting->redactor->name ?? 'Direction / DRH' }}</span>
                </div>
            </div>

            <div class="agenda-box">
                <strong>Ordre du jour & Rappels :</strong><br>
                {!! nl2br(e($meeting->agenda)) !!}
            </div>

            <div class="badge-notice">
                📌 <strong>Émargement le jour J :</strong><br>
                Le jour de la réunion, un bouton interactif <strong>« Marquer ma présence »</strong> sera actif sur votre tableau de bord Wakati pour valider votre présence en un clic. Vous pourrez également badger via votre carte RFID ou le QR code de l'émargement en salle.
            </div>

            <p style="margin-top: 24px; font-size: 13px; color: #64748b;">
                Merci de veiller au respect de la ponctualité. En cas d'empêchement majeur, veuillez informer votre responsable ou le service RH sans délai.
            </p>
        </div>
        <div class="footer">
            Cet e-mail automatique est émis par le module Réunions de Wakati Mono-Entreprise.
        </div>
    </div>
</body>
</html>
