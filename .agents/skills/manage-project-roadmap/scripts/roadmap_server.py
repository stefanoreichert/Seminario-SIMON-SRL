"""Validated, loopback-only preview; serves snapshots, never arbitrary files."""
from __future__ import annotations

import hashlib
import json
import socket
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import quote, unquote, urlsplit

from roadmap import render, snapshot, write_document


class Monitor:
    def __init__(self, root: Path, plan_dir: Path, output: Path):
        self.root, self.plan_dir, self.output = root, plan_dir, output
        self.lock = threading.RLock()
        self.attempted = None
        self.graph, self.sources, self.document, self.error = None, {}, b'', ''

    def fingerprint(self) -> str:
        digest = hashlib.sha256()
        for path in sorted(self.plan_dir.rglob('*.md')):
            if path.is_symlink() or not path.resolve().is_relative_to(self.plan_dir.resolve()):
                raise ValueError('El plan contiene una fuente enlazada fuera de sus archivos.')
            digest.update(path.relative_to(self.plan_dir).as_posix().encode('utf-8') + b'\0' + path.read_bytes() + b'\0')
        config = self.root / '.agents/project.json'
        if config.is_symlink():
            raise ValueError('La configuración no puede ser un enlace simbólico.')
        if config.exists():
            digest.update(config.read_bytes())
        return digest.hexdigest()

    def refresh(self) -> bool:
        with self.lock:
            fingerprint = None
            try:
                fingerprint = self.fingerprint()
                if fingerprint == self.attempted:
                    return False
                graph, sources = snapshot(self.root, self.plan_dir, self.output)
                if self.fingerprint() != fingerprint:
                    return False
                for task in graph['tasks']:
                    task['sourceUrl'] = '__roadmap__/source/' + quote(task['source'], safe='/')
                graph.update(revision=fingerprint,
                             live={'statusUrl': '__roadmap__/status', 'dataUrl': '__roadmap__/data', 'intervalMs': 1000})
                document = render(graph)
                write_document(self.output, document)
                self.graph, self.sources, self.document = graph, sources, document.encode('utf-8')
                self.attempted, self.error = fingerprint, ''
                return True
            except (OSError, ValueError) as error:
                self.error = str(error)
                if isinstance(error, ValueError) and fingerprint is not None:
                    self.attempted = fingerprint
                return False

    def status(self) -> dict:
        with self.lock:
            return {'projectId': (self.graph or {}).get('projectId'),
                    'projectName': (self.graph or {}).get('projectName'),
                    'revision': (self.graph or {}).get('revision'),
                    'generatedAt': (self.graph or {}).get('generatedAt'),
                    'status': 'invalid' if self.error else 'ok', 'error': self.error}


class RoadmapHTTPServer(ThreadingHTTPServer):
    allow_reuse_address = not hasattr(socket, 'SO_EXCLUSIVEADDRUSE')

    def server_bind(self) -> None:
        if hasattr(socket, 'SO_EXCLUSIVEADDRUSE'):
            self.socket.setsockopt(socket.SOL_SOCKET, socket.SO_EXCLUSIVEADDRUSE, 1)
        super().server_bind()


def create_server(monitor: Monitor, port: int = 8788) -> ThreadingHTTPServer:
    class Handler(BaseHTTPRequestHandler):
        def do_GET(self):
            allowed_hosts = {f'127.0.0.1:{self.server.server_port}', f'localhost:{self.server.server_port}'}
            if self.headers.get('Host') not in allowed_hosts:
                self.send_error(403)
                return
            route = unquote(urlsplit(self.path).path)
            with monitor.lock:
                if route in {'/', '/' + monitor.output.name}:
                    self.send_payload(monitor.document, 'text/html')
                elif route == '/__roadmap__/status':
                    self.send_payload(json.dumps(monitor.status(), ensure_ascii=False).encode(), 'application/json')
                elif route == '/__roadmap__/data':
                    self.send_payload(json.dumps(monitor.graph, ensure_ascii=False).encode(), 'application/json')
                elif route.startswith('/__roadmap__/source/') and route[len('/__roadmap__/source/'):] in monitor.sources:
                    self.send_payload(monitor.sources[route[len('/__roadmap__/source/'):]].encode(), 'text/plain')
                else:
                    self.send_error(404)

        def send_payload(self, payload: bytes, content_type: str):
            self.send_response(200)
            self.send_header('Content-Type', content_type + '; charset=utf-8')
            self.send_header('Content-Length', str(len(payload)))
            self.send_header('Cache-Control', 'no-store')
            self.send_header('X-Content-Type-Options', 'nosniff')
            self.end_headers()
            self.wfile.write(payload)

        def log_message(self, format, *args):
            pass

    return RoadmapHTTPServer(('127.0.0.1', port), Handler)


def serve(root: Path, plan_dir: Path, output: Path, port: int, interval: float) -> int:
    monitor = Monitor(root, plan_dir, output)
    server = create_server(monitor, port)
    if not monitor.refresh():
        server.server_close()
        raise ValueError(monitor.error or 'El plan cambió durante la lectura; reintentar con las fuentes estables.')
    stopped = threading.Event()

    def watch():
        while not stopped.wait(interval):
            monitor.refresh()

    worker = threading.Thread(target=watch, name='roadmap-watch', daemon=True)
    worker.start()
    print(f"{monitor.graph['projectName']} — proyecto {monitor.graph['projectId']}\n"
          f'http://127.0.0.1:{server.server_port}/{output.name}\nCtrl+C para detener.', flush=True)
    try:
        server.serve_forever(poll_interval=0.25)
    except KeyboardInterrupt:
        pass
    finally:
        stopped.set()
        server.server_close()
        worker.join(timeout=2)
    return 0
