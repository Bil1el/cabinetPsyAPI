#!/usr/bin/env bash

set -Eeuo pipefail

backend_url='http://localhost:8000'
frontend_url='http://localhost:5174'

if command -v curl.exe >/dev/null 2>&1; then
    curl_bin='curl.exe'
    null_device='NUL'
else
    curl_bin='curl'
    null_device='/dev/null'
fi

check_status() {
    local expected_status=$1
    local url=$2
    shift 2
    local status

    status=$("${curl_bin}" --silent --show-error --output "${null_device}" --write-out '%{http_code}' --max-time 5 "$@" "${url}")

    if [[ "${status}" != "${expected_status}" ]]; then
        printf 'FAIL %s: expected HTTP %s, received %s\n' "${url}" "${expected_status}" "${status}" >&2
        return 1
    fi

    printf 'PASS %s: HTTP %s\n' "${url}" "${status}"
}

check_status 204 "${backend_url}/sanctum/csrf-cookie"

api_response=$("${curl_bin}" --silent --show-error --max-time 5 -H 'Accept: application/json' --write-out $'\n%{http_code}\n%{content_type}' "${backend_url}/api/me")
api_status=$(printf '%s\n' "${api_response}" | tail -n 2 | head -n 1)
api_content_type=$(printf '%s\n' "${api_response}" | tail -n 1)
api_body=$(printf '%s\n' "${api_response}" | sed '$d; $d')

if [[ "${api_status}" != '401' ]] || [[ "${api_content_type}" != application/json* ]] || ! grep -q '"message":"Unauthenticated\."' <<<"${api_body}"; then
    printf 'FAIL %s: expected JSON HTTP 401 Unauthenticated response\n' "${backend_url}/api/me" >&2
    exit 1
fi

printf 'PASS %s: JSON HTTP 401\n' "${backend_url}/api/me"
check_status 200 "${frontend_url}/"
