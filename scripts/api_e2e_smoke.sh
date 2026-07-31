#!/bin/bash
# Posto de Enfermagem API — Smoke E2E (curl + php, sem python3)
# Uso: chmod +x scripts/api_e2e_smoke.sh && ./scripts/api_e2e_smoke.sh

set -eu

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
CODE=""
BODY=""

ok() { PASS=$((PASS+1)); printf '  OK  %s\n' "$*"; }
ko() { FAIL=$((FAIL+1)); printf '  FAIL %s\n' "$*"; }
sk() { SKIP=$((SKIP+1)); printf '  SKIP %s\n' "$*"; }

json_get() {
  local payload="$1"
  local path="$2"
  printf '%s' "$payload" | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
if(!is_array($j))exit;
$cur=$j;
foreach(explode(".",$argv[1]) as $p){
  if($p==="")continue;
  if(is_array($cur)&&array_key_exists($p,$cur))$cur=$cur[$p];
  elseif(is_array($cur)&&ctype_digit($p)&&array_key_exists((int)$p,$cur))$cur=$cur[(int)$p];
  else exit;
}
if(is_bool($cur))echo $cur?"true":"false";
elseif(is_array($cur)||is_object($cur))echo json_encode($cur);
elseif($cur===null)echo "";
else echo $cur;
' "$path" 2>/dev/null || true
}

json_count() {
  local payload="$1"
  printf '%s' "$payload" | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
if(!is_array($j)){echo 0;exit;}
$isList=function_exists("array_is_list")?array_is_list($j):(array_keys($j)===range(0,count($j)-1));
if($isList){echo count($j);exit;}
if(isset($j["data"])&&is_array($j["data"])){echo count($j["data"]);exit;}
echo 0;
' 2>/dev/null || echo 0
}

req() {
  local method="$1" path="$2" body="${3:-}"
  local url="${BASE_URL}${path}"
  local hdr=(-H 'Accept: application/json' -H 'Content-Type: application/json')
  if [ -n "$TENANT_ID" ]; then hdr+=(-H "X-Tenant-ID: $TENANT_ID"); fi
  if [ -n "$APP_TOKEN" ]; then hdr+=(-H "X-App-Token: $APP_TOKEN"); fi
  if [ -n "$ACCESS_TOKEN" ]; then hdr+=(-H "Authorization: Bearer $ACCESS_TOKEN"); fi
  if [ -n "$body" ]; then
    curl -sS -w '\n%{http_code}' -X "$method" "$url" "${hdr[@]}" -d "$body" 2>/dev/null || printf '%s\n000\n' '{"error":"curl_failed"}'
  else
    curl -sS -w '\n%{http_code}' -X "$method" "$url" "${hdr[@]}" 2>/dev/null || printf '%s\n000\n' '{"error":"curl_failed"}'
  fi
}

split_body_code() {
  local raw="$1"
  CODE=$(printf '%s' "$raw" | tail -n 1)
  BODY=$(printf '%s' "$raw" | sed '$d')
}

iso_now() { date -u +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || php -r 'echo gmdate("Y-m-d\TH:i:s\Z");'; }

echo "=============================================="
echo " API E2E Smoke — Posto de Enfermagem"
echo " BASE_URL=$BASE_URL"
echo "=============================================="

echo; echo "[0] Health"
split_body_code "$(req GET /health)"
if [ "$CODE" = "200" ]; then ok "GET /health → 200"; else ko "GET /health → $CODE ($BODY)"; echo "Dica: php artisan up"; exit 1; fi

echo; echo "[1] Application M2M"
split_body_code "$(req POST /auth/application/token "{\"client_id\":\"$CLIENT_ID\",\"client_secret\":\"$CLIENT_SECRET\"}")"
if [ "$CODE" = "200" ]; then
  APP_TOKEN=$(json_get "$BODY" app_token)
  TENANT_ID=$(json_get "$BODY" tenant_id)
  if [ -n "$APP_TOKEN" ] && [ -n "$TENANT_ID" ]; then ok "POST /auth/application/token"; else ko "sem token/tenant: $BODY"; exit 1; fi
else ko "application/token → $CODE $BODY"; exit 1; fi

echo; echo "[2] Login"
split_body_code "$(req POST /auth/login "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")"
if [ "$CODE" = "200" ]; then
  ACCESS_TOKEN=$(json_get "$BODY" access_token)
  [ -z "$ACCESS_TOKEN" ] && ACCESS_TOKEN=$(json_get "$BODY" token)
  MFA=$(json_get "$BODY" mfa_required)
  if [ "$MFA" = "true" ] || [ "$MFA" = "1" ]; then sk "mfa_required"; echo "$BODY"; exit 0; fi
  if [ -n "$ACCESS_TOKEN" ]; then ok "login"; else ko "sem access_token: $BODY"; exit 1; fi
else ko "login → $CODE $BODY"; exit 1; fi

echo; echo "[3] /user"
split_body_code "$(req GET /user)"
if [ "$CODE" = "200" ]; then USER_ID=$(json_get "$BODY" id); ok "user $(json_get "$BODY" name)"; else ko "/user → $CODE $BODY"; fi

echo; echo "[4] patients"
split_body_code "$(req GET /patients)"
if [ "$CODE" = "200" ]; then
  ok "patients count≈$(json_count "$BODY")"
  PATIENT_ID=$(json_get "$BODY" 0.id)
  [ -z "$PATIENT_ID" ] && PATIENT_ID=$(json_get "$BODY" data.0.id)
else ko "patients → $CODE $BODY"; fi

echo; echo "[5] lookup CPF"
split_body_code "$(req GET "/patients/lookup/cpf/$PATIENT_CPF")"
if [ "$CODE" = "200" ]; then
  PATIENT_ID=$(json_get "$BODY" id)
  [ -z "$PATIENT_ID" ] && PATIENT_ID=$(json_get "$BODY" data.id)
  ok "lookup → $PATIENT_ID"
elif [ "$CODE" = "403" ]; then sk "lookup 403"; elif [ "$CODE" = "404" ]; then sk "lookup 404"; else ko "lookup → $CODE $BODY"; fi

if [ -z "$PATIENT_ID" ]; then ko "sem PATIENT_ID"; echo "PASS=$PASS FAIL=$FAIL SKIP=$SKIP"; exit 1; fi

echo; echo "[6] show patient"
split_body_code "$(req GET "/patients/$PATIENT_ID")"
if [ "$CODE" = "200" ]; then ok "show patient"; elif [ "$CODE" = "403" ]; then sk "show 403"; else ko "show → $CODE $BODY"; fi

echo; echo "[7] care-authorizations"
split_body_code "$(req GET "/patients/$PATIENT_ID/care-authorizations")"
if [ "$CODE" = "200" ]; then ok "care-authorizations"; else ko "care-auth → $CODE $BODY"; fi

echo; echo "[8] consents"
split_body_code "$(req GET "/patients/$PATIENT_ID/consents")"
if [ "$CODE" = "200" ]; then ok "consents"; else ko "consents → $CODE $BODY"; fi

echo; echo "[9] create consent"
CREATE_BODY="{\"context\":\"appointment_care\",\"professional_user_id\":\"$USER_ID\",\"purposes\":[\"atendimento_clinico\",\"prontuario\"],\"data_categories\":[\"dados_cadastrais\",\"dados_clinicos\"],\"consent_text_version\":\"appointment_care_v1\"}"
split_body_code "$(req POST "/patients/$PATIENT_ID/consents" "$CREATE_BODY")"
if [ "$CODE" = "201" ] || [ "$CODE" = "200" ]; then CONSENT_ID=$(json_get "$BODY" id); ok "consent $CONSENT_ID"; else ko "create consent → $CODE $BODY"; fi

echo; echo "[10] deny"
if [ -n "$CONSENT_ID" ]; then
  split_body_code "$(req POST "/patients/$PATIENT_ID/consents/$CONSENT_ID/deny" '{}')"
  if [ "$CODE" = "200" ]; then ok "deny"; else ko "deny → $CODE $BODY"; fi
else sk "deny sem id"; fi

echo; echo "[11] accept"
split_body_code "$(req POST "/patients/$PATIENT_ID/consents" "$CREATE_BODY")"
CONSENT_ID2=$(json_get "$BODY" id)
if [ -n "$CONSENT_ID2" ]; then
  split_body_code "$(req POST "/patients/$PATIENT_ID/consents/$CONSENT_ID2/accept" '{}')"
  if [ "$CODE" = "200" ]; then ok "accept"; elif [ "$CODE" = "401" ]; then sk "accept 401 senha"; else ko "accept → $CODE $BODY"; fi
else sk "accept sem consent"; fi

echo; echo "[12] encounters"
split_body_code "$(req GET "/patients/$PATIENT_ID/encounters")"
if [ "$CODE" = "200" ]; then ok "encounters"; elif [ "$CODE" = "403" ]; then sk "encounters 403"; else ko "encounters → $CODE $BODY"; fi

echo; echo "[13] create encounter"
NOW=$(iso_now)
split_body_code "$(req POST "/patients/$PATIENT_ID/encounters" "{\"status\":\"in-progress\",\"reason\":\"Smoke E2E\",\"start_time\":\"$NOW\"}")"
if [ "$CODE" = "201" ] || [ "$CODE" = "200" ]; then
  ENCOUNTER_ID=$(json_get "$BODY" id)
  [ -z "$ENCOUNTER_ID" ] && ENCOUNTER_ID=$(json_get "$BODY" data.id)
  ok "encounter $ENCOUNTER_ID"
elif [ "$CODE" = "403" ]; then sk "encounter 403"; else ko "encounter → $CODE $BODY"; fi

echo; echo "[14] observation"
if [ -n "$ENCOUNTER_ID" ]; then
  NOW=$(iso_now)
  split_body_code "$(req POST "/patients/$PATIENT_ID/encounters/$ENCOUNTER_ID/observations" "{\"type\":\"vital-signs\",\"content\":{\"systolic\":120,\"diastolic\":80,\"heart_rate\":72},\"recorded_at\":\"$NOW\"}")"
  if [ "$CODE" = "201" ] || [ "$CODE" = "200" ]; then
    OBS_ID=$(json_get "$BODY" id)
    ok "observation $OBS_ID"
  else ko "observation → $CODE $BODY"; fi

  echo; echo "[15] version observation"
  if [ -n "$OBS_ID" ]; then
    NOW=$(iso_now)
    split_body_code "$(req PUT "/patients/$PATIENT_ID/encounters/$ENCOUNTER_ID/observations/$OBS_ID" "{\"type\":\"vital-signs\",\"content\":{\"systolic\":118,\"diastolic\":78},\"recorded_at\":\"$NOW\"}")"
    if [ "$CODE" = "201" ] || [ "$CODE" = "200" ]; then ok "version $CODE"; else ko "version → $CODE $BODY"; fi
  else sk "version sem obs"; fi
else sk "obs sem encounter"; fi

echo
echo "=============================================="
echo " Resultado: PASS=$PASS  FAIL=$FAIL  SKIP=$SKIP"
echo "=============================================="
[ "$FAIL" -gt 0 ] && exit 1
exit 0
