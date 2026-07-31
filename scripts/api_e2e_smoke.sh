#!/usr/bin/env bash
# =============================================================================
# Posto de Enfermagem API — Smoke E2E (HTTP)
# Compatível com cPanel: usa curl + php (sem python3).
#
# Uso:
#   chmod +x scripts/api_e2e_smoke.sh
#   ./scripts/api_e2e_smoke.sh
#
# Antes (se necessário):
#   php artisan up
#   php artisan migrate --force
#   php artisan db:seed --class=ClinicalAccessSeeder --force
# =============================================================================

set -euo pipefail

BASE_URL="${BASE_URL:-https://api.postodeenfermagem.com.br/api/v1}"
CLIENT_ID="${CLIENT_ID:-frontend-app-vida}"
CLIENT_SECRET="${CLIENT_SECRET:-secret123}"
EMAIL="${EMAIL:-house@hospitalvida.com.br}"
PASSWORD="${PASSWORD:-password}"
PATIENT_CPF="${PATIENT_CPF:-222.333.444-55}"

PASS=0
FAIL=0
SKIP=0

APP_TOKEN=""
TENANT_ID=""
ACCESS_TOKEN=""
USER_ID=""
PATIENT_ID=""
CONSENT_ID=""
CONSENT_ID2=""
ENCOUNTER_ID=""
OBS_ID=""

red() { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }
yellow() { printf '\033[33m%s\033[0m\n' "$*"; }

ok() { PASS=$((PASS + 1)); green "  OK  $*"; }
ko() { FAIL=$((FAIL + 1)); red "  FAIL $*"; }
sk() { SKIP=$((SKIP + 1)); yellow "  SKIP $*"; }

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Comando obrigatório ausente: $1"; exit 1; }
}

need_cmd curl
need_cmd php

json_get() {
  local payload="$1"
  local path="$2"
  php -r '
    $j = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($j)) { exit(0); }
    $path = $argv[1];
    $cur = $j;
    foreach (explode(".", $path) as $p) {
      if ($p === "") continue;
      if (is_array($cur) && array_key_exists($p, $cur)) {
        $cur = $cur[$p];
      } elseif (is_array($cur) && ctype_digit($p) && array_key_exists((int)$p, $cur)) {
        $cur = $cur[(int)$p];
      } else {
        exit(0);
      }
    }
    if (is_bool($cur)) {
      echo $cur ? "true" : "false";
    } elseif (is_array($cur) || is_object($cur)) {
      echo json_encode($cur);
    } elseif ($cur === null) {
      echo "";
    } else {
      echo $cur;
    }
  ' "$path" <<<<"$payload" 2>/dev/null || true
}

json_count() {
  local payload="$1"
  php -r '
    $j = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($j)) { echo 0; exit; }
    $isList = function_exists("array_is_list") ? array_is_list($j) : (array_keys($j) === range(0, count($j) - 1));
    if ($isList) { echo count($j); exit; }
    if (isset($j["data"]) && is_array($j["data"])) { echo count($j["data"]); exit; }
    echo 0;
  ' <<<<"$payload" 2>/dev/null || echo 0
}

req() {
  local method="$1"
  local path="$2"
  local body="${3:-}"
  local url="${BASE_URL}${path}"
  local args=(-sS -w '\n%{http_code}' -X "$method" "$url"
    -H 'Accept: application/json'
    -H 'Content-Type: application/json')

  if [[ -n "${TENANT_ID}" ]]; then
    args+=(-H "X-Tenant-ID: ${TENANT_ID}")
  fi
  if [[ -n "${APP_TOKEN}" ]]; then
    args+=(-H "X-App-Token: ${APP_TOKEN}")
  fi
  if [[ -n "${ACCESS_TOKEN}" ]]; then
    args+=(-H "Authorization: Bearer ${ACCESS_TOKEN}")
  fi
  if [[ -n "$body" ]]; then
    args+=(-d "$body")
  fi

  local raw
  if ! raw="$(curl "${args[@]}" 2>/dev/null)"; then
    printf '%s\n%s\n' '{"error":"curl_failed"}' '000'
    return 0
  fi
  printf '%s\n' "$raw"
}

split_body_code() {
  local raw="$1"
  CODE="$(printf '%s' "$raw" | tail -n 1)"
  BODY="$(printf '%s' "$raw" | sed '$d')"
}

iso_now() {
  date -u +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || php -r 'echo gmdate("Y-m-d\TH:i:s\Z");'
}

echo "=============================================="
echo " API E2E Smoke — Posto de Enfermagem"
echo " BASE_URL=$BASE_URL"
echo "=============================================="

echo
echo "[0] Health"
raw="$(req GET /health)"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET /health → 200"
else
  ko "GET /health → $CODE ($BODY)"
  echo "Dica: php artisan up"
  exit 1
fi

echo
echo "[1] Application M2M"
raw="$(req POST /auth/application/token "{\"client_id\":\"$CLIENT_ID\",\"client_secret\":\"$CLIENT_SECRET\"}")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  APP_TOKEN="$(json_get "$BODY" "app_token")"
  TENANT_ID="$(json_get "$BODY" "tenant_id")"
  if [[ -n "$APP_TOKEN" && -n "$TENANT_ID" ]]; then
    ok "POST /auth/application/token → token + tenant"
  else
    ko "200 sem app_token/tenant_id: $BODY"
    exit 1
  fi
else
  ko "POST /auth/application/token → $CODE $BODY"
  exit 1
fi

echo
echo "[2] Login profissional"
raw="$(req POST /auth/login "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ACCESS_TOKEN="$(json_get "$BODY" "access_token")"
  if [[ -z "$ACCESS_TOKEN" ]]; then
    ACCESS_TOKEN="$(json_get "$BODY" "token")"
  fi
  MFA="$(json_get "$BODY" "mfa_required")"
  if [[ "$MFA" == "true" || "$MFA" == "1" ]]; then
    sk "mfa_required=true — complete MFA e reexecute"
    echo "$BODY"
    exit 0
  fi
  if [[ -n "$ACCESS_TOKEN" ]]; then
    ok "POST /auth/login → access_token"
  else
    ko "Login 200 sem token: $BODY"
    exit 1
  fi
else
  ko "POST /auth/login → $CODE $BODY"
  exit 1
fi

echo
echo "[3] Usuário autenticado"
raw="$(req GET /user)"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  USER_ID="$(json_get "$BODY" "id")"
  USER_NAME="$(json_get "$BODY" "name")"
  ok "GET /user → ${USER_NAME} (${USER_ID})"
else
  ko "GET /user → $CODE $BODY"
fi

echo
echo "[4] Lista pacientes"
raw="$(req GET /patients)"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  COUNT="$(json_count "$BODY")"
  ok "GET /patients → 200 (itens≈${COUNT})"
  PATIENT_ID="$(json_get "$BODY" "0.id")"
  if [[ -z "$PATIENT_ID" ]]; then
    PATIENT_ID="$(json_get "$BODY" "data.0.id")"
  fi
else
  ko "GET /patients → $CODE $BODY"
fi

echo
echo "[5] Lookup CPF ${PATIENT_CPF}"
raw="$(req GET "/patients/lookup/cpf/${PATIENT_CPF}")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  PATIENT_ID="$(json_get "$BODY" "id")"
  if [[ -z "$PATIENT_ID" ]]; then
    PATIENT_ID="$(json_get "$BODY" "data.id")"
  fi
  ok "Lookup CPF → patient_id=${PATIENT_ID}"
elif [[ "$CODE" == "403" ]]; then
  sk "Lookup CPF → 403 (sem authorization)"
elif [[ "$CODE" == "404" ]]; then
  sk "Lookup CPF → 404 (não encontrado)"
else
  ko "Lookup CPF → $CODE $BODY"
fi

if [[ -z "${PATIENT_ID}" ]]; then
  ko "Sem PATIENT_ID — abortando trecho clínico"
  echo "PASS=$PASS FAIL=$FAIL SKIP=$SKIP"
  exit 1
fi

echo
echo "[6] Show patient"
raw="$(req GET "/patients/${PATIENT_ID}")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET /patients/{id} → 200"
elif [[ "$CODE" == "403" ]]; then
  sk "GET /patients/{id} → 403"
else
  ko "GET /patients/{id} → $CODE $BODY"
fi

echo
echo "[7] Care authorizations"
raw="$(req GET "/patients/${PATIENT_ID}/care-authorizations")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET care-authorizations → 200"
else
  ko "GET care-authorizations → $CODE $BODY"
fi

echo
echo "[8] Consents"
raw="$(req GET "/patients/${PATIENT_ID}/consents")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET consents → 200"
else
  ko "GET consents → $CODE $BODY"
fi

echo
echo "[9] Create consent appointment_care"
CREATE_BODY="{\"context\":\"appointment_care\",\"professional_user_id\":\"${USER_ID}\",\"purposes\":[\"atendimento_clinico\",\"prontuario\",\"compartilhamento_equipe\"],\"data_categories\":[\"dados_cadastrais\",\"dados_clinicos\"],\"consent_text_version\":\"appointment_care_v1\"}"
raw="$(req POST "/patients/${PATIENT_ID}/consents" "$CREATE_BODY")"
split_body_code "$raw"
if [[ "$CODE" == "201" || "$CODE" == "200" ]]; then
  CONSENT_ID="$(json_get "$BODY" "id")"
  ok "POST consents → id=${CONSENT_ID} status=$(json_get "$BODY" status)"
else
  ko "POST consents → $CODE $BODY"
fi

echo
echo "[10] Deny consent"
if [[ -n "$CONSENT_ID" ]]; then
  raw="$(req POST "/patients/${PATIENT_ID}/consents/${CONSENT_ID}/deny" '{}')"
  split_body_code "$raw"
  if [[ "$CODE" == "200" ]]; then
    ok "POST consents/{id}/deny → 200"
  else
    ko "deny → $CODE $BODY"
  fi
else
  sk "deny — sem CONSENT_ID"
fi

echo
echo "[11] Accept appointment_care"
raw="$(req POST "/patients/${PATIENT_ID}/consents" "$CREATE_BODY")"
split_body_code "$raw"
CONSENT_ID2="$(json_get "$BODY" "id")"
if [[ -n "$CONSENT_ID2" ]]; then
  raw="$(req POST "/patients/${PATIENT_ID}/consents/${CONSENT_ID2}/accept" '{}')"
  split_body_code "$raw"
  if [[ "$CODE" == "200" ]]; then
    ok "POST consents/{id}/accept → 200"
  elif [[ "$CODE" == "401" ]]; then
    sk "accept → 401 (senha do titular necessária)"
  else
    ko "accept → $CODE $BODY"
  fi
else
  sk "accept — não criou consent"
fi

echo
echo "[12] List encounters"
raw="$(req GET "/patients/${PATIENT_ID}/encounters")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET encounters → 200"
elif [[ "$CODE" == "403" ]]; then
  sk "GET encounters → 403 (sem care_authorization)"
else
  ko "GET encounters → $CODE $BODY"
fi

echo
echo "[13] Create encounter"
NOW="$(iso_now)"
ENC_BODY="{\"status\":\"in-progress\",\"reason\":\"Smoke E2E\",\"start_time\":\"${NOW}\"}"
raw="$(req POST "/patients/${PATIENT_ID}/encounters" "$ENC_BODY")"
split_body_code "$raw"
if [[ "$CODE" == "201" || "$CODE" == "200" ]]; then
  ENCOUNTER_ID="$(json_get "$BODY" "id")"
  if [[ -z "$ENCOUNTER_ID" ]]; then
    ENCOUNTER_ID="$(json_get "$BODY" "data.id")"
  fi
  ok "POST encounters → ${ENCOUNTER_ID}"
elif [[ "$CODE" == "403" ]]; then
  sk "POST encounters → 403"
else
  ko "POST encounters → $CODE $BODY"
fi

echo
echo "[14] Create observation"
if [[ -n "$ENCOUNTER_ID" ]]; then
  NOW="$(iso_now)"
  OBS_BODY="{\"type\":\"vital-signs\",\"content\":{\"systolic\":120,\"diastolic\":80,\"heart_rate\":72,\"temperature\":36.5,\"spo2\":98},\"recorded_at\":\"${NOW}\"}"
  raw="$(req POST "/patients/${PATIENT_ID}/encounters/${ENCOUNTER_ID}/observations" "$OBS_BODY")"
  split_body_code "$raw"
  if [[ "$CODE" == "201" || "$CODE" == "200" ]]; then
    OBS_ID="$(json_get "$BODY" "id")"
    ok "POST observations → ${OBS_ID}"
  else
    ko "POST observations → $CODE $BODY"
  fi

  echo
  echo "[15] Update observation (versionamento)"
  if [[ -n "$OBS_ID" ]]; then
    NOW="$(iso_now)"
    OBS_UPD="{\"type\":\"vital-signs\",\"content\":{\"systolic\":118,\"diastolic\":78,\"heart_rate\":70,\"temperature\":36.6,\"spo2\":99},\"recorded_at\":\"${NOW}\"}"
    raw="$(req PUT "/patients/${PATIENT_ID}/encounters/${ENCOUNTER_ID}/observations/${OBS_ID}" "$OBS_UPD")"
    split_body_code "$raw"
    if [[ "$CODE" == "201" || "$CODE" == "200" ]]; then
      ok "PUT observations → $CODE (nova versão)"
    else
      ko "PUT observations → $CODE $BODY"
    fi
  else
    sk "versionamento — sem OBS_ID"
  fi
else
  sk "observations — sem ENCOUNTER_ID"
fi

echo
echo "=============================================="
echo " Resultado: PASS=$PASS  FAIL=$FAIL  SKIP=$SKIP"
echo "=============================================="

if [[ "$FAIL" -gt 0 ]]; then
  exit 1
fi
exit 0
