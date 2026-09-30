import { SearchX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Alert,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    EmptyState,
    OfflineBanner,
    Progress,
    RetryPanel,
    Spinner,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

type RetryDemoStatus = 'failed' | 'retrying' | 'recovered';

/** ADM-16 — feedback: messages, retry, offline and progress in context. */
function FeedbackSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.feedback.${key}`);
    const [retryStatus, setRetryStatus] = useState<RetryDemoStatus>('failed');
    const [isAlertVisible, setAlertVisible] = useState(true);
    const retryTimer = useRef<number | null>(null);

    useEffect(
        () => () => {
            if (retryTimer.current !== null) {
                window.clearTimeout(retryTimer.current);
            }
        },
        [],
    );

    const retry = () => {
        setRetryStatus('retrying');
        retryTimer.current = window.setTimeout(
            () => setRetryStatus('recovered'),
            1500,
        );
    };

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="Toast"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="default" detail={demo('liveDemo')} fill>
                    <Stack gap="tight" align="start">
                        <Text>{demo('toastHowTo')}</Text>
                        <Button variant="link" href={adminIndex()}>
                            {demo('toDashboard')}
                        </Button>
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <Text>{demo('toastError')}</Text>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Alert"
                layout="wide"
                notApplicable={['disabled', 'pending', 'loading', 'noMedia']}
            >
                <ShowcaseState state="dismissible" detail={demo('target')} fill>
                    {isAlertVisible ? (
                        <Alert
                            tone="success"
                            title={demo('savedTitle')}
                            description={demo('savedDescription')}
                            dismissLabel={demo('dismiss')}
                            onDismiss={() => setAlertVisible(false)}
                        />
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setAlertVisible(true)}
                        >
                            {demo('restore')}
                        </Button>
                    )}
                </ShowcaseState>
                <ShowcaseState state="inContext" detail={demo('allTones')}>
                    <Button variant="link" href="#adm-01">
                        {demo('toFoundations')}
                    </Button>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="OfflineBanner"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <OfflineBanner message={demo('offline')} />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <OfflineBanner message={demo('offlineLong')} />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="RetryPanel"
                layout="wide"
                notApplicable={['disabled', 'loading', 'empty', 'noMedia']}
            >
                <ShowcaseState state="error" detail={demo('interactive')} fill>
                    {retryStatus === 'recovered' ? (
                        <Stack gap="tight" align="start">
                            <Alert
                                tone="success"
                                title={demo('recoveredTitle')}
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setRetryStatus('failed')}
                            >
                                {demo('restore')}
                            </Button>
                        </Stack>
                    ) : (
                        <RetryPanel
                            title={demo('retryTitle')}
                            description={demo('retryDescription')}
                            retryLabel={demo('retryLabel')}
                            onRetry={retry}
                            isPending={retryStatus === 'retrying'}
                        />
                    )}
                </ShowcaseState>
                <ShowcaseState state="pending" fill>
                    <RetryPanel
                        title={demo('retryTitle')}
                        retryLabel={demo('retryLabel')}
                        onRetry={() => {}}
                        isPending
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <RetryPanel
                        title={demo('retryLongTitle')}
                        description={demo('retryLongDescription')}
                        retryLabel={demo('retryLabel')}
                        onRetry={() => {}}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="EmptyState · Progress · Spinner"
                layout="wide"
                notApplicable={['disabled', 'noMedia']}
            >
                <ShowcaseState state="empty" detail={demo('inList')} fill>
                    <Card padding="compact">
                        <CardHeader>
                            <CardTitle>{demo('listTitle')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <EmptyState
                                icon={SearchX}
                                title={demo('noResultsTitle')}
                                description={demo('noResultsDescription')}
                                action={
                                    <Button variant="outline" size="sm">
                                        {demo('clearFilters')}
                                    </Button>
                                }
                            />
                        </CardContent>
                    </Card>
                </ShowcaseState>
                <ShowcaseState state="loading" detail={demo('inList')} fill>
                    <Card padding="compact">
                        <CardHeader>
                            <CardTitle>{demo('listTitle')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Stack gap="tight" align="center">
                                <Spinner
                                    size="lg"
                                    label={demo('loadingList')}
                                />
                            </Stack>
                        </CardContent>
                    </Card>
                </ShowcaseState>
                <ShowcaseState state="pending" detail={demo('import')} fill>
                    <Card padding="compact">
                        <CardHeader>
                            <CardTitle>{demo('importTitle')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Progress
                                label={demo('importProgress')}
                                value={60}
                            />
                        </CardContent>
                    </Card>
                </ShowcaseState>
                <ShowcaseState state="success" detail={demo('import')} fill>
                    <Card padding="compact">
                        <CardHeader>
                            <CardTitle>{demo('importTitle')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Stack gap="tight">
                                <Progress
                                    label={demo('importProgress')}
                                    value={100}
                                    tone="success"
                                />
                                <Alert
                                    tone="success"
                                    title={demo('importDone')}
                                />
                            </Stack>
                        </CardContent>
                    </Card>
                </ShowcaseState>
                <ShowcaseState state="error" detail={demo('import')} fill>
                    <Card padding="compact">
                        <CardHeader>
                            <CardTitle>{demo('importTitle')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Stack gap="tight">
                                <Progress
                                    label={demo('importProgress')}
                                    value={70}
                                    tone="danger"
                                />
                                <Alert
                                    tone="danger"
                                    title={demo('importFailed')}
                                />
                            </Stack>
                        </CardContent>
                    </Card>
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const feedbackFamily: ShowcaseFamily = {
    id: 'adm-16',
    titleKey: 'admin.designSystem.feedback.title',
    descriptionKey: 'admin.designSystem.feedback.description',
    Component: FeedbackSection,
};
