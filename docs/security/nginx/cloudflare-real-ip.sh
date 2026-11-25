#!/bin/sh
set -eu

tmp_dir=$(mktemp -d)
trap 'rm -f "$tmp_dir/ipv4" "$tmp_dir/ipv6"; rmdir "$tmp_dir"' EXIT
curl -fsS --proto '=https' --max-time 20 https://www.cloudflare.com/ips-v4 -o "$tmp_dir/ipv4"
curl -fsS --proto '=https' --max-time 20 https://www.cloudflare.com/ips-v6 -o "$tmp_dir/ipv6"

python3 - "$tmp_dir/ipv4" "$tmp_dir/ipv6" <<'PY'
import ipaddress
import pathlib
import sys

networks = []
for version, path in zip((4, 6), sys.argv[1:]):
    lines = pathlib.Path(path).read_text(encoding="ascii").splitlines()
    table = [ipaddress.ip_network(line) for line in lines if line.strip()]
    if not table or any(n.version != version or not n.is_global or n.prefixlen < (8 if version == 4 else 16) for n in table):
        raise SystemExit("Cloudflare network list failed validation")
    networks.extend(table)

print("real_ip_header CF-Connecting-IP;")
for network in networks:
    print(f"set_real_ip_from {network};")
PY
