# Audit UI/UX — Intelix Ingeniería (intelix.com.ar)
Auditoría rápida (modo acotado por presupuesto de sesión) — 2026-09-06. Servido localmente en `localhost:8811`.

Páginas revisadas: Inicio (`index.html`), Autodiagnósticos (`diagnosticos.html`), Proyectos (`proyectos.html`), CSS global (`assets/css/site.css`). **No revisadas en esta pasada:** trayectoria.html, porque.html, nosotros.html, prensa.html, blog/, tienda/, developers/ — recomendable una segunda pasada.

## Hallazgos

### 1. CSP y X-Frame-Options mal configurados (Alta prioridad, técnico)
La consola muestra advertencias reales del navegador:
> "The Content Security Policy directive 'frame-ancestors' is ignored when delivered via a `<meta>` element."
> "X-Frame-Options may only be set via an HTTP header..."

Ambas directivas están puestas como `<meta>` tags en el `<head>`, donde el navegador las ignora — es decir, **hoy no ofrecen ninguna protección real** contra clickjacking ni restringen framing. Como el sitio es estático (sin backend de aplicación), esto requiere configurarlo a nivel de servidor web (Nginx/Apache/Cloudflare) o CDN, no en el HTML. **Estado: pendiente de fix** (fuera del alcance de un cambio de archivo estático; requiere acceso al hosting).

### 2. Emoji como iconografía en Autodiagnósticos (Media prioridad, AI Slop / marca)
`diagnosticos.html` usa emoji nativos como iconos de feature (🛡️ 🤖 🗝️ 👪) en vez de iconografía vectorial propia. Es un patrón reconocido de "AI slop" — se ve genérico y no transmite la seriedad técnica que el resto del sitio construye (peritaje matriculado, forense, IA on-premise). Contrasta con el resto del sitio, que sí usa una identidad visual cuidada (dorado sobre oscuro, tipografía Inter, gradientes propios).
**Recomendación:** reemplazar los 4 emoji por iconos SVG de línea, en el mismo dorado (`--gold`) usado en el resto de la marca.

### 3. Hero con posible espacio muerto en viewports intermedios (Baja prioridad, layout)
`header.hero{padding:184px 0 110px}` + `.hero-grid{align-items:center}` en un grid de 2 columnas. En anchos intermedios (~800–940px, justo antes del breakpoint de stack a `940px`), si el contenido de la columna derecha (terminal decorativo) es más bajo que el de la izquierda (título+CTA), el `align-items:center` puede generar una franja de espacio vacío perceptible arriba/abajo de la columna corta. Confirmado por cálculo: `header.hero` h=1123px = 184 (padding-top) + 829 (grid) + 110 (padding-bottom) — los números cuadran exactamente, así que es una consecuencia directa del padding elegido, no un bug de renderizado. Es más una decisión de espaciado generoso que un defecto, pero vale la pena revisar visualmente en ese rango de anchos.
**Estado: no crítico, se deja como observación** (no se tocó CSS por ser un llamado de diseño, no un bug).

### 4. Contenido fuerte en Proyectos (Positivo, no requiere fix)
`proyectos.html` tiene contenido específico y verificable (EstudIAr, Kioske.AR con stack técnico real, métricas concretas) — es exactamente el tipo de prueba social que el informe de competidores (`../competidores.md`) señala como diferencial débil en el mercado. Ningún competidor relevado combina esta especificidad técnica con prueba de producto en producción.

### 5. Consistencia visual entre Intelix y Psyware (Alta prioridad, marca — ver también `Psyware/audit.md`)
Intelix usa una identidad oscura/dorada, tipografía Inter, componentes con `border-radius:8px`. Psyware (la otra marca de la misma empresa) usa una identidad completamente distinta: fondo blanco, hero en degradé azul, botones tipo "pill" (`border-radius:999px`), tipografía Raleway para títulos. Un prospecto que visite ambos sitios (¡son la misma empresa, fundada en 2004!) no lo percibiría como una sola marca. Ver detalle en `../Psyware/audit.md` hallazgo #1.

## Resumen de acciones tomadas en esta sesión
- [x] Reemplazados los emoji de `diagnosticos.html` por iconos SVG (ver commit).
- [ ] CSP/X-Frame-Options por header HTTP real — requiere config de servidor, no se puede resolver editando el HTML estático. Documentado para acción del usuario/hosting.
- [ ] Espaciado del hero en viewports intermedios — dejado como observación de diseño, no aplicado (juicio de espaciado, no bug).
- [ ] Unificación de identidad visual entre Intelix y Psyware — cambio grande de branding, requiere decisión del usuario antes de tocar CSS de ambos sitios (ver recomendación en `../campana-clientes.md` y `../Psyware/audit.md`).
