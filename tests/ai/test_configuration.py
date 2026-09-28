"""Guard the shared skill adapters and reviewer permission configuration."""

import hashlib
import json
import os
import re
import subprocess
import tempfile
try:
    import tomllib
except ModuleNotFoundError:
    try:
        import tomli as tomllib
    except ModuleNotFoundError:
        tomllib = None
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


class AiConfigurationTest(unittest.TestCase):
    def test_commit_and_hook_configuration_protects_the_index(self):
        package = (ROOT / "package.json").read_text()
        self.assertIn('"lefthook"', package)
        self.assertIn('"@commitlint/cli"', package)

        commitlint = (ROOT / "commitlint.config.mjs").read_text()
        self.assertIn("'header-max-length': [2, 'always', 72]", commitlint)
        self.assertIn("'type-enum'", commitlint)

        lefthook = (ROOT / "lefthook.yml").read_text()
        self.assertIn('git diff --cached --check', lefthook)
        self.assertIn('npx commitlint --edit {1}', lefthook)
        self.assertNotIn('stage_fixed: true', lefthook)

        pre_commit = (ROOT / ".githooks/pre-commit").read_text()
        self.assertIn('gitleaks git --staged --redact', pre_commit)
        self.assertIn('npx lefthook run pre-commit', pre_commit)
        self.assertNotIn('--no-verify', pre_commit)

    def test_ci_covers_develop_main_and_manual_deploy(self):
        package = json.loads((ROOT / "package.json").read_text())
        self.assertEqual(
            package["scripts"]["check"],
            "vp check resources/js resources/css scripts tests/e2e vite.config.ts playwright.config.ts && npm run test:ui",
        )
        composer = json.loads((ROOT / "composer.json").read_text())
        self.assertEqual(
            composer["scripts"]["check:i18n"],
            "@php artisan test --compact tests/Feature/LocalizationCatalogGateTest.php",
        )

        ci = (ROOT / ".github/workflows/ci.yml").read_text()
        self.assertIn('branches: [develop, main]', ci)
        self.assertIn('name: commit-convention', ci)
        self.assertIn('name: secrets', ci)
        self.assertIn('name: quality', ci)
        self.assertIn('gitleaks/gitleaks-action@', ci)
        self.assertIn(
            'php artisan wayfinder:generate --with-form --no-interaction',
            ci,
        )
        self.assertIn('Enforce localization catalog contract', ci)
        self.assertIn('composer check:i18n', ci)
        self.assertIn('Enforce UI contract on changed source', ci)
        self.assertIn('node scripts/check-ui-contract.mjs --base', ci)

        lefthook = (ROOT / "lefthook.yml").read_text()
        self.assertIn('i18n-contract:', lefthook)
        self.assertIn('composer check:i18n', lefthook)

        deploy = (ROOT / ".github/workflows/deploy.yml").read_text()
        self.assertIn('workflow_dispatch:', deploy)
        self.assertIn('options: [staging, production]', deploy)
        self.assertIn('environment:', deploy)
        self.assertIn('DEPLOY_SSH_KEY: ${{ secrets.DEPLOY_SSH_KEY }}', deploy)
        self.assertIn('DEPLOY_KNOWN_HOSTS: ${{ secrets.DEPLOY_KNOWN_HOSTS }}', deploy)
        self.assertIn('sha256sum -c .release/starter.tar.gz.sha256', deploy)

    def test_deployer_uses_a_verified_artifact_and_safe_defaults(self):
        deployer = (ROOT / "deploy.php").read_text()
        self.assertIn("set('keep_releases', 5)", deployer)
        self.assertIn("set('shared_files', ['.env'])", deployer)
        self.assertIn("set('shared_dirs', ['storage'])", deployer)
        self.assertIn('DEPLOY_ARTIFACT_SHA256', deployer)
        self.assertIn('DEPLOY_ALLOW_MIGRATIONS', deployer)
        self.assertNotIn('migrate:rollback', deployer)
        self.assertIn("after('deploy:failed', 'deploy:unlock')", deployer)

    def test_deployer_flow_gates_migrations_and_rolls_back_once_on_failed_readiness(self):
        deployer = (ROOT / "deploy.php").read_text()
        flow = re.search(r"task\('deploy', \[(.*?)\]\);", deployer, re.S)
        self.assertIsNotNone(flow, "deploy.php must define the deploy flow explicitly")
        steps = re.findall(r"'([a-z:_]+)'", flow.group(1))
        # The recipe default runs artisan:migrate unconditionally.
        self.assertNotIn("artisan:migrate", steps)
        self.assertNotIn("artisan:optimize", steps)
        for step in ("deploy:migrate", "artisan:storage:link", "artisan:config:cache",
                     "artisan:route:cache", "artisan:view:cache", "artisan:event:cache"):
            self.assertIn(step, steps)
        order = [
            "deploy:migrate", "deploy:smoke", "deploy:remember_healthy", "deploy:symlink",
            "deploy:restart_workers", "deploy:smoke:live", "deploy:cleanup",
        ]
        self.assertEqual([step for step in steps if step in order], order)
        self.assertIn("getenv('DEPLOY_ALLOW_MIGRATIONS') !== 'true'", deployer)
        self.assertIn("php artisan ops:readiness", deployer)
        self.assertIn("/health/ready", deployer)
        self.assertIn("horizon:terminate", deployer)
        self.assertIn("set('smoke_attempts', 3)", deployer)
        self.assertIn("set('smoke_timeout_seconds', 5)", deployer)
        # Exactly one automatic switch back, never a schema rollback.
        self.assertEqual(deployer.count("mv -T current.rollback current"), 1)
        self.assertIn("deploy_blocked", deployer)
        self.assertEqual(steps[0], "deploy:check_blocked")
        self.assertNotIn("migrate:rollback", deployer)
        self.assertNotIn("invoke('rollback')", deployer)

    def test_production_templates_keep_limits_headers_and_timeout_chain(self):
        nginx = (ROOT / "deploy/nginx/starter.conf").read_text()
        headers = (ROOT / "deploy/nginx/security-headers.conf").read_text()
        self.assertIn("client_max_body_size 52m;", nginx)
        self.assertIn("$realpath_root/index.php", nginx)
        self.assertIn("fastcgi_param REQUEST_ID $request_id;", nginx)
        self.assertIn("fastcgi_buffer_size 32k;", nginx)
        self.assertIn("location ~ /\\.(?!well-known)", nginx)
        for location in ("location ^~ /build/assets/", "location ^~ /storage/media/"):
            block = nginx.split(location, 1)[1].split("}", 1)[0]
            self.assertIn('"public, max-age=31536000, immutable"', block)
            self.assertIn("starter-security-headers.conf", block)
        for header in ("X-Content-Type-Options", "Referrer-Policy", "Strict-Transport-Security"):
            self.assertIn(header, headers)
        self.assertNotIn("$request_uri\",", nginx.split("log_format", 1)[1].split(";", 1)[0])

        horizon_config = (ROOT / "config/horizon.php").read_text()
        queue_config = (ROOT / "config/queue.php").read_text()
        supervisor_timeout = int(re.search(r"'timeout' => (\d+)", horizon_config).group(1))
        retry_after = int(re.search(r"REDIS_QUEUE_RETRY_AFTER', (\d+)", queue_config).group(1))
        job_timeouts = [
            int(value)
            for job in (ROOT / "app/Jobs").glob("*.php")
            for value in re.findall(r"public int \$timeout = (\d+);", job.read_text())
        ]
        unit = (ROOT / "deploy/systemd/starter-horizon.service").read_text()
        stop_timeout = int(re.search(r"TimeoutStopSec=(\d+)", unit).group(1))
        self.assertTrue(job_timeouts)
        self.assertLess(max(job_timeouts), supervisor_timeout)
        self.assertLess(supervisor_timeout, retry_after)
        self.assertLess(retry_after, stop_timeout)
        self.assertIn("artisan horizon", unit)

        compose = (ROOT / "compose.yaml").read_text()
        queue_service = compose.split("\n    queue:\n", 1)[1].split("\n    scheduler:\n", 1)[0]
        self.assertIn("command: [php, artisan, horizon]", queue_service)
        self.assertIn("horizon:status", queue_service)
        grace = int(re.search(r"stop_grace_period: (\d+)s", queue_service).group(1))
        self.assertLess(retry_after, grace)

        for name in ("starter-horizon.service", "starter-ssr.service", "starter-scheduler.service", "starter-backup.service"):
            self.assertIn("OnFailure=starter-alert@%n.service", (ROOT / "deploy/systemd" / name).read_text())

    def test_backup_scripts_fail_loudly_and_never_overwrite_live_data(self):
        backup = (ROOT / "scripts/backup/backup.sh").read_text()
        restore = (ROOT / "scripts/backup/restore.sh").read_text()
        for script in (backup, restore):
            self.assertTrue(script.startswith("#!/usr/bin/env bash"))
            self.assertIn("set -Eeuo pipefail", script)
            self.assertIn("umask 077", script)
            self.assertNotRegex(script, r"PGPASSWORD=\S")
        self.assertIn("--format=custom", backup)
        self.assertIn("sha256sum", backup)
        self.assertIn(".partial", backup)
        self.assertIn("ops.backup.failed", backup)
        self.assertIn("logger -p user.crit", backup)
        self.assertIn("already exists; refusing to overwrite", restore)
        self.assertIn("is not empty; refusing to overwrite", restore)
        self.assertIn("dropdb --if-exists", restore)
        self.assertNotIn("--clean", restore)
        for path in ("scripts/backup/backup.sh", "scripts/backup/restore.sh"):
            self.assertTrue(os.access(ROOT / path, os.X_OK), path)
            subprocess.run(["bash", "-n", str(ROOT / path)], check=True)

    def test_manual_skills_resolve_shared_workflows(self):
        for name in ("foundation-fast", "foundation-ui"):
            with self.subTest(skill=name):
                codex = ROOT / ".agents/skills" / name
                claude = ROOT / ".claude/skills" / name
                bodies = []
                for directory in (codex, claude):
                    text = (directory / "SKILL.md").read_text()
                    front, body = text.split("---", 2)[1:]
                    self.assertIn(f"name: {name}\n", front)
                    self.assertRegex(front, r"description: .+")
                    references = re.findall(r"`([^`]+/workflow\.md)`", body)
                    self.assertEqual(len(references), 1)
                    target = ROOT / references[0]
                    self.assertTrue(target.is_file())
                    self.assertGreater(len(target.read_text().strip()), 100)
                    bodies.append(body)
                self.assertEqual(*bodies)
                self.assertIn("disable-model-invocation: true", (claude / "SKILL.md").read_text())
                self.assertRegex((codex / "agents/openai.yaml").read_text().strip(),
                                 r"\Apolicy:\n +allow_implicit_invocation: false\Z")

    def test_gemini_adapter_and_fast_workflow_limit_unnecessary_analysis(self):
        gemini = (ROOT / "GEMINI.md").read_text()
        fast = (ROOT / ".agents/skills/foundation-fast/references/workflow.md").read_text()
        ui = (ROOT / ".agents/skills/foundation-ui/references/workflow.md").read_text()

        for text in (gemini, fast, ui):
            self.assertIn("2 minut", text)
            self.assertRegex(text, r"dwóch\s+nieudanych prób")

        agents_rule = (ROOT / ".ai/rules/agents.md").read_text()
        self.assertIn("maks. 2 min", agents_rule)
        self.assertIn("maks. 5 min", agents_rule)
        self.assertIn("maks. 10 min", agents_rule)
        self.assertIn("AGENTS.md", gemini)
        self.assertIn("Nie rozszerzaj zadania", gemini)
        self.assertIn("Nie uruchamiaj pełnego discovery", gemini)
        self.assertIn("zatrzymaj implementację", ui)

    def test_reviewer_retains_restricted_permissions(self):
        agent = tomllib.loads((ROOT / ".codex/agents/foundation-reviewer.toml").read_text())
        self.assertEqual(agent["sandbox_mode"], "read-only")
        self.assertEqual(agent["approval_policy"], "never")
        self.assertFalse(agent["agents"]["enabled"])
        self.assertFalse(agent["mcp_servers"]["laravel-boost"]["enabled"])
        self.assertNotIn("model", agent)
        text = (ROOT / ".claude/agents/foundation-reviewer.md").read_text()
        front, body = text.split("---", 2)[1:]
        fields = dict(line.split(": ", 1) for line in front.strip().splitlines())
        self.assertEqual(set(fields["tools"].split(", ")), {"Read", "Glob", "Grep"})
        self.assertEqual(fields["model"], "inherit")
        self.assertEqual(int(fields["maxTurns"]), 6)
        self.assertEqual(body.strip(), agent["developer_instructions"].strip().replace("AGENTS.md", "CLAUDE.md"))

    def test_parent_keeps_boost_and_delegation_ceiling(self):
        config = tomllib.loads((ROOT / ".codex/config.toml").read_text())
        self.assertEqual(config["agents"]["max_concurrent_threads_per_session"], 2)
        self.assertEqual(config["mcp_servers"]["laravel-boost"]["args"], ["artisan", "boost:mcp"])

    def test_design_system_exceptions_require_all_mandatory_fields_and_valid_hashes(self):
        required_fields = {
            "id",
            "rule",
            "file",
            "contentHash",
            "reason",
            "alternatives",
            "scope",
            "owner",
            "reviewRef",
            "expires",
            "task",
        }
        raw_text = (ROOT / "design-system.exceptions.json").read_text()
        entries = json.loads(raw_text)
        self.assertIsInstance(entries, list)
        self.assertGreater(len(entries), 0)

        seen_files = set()
        for entry in entries:
            missing = required_fields - set(entry.keys())
            self.assertFalse(missing, f"Entry {entry.get('id')} missing fields: {missing}")
            for field in required_fields:
                self.assertTrue(bool(str(entry[field]).strip()), f"Empty field {field} in {entry.get('id')}")

            file_rel = entry["file"]
            self.assertNotIn(file_rel, seen_files, f"Duplicate file entry: {file_rel}")
            seen_files.add(file_rel)

            file_path = ROOT / file_rel
            self.assertTrue(file_path.is_file(), f"File {file_rel} must exist")

            expected_hash = "sha256:" + hashlib.sha256(file_path.read_bytes()).hexdigest()
            self.assertEqual(
                entry["contentHash"],
                expected_hash,
                f"Exception {entry.get('id')} contentHash does not match actual content of {file_rel}",
            )

    def test_ui_contract_validator_detects_tampered_content_and_invalid_exceptions(self):
        script_path = ROOT / "scripts/check-ui-contract.mjs"
        self.assertTrue(script_path.is_file())

        with tempfile.TemporaryDirectory() as temp_root:
            isolated_root = Path(temp_root)
            env = {**os.environ, "UI_CONTRACT_ROOT": str(isolated_root)}
            baseline = subprocess.run(
                ["node", str(script_path)],
                cwd=str(ROOT),
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertEqual(baseline.returncode, 0, baseline.stderr)
            self.assertIn("PASS ui-contract", baseline.stdout)

            offender = isolated_root / "resources/js/components/offender.tsx"
            offender.parent.mkdir(parents=True)
            offender.write_text('<div className="p-4" />')
            violation = subprocess.run(
                ["node", str(script_path)],
                cwd=str(ROOT),
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertNotEqual(violation.returncode, 0)
            self.assertIn("components/offender.tsx", violation.stderr)

            js_offender = isolated_root / "resources/js/layouts/offender.js"
            js_offender.parent.mkdir(parents=True)
            js_offender.write_text('const node = <div className="p-4" />;')
            js_violation = subprocess.run(
                ["node", str(script_path)],
                cwd=str(ROOT),
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertNotEqual(js_violation.returncode, 0)
            self.assertIn("layouts/offender.js", js_violation.stderr)

            clean_primitive = isolated_root / "resources/js/design-system/primitives/clean.tsx"
            clean_primitive.parent.mkdir(parents=True, exist_ok=True)
            clean_primitive.write_text("export const clean = true;")
            targeted = subprocess.run(
                [
                    "node",
                    str(script_path),
                    "--files",
                    "resources/js/design-system/primitives/clean.tsx",
                ],
                cwd=str(ROOT),
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertEqual(targeted.returncode, 0, targeted.stderr)
            self.assertIn("1 plików UI sprawdzonych", targeted.stdout)

        script_text = script_path.read_text()
        for expected in (
            "resources/js/pages",
            "resources/js/components",
            "resources/js/layouts",
            "resources/css",
            "UI_CONTRACT_ROOT",
            "--staged",
            "--base",
            "--files",
        ):
            self.assertIn(expected, script_text)

        valid_entries = json.loads((ROOT / "design-system.exceptions.json").read_text())

        # 1: Tampered content / hash mismatch
        with tempfile.NamedTemporaryFile("w", suffix=".json") as tmp:
            tampered = [dict(e) for e in valid_entries]
            tampered[0]["contentHash"] = "sha256:" + "0" * 64
            tmp.write(json.dumps(tampered))
            tmp.flush()

            env = {**os.environ, "UI_CONTRACT_EXCEPTIONS_FILE": tmp.name}
            res = subprocess.run(
                ["node", str(script_path)],
                cwd=str(ROOT),
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertNotEqual(res.returncode, 0)
            self.assertIn("zmieniła się od momentu rejestracji", res.stderr)

        # 2: Missing mandatory field
        for field in ("contentHash", "reviewRef", "task", "owner", "expires"):
            with self.subTest(missing_field=field):
                with tempfile.NamedTemporaryFile("w", suffix=".json") as tmp:
                    invalid = [dict(e) for e in valid_entries]
                    del invalid[0][field]
                    tmp.write(json.dumps(invalid))
                    tmp.flush()

                    env = {**os.environ, "UI_CONTRACT_EXCEPTIONS_FILE": tmp.name}
                    res = subprocess.run(
                        ["node", str(script_path)],
                        cwd=str(ROOT),
                        env=env,
                        capture_output=True,
                        text=True,
                    )
                    self.assertNotEqual(res.returncode, 0)
                    self.assertIn("nie zawiera wymaganych pól", res.stderr)

        # 3: Expired exception
        with tempfile.NamedTemporaryFile("w", suffix=".json") as tmp:
            expired = [dict(e) for e in valid_entries]
            expired[0]["expires"] = "2020-01-01"
            tmp.write(json.dumps(expired))
            tmp.flush()

            env = {**os.environ, "UI_CONTRACT_EXCEPTIONS_FILE": tmp.name}
            res = subprocess.run(
                ["node", str(script_path)],
                cwd=str(ROOT),
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertNotEqual(res.returncode, 0)
            self.assertIn("wygasł", res.stderr)

        # 4: Duplicate file entry
        with tempfile.NamedTemporaryFile("w", suffix=".json") as tmp:
            duplicates = [dict(e) for e in valid_entries]
            dup_entry = dict(duplicates[0])
            dup_entry["id"] = dup_entry["id"] + "-DUP"
            duplicates.append(dup_entry)
            tmp.write(json.dumps(duplicates))
            tmp.flush()

            env = {**os.environ, "UI_CONTRACT_EXCEPTIONS_FILE": tmp.name}
            res = subprocess.run(
                ["node", str(script_path)],
                cwd=str(ROOT),
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertNotEqual(res.returncode, 0)
            self.assertIn("Zduplikowany wpis", res.stderr)

        # 5: Orphaned / non-existent file
        with tempfile.NamedTemporaryFile("w", suffix=".json") as tmp:
            orphaned = [dict(e) for e in valid_entries]
            orphaned.append({
                "id": "DS-EXC-NONEXISTENT",
                "rule": "ds/no-style-escape",
                "file": "resources/js/pages/nonexistent.tsx",
                "contentHash": "sha256:" + "a" * 64,
                "reason": "Test",
                "alternatives": "None",
                "scope": "className",
                "owner": "test",
                "reviewRef": "test-review",
                "expires": "2030-12-31",
                "task": "Test Task",
            })
            tmp.write(json.dumps(orphaned))
            tmp.flush()

            env = {**os.environ, "UI_CONTRACT_EXCEPTIONS_FILE": tmp.name}
            res = subprocess.run(
                ["node", str(script_path)],
                cwd=str(ROOT),
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertNotEqual(res.returncode, 0)
            self.assertIn("nieistniejący plik", res.stderr)

        # 6: Missing lineRanges / whole-file exception attempt
        with tempfile.NamedTemporaryFile("w", suffix=".json") as tmp:
            no_ranges = [dict(e) for e in valid_entries]
            if "lineRanges" in no_ranges[0]:
                del no_ranges[0]["lineRanges"]
            if "lines" in no_ranges[0]:
                del no_ranges[0]["lines"]
            tmp.write(json.dumps(no_ranges))
            tmp.flush()

            env = {**os.environ, "UI_CONTRACT_EXCEPTIONS_FILE": tmp.name}
            res = subprocess.run(
                ["node", str(script_path)],
                cwd=str(ROOT),
                env=env,
                capture_output=True,
                text=True,
            )
            self.assertNotEqual(res.returncode, 0)
            self.assertIn("nie precyzuje zakresu linii", res.stderr)

        # 7: Violation outside declared lineRanges
        with tempfile.TemporaryDirectory() as temp_root:
            isolated_root = Path(temp_root)
            target_file = isolated_root / "resources/js/components/dummy.tsx"
            target_file.parent.mkdir(parents=True)
            target_file.write_text(
                "// Line 1\n// Line 2\nconst valid = <div className=\"p-2\" />;\n// Line 4\nconst invalid = <div className=\"m-4\" />;\n"
            )
            content_hash = "sha256:" + hashlib.sha256(target_file.read_bytes()).hexdigest()
            isolated_exceptions = [{
                "id": "DS-EXC-TEST-RANGES",
                "rule": "ds/no-style-escape",
                "file": "resources/js/components/dummy.tsx",
                "contentHash": content_hash,
                "reason": "Test",
                "alternatives": "None",
                "scope": "className",
                "owner": "test",
                "reviewRef": "test-review",
                "expires": "2030-12-31",
                "task": "Test Task",
                "lineRanges": [[3, 3]],
            }]
            with tempfile.NamedTemporaryFile("w", suffix=".json") as tmp:
                tmp.write(json.dumps(isolated_exceptions))
                tmp.flush()

                env = {
                    **os.environ,
                    "UI_CONTRACT_ROOT": str(isolated_root),
                    "UI_CONTRACT_EXCEPTIONS_FILE": tmp.name,
                }
                res = subprocess.run(
                    ["node", str(script_path)],
                    cwd=str(ROOT),
                    env=env,
                    capture_output=True,
                    text=True,
                )
                self.assertNotEqual(res.returncode, 0)
                self.assertIn("poza zatwierdzonym zakresem wyjątku", res.stderr)

    def test_security_auth_rules_cover_critical_auth_and_user_files(self):
        index_text = (ROOT / ".ai/rules/index.md").read_text()
        security_text = (ROOT / ".ai/rules/security-auth.md").read_text()

        for expected in (
            "routes/web.php",
            "app/Models/User.php",
            "app/Providers/FortifyServiceProvider.php",
            "app/Actions/Fortify/**",
            "routes/settings.php",
        ):
            self.assertIn(expected, index_text)
            self.assertIn(expected, security_text)

        self.assertIn("HIGH-RISK", security_text)

    def test_runtime_rules_use_makefile_and_contain_no_env_docker(self):
        index_text = (ROOT / ".ai/rules/index.md").read_text()
        runtime_text = (ROOT / ".ai/rules/runtime.md").read_text()

        self.assertNotIn(".env.docker", index_text)
        self.assertNotIn(".env.docker", runtime_text)

        for cmd in (
            "make setup",
            "make up",
            "make doctor",
            "make test",
            "make artisan",
            "make composer",
            "make npm",
        ):
            self.assertIn(cmd, runtime_text)

    def test_ci_builds_assets_and_enforces_bundle_budget(self):
        package = json.loads((ROOT / "package.json").read_text())
        self.assertEqual(
            package["scripts"]["check:budget"],
            "node scripts/check-bundle-budget.mjs",
        )

        ci = (ROOT / ".github/workflows/ci.yml").read_text()
        build_step = ci.index("run: npm run build\n")
        budget_step = ci.index("run: npm run check:budget")
        self.assertLess(build_step, budget_step)

        budget = json.loads((ROOT / "bundle-budget.json").read_text())
        self.assertEqual(budget["entry"], "resources/js/app.tsx")
        self.assertIn("resources/js/pages/welcome.tsx", budget["publicPages"])
        self.assertIn("resources/js/pages/pages/show.tsx", budget["publicPages"])
        self.assertEqual(
            set(budget["limits"]), {"cssGzipKb", "publicPageTotalJsGzipKb"}
        )
        for limit in budget["limits"].values():
            self.assertGreater(limit, 0)
        for warning in ("entryJsGzipKb", "pageJsGzipKb", "largestChunkGzipKb"):
            self.assertGreater(budget["warnings"][warning], 0)

    def test_bundle_budget_script_fails_on_overrun_and_leaked_tests(self):
        script_path = ROOT / "scripts/check-bundle-budget.mjs"

        def run_budget(
            build_dir, limits, manifest_extra=None, lazy_modules=None, warnings=None
        ):
            manifest = {
                "resources/js/app.tsx": {
                    "file": "assets/app.js",
                    "isEntry": True,
                    "imports": ["_vendor.js"],
                    "dynamicImports": ["resources/js/pages/welcome.tsx"],
                },
                "_vendor.js": {"file": "assets/vendor.js"},
                "resources/css/app.css": {"file": "assets/app.css", "isEntry": True},
                "resources/js/pages/welcome.tsx": {
                    "file": "assets/welcome.js",
                    "src": "resources/js/pages/welcome.tsx",
                    "isDynamicEntry": True,
                    "imports": ["resources/js/app.tsx", "_vendor.js"],
                },
                **(manifest_extra or {}),
            }
            (build_dir / "manifest.json").write_text(json.dumps(manifest))
            budget_path = build_dir / "budget.json"
            budget_path.write_text(
                json.dumps(
                    {
                        "entry": "resources/js/app.tsx",
                        "css": ["resources/css/app.css"],
                        "publicPages": ["resources/js/pages/welcome.tsx"],
                        "lazyModules": lazy_modules or [],
                        "limits": limits,
                        "warnings": warnings or {},
                    }
                )
            )
            return subprocess.run(
                [
                    "node",
                    str(script_path),
                    "--build-dir",
                    str(build_dir),
                    "--budget",
                    str(budget_path),
                ],
                cwd=str(ROOT),
                capture_output=True,
                text=True,
            )

        generous = {
            "cssGzipKb": 100,
            "publicPageTotalJsGzipKb": 100,
        }

        with tempfile.TemporaryDirectory() as temp_root:
            build_dir = Path(temp_root)
            assets = build_dir / "assets"
            assets.mkdir()
            (assets / "app.js").write_text("export const app = 1;")
            (assets / "vendor.js").write_bytes(os.urandom(8 * 1024))
            (assets / "app.css").write_text("body{margin:0}")
            (assets / "welcome.js").write_text("export default 1;")
            (assets / "leak.js").write_text("export default 1;")

            passing = run_budget(build_dir, generous)
            self.assertEqual(passing.returncode, 0, passing.stderr)
            self.assertIn("PASS bundle-budget", passing.stdout)

            over = run_budget(build_dir, {**generous, "publicPageTotalJsGzipKb": 1})
            self.assertEqual(over.returncode, 1)
            self.assertIn("łączny JS", over.stderr)

            css_over = run_budget(build_dir, {**generous, "cssGzipKb": 0.001})
            self.assertEqual(css_over.returncode, 1)
            self.assertIn("krytyczny CSS", css_over.stderr)

            warned = run_budget(
                build_dir,
                generous,
                warnings={"entryJsGzipKb": 1, "pageJsGzipKb": 0.001},
            )
            self.assertEqual(warned.returncode, 0, warned.stderr)
            self.assertIn("WARN bundle-budget", warned.stderr)
            self.assertIn("entry JS", warned.stderr)
            self.assertIn("PASS bundle-budget", warned.stdout)

            leaked = run_budget(
                build_dir,
                generous,
                {
                    "resources/js/pages/admin/index.test.tsx": {
                        "file": "assets/leak.js",
                        "src": "resources/js/pages/admin/index.test.tsx",
                        "isDynamicEntry": True,
                    }
                },
            )
            self.assertEqual(leaked.returncode, 1)
            self.assertIn("index.test.tsx", leaked.stderr)

            lazy_ok = run_budget(
                build_dir, generous, lazy_modules=["resources/js/pages/welcome.tsx"]
            )
            self.assertEqual(lazy_ok.returncode, 0, lazy_ok.stderr)

            lazy_in_entry = run_budget(
                build_dir,
                generous,
                {
                    "resources/js/app.tsx": {
                        "file": "assets/app.js",
                        "isEntry": True,
                        "imports": ["_vendor.js", "resources/js/components/dialog.tsx"],
                    },
                    "resources/js/components/dialog.tsx": {
                        "file": "assets/leak.js",
                        "src": "resources/js/components/dialog.tsx",
                        "isDynamicEntry": True,
                    },
                },
                lazy_modules=["resources/js/components/dialog.tsx"],
            )
            self.assertEqual(lazy_in_entry.returncode, 1)
            self.assertIn("statycznie w entry", lazy_in_entry.stderr)

            lazy_missing = run_budget(
                build_dir, generous, lazy_modules=["resources/js/components/missing.tsx"]
            )
            self.assertEqual(lazy_missing.returncode, 1)
            self.assertIn("nie jest osobnym chunkiem", lazy_missing.stderr)

    def test_bundle_budget_keeps_password_confirmation_dialog_lazy(self):
        budget = json.loads((ROOT / "bundle-budget.json").read_text())
        self.assertIn(
            "resources/js/components/password-confirmation-dialog.tsx",
            budget["lazyModules"],
        )
        gate = (ROOT / "resources/js/components/password-confirmation-modal.tsx").read_text()
        self.assertIn("import('@/components/password-confirmation-dialog')", gate)
        self.assertNotIn("from '@/components/ui/dialog'", gate)
        self.assertNotIn("passkey-verify", gate)
        self.assertNotIn("@laravel/passkeys", gate)

    def test_ci_runs_browser_e2e_against_the_docker_stack(self):
        ci = (ROOT / ".github/workflows/ci.yml").read_text()
        self.assertIn("\n  e2e:\n    name: e2e\n", ci)
        e2e_job = ci[ci.index("\n  e2e:\n"):]
        self.assertIn("cp .env.example .env", e2e_job)
        self.assertIn("run: make e2e", e2e_job)
        self.assertIn("actions/upload-artifact@", e2e_job)
        self.assertIn("if: failure()", e2e_job)
        self.assertIn("playwright-report/", e2e_job)
        self.assertNotIn("secrets.", e2e_job)
        for line in e2e_job.splitlines():
            if "docker compose up" in line:
                self.assertNotIn("clamav", line)

        makefile = (ROOT / "Makefile").read_text()
        self.assertIn("e2e: env ##", makefile)
        self.assertIn("app:e2e-prepare --client-host=playwright", makefile)
        self.assertIn("npx playwright test", makefile)
        self.assertNotRegex(makefile, r"(?m)^E2E_PASSWORD\s*[:?]?=")

        package = json.loads((ROOT / "package.json").read_text())
        playwright_version = package["devDependencies"]["@playwright/test"]
        self.assertRegex(playwright_version, r"^\d+\.\d+\.\d+$")
        compose = (ROOT / "compose.yaml").read_text()
        self.assertIn(
            f"image: mcr.microsoft.com/playwright:v{playwright_version}-noble",
            compose,
        )
        self.assertIn("profiles: [e2e]", compose)
        e2e_override = (ROOT / "compose.e2e.yaml").read_text()
        self.assertIn("APP_ENV: e2e", e2e_override)
        self.assertIn("-f compose.e2e.yaml", makefile)

        for spec in ("journeys.spec.ts", "perf.spec.ts"):
            self.assertTrue((ROOT / "tests/e2e" / spec).is_file(), spec)
        perf = (ROOT / "tests/e2e/perf.spec.ts").read_text()
        self.assertIn("LCP_BUDGET_MS = 2_500", perf)
        self.assertIn("CLS_BUDGET = 0.1", perf)

    def test_e2e_accounts_are_synthetic_and_local_only(self):
        command = (ROOT / "app/Console/Commands/PrepareE2eCommand.php").read_text()
        self.assertIn("app()->environment(['local', 'testing'])", command)
        config = (ROOT / "config/e2e.php").read_text()
        self.assertIn("env('E2E_PASSWORD')", config)
        self.assertIn("@example.test", config)
        env_example = (ROOT / ".env.example").read_text()
        self.assertIn("# E2E_PASSWORD=", env_example)

    def test_local_nginx_caches_hashed_assets_and_compresses_text(self):
        nginx = (ROOT / "docker/local/nginx.conf").read_text()
        self.assertIn("location ^~ /build/assets/", nginx)
        self.assertIn('"public, max-age=31536000, immutable"', nginx)
        self.assertIn("gzip on;", nginx)
        self.assertIn("gzip_vary on;", nginx)
        for mime in ("text/css", "application/javascript", "application/json", "image/svg+xml"):
            self.assertIn(mime, nginx)
