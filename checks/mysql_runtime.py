"""Run real MySQL checks using a disposable local server, never installed app databases."""
import argparse
import os
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
HIDDEN = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0

def run():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--mysqld', default=os.environ.get('MEDIA_BROWSER_MYSQLD') or shutil.which('mysqld'))
    args = parser.parse_args()
    if not args.mysqld and os.name == 'nt':
        candidates = sorted(Path('D:/laragon/bin/mysql').glob('*/bin/mysqld.exe'), reverse=True)
        if candidates:
            args.mysqld = str(candidates[0])
    if not args.mysqld:
        parser.error('Set --mysqld or MEDIA_BROWSER_MYSQLD to a local MySQL server executable.')
    daemon = Path(args.mysqld).resolve()
    client = daemon.with_name('mysql.exe' if os.name == 'nt' else 'mysql')
    admin = daemon.with_name('mysqladmin.exe' if os.name == 'nt' else 'mysqladmin')
    with tempfile.TemporaryDirectory(prefix='media-mysql-') as temp:
        directory = Path(temp)
        with socket.socket() as reservation:
            reservation.bind(('127.0.0.1', 0))
            port = reservation.getsockname()[1]
        log_path = directory / 'server.log'
        base = [str(daemon), '--no-defaults', f'--basedir={daemon.parent.parent}', f'--datadir={directory / "data"}']
        server = None
        try:
            with log_path.open('wb') as log:
                subprocess.run(base + ['--initialize-insecure', '--console'], check=True, stdout=log, stderr=log, timeout=120, creationflags=HIDDEN)
                server = subprocess.Popen(base + [f'--port={port}', '--bind-address=127.0.0.1', '--mysqlx=OFF', '--skip-log-bin', '--innodb-buffer-pool-size=64M', f'--pid-file={directory / "mysql.pid"}', '--console'], stdout=log, stderr=log, creationflags=HIDDEN)
                connection = ['--no-defaults', '--host=127.0.0.1', f'--port={port}', '--user=root', '--protocol=TCP']
                for _ in range(120):
                    if server.poll() is not None:
                        raise RuntimeError('Disposable MySQL server exited during startup.')
                    ready = subprocess.run([str(admin), *connection, 'ping'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=HIDDEN)
                    if ready.returncode == 0:
                        break
                    time.sleep(.25)
                else:
                    raise RuntimeError('Disposable MySQL server did not become ready.')
                subprocess.run([str(client), *connection, '-e', 'CREATE DATABASE media_browser_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'], check=True, stdout=subprocess.DEVNULL, stderr=log, creationflags=HIDDEN)
                env = dict(os.environ, MEDIA_BROWSER_TEST_MYSQL_DSN=f'mysql:host=127.0.0.1;port={port};dbname=media_browser_check;charset=utf8mb4', MEDIA_BROWSER_TEST_MYSQL_USER='root', MEDIA_BROWSER_TEST_MYSQL_PASSWORD='')
                php = shutil.which('php') or 'php'
                scripts = ['checks/database.php', 'checks/seed.php', 'checks/store.php', 'checks/kernel.php', 'checks/field.php']
                if (ROOT / 'checks/frameworks/vendor/autoload.php').exists():
                    scripts += ['checks/frameworks/laravel.php', 'checks/frameworks/codeigniter.php']
                for script in scripts:
                    result = subprocess.run([php, '-d', 'display_errors=0', '-d', 'display_startup_errors=0', script], cwd=ROOT, env=env, capture_output=True, text=True, creationflags=HIDDEN)
                    print(result.stdout.strip(), flush=True)
                    if result.returncode:
                        print(result.stderr, file=sys.stderr)
                        result.check_returncode()
                result = subprocess.run([sys.executable, 'checks/api_http.py'], cwd=ROOT, env=env, capture_output=True, text=True, creationflags=HIDDEN)
                print(result.stdout.strip(), flush=True)
                if result.returncode:
                    print(result.stderr, file=sys.stderr)
                    result.check_returncode()
                version = subprocess.check_output([str(daemon), '--version'], text=True, creationflags=HIDDEN).strip()
                print(f'PASS: disposable {version}; storage, helper, profiles, native forms, HTTP and available framework adapters.', flush=True)
                subprocess.run([str(admin), *connection, 'shutdown'], stdout=subprocess.DEVNULL, stderr=log, timeout=15, creationflags=HIDDEN)
                server.wait(timeout=20)
        except Exception:
            if log_path.exists():
                print(log_path.read_text(errors='replace')[-5000:], file=sys.stderr)
            raise
        finally:
            if server is not None and server.poll() is None:
                server.terminate()
                server.wait(timeout=20)

if __name__ == '__main__':
    run()
