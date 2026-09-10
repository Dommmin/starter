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
            "vp check resources/js resources/css scripts vite.config.ts",
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

        baseline = subprocess.run(
            ["node", str(script_path)],
            cwd=str(ROOT),
            capture_output=True,
            text=True,
        )
        self.assertEqual(baseline.returncode, 0, f"Validator failed on current repo: {baseline.stderr}")
        self.assertIn("PASS ui-contract", baseline.stdout)

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
