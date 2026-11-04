from __future__ import annotations

import importlib.util
import json
import re
import shutil
import subprocess
import sys
import tempfile
import threading
import unittest
import zipfile
from pathlib import Path
from unittest.mock import patch
from urllib.error import HTTPError
from urllib.request import Request, urlopen

KIT = Path(__file__).resolve().parents[1]
SCRIPTS = KIT / 'skills/manage-project-roadmap/scripts'
sys.path.insert(0, str(SCRIPTS))
from plan_model import FIELDS, SCHEMA, STATES, parse_plan
from roadmap import metadata, render, snapshot
from roadmap_server import Monitor, create_server


def load(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def create_symlink(test, link, target, *, is_directory=False):
    try:
        link.symlink_to(target, target_is_directory=is_directory)
    except OSError as error:
        if getattr(error, 'winerror', None) == 1314:
            test.skipTest('Windows no permite crear enlaces simbólicos en esta sesión (WinError 1314).')
        raise


initializer = load('kit_initializer', KIT / 'scripts/init_project.py')
packager = load('kit_packager', KIT / 'scripts/package_kit.py')


def task(key='QA-001', status='PENDIENTE', scope='SOLICITADO', dependencies='ninguna', title='Resultado verificable', **changes):
    active = status in {'EN_CURSO', 'PARCIAL', 'HECHO'}
    values = {
        'Capa': scope, 'Fuente': 'Solicitud de prueba; DEC-2026-11-03-01.',
        'Contexto propietario': 'Contexto de prueba', 'Dependencias': dependencies,
        'Alcance': 'Comportamiento incluido', 'Fuera de alcance': 'Ningún dato real',
        'Invariantes': 'Regla comprobable', 'Contratos y archivos previstos': 'Interfaz de prueba',
        'Criterios de aceptación': 'El resultado puede observarse y verificarse',
        'Responsable': 'Agente de prueba' if active else 'sin asignar',
        'Fecha de inicio': '2026-11-03' if active else 'pendiente',
        'Última actualización': '2026-11-03', 'Próximo paso': 'Comprobar resultado',
        'Evidencia actual': 'tests: comprobación aislada correcta' if active else 'No iniciado; datos de prueba',
    }
    values.update(changes)
    return f'### [{status}] {key} — {title}\n\n' + '\n'.join(f'- **{name}:** {value}' for name, value in values.items()) + '\n'


class PlanFixture(unittest.TestCase):
    def setUp(self):
        directory = tempfile.TemporaryDirectory(prefix='kit-test-')
        self.addCleanup(directory.cleanup)
        self.root = Path(directory.name)
        self.plan = self.root / 'docs/plan-maestro'
        self.plan.mkdir(parents=True)
        self.index = self.plan / 'README.md'
        self.index.write_text('# Plan de prueba\n\n' + SCHEMA['marker'] + '\n', encoding='utf-8')
        self.source = self.plan / '01-prueba.md'
        self.source.write_text('# Área de prueba\n\n' + task(), encoding='utf-8')
        self.output = self.plan / 'visualizacion.html'

    def validate(self, text):
        self.source.write_text(text, encoding='utf-8')
        return parse_plan(self.plan)

    def cli(self, *args):
        return subprocess.run([sys.executable, '-X', 'utf8', '-B', str(SCRIPTS / 'roadmap.py'), *args, '--root', str(self.root)],
                              capture_output=True, text=True, encoding='utf-8', timeout=15)


class ParserTest(PlanFixture):
    def test_valid_plan_preserves_all_fields_and_multiline_evidence(self):
        content = task(**{'Evidencia actual': 'Primera línea\n  - comando → resultado\n  - límite de prueba'})
        report = self.validate(content)
        self.assertTrue(report['valid'], report['errors'])
        self.assertEqual(14, len(FIELDS))
        self.assertEqual(set(FIELDS), set(report['tasks'][0]['fields']))
        self.assertIn('comando → resultado', report['tasks'][0]['fields']['Evidencia actual'])

    def test_requires_index_schema_and_at_least_one_task(self):
        self.index.write_text('# Otro formato', encoding='utf-8')
        self.assertFalse(parse_plan(self.plan)['valid'])
        self.index.unlink()
        self.assertFalse(parse_plan(self.plan)['valid'])
        self.index.write_text(SCHEMA['marker'], encoding='utf-8')
        self.assertFalse(self.validate('# Sin tareas')['valid'])

    def test_ignores_fenced_examples_and_ends_task_at_a_section(self):
        content = '```markdown\n' + task('QA-999') + '```\n\n' + task() + '\n## Notas\nTexto fuera de la tarea\n'
        report = self.validate(content)
        self.assertEqual(['QA-001'], [item['id'] for item in report['tasks']])
        self.assertNotIn('Texto fuera', report['tasks'][0]['fields']['Evidencia actual'])

    def test_nested_module_and_source_line_are_preserved(self):
        nested = self.plan / 'area con espacio' / 'módulo.md'
        nested.parent.mkdir()
        nested.write_text('# Otro módulo\n\n' + task('QA-002', dependencies='QA-001'), encoding='utf-8')
        report = parse_plan(self.plan)
        self.assertTrue(report['valid'], report['errors'])
        item = next(t for t in report['tasks'] if t['id'] == 'QA-002')
        self.assertEqual('area con espacio/módulo.md', item['source'])
        self.assertEqual(3, item['line'])

    def test_rejects_malformed_headings_unknown_states_and_duplicate_ids(self):
        for content in (task(key='qa-001'), task(key='QA-1'), task(status='LISTO'), task() + task()):
            with self.subTest(content=content):
                self.assertFalse(self.validate(content)['valid'])

    def test_rejects_empty_missing_duplicate_and_unknown_fields(self):
        for content in (
            task(**{'Criterios de aceptación': ''}),
            task().replace('- **Responsable:** sin asignar\n', ''),
            task() + '- **Dependencias:** QA-999\n',
            task() + '- **Fecha inventada:** mañana\n',
        ):
            with self.subTest(content=content):
                self.assertFalse(self.validate(content)['valid'])

    def test_rejects_bad_dependency_formats_unknown_self_and_cycles(self):
        cases = [task(dependencies=value) for value in ('qa-999', 'QA-999', 'QA-001', 'QA-002; QA-003', 'todas')]
        cases.append(task(dependencies='QA-002') + task('QA-002', dependencies='QA-001'))
        for content in cases:
            with self.subTest(content=content):
                self.assertFalse(self.validate(content)['valid'])

    def test_rejects_false_completion_without_owner_date_evidence_or_prerequisites(self):
        cases = [task(status='HECHO', **{field: value}) for field, value in (
            ('Responsable', 'sin asignar'), ('Fecha de inicio', 'pendiente'), ('Evidencia actual', 'sin evidencia'))]
        cases.append(task() + task('QA-002', status='HECHO', dependencies='QA-001'))
        for content in cases:
            with self.subTest(content=content):
                self.assertFalse(self.validate(content)['valid'])

    def test_rejects_bad_or_reversed_dates(self):
        for changes in ({'Última actualización': '2026-02-30'},
                        {'Última actualización': '20261103'},
                        {'Fecha de inicio': '2026-11-04', 'Última actualización': '2026-11-03'}):
            self.assertFalse(self.validate(task(status='EN_CURSO', **changes))['valid'])

    def test_unclosed_code_fence_does_not_hide_tasks_silently(self):
        report = self.validate(task() + '\n```markdown\n' + task('QA-002'))
        self.assertFalse(report['valid'])
        self.assertTrue(any('sin cierre' in message for message in report['errors']))

    def test_requires_authorization_and_consistent_exclusion_with_decision(self):
        self.assertFalse(self.validate(task(status='EN_CURSO', scope='PROPUESTO'))['valid'])
        self.assertFalse(self.validate(task(status='EXCLUIDO'))['valid'])
        self.assertFalse(self.validate(task(status='EXCLUIDO', scope='EXCLUIDO', Fuente='Sin decisión'))['valid'])
        self.assertTrue(self.validate(task(status='EXCLUIDO', scope='EXCLUIDO'))['valid'])

    def test_all_states_and_scopes_survive_the_parser(self):
        content = ''.join(task(f'QA-{index:03}', status=state, scope='EXCLUIDO' if state == 'EXCLUIDO' else 'SOLICITADO')
                          for index, state in enumerate(STATES, 1)) + task('QA-007', scope='PROPUESTO')
        report = self.validate(content)
        self.assertTrue(report['valid'], report['errors'])
        self.assertEqual(set(STATES), {item['status'] for item in report['tasks']})
        self.assertEqual(set(SCHEMA['scopes']), {item['scope'] for item in report['tasks']})

    def test_rejects_symlink_sources(self):
        foreign = self.root / 'foreign.md'
        foreign.write_text(task('QA-002'), encoding='utf-8')
        linked = self.plan / 'linked.md'
        create_symlink(self, linked, foreign)
        self.assertFalse(parse_plan(self.plan)['valid'])

    def test_rejects_non_utf8_files(self):
        self.source.write_bytes(b'\xff\xfe')
        self.assertFalse(parse_plan(self.plan)['valid'])


class ReportAndExportTest(PlanFixture):
    def test_json_filter_still_validates_the_whole_plan(self):
        self.validate(task(status='HECHO') + task('QA-002'))
        result = self.cli('report', '--json', '--status', 'PENDIENTE')
        report = json.loads(result.stdout)
        self.assertEqual(0, result.returncode)
        self.assertEqual(['QA-002'], [row['id'] for row in report['tasks']])
        self.assertEqual({'HECHO': 1, 'PENDIENTE': 1}, report['counts'])
        self.validate(task(status='HECHO', dependencies='QA-999') + task('QA-002'))
        result = self.cli('report', '--json', '--status', 'PENDIENTE')
        self.assertEqual(1, result.returncode)
        self.assertFalse(json.loads(result.stdout)['valid'])

    def test_shared_prerequisites_edges_direction_and_isolated_tasks(self):
        self.validate(task() + task('QA-002', dependencies='QA-001, QA-001')
                      + task('QA-003', dependencies='QA-001') + task('QA-004', dependencies='QA-002, QA-003') + task('QA-005'))
        graph, _ = snapshot(self.root, self.plan, self.output)
        self.assertEqual(5, len(graph['tasks']))
        self.assertEqual([{'from': 'QA-001', 'to': 'QA-002'}, {'from': 'QA-001', 'to': 'QA-003'},
                          {'from': 'QA-002', 'to': 'QA-004'}, {'from': 'QA-003', 'to': 'QA-004'}], graph['edges'])

    def test_untrusted_text_cannot_close_json_script_or_replace_other_markers(self):
        title = '</script><script>bad()</script> & __PROJECT_NAME__'
        self.validate(task(title=title))
        graph, _ = snapshot(self.root, self.plan, self.output)
        graph['projectName'] = '<img src=x onerror=bad()> __GENERATED_AT__'
        document = render(graph)
        payload = re.search(r'<script type="application/json" id="roadmap-data">(.*?)</script>', document, re.S)[1]
        self.assertNotIn('<', payload)
        self.assertNotIn('&', payload)
        decoded = json.loads(payload)
        self.assertEqual(title, decoded['tasks'][0]['title'])
        self.assertEqual(set(FIELDS), set(decoded['tasks'][0]['fields']))
        self.assertEqual(list(FIELDS), decoded['fieldNames'])
        self.assertNotIn('<img src=x', document)
        self.assertIn('__GENERATED_AT__', decoded['projectName'])

    def test_static_export_is_self_contained_and_sources_are_relative(self):
        result = self.cli('tree')
        self.assertEqual(0, result.returncode, result.stderr)
        document = self.output.read_text(encoding='utf-8')
        self.assertNotRegex(document, r'<script[^>]+src=|<link[^>]+href=')
        graph, _ = snapshot(self.root, self.plan, self.output)
        self.assertEqual('01-prueba.md', graph['tasks'][0]['sourceUrl'])
        other, _ = snapshot(self.root, self.plan, self.root / 'exports/view.html')
        self.assertEqual('../docs/plan-maestro/01-prueba.md', other['tasks'][0]['sourceUrl'])

    def test_invalid_plan_or_output_keeps_the_previous_artifact(self):
        self.output.write_text('Previous valid artifact', encoding='utf-8')
        self.validate(task(dependencies='QA-999'))
        self.assertEqual(1, self.cli('tree').returncode)
        self.assertEqual('Previous valid artifact', self.output.read_text(encoding='utf-8'))
        before = self.source.read_bytes()
        self.assertEqual(1, self.cli('tree', '--output', str(self.source)).returncode)
        self.assertEqual(before, self.source.read_bytes())


class MonitorTest(PlanFixture):
    def monitor(self):
        monitor = Monitor(self.root, self.plan, self.output)
        self.assertTrue(monitor.refresh(), monitor.error)
        return monitor

    def test_unchanged_sources_do_not_rewrite_output(self):
        monitor = self.monitor()
        written = self.output.stat().st_mtime_ns
        self.assertFalse(monitor.refresh())
        self.assertEqual(written, self.output.stat().st_mtime_ns)

    def test_valid_changes_update_fields_edges_and_revision(self):
        monitor = self.monitor()
        previous = monitor.status()['revision']
        self.validate(task(status='HECHO') + task('QA-002', dependencies='QA-001'))
        self.assertTrue(monitor.refresh())
        self.assertNotEqual(previous, monitor.status()['revision'])
        self.assertEqual('HECHO', monitor.graph['tasks'][0]['status'])
        self.assertEqual([{'from': 'QA-001', 'to': 'QA-002'}], monitor.graph['edges'])
        self.assertEqual(set(FIELDS), set(monitor.graph['tasks'][0]['fields']))

    def test_invalid_edits_keep_last_valid_document_graph_and_source_then_recover(self):
        monitor = self.monitor()
        document, graph, source = monitor.document, monitor.graph, dict(monitor.sources)
        self.validate(task(dependencies='QA-999'))
        self.assertFalse(monitor.refresh())
        self.assertEqual(document, monitor.document)
        self.assertIs(graph, monitor.graph)
        self.assertEqual(source, monitor.sources)
        self.assertEqual('invalid', monitor.status()['status'])
        self.validate(task(status='HECHO'))
        self.assertTrue(monitor.refresh())
        self.assertEqual('ok', monitor.status()['status'])

    def test_missing_index_is_detected_and_restoring_it_recovers(self):
        monitor = self.monitor()
        self.index.unlink()
        self.assertFalse(monitor.refresh())
        self.assertEqual('invalid', monitor.status()['status'])
        self.index.write_text(SCHEMA['marker'], encoding='utf-8')
        self.assertTrue(monitor.refresh())

    def test_added_renamed_and_removed_modules_are_detected(self):
        monitor = self.monitor()
        other = self.plan / '02-other.md'
        other.write_text(task('QA-002'), encoding='utf-8')
        self.assertTrue(monitor.refresh())
        self.assertEqual(2, len(monitor.graph['tasks']))
        moved = other.rename(self.plan / '03-renamed.md')
        self.assertTrue(monitor.refresh())
        self.assertEqual('03-renamed.md', monitor.graph['tasks'][1]['source'])
        moved.unlink()
        self.assertTrue(monitor.refresh())
        self.assertEqual(1, len(monitor.graph['tasks']))

    def test_source_race_and_write_error_do_not_publish_partial_updates(self):
        monitor = self.monitor()
        previous = monitor.graph
        self.validate(task(status='HECHO'))
        with patch.object(monitor, 'fingerprint', side_effect=['before', 'after']):
            self.assertFalse(monitor.refresh())
        self.assertIs(previous, monitor.graph)
        with patch('roadmap_server.write_document', side_effect=OSError('No write')):
            self.assertFalse(monitor.refresh())
        self.assertIs(previous, monitor.graph)
        self.assertTrue(monitor.refresh())

    def test_project_configuration_changes_name_but_not_identity(self):
        monitor = self.monitor()
        project_id = monitor.status()['projectId']
        config = self.root / '.agents/project.json'
        config.parent.mkdir()
        config.write_text(json.dumps({'name': 'New name'}), encoding='utf-8')
        self.assertTrue(monitor.refresh())
        self.assertEqual('New name', monitor.status()['projectName'])
        self.assertEqual(project_id, monitor.status()['projectId'])

    def test_symlink_error_keeps_previous_snapshot(self):
        monitor = self.monitor()
        previous = monitor.document
        foreign = self.root / 'foreign.md'
        foreign.write_text('Outside', encoding='utf-8')
        create_symlink(self, self.plan / 'linked.md', foreign)
        self.assertFalse(monitor.refresh())
        self.assertEqual(previous, monitor.document)
        self.assertEqual('invalid', monitor.status()['status'])


class HttpTest(PlanFixture):
    def setUp(self):
        super().setUp()
        self.monitor = Monitor(self.root, self.plan, self.root / 'exports/view.html')
        self.assertTrue(self.monitor.refresh())
        self.server = create_server(self.monitor, 0)
        self.worker = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.worker.start()
        self.base = f'http://127.0.0.1:{self.server.server_port}'
        self.addCleanup(self.stop)

    def stop(self):
        self.server.shutdown()
        self.server.server_close()
        self.worker.join(timeout=2)

    def test_serves_only_the_viewer_data_status_and_validated_source_snapshots(self):
        for route, expected in (('/view.html', b'roadmap-data'), ('/__roadmap__/data', b'QA-001'),
                                ('/__roadmap__/status', b'"status": "ok"'),
                                ('/__roadmap__/source/01-prueba.md', b'QA-001')):
            with self.subTest(route=route), urlopen(self.base + route, timeout=3) as response:
                self.assertEqual(200, response.status)
                self.assertIn(expected, response.read())
                self.assertEqual('no-store', response.headers['Cache-Control'])

    def test_rejects_other_files_path_traversal_and_foreign_host_headers(self):
        (self.root / 'private.txt').write_text('Private', encoding='utf-8')
        for route in ('/private.txt', '/../private.txt', '/__roadmap__/source/%2e%2e/private.txt', '/.agents/project.json'):
            with self.subTest(route=route), self.assertRaises(HTTPError) as raised:
                urlopen(self.base + route, timeout=3)
            self.assertEqual(404, raised.exception.code)
            raised.exception.close()
        with self.assertRaises(HTTPError) as raised:
            urlopen(Request(self.base + '/', headers={'Host': 'foreign.example'}), timeout=3)
        self.assertEqual(403, raised.exception.code)
        raised.exception.close()

    def test_occupied_port_does_not_modify_an_export(self):
        output = self.root / 'previous.html'
        output.write_text('Keep it', encoding='utf-8')
        result = self.cli('serve', '--port', str(self.server.server_port), '--output', str(output))
        self.assertNotEqual(0, result.returncode)
        self.assertEqual('Keep it', output.read_text(encoding='utf-8'))


class InstallerTest(unittest.TestCase):
    def setUp(self):
        directory = tempfile.TemporaryDirectory(prefix='kit-install-')
        self.addCleanup(directory.cleanup)
        self.root = Path(directory.name)
        self.target = self.root / 'Proyecto con espacios'

    def install(self, **changes):
        args = dict(target=self.target, name='Proyecto ágil', profile='base', started='2026-11-03')
        args.update(changes)
        return initializer.install(**args)

    def test_installation_prepares_skills_and_three_pending_tasks_without_product_code(self):
        self.install()
        config = json.loads((self.target / '.agents/project.json').read_text(encoding='utf-8'))
        self.assertEqual('base', config['profile'])
        self.assertEqual('preparacion', config['stage'])
        self.assertEqual('src', config['source_root'])
        self.assertFalse(config['source_root_override'])
        self.assertEqual(b'', (self.target / 'src/.gitkeep').read_bytes())
        self.assertEqual([self.target / 'src/.gitkeep'], list((self.target / 'src').iterdir()))
        self.assertTrue((self.target / config['sources_index']).is_file())
        self.assertEqual(3, len(list((self.target / '.agents/skills').glob('*/SKILL.md'))))
        report = parse_plan(self.target / 'docs/plan-maestro')
        self.assertTrue(report['valid'], report['errors'])
        self.assertEqual({'PENDIENTE': 3}, report['counts'])
        for path in ('app', 'lib', 'internal', 'go.mod', 'composer.json', 'package.json'):
            self.assertFalse((self.target / path).exists())

    def test_dry_run_makes_no_target_changes(self):
        result = self.install(dry_run=True)
        self.assertTrue(result['create'])
        self.assertIn('src/.gitkeep', result['create'])
        self.assertIn('docs/fuentes/README.md', result['create'])
        self.assertFalse(self.target.exists())

    def test_repeated_installation_is_idempotent_and_preserves_initial_date(self):
        result = self.install()
        before = {name: (self.target / name).stat().st_mtime_ns for name in result['create']}
        repeated = self.install(started=None)
        self.assertEqual([], repeated['create'])
        self.assertEqual(set(before), set(repeated['unchanged']))
        self.assertEqual(before, {name: (self.target / name).stat().st_mtime_ns for name in before})

    def test_all_profiles_install_without_assuming_a_framework_for_base(self):
        for profile in initializer.PROFILES:
            with self.subTest(profile=profile):
                target = self.root / profile
                self.install(target=target, profile=profile)
                config = json.loads((target / '.agents/project.json').read_text(encoding='utf-8'))
                self.assertEqual(profile, config['profile'])
                self.assertEqual('src', config['source_root'])
                self.assertTrue((target / 'src/.gitkeep').is_file())
                for alternative in ('app/Contexts', 'lib', 'internal'):
                    self.assertFalse((target / alternative).exists())
                self.assertTrue(parse_plan(target / 'docs/plan-maestro')['valid'])

    def test_explicit_source_root_is_used_and_preserved_on_repeat(self):
        self.install(source_root='codigo propio/contextos')
        config = json.loads((self.target / '.agents/project.json').read_text(encoding='utf-8'))
        self.assertEqual('codigo propio/contextos', config['source_root'])
        self.assertTrue(config['source_root_override'])
        self.assertTrue((self.target / config['source_root'] / '.gitkeep').is_file())
        self.assertFalse((self.target / 'src').exists())
        self.assertEqual([], self.install(source_root=None, started=None)['create'])
        before = {path.relative_to(self.target): path.read_bytes() for path in self.target.rglob('*') if path.is_file()}
        with self.assertRaises(ValueError):
            self.install(source_root='src')
        self.assertEqual(before, {path.relative_to(self.target): path.read_bytes() for path in self.target.rglob('*') if path.is_file()})

    def test_cli_uses_src_unless_a_different_source_root_is_explicit(self):
        command = [sys.executable, '-X', 'utf8', '-B', str(KIT / 'scripts/init_project.py'), '--name', 'Proyecto CLI', '--profile', 'laravel']
        for folder, options, expected in (('default', [], 'src'), ('explicit', ['--source-root', 'app/Contexts'], 'app/Contexts')):
            with self.subTest(folder=folder):
                target = self.root / folder
                result = subprocess.run(command + ['--target', str(target)] + options, capture_output=True, text=True, encoding='utf-8', timeout=15)
                self.assertEqual(0, result.returncode, result.stderr)
                config = json.loads((target / '.agents/project.json').read_text(encoding='utf-8'))
                self.assertEqual(expected, config['source_root'])
                self.assertTrue((target / expected / '.gitkeep').is_file())
                self.assertFalse((target / ('app' if expected == 'src' else 'src')).exists())

    def test_invalid_source_roots_do_not_create_target_files(self):
        for source_root in ('', '.', '..', '../outside', '/src', 'src/../outside', 'src//core', 'src/./core',
                            'src/', ' src', 'src\\core', 'C:/src', '.git/core', '.agents/core', 'docs/core', 'src\ncore'):
            with self.subTest(source_root=source_root), self.assertRaises(ValueError):
                self.install(source_root=source_root)
            self.assertFalse(self.target.exists())

    def test_incompatible_source_root_blocks_all_writes(self):
        self.target.mkdir()
        source = self.target / 'src'
        source.write_text('Existing file', encoding='utf-8')
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual([source], list(self.target.iterdir()))
        self.assertEqual('Existing file', source.read_text(encoding='utf-8'))

    def test_linked_source_root_blocks_all_writes(self):
        self.target.mkdir()
        source = self.target / 'src'
        outside = self.root / 'external-code'
        outside.mkdir()
        create_symlink(self, source, outside, is_directory=True)
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual([], list(outside.iterdir()))
        self.assertEqual([source], list(self.target.iterdir()))

    def test_existing_business_files_are_preserved_without_creating_framework_contexts(self):
        source = self.target / 'src/Orders/Domain/order.php'
        source.parent.mkdir(parents=True)
        source.write_text('Existing business code', encoding='utf-8')
        self.install(profile='laravel')
        self.assertEqual('Existing business code', source.read_text(encoding='utf-8'))
        self.assertFalse((self.target / 'app').exists())

    def test_conflicting_agents_or_managed_file_blocks_all_writes(self):
        self.target.mkdir()
        agents = self.target / 'AGENTS.md'
        agents.write_text('Existing instructions', encoding='utf-8')
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual('Existing instructions', agents.read_text(encoding='utf-8'))
        self.assertEqual([agents], list(self.target.iterdir()))
        agents.unlink()
        self.install()
        changed = self.target / 'docs/proyecto.md'
        changed.write_text('User changes', encoding='utf-8')
        before = {path.relative_to(self.target): path.read_bytes() for path in self.target.rglob('*') if path.is_file()}
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual(before, {path.relative_to(self.target): path.read_bytes() for path in self.target.rglob('*') if path.is_file()})

    def test_symlink_destination_is_not_followed(self):
        self.target.mkdir()
        outside = self.root / 'outside'
        outside.mkdir()
        create_symlink(self, self.target / '.agents', outside, is_directory=True)
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual([], list(outside.iterdir()))
        self.assertFalse((self.target / 'AGENTS.md').exists())

    def test_invalid_names_dates_profiles_and_internal_targets_do_not_write(self):
        for changes in ({'name': 'two\nlines'}, {'started': '2026-02-30'}, {'profile': 'invented'}, {'target': KIT / 'nested'}):
            with self.subTest(changes=changes), self.assertRaises(ValueError):
                self.install(**changes)
        self.assertFalse(self.target.exists())

    def test_invalid_template_blocks_installation_before_target_creation(self):
        copied = self.root / 'broken-kit'
        shutil.copytree(KIT, copied, ignore=shutil.ignore_patterns('__pycache__'))
        (copied / 'templates/preparacion.md.in').write_text('# Empty\n', encoding='utf-8')
        with patch.object(initializer, 'KIT', copied), self.assertRaises(ValueError):
            self.install()
        self.assertFalse(self.target.exists())


class PackageTest(unittest.TestCase):
    def test_reproducible_archive_can_be_moved_extracted_and_used_without_source_paths(self):
        with tempfile.TemporaryDirectory(prefix='kit-package-') as directory:
            root = Path(directory)
            first, second = root / 'first.zip', root / 'second.zip'
            packager.package(first)
            packager.package(second)
            self.assertEqual(first.read_bytes(), second.read_bytes())
            moved = root / 'moved kit'
            with zipfile.ZipFile(first) as archive:
                names = archive.namelist()
                self.assertIn('kit-inicio-proyecto/PROMPT_MAESTRO.md', names)
                self.assertIn('kit-inicio-proyecto/MANIFEST.sha256', names)
                self.assertFalse(any('__pycache__' in name or name.endswith('.pyc') or '/.env' in name for name in names))
                archive.extractall(moved)
            extracted = moved / 'kit-inicio-proyecto'
            project = root / 'New project'
            result = subprocess.run([sys.executable, '-X', 'utf8', '-B', str(extracted / 'scripts/init_project.py'), '--target', str(project),
                                     '--name', 'Proyecto portable', '--profile', 'go', '--date', '2026-11-03'], capture_output=True, text=True, encoding='utf-8', timeout=15)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertTrue((project / 'src/.gitkeep').is_file())
            self.assertTrue((project / 'docs/fuentes/README.md').is_file())
            command = [sys.executable, '-X', 'utf8', '-B', str(project / '.agents/skills/manage-project-roadmap/scripts/roadmap.py')]
            report = subprocess.run(command + ['report', '--root', str(project), '--json'], capture_output=True, text=True, encoding='utf-8', timeout=15)
            self.assertEqual(0, report.returncode, report.stderr)
            self.assertEqual(3, len(json.loads(report.stdout)['tasks']))
            rendered = subprocess.run(command + ['tree', '--root', str(project)], capture_output=True, text=True, encoding='utf-8', timeout=15)
            self.assertEqual(0, rendered.returncode, rendered.stderr)
            self.assertTrue((project / 'docs/plan-maestro/visualizacion.html').is_file())


if __name__ == '__main__':
    unittest.main()
