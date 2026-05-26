/**
 * FYLCAD — Asistente Topográfico FilBot
 * Archivo: asistente.js
 * Ubicación: C:\xamppp\htdocs\FYLCAD\js\asistente.js
 */

(function () {
    'use strict';

    // ── Configuración ──────────────────────────────────────
    const CONFIG = {
        endpoint:    '/FYLCAD/asistente.php',
        maxHistorial: 10,
        sugerencias: [
            '¿Cómo cargo un CSV?',
            '¿Qué es la triangulación Delaunay?',
            '¿Cómo se calcula el volumen?',
            '¿Qué incluye el plan Premium?',
            '¿Cómo genero una cotización?',
        ],
    };

    // ── Estado ─────────────────────────────────────────────
    let abierto   = false;
    let cargando  = false;
    let historial = [];

    // ── Crear HTML del widget ──────────────────────────────
    function crearWidget() {
        const widget = document.createElement('div');
        widget.id = 'filbot-widget';
        widget.innerHTML = `
            <!-- Ventana del chat (oculta por defecto) -->
            <div id="filbot-chat" style="display:none;">

                <!-- Header -->
                <div id="filbot-header">
                    <div id="filbot-avatar">🗺️</div>
                    <div id="filbot-info">
                        <h4>FilBot</h4>
                        <span>Asistente topográfico FYLCAD</span>
                    </div>
                    <button id="filbot-close" title="Cerrar">✕</button>
                </div>

                <!-- Mensajes -->
                <div id="filbot-mensajes"></div>

                <!-- Sugerencias -->
                <div id="filbot-sugerencias"></div>

                <!-- Input -->
                <div id="filbot-input-area">
                    <textarea
                        id="filbot-input"
                        rows="1"
                        placeholder="Pregunta sobre topografía o FYLCAD..."
                    ></textarea>
                    <button id="filbot-enviar" title="Enviar">
                        <svg viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Botón flotante -->
            <button id="filbot-btn" title="Abrir asistente FilBot">
                <svg viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                </svg>
            </button>
        `;
        document.body.appendChild(widget);
    }

    // ── Mostrar/ocultar chat ───────────────────────────────
    function toggleChat() {
        abierto = !abierto;
        const chat = document.getElementById('filbot-chat');
        chat.style.display = abierto ? 'flex' : 'none';
        if (abierto && historial.length === 0) {
            mostrarBienvenida();
            mostrarSugerencias();
        }
        if (abierto) {
            setTimeout(() => {
                document.getElementById('filbot-input').focus();
            }, 100);
        }
    }

    // ── Mensaje de bienvenida ──────────────────────────────
    function mostrarBienvenida() {
        agregarMensaje(
            '👋 Hola, soy **FilBot**, tu asistente de topografía en FYLCAD.\n\n' +
            'Puedo ayudarte con:\n' +
            '• Cómo usar la plataforma\n' +
            '• Conceptos de topografía digital\n' +
            '• Interpretar tus resultados\n' +
            '• Generar cotizaciones de obra\n\n' +
            '¿En qué te puedo ayudar hoy? 📐',
            'bot'
        );
    }

    // ── Mostrar sugerencias ────────────────────────────────
    function mostrarSugerencias() {
        const cont = document.getElementById('filbot-sugerencias');
        cont.innerHTML = '';
        CONFIG.sugerencias.forEach(texto => {
            const btn = document.createElement('button');
            btn.className   = 'filbot-sugerencia';
            btn.textContent = texto;
            btn.onclick     = () => {
                cont.innerHTML = '';
                enviarMensaje(texto);
            };
            cont.appendChild(btn);
        });
    }

    // ── Agregar mensaje al chat ────────────────────────────
    function agregarMensaje(texto, tipo) {
        const cont = document.getElementById('filbot-mensajes');
        const div  = document.createElement('div');
        div.className = `filbot-msg ${tipo}`;

        // Formateo básico: **negrita**, saltos de línea, bullets
        div.innerHTML = texto
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>')
            .replace(/^• /gm, '&bull; ');

        cont.appendChild(div);
        cont.scrollTop = cont.scrollHeight;
        return div;
    }

    // ── Indicador de escritura ─────────────────────────────
    function mostrarTyping() {
        const cont = document.getElementById('filbot-mensajes');
        const div  = document.createElement('div');
        div.className = 'filbot-typing';
        div.id        = 'filbot-typing';
        div.innerHTML = '<span></span><span></span><span></span>';
        cont.appendChild(div);
        cont.scrollTop = cont.scrollHeight;
    }

    function ocultarTyping() {
        const t = document.getElementById('filbot-typing');
        if (t) t.remove();
    }

    // ── Enviar mensaje a la API ────────────────────────────
    async function enviarMensaje(texto) {
        texto = texto.trim();
        if (!texto || cargando) return;

        // Limpiar input
        const input = document.getElementById('filbot-input');
        input.value = '';
        input.style.height = 'auto';

        // Mostrar mensaje del usuario
        agregarMensaje(texto, 'usuario');

        // Agregar al historial
        historial.push({ rol: 'user', contenido: texto });
        if (historial.length > CONFIG.maxHistorial) {
            historial = historial.slice(-CONFIG.maxHistorial);
        }

        // Bloquear input y mostrar typing
        cargando = true;
        document.getElementById('filbot-enviar').disabled = true;
        mostrarTyping();

        try {
            const resp = await fetch(CONFIG.endpoint, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    mensaje:   texto,
                    historial: historial.slice(0, -1), // sin el último (ya lo mandamos)
                }),
            });

            const data = await resp.json();
            ocultarTyping();

            if (data.error) {
                agregarMensaje('❌ ' + data.error, 'error');
            } else {
                agregarMensaje(data.respuesta, 'bot');
                historial.push({ rol: 'assistant', contenido: data.respuesta });
            }

        } catch (err) {
            ocultarTyping();
            agregarMensaje('❌ Error de conexión. Verifica que el servidor esté activo.', 'error');
        }

        // Desbloquear input
        cargando = false;
        document.getElementById('filbot-enviar').disabled = false;
        input.focus();
    }

    // ── Auto-resize del textarea ───────────────────────────
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 100) + 'px';
    }

    // ── Inicializar ────────────────────────────────────────
    function init() {
        crearWidget();

        // Eventos
        document.getElementById('filbot-btn').addEventListener('click', toggleChat);
        document.getElementById('filbot-close').addEventListener('click', toggleChat);

        document.getElementById('filbot-enviar').addEventListener('click', () => {
            const val = document.getElementById('filbot-input').value;
            enviarMensaje(val);
        });

        document.getElementById('filbot-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const val = document.getElementById('filbot-input').value;
                enviarMensaje(val);
            }
        });

        document.getElementById('filbot-input').addEventListener('input', function () {
            autoResize(this);
        });
    }

    // ── Arrancar cuando el DOM esté listo ─────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
