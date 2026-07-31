#!/usr/bin/env bash
# =============================================================================
# Posto de Enfermagem API — Smoke E2E (HTTP)
# Roda contra a API publicada (ou BASE_URL local).
#
# Uso:
#   chmod +x scripts/api_e2e_smoke.sh
#   ./scripts/api_e2e_smoke.sh
#
# Variáveis opcionais:
#   BASE_URL=https://api.postodeenfermagem.com.br/api/v1
#   CLIENT_ID=frontend-app-vida
#   CLIENT_SECRET=secret123
#   EMAIL=house@hospitalvida.com.br
#   PASSWORD=password
#   PATIENT_CPF=222.333.444-55
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
need_cmd python3

json_get() {
  # json_get '<json>' 'python expression using obj'
  local payload="$1"
  local expr="$2"
  python3 -c "import json,sys; obj=json.load(sys.stdin); print($expr)" <<<<"$payload" 2>/dev/null || true
}

req() {
  # req METHOD PATH [json_body]
  local method="$1"
  local path="$2"
  local body="${3:-}"
  local url="${BASE_URL}${path}"
  local args=(-sS -w '\n%{http_code}' -X "$method" "$url"
    -H 'Accept: application/json'
    -H 'Content-Type: application/json')

  if [[ -n "${TENANT_ID:-}" ]]; then
    args+=(-H "X-Tenant-ID: ${TENANT_ID}")
  fi
  if [[ -n "${APP_TOKEN:-}" ]]; then
    args+=(-H "X-App-Token: ${APP_TOKEN}")
  fi
  if [[ -n "${ACCESS_TOKEN:-}" ]]; then
    args+=(-H "Authorization: Bearer ${ACCESS_TOKEN}")
  fi
  if [[ -n "$body" ]]; then
    args+=(-d "$body")
  fi

  local raw
  raw="$(curl "${args[@]}")" || {
    echo "CURL_ERROR"
    echo "000"
    return 0
  }
  echo "$raw"
}

split_body_code() {
  # sets BODY and CODE from multiline curl output (last line = code)
  local raw="$1"
  CODE="$(printf '%s' "$raw" | tail -n 1)"
  BODY="$(printf '%s' "$raw" | sed '$d')"
}

echo "=============================================="
echo " API E2E Smoke — Posto de Enfermagem"
echo " BASE_URL=$BASE_URL"
echo "=============================================="

# --- 0) Health ---
echo
echo "[0] Health"
raw="$(req GET /health)"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET /health → 200 ($BODY)"
else
  ko "GET /health → $CODE ($BODY)"
  echo "API indisponível ou em manutenção. Abortando."
  exit 1
fi

# --- 1) Application token ---
echo
echo "[1] Application M2M"
raw="$(req POST /auth/application/token "{\"client_id\":\"$CLIENT_ID\",\"client_secret\":\"$CLIENT_SECRET\"}")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  APP_TOKEN="$(json_get "$BODY" "obj.get('app_token','')")"
  TENANT_ID="$(json_get "$BODY" "obj.get('tenant_id','')")"
  if [[ -n "$APP_TOKEN" && -n "$TENANT_ID" ]]; then
    ok "POST /auth/application/token → token + tenant_id"
  else
    ko "POST /auth/application/token → 200 mas sem app_token/tenant_id: $BODY"
    exit 1
  fi
else
  ko "POST /auth/application/token → $CODE $BODY"
  exit 1
fi

# --- 2) Login profissional ---
echo
echo "[2] Login profissional"
raw="$(req POST /auth/login "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ACCESS_TOKEN="$(json_get "$BODY" "obj.get('access_token') or obj.get('token') or ''")"
  MFA="$(json_get "$BODY" "obj.get('mfa_required', False)")"
  if [[ "$MFA" == "True" ]]; then
    sk "Login retornou mfa_required=true — complete MFA manualmente e reexecute com token"
    echo "$BODY"
    exit 0
  fi
  if [[ -n "$ACCESS_TOKEN" ]]; then
    ok "POST /auth/login → access_token"
  else
    ko "Login 200 sem access_token: $BODY"
    exit 1
  fi
else
  ko "POST /auth/login → $CODE $BODY"
  exit 1
fi

# --- 3) /user ---
echo
echo "[3] Usuário autenticado"
raw="$(req GET /user)"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  USER_ID="$(json_get "$BODY" "obj.get('id','')")"
  USER_NAME="$(json_get "$BODY" "obj.get('name','')")"
  ok "GET /user → $USER_NAME ($USER_ID)"
else
  ko "GET /user → $CODE $BODY"
fi

# --- 4) List patients ---
echo
echo "[4] Pacientes"
raw="$(req GET /patients)"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  COUNT="$(json_get "$BODY" "len(obj) if isinstance(obj, list) else len(obj.get('data',[]))")"
  ok "GET /patients → $CODE (itens≈$COUNT)"
else
  ko "GET /patients → $CODE $BODY"
fi

# --- 5) Lookup CPF ---
echo
echo "[5] Busca CPF $PATIENT_CPF"
raw="$(req GET "/patients/lookup/cpf/${PATIENT_CPF}")"
split_body_code "$raw"
PATIENT_ID=""
if [[ "$CODE" == "200" ]]; then
  PATIENT_ID="$(json_get "$BODY" "obj.get('id') or (obj.get('data') or {}).get('id','')")"
  ok "Lookup CPF → patient_id=$PATIENT_ID"
elif [[ "$CODE" == "403" ]]; then
  sk "Lookup CPF → 403 (sem authorization ainda). Tentando obter id via lista..."
  raw2="$(req GET /patients)"
  split_body_code "$raw2"
  PATIENT_ID="$(json_get "$BODY" "(obj[0]['id'] if isinstance(obj, list) and obj else (obj.get('data') or [{}])[0].get('id',''))")"
else
  ko "Lookup CPF → $CODE $BODY"
fi

if [[ -z "$PATIENT_ID" ]]; then
  ko "Sem PATIENT_ID — não é possível continuar testes clínicos"
  echo "PASS=$PASS FAIL=$FAIL SKIP=$SKIP"
  exit 1
fi

# --- 6) Show patient ---
echo
echo "[6] Show patient"
raw="$(req GET "/patients/${PATIENT_ID}")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET /patients/{id} → 200"
elif [[ "$CODE" == "403" ]]; then
  sk "GET /patients/{id} → 403 (esperado se seed sem care_authorization)"
else
  ko "GET /patients/{id} → $CODE $BODY"
fi

# --- 7) Care authorizations list ---
echo
echo "[7] Care authorizations"
raw="$(req GET "/patients/${PATIENT_ID}/care-authorizations")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET care-authorizations → 200"
else
  ko "GET care-authorizations → $CODE $BODY"
fi

# --- 8) Consents list ---
echo
echo "[8] Consents"
raw="$(req GET "/patients/${PATIENT_ID}/consents")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET consents → 200"
else
  ko "GET consents → $CODE $BODY"
fi

# --- 9) Create appointment_care consent (pending) ---
echo
echo "[9] Create consent appointment_care"
CREATE_BODY=$(cat <<EOF
{
  "context": "appointment_care",
  "professional_user_id": "${USER_ID}",
  "purposes": ["atendimento_clinico", "prontuario", "compartilhamento_equipe"],
  "data_categories": ["dados_cadastrais", "dados_clinicos"],
  "consent_text_version": "appointment_care_v1"
}
EOF
)
raw="$(req POST "/patients/${PATIENT_ID}/consents" "$CREATE_BODY")"
split_body_code "$raw"
CONSENT_ID=""
if [[ "$CODE" == "201" || "$CODE" == "200" ]]; then
  CONSENT_ID="$(json_get "$BODY" "obj.get('id','')")"
  STATUS="$(json_get "$BODY" "obj.get('status','')")"
  ok "POST consents → id=$CONSENT_ID status=$STATUS"
else
  ko "POST consents → $CODE $BODY"
fi

# --- 10) Deny path (novo consent) para não quebrar o fluxo de accept ---
echo
echo "[10] Deny consent (fluxo negativo)"
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

# --- 11) Novo consent + accept (pode falhar se exigir senha de paciente) ---
echo
echo "[11] Accept appointment_care (pode exigir senha do titular)"
raw="$(req POST "/patients/${PATIENT_ID}/consents" "$CREATE_BODY")"
split_body_code "$raw"
CONSENT_ID2="$(json_get "$BODY" "obj.get('id','')")"
if [[ -n "$CONSENT_ID2" ]]; then
  # Se a API só valida senha quando authenticated_with=password no registro,
  # accept sem password deve funcionar para pending simples.
  raw="$(req POST "/patients/${PATIENT_ID}/consents/${CONSENT_ID2}/accept" '{}')"
  split_body_code "$raw"
  if [[ "$CODE" == "200" ]]; then
    ok "POST consents/{id}/accept → 200"
  elif [[ "$CODE" == "401" ]]; then
    sk "accept → 401 (senha do paciente necessária — configure accepted_by_user_id + password)"
  else
    ko "accept → $CODE $BODY"
  fi
else
  sk "accept — não criou segundo consent"
fi

# --- 12) Encounters list ---
echo
echo "[12] Encounters"
raw="$(req GET "/patients/${PATIENT_ID}/encounters")"
split_body_code "$raw"
if [[ "$CODE" == "200" ]]; then
  ok "GET encounters → 200"
elif [[ "$CODE" == "403" ]]; then
  sk "GET encounters → 403 (sem care_authorization ativa)"
else
  ko "GET encounters → $CODE $BODY"
fi

# --- 13) Create encounter (se autorizado) ---
echo
echo "[13] Create encounter"
ENC_BODY=$(cat <<EOF
{
  "status": "in-progress",
  "reason": "Smoke E2E",
  "start_time": "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
}
EOF
)
raw="$(req POST "/patients/${PATIENT_ID}/encounters" "$ENC_BODY")"
split_body_code "$raw"
ENCOUNTER_ID=""
if [[ "$CODE" == "201" || "$CODE" == "200" ]]; then
  ENCOUNTER_ID="$(json_get "$BODY" "obj.get('id') or (obj.get('data') or {}).get('id','')")"
  ok "POST encounters → $ENCOUNTER_ID"
elif [[ "$CODE" == "403" ]]; then
  sk "POST encounters → 403 (sem write authorization)"
else
  ko "POST encounters → $CODE $BODY"
fi

# --- 14) Observation vital-signs ---
echo
echo "[14] Observation vital-signs"
if [[ -n "$ENCOUNTER_ID" ]]; then
  OBS_BODY='{"type":"vital-signs","content":{"systolic":120,"diastolic":80,"heart_rate":72,"temperature":36.5,"spo2":98},"recorded_at":"'$(date -u +%Y-%m-%dT%H:%M:%SZ)'"}'
  raw="$(req POST "/patients/${PATIENT_ID}/encounters/${ENCOUNTER_ID}/observations" "$OBS_BODY")"
  split_body_code "$raw"
  OBS_ID=""
  if [[ "$CODE" == "201" || "$CODE" == "200" ]]; then
    OBS_ID="$(json_get "$BODY" "obj.get('id','')")"
    ok "POST observations → $OBS_ID"
  else
    ko "POST observations → $CODE $BODY"
  fi

  # --- 15) Versionamento (corrigir) ---
  echo
  echo "[15] Observation update (versionamento)"
  if [[ -n "$OBS_ID" ]]; then
    OBS_UPD='{"type":"vital-signs","content":{"systolic":118,"diastolic":78,"heart_rate":70,"temperature":36.6,"spo2":99},"recorded_at":"'$(date -u +%Y-%m-%dT%H:%M:%SZ)'"}'
    raw="$(req PUT "/patients/${PATIENT_ID}/encounters/${ENCOUNTER_ID}/observations/${OBS_ID}" "$OBS_UPD")"
    split_body_code "$raw"
    if [[ "$CODE" == "201" || "$CODE" == "200" ]]; then
      ok "PUT observations (nova versão) → $CODE"
    else
      ko "PUT observations → $CODE $BODY"
    fi
  else
    sk "versionamento — sem OBS_ID"
  fi
else
  sk "observations — sem ENCOUNTER_ID"
fi

# --- Summary ---
echo
echo "=============================================="
echo " Resultado: PASS=$PASS  FAIL=$FAIL  SKIP=$SKIP"
echo "=============================================="

if [[ "$FAIL" -gt 0 ]]; then
  exit 1
fi
exit 0
