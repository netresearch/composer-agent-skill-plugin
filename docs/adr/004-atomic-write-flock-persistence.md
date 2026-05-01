# ADR-004: Atomic write + flock for trust persistence

**Status:** Accepted
**Date:** 2026-05-01

## Context

The trust manager persists `extra.ai-agent-skill.allow-skills` to the root `composer.json`. Two failure modes were flagged:

1. **Mid-write crash.** A killed process during `file_put_contents` leaves a half-written `composer.json`. Composer's own `JsonConfigSource::addProperty()` uses plain `file_put_contents` and is vulnerable to this.
2. **Concurrent writers.** `composer skills:trust …` invoked alongside `composer install` (or two parallel installs) reads, mutates, writes — and a write between read and write is silently overwritten.

The architectural review suggested migrating persistence to Composer's `JsonConfigSource::addProperty()` for in-memory config sync (`Config::merge()` after write). That route lacks the atomic-write and locking guarantees we need.

## Decision

Keep our own persistence path, layered on Composer's `JsonManipulator` for the structural mutation but adding our own atomic-write and locking on top. Implementation in `TrustStore`:

1. **Lock first.** Acquire `LOCK_EX` on a sidecar `.skill-trust.lock` file via `flock`. If `flock` fails (filesystems without lock support: NFS, certain Docker setups), warn and proceed unlocked rather than refuse the write — refusing would render trust unusable.
2. **Write atomically.** Build new contents in memory, write to a randomly-suffixed temp file (`composer.json.skill-trust.<hex>`), then `rename(temp, composer.json)`. Rename is atomic on the same filesystem.
3. **Don't unlink the lock file** on release. Only unlock + close the handle. Removing the lock file opens a TOCTOU race: another process could create a new inode and lock it while the original is still being held.

We deliberately do **not** call `Config::merge()` after writing. Our hook runs after install/update; nothing in the same process re-reads the value, so the in-memory sync provides no benefit.

## Consequences

**Positive**
- Mid-write crashes leave `composer.json` either fully old or fully new — never half-written.
- Cross-process races between `composer skills:trust` and `composer install` are serialized by `flock`.
- We're stricter than Composer itself for our own writes. Composer benefits transitively (since `composer.json` is *its* file).

**Negative**
- Lock file accumulates in the project directory (a few bytes; never removed). Worth it to close the inode race.
- `flock` semantics differ across filesystems. We warn and degrade rather than fail.
- Adds ~50 LOC and a sidecar collaborator (`TrustStore`) that wouldn't exist if we used `ConfigSource::addProperty`.

## Alternatives considered

- **Use `Composer\Config\JsonConfigSource::addProperty`.** Rejected — no atomic write, no flock. Composer itself writes plain `file_put_contents`. We'd be giving up real protection for in-memory config sync we don't need.
- **Open a lock on `composer.json` itself.** Rejected — Composer reads/writes it through its own code paths that don't lock, so our lock would protect against ourselves but not against Composer.
- **Refuse the write when `flock` fails.** Rejected — would break trust persistence on shared filesystems where flock isn't supported (NFS without `lockd`, some Docker volume mounts), and the consequence of an actual race (rare, narrow window) is recoverable via `composer skills:trust`.
