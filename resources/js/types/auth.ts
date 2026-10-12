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
};

export type Auth = {
    user: User;
    can: AuthAbilities;
    /** Self-service sign-up is switched on (`APP_REGISTRATION_ENABLED`). */
    canRegister: boolean;
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
