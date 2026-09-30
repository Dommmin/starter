import { useEffect, useRef, useState } from 'react';
import {
    Alert,
    Button,
    ErrorSummary,
    FormActions,
    FormSection,
    ResourceForm,
    Stack,
    TextField,
    type ResourceFormSection,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

type DemoFormValues = {
    name: string;
    email: string;
    topic: string;
    quantity: string;
    startDate: string;
    message: string;
    consent: boolean;
    newsletter: boolean;
};

const emptyValues: DemoFormValues = {
    name: '',
    email: '',
    topic: '',
    quantity: '',
    startDate: '',
    message: '',
    consent: false,
    newsletter: false,
};

/** Simulated save time; nothing is sent, the demo only flips local state. */
const demoSaveMs = 1500;
const demoSavedMs = 4000;

/** Field-name → id prefix of a static `ErrorSummary` demo. */
const summaryFieldId = (name: string) => `adm-08-summary-${name}`;

function useDemoFormSections(): ResourceFormSection<DemoFormValues>[] {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.forms.${key}`);

    return [
        {
            id: 'contact',
            title: demo('contactSection'),
            description: demo('contactSectionDescription'),
            fields: [
                {
                    type: 'text',
                    name: 'name',
                    label: demo('nameLabel'),
                    required: true,
                    autoComplete: 'off',
                },
                {
                    type: 'text',
                    name: 'email',
                    inputType: 'email',
                    label: demo('emailLabel'),
                    hint: demo('emailHint'),
                    required: true,
                    autoComplete: 'off',
                },
                {
                    type: 'select',
                    name: 'topic',
                    label: demo('topicLabel'),
                    placeholder: demo('topicPlaceholder'),
                    required: true,
                    options: [
                        { value: 'offer', label: demo('topicOffer') },
                        { value: 'support', label: demo('topicSupport') },
                        { value: 'other', label: demo('topicOther') },
                    ],
                },
            ],
        },
        {
            id: 'details',
            title: demo('detailsSection'),
            fields: [
                {
                    type: 'number',
                    name: 'quantity',
                    label: demo('quantityLabel'),
                    hint: demo('quantityHint'),
                    min: 1,
                    max: 10,
                    step: 1,
                },
                {
                    type: 'date',
                    name: 'startDate',
                    label: demo('startDateLabel'),
                },
                {
                    type: 'textarea',
                    name: 'message',
                    label: demo('messageLabel'),
                    required: true,
                    rows: 3,
                },
                {
                    type: 'checkbox',
                    name: 'consent',
                    label: demo('consentLabel'),
                    required: true,
                },
                {
                    type: 'switch',
                    name: 'newsletter',
                    label: demo('newsletterLabel'),
                    hint: demo('newsletterHint'),
                },
            ],
        },
    ];
}

/** Client-side stand-in for a 422 response; the demo has no backend. */
function validateDemo(
    values: DemoFormValues,
    message: (key: string) => string,
): Partial<Record<keyof DemoFormValues, string>> {
    const errors: Partial<Record<keyof DemoFormValues, string>> = {};
    const quantity = Number(values.quantity);

    if (values.name.trim() === '') {
        errors.name = message('nameRequired');
    }
    if (!/^[^\s@]+@[^\s@]+$/.test(values.email.trim())) {
        errors.email = message('emailInvalid');
    }
    if (values.topic === '') {
        errors.topic = message('topicRequired');
    }
    if (
        values.quantity !== '' &&
        (!Number.isInteger(quantity) || quantity < 1 || quantity > 10)
    ) {
        errors.quantity = message('quantityInvalid');
    }
    if (values.message.trim() === '') {
        errors.message = message('messageRequired');
    }
    if (!values.consent) {
        errors.consent = message('consentRequired');
    }

    return errors;
}

/**
 * Interactive `ResourceForm`: submitting runs local validation (errors at
 * the fields, `ErrorSummary`, focus on the first invalid field), a valid
 * form is "saved" after a short simulated pending state.
 */
function InteractiveResourceForm() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.forms.${key}`);
    const sections = useDemoFormSections();
    const [values, setValues] = useState<DemoFormValues>(emptyValues);
    const [errors, setErrors] = useState<Partial<Record<string, string>>>({});
    const [isPending, setPending] = useState(false);
    const [isSaved, setSaved] = useState(false);
    const timers = useRef<number[]>([]);

    useEffect(() => {
        const pendingTimers = timers.current;

        return () => pendingTimers.forEach((id) => window.clearTimeout(id));
    }, []);

    function submit() {
        const nextErrors = validateDemo(values, demo);
        setErrors(nextErrors);
        setSaved(false);

        if (Object.keys(nextErrors).length > 0) {
            return;
        }

        setPending(true);
        timers.current.push(
            window.setTimeout(() => {
                setPending(false);
                setSaved(true);
                timers.current.push(
                    window.setTimeout(() => setSaved(false), demoSavedMs),
                );
            }, demoSaveMs),
        );
    }

    return (
        <ResourceForm<DemoFormValues>
            sections={sections}
            values={values}
            errors={errors}
            onChange={(name, value) =>
                setValues((previous) => ({ ...previous, [name]: value }))
            }
            onSubmit={submit}
            isPending={isPending}
            recentlySuccessful={isSaved}
            onCancel={() => {
                setValues(emptyValues);
                setErrors({});
                setSaved(false);
            }}
            labels={{
                submit: demo('submit'),
                cancel: demo('reset'),
                errorSummaryTitle: demo('errorSummaryTitle'),
                saved: demo('saved'),
            }}
        />
    );
}

/**
 * A filled, first-section-only `ResourceForm` frozen in the pending or the
 * saved state; typing works, submitting does nothing.
 */
function StaticResourceForm({
    isPending = false,
    recentlySuccessful = false,
}: {
    isPending?: boolean;
    recentlySuccessful?: boolean;
}) {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.forms.${key}`);
    const sections = useDemoFormSections();
    const [values, setValues] = useState<DemoFormValues>(() => ({
        ...emptyValues,
        name: demo('nameValue'),
        email: 'anna@example.com',
        topic: 'offer',
    }));

    return (
        <ResourceForm<DemoFormValues>
            sections={sections.slice(0, 1).map((section) => ({
                ...section,
                title: `${section.title} · ${t(
                    `admin.designSystem.states.${isPending ? 'pending' : 'success'}`,
                )}`,
            }))}
            values={values}
            onChange={(name, value) =>
                setValues((previous) => ({ ...previous, [name]: value }))
            }
            onSubmit={() => undefined}
            isPending={isPending}
            recentlySuccessful={recentlySuccessful}
            labels={{
                submit: demo('submit'),
                errorSummaryTitle: demo('errorSummaryTitle'),
                saved: demo('saved'),
            }}
        />
    );
}

/** ADM-08 — the form as a whole: sections, actions, summary, ResourceForm. */
function FormsSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.forms.${key}`);
    const [sectionName, setSectionName] = useState('');
    const [summaryName, setSummaryName] = useState('');
    const [summaryEmail, setSummaryEmail] = useState('anna.example.com');

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="ResourceForm"
                layout="wide"
                notApplicable={['loading', 'empty', 'readonly']}
            >
                <ShowcaseState
                    state="focusFirstError"
                    detail={demo('interactiveHint')}
                    fill
                >
                    <InteractiveResourceForm />
                </ShowcaseState>
                <ShowcaseState state="pending" fill>
                    <StaticResourceForm isPending />
                </ShowcaseState>
                <ShowcaseState state="success" fill>
                    <StaticResourceForm recentlySuccessful />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="ErrorSummary"
                layout="wide"
                notApplicable={['disabled', 'pending', 'loading', 'success']}
            >
                <ShowcaseState
                    state="formError"
                    detail={demo('summaryLinkHint')}
                    fill
                >
                    <Stack gap="default">
                        <ErrorSummary
                            title={demo('errorSummaryTitle')}
                            autoFocus={false}
                            items={[
                                {
                                    fieldId: summaryFieldId('name'),
                                    label: demo('nameLabel'),
                                    message: demo('nameRequired'),
                                },
                                {
                                    fieldId: summaryFieldId('email'),
                                    label: demo('emailLabel'),
                                    message: demo('emailInvalid'),
                                },
                            ]}
                        />
                        <TextField
                            id={summaryFieldId('name')}
                            name="demo-summary-name"
                            label={demo('nameLabel')}
                            required
                            error={demo('nameRequired')}
                            value={summaryName}
                            onChange={setSummaryName}
                        />
                        <TextField
                            id={summaryFieldId('email')}
                            name="demo-summary-email"
                            type="email"
                            label={demo('emailLabel')}
                            required
                            error={demo('emailInvalid')}
                            value={summaryEmail}
                            onChange={setSummaryEmail}
                        />
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <ErrorSummary
                        title={demo('longSummaryTitle')}
                        autoFocus={false}
                        items={[
                            {
                                fieldId: summaryFieldId('long'),
                                label: demo('longFieldLabel'),
                                message: demo('longErrorMessage'),
                            },
                        ]}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="empty"
                    detail={demo('emptySummaryHint')}
                    fill
                >
                    <ErrorSummary
                        title={demo('errorSummaryTitle')}
                        items={[]}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="error"
                    detail={demo('generalErrorHint')}
                    fill
                >
                    <Alert
                        tone="danger"
                        title={demo('generalErrorTitle')}
                        description={demo('generalErrorDescription')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="FormSection"
                layout="wide"
                notApplicable={['disabled', 'pending', 'loading', 'error']}
            >
                <ShowcaseState state="default" fill>
                    <FormSection title={demo('sectionTitleDefault')}>
                        <TextField
                            name="demo-section-name"
                            label={demo('nameLabel')}
                            value={sectionName}
                            onChange={setSectionName}
                        />
                    </FormSection>
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <FormSection
                        title={demo('sectionTitleDescribed')}
                        description={demo('contactSectionDescription')}
                    >
                        <TextField
                            name="demo-section-name-described"
                            label={demo('nameLabel')}
                            value={sectionName}
                            onChange={setSectionName}
                        />
                    </FormSection>
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <FormSection
                        title={demo('longSectionTitle')}
                        description={demo('longSectionDescription')}
                    >
                        <TextField
                            name="demo-section-name-long"
                            label={demo('longFieldLabel')}
                            value={sectionName}
                            onChange={setSectionName}
                        />
                    </FormSection>
                </ShowcaseState>
                <ShowcaseState state="empty" fill>
                    <FormSection title={demo('sectionTitleEmpty')}>
                        {null}
                    </FormSection>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="FormActions"
                layout="wide"
                notApplicable={['loading', 'empty', 'error', 'success']}
            >
                <ShowcaseState state="default" detail="align=end" fill>
                    <FormActions>
                        <Button variant="outline">{demo('cancel')}</Button>
                        <Button>{demo('submit')}</Button>
                    </FormActions>
                </ShowcaseState>
                <ShowcaseState state="variants" detail="align=start" fill>
                    <FormActions align="start">
                        <Button>{demo('submit')}</Button>
                        <Button variant="outline">{demo('cancel')}</Button>
                    </FormActions>
                </ShowcaseState>
                <ShowcaseState state="variants" detail="align=between" fill>
                    <FormActions align="between">
                        <Button variant="destructive">{demo('delete')}</Button>
                        <Button>{demo('submit')}</Button>
                    </FormActions>
                </ShowcaseState>
                <ShowcaseState state="pending" fill>
                    <FormActions>
                        <Button variant="outline" disabled>
                            {demo('cancel')}
                        </Button>
                        <Button isPending>{demo('submit')}</Button>
                    </FormActions>
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <FormActions>
                        <Button variant="outline" disabled>
                            {demo('cancel')}
                        </Button>
                        <Button disabled>{demo('submit')}</Button>
                    </FormActions>
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <FormActions>
                        <Button variant="outline">{demo('cancel')}</Button>
                        <Button>{demo('longSubmit')}</Button>
                    </FormActions>
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const formsFamily: ShowcaseFamily = {
    id: 'adm-08',
    titleKey: 'admin.designSystem.forms.title',
    descriptionKey: 'admin.designSystem.forms.description',
    Component: FormsSection,
};
