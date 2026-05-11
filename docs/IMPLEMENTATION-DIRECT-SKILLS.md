# Direct Skills — Umsetzungsreihenfolge

1. **Config + Lock + Hash** — `extra.ai-agent-skills`, `composer.skills.lock`, `content-hash` (ohne `trust`).
2. **Resolver + Fetch** — GitHub-Kurzform, Basis-URLs, lokaler Pfad; `git clone` / Verzeichnis lesen.
3. **Installer + Checksumme** — Materialisierung unter `vendor/agent-skills/installed/`.
4. **Discovery + Gate** — installierte Direct Skills als Zeilen mit Trust-Key `direct:{source}/{skill}`.
5. **CLI** — Command `skills` + Alias `skills:*`; Unterbefehle `add`, `install`, `update`, `remove`, `list`.
6. **Lifecycle** — `POST_INSTALL_CMD` → install aus Lock; `POST_UPDATE_CMD` → update; Sync wirft bei Lock-Fehler; AGENTS.md bleibt im breiten `catch`.

`COMPOSER_AGENT_SKILLS=0` schaltet Direct-Skill-Sync und die Unterscheidung install/update ab (nur Legacy-AGENTS-Pfad).

## Tests

- **Offline / schnell:** `tests/Unit/*`, `tests/Integration/DirectSkillsCoordinatorTest.php` (nur lokale Pfade).
- **Netzwerk (`@group network`):** `tests/Integration/DirectSkillsNetworkTest.php` — echtes `git clone` von `github.com` (u. a. `vercel-labs/skills`), plus Fehlerfälle (stale/missing Lock, ungültiger Ref, nicht erreichbares Repo, fehlende `--skill`).
  - Überspringen: `SKIP_NETWORK_TESTS=1 vendor/bin/phpunit`
  - In CI optional per Repository-Variable `SKIP_NETWORK_TESTS=1` (siehe Workflow `SKIP_NETWORK_TESTS: ${{ vars.SKIP_NETWORK_TESTS }}`).
