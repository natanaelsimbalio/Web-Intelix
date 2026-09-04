#!/usr/bin/env bash
# Verifies the "Is Agentic" readiness fixes against a live host.
# Usage: tests/verify-agent-readiness.sh [https://intelix.com.ar]
set -u
BASE="${1:-https://intelix.com.ar}"
fail=0

pass() { echo "PASS: $1"; }
failmsg() { echo "FAIL: $1"; fail=1; }

# 1. Agent-friendly 404s: unknown path must return a real 404, not 200.
status=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/some-path-that-does-not-exist-xyz")
[ "$status" = "404" ] && pass "unknown path returns 404 (got $status)" || failmsg "unknown path returns 404 (got $status)"

md_body=$(curl -s -H "Accept: text/markdown" "$BASE/some-path-that-does-not-exist-xyz")
if grep -qiE 'sitemap|llms\.txt' <<<"$md_body"; then
  pass "markdown 404 body mentions sitemap or llms.txt"
else
  failmsg "markdown 404 body mentions sitemap or llms.txt"
fi

# 1b. /api/* misses return JSON, not the HTML 404 page or a bare hosting 404.
api_ct=$(curl -s -o /dev/null -w "%{content_type}" "$BASE/api/does-not-exist.php")
[[ "$api_ct" == application/json* ]] && pass "unknown /api/ path returns application/json (got $api_ct)" || failmsg "unknown /api/ path returns application/json (got $api_ct)"
api_status=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/does-not-exist.php")
[ "$api_status" = "404" ] && pass "unknown /api/ path returns 404 (got $api_status)" || failmsg "unknown /api/ path returns 404 (got $api_status)"
api_body=$(curl -s "$BASE/api/does-not-exist.php")
if grep -q '"error"' <<<"$api_body"; then
  pass "unknown /api/ path body is the structured JSON error envelope"
else
  failmsg "unknown /api/ path body is the structured JSON error envelope (got: $api_body)"
fi

# 2. Content without JavaScript: homepage must SSR an H1 and 500+ chars of text.
html=$(curl -s "$BASE/")
if grep -qi '<h1' <<<"$html"; then
  pass "homepage has an <h1>"
else
  failmsg "homepage has an <h1>"
fi
textlen=$(sed -e 's/<script[^>]*>.*<\/script>//g' -e 's/<style[^>]*>.*<\/style>//g' -e 's/<[^>]*>/ /g' <<<"$html" | tr -s '[:space:]' ' ' | wc -c)
[ "$textlen" -gt 500 ] && pass "homepage raw HTML has 500+ chars of text (got $textlen)" || failmsg "homepage raw HTML has 500+ chars of text (got $textlen)"

# 3. OpenAPI spec published.
openapi_status=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/openapi.json")
[ "$openapi_status" = "200" ] && pass "/openapi.json is reachable (got $openapi_status)" || failmsg "/openapi.json is reachable (got $openapi_status)"
if curl -s "$BASE/openapi.json" | grep -q '"openapi"'; then
  pass "/openapi.json declares an OpenAPI version"
else
  failmsg "/openapi.json declares an OpenAPI version"
fi

# 4. JSON error responses from the API.
prod_status=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/productos.php")
[ "$prod_status" = "200" ] && pass "/api/productos.php responds 200" || failmsg "/api/productos.php responds 200"
prod_ct=$(curl -s -o /dev/null -w "%{content_type}" "$BASE/api/productos.php")
[[ "$prod_ct" == application/json* ]] && pass "/api/productos.php content-type is application/json (got $prod_ct)" || failmsg "/api/productos.php content-type is application/json (got $prod_ct)"

# 4b. REST versioning: /api/v1/ is the canonical path and carries an API-Version header.
v1_status=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/v1/productos.php")
[ "$v1_status" = "200" ] && pass "/api/v1/productos.php responds 200" || failmsg "/api/v1/productos.php responds 200"
v1_headers=$(curl -sI "$BASE/api/v1/productos.php")
if grep -qi '^api-version: *v1' <<<"$v1_headers"; then
  pass "/api/v1/productos.php sends API-Version: v1 header"
else
  failmsg "/api/v1/productos.php sends API-Version: v1 header"
fi
alias_headers=$(curl -sI "$BASE/api/productos.php")
if grep -qi '^api-version: *v1' <<<"$alias_headers"; then
  pass "/api/productos.php alias also sends API-Version: v1 header"
else
  failmsg "/api/productos.php alias also sends API-Version: v1 header"
fi

# 4c. Developer portal: reachable and documents versioning/quickstart.
dev_status=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/developers/")
[ "$dev_status" = "200" ] && pass "/developers/ is reachable (got $dev_status)" || failmsg "/developers/ is reachable (got $dev_status)"
dev_body=$(curl -s "$BASE/developers/")
if grep -qi 'quickstart\|versionado' <<<"$dev_body"; then
  pass "/developers/ mentions quickstart or versioning policy"
else
  failmsg "/developers/ mentions quickstart or versioning policy"
fi

# 4d. Homepage links to the developer portal.
if grep -qi 'developers/' <<<"$html"; then
  pass "homepage links to /developers/"
else
  failmsg "homepage links to /developers/"
fi

# 5. Markdown content negotiation on the homepage (acceptmarkdown.com).
md_headers=$(curl -sI -H "Accept: text/markdown" "$BASE/")
if grep -qi '^content-type: *text/markdown' <<<"$md_headers"; then
  pass "Accept: text/markdown returns Content-Type: text/markdown"
else
  failmsg "Accept: text/markdown returns Content-Type: text/markdown"
fi
if grep -qi '^vary:.*accept' <<<"$md_headers"; then
  pass "Response carries Vary: Accept"
else
  failmsg "Response carries Vary: Accept"
fi

html_headers=$(curl -sI "$BASE/")
if grep -qi '^content-type: *text/html' <<<"$html_headers"; then
  pass "Default request (no markdown Accept) still returns text/html"
else
  failmsg "Default request (no markdown Accept) still returns text/html"
fi

if [ "$fail" -eq 0 ]; then
  echo "All checks passed."
else
  echo "Some checks FAILED."
fi
exit $fail
