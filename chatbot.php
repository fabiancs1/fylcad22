<?php
/**
 * FYLCAD — Chatbot de Respuestas Predefinidas
 * Archivo: chatbot.php
 * Ubicación: C:\xamppp\htdocs\FYLCAD\chatbot.php
 *
 * Sin API, sin costo. Respuestas definidas manualmente.
 * Busca la pregunta más similar usando palabras clave.
 */

header('Content-Type: application/json');

$input   = json_decode(file_get_contents('php://input'), true);
$mensaje = strtolower(trim($input['mensaje'] ?? ''));

if (empty($mensaje)) {
    echo json_encode(['respuesta' => 'Escribe tu pregunta y te ayudo.', 'categoria' => '']);
    exit;
}

// ══════════════════════════════════════════════════════════
// BASE DE CONOCIMIENTO FYLCAD
// Estructura: ['palabras_clave' => [], 'respuesta' => '', 'categoria' => '']
// ══════════════════════════════════════════════════════════
$conocimiento = [

    // ────────────────────────────────────────────────
    // CATEGORÍA 1: TOPOGRAFÍA GENERAL
    // ────────────────────────────────────────────────
    [
        'palabras' => ['que es topografia', 'topografia es', 'definicion topografia', 'que significa topografia'],
        'respuesta' => "📐 **¿Qué es la topografía?**\n\nLa topografía es la ciencia que estudia y describe la superficie terrestre, representando sus formas, accidentes y detalles naturales o artificiales en planos y mapas.\n\nEn la práctica, un topógrafo mide puntos del terreno (coordenadas X, Y, Z) para crear representaciones precisas que sirven como base para proyectos de construcción, urbanismo, agricultura y minería.",
        'categoria' => 'Topografía General'
    ],
    [
        'palabras' => ['que es levantamiento', 'levantamiento topografico', 'como se hace levantamiento'],
        'respuesta' => "🗺️ **Levantamiento topográfico**\n\nEs el proceso de medir y registrar las coordenadas de puntos del terreno mediante instrumentos como estación total, GPS o dron.\n\n**Pasos básicos:**\n• Se ubican estaciones de control\n• Se miden puntos de interés del terreno\n• Se registran coordenadas X (Este), Y (Norte), Z (Cota)\n• Los datos se exportan en formato CSV o texto\n• Se procesan en software como FYLCAD para generar el plano",
        'categoria' => 'Topografía General'
    ],
    [
        'palabras' => ['que es cota', 'cota minima', 'cota maxima', 'que significa cota'],
        'respuesta' => "📏 **¿Qué es una cota?**\n\nLa cota (o coordenada Z) representa la **altura** de un punto sobre el nivel de referencia, generalmente el nivel del mar.\n\n• **Cota mínima:** el punto más bajo del terreno medido\n• **Cota máxima:** el punto más alto del terreno medido\n• **Desnivel:** la diferencia entre cota máxima y mínima\n\nEn FYLCAD puedes ver estas tres métricas automáticamente al procesar tu archivo CSV.",
        'categoria' => 'Topografía General'
    ],
    [
        'palabras' => ['que es desnivel', 'desnivel del terreno', 'diferencia altura'],
        'respuesta' => "⛰️ **Desnivel del terreno**\n\nEl desnivel es la diferencia de altura entre el punto más alto y el punto más bajo de un terreno.\n\n**Fórmula:** Desnivel = Cota máxima − Cota mínima\n\n**Ejemplo:** Si la cota máxima es 397.7 m y la mínima es 376.3 m, el desnivel es **21.4 metros**.\n\nEn FYLCAD este valor se calcula automáticamente y aparece en el panel de resultados.",
        'categoria' => 'Topografía General'
    ],
    [
        'palabras' => ['que es tin', 'triangulacion', 'delaunay', 'red de triangulos', 'que es la red'],
        'respuesta' => "🔺 **Triangulación Delaunay y red TIN**\n\nLa **red TIN** (Triangulated Irregular Network) es un modelo digital del terreno formado por triángulos que conectan los puntos medidos.\n\nFYLCAD usa el algoritmo de **Delaunay** para construirla. Este algoritmo crea triángulos lo más equiláteros posible, maximizando la precisión del modelo.\n\n**¿Para qué sirve?**\n• Calcular el área real del terreno\n• Estimar volúmenes de corte y relleno\n• Generar curvas de nivel\n• Visualizar el terreno en 3D",
        'categoria' => 'Topografía General'
    ],
    [
        'palabras' => ['curvas de nivel', 'que son curvas', 'isolineas', 'contornos'],
        'respuesta' => "〰️ **Curvas de nivel**\n\nSon líneas que unen puntos del terreno que tienen la misma cota (altura). Permiten representar el relieve de un terreno en un plano 2D.\n\n**Cómo interpretarlas:**\n• Curvas juntas = terreno empinado\n• Curvas separadas = terreno plano\n• Curvas cerradas = montículo o depresión\n\nEn FYLCAD las curvas de nivel se generan automáticamente al procesar el levantamiento.",
        'categoria' => 'Topografía General'
    ],
    [
        'palabras' => ['que es planimetria', 'planimetria', 'altimetria', 'diferencia planimetria altimetria'],
        'respuesta' => "📋 **Planimetría vs Altimetría**\n\n• **Planimetría:** representa los objetos del terreno en un plano horizontal (coordenadas X e Y). No considera la altura.\n\n• **Altimetría:** representa las variaciones de altura del terreno (coordenada Z o cota).\n\nEn un levantamiento topográfico completo se trabajan las tres coordenadas: X, Y y Z.",
        'categoria' => 'Topografía General'
    ],
    [
        'palabras' => ['que es csv', 'formato csv', 'como es el archivo', 'formato del archivo'],
        'respuesta' => "📄 **Formato CSV para FYLCAD**\n\nCSV significa *Comma Separated Values* (valores separados por comas). Es el formato que FYLCAD usa para importar los puntos del levantamiento.\n\n**Formato requerido:**\n```\nX,Y,Z\n1384700.5,1136300.2,376.45\n1384720.3,1136315.8,377.12\n```\n\n**Columnas:**\n• X = coordenada Este\n• Y = coordenada Norte\n• Z = cota o altura\n\nPuedes exportar este formato desde Excel, AutoCAD Civil 3D o tu estación total.",
        'categoria' => 'Topografía General'
    ],
    [
        'palabras' => ['estacion total', 'gps topografico', 'instrumento', 'equipo topografico'],
        'respuesta' => "🔭 **Instrumentos topográficos**\n\n• **Estación total:** mide ángulos y distancias con alta precisión. Es el instrumento más usado en levantamientos de detalle.\n\n• **GPS topográfico (GNSS):** determina coordenadas usando satélites. Ideal para trabajos de gran extensión.\n\n• **Dron con fotogrametría:** genera nubes de puntos por fotografías aéreas. Muy eficiente para grandes áreas.\n\nCualquiera de estos equipos puede exportar los puntos en formato CSV compatible con FYLCAD.",
        'categoria' => 'Topografía General'
    ],

    // ────────────────────────────────────────────────
    // CATEGORÍA 2: CÓMO USAR FYLCAD
    // ────────────────────────────────────────────────
    [
        'palabras' => ['como uso fylcad', 'como funciona fylcad', 'como empiezo', 'primeros pasos', 'como inicio'],
        'respuesta' => "🚀 **¿Cómo usar FYLCAD?**\n\n**Paso 1:** Regístrate o inicia sesión en la plataforma.\n\n**Paso 2:** Ve al módulo de proyectos y crea uno nuevo.\n\n**Paso 3:** Carga tu archivo CSV con las coordenadas del levantamiento.\n\n**Paso 4:** FYLCAD procesa automáticamente los puntos y genera:\n• Visualización 3D del terreno\n• Métricas de área, volumen y desnivel\n• Red de triangulación TIN\n• Curvas de nivel\n\n**Paso 5:** Genera la cotización de obra o exporta el plano en PDF.",
        'categoria' => 'Uso de FYLCAD'
    ],
    [
        'palabras' => ['como cargo csv', 'subir archivo', 'cargar archivo', 'importar puntos', 'como subo'],
        'respuesta' => "📤 **Cómo cargar tu archivo CSV**\n\n1. Inicia sesión en FYLCAD\n2. Ve a **Mis Proyectos** → **Nuevo Proyecto**\n3. Asigna un nombre al proyecto\n4. Haz clic en **Seleccionar archivo** y elige tu CSV\n5. Haz clic en **Procesar**\n\n**Requisitos del archivo:**\n• Formato: .csv o .txt\n• Columnas: X, Y, Z (con encabezado)\n• Separador: coma (,)\n• Plan Free: máximo 50 puntos\n• Plan Premium: puntos ilimitados",
        'categoria' => 'Uso de FYLCAD'
    ],
    [
        'palabras' => ['como exporto', 'exportar pdf', 'descargar plano', 'guardar plano', 'exportar resultado'],
        'respuesta' => "📥 **Exportar plano y resultados**\n\nDesde el visor de tu proyecto puedes exportar:\n\n• **PNG:** imagen del plano topográfico (disponible en todos los planes)\n• **PDF:** reporte profesional con métricas, cotización y plano (solo Plan Premium)\n\n**Pasos:**\n1. Abre tu proyecto procesado\n2. Haz clic en el botón **Exportar**\n3. Elige el formato deseado\n4. El archivo se descarga automáticamente",
        'categoria' => 'Uso de FYLCAD'
    ],
    [
        'palabras' => ['visor 3d', 'como veo en 3d', 'visualizacion', 'como roto el modelo', 'navegar terreno'],
        'respuesta' => "🌐 **Visor 3D de FYLCAD**\n\nUna vez procesado tu levantamiento, el visor 3D te permite:\n\n• **Rotar:** clic izquierdo + arrastrar\n• **Zoom:** rueda del mouse\n• **Desplazar:** clic derecho + arrastrar\n• **Ver curvas de nivel:** activar desde el panel lateral\n• **Ver simbología:** identificar tipos de punto por color\n\nEl visor funciona directamente en el navegador, sin instalar nada adicional.",
        'categoria' => 'Uso de FYLCAD'
    ],
    [
        'palabras' => ['error al cargar', 'no carga', 'fallo', 'error archivo', 'problema csv'],
        'respuesta' => "❗ **Errores comunes al cargar archivos**\n\n**El archivo no carga:**\n• Verifica que sea .csv o .txt\n• Asegúrate que tenga encabezado X,Y,Z\n• Revisa que el separador sea coma (,) no punto y coma (;)\n\n**El modelo no se ve bien:**\n• Verifica que las coordenadas estén en el sistema correcto\n• Asegúrate que Z no sea cero en todos los puntos\n\n**Límite de puntos:**\n• Plan Free acepta máximo 50 puntos\n• Actualiza a Premium para procesar levantamientos completos",
        'categoria' => 'Uso de FYLCAD'
    ],

    // ────────────────────────────────────────────────
    // CATEGORÍA 3: INTERPRETACIÓN DE RESULTADOS
    // ────────────────────────────────────────────────
    [
        'palabras' => ['que significa area', 'como interpreto area', 'area m2', 'area del terreno'],
        'respuesta' => "📐 **Interpretación del Área**\n\nEl área que muestra FYLCAD es el **área superficial del polígono** formado por los puntos del levantamiento, calculada con la fórmula de Gauss (Shoelace).\n\n**¿Qué significa?**\n• Es la superficie horizontal proyectada del terreno\n• Se expresa en metros cuadrados (m²)\n• Para convertir a hectáreas: divide entre 10.000\n\n**Ejemplo:** 13.614 m² = 1.36 hectáreas\n\n⚠️ No confundir con el área 3D que incluye las pendientes del terreno.",
        'categoria' => 'Resultados'
    ],
    [
        'palabras' => ['que significa volumen', 'como interpreto volumen', 'volumen m3', 'corte relleno'],
        'respuesta' => "🏔️ **Interpretación del Volumen**\n\nEl volumen en FYLCAD representa la **cantidad de material (tierra)** entre el terreno natural y un plano de referencia horizontal.\n\n**Usos principales:**\n• Estimar cuánto material hay que mover en una obra\n• Calcular el costo de movimiento de tierra\n• Diseñar plataformas y terrazas\n\nSe expresa en metros cúbicos (m³).\n\n**Ejemplo:** 154.437 m³ significa que se necesitaría mover esa cantidad de tierra para nivelar el terreno completamente.",
        'categoria' => 'Resultados'
    ],
    [
        'palabras' => ['que es perimetro', 'perimetro del terreno', 'como mido perimetro'],
        'respuesta' => "📏 **Perímetro del terreno**\n\nEs la longitud total del contorno del polígono topográfico. Se calcula sumando las distancias entre todos los puntos consecutivos del levantamiento.\n\nSe expresa en metros lineales (m) y es útil para:\n• Calcular el costo de cerramiento (malla, muro, cerca)\n• Delimitar el predio en planos\n• Verificar medidas con escrituras o linderos",
        'categoria' => 'Resultados'
    ],

    // ────────────────────────────────────────────────
    // CATEGORÍA 4: COTIZACIÓN AUTOMÁTICA
    // ────────────────────────────────────────────────
    [
        'palabras' => ['como genero cotizacion', 'cotizacion automatica', 'como cotizo', 'generar presupuesto'],
        'respuesta' => "💰 **Generar cotización automática**\n\nDespués de procesar tu levantamiento:\n\n1. Ve al panel de resultados de tu proyecto\n2. Haz clic en **Generar Cotización**\n3. Ajusta las tarifas según el mercado local:\n   • Tarifa movimiento de tierra ($/m³)\n   • Tarifa nivelación ($/m²)\n   • Tarifa cerramiento ($/ml)\n4. FYLCAD calcula automáticamente el costo total\n5. Exporta el presupuesto en PDF (Plan Premium)",
        'categoria' => 'Cotización'
    ],
    [
        'palabras' => ['que calcula cotizacion', 'que incluye cotizacion', 'items cotizacion', 'que cubre el presupuesto'],
        'respuesta' => "📋 **¿Qué calcula la cotización de FYLCAD?**\n\nLa cotización incluye tres ítems principales:\n\n**1. Movimiento de tierra**\n• Basado en el volumen (m³) del levantamiento\n• Incluye corte, cargue y transporte de material\n\n**2. Nivelación del terreno**\n• Basado en el área (m²)\n• Incluye compactación y conformación de la plataforma\n\n**3. Cerramiento del predio**\n• Basado en el perímetro (ml)\n• Estimado para malla o cerramiento convencional\n\nCada tarifa es configurable por el usuario según los precios del mercado local.",
        'categoria' => 'Cotización'
    ],
    [
        'palabras' => ['como cambio tarifa', 'modificar tarifa', 'precio por metro', 'ajustar precio'],
        'respuesta' => "⚙️ **Ajustar tarifas de cotización**\n\nLas tarifas predeterminadas en FYLCAD son:\n• Movimiento de tierra: $8.50 USD/m³\n• Nivelación: $3.20 USD/m²\n• Cerramiento: $45.00 USD/ml\n\nPuedes modificarlas directamente en el formulario de cotización antes de generarla. Estas tarifas varían según:\n• La ciudad y región\n• El tipo de terreno\n• La distancia de acarreo\n• El costo de mano de obra local",
        'categoria' => 'Cotización'
    ],

    // ────────────────────────────────────────────────
    // CATEGORÍA 5: INTERMEDIACIÓN CON PROVEEDORES
    // ────────────────────────────────────────────────
    [
        'palabras' => ['proveedores', 'como contacto proveedor', 'conseguir materiales', 'maquinaria', 'intermediacion'],
        'respuesta' => "🤝 **Intermediación con proveedores**\n\nFYLCAD conecta a topógrafos e ingenieros con proveedores verificados de:\n• Materiales de construcción (cemento, acero, gravilla)\n• Maquinaria pesada (retroexcavadoras, motoniveladoras)\n• Mano de obra especializada\n\n**¿Cómo funciona?**\n1. Genera tu cotización en FYLCAD\n2. Accede al directorio de proveedores\n3. Filtra por tipo de servicio o material\n4. Solicita cotización directamente desde la plataforma\n5. FYLCAD te conecta con el proveedor",
        'categoria' => 'Proveedores'
    ],
    [
        'palabras' => ['costo intermediacion', 'comision proveedor', 'cuanto cobra fylcad proveedor', 'gratis proveedores'],
        'respuesta' => "💲 **Costos del servicio de intermediación**\n\n• **Consultar el directorio:** gratuito para todos los usuarios\n• **Solicitar cotización a proveedores:** gratuito\n• **Intermediación exitosa (cierre del negocio):** 3% del valor del contrato\n• **Plan Premium:** incluye 5 solicitudes sin comisión por mes\n\nSolo se cobra la comisión cuando el negocio se cierra efectivamente entre el cliente y el proveedor.",
        'categoria' => 'Proveedores'
    ],
    [
        'palabras' => ['como registro proveedor', 'ser proveedor', 'ofrecer servicios', 'registrar empresa'],
        'respuesta' => "🏢 **¿Eres proveedor y quieres registrarte?**\n\nSi tienes una empresa de materiales, maquinaria o mano de obra y quieres aparecer en el directorio de FYLCAD:\n\n📧 Escríbenos a: **proveedores@fylcad.com**\n\nIncluye:\n• Nombre de la empresa\n• Tipo de servicio o producto\n• Ciudad de operación\n• Teléfono de contacto\n\nLa verificación toma entre 24 y 72 horas hábiles.",
        'categoria' => 'Proveedores'
    ],

    // ────────────────────────────────────────────────
    // CATEGORÍA 6: SUSCRIPCIONES
    // ────────────────────────────────────────────────
    [
        'palabras' => ['planes fylcad', 'que planes hay', 'free vs premium', 'diferencia planes', 'que incluye cada plan'],
        'respuesta' => "📦 **Planes de FYLCAD**\n\n**Plan Free (Gratis)**\n• Hasta 50 puntos por proyecto\n• Visualización 3D básica\n• Exportación en PNG\n• 3 proyectos activos\n\n**Plan Premium ($49.900 COP/mes)**\n• Puntos ilimitados\n• Exportación en PDF profesional\n• Cotización automática completa\n• Historial de proyectos ilimitado\n• Acceso al directorio de proveedores con 5 solicitudes sin comisión\n• Soporte prioritario\n• Período de prueba: 7 días gratis",
        'categoria' => 'Suscripciones'
    ],
    [
        'palabras' => ['como actualizo plan', 'como compro premium', 'upgrade premium', 'como pago', 'suscribirme'],
        'respuesta' => "⬆️ **Cómo actualizar a Plan Premium**\n\n1. Inicia sesión en FYLCAD\n2. Ve a **Mi Perfil** → **Planes**\n3. Haz clic en **Actualizar a Premium**\n4. Elige el plan mensual o anual\n5. Completa el pago con tarjeta de crédito/débito o PSE\n\n**Precios:**\n• Mensual: $49.900 COP/mes\n• Anual: $449.900 COP/año (ahorro del 25%)\n\n🎁 Los primeros 7 días son gratis sin cargo.",
        'categoria' => 'Suscripciones'
    ],
    [
        'palabras' => ['cuanto cuesta', 'precio fylcad', 'cuanto vale', 'es gratis fylcad', 'costo suscripcion'],
        'respuesta' => "💳 **Precios de FYLCAD**\n\n• **Plan Free:** completamente gratis, siempre\n• **Plan Premium mensual:** $49.900 COP/mes\n• **Plan Premium anual:** $449.900 COP/año\n• **Cotización individual (Free):** $9.900 COP por cotización\n• **Paquete 10 cotizaciones:** $79.900 COP\n\nTodos los planes incluyen período de prueba de 7 días con acceso completo.",
        'categoria' => 'Suscripciones'
    ],
    [
        'palabras' => ['cancelar suscripcion', 'como cancelo', 'cancelar plan', 'dejar premium'],
        'respuesta' => "❌ **Cancelar suscripción**\n\nPuedes cancelar tu Plan Premium en cualquier momento:\n\n1. Ve a **Mi Perfil** → **Planes**\n2. Haz clic en **Cancelar suscripción**\n3. Confirma la cancelación\n\nTu plan Premium sigue activo hasta el final del período pagado. No se hacen reembolsos parciales.\n\n¿Tienes algún problema con tu suscripción? Escríbenos a **soporte@fylcad.com**",
        'categoria' => 'Suscripciones'
    ],
    [
        'palabras' => ['prueba gratis', 'trial', '7 dias', 'periodo de prueba', 'probar premium'],
        'respuesta' => "🎁 **Período de prueba gratuito**\n\nFYLCAD ofrece **7 días de prueba gratuita** del Plan Premium con acceso completo:\n• Puntos ilimitados\n• Exportación PDF\n• Cotización automática\n• Directorio de proveedores\n\n**¿Cómo activarlo?**\n1. Regístrate en FYLCAD\n2. Ve a Planes\n3. Selecciona Premium\n4. No se cobra nada durante los primeros 7 días\n5. Puedes cancelar antes de que terminen sin costo",
        'categoria' => 'Suscripciones'
    ],

    // ────────────────────────────────────────────────
    // CATEGORÍA 7: PREGUNTAS GENERALES
    // ────────────────────────────────────────────────
    [
        'palabras' => ['que es fylcad', 'para que sirve fylcad', 'fylcad es', 'como funciona la plataforma'],
        'respuesta' => "🌐 **¿Qué es FYLCAD?**\n\nFYLCAD es una plataforma SaaS (Software como Servicio) de **topografía digital** desarrollada en Colombia.\n\nPermite a topógrafos, ingenieros y constructoras:\n• Procesar levantamientos topográficos desde el navegador\n• Visualizar terrenos en 3D sin instalar software\n• Generar cotizaciones de obra automáticamente\n• Conectarse con proveedores de materiales y maquinaria\n\nEstá disponible desde cualquier dispositivo con navegador web.",
        'categoria' => 'General'
    ],
    [
        'palabras' => ['quien puede usar fylcad', 'para quien es', 'perfil usuario', 'a quien va dirigido'],
        'respuesta' => "👥 **¿Quién puede usar FYLCAD?**\n\n• **Topógrafos independientes:** procesa tus levantamientos y presenta resultados profesionales a tus clientes\n• **Ingenieros civiles:** genera presupuestos de movimiento de tierra en minutos\n• **Constructoras:** gestiona múltiples proyectos y conecta con proveedores\n• **Estudiantes de topografía:** aprende procesamiento digital sin software costoso\n• **Municipios y entidades públicas:** digitaliza y gestiona levantamientos de zonas públicas",
        'categoria' => 'General'
    ],
    [
        'palabras' => ['como me registro', 'crear cuenta', 'registrarme', 'como creo cuenta'],
        'respuesta' => "✅ **Crear cuenta en FYLCAD**\n\n1. Ve a la página principal de FYLCAD\n2. Haz clic en **Registrarse**\n3. Ingresa tu nombre, correo y contraseña\n4. Confirma tu correo electrónico\n5. ¡Listo! Ya puedes usar el Plan Free\n\nEl registro es completamente gratuito y no requiere tarjeta de crédito.",
        'categoria' => 'General'
    ],
    [
        'palabras' => ['olvide contrasena', 'recuperar contrasena', 'no puedo entrar', 'reset password'],
        'respuesta' => "🔑 **Recuperar contraseña**\n\n1. Ve a la página de inicio de sesión\n2. Haz clic en **¿Olvidaste tu contraseña?**\n3. Ingresa tu correo registrado\n4. Recibirás un enlace de recuperación en tu correo\n5. Haz clic en el enlace y crea una nueva contraseña\n\nEl enlace de recuperación es válido por 1 hora.\n\n¿No te llega el correo? Revisa tu carpeta de spam o escríbenos a **soporte@fylcad.com**",
        'categoria' => 'General'
    ],
    [
        'palabras' => ['contacto', 'soporte', 'ayuda', 'problema', 'reportar error', 'escribir fylcad'],
        'respuesta' => "📬 **Contacto y soporte FYLCAD**\n\n• **Soporte general:** soporte@fylcad.com\n• **Proveedores:** proveedores@fylcad.com\n• **Alianzas:** alianzas@fylcad.com\n• **WhatsApp:** +57 310 XXX XXXX\n• **Horario:** lunes a viernes 8am - 6pm\n• **Ubicación:** Cúcuta, Norte de Santander, Colombia\n\nTambién puedes usar este chat para resolver dudas básicas.",
        'categoria' => 'General'
    ],
    [
        'palabras' => ['cuantos puntos necesito', 'minimo puntos', 'puntos necesarios', 'cuantos puntos para'],
        'respuesta' => "📊 **¿Cuántos puntos necesito?**\n\nDepende del área y la precisión requerida:\n\n• **Lote pequeño (< 500 m²):** 20-50 puntos (Plan Free)\n• **Lote mediano (500-5.000 m²):** 50-200 puntos\n• **Terreno grande (> 5.000 m²):** 200-1.000+ puntos\n• **Levantamiento de detalle:** 500+ puntos\n\nEl mínimo para procesar en FYLCAD es **3 puntos**. Para resultados precisos se recomienda tomar puntos en todos los cambios de pendiente y en los vértices del predio.",
        'categoria' => 'General'
    ],
    [
        'palabras' => ['hola', 'buenos dias', 'buenas', 'hey', 'saludos'],
        'respuesta' => "👋 ¡Hola! Bienvenido a FYLCAD.\n\nSoy el asistente virtual de la plataforma. Puedo ayudarte con:\n• 📐 Conceptos de topografía\n• 🚀 Cómo usar FYLCAD\n• 💰 Cotizaciones y precios\n• 🤝 Intermediación con proveedores\n• 📦 Información sobre planes\n\n¿Sobre qué quieres saber?",
        'categoria' => 'General'
    ],
    [
        'palabras' => ['gracias', 'muchas gracias', 'ok gracias', 'listo gracias'],
        'respuesta' => "😊 ¡Con gusto! Si tienes más preguntas sobre FYLCAD o topografía, aquí estoy.\n\n¿Hay algo más en lo que pueda ayudarte?",
        'categoria' => 'General'
    ],
];

// ══════════════════════════════════════════════════════════
// MOTOR DE BÚSQUEDA POR PALABRAS CLAVE
// ══════════════════════════════════════════════════════════
function buscarRespuesta(string $mensaje, array $conocimiento): array {
    $mejor        = null;
    $maxPuntos    = 0;

    foreach ($conocimiento as $item) {
        $puntos = 0;
        foreach ($item['palabras'] as $frase) {
            // Coincidencia exacta de frase
            if (strpos($mensaje, $frase) !== false) {
                $puntos += 10;
            }
            // Coincidencia por palabras individuales
            $palabras = explode(' ', $frase);
            foreach ($palabras as $palabra) {
                if (strlen($palabra) > 3 && strpos($mensaje, $palabra) !== false) {
                    $puntos += 2;
                }
            }
        }
        if ($puntos > $maxPuntos) {
            $maxPuntos = $puntos;
            $mejor     = $item;
        }
    }

    // Si no encontró nada relevante
    if ($maxPuntos < 2 || $mejor === null) {
        return [
            'respuesta' => "🤔 No encontré una respuesta específica para eso.\n\nPuedo ayudarte con:\n• Topografía (conceptos, levantamientos, coordenadas)\n• Uso de FYLCAD (carga de CSV, resultados, exportar)\n• Cotizaciones de obra\n• Proveedores\n• Planes y precios\n\nIntenta reformular tu pregunta o escríbenos a **soporte@fylcad.com**",
            'categoria' => 'Sin resultado'
        ];
    }

    return [
        'respuesta' => $mejor['respuesta'],
        'categoria' => $mejor['categoria']
    ];
}

// ── Buscar y responder ─────────────────────────────────────
$resultado = buscarRespuesta($mensaje, $conocimiento);
echo json_encode($resultado);
