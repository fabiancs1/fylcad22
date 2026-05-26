/**
 * FYLCAD — Widget de Asistente IA (FYLIA)
 * Archivo: js/Fylcad_ai_widget.js
 *
 * USO: Agregar al final del <body> en cualquier página:
 *   <script src="js/Fylcad_ai_widget.js" data-pagina="dashboard"></script>
 *
 * Valores para data-pagina:
 *   dashboard | proyecto | mis_proyectos | perfil | planes | cotizacion | index
 */

(function () {
    'use strict';

    // ── Detectar página actual ──────────────────────────────────────
    const scriptTag = document.currentScript;
    const paginaAttr = scriptTag?.getAttribute('data-pagina') || '';
    const paginaAuto = window.location.pathname.split('/').pop().replace('.php', '') || 'index';
    const PAGINA = paginaAttr || paginaAuto;

    // ── Estado ──────────────────────────────────────────────────────
    let abierto   = false;
    let cargando  = false;
    let historial = [];

    const SUGERENCIAS = {
        dashboard:     ['¿Cómo creo un nuevo proyecto?', '¿Qué muestran las estadísticas?', '¿Cómo actualizo mi plan?'],
        proyecto:      ['¿Cómo cargo un archivo CSV?', '¿Qué formato tienen las coordenadas?', '¿Cómo calculo el volumen?'],
        mis_proyectos: ['¿Cómo abro un proyecto?', '¿Puedo eliminar proyectos?', '¿Cómo genero una cotización?'],
        perfil:        ['¿Cómo cambio mi contraseña?', '¿Puedo cambiar mi email?', '¿Cómo subo una foto?'],
        planes:        ['¿Qué incluye el plan Premium?', '¿Cuánto cuesta?', '¿Puedo cancelar cuando quiera?'],
        cotizacion:    ['¿Cómo configuro los precios?', '¿Cómo exporto a PDF?', '¿Qué es el precio unitario?'],
        index:         ['¿Qué es FYLCAD?', '¿Cómo empiezo gratis?', '¿Qué tipo de archivos acepta?'],
    };

    const sugs = SUGERENCIAS[PAGINA] || SUGERENCIAS['index'];

    // ── Inyectar estilos ────────────────────────────────────────────
    const css = `
    #fylia-btn {
        position: fixed;
        bottom: 28px;
        right: 28px;
        z-index: 9998;
        width: 52px;
        height: 52px;
        border-radius: 16px;
        background: linear-gradient(135deg, #00e5c0, #3b82f6);
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        box-shadow: 0 8px 24px rgba(0,229,192,0.35), 0 2px 8px rgba(0,0,0,0.4);
        transition: transform .2s, box-shadow .2s;
        animation: fylia-pop .4s cubic-bezier(0.34,1.56,0.64,1) forwards;
    }
    #fylia-btn:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 12px 32px rgba(0,229,192,0.45), 0 2px 8px rgba(0,0,0,0.4);
    }
    #fylia-btn .fylia-pulse {
        position: absolute;
        top: -3px; right: -3px;
        width: 12px; height: 12px;
        background: #00e5c0;
        border-radius: 50%;
        border: 2px solid #05080f;
        animation: fylia-pulse 2s infinite;
    }
    @keyframes fylia-pop {
        from { opacity:0; transform: scale(0.6); }
        to   { opacity:1; transform: scale(1); }
    }
    @keyframes fylia-pulse {
        0%,100% { box-shadow: 0 0 0 0 rgba(0,229,192,.5); }
        50%      { box-shadow: 0 0 0 5px rgba(0,229,192,0); }
    }

    #fylia-panel {
        position: fixed;
        bottom: 92px;
        right: 28px;
        z-index: 9999;
        width: 360px;
        max-height: 560px;
        background: #0c1120;
        border: 1px solid rgba(255,255,255,0.09);
        border-radius: 20px;
        box-shadow: 0 32px 64px rgba(0,0,0,0.6), 0 0 0 1px rgba(0,229,192,0.06);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: scale(0.92) translateY(12px);
        opacity: 0;
        pointer-events: none;
        transition: transform .3s cubic-bezier(0.34,1.4,0.64,1), opacity .25s ease;
    }
    #fylia-panel.fylia-open {
        transform: scale(1) translateY(0);
        opacity: 1;
        pointer-events: all;
    }

    .fylia-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid rgba(255,255,255,0.07);
        background: rgba(0,229,192,0.04);
        flex-shrink: 0;
    }
    .fylia-header-ico {
        width: 36px; height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg,#00e5c0,#3b82f6);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .fylia-header-info { flex: 1; }
    .fylia-header-name {
        font-family: 'Syne', sans-serif;
        font-size: 14px; font-weight: 800;
        color: #fff; letter-spacing: -.2px;
    }
    .fylia-header-status {
        display: flex; align-items: center; gap: 5px;
        font-size: 11px; color: #64748b;
        margin-top: 1px;
    }
    .fylia-dot {
        width: 6px; height: 6px; border-radius: 50%;
        background: #00e5c0;
        animation: fylia-pulse 2s infinite;
    }
    .fylia-close-btn {
        background: rgba(255,255,255,.06);
        border: 1px solid rgba(255,255,255,.08);
        color: #64748b;
        width: 28px; height: 28px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        display: flex; align-items: center; justify-content: center;
        transition: background .2s, color .2s;
        flex-shrink: 0;
    }
    .fylia-close-btn:hover { background: rgba(255,255,255,.1); color: #e8edf5; }

    .fylia-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        scroll-behavior: smooth;
    }
    .fylia-messages::-webkit-scrollbar { width: 3px; }
    .fylia-messages::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 2px; }

    .fylia-msg {
        display: flex;
        gap: 8px;
        max-width: 100%;
        animation: fylia-msgIn .25s ease forwards;
    }
    @keyframes fylia-msgIn {
        from { opacity:0; transform: translateY(6px); }
        to   { opacity:1; transform: translateY(0); }
    }
    .fylia-msg.user { flex-direction: row-reverse; }

    .fylia-msg-avatar {
        width: 28px; height: 28px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; flex-shrink: 0;
        margin-top: 2px;
    }
    .fylia-msg.bot  .fylia-msg-avatar { background: rgba(0,229,192,.1); border: 1px solid rgba(0,229,192,.2); }
    .fylia-msg.user .fylia-msg-avatar { background: rgba(59,130,246,.15); border: 1px solid rgba(59,130,246,.2); font-size:11px; font-weight:700; color:#93c5fd; }

    .fylia-bubble {
        padding: 10px 14px;
        border-radius: 14px;
        font-size: 13px;
        line-height: 1.6;
        max-width: calc(100% - 40px);
        word-break: break-word;
    }
    .fylia-msg.bot  .fylia-bubble {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.07);
        color: #e8edf5;
        border-bottom-left-radius: 4px;
    }
    .fylia-msg.user .fylia-bubble {
        background: rgba(0,229,192,.1);
        border: 1px solid rgba(0,229,192,.2);
        color: #e8edf5;
        border-bottom-right-radius: 4px;
    }

    .fylia-typing {
        display: flex; align-items: center; gap: 4px;
        padding: 10px 14px;
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.07);
        border-radius: 14px;
        border-bottom-left-radius: 4px;
        width: fit-content;
    }
    .fylia-typing span {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: #00e5c0;
        animation: fylia-bounce .9s infinite;
        opacity: .5;
    }
    .fylia-typing span:nth-child(2) { animation-delay: .15s; }
    .fylia-typing span:nth-child(3) { animation-delay: .30s; }
    @keyframes fylia-bounce {
        0%,60%,100% { transform: translateY(0); opacity:.4; }
        30%          { transform: translateY(-5px); opacity:1; }
    }

    .fylia-sugs {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 0 16px 12px;
    }
    .fylia-sug {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 100px;
        padding: 5px 12px;
        font-size: 11px;
        color: #94a3b8;
        cursor: pointer;
        transition: background .2s, border-color .2s, color .2s;
        white-space: nowrap;
    }
    .fylia-sug:hover {
        background: rgba(0,229,192,.08);
        border-color: rgba(0,229,192,.25);
        color: #00e5c0;
    }

    .fylia-input-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px;
        border-top: 1px solid rgba(255,255,255,.07);
        background: rgba(5,8,15,.5);
        flex-shrink: 0;
    }
    .fylia-input {
        flex: 1;
        background: rgba(255,255,255,.05);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 10px;
        padding: 9px 12px;
        font-size: 13px;
        color: #e8edf5;
        font-family: 'DM Sans', sans-serif;
        outline: none;
        resize: none;
        min-height: 38px;
        max-height: 100px;
        transition: border-color .2s, box-shadow .2s;
    }
    .fylia-input:focus {
        border-color: rgba(0,229,192,.35);
        box-shadow: 0 0 0 3px rgba(0,229,192,.06);
    }
    .fylia-input::placeholder { color: #475569; }
    .fylia-send-btn {
        width: 36px; height: 36px;
        border-radius: 10px;
        background: #00e5c0;
        border: none;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
        transition: background .2s, transform .15s, box-shadow .2s;
    }
    .fylia-send-btn:hover:not(:disabled) {
        background: #00ffda;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,229,192,.4);
    }
    .fylia-send-btn:disabled { opacity: .4; cursor: not-allowed; }

    .fylia-footer {
        padding: 6px 14px 10px;
        text-align: center;
        font-size: 10px;
        color: #334155;
        background: rgba(5,8,15,.5);
        border-top: 1px solid rgba(255,255,255,.04);
        flex-shrink: 0;
    }

    @media (max-width: 480px) {
        #fylia-panel { width: calc(100vw - 24px); right: 12px; bottom: 80px; }
        #fylia-btn { right: 16px; bottom: 16px; }
    }
    `;

    const styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    function getIniciales() {
        const av = document.querySelector('.topbar-avatar, .user-avatar');
        return av?.textContent?.trim().substring(0, 2) || '?';
    }

    const btn = document.createElement('button');
    btn.id = 'fylia-btn';
    btn.innerHTML = `<span>✦</span><div class="fylia-pulse"></div>`;
    btn.title = 'Asistente FYLIA';

    const panel = document.createElement('div');
    panel.id = 'fylia-panel';
    panel.innerHTML = `
        <div class="fylia-header">
            <div class="fylia-header-ico">✦</div>
            <div class="fylia-header-info">
                <div class="fylia-header-name">FYLIA</div>
                <div class="fylia-header-status">
                    <div class="fylia-dot"></div>
                    Asistente de FYLCAD · En línea
                </div>
            </div>
            <button class="fylia-close-btn" id="fylia-close">✕</button>
        </div>
        <div class="fylia-messages" id="fylia-msgs"></div>
        <div class="fylia-sugs" id="fylia-sugs"></div>
        <div class="fylia-input-row">
            <textarea class="fylia-input" id="fylia-input"
                placeholder="Escríbeme algo..." rows="1"></textarea>
            <button class="fylia-send-btn" id="fylia-send">➤</button>
        </div>
        <div class="fylia-footer">Powered by FYLCAD IA · claude-haiku</div>
    `;

    document.body.appendChild(btn);
    document.body.appendChild(panel);

    const msgsEl  = document.getElementById('fylia-msgs');
    const inputEl = document.getElementById('fylia-input');
    const sendBtn = document.getElementById('fylia-send');
    const sugsEl  = document.getElementById('fylia-sugs');

    function renderSugs() {
        sugsEl.innerHTML = '';
        sugs.forEach(s => {
            const el = document.createElement('button');
            el.className = 'fylia-sug';
            el.textContent = s;
            el.addEventListener('click', () => {
                inputEl.value = s;
                sugsEl.style.display = 'none';
                enviar();
            });
            sugsEl.appendChild(el);
        });
    }
    renderSugs();

    function agregarMensaje(rol, texto) {
        const iniciales = getIniciales();
        const isBot = rol === 'bot';
        const div = document.createElement('div');
        div.className = `fylia-msg ${rol}`;
        div.innerHTML = `
            <div class="fylia-msg-avatar">${isBot ? '✦' : iniciales}</div>
            <div class="fylia-bubble">${texto.replace(/\n/g, '<br>')}</div>
        `;
        msgsEl.appendChild(div);
        msgsEl.scrollTop = msgsEl.scrollHeight;
        return div;
    }

    function mostrarTyping() {
        const div = document.createElement('div');
        div.className = 'fylia-msg bot';
        div.id = 'fylia-typing-row';
        div.innerHTML = `
            <div class="fylia-msg-avatar">✦</div>
            <div class="fylia-typing">
                <span></span><span></span><span></span>
            </div>
        `;
        msgsEl.appendChild(div);
        msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function quitarTyping() {
        document.getElementById('fylia-typing-row')?.remove();
    }

    async function enviar() {
        const texto = inputEl.value.trim();
        if (!texto || cargando) return;

        sugsEl.style.display = 'none';
        inputEl.value = '';
        inputEl.style.height = 'auto';
        cargando = true;
        sendBtn.disabled = true;

        agregarMensaje('user', texto);
        mostrarTyping();

        historial.push({ role: 'user', content: texto });

        try {
            // ✅ RUTA CORREGIDA: apunta a /FYLCAD/fylcad_ai.php
            const res = await fetch('/FYLCAD/fylcad_ai.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    mensaje:   texto,
                    historial: historial.slice(0, -1),
                    pagina:    PAGINA,
                }),
            });

            const data = await res.json();
            quitarTyping();

            if (data.error) {
                agregarMensaje('bot', '⚠ ' + data.error);
            } else {
                const respuesta = data.respuesta || 'No pude generar una respuesta.';
                agregarMensaje('bot', respuesta);
                historial.push({ role: 'assistant', content: respuesta });
                if (historial.length > 20) historial = historial.slice(-20);
            }
        } catch (e) {
            quitarTyping();
            agregarMensaje('bot', '⚠ Sin conexión. Verifica tu red e intenta de nuevo.');
        }

        cargando = false;
        sendBtn.disabled = false;
        inputEl.focus();
    }

    btn.addEventListener('click', () => {
        abierto = !abierto;
        panel.classList.toggle('fylia-open', abierto);

        if (abierto && msgsEl.children.length === 0) {
            setTimeout(() => {
                agregarMensaje('bot',
                    '¡Hola! Soy FYLIA, tu asistente de FYLCAD. 👋\n¿En qué puedo ayudarte hoy?'
                );
            }, 200);
        }
        if (abierto) setTimeout(() => inputEl.focus(), 300);
    });

    document.getElementById('fylia-close').addEventListener('click', (e) => {
        e.stopPropagation();
        abierto = false;
        panel.classList.remove('fylia-open');
    });

    sendBtn.addEventListener('click', enviar);

    inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            enviar();
        }
    });

    inputEl.addEventListener('input', () => {
        inputEl.style.height = 'auto';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 100) + 'px';
    });

    document.addEventListener('click', (e) => {
        if (abierto && !panel.contains(e.target) && e.target !== btn) {
            abierto = false;
            panel.classList.remove('fylia-open');
        }
    });

})();