import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Alert, Heading, Stack, Text } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export default function DeleteUser() {
    const { t } = useTranslation();
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <Stack gap="default">
            <Stack gap="tight">
                <Heading level={2} variant="group">
                    {t('settings.deleteAccount.title')}
                </Heading>
                <Text tone="muted">
                    {t('settings.deleteAccount.description')}
                </Text>
            </Stack>

            <Alert
                tone="danger"
                title={t('settings.deleteAccount.warningTitle')}
                description={t('settings.deleteAccount.warningDescription')}
            />

            <Dialog>
                <DialogTrigger asChild>
                    <Button variant="destructive">
                        {t('settings.deleteAccount.submit')}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        {t('settings.deleteAccount.confirmTitle')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('settings.deleteAccount.confirmDescription')}
                    </DialogDescription>

                    <Form
                        {...ProfileController.destroy.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        onError={() => passwordInput.current?.focus()}
                        resetOnSuccess
                    >
                        {({ resetAndClearErrors, processing, errors }) => (
                            <Stack gap="default">
                                <Stack gap="tight">
                                    <Label htmlFor="delete-user-password">
                                        {t('settings.deleteAccount.password')}
                                    </Label>

                                    <PasswordInput
                                        id="delete-user-password"
                                        name="password"
                                        ref={passwordInput}
                                        autoComplete="current-password"
                                        aria-invalid={
                                            errors.password ? true : undefined
                                        }
                                        aria-describedby={
                                            errors.password
                                                ? 'delete-user-password-error'
                                                : undefined
                                        }
                                    />

                                    <InputError
                                        id="delete-user-password-error"
                                        role="alert"
                                        message={errors.password}
                                    />
                                </Stack>

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                resetAndClearErrors()
                                            }
                                        >
                                            {t('settings.deleteAccount.cancel')}
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {t('settings.deleteAccount.submit')}
                                    </Button>
                                </DialogFooter>
                            </Stack>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </Stack>
    );
}
