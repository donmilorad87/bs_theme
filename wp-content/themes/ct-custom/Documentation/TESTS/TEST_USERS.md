# Test Guide for Developers

Practical guide for running, writing, and debugging tests in the ct-custom theme.

---

## Quick Start

### Run All Tests

```bash
# PHP tests (from theme root)
cd wp-content/themes/ct-custom
vendor/bin/phpunit

# JS tests (from vite directory)
cd wp-content/themes/ct-custom/assets/frontend/vite
npm test
```

### Run Specific Tests

```bash
# PHP: single file
vendor/bin/phpunit --filter LoginEndpointTest

# PHP: single test method
vendor/bin/phpunit --filter "test_successful_login"

# PHP: auth suite only
vendor/bin/phpunit --testsuite auth

# JS: single file
npx vitest run src/js/auth-page-flows.test.js

# JS: watch mode (re-runs on file changes)
npm run test:watch
```

---

## Adding a New PHP Endpoint Test

### Step 1: Create the Test File

Create `tests/auth/MyNewEndpointTest.php`:

```php
<?php
namespace CTCustom\Tests\Auth;

class MyNewEndpointTest extends AuthTestCase {

    public function test_successful_request(): void {
        // 1. Create a test user (optional)
        $user = $this->createTestUser([
            'user_login' => 'testuser',
            'user_email' => 'test@example.com',
        ]);

        // 2. Build the request
        $request = $this->makeRequest([
            'param1' => 'value1',
            'param2' => 'value2',
        ]);

        // 3. Optionally authenticate
        $this->loginAsUser($user->ID);

        // 4. Create and call the endpoint handler
        $endpoint = new \CTCustom\Auth\Endpoints\MyEndpoint();
        $response = $endpoint->handle($request);

        // 5. Assert the response
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(200, $response->get_status());
    }
}
```

### Step 2: Key Helpers Available in AuthTestCase

| Helper | Purpose |
|--------|---------|
| `$this->makeRequest($params, $method, $headers)` | Create a `WP_REST_Request` |
| `$this->createTestUser($overrides)` | Insert user into in-memory store |
| `$this->loginAsUser($userId)` | Set current user for JWT-protected endpoints |
| `$this->generateToken($userId)` | Create a valid JWT for Authorization header |
| `$this->generateResetToken($email)` | Create a reset-purpose JWT |
| `$this->setJwtConfig($secret, $expiry)` | Configure JWT options |

### Step 3: Test Rate Limiting

```php
public function test_rate_limited_returns_429(): void {
    // Pre-seed the rate limit counter
    $key = 'ct_rate_my_endpoint_127.0.0.1';
    set_transient($key, 5, 900); // 5 attempts

    $request = $this->makeRequest(['param1' => 'value1']);
    $endpoint = new \CTCustom\Auth\Endpoints\MyEndpoint();
    $response = $endpoint->handle($request);

    $this->assertSame(429, $response->get_status());
}
```

### Step 4: Test JWT-Protected Endpoints

```php
public function test_requires_auth(): void {
    $user = $this->createTestUser();
    $token = $this->generateToken($user->ID);

    $request = $this->makeRequest(['data' => 'value']);
    $request->set_header('Authorization', 'Bearer ' . $token);

    $this->loginAsUser($user->ID);

    $endpoint = new \CTCustom\Auth\Endpoints\MyEndpoint();
    $response = $endpoint->handle($request);

    $this->assertTrue($response->get_data()['success']);
}
```

---

## Adding a New JS Unit Test

### Step 1: Create the Test File

Create `src/js/auth/my-module.test.js`:

```js
import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock fetch if the module uses it
vi.stubGlobal('fetch', vi.fn());

import MyModule from './my-module.js';

describe('MyModule', () => {
    beforeEach(() => {
        fetch.mockReset();
    });

    it('does something', () => {
        const result = MyModule.doSomething();
        expect(result).toBe('expected');
    });
});
```

### Step 2: Testing with DOM

```js
beforeEach(() => {
    document.body.innerHTML = `
        <div class="my-container">
            <input name="field1" value="">
            <button class="submit-btn">Submit</button>
        </div>
    `;
});

it('handles user input', () => {
    const input = document.querySelector('input[name="field1"]');
    input.value = 'test value';
    input.dispatchEvent(new Event('input', { bubbles: true }));

    // Assert validation state, button state, etc.
});
```

### Step 3: Testing Async Operations (fetch)

```js
it('handles API response', async () => {
    fetch.mockImplementation(() => Promise.resolve({
        status: 200,
        json: () => Promise.resolve({ success: true, data: { name: 'Test' } }),
    }));

    // Trigger the action that calls fetch
    myModule.submitForm();

    // Flush the promise chain
    for (let i = 0; i < 10; i++) {
        await Promise.resolve();
    }

    // Assert results
    expect(fetch).toHaveBeenCalledTimes(1);
});
```

---

## Adding a New JS Integration Flow Test

### Step 1: Build a DOM Fixture

Mirror the actual PHP template HTML. Include all `data-ct-validate-*` attributes:

```js
function getFixtureHTML() {
    return `
    <div class="ct-auth-card"
         data-rest-url="http://test.local/wp-json/ct/v1/"
         data-nonce="test-nonce"
         data-cache-version="1"
         data-home-url="/">
        <!-- Panels matching actual PHP template output -->
    </div>`;
}
```

### Step 2: Mock window.location

```js
let locationMock;

beforeEach(() => {
    locationMock = {
        href: '',
        search: '',
        hash: '',
        pathname: '/login-register/',
        reload: vi.fn(),
    };
    delete window.location;
    window.location = locationMock;
});

afterEach(() => {
    vi.useRealTimers();  // Always clean up fake timers
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});
```

### Step 3: Test with Fake Timers

For tests involving `setTimeout` (redirects after delays):

```js
it('redirects after 1500ms', async () => {
    vi.useFakeTimers();

    // ... trigger action ...

    // Flush promise chain (works with fake timers)
    for (let i = 0; i < 10; i++) {
        await Promise.resolve();
    }

    // Check intermediate state
    expect(getMessages(panel)[0]).toContain('Success');

    // Advance past the setTimeout
    vi.advanceTimersByTime(1500);

    // Check redirect
    expect(locationMock.href).toBe('/expected-path/');

    vi.useRealTimers();
});
```

---

## Test Conventions

### Naming

- **PHP**: `test_<behavior_description>` (snake_case)
- **JS**: `'<number>. <Readable description>'` for flow tests, plain descriptions for unit tests

### Structure

Every test follows Arrange-Act-Assert:

1. **Arrange**: Set up DOM, mock fetch, create test data
2. **Act**: Fill inputs, click buttons, trigger events
3. **Assert**: Check DOM state, fetch calls, localStorage, location

### Assertions

- **PHP**: `$this->assertSame()` for strict type comparison, `$this->assertTrue()`, `$this->assertStringContainsString()`
- **JS**: `expect(x).toBe(y)` for strict equality, `expect(x).toContain(y)` for substring, `expect(x).toHaveBeenCalledTimes(n)` for mock calls

### Flow Test Helpers

Each flow test file defines local utilities:

| Helper | Purpose |
|--------|---------|
| `mockFetchResponses(responseMap)` | Configure fetch to return specific responses by URL substring |
| `fillInput(container, name, value)` | Set input value and dispatch `input` event |
| `getMessages(panel)` | Read all `.ct-auth-form__message` texts from a panel |
| `pressEnter(form)` | Dispatch Enter keydown on first visible input |
| `clickAction(card, action)` | Click element with `data-ct-auth-action` attribute |
| `flushPromises()` | Flush microtask queue via `await Promise.resolve()` loop |

---

## Troubleshooting

### PHP: `sendmail` stderr warnings

These come from PHPMailer trying to fall back to `sendmail` when SMTP is unavailable. They are harmless and do not affect test results. The `MailService::send()` method returns `false` gracefully.

### JS: `scrollIntoView is not a function`

jsdom does not implement `scrollIntoView`. Add at the top of your test file:

```js
Element.prototype.scrollIntoView = vi.fn();
```

### JS: `Not implemented: navigation`

jsdom throws when `window.location.href` or `window.location.reload()` is called. Mock the location object:

```js
delete window.location;
window.location = { href: '', search: '', hash: '', pathname: '/', reload: vi.fn() };
```

### JS: Tests timeout with fake timers

If a test uses `vi.useFakeTimers()`, always add `vi.useRealTimers()` in `afterEach`. Use `await Promise.resolve()` loop instead of `setTimeout` for promise flushing, since `setTimeout` is captured by fake timers.

### JS: Click on disabled button does nothing

In jsdom, `.click()` on a disabled button may not fire the click event. If testing defense-in-depth validation (e.g., same password error when button should be disabled), enable the button before clicking:

```js
const btn = container.querySelector('[data-ct-auth-action="change-password"]');
btn.disabled = false;
btn.click();
```

### PHP: Autoloading issues

Ensure `composer dump-autoload` has been run. The `composer.json` PSR-4 autoload maps `CTCustom\\` to `inc/`.

### PHP: Test isolation failures

If a test depends on state from a previous test, ensure `AuthTestCase::setUp()` is being called (it resets all globals). Never use `static` properties for test state.
