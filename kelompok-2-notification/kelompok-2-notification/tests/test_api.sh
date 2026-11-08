#!/usr/bin/env bash
# Uji Notification Service.
# Normal  : BASE=http://127.0.0.1:8106 TOKEN=<token asli> bash tests/test_api.sh
# Bypass  : BYPASS=true bash tests/test_api.sh   (jika service dijalankan dengan AUTH_BYPASS=true)
BASE="${BASE:-http://127.0.0.1:8106}"
TOKEN="${TOKEN:-dummy}"
PASS=0; FAIL=0
H=(-H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json")

check() { # nama, status diharapkan, status asli
  if [ "$2" = "$3" ]; then echo "PASS  $1 ($3)"; PASS=$((PASS+1)); else echo "FAIL  $1 (harapan $2, dapat $3)"; FAIL=$((FAIL+1)); fi
}
code() { curl -s -o /tmp/body.json -w '%{http_code}' "$@"; }

check "Health"                  200 "$(code $BASE/health)"
if [ "$BYPASS" = "true" ]; then echo "SKIP  Tanpa token (AUTH_BYPASS aktif)"; else
check "Tanpa token"             401 "$(code "$BASE/api/notifications?user_id=user-001")"; fi
check "POST sukses"             201 "$(code -X POST "${H[@]}" -d '{"user_id":"user-001","message":"Submission berhasil diterima","type":"submission","submission_id":"sub-001"}' $BASE/api/notifications)"
ID=$(grep -o '"id":"[^"]*"' /tmp/body.json | head -1 | cut -d'"' -f4)
check "POST field kosong"       400 "$(code -X POST "${H[@]}" -d '{"user_id":"user-001"}' $BASE/api/notifications)"
check "POST JSON rusak"         400 "$(code -X POST "${H[@]}" -d '{rusak' $BASE/api/notifications)"
check "POST Content-Type salah" 415 "$(code -X POST -H "Authorization: Bearer $TOKEN" -H 'Content-Type: text/plain' -d x $BASE/api/notifications)"
check "GET tanpa user_id"       400 "$(code "${H[@]}" $BASE/api/notifications)"
check "GET daftar"              200 "$(code "${H[@]}" "$BASE/api/notifications?user_id=user-001")"
check "PATCH read"              200 "$(code -X PATCH "${H[@]}" $BASE/api/notifications/$ID/read)"
check "PATCH id tidak ada"      404 "$(code -X PATCH "${H[@]}" $BASE/api/notifications/notif-999/read)"
check "Endpoint salah"          404 "$(code $BASE/api/xyz)"

echo "Hasil: $PASS lulus, $FAIL gagal"; [ $FAIL -eq 0 ]
