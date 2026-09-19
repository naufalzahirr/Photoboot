#!/usr/bin/env python3
"""Local-only configuration. Secrets are entered privately and never printed."""
from pathlib import Path
import getpass, hashlib, re, secrets, os
root = Path(__file__).resolve().parents[1]
os.umask(0o077)
path = root / '.env'
if not path.exists():
    path.write_text((root / '.env.example').read_text())
local = root / '.local'
local.mkdir(exist_ok=True, mode=0o700)
token_file = local / 'device-token.txt'
if token_file.exists():
    token = token_file.read_text().strip()
else:
    token = secrets.token_urlsafe(48)
    token_file.write_text(token + '\n')
token_file.chmod(0o600)
key = getpass.getpass('Midtrans Sandbox Server Key (input hidden): ').strip()
if not re.fullmatch(r'(?:SB-)?Mid-server-[A-Za-z0-9_-]+', key):
    raise SystemExit('Expected a Server Key copied exactly from the Sandbox dashboard. Do not add a prefix. Nothing written to .env.')
values = {'MIDTRANS_ENVIRONMENT': 'sandbox', 'MIDTRANS_PRODUCTION_ENABLED': 'false', 'MIDTRANS_SERVER_KEY': key, 'BOOTH_DEVICE_TOKEN_HASH': hashlib.sha256(token.encode()).hexdigest(), 'APP_DEBUG': 'false'}
text = path.read_text()
for name, value in values.items():
    pattern = rf'^{re.escape(name)}=.*$'
    line = f'{name}={value}'
    text = re.sub(pattern, lambda _: line, text, flags=re.M) if re.search(pattern, text, flags=re.M) else text + '\n' + line + '\n'
path.write_text(text)
path.chmod(0o600)
print('Sandbox configured locally. Device token: backend/.local/device-token.txt (do not share).')
print('Run php artisan config:clear before testing.')
