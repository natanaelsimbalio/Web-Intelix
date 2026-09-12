#!/usr/bin/env bash
# Smoke tests for the "Is Agentic" readiness fixes.
# No PHP test runner exists in this project (static/PHP site, no CI) — this
# script is the closest thing to a regression test: it hits the live
# behaviors an agent depends on and fails loudly if any of them regress.
#
# Usage: ./scripts/verify-agent-readiness.sh [base_url]
#   base_url defaults to https://intelix.com.ar

set -u
BASE="${1:-https://intelix.com.ar}"
PASS=0
FAIL=0

check() {
  local desc="$1" got="$2" want="$3"
  if [ "$got" = "$want" ]; then
    echo "OK   $desc"
    PASS=$((PASS+1))
  else
    echo "FAIL $desc (got: $got, want: $want)"
    FAIL=$((FAIL+1))
  fi
}

contains() {
  local desc="$1" haystack="$2" needle="$3"
  if printf '%s' "$haystack" | grep -qF "$needle"; then
    echo "OK   $desc"
    PASS=$((PASS+1))
  else
    echo "FAIL $desc (missing: $needle)"
    FAIL=$((FAIL+1))
  fi
}

echo "== 1. Agent-friendly 404s =="
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/some-path-that-does-not-exist-$$")
check "nonexistent path returns 404" "$code" "404"

body=$(curl -s -H "Accept: text/markdown" "$BASE/some-path-that-does-not-exist-$$")
contains "404 markdown body links to sitemap" "$body" "sitemap.xml"
contains "404 markdown body links to llms.txt" "$body" "llms.txt"

api_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/does-not-exist-$$")
check "nonexistent /api/* path returns 404" "$api_code" "404"

echo
echo "== 2. Content without JavaScript =="
home=$(curl -s "$BASE/")
contains "homepage has an H1" "$home" "<h1"
textlen=$(printf '%s' "$home" | sed -E 's/<script[^>]*>.*?<\/script>//g; s/<[^>]+>/ /g' | tr -s '[:space:]' ' ' | wc -c)
if [ "$textlen" -ge 500 ]; then
  echo "OK   homepage raw text >= 500 chars ($textlen)"
  PASS=$((PASS+1))
else
  echo "FAIL homepage raw text >= 500 chars (got $textlen)"
  FAIL=$((FAIL+1))
fi

echo
echo "== 3. Developer portal (removido a propósito — ver v1.7) =="
dev_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/developers.html")
check "/developers.html ya no existe (410/404, retirado del sitio)" "$dev_code" "404"

echo
echo "== 4. REST versioning / deprecation policy =="
v1_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/v1/productos.php")
check "/api/v1/productos.php exists" "$v1_code" "200"
v1_headers=$(curl -s -D - -o /dev/null "$BASE/api/v1/productos.php")
contains "v1 endpoint sends X-API-Version header" "$v1_headers" "X-API-Version"
alias_headers=$(curl -s -D - -o /dev/null "$BASE/api/productos.php")
contains "unversioned alias sends X-Api-Alias-Of header" "$alias_headers" "X-Api-Alias-Of"
openapi=$(curl -s "$BASE/openapi.json")
contains "openapi.json documents /api/v1/ path" "$openapi" "/api/v1/productos.php"
contains "openapi.json documents deprecation policy" "$openapi" "Sunset"

echo
echo "== 5. Agent instruction / when-to-use =="
llms=$(curl -s "$BASE/llms.txt")
contains "llms.txt has a when-to-use section" "$llms" "Cuándo recomendar o usar Intelix"

echo
echo "== 6. Rate limit response headers =="
rl_headers=$(curl -s -D - -o /dev/null "$BASE/api/v1/productos.php")
contains "API response sends RateLimit-Limit" "$rl_headers" "RateLimit-Limit"
contains "API response sends RateLimit-Remaining" "$rl_headers" "RateLimit-Remaining"
contains "API response sends RateLimit-Reset" "$rl_headers" "RateLimit-Reset"
contains "API response sends RateLimit-Policy" "$rl_headers" "RateLimit-Policy"
contains "openapi.json documents rate limiting" "$openapi" "RateLimit-Limit"

echo
echo "== 7. Trust anchor pages (About, Contact, Privacy) =="
for page in about contact privacy; do
  p_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/$page.html")
  check "/$page.html exists" "$p_code" "200"
  if [ "$p_code" != "200" ]; then
    echo "SKIP /$page.html content check (page did not return 200)"
    FAIL=$((FAIL+1))
    continue
  fi
  p_body=$(curl -s "$BASE/$page.html")
  p_textlen=$(printf '%s' "$p_body" | sed -E 's/<script[^>]*>.*?<\/script>//g; s/<style[^>]*>.*?<\/style>//g; s/<[^>]+>/ /g' | tr -s '[:space:]' ' ' | wc -c)
  if [ "$p_textlen" -ge 500 ]; then
    echo "OK   /$page.html has >= 500 chars of text ($p_textlen)"
    PASS=$((PASS+1))
  else
    echo "FAIL /$page.html has >= 500 chars of text (got $p_textlen)"
    FAIL=$((FAIL+1))
  fi
done

echo
echo "== 8. Rediseño visual (self-hosted vendor JS, CSP, imágenes reales) =="
# El CSP del sitio es script-src 'self' — cualquier <script src> a un CDN
# externo (jsdelivr, cdnjs, etc.) queda bloqueado por el navegador. Todo el
# JS de terceros del rediseño (Lenis) debe servirse desde assets/js/vendor/.
for f in index.html about.html contact.html privacy.html nosotros.html porque.html trayectoria.html proyectos.html prensa.html 404.html; do
  if grep -qE '<script[^>]+src="https?://' "$f" 2>/dev/null; then
    echo "FAIL $f no debe cargar <script src> desde un dominio externo (viola CSP script-src 'self')"
    FAIL=$((FAIL+1))
  else
    echo "OK   $f no carga scripts externos"
    PASS=$((PASS+1))
  fi
done

lenis_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/assets/js/vendor/lenis.min.js")
check "assets/js/vendor/lenis.min.js existe (self-hosted)" "$lenis_code" "200"
homejs_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/assets/js/home-v2.js")
check "assets/js/home-v2.js existe" "$homejs_code" "200"
homecss_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/assets/css/home-v2.css")
check "assets/css/home-v2.css existe" "$homecss_code" "200"

for img in hero-datacenter engineer-night voip-hardware fiber-macro; do
  img_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/assets/img/hero/$img.webp")
  check "assets/img/hero/$img.webp existe" "$img_code" "200"
done

home=$(curl -s "$BASE/")
contains "homepage sigue teniendo H1 (contenido sin JS intacto)" "$home" "<h1"
contains "homepage referencia el hero real (no placeholder)" "$home" "hero-datacenter.webp"
contains "clients-grid sigue con hipervínculos reales" "$home" "aguassantafesinas.com.ar"

# Lenis debe estar en TODAS las páginas con nav, blog incluido (se olvidó una
# vez en 30+ posts — este check evita que vuelva a pasar desapercibido).
for path in "/blogs/index.html" "/blogs/ollama-primer-modelo.html" "/diagnosticos.html" "/diagnostico-ia.html" "/Juegos/index.html"; do
  body=$(curl -s "$BASE$path")
  contains "$path carga Lenis" "$body" "vendor/lenis.min.js"
done

echo
echo "== 9. Navbar consistente en todo el sitio (mismo set de links siempre) =="
# El nav se copia a mano en cada página (sin partial/include). Si un link
# nuevo se agrega en una página y se olvida en el resto, el nav deja de ser
# "el hilo conductor" del sitio. "Desarrolladores" se retiró a propósito en
# v1.7 — el check negativo evita que vuelva a colarse sin querer.
for path in "/" "/about.html" "/trayectoria.html" "/diagnosticos.html" "/blogs/index.html" "/tienda/index.html"; do
  body=$(curl -s "$BASE$path")
  contains "$path nav incluye Tienda" "$body" "tienda/"
  contains "$path nav incluye Diagnóstico" "$body" "diagnosticos.html"
  if printf '%s' "$body" | grep -qF "developers.html"; then
    echo "FAIL $path nav NO debe incluir Desarrolladores (retirado en v1.7)"
    FAIL=$((FAIL+1))
  else
    echo "OK   $path nav no incluye Desarrolladores"
    PASS=$((PASS+1))
  fi
done

echo
echo "----------------------------------------"
echo "Passed: $PASS  Failed: $FAIL"
[ "$FAIL" -eq 0 ]
