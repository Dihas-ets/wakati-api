<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wakati API • Service Opérationnel</title>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg-color: #07090e;
            --text-main: #ffffff;
            --text-highlight: #c4b5fd;
            --brand-primary: #795ffc;
            --emerald: #10b981;
            --muted: #4b5563;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Lueur d'ambiance centrale douce */
        .ambient-glow {
            position: absolute;
            top: 30%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 720px;
            height: 480px;
            background: radial-gradient(ellipse at center, rgba(121, 95, 252, 0.16) 0%, rgba(59, 130, 246, 0.08) 35%, rgba(7, 9, 14, 0) 70%);
            filter: blur(50px);
            pointer-events: none;
            z-index: 1;
        }

        /* Header en haut */
        header {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 32px 48px;
            width: 100%;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-box {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            padding: 6px;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
        }

        .badge-core {
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 9999px;
            background: rgba(121, 95, 252, 0.18);
            color: #b4a2fe;
            border: 1px solid rgba(121, 95, 252, 0.35);
        }

        /* Badge Statut Opérationnel */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 22px;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #2ed697;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.38);
            letter-spacing: 0.01em;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.1);
        }

        /* Contenu Principal Centré */
        main {
            position: relative;
            z-index: 10;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }

        .hero-title {
            font-size: clamp(2rem, 4vw, 2.9rem);
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.25;
            color: #ffffff;
            max-width: 900px;
        }

        .hero-title .highlight {
            color: var(--text-highlight);
            font-weight: 700;
        }

        /* Pied de page */
        footer {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 28px 20px;
            font-size: 0.8rem;
            color: var(--muted);
            letter-spacing: -0.01em;
        }

        @media (max-width: 640px) {
            header {
                padding: 20px 24px;
            }
            .hero-title {
                font-size: 1.85rem;
            }
            .badge-core {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- Lueur ambiante d'arrière-plan -->
    <div class="ambient-glow"></div>

    <!-- En-tête -->
    <header>
        <div class="header-brand">
            <div class="logo-box">
                <img src="/logos.svg" alt="Wakati" />
            </div>
            <span class="brand-title">Wakati</span>
            <span class="badge-core">API CORE</span>
        </div>

        <div class="status-badge">
            Opérationnel
        </div>
    </header>

    <!-- Titre au centre -->
    <main>
        <h1 class="hero-title">
            L&apos;API de Wakati fonctionne <span class="highlight">correctement</span>
        </h1>
    </main>

    <!-- Pied de page -->
    <footer>
        <p>© 2026 Wakati Système par Diha&apos;s</p>
    </footer>

</body>
</html>
