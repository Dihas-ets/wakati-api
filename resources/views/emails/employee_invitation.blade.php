<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation à rejoindre Wakati</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 32px 16px;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(121, 95, 252, 0.08);
        }
        .header {
            background: linear-gradient(135deg, #795FFC 0%, #5839EE 100%);
            color: #ffffff;
            padding: 36px 32px;
            text-align: center;
        }
        .logo-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 9999px;
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .header p {
            margin: 0;
            opacity: 0.92;
            font-size: 14px;
            font-weight: 400;
        }
        .content {
            padding: 36px 32px;
            line-height: 1.65;
            font-size: 15px;
            color: #334155;
        }
        .greeting {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .highlight-box {
            background: #f8f6ff;
            border: 1px solid #e9d5ff;
            border-left: 5px solid #795FFC;
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 14px;
        }
        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .detail-label {
            color: #64748b;
            font-weight: 500;
        }
        .detail-value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0 24px 0;
        }
        .btn-action {
            display: inline-block;
            background: #795FFC;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            padding: 16px 36px;
            border-radius: 14px;
            box-shadow: 0 10px 20px -3px rgba(121, 95, 252, 0.35);
        }
        .validity-alert {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            color: #92400e;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 13px;
            margin: 20px 0;
        }
        .fallback-link {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            color: #64748b;
            word-break: break-all;
        }
        .fallback-link a {
            color: #795FFC;
            text-decoration: underline;
        }
        .footer {
            padding: 24px 32px;
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-badge">Wakati • Gestion du Temps & Collaborateurs</div>
            <h1>Bienvenue dans l'équipe !</h1>
            <p>Vous êtes invité(e) à rejoindre notre espace collaborateur sur Wakati</p>
        </div>

        <div class="content">
            <div class="greeting">
                Bonjour {{ $invitation->first_name ? $invitation->first_name . ' ' . $invitation->last_name : 'Cher(e) collaborateur(rice)' }},
            </div>

            <p>
                La direction des ressources humaines vous invite à finaliser la création de votre compte collaborateur sur la plateforme <strong>Wakati</strong>.
            </p>

            <div class="highlight-box">
                @if($invitation->department)
                <div class="detail-row">
                    <span class="detail-label">Département</span>
                    <span class="detail-value">{{ $invitation->department->name }}</span>
                </div>
                @endif
                @if($invitation->position)
                <div class="detail-row">
                    <span class="detail-label">Poste attribué</span>
                    <span class="detail-value">{{ $invitation->position->title }}</span>
                </div>
                @endif
                <div class="detail-row">
                    <span class="detail-label">Email d'inscription</span>
                    <span class="detail-value">{{ $invitation->email }}</span>
                </div>
            </div>

            <div class="validity-alert">
                ⏱️ <strong>Durée de validité :</strong> Ce lien d'invitation sécurisé est personnel et valable pendant <strong>48 heures</strong>.
            </div>

            <div class="btn-container">
                <a href="{{ $activationUrl }}" class="btn-action" target="_blank" rel="noopener noreferrer">
                    Finaliser mon inscription →
                </a>
            </div>

            <div class="fallback-link">
                Si le bouton ne s'affiche pas correctement, vous pouvez copier et ouvrir ce lien dans votre navigateur :<br>
                <a href="{{ $activationUrl }}">{{ $activationUrl }}</a>
            </div>
        </div>

        <div class="footer">
            Cet e-mail est généré automatiquement par la plateforme Wakati.<br>
            Si vous n'êtes pas le destinataire attendu de ce message, merci de l'ignorer.
        </div>
    </div>
</body>
</html>
