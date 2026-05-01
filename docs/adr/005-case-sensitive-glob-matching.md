# ADR-005: Case-sensitive glob matching for trust patterns

**Status:** Accepted
**Date:** 2026-05-01

## Context

Composer ships [`BasePackage::packageNameToRegexp()`](https://github.com/composer/composer/blob/main/src/Composer/Package/BasePackage.php) for matching package-name patterns. It produces a regex with the `/i` flag — case-insensitive matching is built in.

The first revision of `SkillTrustManager::matchPattern()` used this helper. Security review caught the consequence: `acme/*: true` also matches `Acme/Evil`, `ACME/EVIL`, and any case variation. Composer normalizes published package names to lowercase, but private repositories may not, and a malicious package author can publish under a name that matches common glob shapes case-insensitively.

There's also the question of glob precedence: an exact key persisted *after* a broader glob was being silently shadowed because `matchPattern` returned on the first iteration hit.

## Decision

1. **Build our own regex.** `SkillTrustManager::patternToRegex()` produces `'{^' . preg_quote(pattern) . '$}'` with `*` replaced by `.*`. **No `/i` flag.** Trust matching is exact-case.
2. **Two-pass matching.** Exact-string keys win over glob patterns regardless of insertion order. `matchPattern` checks `array_key_exists($packageName, $rules)` first; glob iteration is the fallback.
3. **Validate persisted patterns.** `TrustSkillCommand` rejects uppercase and special-char inputs at the CLI boundary so a malformed pattern can't end up in the map and break `preg_match` for every subsequent query.

## Consequences

**Positive**
- `acme/*: true` does not match `Acme/Evil`. Security closure for the private-repo case.
- Explicit per-package decisions can override glob defaults regardless of order — no subtle "depends on whether you ran skills:trust before or after editing your glob".
- Patterns that round-trip through `composer skills:trust` are guaranteed to be valid Composer-style names.

**Negative**
- Diverges from Composer's `packageNameToRegexp` semantics. Users familiar with `allow-plugins` might expect case-insensitive matching.
- Custom regex builder needs documenting (see [README's Trust Model](../../README.md#trust-model) and the inline docblock).

## Alternatives considered

- **Use `BasePackage::packageNameToRegexp` and lowercase candidates.** Lowercasing the candidate before matching closes the security gap — but Composer might change `packageNameToRegexp` semantics in a future version, and we'd inherit the change silently. Owning our regex builder pins the contract.
- **Wrap the regex with `(?-i)` to disable the inline `i` flag.** Possible but fragile — depends on Composer's exact regex format never changing.
- **Use `fnmatch()`.** Suggested in review. Rejected because `fnmatch`'s semantics are platform-defined (BSD vs GNU vs macOS) and depend on the system C library. Our regex is portable across PHP installations.
- **Accept the broad-match risk and document it.** Rejected — security review flagged it as MEDIUM, and the fix is small.
