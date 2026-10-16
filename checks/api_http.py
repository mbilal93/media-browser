"""Exercise the real PHP API in isolated temporary storage using the standard library."""
import base64
import http.cookiejar
import json
import html
import re
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import secrets
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PNG = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')

def run():
    with tempfile.TemporaryDirectory(prefix='media-http-') as storage:
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}/'
        env = dict(os.environ, MEDIA_BROWSER_STORAGE=storage, MEDIA_BROWSER_PASSWORD='test-only-password')
        env.update(MEDIA_BROWSER_DB_DRIVER='sqlite', MEDIA_BROWSER_DB_DSN='', MEDIA_BROWSER_DB_TABLE='media', MEDIA_BROWSER_DB_AUTO_CREATE='true')
        if os.environ.get('MEDIA_BROWSER_TEST_MYSQL_DSN'):
            env.update(MEDIA_BROWSER_DB_DRIVER='mysql', MEDIA_BROWSER_DB_DSN=os.environ['MEDIA_BROWSER_TEST_MYSQL_DSN'],
                       MEDIA_BROWSER_DB_USER=os.environ.get('MEDIA_BROWSER_TEST_MYSQL_USER', 'root'),
                       MEDIA_BROWSER_DB_PASSWORD=os.environ.get('MEDIA_BROWSER_TEST_MYSQL_PASSWORD', ''),
                       MEDIA_BROWSER_DB_TABLE='mb_check_' + secrets.token_hex(6))
        with tempfile.TemporaryFile() as log:
            process = subprocess.Popen([shutil.which('php') or 'php', '-d', 'display_errors=0', '-d', 'display_startup_errors=0', '-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public')], env=env, stdout=log, stderr=log)
            try:
                client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

                def request(path, body=None, csrf=None, content_type=None):
                    headers = {}
                    if csrf is not None:
                        headers['X-CSRF-Token'] = csrf
                    if isinstance(body, dict):
                        body = urllib.parse.urlencode(body).encode()
                        headers['Content-Type'] = 'application/x-www-form-urlencoded'
                    if content_type:
                        headers['Content-Type'] = content_type
                    req = urllib.request.Request(base + path, data=body, headers=headers)
                    try:
                        response = client.open(req, timeout=10)
                    except urllib.error.HTTPError as error:
                        response = error
                    raw = response.read()
                    try:
                        value = json.loads(raw) if 'application/json' in response.headers.get('Content-Type', '') else raw
                    except json.JSONDecodeError as error:
                        raise RuntimeError(f'Invalid JSON response ({response.status}): {raw[:600]!r}') from error
                    return response.status, value, response.headers

                for _ in range(80):
                    try:
                        status, session, _ = request('api.php?action=session')
                        break
                    except urllib.error.URLError:
                        if process.poll() is not None:
                            raise RuntimeError('PHP server exited before startup.')
                        time.sleep(0.1)
                else:
                    raise RuntimeError('PHP server did not start.')
                status, home, _ = request('')
                assert status == 200 and b'Integration samples' in home
                assert b'<script' not in home, 'Directory must render without JavaScript.'
                for framework in ('laravel', 'codeigniter', 'core-php'):
                    link = 'sample.php?framework=' + framework
                    assert link.encode() in home
                    sample_status, source_page, _ = request(link)
                    assert sample_status == 200 and b'Resolve submitted images' in source_page
                    assert b'&lt;' in source_page, 'Sample source must be escaped.'
                assert request('sample.php?framework=../../config.php')[0] == 404
                assert request('sample.php?framework[]=laravel')[0] == 404
                login_page = request('playground.php')[1]
                assert b'id="login-panel" >' in login_page, 'Login must be visible before scripts run.'
                csrf = session['csrf']
                assert not session['authorized']
                assert request('api.php?action=list')[0] == 401
                assert request('api.php?action=delete')[0] == 405
                assert request('api.php?action=login', {'password': 'test-only-password'})[0] == 403
                rejected_login = request('api.php?action=login', {'password': 'wrong'}, csrf)
                assert rejected_login[0] == 401, rejected_login[:2]
                assert request('api.php?action=login', {'password': 'test-only-password'}, csrf)[0] == 200
                assert request('playground.php')[0] == 200
                assert b'id="demo-panel" >' in request('playground.php')[1], 'Demo must be visible before scripts run.'
                assert request('assets/media-browser.js')[0] == 200
                assert request('api.php?action=list')[1]['total'] == 0

                def upload(data, name, mime, profile=''):
                    boundary = 'test-media-browser-boundary'
                    payload = (f'--{boundary}\r\nContent-Disposition: form-data; name="files[]"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + data + f'\r\n--{boundary}--\r\n'.encode())
                    return request('api.php?action=upload' + ('&profile=' + profile if profile else ''), payload, csrf, f'multipart/form-data; boundary={boundary}')

                status, result, _ = upload(PNG, 'sample.png', 'image/png')
                assert status == 201, result
                item = result['items'][0]
                second = upload(PNG, 'second.png', 'image/png')[1]['items'][0]
                selected = request('selection.php', {'gallery_ids': json.dumps([second['id'], item['id']]), 'cover_ids': json.dumps([item['id']])}, csrf)
                assert selected[0] == 200, selected[:2]
                assert [image['id'] for image in selected[1]['images']['gallery']] == [second['id'], item['id']]
                native_form = request('playground.php', {'_media_csrf': csrf, 'gallery_ids': json.dumps([second['id'], item['id']]), 'cover_ids': json.dumps([item['id']])})
                assert native_form[0] == 200, native_form[:2]
                form_html = native_form[1].decode()
                result_text = re.search(r'<pre id="form-result"[^>]*>(.*?)</pre>', form_html, re.S).group(1)
                resolved_form = json.loads(html.unescape(result_text))
                assert [image['id'] for image in resolved_form['images']['gallery']] == [second['id'], item['id']]
                restored_hidden = re.search(r'name="gallery_ids" value="([^"]*)"', form_html).group(1)
                assert json.loads(html.unescape(restored_hidden)) == [second['id'], item['id']]
                assert 'method="post"' in form_html and 'type="reset"' in form_html
                assert request('playground.php', {'gallery_ids': '[]'})[0] == 403
                assert request('playground.php', {'_media_csrf': csrf, 'gallery_ids': '[bad'})[0] == 422
                assert request('selection.php', {'gallery_ids': '[]'}, None)[0] == 403
                assert request('selection.php', {'gallery_ids': '[invalid'}, csrf)[0] == 422
                assert request('selection.php', {'gallery_ids': json.dumps([item['id'], item['id']])}, csrf)[0] == 422
                profile_session = request('api.php?action=session&profile=product_gallery')[1]
                assert profile_session['settings']['pageSize'] == 6 and profile_session['settings']['maxBytes'] == 5 * 1024 * 1024
                assert request('api.php?action=list&profile=missing')[0] == 422
                profile_list = request('api.php?action=list&profile=product_gallery')[1]
                assert 'profile=product_gallery' in profile_list['items'][0]['url']
                pdf = b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'
                assert upload(pdf, 'guide.pdf', 'application/pdf', 'product_gallery')[0] == 422
                document = upload(pdf, 'guide.pdf', 'application/pdf')[1]['items'][0]
                assert request('selection.php', {'gallery_ids': json.dumps([document['id']])}, csrf)[0] == 422
                assert request('api.php?action=list&profile=product_gallery')[1]['total'] == 2
                page = request('playground.php')[1]
                assert b'data-media-field=' in page and b'gallery_ids' in page
                assert request('assets/media-field.js')[0] == 200
                status, content, headers = request(item['url'])
                assert status == 200 and content == PNG and headers['Content-Type'] == 'image/png'
                assert request('api.php?action=rename', {'id': item['id'], 'name': 'Updated cover.png'}, csrf)[1]['item']['url'] == item['url']
                assert request('api.php?action=rename', {'id': item['id'], 'name': '../escape'}, csrf)[0] == 422
                assert request('api.php?action=list&q=Updated')[1]['total'] == 1
                assert request('api.php?action=list&q%5B%5D=invalid')[0] == 422
                assert request('api.php?action=rename', {'id': item['id'], 'name[]': 'invalid'}, csrf)[0] == 422
                assert request('api.php?action%5B%5D=list')[0] == 422
                assert upload(b'<?php echo "bad";', 'fake.png', 'image/png')[0] == 422
                assert request('api.php?action=file&id=../../config.php')[0] == 404
                assert request('api.php?action=delete', {'id': item['id']}, csrf)[0] == 200
                assert request(item['url'])[0] == 404
                before_seed = request('api.php?action=list')[1]['total']
                php_command = [shutil.which('php') or 'php', '-d', 'display_errors=0', '-d', 'display_startup_errors=0']
                setup = subprocess.run([*php_command, 'bin/setup.php'], cwd=ROOT, env=env, capture_output=True, text=True)
                assert setup.returncode == 0, setup.stderr
                seed = subprocess.run([*php_command, 'bin/seed.php'], cwd=ROOT, env=env, capture_output=True, text=True)
                assert seed.returncode == 0 and '3 added, 0 already present' in seed.stdout, (seed.stdout, seed.stderr)
                assert request('api.php?action=list')[1]['total'] == before_seed + 3
                repeat_seed = subprocess.run([*php_command, 'demo/seed.php'], cwd=ROOT, env=env, capture_output=True, text=True)
                assert repeat_seed.returncode == 0 and '0 added, 3 already present' in repeat_seed.stdout, (repeat_seed.stdout, repeat_seed.stderr)
                assert request('api.php?action=list')[1]['total'] == before_seed + 3
                core_form = request('form-example.php', {'_media_csrf': csrf, 'product_images': json.dumps([second['id']])})
                assert core_form[0] == 200, core_form[:2]
                core_text = re.search(r'<pre id="core-result"[^>]*>(.*?)</pre>', core_form[1].decode(), re.S).group(1)
                assert [image['id'] for image in json.loads(html.unescape(core_text))] == [second['id']]
                assert request('form-example.php', {'product_images': '[]'})[0] == 403
                assert request('api.php?action=logout', {}, csrf)[0] == 200
                assert request('api.php?action=list')[0] == 401
                assert request('selection.php', {'gallery_ids': '[]'}, csrf)[0] == 401
                print('PASS: HTTP auth/CSRF, uploads, profiles, normal/AJAX/core PHP forms, configured CLI setup/seed, repeat seeding and ordered image arrays.')
            finally:
                process.terminate()
                process.wait(timeout=10)

if __name__ == '__main__':
    run()
