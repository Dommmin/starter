import { home, login, logout, register } from '@/routes';
import { store as loginStore } from '@/routes/login';
import {
    home as localizedHome,
    login as localizedLogin,
    logout as localizedLogout,
    register as localizedRegister,
} from '@/routes/localized';
import { store as localizedLoginStore } from '@/routes/localized/login';
import { store as localizedRegisterStore } from '@/routes/localized/register';
import { store as registerStore } from '@/routes/register';
import {
    email as passwordEmail,
    request as passwordRequest,
    update as passwordUpdate,
} from '@/routes/password';
import {
    email as localizedPasswordEmail,
    request as localizedPasswordRequest,
    update as localizedPasswordUpdate,
} from '@/routes/localized/password';
import { store as passwordConfirmStore } from '@/routes/password/confirm';
import { store as localizedPasswordConfirmStore } from '@/routes/localized/password/confirm';
import { store as twoFactorLoginStore } from '@/routes/two-factor/login';
import { store as localizedTwoFactorLoginStore } from '@/routes/localized/two-factor/login';
import { send as verificationSend } from '@/routes/verification';
import { send as localizedVerificationSend } from '@/routes/localized/verification';

export function isDefaultLocale(
    locale: string,
    defaultLocale: string,
): boolean {
    return locale === defaultLocale;
}

export function getLocalizedHomeRoute(locale: string, defaultLocale: string) {
    return isDefaultLocale(locale, defaultLocale)
        ? home()
        : localizedHome({ locale });
}

export function getLocalizedLoginRoute(locale: string, defaultLocale: string) {
    return isDefaultLocale(locale, defaultLocale)
        ? login()
        : localizedLogin({ locale });
}

export function getLocalizedLoginForm(locale: string, defaultLocale: string) {
    return isDefaultLocale(locale, defaultLocale)
        ? loginStore.form()
        : localizedLoginStore.form({ locale });
}

export function getLocalizedRegisterRoute(
    locale: string,
    defaultLocale: string,
) {
    return isDefaultLocale(locale, defaultLocale)
        ? register()
        : localizedRegister({ locale });
}

export function getLocalizedRegisterForm(
    locale: string,
    defaultLocale: string,
) {
    return isDefaultLocale(locale, defaultLocale)
        ? registerStore.form()
        : localizedRegisterStore.form({ locale });
}

export function getLocalizedForgotPasswordRoute(
    locale: string,
    defaultLocale: string,
) {
    return isDefaultLocale(locale, defaultLocale)
        ? passwordRequest()
        : localizedPasswordRequest({ locale });
}

export function getLocalizedForgotPasswordForm(
    locale: string,
    defaultLocale: string,
) {
    return isDefaultLocale(locale, defaultLocale)
        ? passwordEmail.form()
        : localizedPasswordEmail.form({ locale });
}

export function getLocalizedResetPasswordForm(
    locale: string,
    defaultLocale: string,
) {
    return isDefaultLocale(locale, defaultLocale)
        ? passwordUpdate.form()
        : localizedPasswordUpdate.form({ locale });
}

export function getLocalizedTwoFactorLoginForm(
    locale: string,
    defaultLocale: string,
) {
    return isDefaultLocale(locale, defaultLocale)
        ? twoFactorLoginStore.form()
        : localizedTwoFactorLoginStore.form({ locale });
}

export function getLocalizedPasswordConfirmForm(
    locale: string,
    defaultLocale: string,
) {
    return isDefaultLocale(locale, defaultLocale)
        ? passwordConfirmStore.form()
        : localizedPasswordConfirmStore.form({ locale });
}

export function getLocalizedVerificationSendForm(
    locale: string,
    defaultLocale: string,
) {
    return isDefaultLocale(locale, defaultLocale)
        ? verificationSend.form()
        : localizedVerificationSend.form({ locale });
}

export function getLocalizedLogoutRoute(locale: string, defaultLocale: string) {
    return isDefaultLocale(locale, defaultLocale)
        ? logout()
        : localizedLogout({ locale });
}
