<?php
// ============================================================
// Config de la tienda — datos sensibles. NO subir a un repo público
// con el token real cargado. Este archivo está bloqueado a acceso
// web directo por .htaccess (ver api/.htaccess).
// ============================================================

// Access Token de Mercado Pago (Developer > Tus integraciones > Credenciales
// de producción). Empieza con "APP_USR-...".
define('MP_ACCESS_TOKEN', 'APP_USR-6693474811803582-081519-cb78c67f7a2d6ce2fed9a1f29241b82b-70419436');

// Public key (no es secreta, pero no se usa en este flujo server-side de
// Checkout Pro — solo haría falta si más adelante se agrega Checkout Bricks
// del lado del navegador). Queda guardada acá por si se necesita después.
define('MP_PUBLIC_KEY', 'APP_USR-00b6fd38-b2f2-41d5-ba91-75a5ace248f1');

// Alias, CVU y titular para mostrar como método de pago alternativo
// (transferencia manual directa a la cuenta de Mercado Pago).
define('MP_ALIAS', 'fmvillares1978.mp');
define('MP_CVU', '0000003100037300934977');
define('MP_TITULAR', 'Fernando Maximiliano Villares');

// A dónde llegan las notificaciones de pedido nuevo / pago confirmado.
define('SHOP_NOTIFY_EMAIL', 'contacto@intelix.com.ar');

// Dominio público del sitio (sin barra final) — se usa para armar las
// URLs de retorno y de notificación que recibe Mercado Pago.
define('SITE_URL', 'https://intelix.com.ar');

// Valor de emergencia del dólar oficial (ARS por USD), usado SOLO si
// dolarapi.com está caída y todavía no hay ningún valor cacheado.
// Conviene revisar/actualizar este número cada tanto a mano.
define('FALLBACK_USD_ARS', 1450.00);
