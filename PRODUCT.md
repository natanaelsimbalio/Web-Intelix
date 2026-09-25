# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Mix institucional + PyME, sostenido por igual (confirmado):

- **PyME / empresa privada mediana** (ej. Aceros Coco, Favareto, Carla Chiani Negocios
  Inmobiliarios) que llega buscando resolver un problema concreto: automatizar un proceso,
  modernizar su software, adoptar IA local, o resolver telefonía/VoIP.
- **Sector público e institucional** (Gobierno de San Juan, Universidad del Gran Rosario,
  Universidad Adventista del Plata, Sanatorio Adventista del Plata) evaluando a Intelix como
  proveedor formal — a menudo en el marco de una licitación o evaluación donde credibilidad,
  trayectoria y certificaciones pesan más que el copy de marketing.

El sitio tiene que sostener ambas lecturas a la vez: rápido y concreto para quien ya sabe
qué necesita, y con suficiente evidencia (años, certificaciones, clientes reales, partners)
para quien tiene que justificar la elección ante otros.

## Product Purpose

Sitio institucional de Intelix Ingeniería & Telecomunicaciones — no es un producto SaaS,
es la puerta de entrada a una consultora de ingeniería real (Rosario, Argentina, desde 2004).
Existe para que un visitante entienda en qué es buena la empresa, confíe en su trayectoria,
y tenga una vía directa de contacto (WhatsApp, teléfono, email, autodiagnóstico).

Éxito = el visitante correcto (PyME o institución) llega a un canal de contacto real, o
completa un autodiagnóstico gratuito como paso intermedio de calificación.

## Positioning

"+20 años de trayectoria, ahora con IA local y software libre" — la diferenciación real
es que la IA y la infraestructura corren on-premise, en el servidor del cliente, no en la
nube de un tercero. Es una promesa de soberanía de datos que un proveedor de IA basado en
SaaS/cloud no puede igualar honestamente. Se apoya en trayectoria real (20+ años, presencia
en 4 países) y certificaciones únicas en Argentina (QueueMetrics, Yeastar) en vez de discurso
de startup.

## Operating Context

- Contacto directo vía WhatsApp (+54 341 228 0272), teléfono, email (contacto@intelix.com.ar).
- Autodiagnósticos gratuitos (ciberseguridad, madurez en IA, soberanía tecnológica, crianza
  digital — 18-25 preguntas cada uno, ~4-5 min) como paso de calificación antes del contacto
  directo. Sin registro, resultado orientativo automático.
- Tienda a consulta (`tienda/index.html`): catálogo de equipos/servicios, sin checkout
  automatizado — la compra se resuelve por WhatsApp/consulta humana.
- Blog técnico con 30+ artículos (IA local, ciberseguridad, infraestructura, VoIP).
- Portal de desarrolladores (`developers.html`) con API pública de solo lectura y versionada
  (`/api/v1/productos.php`), rate-limited, documentada con OpenAPI.
- Base de operación: microcentro de Rosario, Santa Fe, Argentina; servicios también en
  Colombia, Estados Unidos y España.
- Compromiso social activo (Ley Ema — prevención de violencia digital en el ámbito
  educativo) como parte real de la operación, no solo marketing.
- Optimizado para agentes de IA/búsqueda agéntica: `llms.txt`, `index.md` (negociación de
  contenido Markdown en la portada), `openapi.json`, páginas de confianza dedicadas
  (`about.html`, `contact.html`, `privacy.html`).

## Capabilities and Constraints

- Sitio 100% estático (HTML/CSS/JS vanilla, sin build step, sin framework de frontend) más
  una pequeña API PHP de solo lectura para el catálogo de la tienda (rate limiting propio,
  sin dependencias).
- **CSP estricto real** (`script-src 'self' 'unsafe-inline'`, ver `.htaccess` e
  `index.html`): ningún `<script src>` puede apuntar a un CDN externo. Cualquier librería de
  terceros (ej. Lenis) debe self-hostearse en `assets/js/vendor/`.
- Sin CMS: el contenido está hardcodeado en cada `.html`, extraído 1:1 del contenido real de
  la empresa (no hay copy inventado).
- Sin backend de aplicación tradicional: formularios vía Web3Forms (sin servidor propio de
  formularios); la tienda no tiene checkout, solo consulta.
- `site.css` es compartido por 12 páginas (index + institucionales + 404 + tienda) — un
  cambio ahí afecta a todo el sitio; blog (30 posts) y diagnósticos usan `<style>` inline
  autocontenido, no `site.css`.
- Sin requisito formal de accesibilidad (WCAG AA no exigido, confirmado) — ver Accessibility.

## Brand Commitments

- Nombre: Intelix Ingeniería & Telecomunicaciones. Logo real en `assets/logo-intelix.jpg`
  (+ set de favicons en `assets/`).
- Tagline real: "Ingeniería con trayectoria, ahora con IA local y software libre".
- Paleta de marca real (definida en `assets/css/site.css`): fondo `#121110`, acento dorado
  `#C9963A`, texto `#F0EDE8`. Tipografía Inter (texto) + JetBrains Mono (datos/código).
- Compromiso social: Ley Ema (impulsada en memoria de Ema Bondaruk), acompañamiento a
  instituciones educativas y víctimas de violencia digital — hecho real de la operación,
  no ángulo de marketing inventado.

## Evidence on Hand

- Contenido textual completo real (nav, hero, servicios, autodiagnósticos, Ley Ema, listado
  real de 18 clientes y 12 partners tecnológicos con logo + hipervínculo real, FAQ, datos de
  contacto) — ver `index.html`, `llms.txt`, `INFORME.txt`.
- 3 imágenes hero generadas con IA (Higgsfield, modelo z_image) ya integradas y optimizadas
  a WebP en `assets/img/hero/` (hero-datacenter, engineer-night, fiber-macro)
  — usadas como fondo del hero y de los headers institucionales. No fabricar más fotos de
  stock genéricas sin indicación explícita de qué sección lo necesita. (La cuarta, voip-hardware,
  se retiró por quedar mal generada por IA; las páginas que la usaban pasaron a fiber-macro.)
- Brochure PDF real descargable (`assets/pdf/brochure-intelix-psyware.pdf`).
- Sin testimonios ni casos de estudio con citas reales disponibles más allá del listado de
  nombres de clientes — no inventar citas ni métricas de resultados.
- Historial de cambios documentado en `VERSION.txt` (versionado desde v1.0); detalle técnico
  completo en `INFORME.txt`.

## Product Principles

1. Nunca inventar copy, clientes, cifras o testimonios — todo el contenido sale del sitio
   real o de evidencia confirmada por el usuario.
2. El sitio debe sostener dos lecturas simultáneas por igual: PyME que resuelve un problema
   puntual, e institución/sector público evaluando un proveedor formalmente.
3. La credibilidad se construye con evidencia concreta (trayectoria, certificaciones,
   clientes reales, partners), no con lenguaje de marketing genérico ("elevate", "seamless").
4. La soberanía de datos (IA local, software libre, on-premise) es la diferenciación real de
   Intelix frente a competidores SaaS/cloud — debe sostenerse en cada superficie nueva, no
   solo repetirse como eslogan.
5. Cualquier adición técnica nueva debe respetar el CSP estricto existente (self-hosting de
   terceros) y no asumir que hay build step o framework disponible.

## Accessibility & Inclusion

Sin requisito formal de certificación WCAG (confirmado por el usuario) — aunque la cartera
de clientes incluye organismos públicos que podrían requerirlo a futuro en procesos de
licitación, revisar si esto cambia. Buenas prácticas generales ya en pie y auditadas
(ver `VERSION.txt` v1.3): foco de teclado visible, `prefers-reduced-motion` respetado en
toda animación (incluye Lenis), skip-link, orden correcto de headings, contraste AA en
texto atenuado (`--dim`).
