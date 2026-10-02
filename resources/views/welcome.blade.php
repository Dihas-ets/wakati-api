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
            --border-subtle: rgba(121, 95, 252, 0.15);
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

        /* En-tête */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-group {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 44px;
            height: 34px;
            border-radius: 8px;
            background: rgba(121, 95, 252, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 16px var(--brand-primary-glow);
            border: 1px solid rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
            overflow: hidden;
            padding: 2px 4px;
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
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

        /* Contenu central */
        .center-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
            position: relative;
            z-index: 10;
        }

        .center-title {
            font-size: clamp(1.6rem, 3.5vw, 2.5rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.3;
            background: linear-gradient(135deg, #ffffff 40%, #c4b5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 35px rgba(121, 95, 252, 0.25);
        }

        /* Pied de page */
        footer {
            padding: 24px 20px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-dim);
            position: relative;
            z-index: 10;
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
                    <img src="{{ asset('logos.svg') }}" alt="Logo Wakati">
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
    </div>

    <!-- Message central -->
    <main class="center-container">
        <h1 class="center-title">L&apos;API de Wakati fonctionne correctement</h1>
    </main>

    <!-- Pied de page -->
    <footer>
        <p>© {{ date('Y') }} Wakati Système par Diha's</p>
    </footer>

</body>
</html>
