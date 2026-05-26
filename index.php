<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FYLCAD — Topografía Digital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --teal:     #00e5c0;
            --teal-dim: rgba(0,229,192,0.12);
            --dark:     #04060c;
            --mid:      #080d18;
            --text:     #e2e8f4;
            --muted:    #5a6478;
            --border:   rgba(0,229,192,0.12);
        }

        html { scroll-behavior: smooth; }
        body {
            background: var(--dark);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            overflow-x: hidden;
        }

        /* ══════════════════════════════════════
           NAV
        ══════════════════════════════════════ */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 200;
            padding: 22px 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.4s;
        }

        nav.scrolled {
            background: rgba(4,6,12,0.94);
            backdrop-filter: blur(16px);
            padding: 14px 56px;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 30px;
            letter-spacing: 5px;
            color: var(--text);
            text-decoration: none;
        }
        .logo b { color: var(--teal); }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 36px;
        }

        .nav-link {
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .nav-link:hover { color: var(--text); }

        .nav-btn {
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            letter-spacing: 1.5px;
            color: var(--dark);
            background: var(--teal);
            padding: 10px 24px;
            border-radius: 3px;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .nav-btn:hover { opacity: 0.85; }

        /* ══════════════════════════════════════
           HERO — VIDEO FULLSCREEN
        ══════════════════════════════════════ */
        #hero {
            position: relative;
            height: 100vh;
            min-height: 700px;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
        }

        /* Múltiples videos que rotan */
        .video-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            filter: brightness(0.28) saturate(0.6);
            opacity: 0;
            transition: opacity 1.5s ease;
        }
        .video-bg.active { opacity: 1; }

        /* Overlays */
        .hero-overlay-gradient {
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(0,229,192,0.04) 0%, transparent 60%),
                linear-gradient(to right, rgba(4,6,12,0.9) 0%, rgba(4,6,12,0.2) 60%, transparent 100%),
                linear-gradient(to top, rgba(4,6,12,1) 0%, transparent 50%);
        }

        /* Grid topográfico decorativo */
        .hero-grid {
            position: absolute;
            inset: 0;
            z-index: 1;
            background-image:
                linear-gradient(rgba(0,229,192,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,229,192,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.3) 30%, transparent 100%);
        }

        /* Línea horizontal teal arriba */
        .hero-top-line {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(to right, transparent, var(--teal) 30%, var(--teal) 70%, transparent);
            z-index: 3;
            opacity: 0.6;
        }

        /* Línea horizontal teal abajo */
        .hero-bottom-line {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--teal), transparent);
            z-index: 3;
        }

        /* Contenido hero */
        .hero-content {
            position: relative;
            z-index: 2;
            padding: 0 56px 80px;
            max-width: 780px;
        }

        .hero-eyebrow {
            font-family: 'Space Mono', monospace;
            font-size: 10px;
            letter-spacing: 4px;
            color: var(--teal);
            text-transform: uppercase;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            opacity: 0;
            animation: fadeUp 0.8s 0.3s ease forwards;
        }

        .hero-eyebrow::before {
            content: '';
            width: 40px;
            height: 1px;
            background: var(--teal);
        }

        h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(72px, 11vw, 148px);
            line-height: 0.88;
            letter-spacing: 3px;
            color: var(--text);
            margin-bottom: 32px;
            opacity: 0;
            animation: fadeUp 0.8s 0.5s ease forwards;
        }

        h1 .accent {
            color: var(--teal);
            display: block;
        }

        h1 .outline {
            -webkit-text-stroke: 1px rgba(226,232,244,0.3);
            color: transparent;
        }

        .hero-desc {
            font-size: 16px;
            font-weight: 300;
            line-height: 1.75;
            color: rgba(226,232,244,0.6);
            max-width: 460px;
            margin-bottom: 48px;
            opacity: 0;
            animation: fadeUp 0.8s 0.7s ease forwards;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            opacity: 0;
            animation: fadeUp 0.8s 0.9s ease forwards;
        }

        .btn-main {
            font-family: 'Space Mono', monospace;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--dark);
            background: var(--teal);
            padding: 16px 36px;
            border-radius: 3px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: opacity 0.2s, transform 0.2s;
        }
        .btn-main:hover { opacity: 0.85; transform: translateY(-2px); }

        .btn-ghost {
            font-family: 'Space Mono', monospace;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text);
            background: transparent;
            border: 1px solid rgba(226,232,244,0.15);
            padding: 16px 36px;
            border-radius: 3px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: border-color 0.2s, transform 0.2s;
        }
        .btn-ghost:hover { border-color: rgba(226,232,244,0.4); transform: translateY(-2px); }

        /* Stats flotantes derecha */
        .hero-stats {
            position: absolute;
            right: 56px;
            bottom: 80px;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 0;
            opacity: 0;
            animation: fadeLeft 0.8s 1.1s ease forwards;
        }

        .stat-row {
            padding: 20px 28px;
            border: 1px solid var(--border);
            border-bottom: none;
            background: rgba(4,6,12,0.6);
            backdrop-filter: blur(8px);
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 200px;
        }
        .stat-row:last-child { border-bottom: 1px solid var(--border); }

        .stat-val {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 38px;
            letter-spacing: 2px;
            color: var(--teal);
            line-height: 1;
        }

        .stat-lbl {
            font-family: 'Space Mono', monospace;
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--muted);
        }

        /* Indicador de video */
        .video-dots {
            position: absolute;
            bottom: 36px;
            left: 56px;
            z-index: 2;
            display: flex;
            gap: 8px;
            opacity: 0;
            animation: fadeUp 0.8s 1.3s ease forwards;
        }

        .video-dot {
            width: 24px;
            height: 2px;
            background: rgba(226,232,244,0.2);
            cursor: pointer;
            transition: background 0.3s, width 0.3s;
        }
        .video-dot.active {
            background: var(--teal);
            width: 40px;
        }

        /* ══════════════════════════════════════
           TICKER STRIP
        ══════════════════════════════════════ */
        .ticker {
            background: var(--teal);
            overflow: hidden;
            padding: 12px 0;
            position: relative;
            z-index: 10;
        }

        .ticker-inner {
            display: flex;
            gap: 0;
            animation: ticker 20s linear infinite;
            white-space: nowrap;
        }

        .ticker-item {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 14px;
            letter-spacing: 3px;
            color: var(--dark);
            padding: 0 32px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .ticker-item::after {
            content: '◆';
            font-size: 8px;
            opacity: 0.4;
        }

        @keyframes ticker {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        /* ══════════════════════════════════════
           SECCIÓN: CÓMO FUNCIONA
        ══════════════════════════════════════ */
        #como {
            padding: 140px 56px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        .como-left .sec-tag {
            font-family: 'Space Mono', monospace;
            font-size: 10px;
            letter-spacing: 3px;
            color: var(--teal);
            text-transform: uppercase;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sec-tag::before {
            content: '';
            width: 24px;
            height: 1px;
            background: var(--teal);
        }

        .sec-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(42px, 6vw, 80px);
            letter-spacing: 2px;
            line-height: 0.95;
            color: var(--text);
            margin-bottom: 28px;
        }

        .sec-desc {
            font-size: 15px;
            font-weight: 300;
            line-height: 1.8;
            color: rgba(226,232,244,0.55);
            margin-bottom: 44px;
        }

        .steps {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .step {
            display: grid;
            grid-template-columns: 48px 1fr;
            gap: 20px;
            padding: 24px 0;
            border-bottom: 1px solid rgba(226,232,244,0.06);
            align-items: start;
        }

        .step-num {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 32px;
            color: var(--teal);
            line-height: 1;
            opacity: 0.4;
        }

        .step-body h4 {
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.5px;
            color: var(--text);
            margin-bottom: 6px;
        }

        .step-body p {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
        }

        /* Panel derecho: terminal mockup */
        .como-right {
            background: var(--mid);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        .terminal-bar {
            background: rgba(0,229,192,0.06);
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .t-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .terminal-body {
            padding: 28px;
            font-family: 'Space Mono', monospace;
            font-size: 12px;
            line-height: 1.8;
        }

        .t-comment { color: #3d5a4f; }
        .t-key     { color: var(--teal); }
        .t-val     { color: #a8d5c8; }
        .t-num     { color: #f0a05a; }
        .t-str     { color: #85c7b3; }
        .t-ok      { color: #4ade80; }
        .t-label   { color: var(--muted); }

        /* ══════════════════════════════════════
           SERVICIOS
        ══════════════════════════════════════ */
        #servicios {
            padding: 0 56px 140px;
        }

        .servicios-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 48px;
        }

        .servicios-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--border);
        }

        .s-card {
            background: var(--dark);
            padding: 52px 40px;
            position: relative;
            overflow: hidden;
            transition: background 0.3s;
        }
        .s-card:hover { background: #080e1c; }

        .s-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: var(--teal);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s;
        }
        .s-card:hover::after { transform: scaleX(1); }

        .s-num {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 80px;
            color: rgba(0,229,192,0.05);
            line-height: 1;
            position: absolute;
            top: 24px;
            right: 24px;
        }

        .s-icon {
            font-size: 36px;
            margin-bottom: 24px;
            display: block;
        }

        .s-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 30px;
            letter-spacing: 2px;
            color: var(--text);
            margin-bottom: 16px;
        }

        .s-desc {
            font-size: 14px;
            font-weight: 300;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 32px;
        }

        .s-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 32px;
        }

        .s-tag {
            font-family: 'Space Mono', monospace;
            font-size: 9px;
            letter-spacing: 1.5px;
            color: var(--teal);
            border: 1px solid var(--border);
            padding: 4px 10px;
            border-radius: 2px;
        }

        .s-link {
            font-family: 'Space Mono', monospace;
            font-size: 10px;
            letter-spacing: 2px;
            color: rgba(226,232,244,0.4);
            text-decoration: none;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s, gap 0.2s;
        }
        .s-link:hover { color: var(--teal); gap: 14px; }

        /* ══════════════════════════════════════
           PLANES
        ══════════════════════════════════════ */
        #planes {
            padding: 140px 56px;
            background: var(--mid);
            position: relative;
        }

        #planes::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--teal) 40%, var(--teal) 60%, transparent);
        }

        .planes-wrap {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 80px;
            align-items: start;
            max-width: 1100px;
        }

        .planes-left p {
            font-size: 15px;
            font-weight: 300;
            color: rgba(226,232,244,0.5);
            line-height: 1.8;
            margin-bottom: 48px;
            max-width: 400px;
        }

        .comparativa {
            display: flex;
            flex-direction: column;
            gap: 1px;
            background: var(--border);
        }

        .comp-row {
            display: grid;
            grid-template-columns: 1fr 80px 80px;
            gap: 0;
            background: var(--dark);
        }

        .comp-row.header {
            background: rgba(0,229,192,0.06);
        }

        .comp-cell {
            padding: 14px 16px;
            font-size: 12px;
            color: var(--muted);
            border-right: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .comp-cell:first-child {
            justify-content: flex-start;
            color: rgba(226,232,244,0.6);
        }

        .comp-cell.header-cell {
            font-family: 'Space Mono', monospace;
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--teal);
            font-weight: 700;
        }

        .comp-cell:last-child { border-right: none; }
        .comp-cell .yes { color: var(--teal); font-size: 16px; }
        .comp-cell .no  { color: #2a3040; font-size: 14px; }

        /* Card premium */
        .plan-featured {
            background: var(--dark);
            border: 1px solid var(--teal);
            padding: 44px 36px;
            position: relative;
            overflow: hidden;
        }

        .plan-featured::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(0,229,192,0.08) 0%, transparent 70%);
        }

        .plan-badge {
            font-family: 'Space Mono', monospace;
            font-size: 9px;
            letter-spacing: 2px;
            color: var(--dark);
            background: var(--teal);
            padding: 4px 12px;
            display: inline-block;
            margin-bottom: 24px;
        }

        .plan-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 42px;
            letter-spacing: 4px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .plan-price {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 64px;
            letter-spacing: 2px;
            color: var(--teal);
            line-height: 1;
        }

        .plan-period {
            font-family: 'Space Mono', monospace;
            font-size: 10px;
            letter-spacing: 1px;
            color: var(--muted);
            margin-bottom: 36px;
        }

        .plan-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 40px;
        }

        .plan-list li {
            font-size: 14px;
            color: rgba(226,232,244,0.7);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.4;
        }

        .plan-list li::before {
            content: '→';
            color: var(--teal);
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .plan-trial {
            font-family: 'Space Mono', monospace;
            font-size: 9px;
            letter-spacing: 1.5px;
            color: var(--muted);
            text-align: center;
            margin-top: 16px;
        }

        /* ══════════════════════════════════════
           CTA FINAL
        ══════════════════════════════════════ */
        #cta {
            padding: 120px 56px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        #cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at 50% 50%, rgba(0,229,192,0.06) 0%, transparent 70%);
        }

        #cta .sec-title { font-size: clamp(48px, 8vw, 100px); }

        #cta p {
            font-size: 16px;
            font-weight: 300;
            color: rgba(226,232,244,0.5);
            max-width: 480px;
            margin: 20px auto 48px;
            line-height: 1.7;
        }

        .cta-btns {
            display: flex;
            justify-content: center;
            gap: 16px;
        }

        /* ══════════════════════════════════════
           FOOTER
        ══════════════════════════════════════ */
        footer {
            border-top: 1px solid var(--border);
            padding: 40px 56px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            align-items: center;
        }

        .f-logo {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 24px;
            letter-spacing: 5px;
            color: var(--muted);
        }
        .f-logo b { color: var(--teal); }

        .f-links {
            display: flex;
            justify-content: center;
            gap: 24px;
        }

        .f-link {
            font-size: 12px;
            color: var(--muted);
            text-decoration: none;
            letter-spacing: 0.5px;
            transition: color 0.2s;
        }
        .f-link:hover { color: var(--text); }

        .f-copy {
            font-family: 'Space Mono', monospace;
            font-size: 10px;
            letter-spacing: 1px;
            color: var(--muted);
            text-align: right;
        }

        /* ══════════════════════════════════════
           ANIMATIONS
        ══════════════════════════════════════ */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeLeft {
            from { opacity: 0; transform: translateX(30px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ══════════════════════════════════════
           RESPONSIVE
        ══════════════════════════════════════ */
        @media (max-width: 960px) {
            nav { padding: 16px 24px; }
            nav.scrolled { padding: 12px 24px; }
            .nav-right .nav-link { display: none; }
            .hero-content { padding: 0 24px 80px; }
            .hero-stats { display: none; }
            .video-dots { left: 24px; }
            #como { grid-template-columns: 1fr; padding: 80px 24px; gap: 48px; }
            #servicios { padding: 0 24px 80px; }
            .servicios-grid { grid-template-columns: 1fr; }
            .servicios-header { flex-direction: column; align-items: flex-start; gap: 20px; }
            #planes { padding: 80px 24px; }
            .planes-wrap { grid-template-columns: 1fr; gap: 48px; }
            #cta { padding: 80px 24px; }
            footer { grid-template-columns: 1fr; gap: 24px; padding: 32px 24px; }
            .f-links { justify-content: flex-start; }
            .f-copy { text-align: left; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav id="nav">
    <a href="#" class="logo">FYL<b>CAD</b></a>
    <div class="nav-right">
        <a href="#como" class="nav-link">Cómo funciona</a>
        <a href="#servicios" class="nav-link">Servicios</a>
        <a href="planes.php" class="nav-link">Planes</a>
        <a href="login.php" class="nav-btn">Ingresar →</a>
    </div>
</nav>

<!-- HERO -->
<section id="hero">
    <!-- Video 1: Drone topográfico sobre terreno montañoso -->
    <video class="video-bg active" autoplay muted loop playsinline>
    <source src="videos/terreno1.mp4" type="video/mp4">
</video>
<video class="video-bg" muted loop playsinline>
    <source src="videos/terreno2.mp4" type="video/mp4">
</video>
<video class="video-bg" muted loop playsinline>
    <source src="videos/terreno3.mp4" type="video/mp4">
</video>

    <div class="hero-top-line"></div>
    <div class="hero-overlay-gradient"></div>
    <div class="hero-grid"></div>

    <div class="hero-content">
        <div class="hero-eyebrow">Topografía Digital · Cúcuta, Colombia</div>
        <h1>
            <span class="outline">PROCESA</span>
            <span class="accent">TERRENOS</span>
            EN 3D
        </h1>
        <p class="hero-desc">
            Carga tu levantamiento topográfico, visualiza el terreno en 3D
            y genera cotizaciones de obra en segundos.
            Sin software, sin instalaciones, desde el navegador.
        </p>
        <div class="hero-actions">
            <a href="proyecto.php" class="btn-main">▶ Ver Demo</a>
            <a href="login.php" class="btn-ghost">Iniciar Sesión →</a>
        </div>
    </div>

    <!-- Stats panel derecho -->
    <div class="hero-stats">
        <div class="stat-row">
            <div class="stat-val">CSV</div>
            <div class="stat-lbl">Carga directa de coordenadas</div>
        </div>
        <div class="stat-row">
            <div class="stat-val">TIN</div>
            <div class="stat-lbl">Triangulación Delaunay</div>
        </div>
        <div class="stat-row">
            <div class="stat-val">3D</div>
            <div class="stat-lbl">Visor interactivo en tiempo real</div>
        </div>
        <div class="stat-row">
            <div class="stat-val">$0</div>
            <div class="stat-lbl">Para empezar hoy</div>
        </div>
    </div>

    <!-- Puntos de video -->
    <div class="video-dots">
        <div class="video-dot active" onclick="cambiarVideo(0)"></div>
        <div class="video-dot" onclick="cambiarVideo(1)"></div>
        <div class="video-dot" onclick="cambiarVideo(2)"></div>
    </div>

    <div class="hero-bottom-line"></div>
</section>

<!-- TICKER -->
<div class="ticker">
    <div class="ticker-inner">
        <span class="ticker-item">Topografía Digital</span>
        <span class="ticker-item">Triangulación Delaunay</span>
        <span class="ticker-item">Visualización 3D</span>
        <span class="ticker-item">Cotización Automática</span>
        <span class="ticker-item">Curvas de Nivel</span>
        <span class="ticker-item">Movimiento de Tierra</span>
        <span class="ticker-item">Red TIN</span>
        <span class="ticker-item">Planimetría y Altimetría</span>
        <span class="ticker-item">Topografía Digital</span>
        <span class="ticker-item">Triangulación Delaunay</span>
        <span class="ticker-item">Visualización 3D</span>
        <span class="ticker-item">Cotización Automática</span>
        <span class="ticker-item">Curvas de Nivel</span>
        <span class="ticker-item">Movimiento de Tierra</span>
        <span class="ticker-item">Red TIN</span>
        <span class="ticker-item">Planimetría y Altimetría</span>
    </div>
</div>

<!-- CÓMO FUNCIONA -->
<section id="como">
    <div class="como-left reveal">
        <div class="sec-tag">Proceso</div>
        <h2 class="sec-title">DEL CAMPO<br>AL PLANO.</h2>
        <p class="sec-desc">
            FYLCAD transforma tus coordenadas topográficas en planos digitales
            profesionales. El proceso completo toma menos de 2 minutos.
        </p>
        <div class="steps">
            <div class="step">
                <div class="step-num">01</div>
                <div class="step-body">
                    <h4>Carga tu archivo CSV</h4>
                    <p>Exporta desde tu estación total o GPS y sube el archivo con las coordenadas X, Y, Z del levantamiento.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num">02</div>
                <div class="step-body">
                    <h4>Procesamiento automático</h4>
                    <p>FYLCAD ejecuta la triangulación Delaunay y calcula área, volumen, desnivel y curvas de nivel en segundos.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num">03</div>
                <div class="step-body">
                    <h4>Visualiza en 3D</h4>
                    <p>Explora el terreno en el visor 3D interactivo. Rota, escala y analiza cada punto del levantamiento.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num">04</div>
                <div class="step-body">
                    <h4>Cotiza y exporta</h4>
                    <p>Genera el presupuesto de obra automáticamente y exporta el plano en PDF profesional.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="como-right reveal">
        <div class="terminal-bar">
            <div class="t-dot" style="background:#ff5f57"></div>
            <div class="t-dot" style="background:#ffbd2e"></div>
            <div class="t-dot" style="background:#28c840"></div>
            <span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-left:8px">fylcad — resultado.json</span>
        </div>
        <div class="terminal-body">
            <div class="t-comment">// Resultado procesamiento FYLCAD</div>
            <div class="t-comment">// Proyecto: La Sanjuana Cancha</div>
            <br>
            <div><span class="t-key">"proyecto"</span>: {</div>
            <div>&nbsp;&nbsp;<span class="t-key">"total_puntos"</span>: <span class="t-num">612</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"total_triangulos"</span>: <span class="t-num">1208</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"area_m2"</span>: <span class="t-num">13614.30</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"volumen_m3"</span>: <span class="t-num">154437.29</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"perimetro_m"</span>: <span class="t-num">2790.64</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"cota_min"</span>: <span class="t-num">376.34</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"cota_max"</span>: <span class="t-num">397.75</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"desnivel"</span>: <span class="t-num">21.41</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"estado"</span>: <span class="t-str">"completo"</span></div>
            <div>}</div>
            <br>
            <div class="t-comment">// Cotización automática generada</div>
            <div><span class="t-key">"cotizacion"</span>: {</div>
            <div>&nbsp;&nbsp;<span class="t-key">"mov_tierra"</span>: <span class="t-num">$1.312.717</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"nivelacion"</span>: <span class="t-num">$43.566</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"cerramiento"</span>: <span class="t-num">$125.579</span>,</div>
            <div>&nbsp;&nbsp;<span class="t-key">"total_usd"</span>: <span class="t-num">$1.481.861</span></div>
            <div>}</div>
            <br>
            <div><span class="t-ok">✓ Procesado en 1.4s · PDF generado</span></div>
        </div>
    </div>
</section>

<!-- SERVICIOS -->
<section id="servicios">
    <div class="servicios-header reveal">
        <div>
            <div class="sec-tag">Servicios</div>
            <h2 class="sec-title">LO QUE<br>OFRECEMOS.</h2>
        </div>
        <a href="register.php" class="btn-ghost">Ver todos →</a>
    </div>

    <div class="servicios-grid">
        <div class="s-card reveal">
            <div class="s-num">01</div>
            <span class="s-icon">🗺️</span>
            <div class="s-title">Plan Premium</div>
            <p class="s-desc">Acceso completo a la plataforma con puntos ilimitados, visor 3D avanzado, exportación PDF y cotización automática de obra.</p>
            <div class="s-tags">
                <span class="s-tag">Ilimitado</span>
                <span class="s-tag">PDF</span>
                <span class="s-tag">3D</span>
            </div>
            <a href="planes.php" class="s-link">Conocer plan →</a>
        </div>
        <div class="s-card reveal">
            <div class="s-num">02</div>
            <span class="s-icon">💰</span>
            <div class="s-title">Cotización de Obra</div>
            <p class="s-desc">Genera automáticamente el presupuesto de movimiento de tierra, nivelación y cerramiento con tarifas configurables por el usuario.</p>
            <div class="s-tags">
                <span class="s-tag">Automático</span>
                <span class="s-tag">Configurable</span>
            </div>
            <a href="cotizacion.php" class="s-link">Ver cotización →</a>
        </div>
        <div class="s-card reveal">
            <div class="s-num">03</div>
            <span class="s-icon">🤝</span>
            <div class="s-title">Intermediación</div>
            <p class="s-desc">Conectamos tu proyecto con proveedores verificados de materiales, maquinaria y mano de obra en Cúcuta y Norte de Santander.</p>
            <div class="s-tags">
                <span class="s-tag">Cúcuta</span>
                <span class="s-tag">Verificado</span>
                <span class="s-tag">3% comisión</span>
            </div>
            <a href="#" class="s-link">Ver proveedores →</a>
        </div>
    </div>
</section>

<!-- PLANES -->
<section id="planes">
    <div class="planes-wrap">
        <div class="planes-left reveal">
            <div class="sec-tag">Precios</div>
            <h2 class="sec-title">ELIGE<br>TU PLAN.</h2>
            <p>Sin contratos, sin compromisos. Empieza gratis y escala cuando quieras. El Plan Premium incluye 7 días de prueba sin cargo.</p>

            <div class="comparativa">
                <div class="comp-row header">
                    <div class="comp-cell">Función</div>
                    <div class="comp-cell header-cell">Free</div>
                    <div class="comp-cell header-cell">Pro</div>
                </div>
                <div class="comp-row">
                    <div class="comp-cell">Puntos topográficos</div>
                    <div class="comp-cell"><span style="font-size:11px;color:var(--muted)">50</span></div>
                    <div class="comp-cell"><span class="yes">∞</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-cell">Visualización 3D</div>
                    <div class="comp-cell"><span class="yes">✓</span></div>
                    <div class="comp-cell"><span class="yes">✓</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-cell">Exportación PNG</div>
                    <div class="comp-cell"><span class="yes">✓</span></div>
                    <div class="comp-cell"><span class="yes">✓</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-cell">Exportación PDF</div>
                    <div class="comp-cell"><span class="no">—</span></div>
                    <div class="comp-cell"><span class="yes">✓</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-cell">Cotización automática</div>
                    <div class="comp-cell"><span class="no">—</span></div>
                    <div class="comp-cell"><span class="yes">✓</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-cell">Proveedores sin comisión</div>
                    <div class="comp-cell"><span class="no">—</span></div>
                    <div class="comp-cell"><span style="font-size:11px;color:var(--teal)">5/mes</span></div>
                </div>
            </div>
        </div>

        <div class="plan-featured reveal">
            <div class="plan-badge">PREMIUM · MÁS POPULAR</div>
            <div class="plan-name">PREMIUM</div>
            <div class="plan-price">$49.900</div>
            <div class="plan-period">COP / mes · o $449.900/año</div>
            <ul class="plan-list">
                <li>Puntos XYZ ilimitados por proyecto</li>
                <li>Triangulación Delaunay completa</li>
                <li>Visor 3D interactivo avanzado</li>
                <li>Exportación planos PDF profesional</li>
                <li>Cotización automática de obra</li>
                <li>5 solicitudes a proveedores sin comisión</li>
                <li>Historial ilimitado de proyectos</li>
                <li>Soporte prioritario</li>
            </ul>
            <a href="register.php" class="btn-main" style="width:100%;justify-content:center;">
                Probar 7 días gratis →
            </a>
            <p class="plan-trial">Sin tarjeta · Cancela cuando quieras</p>
        </div>
    </div>
</section>

<!-- CTA FINAL -->
<section id="cta">
    <div class="sec-tag" style="justify-content:center">Empieza hoy</div>
    <h2 class="sec-title reveal">TU TERRENO<br>EN <span style="color:var(--teal)">3D</span>.</h2>
    <p class="reveal">Únete a los topógrafos e ingenieros que ya digitalizaron sus levantamientos con FYLCAD.</p>
    <div class="cta-btns reveal">
        <a href="register.php" class="btn-main">Crear cuenta gratis →</a>
        <a href="proyecto.php" class="btn-ghost">Ver demo</a>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="f-logo">FYL<b>CAD</b></div>
    <div class="f-links">
        <a href="#como" class="f-link">Cómo funciona</a>
        <a href="#servicios" class="f-link">Servicios</a>
        <a href="#planes" class="f-link">Planes</a>
        <a href="login.php" class="f-link">Ingresar</a>
    </div>
    <div class="f-copy">© 2026 FYLCAD · Cúcuta, Colombia<br>soporte@fylcad.com</div>
</footer>

<script>
// ── Nav scroll
const nav = document.getElementById('nav');
window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 60);
});

// ── Video rotación automática
const videos  = document.querySelectorAll('.video-bg');
const dots    = document.querySelectorAll('.video-dot');
let current   = 0;
let interval;

function cambiarVideo(idx) {
    videos[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = idx;
    videos[current].classList.add('active');
    videos[current].play();
    dots[current].classList.add('active');
    clearInterval(interval);
    interval = setInterval(autoNext, 8000);
}

function autoNext() {
    cambiarVideo((current + 1) % videos.length);
}

interval = setInterval(autoNext, 8000);

// ── Scroll reveal
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>

</body>
</html>