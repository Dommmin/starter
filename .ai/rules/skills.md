---
paths:
  - '.claude/skills/**'
  - '.agents/skills/**'
  - 'composer.json'
---

# Skills

## Composer/Boost update overwrites project skill constraints
`composer require/update` runs Boost and regenerates `.claude/skills/*` and `.agents/skills/*` (e.g. tailwindcss-development), dropping the project-specific ADR-019 constraint block. After any composer change run `git diff .claude/skills .agents/skills` and restore those hunks with `git checkout --` before committing.

## Boost `record-rule` przepisuje indeks
Narzędzie `record-rule` nadpisuje `.ai/rules/index.md` własnym, niepełnym formatem i usuwa istniejące mapowania. Po jego użyciu przywróć indeks (`git checkout -- .ai/rules/index.md`) i dopisz wiersz ręcznie; `tests/ai` wykrywa uszkodzony indeks.
