"""Hook PreToolUse (Bash|PowerShell): blokuje komendy destrukcyjne niezależnie od zapisu.

Wejście: JSON na stdin (tool_input.command, cwd). Blokada: kod 2 i powód na stderr (widzi go agent).
Copilot (hook preToolUse w .github/hooks/, wejście toolName/toolArgs, toolArgs jako obiekt albo napis JSON):
blokada to JSON {"permissionDecision": "deny", ...} na stdout i kod 0; sprawdza też ścieżki narzędzi plików
(view, edit, create), bo Copilot nie ma odpowiednika permissions.deny dla odczytu .env.
Komenda jest dzielona na słowa jak w powłoce (cudzysłowy, &&, ;, |, $(...)), więc tekst
w argumencie (np. grep -r migrate:fresh docs/) nie blokuje. Bliźniaki: guard-bash.mjs i guard-bash.php;
ten sam zestaw przypadków testowych. Tylko biblioteka standardowa.

Starter: kopia wariantu python z ai-playbook-templates 3.62.2 z dodatkami (testy: tests/ai/test_guard_bash.py):
--exec=<podpowiedź> blokuje PHP i narzędzia PHP poza kontenerem (jak guard-bash.mjs), prefiks rtk
(globalny hook RTK przepisuje komendy) jest zdejmowany, a targety Makefile `fresh` i `artisan ARGS=...`
podlegają tym samym regułom co artisan.
"""

from __future__ import annotations

import fnmatch
import json
import os
import re
import sys
from pathlib import Path

PREFIX = "Zablokowane przez hook guard-bash"
# Klucze ścieżki w argumentach narzędzi plików (Copilot nie dokumentuje nazw: kilka wariantów).
FILE_KEYS = ("path", "file_path", "filePath", "file", "paths")
DB = (
    "kasuje dane w bazie. Reset bazy deweloperskiej tylko przez wrapper scripts/db-reset.* "
    "projektu z --confirm i po zgodzie człowieka."
)
REASONS = {
    "push": "wymuszony push nadpisuje historię na zdalnym repo. Zrób zwykły push albo poproś człowieka.",
    "reset": "git reset --hard usuwa niezatwierdzone zmiany. Użyj git stash albo git restore na konkretnych plikach.",
    "rm": "rekurencyjne usuwanie katalogu repo, jego rodzica albo .git. Usuwaj konkretne pliki lub podkatalogi.",
    "env": "pliki .env* (poza .env.example) zawierają sekrety. Nazwy zmiennych są w .env.example.",
    "run": "docker compose run potrafi zostawić w storage pliki należące do roota. Użyj docker compose exec -T app <komenda>.",
    "clean": "git clean -f usuwa nieśledzone pliki, także lokalne konfiguracje i .env. Pokaż listę przez git clean -n i poproś człowieka.",
    "remote": "usuwa albo nadpisuje gałęzie na zdalnym repo (--mirror, --delete, --prune, :gałąź). Poproś człowieka.",
    "volumes": "usuwa wolumeny Dockera razem z danymi bazy. Zatrzymaj kontenery bez -v (docker compose down) albo poproś człowieka.",
    "protected": "ścieżka z .ai/protected-paths (klucz odpowiedzi: zbiór eval albo holdout). Agent jej nie czyta; wynik oceny poda człowiek albo skrypt ewaluacji.",
    "make": "make fresh usuwa lokalną bazę (migrate:fresh). Reset bazy tylko przez człowieka.",
    "input": "nieczytelne wejście hooka (oczekiwany JSON z tool_input.command). Komenda nie została sprawdzona, więc jest zablokowana.",
}
ARTISAN = {
    "migrate:fresh",
    "migrate:reset",
    "migrate:refresh",
    "migrate:rollback",
    "db:wipe",
    "db:seed",
}
# Narzędzia PHP, które przy --exec nie działają na hoście (bliźniak PHP_TOOLS z guard-bash.mjs).
PHP_TOOLS = r"php[\d.]*|composer|artisan|pint|phpstan|phpunit|pest|paratest|rector|psalm"
DJANGO = {"flush", "reset_db", "sqlflush"}
RAILS = {"db:drop", "db:reset", "db:purge", "db:schema:load", "db:truncate_all"}
RUNNERS = {"uv", "poetry", "pipenv", "pdm", "hatch", "rye"}
ENV_OK = {".env.example", ".env.sample", ".env.dist", ".env.template"}
ENV_SAMPLES = (".env", ".env.local", ".env.production", ".env.testing")
READERS = {
    "cat",
    "less",
    "more",
    "head",
    "tail",
    "grep",
    "egrep",
    "fgrep",
    "rg",
    "sed",
    "awk",
    "gawk",
    "bat",
    "nl",
    "tac",
    "xxd",
    "od",
    "base64",
    "strings",
    "sort",
    "uniq",
    "cut",
    "diff",
    "type",
    "get-content",
    "gc",
    "select-string",
    "sls",
}
COPIERS = {"cp", "scp", "rsync", "copy-item", "cpi", "copy"}
# Klienci HTTP wysyłają plik podany wprost (-T .env, --post-file=.env, PowerShell -InFile .env) albo po @ i <
# (-d @.env, -F f=<.env, f@.env).
UPLOADERS = {"curl", "wget", "http", "https", "xh", "xhs", "invoke-webrequest", "iwr", "invoke-restmethod", "irm"}
REMOVERS = {"rm", "remove-item", "ri", "del", "erase", "rd", "rmdir"}
SHELLS = {"sh", "bash", "zsh", "dash", "ksh", "pwsh", "powershell"}
PASS = {
    "command",
    "builtin",
    "exec",
    "time",
    "nohup",
    "if",
    "elif",
    "while",
    "until",
    "then",
    "do",
    "else",
    "!",
    "{",
}
# Opcje z wartością w osobnym słowie, dla programów, które przeskakujemy.
VALUED = {
    "sudo": ["-u", "-g", "-U", "-C", "-D", "-p", "-r", "-t", "-h"],
    "env": ["-u", "-C", "-S", "--unset", "--chdir"],
    "nice": ["-n"],
    "xargs": ["-I", "-n", "-P", "-d", "-L", "-s", "-E", "-a"],
    "wsl": ["-d", "--distribution", "-u", "--user", "--cd"],
    "npx": ["-p", "--package", "-c", "--call"],
    "docker": ["--context", "-c", "-H", "--host", "--config", "-l", "--log-level"],
    "compose": [
        "-f",
        "--file",
        "-p",
        "--project-name",
        "--project-directory",
        "--profile",
        "--env-file",
        "--ansi",
        "--progress",
        "--parallel",
    ],
    "exec": [
        "-e",
        "--env",
        "-u",
        "--user",
        "-w",
        "--workdir",
        "--index",
        "--env-file",
        "--detach-keys",
    ],
    "git": [
        "-C",
        "-c",
        "--git-dir",
        "--work-tree",
        "--namespace",
        "--exec-path",
        "--config-env",
    ],
    "php": ["-d", "-c", "-z"],
    "alembic": ["-c", "--config", "-n", "--name", "-x"],
    "uvx": [
        "--from",
        "--with",
        "--with-editable",
        "--with-requirements",
        "--python",
        "-p",
        "--index",
        "--index-url",
        "--extra-index-url",
        "--directory",
    ],
    "run": [
        "--with",
        "--python",
        "-p",
        "--directory",
        "--project",
        "--package",
        "--env-file",
        "--extra",
        "--group",
        "-e",
        "--env",
    ],
}


def name(word: str | None) -> str:
    base = (word or "").replace("\\", "/").split("/")[-1].lower()
    return re.sub(r"\.(exe|cmd|bat)$", "", base)


def parse(command: str, nested: list[str]) -> list[list[str]]:
    """Dzieli komendę na segmenty (listy słów); treść $(...) i `...` trafia do nested."""
    segments: list[list[str]] = []
    words: list[str] = []
    state = {"word": "", "started": False, "want_delimiter": False}
    delimiters: list[str] = []
    quote = None

    def end_word() -> None:
        if state["started"]:
            words.append(state["word"])
            if state["want_delimiter"]:
                delimiters.append(state["word"])
            state["want_delimiter"] = False
        state["word"], state["started"] = "", False

    def end_segment() -> None:
        nonlocal words
        end_word()
        if words:
            segments.append(words)
        words = []

    i, n = 0, len(command)
    while i < n:
        c = command[i]
        nxt = command[i + 1] if i + 1 < n else ""
        if quote == "'":
            if c == "'":
                quote = None
            else:
                state["word"] += c
            i += 1
            continue
        if c == "\\" and nxt == "\n":  # kontynuacja linii
            i += 2
            continue
        if c == "\\" and i + 1 < n and (quote is None or nxt in '"\\$`'):
            state["word"] += nxt
            state["started"] = True
            i += 2
            continue
        if c == "$" and nxt == "(":
            depth, j = 1, i + 2
            while j < n and depth:
                depth += 1 if command[j] == "(" else -1 if command[j] == ")" else 0
                j += 1
            inner = command[i + 2 : j - 1]
            nested.append(inner)
            state["word"] += f"$({inner})"
            state["started"] = True
            i = j
            continue
        if c == "`":
            j = command.find("`", i + 1)
            end = n if j == -1 else j
            nested.append(command[i + 1 : end])
            state["started"] = True
            i = end + 1
            continue
        if quote == '"':
            if c == '"':
                quote = None
            else:
                state["word"] += c
            i += 1
            continue
        if c in "\"'":
            quote = c
            state["started"] = True
            i += 1
            continue
        if c != "\n" and c.isspace():
            end_word()
            i += 1
            continue
        if c == "\n" and delimiters:
            # Treść heredoc to dane, nie komendy: przeskakujemy do linii z ogranicznikiem.
            end_segment()
            while delimiters and i < n:
                delimiter = delimiters.pop(0)
                end = command.find("\n", i + 1)
                while end != -1 and command[i + 1 : end].strip() != delimiter:
                    i = end
                    end = command.find("\n", i + 1)
                i = n if end == -1 else end
            i += 1
            continue
        if c in ";&|()\n":
            end_segment()
            i += 1
            continue
        if c in "<>":
            if re.fullmatch(r"\d+", state["word"]):  # 2>&1, 2>/dev/null
                state["word"], state["started"] = "", False
            end_word()
            op = c
            while i + 1 < n and command[i + 1] in "<>":
                i += 1
                op += command[i]
            if i + 1 < n and command[i + 1] == "&":
                i += 1
                while i + 1 < n and re.match(r"[\d-]", command[i + 1]):
                    i += 1
                i += 1
                continue
            words.append(op)
            if op in ("<<", "<<-"):
                state["want_delimiter"] = True
            i += 1
            continue
        state["word"] += c
        state["started"] = True
        i += 1
    end_segment()
    return segments


def skip_options(words: list[str], i: int, valued: list[str] | None = None) -> int:
    valued = valued or []
    while i < len(words) and words[i].startswith("-") and words[i] != "-":
        if words[i] == "--":
            return i + 1
        i += 2 if words[i] in valued else 1
    return i


def unwrap(words: list[str]) -> dict:
    """Zdejmuje przedrostki (zmienne, sudo, npx, uv run, docker compose exec, sail, rtk...) do właściwego programu.

    contained: komenda działa w kontenerze (docker exec, docker compose exec, sail).
    """
    contained = False
    for _ in range(20):
        if not words:
            break
        first = name(words[0])
        if re.match(r"^[A-Za-z_][A-Za-z0-9_]*=", words[0]) or first in PASS:
            words = words[1:]
            continue
        if first in ("sudo", "doas", "env", "nice", "xargs", "wsl"):
            i = skip_options(words, 1, VALUED.get(first, []))
            while first == "env" and i < len(words) and "=" in words[i]:
                i += 1
            if first == "wsl" and i < len(words) and words[i] in ("-e", "--exec"):
                i += 1
            words = words[i:]
            continue
        if first == "timeout":
            words = words[
                skip_options(words, 1, ["-s", "-k", "--signal", "--kill-after"]) + 1 :
            ]
            continue
        if first == "cmd":
            for k, w in enumerate(words):
                if k > 0 and re.fullmatch(r"/[ck]", w, re.IGNORECASE):
                    return {"nested": " ".join(words[k + 1 :]), "contained": contained}
            return {"words": []}
        if first in SHELLS:
            for k, w in enumerate(words):
                if (
                    k > 0
                    and k + 1 < len(words)
                    and re.fullmatch(r"-(c|lc|ic|command)", w, re.IGNORECASE)
                ):
                    return {"nested": " ".join(words[k + 1 :]), "contained": contained}
            return {"words": []}
        if first == "eval":
            return {"nested": " ".join(words[1:]), "contained": contained}
        if first == "rtk":
            # rtk <komenda> i rtk proxy <komenda> uruchamiają komendę; rtk read <plik> czyta plik jak cat.
            sub = words[1] if len(words) > 1 else ""
            if sub == "read":
                words = ["cat", *words[2:]]
                continue
            words = words[2:] if sub == "proxy" else words[1:]
            continue
        if first in RUNNERS and len(words) > 1 and words[1] == "run":
            words = words[skip_options(words, 2, VALUED["run"]) :]
            continue
        if first == "uvx" or (first == "uv" and words[1:3] == ["tool", "run"]):
            start = 1 if first == "uvx" else 3
            words = words[skip_options(words, start, VALUED["uvx"]) :]
            continue
        if first == "bundle" and len(words) > 1 and words[1] == "exec":
            words = words[2:]
            continue
        if first in ("npx", "bunx", "pnpx"):
            words = words[skip_options(words, 1, VALUED["npx"]) :]
            continue
        if first in ("pnpm", "yarn", "bun", "npm"):
            sub = words[1] if len(words) > 1 else None
            if sub in ("exec", "dlx", "x"):
                words = words[skip_options(words, 2, VALUED["npx"]) :]
                continue
            if (
                first != "npm"
                and sub
                and not sub.startswith("-")
                and sub not in ("run", "install", "add", "test")
            ):
                words = words[1:]
                continue
            return {"words": words, "contained": contained}
        if first == "sail":
            sub = words[1] if len(words) > 1 else ""
            if sub in ("artisan", "art"):
                words = ["php", "artisan", *words[2:]]
                contained = True
                continue
            if sub in ("php", "composer", "npm", "npx", "git"):
                words = words[1:]
                contained = True
                continue
            return {"words": words, "contained": contained}
        if first in ("docker", "docker-compose"):
            i = 1
            if first == "docker":
                i = skip_options(words, 1, VALUED["docker"])
                at = words[i] if i < len(words) else ""
                after = words[i + 1] if i + 1 < len(words) else ""
                if at == "exec":
                    i = skip_options(words, i + 1, VALUED["exec"])
                    words = words[i + 1 :]
                    contained = True
                    continue
                if at == "volume" and after in ("rm", "prune"):
                    return {"reason": REASONS["volumes"]}
                if at == "system" and after == "prune" and "--volumes" in words:
                    return {"reason": REASONS["volumes"]}
                if at != "compose":
                    return {"words": []}
                i += 1
            i = skip_options(words, i, VALUED["compose"])
            at = words[i] if i < len(words) else ""
            if at == "run":
                return {"reason": REASONS["run"]}
            if at == "down" and any(
                a == "--volumes" or re.fullmatch(r"-[a-zA-Z]*v[a-zA-Z]*", a)
                for a in words[i + 1 :]
            ):
                return {"reason": REASONS["volumes"]}
            if at != "exec":
                return {"words": []}
            i = skip_options(words, i + 1, VALUED["exec"])
            words = words[i + 1 :]
            contained = True
            continue
        break
    return {"words": words, "contained": contained}


def normalize(path: str) -> str:
    drive = path[:2].lower() if re.match(r"^[A-Za-z]:", path) else ""
    parts: list[str] = []
    for part in path[len(drive) :].split("/"):
        if part in ("", "."):
            continue
        if part == "..":
            if parts:
                parts.pop()
        else:
            parts.append(part)
    return f"{drive}/{'/'.join(parts)}"


def expand(target: str, ctx: dict) -> str | None:
    t = target.replace("\\", "/")
    t = re.sub(r"^~(?=/|$)", lambda _: ctx["home"], t)
    t = re.sub(r"^\$\{?HOME\}?(?=/|$)", lambda _: ctx["home"], t)
    t = re.sub(r"^(\$\{?PWD\}?|\$\(pwd\))(?=/|$)", lambda _: ctx["cwd"], t)
    t = re.sub(r"^\$\{?CLAUDE_PROJECT_DIR\}?(?=/|$)", lambda _: ctx["root"], t)
    if "$" in t:
        return None
    t = re.sub(r"(^|/)\.?\*$", r"\1", t) or "."
    absolute = t.startswith("/") or re.match(r"^[A-Za-z]:/", t)
    return normalize(t if absolute else f"{ctx['cwd'].replace(chr(92), '/')}/{t}")


def dangerous_target(target: str, ctx: dict) -> bool:
    resolved = expand(target, ctx)
    if resolved is None:
        return False
    root = normalize(ctx["root"].replace("\\", "/"))
    home = normalize(ctx["home"].replace("\\", "/"))
    return (
        resolved == "/"
        or resolved.split("/")[-1] == ".git"
        or resolved in (root, home)
        or "/.git/" in resolved
        or root.startswith(resolved + "/")
        or os.path.exists(resolved + "/.git")
    )


def is_env_file(word: str) -> bool:
    value = (
        word[word.index("=") + 1 :] if word.startswith("-") and "=" in word else word
    )
    base = value.replace("\\", "/").split("/")[-1].lower()
    if base in ENV_OK:
        return False
    if re.fullmatch(r"\.env(\..*)?", base) or re.match(r"\.env[*?]", base):
        return True
    # Wzorzec powłoki zaczynający się od kropki (.en*, .e?v, .[e]nv) może trafić w .env: traktujemy go jak .env.
    if not base.startswith(".") or not re.search(r"[*?\[]", base):
        return False
    return any(fnmatch.fnmatchcase(sample, base) for sample in ENV_SAMPLES)


def recursive(words: list[str]) -> bool:
    for w in words:
        lower = w.lower()
        if lower in ("--recursive", "/s"):
            return True
        if not re.fullmatch(r"-[a-z]+", lower):
            continue
        flag = lower[1:]
        if len(flag) >= 2 and "recurse".startswith(flag):
            return True
        if len(flag) <= 4 and "r" in flag:
            return True
    return False


# Interpreter (php -r, python -c, node -e, deno eval, ruby/perl -e, skrypt z argumentem): każdy argument, który
# wymienia plik .env*, także w kodzie i w grupie flag (python -Bc). Wielkość liter bez znaczenia (macOS, Windows).
INTERPRETERS = r"php[\d.]*|php-cli|python[\d.]*|py|pypy[\d.]*|node|nodejs|bun|deno|ruby[\d.]*|perl[\d.]*"


def mentions_env_file(text: str) -> bool:
    return any(
        m.group(0).lower() not in ENV_OK
        for m in re.finditer(r"(?<![\w$])\.env(?:\.[\w-]+)?(?![\w-])", text, re.I)
    )


def find_commands(args: list[str]) -> list[list[str]]:
    """find ... -exec|-execdir|-ok|-okdir <komenda> ; : komenda z {} zamienionym na wzorzec -name/-path."""
    names: list[str] = []
    commands: list[list[str]] = []
    i = 0
    while i < len(args):
        if args[i] in ("-name", "-iname", "-path", "-ipath", "-wholename"):
            names.append(args[i + 1] if i + 1 < len(args) else "")
            i += 2
            continue
        if args[i] in ("-exec", "-execdir", "-ok", "-okdir"):
            words: list[str] = []
            i += 1
            while i < len(args) and args[i] not in (";", "+"):
                words.append(args[i])
                i += 1
            commands.append(words)
        i += 1
    values = names or ["{}"]
    return [
        [w.replace("{}", value) for w in words]
        for words in commands
        for value in values
    ]


def first_positional(args: list[str]) -> str | None:
    return next((a for a in args if not a.startswith("-")), None)


def python_module(args: list[str]) -> list[str] | None:
    """[moduł, *argumenty] z -m przed pierwszym argumentem pozycyjnym (python -I -m x, -Im x, -mx)."""
    i = 0
    while i < len(args) and args[i].startswith("-") and args[i] not in ("-", "--"):
        word = args[i]
        i += 1
        if word.startswith("--"):
            continue
        for j, flag in enumerate(word[1:], start=2):
            if flag == "m":
                if word[j:]:
                    return [word[j:], *args[i:]]
                return args[i:] or None
            if flag == "c":
                return None
            if flag in "WX":
                if not word[j:]:
                    i += 1
                break
    return None


def check_words(raw: list[str], ctx: dict) -> str | None:
    words: list[str] = []
    i = 0
    while i < len(raw):
        w = raw[i]
        if w == "<":
            if is_env_file(raw[i + 1] if i + 1 < len(raw) else ""):
                return REASONS["env"]
            i += 2
            continue
        if re.fullmatch(r"[<>]+", w):
            i += 2
            continue
        words.append(w)
        i += 1
    unwrapped = unwrap(words)
    if "reason" in unwrapped:
        return unwrapped["reason"]
    inner = {**ctx, "contained": True} if unwrapped.get("contained") else ctx
    if "nested" in unwrapped:
        return check(unwrapped["nested"], inner)
    words = unwrapped["words"]
    if not words:
        return None
    program = name(words[0])
    if ctx.get("exec") and not inner.get("contained") and re.fullmatch(PHP_TOOLS, program):
        return (
            f"{program} na hoście: PHP i narzędzia PHP działają w kontenerze. "
            f"Uruchom przez {ctx['exec']}."
        )
    args = words[1:]
    if program == "make":
        return check_make(args, ctx)
    if re.fullmatch(INTERPRETERS, program) and any(mentions_env_file(a) for a in args):
        return REASONS["env"]

    is_php = re.fullmatch(r"php[\d.]*", program) is not None
    if is_php:
        i = skip_options(words, 1, VALUED["php"])
        if name(words[i] if i < len(words) else "") != "artisan":
            return None
        args = words[i + 1 :]
    if is_php or program == "artisan":
        sub = first_positional(args)
        if sub in ARTISAN or (sub == "migrate" and "--seed" in args):
            return f"artisan {sub} {DB}"
        return None
    if program == "prisma":
        positional = [a for a in args if not a.startswith("-")]
        sub = " ".join(positional[:2])
        if "--accept-data-loss" in args:
            return f"prisma {sub} --accept-data-loss {DB}"
        if sub in ("migrate reset", "db push"):
            return f"prisma {sub} {DB}"
        return None
    if program == "git":
        i = skip_options(words, 1, VALUED["git"])
        sub = words[i] if i < len(words) else ""
        rest = words[i + 1 :]
        if sub == "push" and any(
            a in ("--force", "--force-with-lease", "--force-if-includes")
            or a.startswith("--force-with-lease=")
            or re.fullmatch(r"-[a-zA-Z]*f[a-zA-Z]*", a)
            or re.match(r"^\+[^+]", a)
            for a in rest
        ):
            return REASONS["push"]
        if sub == "push" and any(
            a in ("--mirror", "--delete", "-d", "--prune") or re.match(r"^:[^:]", a)
            for a in rest
        ):
            return REASONS["remote"]
        if sub == "reset" and "--hard" in rest:
            return REASONS["reset"]
        if sub == "clean" and any(
            a == "--force" or re.fullmatch(r"-[a-zA-Z]*f[a-zA-Z]*", a) for a in rest
        ):
            return REASONS["clean"]
        return None
    if program == "find":
        for inner in find_commands(args):
            reason = check_words(inner, ctx)
            if reason:
                return reason
        return None
    if re.fullmatch(r"python[\d.]*", program) or program == "py":
        module = python_module(args)
        if module:
            return check_words(module, ctx) if module[0] == "alembic" else None
        positional = [a for a in args if not a.startswith("-")]
        if (
            positional
            and name(positional[0]) == "manage.py"
            and len(positional) > 1
            and positional[1] in DJANGO
        ):
            return f"manage.py {positional[1]} {DB}"
        return None
    if program == "alembic":
        i = skip_options(args, 0, VALUED["alembic"])
        sub = args[i] if i < len(args) else None
        return f"alembic downgrade {DB}" if sub == "downgrade" else None
    if program in ("rails", "rake"):
        sub = next((a for a in args if a in RAILS), None)
        return f"{program} {sub} {DB}" if sub else None
    if program == "mix":
        sub = next((a for a in args if a in ("ecto.drop", "ecto.reset")), None)
        return f"mix {sub} {DB}" if sub else None
    if program == "dropdb":
        return f"dropdb {DB}"
    if program in REMOVERS:
        if not recursive(args):
            return None
        targets: list[str] = []
        options = True
        i = 0
        while i < len(args):
            a = args[i]
            if options and a == "--":
                options = False
            elif options and re.fullmatch(r"-(path|literalpath|lp)", a, re.IGNORECASE):
                targets.append(args[i + 1] if i + 1 < len(args) else "")
                i += 1
            elif options and (
                a.startswith("-") or re.fullmatch(r"/[a-z]", a, re.IGNORECASE)
            ):
                pass  # -rf, -Recurse, /s (cmd)
            else:
                targets.append(a)
            i += 1
        return REASONS["rm"] if any(dangerous_target(t, ctx) for t in targets) else None
    if program in COPIERS:
        positional = [a for a in args if not a.startswith("-")]
        sources = positional[:-1] + [a for a in args if a.startswith("-") and "=" in a]
        return REASONS["env"] if any(is_env_file(s) for s in sources) else None
    if program in READERS:
        return REASONS["env"] if any(is_env_file(a) for a in args) else None
    if program in UPLOADERS:
        return REASONS["env"] if any(uploads_env_file(a) for a in args) else None
    return None


def uploads_env_file(word: str) -> bool:
    """Argument klienta HTTP wskazuje plik .env*: wprost, po sklejonych krótkich opcjach (-T.env, -sT.env) albo po @ i <
    (bez dopisku ;type=...)."""
    parts = [word] + [p.split(";")[0] for p in re.split(r"[@<]", word)[1:]]
    if re.match(r"-[A-Za-z].", word):
        parts += [word[i:] for i in range(2, len(word))]
    return any(is_env_file(p) for p in parts)


def check_make(args: list[str], ctx: dict) -> str | None:
    """Targety Makefile startera: fresh kasuje bazę, artisan ARGS='...' to artisan w kontenerze."""
    targets = [a for a in args if not a.startswith("-") and "=" not in a]
    if "fresh" in targets:
        return REASONS["make"]
    if "artisan" in targets:
        for a in args:
            if a.startswith("ARGS="):
                return check(f"php artisan {a[len('ARGS='):]}", {**ctx, "contained": True})
    return None


def check(command: str, ctx: dict) -> str | None:
    nested: list[str] = []
    segments = parse(command, nested)
    for inner in nested:
        reason = check(inner, ctx)
        if reason:
            return reason
    for words in segments:
        reason = check_words(words, ctx)
        if reason:
            return reason
    return None


def tool_arguments(data: dict) -> tuple[object, bool]:
    """(argumenty narzędzia, czy wejście Copilota w formacie camelCase)."""
    if "tool_input" in data or ("toolArgs" not in data and "toolName" not in data):
        return data.get("tool_input"), False
    args = data.get("toolArgs")
    if isinstance(args, str):
        try:
            args = json.loads(args)
        except ValueError:
            args = {"command": args}
    return args, True


def env_path(args: dict) -> bool:
    """Narzędzie plików (Copilot view, edit, create) na pliku .env*."""
    for key in FILE_KEYS:
        value = args.get(key)
        for item in value if isinstance(value, list) else [value]:
            if isinstance(item, str) and is_env_file(item):
                return True
    return False


def protected_paths(root: str) -> list[str]:
    """Ścieżki z <root>/.ai/protected-paths: jedna na linię, względem roota, # to komentarz."""
    try:
        lines = Path(root, ".ai", "protected-paths").read_text(encoding="utf-8").splitlines()
    except OSError:
        return []
    paths = []
    for line in lines:
        line = line.split("#", 1)[0].strip().replace("\\", "/")
        while line.startswith("./"):
            line = line[2:]
        line = line.strip("/")
        if line:
            paths.append(line)
    return paths


def touches_protected(text: str, paths: list[str], root: str) -> bool:
    """Tekst komendy albo ścieżka narzędzia plików wskazuje chronioną ścieżkę (względną, ./ albo absolutną)."""
    text = text.replace("\\", "/")
    root = root.replace("\\", "/").rstrip("/")
    for path in paths:
        for form in (path, f"{root}/{path}"):
            pattern = r"(?<![\w.-])(?:\./)?" + re.escape(form) + r"(?=$|/|[\s'\"`;|&<>()*?\[])"
            if re.search(pattern, text, re.I):
                return True
    return False


def main() -> int:
    try:
        data = json.loads(sys.stdin.read())
    except (ValueError, UnicodeDecodeError):
        data = None
    if not isinstance(data, dict):
        sys.stderr.write(f"{PREFIX}: {REASONS['input']}\n")
        return 2
    args, copilot = tool_arguments(data)
    if not isinstance(args, dict):
        return 0
    command = args.get("command")
    reason = None
    if isinstance(command, str):
        cwd = data.get("cwd") if isinstance(data.get("cwd"), str) else os.getcwd()
        ctx = {
            "cwd": cwd,
            "root": os.environ.get("CLAUDE_PROJECT_DIR") or cwd,
            "home": str(Path.home()),
            "exec": next((a[len("--exec="):].strip() for a in sys.argv[1:] if a.startswith("--exec=")), ""),
        }
        reason = check(command, ctx)
    # Ścieżki zawsze: narzędzie plików może mieć też pole command (str_replace_editor: command "view").
    if not reason and env_path(args):
        reason = REASONS["env"]
    root = os.environ.get("CLAUDE_PROJECT_DIR") or (data.get("cwd") if isinstance(data.get("cwd"), str) else os.getcwd())
    paths = protected_paths(root) if not reason else []
    if paths:
        texts = [command] if isinstance(command, str) else []
        for key in FILE_KEYS:
            value = args.get(key)
            texts += [v for v in (value if isinstance(value, list) else [value]) if isinstance(v, str)]
        if any(touches_protected(t, paths, root) for t in texts):
            reason = REASONS["protected"]
    if not reason:
        return 0
    if copilot:
        sys.stdout.write(json.dumps({"permissionDecision": "deny",
                                     "permissionDecisionReason": f"{PREFIX}: {reason}"}, ensure_ascii=False) + "\n")
        return 0
    sys.stderr.write(f"{PREFIX}: {reason}\n")
    return 2


if __name__ == "__main__":
    sys.exit(main())
