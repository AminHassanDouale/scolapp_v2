<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Message reçu — ScolApp</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f0f4ff;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
        }

        .email-wrapper {
            max-width: 620px;
            margin: 40px auto;
            padding: 0 20px 40px;
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
            border-radius: 20px 20px 0 0;
            padding: 48px 40px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .header::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 200px; height: 200px;
            background: rgba(99,102,241,0.2);
            border-radius: 50%;
        }
        .header::after {
            content: '';
            position: absolute;
            bottom: -40px; left: -40px;
            width: 150px; height: 150px;
            background: rgba(139,92,246,0.15);
            border-radius: 50%;
        }

        .logo-wrap {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            position: relative;
            z-index: 1;
        }
        .logo-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 24px rgba(99,102,241,0.4);
        }
        .logo-text {
            font-size: 22px;
            font-weight: 800;
            color: white;
            letter-spacing: -0.3px;
        }
        .logo-dot { color: #a5b4fc; }

        .check-circle {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 8px 32px rgba(16,185,129,0.4);
            position: relative;
            z-index: 1;
        }
        .check-circle svg { width: 36px; height: 36px; }

        .header h1 {
            font-size: 26px;
            font-weight: 800;
            color: white;
            line-height: 1.3;
            position: relative;
            z-index: 1;
        }
        .header p {
            font-size: 15px;
            color: #c7d2fe;
            margin-top: 10px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        /* ── Body ── */
        .body {
            background: white;
            padding: 40px;
        }

        .greeting {
            font-size: 17px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
        }

        .body-text {
            font-size: 15px;
            color: #475569;
            line-height: 1.75;
            margin-bottom: 28px;
        }

        /* ── Message summary card ── */
        .summary-card {
            background: linear-gradient(135deg, #f8faff, #eef2ff);
            border: 1px solid #e0e7ff;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 32px;
        }
        .summary-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6366f1;
            margin-bottom: 20px;
        }
        .summary-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #e0e7ff;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-icon {
            width: 32px; height: 32px;
            background: white;
            border: 1px solid #e0e7ff;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .summary-icon svg { width: 16px; height: 16px; color: #6366f1; }
        .summary-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 2px;
        }
        .summary-value {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            line-height: 1.5;
        }
        .summary-message {
            font-size: 14px;
            color: #475569;
            line-height: 1.7;
            font-style: italic;
        }

        /* ── Timeline / what happens next ── */
        .next-steps {
            margin-bottom: 32px;
        }
        .next-steps h3 {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
        }
        .step {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 16px;
        }
        .step-num {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            color: white;
            font-size: 13px;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(99,102,241,0.3);
        }
        .step-content { padding-top: 4px; }
        .step-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }
        .step-desc {
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
        }

        /* ── CTA button ── */
        .cta-wrap { text-align: center; margin-bottom: 32px; }
        .cta-btn {
            display: inline-block;
            padding: 16px 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white !important;
            text-decoration: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.2px;
            box-shadow: 0 8px 24px rgba(99,102,241,0.35);
        }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 1px solid #f1f5f9;
            margin: 28px 0;
        }

        /* ── Contact info ── */
        .contact-info {
            background: #f8faff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 28px;
        }
        .contact-info p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.7;
        }
        .contact-info a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
        }

        /* ── Footer ── */
        .footer {
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
            border-radius: 0 0 20px 20px;
            padding: 32px 40px;
            text-align: center;
        }
        .footer-logo {
            font-size: 18px;
            font-weight: 800;
            color: white;
            margin-bottom: 12px;
        }
        .footer-tagline {
            font-size: 13px;
            color: #7c86a1;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .footer-links {
            margin-bottom: 20px;
        }
        .footer-links a {
            display: inline-block;
            color: #a5b4fc;
            text-decoration: none;
            font-size: 13px;
            margin: 0 10px;
        }
        .footer-copy {
            font-size: 12px;
            color: #4a5568;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .email-wrapper { padding: 0 12px 24px; }
            .header, .body, .footer { padding: 28px 24px; }
            .header h1 { font-size: 22px; }
            .summary-card { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="email-wrapper">

    {{-- ── HEADER ── --}}
    <div class="header">
        <div class="logo-wrap">
            <div class="logo-icon">
                <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <span class="logo-text">Scol<span class="logo-dot">App</span></span>
        </div>

        <div class="check-circle">
            <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1>Message bien reçu,<br>{{ $contact->name }} !</h1>
        <p>Notre équipe prend en charge votre demande et<br>vous répondra dans les plus brefs délais.</p>
    </div>

    {{-- ── BODY ── --}}
    <div class="body">
        <p class="greeting">Bonjour {{ $contact->name }},</p>
        <p class="body-text">
            Merci de nous avoir contactés. Nous avons bien reçu votre message et nous nous engageons
            à vous répondre dans un délai maximum de <strong>24 heures ouvrables</strong>.
        </p>

        {{-- Message summary --}}
        <div class="summary-card">
            <div class="summary-title">📋 Récapitulatif de votre message</div>

            <div class="summary-row">
                <div class="summary-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#6366f1" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="summary-label">Nom</div>
                    <div class="summary-value">{{ $contact->name }}</div>
                </div>
            </div>

            <div class="summary-row">
                <div class="summary-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#6366f1" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="summary-label">Email</div>
                    <div class="summary-value">{{ $contact->email }}</div>
                </div>
            </div>

            @if($contact->school)
            <div class="summary-row">
                <div class="summary-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#6366f1" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div>
                    <div class="summary-label">Établissement</div>
                    <div class="summary-value">{{ $contact->school }}</div>
                </div>
            </div>
            @endif

            @if($contact->phone)
            <div class="summary-row">
                <div class="summary-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#6366f1" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </div>
                <div>
                    <div class="summary-label">Téléphone</div>
                    <div class="summary-value">{{ $contact->phone }}</div>
                </div>
            </div>
            @endif

            <div class="summary-row">
                <div class="summary-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#6366f1" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <div>
                    <div class="summary-label">Votre message</div>
                    <div class="summary-message">« {{ $contact->message }} »</div>
                </div>
            </div>
        </div>

        {{-- What happens next --}}
        <div class="next-steps">
            <h3>🚀 Ce qui se passe maintenant</h3>
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-content">
                    <div class="step-title">Réception & analyse</div>
                    <div class="step-desc">Notre équipe a été notifiée et prend connaissance de votre demande.</div>
                </div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-content">
                    <div class="step-title">Réponse personnalisée</div>
                    <div class="step-desc">Un conseiller ScolApp vous répondra sous 24h ouvrables avec une réponse adaptée.</div>
                </div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-content">
                    <div class="step-title">Démonstration gratuite</div>
                    <div class="step-desc">Si vous le souhaitez, nous pouvons planifier une démo live de la plateforme.</div>
                </div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="cta-wrap">
            <a href="https://scolapp.com" class="cta-btn">
                Visiter scolapp.com →
            </a>
        </div>

        <hr class="divider">

        {{-- Contact info --}}
        <div class="contact-info">
            <p>
                Vous pouvez également nous joindre directement à
                <a href="mailto:contact@scolapp.com">contact@scolapp.com</a>.
            </p>
            <p style="margin-top: 8px;">
                💬 WhatsApp : <a href="https://wa.me/25377049495" style="color:#25d366; font-weight:600;">+253 77 04 94 95</a>
            </p>
            <p style="margin-top: 8px;">
                🕐 Support disponible <strong>24h/24 — 7j/7</strong>.
            </p>
        </div>

        <p style="font-size: 14px; color: #94a3b8; line-height: 1.7;">
            Cet email a été envoyé automatiquement suite à votre demande de contact sur
            <a href="https://scolapp.com" style="color: #6366f1; text-decoration: none;">scolapp.com</a>.
            Si vous n'êtes pas à l'origine de cette demande, veuillez ignorer cet email.
        </p>
    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer">
        <div class="footer-logo">ScolApp</div>
        <div class="footer-tagline">
            La plateforme de gestion scolaire nouvelle génération.<br>
            Simplifiez. Automatisez. Excellez.
        </div>
        <div class="footer-links">
            <a href="https://scolapp.com">Accueil</a>
            <a href="https://scolapp.com/#features">Fonctionnalités</a>
            <a href="https://scolapp.com/#contact">Contact</a>
        </div>
        <div class="footer-copy">
            © {{ date('Y') }} ScolApp. Tous droits réservés.<br>
            <a href="mailto:contact@scolapp.com" style="color: #4a5568; text-decoration: none;">contact@scolapp.com</a>
        </div>
    </div>

</div>
</body>
</html>
