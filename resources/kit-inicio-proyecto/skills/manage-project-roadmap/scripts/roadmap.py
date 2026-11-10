#!/usr/bin/env python3
"""Portable roadmap commands; Python standard library only."""
from __future__ import annotations

import argparse
import hashlib
import html
import json
import os
import re
import tempfile
from datetime import datetime
from pathlib import Path
from urllib.parse import quote

from plan_model import STATES, parse_plan

ASSETS = Path(__file__).resolve().parents[1] / 'assets'


def metadata(root: Path) -> dict:
    path = root / '.agents/project.json'
    if path.is_symlink():
        raise ValueError('La configuración de proyecto no puede ser un enlace simbólico.')
    config = json.loads(path.read_text(encoding='utf-8')) if path.exists() else {}
    if not isinstance(config, dict) or not isinstance(config.get('name', root.name), str):
        raise ValueError('Configuración de proyecto inválida.')
    return {'projectName': config.get('name', root.name),
            'projectId': hashlib.sha256(str(root.resolve()).encode('utf-8')).hexdigest()[:16],
            'projectPath': str(root.resolve()),
            'generatedAt': datetime.now().astimezone().isoformat(timespec='seconds')}


def public_report(report: dict, meta: dict) -> dict:
    return {**{key: value for key, value in report.items() if key != 'sources'}, **meta}


def snapshot(root: Path, plan_dir: Path, output: Path) -> tuple[dict, dict]:
    report = parse_plan(plan_dir)
    if not report['valid']:
        raise ValueError('Plan inválido:\n- ' + '\n- '.join(report['errors']))
    graph = public_report(report, metadata(root))
    for task in graph['tasks']:
        relative_source = Path(os.path.relpath(plan_dir / task['source'], output.parent)).as_posix()
        task['sourceUrl'] = quote(relative_source, safe='/')
    return graph, report['sources']


def render(graph: dict) -> str:
    payload = json.dumps(graph, ensure_ascii=False).replace('&', '\\u0026').replace('<', '\\u003c').replace('>', '\\u003e')
    replacements = {
        '/*__ROADMAP_CSS__*/': (ASSETS / 'roadmap-tree.css').read_text(encoding='utf-8'),
        '/*__ROADMAP_JS__*/': (ASSETS / 'roadmap-context.js').read_text(encoding='utf-8') + '\n' + (ASSETS / 'roadmap-tree.js').read_text(encoding='utf-8'),
        '__ROADMAP_DATA__': payload,
        '__PROJECT_NAME__': html.escape(graph['projectName']),
        '__GENERATED_AT__': html.escape(graph['generatedAt']),
    }
    template = (ASSETS / 'roadmap-tree.html').read_text(encoding='utf-8')
    return re.sub('|'.join(re.escape(key) for key in replacements), lambda match: replacements[match[0]], template)


def write_document(output: Path, document: str) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    temporary = None
    try:
        with tempfile.NamedTemporaryFile(mode='w', encoding='utf-8', dir=output.parent,
                                         prefix='.roadmap-', suffix='.tmp', delete=False) as handle:
            temporary = Path(handle.name)
            handle.write(document)
        temporary.replace(output)
    finally:
        if temporary is not None:
            temporary.unlink(missing_ok=True)


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(description='Validar, consultar y visualizar el plan maestro del proyecto.')
    parser.add_argument('command', choices=('check', 'report', 'tree', 'serve'))
    parser.add_argument('--root', type=Path, default=Path.cwd(), help='Raíz del proyecto; por defecto, directorio actual.')
    parser.add_argument('--plan-dir', type=Path)
    parser.add_argument('--output', type=Path)
    parser.add_argument('--status', choices=STATES)
    parser.add_argument('--json', action='store_true')
    parser.add_argument('--port', type=int, default=8788)
    parser.add_argument('--interval', type=float, default=1.0)
    parser.add_argument('--allow-edit', action='store_true', help='Autorizar edición local con revisión explícita en una rama Git distinta de main/master.')
    args = parser.parse_args(argv)
    if args.allow_edit and args.command != 'serve':
        parser.error('--allow-edit solo se admite con serve.')
    root = args.root.resolve()
    plan_dir = (args.plan_dir or root / 'docs/plan-maestro').resolve()
    output = (args.output or plan_dir / 'visualizacion.html').absolute()
    try:
        if args.command in {'check', 'report'}:
            report = public_report(parse_plan(plan_dir), metadata(root))
            selected = [task for task in report['tasks'] if args.status is None or task['status'] == args.status]
            if args.json:
                selected_ids = {task['id'] for task in selected}
                print(json.dumps({**report, 'tasks': selected,
                                  'edges': [edge for edge in report['edges'] if edge['from'] in selected_ids and edge['to'] in selected_ids]},
                                 ensure_ascii=False, indent=2))
            elif not report['valid']:
                print('Plan inválido:\n- ' + '\n- '.join(report['errors']))
            else:
                print(f"Plan válido: {len(report['tasks'])} tareas; esquema {report['schemaVersion']}.")
                print('Estados: ' + ', '.join(f'{key}={value}' for key, value in report['counts'].items()))
                if args.command == 'report':
                    for task in selected:
                        print(f"[{task['status']}] {task['id']} — {task['title']}")
            return 0 if report['valid'] else 1
        if output.suffix.lower() != '.html':
            raise ValueError('La salida debe ser HTML y no puede sobrescribir las especificaciones.')
        if output.is_symlink():
            raise ValueError('La salida no puede ser un enlace simbólico.')
        if args.interval < 0.1 or not 0 <= args.port <= 65535:
            raise ValueError('Usar intervalo >= 0.1 segundos y puerto entre 0 y 65535.')
        if args.command == 'serve':
            from roadmap_server import serve
            return serve(root, plan_dir, output, args.port, args.interval, allow_edit=args.allow_edit)
        graph, _ = snapshot(root, plan_dir, output)
        write_document(output, render(graph))
        print(f"Visor generado: {len(graph['tasks'])} tareas, {len(graph['edges'])} dependencias.\n{output}")
    except (OSError, ValueError) as error:
        parser.exit(1, f'{error}\n')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
