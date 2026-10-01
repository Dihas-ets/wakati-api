<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wakati API • Service Opérationnel</title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --brand-primary: #795FFC;
            --brand-primary-light: #9b87fd;
            --brand-primary-glow: rgba(121, 95, 252, 0.35);
            --brand-emerald: #10B981;
            --brand-emerald-glow: rgba(16, 185, 129, 0.4);
            --brand-cyan: #06B6D4;
            --bg-dark: #0a0d14;
            --bg-card: rgba(16, 21, 36, 0.75);
            --bg-card-hover: rgba(22, 29, 49, 0.9);
            --border-subtle: rgba(121, 95, 252, 0.15);
            --border-active: rgba(121, 95, 252, 0.4);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow-x: hidden;
            line-height: 1.5;
        }

        /* Effets de lueur et d'arrière-plan vibrant */
        .ambient-glow-1 {
            position: fixed;
            top: -150px;
            left: 50%;
            transform: translateX(-50%);
            width: 700px;
            height: 450px;
            background: radial-gradient(circle, rgba(121, 95, 252, 0.28) 0%, rgba(59, 130, 246, 0.15) 45%, transparent 70%);
            pointer-events: none;
            z-index: 0;
            filter: blur(40px);
        }

        .ambient-glow-2 {
            position: fixed;
            bottom: -100px;
            right: 5%;
            width: 500px;
            height: 350px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
            filter: blur(50px);
        }

        .content-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* Header */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 24px;
        }

        .logo-group {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #795FFC 0%, #4F46E5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 16px var(--brand-primary-glow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            flex-shrink: 0;
        }

        .logo-icon svg {
            width: 18px;
            height: 18px;
            color: #ffffff;
        }

        .logo-title {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(to right, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .badge-gateway {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 9999px;
            background: rgba(121, 95, 252, 0.15);
            color: var(--brand-primary-light);
            border: 1px solid rgba(121, 95, 252, 0.35);
            margin-left: 8px;
        }

        /* Pillule de statut en haut */
        .live-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.35);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.18);
        }

        .ping-beacon {
            position: relative;
            display: inline-block;
            width: 8px;
            height: 8px;
        }

        .ping-beacon .ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background-color: var(--brand-emerald);
            opacity: 0.75;
            animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        .ping-beacon .dot {
            position: relative;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--brand-emerald);
            box-shadow: 0 0 8px var(--brand-emerald);
        }

        @keyframes ping {
            75%, 100% {
                transform: scale(2.3);
                opacity: 0;
            }
        }

        /* Hero Card */
        .hero-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-subtle);
            border-radius: 24px;
            padding: 36px 32px;
            box-shadow: 0 20px 50px -15px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #795FFC, #10B981, transparent);
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            margin-bottom: 18px;
        }

        .status-chip svg {
            width: 14px;
            height: 14px;
        }

        .hero-title {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #ffffff 30%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            max-width: 620px;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        /* Grille des Métriques */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.07);
        }

        .metric-card {
            background: rgba(10, 14, 25, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 14px 16px;
            transition: all 0.2s ease;
        }

        .metric-card:hover {
            border-color: var(--border-active);
            transform: translateY(-2px);
            background: rgba(15, 20, 36, 0.7);
        }

        .metric-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .metric-header svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        .metric-value {
            font-size: 0.92rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .metric-value .dot-indicator {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: var(--brand-emerald);
            box-shadow: 0 0 6px var(--brand-emerald);
        }

        .metric-sub {
            font-size: 0.7rem;
            color: var(--text-dim);
            font-weight: normal;
            margin-left: 2px;
        }

        /* Boutons d'Action */
        .actions-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #795FFC 0%, #5d42ea 100%);
            color: #ffffff;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 18px var(--brand-primary-glow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(121, 95, 252, 0.5);
            background: linear-gradient(135deg, #876ffa 0%, #684bf5 100%);
        }

        .btn-primary svg {
            width: 14px;
            height: 14px;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.09);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-secondary svg {
            width: 14px;
            height: 14px;
            color: var(--text-muted);
        }

        /* Carte des Endpoints */
        .endpoints-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .endpoints-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-dim);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .endpoints-title svg {
            width: 14px;
            height: 14px;
            color: var(--brand-primary-light);
        }

        .endpoints-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 10px;
        }

        .endpoint-row {
            background: rgba(10, 14, 25, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            transition: all 0.15s ease;
        }

        .endpoint-row:hover {
            background: rgba(18, 24, 42, 0.7);
            border-color: rgba(121, 95, 252, 0.25);
        }

        .endpoint-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .method-pill {
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 6px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        .method-post {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .method-get {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .endpoint-path {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.75rem;
            color: #e2e8f0;
            font-weight: 500;
        }

        .endpoint-desc {
            font-size: 0.7rem;
            color: var(--text-dim);
            text-align: right;
            white-space: nowrap;
        }

        /* Footer */
        footer {
            padding: 24px 20px;
            text-align: center;
            font-size: 0.72rem;
            color: var(--text-dim);
            position: relative;
            z-index: 10;
        }

        @media (max-width: 640px) {
            .hero-card {
                padding: 24px 20px;
            }
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            .endpoint-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .endpoint-desc {
                text-align: left;
            }
        }
    </style>
</head>
<body>

    <!-- Lueur ambiante dynamique -->
    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <div class="content-wrapper">
        <!-- En-tête -->
        <header>
            <div class="logo-group">
                <div class="logo-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="9" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3" />
                    </svg>
                </div>
                <div style="display: flex; align-items: center;">
                    <span class="logo-title">Wakati</span>
                    <span class="badge-gateway">API Core</span>
                </div>
            </div>

            <div class="live-status-pill">
                <span class="ping-beacon">
                    <span class="ring"></span>
                    <span class="dot"></span>
                </span>
                <span>Opérationnel</span>
            </div>
        </header>

        <!-- Carte Principale -->
        <main>
            <div class="hero-card">
                <div class="status-chip">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>HTTP 200 • Passerelle API Active</span>
                </div>

                <h1 class="hero-title">
                    L&apos;API Wakati fonctionne parfaitement
                </h1>

                <p class="hero-desc">
                    Tous les services backend sont opérationnels : traitement des requêtes d&apos;authentification, vérification des badges RFID/QR, synchronisation des présences et gestion des plannings.
                </p>

                <!-- Métriques de santé -->
                <div class="metrics-grid">
                    <!-- Base de données -->
                    <div class="metric-card">
                        <div class="metric-header" style="color: #34d399;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                            <span>Base de données</span>
                        </div>
                        <div class="metric-value">
                            <span class="dot-indicator" style="background-color: {{ $dbOk ? '#10B981' : '#EF4444' }}; box-shadow: 0 0 8px {{ $dbOk ? '#10B981' : '#EF4444' }};"></span>
                            <span>{{ $dbOk ? 'Connectée' : 'Erreur' }}</span>
                            <span class="metric-sub">({{ $dbDriver }})</span>
                        </div>
                    </div>

                    <!-- Environnement -->
                    <div class="metric-card">
                        <div class="metric-header" style="color: var(--brand-primary-light);">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span>Environnement</span>
                        </div>
                        <div class="metric-value" style="text-transform: capitalize;">
                            {{ $environment }}
                        </div>
                    </div>

                    <!-- Moteur PHP / Laravel -->
                    <div class="metric-card">
                        <div class="metric-header" style="color: #60a5fa;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            <span>Moteur d&apos;exécution</span>
                        </div>
                        <div class="metric-value">
                            PHP {{ $phpVersion }}
                            <span class="metric-sub">/ v{{ $laravelVersion }}</span>
                        </div>
                    </div>

                    <!-- Horodatage Serveur -->
                    <div class="metric-card">
                        <div class="metric-header" style="color: #fbbf24;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Heure Serveur (UTC)</span>
                        </div>
                        <div class="metric-value" style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.85rem;">
                            {{ $timestamp->format('H:i:s') }}
                            <span class="metric-sub">{{ $timestamp->format('d/m/Y') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Actions Rapides -->
                <div class="actions-group">
                    <a href="http://localhost:3000" class="btn-primary">
                        <span>Ouvrir l&apos;application Web</span>
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>

                    <a href="/api/attendance/valid-badges" class="btn-secondary">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Tester le flux JSON Badges</span>
                    </a>
                </div>
            </div>

            <!-- Carte des Endpoints Clés -->
            <div class="endpoints-card">
                <div class="endpoints-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Points d&apos;accès principaux de l&apos;API</span>
                </div>

                <div class="endpoints-grid">
                    <div class="endpoint-row">
                        <div class="endpoint-left">
                            <span class="method-pill method-post">POST</span>
                            <span class="endpoint-path">/api/login</span>
                        </div>
                        <span class="endpoint-desc">Connexion & Token Sanctum</span>
                    </div>

                    <div class="endpoint-row">
                        <div class="endpoint-left">
                            <span class="method-pill method-get">GET</span>
                            <span class="endpoint-path">/api/attendance/valid-badges</span>
                        </div>
                        <span class="endpoint-desc">Sync Badges Kiosk</span>
                    </div>

                    <div class="endpoint-row">
                        <div class="endpoint-left">
                            <span class="method-pill method-post">POST</span>
                            <span class="endpoint-path">/api/attendance/sync-offline</span>
                        </div>
                        <span class="endpoint-desc">Pointages hors-ligne</span>
                    </div>

                    <div class="endpoint-row">
                        <div class="endpoint-left">
                            <span class="method-pill method-get">GET</span>
                            <span class="endpoint-path">/api/attendances</span>
                        </div>
                        <span class="endpoint-desc">Historique des présences</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Pied de page -->
    <footer>
        <p>© {{ date('Y') }} Wakati System • Plateforme de gestion du temps & des présences.</p>
    </footer>

</body>
</html>
