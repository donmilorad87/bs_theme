/**
 * Profile Page Flow Integration Tests
 *
 * Simulates complete user interaction sequences on the profile page:
 * tab switching, save profile, change password, logout, and messages loading.
 *
 * @package CT_Custom
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

/* ------------------------------------------------------------------ */
/*  Global mocks (before any imports that use them)                    */
/* ------------------------------------------------------------------ */

vi.stubGlobal('fetch', vi.fn());

/* jsdom workaround: scrollIntoView is not implemented */
Element.prototype.scrollIntoView = vi.fn();

import ProfilePage from './profile-page.js';

/* ------------------------------------------------------------------ */
/*  Shared test utilities                                              */
/* ------------------------------------------------------------------ */

function mockFetchResponses(responseMap) {
    fetch.mockImplementation((url) => {
        const keys = Object.keys(responseMap);
        for (let i = 0; i < keys.length; i++) {
            if (url.indexOf(keys[i]) !== -1) {
                const entry = responseMap[keys[i]];
                return Promise.resolve({
                    status: entry.status || 200,
                    json: () => Promise.resolve(entry.body),
                });
            }
        }
        return Promise.resolve({
            status: 200,
            json: () => Promise.resolve({ success: true }),
        });
    });
}

function fillInput(container, name, value) {
    const input = container.querySelector('input[name="' + name + '"]');
    if (!input) { return; }
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function getMessages(container) {
    const msgs = container.querySelectorAll('.ct-auth-form__message');
    const result = [];
    for (let i = 0; i < msgs.length; i++) {
        result.push(msgs[i].textContent);
    }
    return result;
}

function pressEnter(form) {
    const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
    const firstInput = form.querySelector('input:not([type="hidden"]):not([readonly])');
    if (firstInput) {
        firstInput.dispatchEvent(event);
    }
}

function clickAction(card, action) {
    const btn = card.querySelector('[data-ct-auth-action="' + action + '"]');
    if (btn) { btn.click(); }
}

async function flushPromises() {
    for (let i = 0; i < 10; i++) {
        await Promise.resolve();
    }
}

const STRONG_PASS = 'Test1234!';
const STRONG_PASS_ALT = 'Other5678@';

/* ------------------------------------------------------------------ */
/*  DOM fixture                                                        */
/* ------------------------------------------------------------------ */

function getProfileCardHTML() {
    return `
    <div class="ct-auth-links">
        <span class="ct-auth-links__greeting">John Doe</span>
    </div>
    <div class="ct-profile-card"
         data-rest-url="http://test.local/wp-json/ct/v1/"
         data-nonce="test-nonce-456"
         data-cache-version="1"
         data-auth-url="/login-register/">

        <div class="ct-auth-form ct-auth-form--profile" role="form">
            <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>

            <!-- Tab navigation -->
            <div class="ct-auth-form__tabs df" role="tablist">
                <button type="button" class="ct-auth-form__tab ct-auth-form__tab--active fs14" data-ct-profile-tab="profile" role="tab" aria-selected="true">Profile</button>
                <button type="button" class="ct-auth-form__tab fs14" data-ct-profile-tab="messages" role="tab" aria-selected="false">Messages</button>
            </div>

            <!-- Profile tab panel -->
            <div class="ct-auth-form__tab-panel ct-auth-form__tab-panel--active" data-ct-profile-panel="profile" role="tabpanel">
                <div class="ct-auth-form__fields">
                    <div class="ct-auth-form__field">
                        <input type="email" class="ct-auth-form__input ct-auth-form__input--readonly fs14" value="user@test.com" readonly>
                    </div>
                    <div class="ct-auth-form__field">
                        <input type="text" class="ct-auth-form__input ct-auth-form__input--readonly fs14" value="testuser" readonly>
                    </div>
                    <div class="ct-auth-form__field">
                        <input type="text" class="ct-auth-form__input fs14" name="first_name" value="John"
                               data-ct-validate-required="true">
                    </div>
                    <div class="ct-auth-form__field">
                        <input type="text" class="ct-auth-form__input fs14" name="last_name" value="Doe"
                               data-ct-validate-required="true">
                    </div>
                </div>
                <button type="button" class="ct-auth-form__submit" data-ct-auth-action="save-profile">Save Profile</button>

                <hr class="ct-auth-form__divider">
                <div class="ct-auth-form__change-password-section" data-ct-password-section>
                    <h4 class="ct-auth-form__section-title">Change Password</h4>
                    <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>
                    <div class="ct-auth-form__fields">
                        <div class="ct-auth-form__field">
                            <div class="ct-auth-form__password-wrap">
                                <input type="password" class="ct-auth-form__input fs14" name="current_password"
                                       data-ct-validate-password="true">
                                <button type="button" class="ct-auth-form__password-toggle"></button>
                            </div>
                            <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                                <div class="ct-auth-form__rule df aic" data-rule="min-length">At least 8 characters</div>
                                <div class="ct-auth-form__rule df aic" data-rule="lowercase">One lowercase letter</div>
                                <div class="ct-auth-form__rule df aic" data-rule="uppercase">One uppercase letter</div>
                                <div class="ct-auth-form__rule df aic" data-rule="digit">One digit</div>
                                <div class="ct-auth-form__rule df aic" data-rule="special">One special character</div>
                            </div>
                        </div>
                        <div class="ct-auth-form__field">
                            <div class="ct-auth-form__password-wrap">
                                <input type="password" class="ct-auth-form__input fs14" name="new_password"
                                       data-ct-validate-password="true">
                                <button type="button" class="ct-auth-form__password-toggle"></button>
                            </div>
                            <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                                <div class="ct-auth-form__rule df aic" data-rule="min-length">At least 8 characters</div>
                                <div class="ct-auth-form__rule df aic" data-rule="lowercase">One lowercase letter</div>
                                <div class="ct-auth-form__rule df aic" data-rule="uppercase">One uppercase letter</div>
                                <div class="ct-auth-form__rule df aic" data-rule="digit">One digit</div>
                                <div class="ct-auth-form__rule df aic" data-rule="special">One special character</div>
                                <div class="ct-auth-form__rule df aic" data-rule="different" data-rule-compare="current_password">
                                    <span class="ct-auth-form__rule-default">Must be different from current password</span>
                                    <span class="ct-auth-form__rule-info">This is your current password</span>
                                </div>
                            </div>
                        </div>
                        <div class="ct-auth-form__field">
                            <div class="ct-auth-form__password-wrap">
                                <input type="password" class="ct-auth-form__input fs14" name="new_password_confirm"
                                       data-ct-validate-match="new_password">
                                <button type="button" class="ct-auth-form__password-toggle"></button>
                            </div>
                            <div class="ct-auth-form__match-hint ct-auth-form__match-hint--hidden" aria-live="polite">
                                <div class="ct-auth-form__rule ct-auth-form__match-hint-pass df aic">Passwords match</div>
                                <div class="ct-auth-form__rule ct-auth-form__match-hint-fail df aic">Passwords do not match</div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="ct-auth-form__submit ct-auth-form__submit--secondary ct-auth-form__submit--disabled" data-ct-auth-action="change-password" disabled>Change Password</button>
                </div>
            </div>

            <!-- Messages tab panel -->
            <div class="ct-auth-form__tab-panel" data-ct-profile-panel="messages" role="tabpanel" style="display:none;">
                <div class="ct-auth-form__messages-history" id="ct_profile_messages">
                    <p class="ct-auth-form__loading fs14">Loading messages...</p>
                </div>
            </div>

            <!-- Logout action (outside tabs, always visible) -->
            <button type="button" data-ct-auth-action="logout">Logout</button>
        </div>
    </div>`;
}

/* ------------------------------------------------------------------ */
/*  Setup / Teardown                                                   */
/* ------------------------------------------------------------------ */

let locationMock;

beforeEach(() => {
    fetch.mockReset();
    localStorage.clear();
    localStorage.setItem('ct_auth_token', 'existing-jwt-token');

    locationMock = {
        href: 'http://test.local/profile/',
        search: '',
        hash: '',
        pathname: '/profile/',
        reload: vi.fn(),
    };
    delete window.location;
    window.location = locationMock;
});

afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

/* ================================================================== */
/*  Group F: Profile Tab Switching                                     */
/* ================================================================== */

describe('Profile Page Flows — Tab Switching', () => {

    it('31. Profile tab active by default, messages panel hidden', () => {
        document.body.innerHTML = getProfileCardHTML();
        const page = new ProfilePage();

        const profilePanel = document.querySelector('[data-ct-profile-panel="profile"]');
        const messagesPanel = document.querySelector('[data-ct-profile-panel="messages"]');

        expect(profilePanel.classList.contains('ct-auth-form__tab-panel--active')).toBe(true);
        expect(messagesPanel.style.display).toBe('none');
    });

    it('32. Clicking messages tab shows messages panel, hides profile', () => {
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            'user-messages': { status: 200, body: { success: true, data: { messages: [] } } },
        });
        const page = new ProfilePage();

        const messagesTab = document.querySelector('[data-ct-profile-tab="messages"]');
        messagesTab.click();

        const profilePanel = document.querySelector('[data-ct-profile-panel="profile"]');
        const messagesPanel = document.querySelector('[data-ct-profile-panel="messages"]');

        expect(profilePanel.classList.contains('ct-auth-form__tab-panel--active')).toBe(false);
        expect(profilePanel.style.display).toBe('none');
        expect(messagesPanel.classList.contains('ct-auth-form__tab-panel--active')).toBe(true);
        expect(messagesPanel.style.display).toBe('');
    });

    it('33. Messages tab lazy-loads on first activation only (single fetch call)', async () => {
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            'user-messages': { status: 200, body: { success: true, data: { messages: [] } } },
        });
        const page = new ProfilePage();

        const messagesTab = document.querySelector('[data-ct-profile-tab="messages"]');
        const profileTab = document.querySelector('[data-ct-profile-tab="profile"]');

        /* First click loads messages */
        messagesTab.click();
        await flushPromises();

        const fetchCount1 = fetch.mock.calls.filter((c) => c[0].indexOf('user-messages') !== -1).length;
        expect(fetchCount1).toBe(1);

        /* Switch away and back */
        profileTab.click();
        messagesTab.click();
        await flushPromises();

        const fetchCount2 = fetch.mock.calls.filter((c) => c[0].indexOf('user-messages') !== -1).length;
        expect(fetchCount2).toBe(1);
    });
});

/* ================================================================== */
/*  Group G: Save Profile                                              */
/* ================================================================== */

describe('Profile Page Flows — Save Profile', () => {

    it('34. Save sends first_name/last_name to API with JWT auth', async () => {
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            'profile/update': { status: 200, body: { success: true, message: 'Profile updated.', data: { display_name: 'Jane Smith' } } },
        });
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');

        fillInput(card, 'first_name', 'Jane');
        fillInput(card, 'last_name', 'Smith');
        clickAction(card, 'save-profile');

        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(1);

        const callOpts = fetch.mock.calls[0][1];
        expect(callOpts.headers['Authorization']).toBe('Bearer existing-jwt-token');

        const body = JSON.parse(callOpts.body);
        expect(body.first_name).toBe('Jane');
        expect(body.last_name).toBe('Smith');
    });

    it('35. Success updates header greeting display name', async () => {
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            'profile/update': { status: 200, body: { success: true, data: { display_name: 'Updated Name' } } },
        });
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');

        fillInput(card, 'first_name', 'Updated');
        fillInput(card, 'last_name', 'Name');
        clickAction(card, 'save-profile');

        await flushPromises();

        const greeting = document.querySelector('.ct-auth-links__greeting');
        expect(greeting.textContent).toBe('Updated Name');
    });

    it('36. Shows error when name fields empty', async () => {
        document.body.innerHTML = getProfileCardHTML();
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');

        fillInput(card, 'first_name', '');
        fillInput(card, 'last_name', '');
        clickAction(card, 'save-profile');

        await flushPromises();

        /* Should NOT have called fetch */
        expect(fetch).not.toHaveBeenCalled();

        const msgs = getMessages(card);
        expect(msgs.length).toBe(1);
        expect(msgs[0]).toContain('name fields');
    });
});

/* ================================================================== */
/*  Group H: Change Password                                           */
/* ================================================================== */

describe('Profile Page Flows — Change Password', () => {

    it('37. Change password button starts disabled', () => {
        document.body.innerHTML = getProfileCardHTML();
        const page = new ProfilePage();
        const btn = document.querySelector('[data-ct-auth-action="change-password"]');

        expect(btn.disabled).toBe(true);
        expect(btn.classList.contains('ct-auth-form__submit--disabled')).toBe(true);
    });

    it('38. Button enables when all 3 password fields valid + new differs from current', () => {
        document.body.innerHTML = getProfileCardHTML();
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');
        const btn = card.querySelector('[data-ct-auth-action="change-password"]');

        fillInput(card, 'current_password', STRONG_PASS);
        fillInput(card, 'new_password', STRONG_PASS_ALT);
        fillInput(card, 'new_password_confirm', STRONG_PASS_ALT);

        expect(btn.disabled).toBe(false);
    });

    it('39. Same current/new password shows error', async () => {
        document.body.innerHTML = getProfileCardHTML();
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');

        fillInput(card, 'current_password', STRONG_PASS);
        fillInput(card, 'new_password', STRONG_PASS);
        fillInput(card, 'new_password_confirm', STRONG_PASS);

        /* Button stays disabled because "different" rule fails.
           Enable it to simulate the defense-in-depth check in changePassword(). */
        const btn = card.querySelector('[data-ct-auth-action="change-password"]');
        btn.disabled = false;
        btn.click();

        await flushPromises();

        expect(fetch).not.toHaveBeenCalled();

        const section = card.querySelector('[data-ct-password-section]');
        const msgs = getMessages(section);
        expect(msgs.length).toBe(1);
        expect(msgs[0]).toContain('different');
    });

    it('40. Successful change clears token + redirects to auth URL after 1500ms', async () => {
        vi.useFakeTimers();
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            'profile/change-password': { status: 200, body: { success: true, message: 'Password changed.' } },
        });
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');

        fillInput(card, 'current_password', STRONG_PASS);
        fillInput(card, 'new_password', STRONG_PASS_ALT);
        fillInput(card, 'new_password_confirm', STRONG_PASS_ALT);
        clickAction(card, 'change-password');

        await flushPromises();

        expect(localStorage.getItem('ct_auth_token')).toBeNull();

        vi.advanceTimersByTime(1500);

        expect(locationMock.href).toBe('/login-register/');

        vi.useRealTimers();
    });

    it('41. Enter keypress submits when enabled, prevented when disabled', async () => {
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            'profile/change-password': { status: 200, body: { success: true, message: 'Password changed.' } },
        });
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');
        const section = card.querySelector('[data-ct-password-section]');

        /* Disabled state — Enter should NOT trigger submit */
        pressEnter(section);
        await flushPromises();
        expect(fetch).not.toHaveBeenCalled();

        /* Fill valid fields — Enter should submit */
        fillInput(card, 'current_password', STRONG_PASS);
        fillInput(card, 'new_password', STRONG_PASS_ALT);
        fillInput(card, 'new_password_confirm', STRONG_PASS_ALT);
        pressEnter(section);
        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch.mock.calls[0][0]).toContain('change-password');
    });
});

/* ================================================================== */
/*  Group I: Profile Logout                                            */
/* ================================================================== */

describe('Profile Page Flows — Logout', () => {

    it('42. Logout clears token from localStorage', async () => {
        document.body.innerHTML = getProfileCardHTML();
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');

        expect(localStorage.getItem('ct_auth_token')).toBe('existing-jwt-token');

        mockFetchResponses({
            logout: { status: 200, body: { success: true } },
        });

        clickAction(card, 'logout');

        expect(localStorage.getItem('ct_auth_token')).toBeNull();
    });

    it('43. Logout calls POST to logout endpoint', async () => {
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            logout: { status: 200, body: { success: true } },
        });
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');

        clickAction(card, 'logout');
        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(1);
        const callUrl = fetch.mock.calls[0][0];
        expect(callUrl).toContain('logout');
        expect(fetch.mock.calls[0][1].method).toBe('POST');
    });

    it('44. Logout reloads page (success or failure)', async () => {
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            logout: { status: 200, body: { success: true } },
        });
        const page = new ProfilePage();
        const card = document.querySelector('.ct-profile-card');

        clickAction(card, 'logout');
        await flushPromises();

        expect(locationMock.reload).toHaveBeenCalled();
    });
});

/* ================================================================== */
/*  Group J: Messages Loading                                          */
/* ================================================================== */

describe('Profile Page Flows — Messages Loading', () => {

    it('45. Messages with data renders message cards', async () => {
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            'user-messages': {
                status: 200,
                body: {
                    success: true,
                    data: {
                        messages: [
                            {
                                date: '2025-01-15T12:00:00Z',
                                pointer: 'Support Ticket #42',
                                body: 'Hello, I need help with my order.',
                                replies: [
                                    { author_name: 'Admin', date: '2025-01-16T08:00:00Z', body: 'We will look into this.' },
                                ],
                            },
                        ],
                    },
                },
            },
        });
        const page = new ProfilePage();
        const messagesTab = document.querySelector('[data-ct-profile-tab="messages"]');
        messagesTab.click();

        await flushPromises();

        const messagesEl = document.querySelector('#ct_profile_messages');
        const cards = messagesEl.querySelectorAll('.ct-auth-form__message-card');
        expect(cards.length).toBe(1);

        const bodyText = cards[0].querySelector('.ct-auth-form__message-card-body').textContent;
        expect(bodyText).toContain('help with my order');

        const replies = cards[0].querySelectorAll('.ct-auth-form__message-reply');
        expect(replies.length).toBe(1);
    });

    it('46. API error shows fallback message', async () => {
        document.body.innerHTML = getProfileCardHTML();
        fetch.mockImplementation(() => Promise.reject(new Error('Server down')));

        const page = new ProfilePage();
        const messagesTab = document.querySelector('[data-ct-profile-tab="messages"]');
        messagesTab.click();

        await flushPromises();

        const messagesEl = document.querySelector('#ct_profile_messages');
        expect(messagesEl.textContent).toContain('Could not load messages');
    });

    it('47. Message HTML is escaped (XSS prevention)', async () => {
        document.body.innerHTML = getProfileCardHTML();
        mockFetchResponses({
            'user-messages': {
                status: 200,
                body: {
                    success: true,
                    data: {
                        messages: [
                            {
                                date: '2025-01-15T12:00:00Z',
                                pointer: '<script>alert("xss")</script>',
                                body: '<img onerror="alert(1)" src="x">',
                                replies: [],
                            },
                        ],
                    },
                },
            },
        });
        const page = new ProfilePage();
        const messagesTab = document.querySelector('[data-ct-profile-tab="messages"]');
        messagesTab.click();

        await flushPromises();

        const messagesEl = document.querySelector('#ct_profile_messages');
        const html = messagesEl.innerHTML;

        /* Script/img tags must be escaped, not rendered as real elements */
        expect(html).not.toContain('<script>');
        expect(html).not.toContain('<img');
        expect(html).toContain('&lt;script&gt;');
    });
});
