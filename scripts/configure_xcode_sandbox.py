#!/usr/bin/env python3
"""Create a private user scheme without printing credentials or editing signing."""
from pathlib import Path
from urllib.parse import urlparse
import getpass, os, sys, xml.etree.ElementTree as ET
root = Path(__file__).resolve().parents[2]
if len(sys.argv) != 2:
    raise SystemExit('Usage: python3 backend/scripts/configure_xcode_sandbox.py https://backend-host')
url = sys.argv[1].rstrip('/')
p = urlparse(url)
if p.scheme != 'https' or not p.hostname or p.username or p.password or p.query or p.fragment or p.path:
    raise SystemExit('Use the HTTPS origin only, without a path or credentials.')
token = (root/'backend/.local/device-token.txt').read_text().strip()
if len(token) < 32: raise SystemExit('Device token not configured.')
project = root/'PhotoBooth.xcodeproj'
source = project/'xcshareddata/xcschemes/PhotoBooth.xcscheme'
tree = ET.parse(source)
launch = tree.getroot().find('LaunchAction')
for item in list(launch):
    if item.tag == 'EnvironmentVariables': launch.remove(item)
variables = ET.SubElement(launch, 'EnvironmentVariables')
for key, value in {'PHOTOBOOTH_PAYMENT_MODE':'sandbox','PHOTOBOOTH_SANDBOX_API_URL':url+'/api','PHOTOBOOTH_DEVICE_TOKEN':token}.items():
    ET.SubElement(variables,'EnvironmentVariable',{'key':key,'value':value,'isEnabled':'YES'})
# Tests must stay mock-only, even when selecting the sandbox launch scheme.
tree.getroot().find('TestAction').set('shouldUseLaunchSchemeArgsEnv','NO')
target = project/'xcuserdata'/f'{getpass.getuser()}.xcuserdatad'/'xcschemes'/'PhotoBooth Sandbox.xcscheme'
target.parent.mkdir(parents=True, exist_ok=True)
os.umask(0o077)
tree.write(target, encoding='UTF-8', xml_declaration=True)
target.chmod(0o600)
(root/'backend/.local/sandbox-origin.txt').write_text(url+'\n')
print('Private scheme ready: PhotoBooth Sandbox (device token not displayed).')
