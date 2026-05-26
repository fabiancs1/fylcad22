/**
 * FYLCAD — Chatbot sin API
 * Archivo: chatbot.js
 * Ubicación: C:\xamppp\htdocs\FYLCAD\js\chatbot.js
 */

(function () {
    'use strict';

    const CONFIG = {
      endpoint: '/FYLCAD%20(2)/FYLCAD/chatbot.php',
        sugerencias: [
            '¿Qué es FYLCAD?',
            '¿Cómo cargo un CSV?',
            '¿Qué planes hay?',
            '¿Cómo genero una cotización?',
            '¿Qué es la triangulación Delaunay?',
            '¿Cómo contacto proveedores?',
        ],
    };

    let abierto  = false;
    let cargando = false;

    function crearWidget() {
        const w = document.createElement('div');
        w.id = 'fylbot-widget';
        w.innerHTML = `
            <div id="fylbot-chat" style="display:none;">
                <div id="fylbot-header">
                    <div id="fylbot-avatar">📐</div>
                    <div id="fylbot-info">
                        <h4>Asistente FYLCAD</h4>
                        <span>Topografía digital · Sin conexión requerida</span>
                    </div>
                    <button id="fylbot-close">✕</button>
                </div>
                <div id="fylbot-mensajes"></div>
                <div id="fylbot-sugerencias"></div>
                <div id="fylbot-input-area">
                    <textarea id="fylbot-input" rows="1" placeholder="¿En qué te puedo ayudar?"></textarea>
                    <button id="fylbot-enviar">➤</button>
                </div>
            </div>
            <button id="fylbot-btn" title="Asistente FYLCAD">📐</button>
        `;
        document.body.appendChild(w);
    }

    function toggleChat() {
        abierto = !abierto;
        const chat = document.getElementById('fylbot-chat');
        chat.style.display = abierto ? 'flex' : 'none';
        if (abierto) {
            const msgs = document.getElementById('fylbot-mensajes');
            if (msgs.children.length === 0) {
                agregarMensaje('👋 ¡Hola! Soy el asistente de **FYLCAD**.\n\nPuedo ayudarte con topografía, uso de la plataforma, cotizaciones y planes.\n\n¿Sobre qué quieres saber? 📐', 'bot');
                mostrarSugerencias();
            }
            setTimeout(() => document.getElementById('fylbot-input').focus(), 100);
        }
    }

    function mostrarSugerencias() {
        const cont = document.getElementById('fylbot-sugerencias');
        cont.innerHTML = '';
        CONFIG.sugerencias.forEach(texto => {
            const btn = document.createElement('button');
            btn.className   = 'fylbot-sugerencia';
            btn.textContent = texto;
            btn.onclick     = () => { cont.innerHTML = ''; enviar(texto); };
            cont.appendChild(btn);
        });
    }

    function agregarMensaje(texto, tipo, categoria) {
        const cont = document.getElementById('fylbot-mensajes');
        const div  = document.createElement('div');
        div.className  = `fylbot-msg ${tipo}`;
        div.innerHTML  = texto
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>')
            .replace(/```[\s\S]*?```/g, m => `<code style="display:block;background:#0d1117;padding:8px;border-radius:6px;font-size:11px;margin-top:6px;">${m.replace(/```/g,'')}</code>`);
        cont.appendChild(div);

        if (categoria && categoria !== 'Sin resultado') {
            const cat = document.createElement('div');
            cat.className   = 'fylbot-categoria';
            cat.textContent = '📁 ' + categoria;
            cont.appendChild(cat);
        }

        cont.scrollTop = cont.scrollHeight;
    }

    function mostrarTyping() {
        const cont = document.getElementById('fylbot-mensajes');
        const div  = document.createElement('div');
        div.className = 'fylbot-typing';
        div.id        = 'fylbot-typing';
        div.innerHTML = '<span></span><span></span><span></span>';
        cont.appendChild(div);
        cont.scrollTop = cont.scrollHeight;
    }

    function ocultarTyping() {
        const t = document.getElementById('fylbot-typing');
        if (t) t.remove();
    }

    async function enviar(texto) {
        texto = (texto || document.getElementById('fylbot-input').value).trim();
        if (!texto || cargando) return;

        document.getElementById('fylbot-input').value = '';
        agregarMensaje(texto, 'usuario');

        cargando = true;
        document.getElementById('fylbot-enviar').disabled = true;
        mostrarTyping();

        // Pequeño delay para simular "pensando"
        await new Promise(r => setTimeout(r, 400));

        try {
            const resp = await fetch(CONFIG.endpoint, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ mensaje: texto }),
            });
            const data = await resp.json();
            ocultarTyping();
            agregarMensaje(data.respuesta, 'bot', data.categoria);
        } catch {
            ocultarTyping();
            agregarMensaje('❌ Error de conexión con el servidor.', 'bot');
        }

        cargando = false;
        document.getElementById('fylbot-enviar').disabled = false;
        document.getElementById('fylbot-input').focus();
    }

    function init() {
        crearWidget();
        document.getElementById('fylbot-btn').addEventListener('click', toggleChat);
        document.getElementById('fylbot-close').addEventListener('click', toggleChat);
        document.getElementById('fylbot-enviar').addEventListener('click', () => enviar());
        document.getElementById('fylbot-input').addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); }
        });
        document.getElementById('fylbot-input').addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
