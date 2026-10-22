export type UserRole = 'admin' | 'editor';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    role: UserRole | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type AuthAbilities = {
    accessAdminPanel: boolean;
    manageUsers: boolean;
    viewAudit: boolean;
    manageSiteSettings: boolean;
    viewContactMessages: boolean;
    manageTrash: boolean;
};

export type Auth = {
    user: User;
    can: AuthAbilities;
    /**
     * Localized sign-up page URL, or `null` while self-service sign-up is off
     * (`APP_REGISTRATION_ENABLED`). Comes from the backend because Wayfinder
     * does not generate the registration routes when they are disabled.
     */
    registerUrl: string | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
