"""Guard the shared skill adapters and reviewer permission configuration."""

import re
import tomllib
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
        ci = (ROOT / ".github/workflows/ci.yml").read_text()
        self.assertIn('branches: [develop, main]', ci)
        self.assertIn('name: commit-convention', ci)
        self.assertIn('name: secrets', ci)
        self.assertIn('name: quality', ci)
        self.assertIn('gitleaks/gitleaks-action@', ci)

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
