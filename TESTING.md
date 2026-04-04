# Testing Guide — Next Level FAQ Plugin

This document explains how to install dependencies, configure the environment, and run the full test suite (PHPUnit unit tests + Playwright E2E tests).

---

## Prerequisites

| Tool | Minimum Version | Purpose |
|------|----------------|---------|
| **PHP** | 7.4+ | Plugin runtime & PHPUnit |
| **Composer** | 2.x | PHP dependency manager |
| **Node.js** | 18+ | Playwright & build tools |
| **npm** | 9+ | Node package manager |
| **WordPress** | 6.0+ | Running local WP instance (for E2E tests) |

---

## 1. Install Dependencies

### PHP dependencies (PHPUnit, Brain Monkey, Mockery)

```bash
composer install
```

This installs:
- `phpunit/phpunit` ^9.6 — test runner
- `brain/monkey` ^2.6 — WordPress function mocking
- `mockery/mockery` ^1.5 — object mocking
- `yoast/phpunit-polyfills` ^2.0 — PHPUnit 9.x compatibility

### Node.js dependencies (Playwright, build tools)

```bash
npm install
```

### Playwright browsers

After `npm install`, you need to download the browser binaries:

```bash
npx playwright install
```

This downloads Chromium (and optionally Firefox/WebKit) for E2E testing.

---

## 2. Environment Configuration

### `.env` file

Create a `.env` file in the plugin root with your local WordPress credentials:

```env
WP_BASE_URL=http://wp.local
WP_ADMIN_USER=root
WP_ADMIN_PASS=root
```

| Variable | Description | Default |
|----------|-------------|---------|
| `WP_BASE_URL` | URL of your local WordPress site | `http://wp.local` |
| `WP_ADMIN_USER` | WordPress admin username | `admin` |
| `WP_ADMIN_PASS` | WordPress admin password | `admin` |

> **Important:** Your local WordPress site must be running and accessible at `WP_BASE_URL` before running E2E tests.

---

## 3. Running Tests

### Run everything (recommended)

```bash
npm test
```

This runs PHPUnit first, then Playwright. If PHPUnit fails, Playwright will not run.

### Run only PHPUnit (unit tests)

```bash
npm run test:unit
```

Or directly:

```bash
vendor/bin/phpunit
```

**What it tests:** PHP classes in isolation — no database, no WordPress, no browser needed.

### Run only Playwright (E2E tests)

```bash
npm run test:e2e
```

**What it tests:** Real browser interactions against your running WordPress site.

### Run E2E with visible browser (debugging)

```bash
npm run test:e2e:headed
```

### Run E2E with Playwright UI (interactive debugging)

```bash
npm run test:e2e:ui
```

### Run a specific test file

```bash
# PHPUnit — single file
vendor/bin/phpunit tests/Unit/Core/RepositoryTest.php

# Playwright — single file
npx playwright test tests/e2e/admin/faq-groups.spec.js

# Playwright — single test by line number
npx playwright test tests/e2e/admin/faq-groups.spec.js:51
```

### Run tests by folder

```bash
# E2E admin tests only
npx playwright test tests/e2e/admin/

# E2E frontend tests only
npx playwright test tests/e2e/frontend/
```

---

## 4. Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap (defines WP constants, loads autoloader)
├── WpTestCase.php             # Base test class with Brain Monkey stubs
├── MockWpdb.php               # Lightweight $wpdb mock (no real DB needed)
│
├── Unit/                      # PHPUnit unit tests (no WP, no DB)
│   ├── Core/
│   │   ├── PresetsTest.php          # Preset registry, normalize_slug, values
│   │   ├── OptionsTest.php          # Input sanitization (colors, fonts, clamps)
│   │   ├── FunctionsTest.php        # Asset URL/path helpers
│   │   ├── StyleGeneratorTest.php   # CSS generation from options
│   │   ├── SettingsRepositoryTest.php  # DB settings read/write
│   │   ├── CacheTest.php           # Object cache + transient caching
│   │   ├── GroupsRepositoryTest.php # FAQ groups CRUD
│   │   └── RepositoryTest.php      # FAQ items CRUD
│   └── Admin/
│       ├── AdminSettingsTest.php    # Admin hooks registration
│       └── GroupAdminTest.php       # AJAX save handler security
│
└── e2e/                       # Playwright E2E tests (real browser)
    ├── global-setup.js        # Logs in once, saves cookies for all tests
    ├── .auth/                 # Cached login session (gitignored)
    │
    ├── helpers/
    │   └── admin-auth.js      # loginAsAdmin() helper
    │
    ├── admin/
    │   ├── faq-groups.spec.js     # Group list, create, edit, delete
    │   └── faq-settings.spec.js   # Style settings, presets, AJAX save, menu
    │
    └── frontend/
        └── faq-accordion.spec.js  # Shortcode rendering, accordion open/close
```

---

## 5. Test Coverage

### Current stats

| Layer | Tests | Status |
|-------|-------|--------|
| **PHPUnit** | 181 tests, 478 assertions | All passing |
| **E2E Admin** | 17 tests | All passing |
| **E2E Frontend** | 6 tests (2 skipped*) | All passing |
| **Total** | 204 tests | All passing |

*Search tests are skipped when the search feature is not enabled on the test group.

### Generate coverage report (requires PCOV or Xdebug)

```bash
composer test:coverage
```

Or with HTML output:

```bash
vendor/bin/phpunit --coverage-html coverage-report
```

---

## 6. How Tests Work

### PHPUnit (Unit Tests)

- **No WordPress needed.** Tests use Brain Monkey to mock WP functions like `get_option()`, `sanitize_text_field()`, etc.
- **No database needed.** `MockWpdb` simulates `$wpdb` queries with pre-set return values.
- **Fast.** The full suite runs in ~2 seconds.
- **Config:** `phpunit.xml.dist` at the plugin root.

### Playwright (E2E Tests)

- **Requires a running WordPress site** with the plugin activated.
- **storageState:** The `global-setup.js` file logs in once before all tests and saves the cookies to `tests/e2e/.auth/admin.json`. All tests reuse this session, avoiding repeated logins.
- **Test data cleanup:** Tests that create FAQ groups or pages clean up after themselves using real WordPress nonces (not fake ones).
- **Config:** `playwright.config.js` at the plugin root.

---

## 7. Writing New Tests

### New PHPUnit test

1. Create a file in `tests/Unit/Core/` or `tests/Unit/Admin/` named `*Test.php`.
2. Extend `WpTestCase` (if you need WP function stubs) or `PHPUnit\Framework\TestCase` (pure PHP).
3. Use `MockWpdb` for any code that uses `$wpdb`.
4. Run with `vendor/bin/phpunit tests/Unit/Core/YourTest.php`.

Example:

```php
<?php
namespace Krslys\NextLevelFaq\Tests\Unit\Core;

use Krslys\NextLevelFaq\Tests\WpTestCase;

class MyFeatureTest extends WpTestCase {
    public function test_it_does_something(): void {
        $this->assertTrue( true );
    }
}
```

### New E2E test

1. Create a `.spec.js` file in `tests/e2e/admin/` or `tests/e2e/frontend/`.
2. Use `loginAsAdmin( page, '/wp-admin/...' )` to navigate to admin pages.
3. Clean up any test data you create (groups, pages) in `afterAll`.
4. Run with `npx playwright test tests/e2e/admin/your-test.spec.js`.

Example:

```javascript
'use strict';
const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin } = require( '../helpers/admin-auth' );

test.describe( 'My Feature', () => {
    test( 'page loads', async ( { page } ) => {
        await loginAsAdmin( page, '/wp-admin/admin.php?page=my-page' );
        await expect( page.locator( '#wpwrap' ) ).toBeVisible();
    } );
} );
```

---

## 8. Troubleshooting

### PHPUnit: "Class not found"

Make sure you ran `composer install` and the autoloader is up to date:

```bash
composer dump-autoload
```

### Playwright: "Target page, context or browser has been closed"

Your WordPress site is not running. Start it and verify `WP_BASE_URL` is accessible.

### Playwright: "Timeout waiting for selector"

- Run in headed mode to see what the browser shows: `npm run test:e2e:headed`
- Check the failure screenshots in `test-results/` folder.

### Playwright: Login fails

- Verify your `.env` credentials are correct.
- Delete `tests/e2e/.auth/admin.json` to force a fresh login session.

### E2E: "Object not found" (404) on frontend pages

Your WordPress does not have pretty permalinks enabled. The tests use `?page_id=` format to avoid this issue. If you still get 404s, make sure the plugin is activated.

---

## 9. Available npm Scripts

| Command | What it does |
|---------|-------------|
| `npm test` | Run PHPUnit + Playwright (full suite) |
| `npm run test:unit` | Run PHPUnit only |
| `npm run test:e2e` | Run Playwright only |
| `npm run test:e2e:headed` | Run Playwright with visible browser |
| `npm run test:e2e:ui` | Run Playwright interactive UI |
| `npm run build` | Build frontend assets (webpack) |
| `npm run start` | Watch mode for frontend assets |

## 10. Available Composer Scripts

| Command | What it does |
|---------|-------------|
| `composer test` | Run PHPUnit |
| `composer test:coverage` | Run PHPUnit with text coverage report |
