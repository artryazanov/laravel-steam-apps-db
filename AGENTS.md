# AGENTS.md

This document provides guidance for AI coding agents contributing to this repository. It summarizes how to work safely, run tests, and follow the project’s conventions.

## Repo Overview
- Framework: Laravel (package) using Orchestra Testbench for tests.
- Language: PHP 8.4+.
- Tests: PHPUnit (`vendor/bin/phpunit` or `composer test`).
- Domain: Steam apps database and updaters (jobs fetching details/news) with rate limiting and unique job semantics.

## Quick Start
- Install dependencies: `composer install`.
- Run tests: `composer test` (alias to `vendor/bin/phpunit`).
- Style: Follow existing code style; avoid introducing new tooling or formatting configs.
- Keep changes minimal and scoped to the task. Do not modify unrelated parts of the codebase.

## Coding Guidelines
- Respect existing structure and naming. Prefer small, focused changes.
- No license header changes. Do not alter package metadata unless requested.
- Prefer pure PHP and Laravel-native facilities already used in the repo.
- Add or update tests when changing behavior. Don’t add tests for unrelated issues.
- For jobs/components:
  - `src/Jobs/FetchSteamAppBasicJob.php` implements `ShouldBeUnique` with a `uniqueId()` based on `appid`.
  - Details/news jobs extend the basic job and share uniqueness per `appid`, but uniqueness is scoped by job class.
  - Be mindful of backoff/tries and the `decay_seconds` sleep in `handle()` when simulating rates.

## Testing Notes
- Test base class: `tests/TestCase.php` (uses in-memory sqlite and RefreshDatabase).
- Deterministic time: use `Illuminate\Support\Carbon::setTestNow(...)` in tests requiring time math.
- HTTP calls should be faked via `Http::fake(...)` in tests.
- Queue interactions should use `Bus::fake()` and the Laravel Bus fake assertions.

### Unique Job Locks (ShouldBeUnique)
- Unique locks are acquired at dispatch time (see `Illuminate\Foundation\Bus\PendingDispatch`).
- In tests with the array cache store, `Cache::flush()` does not clear lock state because locks are stored separately.
- If a test dispatches the same unique job multiple times in the same PHP process, clear both cache and array-locks between assertions:
  ```php
  use Illuminate\Support\Facades\Cache;
  
  private function flushCacheAndLocks(): void
  {
      Cache::flush();
      $store = Cache::getStore();
      if (property_exists($store, 'locks')) {
          $store->locks = [];
      }
  }
  ```
- See: `tests/Unit/Actions/ImportSteamAppsActionTest.php` and `tests/Unit/Jobs/FetchSteamAppJobsUniquenessTest.php` for examples.

## Running and Updating Tests
- Prefer adding targeted tests near existing ones. Mirror established patterns:
  - Use factory/ORM APIs (`updateOrCreate`, relations) rather than raw queries.
  - Use `Bus::assertDispatched*` and `Bus::assertNotDispatched*` for queue checks.
- When changing dispatch logic, add/adjust tests to cover edge cases:
  - Unknown/future release dates treated as recent.
  - Thresholds controlled by `config('laravel-steam-apps-db.*')` values.
  - Exact boundary conditions do not dispatch (strictly greater-than comparisons).

## PR Checklist
- Code compiles; unit tests pass locally: `composer test`.
- New behavior is covered with tests.
- No unrelated refactors or formatting churn.
- Updated docs if you changed behavior that users depend on.

## Common Pitfalls
- Forgetting to reset unique job locks between test sub-scenarios can cause false negatives in dispatch assertions.
- Not faking HTTP calls leads to network coupling in tests.
- Relying on wall clock time instead of `Carbon::setTestNow` causes flaky tests.
- Introducing global config changes without resetting can leak state across tests.

## Commands Reference
- Run tests: `vendor/bin/phpunit` or `composer test`.
- Filter tests: `vendor/bin/phpunit --filter TestName`.
- Show verbose output: `vendor/bin/phpunit -v`.

## Contact & Ownership
- Package provider: `LaravelSteamAppsDbServiceProvider`.
- Primary areas:
  - Jobs: `src/Jobs/*`
  - Actions: `src/Actions/*`
  - Services: `src/Services/*`
  - Models: `src/Models/*`
  - Tests: `tests/*`

If you’re an automated agent, apply minimal diffs and include a concise summary of changes in your output. When in doubt, prefer adjusting tests to reflect intended behavior rather than weakening core guarantees like job uniqueness.

