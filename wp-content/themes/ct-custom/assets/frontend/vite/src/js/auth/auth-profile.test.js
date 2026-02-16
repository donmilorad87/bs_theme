import { describe, it, expect, vi, beforeEach } from 'vitest';
import AuthProfile from './auth-profile.js';

describe('AuthProfile', () => {
    let mockApi;
    let mockShowMessage;
    let mockSetLoading;
    let authProfile;

    beforeEach(() => {
        mockApi = {
            postAuth: vi.fn(() => Promise.resolve({ success: true, message: 'OK', data: { display_name: 'John' } })),
            getAuth: vi.fn(() => Promise.resolve({ success: true, data: { messages: [] } })),
            uploadAuth: vi.fn(() => Promise.resolve({ success: true, message: 'Uploaded', data: { avatar_url: 'http://test.com/img.jpg' } })),
        };
        mockShowMessage = vi.fn();
        mockSetLoading = vi.fn();
        authProfile = new AuthProfile(mockApi, mockShowMessage, mockSetLoading);
    });

    function createProfileContainer() {
        const container = document.createElement('div');
        container.innerHTML = `
            <input name="first_name" value="John" />
            <input name="last_name" value="Doe" />
            <input name="current_password" value="" />
            <input name="new_password" value="" />
            <input name="new_password_confirm" value="" />
            <div data-ct-password-section></div>
            <div id="ct_profile_messages"></div>
            <input type="file" class="ct-auth-form__avatar-file-input" />
            <div class="ct-auth-form__avatar-img"></div>
        `;
        return container;
    }

    it('saveProfile calls postAuth with correct data', async () => {
        const container = createProfileContainer();
        const onSuccess = vi.fn();

        await authProfile.saveProfile(container, onSuccess);

        expect(mockApi.postAuth).toHaveBeenCalledWith('profile/update', {
            first_name: 'John',
            last_name: 'Doe'
        });
    });

    it('saveProfile shows error when first_name is empty', async () => {
        const container = createProfileContainer();
        const firstNameInput = container.querySelector('input[name="first_name"]');
        firstNameInput.value = '';
        const onSuccess = vi.fn();

        await authProfile.saveProfile(container, onSuccess);

        expect(mockShowMessage).toHaveBeenCalledWith(container, 'error', 'Please fill in both name fields.');
        expect(mockApi.postAuth).not.toHaveBeenCalled();
    });

    it('saveProfile calls onSuccess with display_name', async () => {
        const container = createProfileContainer();
        const onSuccess = vi.fn();

        await authProfile.saveProfile(container, onSuccess);

        expect(onSuccess).toHaveBeenCalledWith('John');
    });

    it('changePassword shows error when fields are empty', async () => {
        const container = createProfileContainer();
        const onSuccess = vi.fn();

        await authProfile.changePassword(container, onSuccess);

        const section = container.querySelector('[data-ct-password-section]');
        expect(mockShowMessage).toHaveBeenCalledWith(section, 'error', 'Please fill in all password fields.');
        expect(mockApi.postAuth).not.toHaveBeenCalled();
    });

    it('changePassword shows error when current === new', async () => {
        const container = createProfileContainer();
        const currentPasswordInput = container.querySelector('input[name="current_password"]');
        const newPasswordInput = container.querySelector('input[name="new_password"]');
        const confirmPasswordInput = container.querySelector('input[name="new_password_confirm"]');

        currentPasswordInput.value = 'SamePass1!';
        newPasswordInput.value = 'SamePass1!';
        confirmPasswordInput.value = 'SamePass1!';

        const onSuccess = vi.fn();

        await authProfile.changePassword(container, onSuccess);

        const section = container.querySelector('[data-ct-password-section]');
        expect(mockShowMessage).toHaveBeenCalledWith(section, 'error', 'New password must be different from your current password.');
        expect(mockApi.postAuth).not.toHaveBeenCalled();
    });

    it('changePassword shows error when passwords don\'t match', async () => {
        const container = createProfileContainer();
        const currentPasswordInput = container.querySelector('input[name="current_password"]');
        const newPasswordInput = container.querySelector('input[name="new_password"]');
        const confirmPasswordInput = container.querySelector('input[name="new_password_confirm"]');

        currentPasswordInput.value = 'OldPass1!';
        newPasswordInput.value = 'NewPass1!';
        confirmPasswordInput.value = 'DifferentPass1!';

        const onSuccess = vi.fn();

        await authProfile.changePassword(container, onSuccess);

        const section = container.querySelector('[data-ct-password-section]');
        expect(mockShowMessage).toHaveBeenCalledWith(section, 'error', 'New passwords do not match.');
        expect(mockApi.postAuth).not.toHaveBeenCalled();
    });

    it('changePassword shows error when password too short', async () => {
        const container = createProfileContainer();
        const currentPasswordInput = container.querySelector('input[name="current_password"]');
        const newPasswordInput = container.querySelector('input[name="new_password"]');
        const confirmPasswordInput = container.querySelector('input[name="new_password_confirm"]');

        currentPasswordInput.value = 'OldPass1!';
        newPasswordInput.value = 'Short1!';
        confirmPasswordInput.value = 'Short1!';

        const onSuccess = vi.fn();

        await authProfile.changePassword(container, onSuccess);

        const section = container.querySelector('[data-ct-password-section]');
        expect(mockShowMessage).toHaveBeenCalledWith(section, 'error', 'Password must be at least 8 characters.');
        expect(mockApi.postAuth).not.toHaveBeenCalled();
    });

    it('changePassword calls postAuth with valid data', async () => {
        const container = createProfileContainer();
        const currentPasswordInput = container.querySelector('input[name="current_password"]');
        const newPasswordInput = container.querySelector('input[name="new_password"]');
        const confirmPasswordInput = container.querySelector('input[name="new_password_confirm"]');

        currentPasswordInput.value = 'OldPass1!';
        newPasswordInput.value = 'NewPass1!';
        confirmPasswordInput.value = 'NewPass1!';

        const onSuccess = vi.fn();

        await authProfile.changePassword(container, onSuccess);

        expect(mockApi.postAuth).toHaveBeenCalledWith('profile/change-password', {
            current_password: 'OldPass1!',
            new_password: 'NewPass1!',
            new_password_confirm: 'NewPass1!'
        });
    });

    it('loadUserMessages shows "No messages yet." for empty messages', async () => {
        const container = createProfileContainer();

        await authProfile.loadUserMessages(container);

        const messagesDiv = container.querySelector('#ct_profile_messages');
        expect(messagesDiv.textContent).toContain('No messages yet.');
        expect(mockApi.getAuth).toHaveBeenCalledWith('contact/user-messages');
    });
});
