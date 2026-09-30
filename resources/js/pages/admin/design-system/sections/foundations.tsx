import {
    Bell,
    CheckCircle2,
    Download,
    Inbox,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import {
    Alert,
    Avatar,
    Badge,
    Button,
    EmptyState,
    Icon,
    IconButton,
    Link,
    Progress,
    Separator,
    Skeleton,
    Spinner,
    Stack,
    Tooltip,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

const buttonVariants = [
    'primary',
    'secondary',
    'outline',
    'ghost',
    'destructive',
    'link',
] as const;
const iconButtonVariants = [
    'primary',
    'secondary',
    'outline',
    'ghost',
    'destructive',
] as const;
const threeSizes = ['sm', 'default', 'lg'] as const;
const tooltipSides = ['top', 'right', 'bottom', 'left'] as const;
const badgeTones = [
    'neutral',
    'primary',
    'success',
    'danger',
    'outline',
] as const;
const iconTones = [
    'default',
    'muted',
    'subtle',
    'primary',
    'success',
    'danger',
] as const;

/** Synthetic file offered by the download demo; nothing leaves the browser. */
const demoDownloadHref = 'data:text/plain;charset=utf-8,design-system-demo';
/** An existing public asset stands in for a profile photo. */
const demoAvatarSrc = '/apple-touch-icon.png';
const demoBrokenAvatarSrc = '/design-system-demo-missing-avatar.png';
const demoExternalHref = 'https://example.com/';

/** ADM-01 — UI foundations: every component in its applicable states. */
function FoundationsSection() {
    const { t } = useTranslation();
    const demo = (key: string, params?: Record<string, string>) =>
        t(`admin.designSystem.foundations.${key}`, params);
    const [isAlertVisible, setAlertVisible] = useState(true);

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="Button"
                notApplicable={['empty', 'error', 'success', 'noMedia']}
            >
                <ShowcaseState state="variants">
                    <Stack gap="tight" align="start">
                        {buttonVariants.map((variant) => (
                            <Button key={variant} variant={variant}>
                                {demo(`buttonVariants.${variant}`)}
                            </Button>
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="sizes">
                    <Stack gap="tight" align="start">
                        {threeSizes.map((size) => (
                            <Button key={size} size={size}>
                                {demo(`sizes.${size}`)}
                            </Button>
                        ))}
                        <Button size="icon" ariaLabel={demo('add')}>
                            <Plus aria-hidden="true" />
                        </Button>
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="disabled">
                    <Stack gap="tight" align="start">
                        <Button disabled>{demo('save')}</Button>
                        <Button variant="outline" disabled>
                            {demo('cancel')}
                        </Button>
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="pending">
                    <Stack gap="tight" align="start">
                        <Button isPending>{demo('saving')}</Button>
                        <Button variant="destructive" isPending>
                            {demo('deleting')}
                        </Button>
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="longContent">
                    <Button>{demo('longAction')}</Button>
                </ShowcaseState>
                <ShowcaseState state="responsiveLabel">
                    <Button variant="outline" responsiveLabel>
                        <Pencil aria-hidden="true" />
                        <span>{demo('edit')}</span>
                    </Button>
                </ShowcaseState>
                <ShowcaseState state="internalLink">
                    <Button variant="secondary" href={adminIndex()}>
                        {demo('toDashboard')}
                    </Button>
                </ShowcaseState>
                <ShowcaseState state="anchor">
                    <Button variant="ghost" href="#adm-01">
                        {demo('toSectionTop')}
                    </Button>
                </ShowcaseState>
                <ShowcaseState state="external">
                    <Button variant="outline" href={demoExternalHref} external>
                        {demo('openExample')}
                    </Button>
                </ShowcaseState>
                <ShowcaseState state="download">
                    <Button
                        variant="outline"
                        href={demoDownloadHref}
                        download="design-system-demo.txt"
                    >
                        <Download aria-hidden="true" />
                        {demo('downloadFile')}
                    </Button>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="IconButton"
                notApplicable={[
                    'empty',
                    'error',
                    'success',
                    'longContent',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="variants">
                    <Stack gap="tight" align="start">
                        {iconButtonVariants.map((variant) => (
                            <IconButton
                                key={variant}
                                icon={variant === 'destructive' ? Trash2 : Bell}
                                variant={variant}
                                ariaLabel={demo(`buttonVariants.${variant}`)}
                            />
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="sizes">
                    <Stack gap="tight" align="start">
                        {threeSizes.map((size) => (
                            <IconButton
                                key={size}
                                icon={Pencil}
                                size={size}
                                variant="outline"
                                ariaLabel={demo(`sizes.${size}`)}
                            />
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="disabled">
                    <IconButton
                        icon={Pencil}
                        variant="outline"
                        ariaLabel={demo('edit')}
                        disabled
                    />
                </ShowcaseState>
                <ShowcaseState state="pending">
                    <IconButton
                        icon={Pencil}
                        variant="outline"
                        ariaLabel={demo('saving')}
                        isPending
                    />
                </ShowcaseState>
                <ShowcaseState state="withTooltip">
                    <Tooltip content={demo('edit')}>
                        <IconButton
                            icon={Pencil}
                            variant="outline"
                            ariaLabel={demo('edit')}
                        />
                    </Tooltip>
                </ShowcaseState>
                <ShowcaseState state="internalLink">
                    <IconButton
                        icon={Inbox}
                        variant="outline"
                        href={adminIndex()}
                        ariaLabel={demo('toDashboard')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Link"
                notApplicable={[
                    'disabled',
                    'pending',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="tones">
                    <Stack gap="tight" align="start">
                        <Link href={adminIndex()}>{demo('toDashboard')}</Link>
                        <Link href={adminIndex()} tone="muted">
                            {demo('toDashboard')}
                        </Link>
                        <Link href={adminIndex()} tone="primary">
                            {demo('toDashboard')}
                        </Link>
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="external">
                    <Link href={demoExternalHref} external>
                        {demo('openExample')}
                    </Link>
                </ShowcaseState>
                <ShowcaseState state="longContent">
                    <Link href={adminIndex()}>{demo('longLink')}</Link>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Avatar"
                notApplicable={[
                    'disabled',
                    'pending',
                    'empty',
                    'error',
                    'success',
                ]}
            >
                <ShowcaseState state="withMedia">
                    <Stack gap="tight" align="start">
                        {threeSizes.map((size) => (
                            <Avatar
                                key={size}
                                name={demo('personName')}
                                src={demoAvatarSrc}
                                fallback={demo('personInitials')}
                                size={size}
                            />
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="noMedia">
                    <Stack gap="tight" align="start">
                        {threeSizes.map((size) => (
                            <Avatar
                                key={size}
                                name={demo('personName')}
                                fallback={demo('personInitials')}
                                size={size}
                            />
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="brokenMedia">
                    <Avatar
                        name={demo('personName')}
                        src={demoBrokenAvatarSrc}
                        fallback={demo('personInitials')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent">
                    <Avatar
                        name={demo('longPersonName')}
                        fallback={demo('longPersonInitials')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Badge"
                notApplicable={['disabled', 'pending', 'empty', 'noMedia']}
            >
                <ShowcaseState state="tones">
                    <Stack gap="tight" align="start">
                        {badgeTones.map((tone) => (
                            <Badge key={tone} tone={tone}>
                                {demo(`badgeTones.${tone}`)}
                            </Badge>
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="withIcon">
                    <Badge tone="success">
                        <CheckCircle2 aria-hidden="true" />
                        {demo('badgeTones.success')}
                    </Badge>
                </ShowcaseState>
                <ShowcaseState state="longContent">
                    <Badge>{demo('longBadge')}</Badge>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Icon"
                notApplicable={[
                    'disabled',
                    'pending',
                    'empty',
                    'longContent',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="sizes">
                    <Stack gap="tight" align="start">
                        {threeSizes.map((size) => (
                            <Icon key={size} icon={Bell} size={size} />
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="tones">
                    <Stack gap="tight" align="start">
                        {iconTones.map((tone) => (
                            <Icon key={tone} icon={Bell} tone={tone} />
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="meaningful">
                    <Icon
                        icon={CheckCircle2}
                        tone="success"
                        label={demo('iconMeaningful')}
                    />
                </ShowcaseState>
                <ShowcaseState state="decorative">
                    <Icon icon={Inbox} tone="muted" />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Separator"
                notApplicable={[
                    'disabled',
                    'pending',
                    'empty',
                    'error',
                    'success',
                    'longContent',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="horizontal" fill>
                    <Separator />
                </ShowcaseState>
                <ShowcaseState state="vertical" fill>
                    <Separator orientation="vertical" />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Tooltip"
                notApplicable={[
                    'pending',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="sides">
                    <Stack gap="tight" align="start">
                        {tooltipSides.map((side) => (
                            <Tooltip
                                key={side}
                                side={side}
                                content={demo(`sides.${side}`)}
                            >
                                <Button variant="outline" size="sm">
                                    {demo(`sides.${side}`)}
                                </Button>
                            </Tooltip>
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="longContent">
                    <Tooltip content={demo('longTooltip')}>
                        <IconButton
                            icon={Bell}
                            variant="outline"
                            ariaLabel={demo('notifications')}
                        />
                    </Tooltip>
                </ShowcaseState>
                <ShowcaseState state="disabled">
                    <Tooltip content={demo('edit')}>
                        <IconButton
                            icon={Pencil}
                            variant="outline"
                            ariaLabel={demo('edit')}
                            disabled
                        />
                    </Tooltip>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Spinner"
                notApplicable={[
                    'disabled',
                    'empty',
                    'error',
                    'success',
                    'longContent',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="sizes">
                    <Stack gap="tight" align="start">
                        {threeSizes.map((size) => (
                            <Spinner
                                key={size}
                                size={size}
                                label={demo('loadingData')}
                            />
                        ))}
                    </Stack>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Skeleton"
                notApplicable={[
                    'disabled',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="loading" detail="shape=text" fill>
                    <Skeleton lines={3} />
                </ShowcaseState>
                <ShowcaseState state="loading" detail="shape=circle">
                    <Stack gap="tight" align="start">
                        {threeSizes.map((size) => (
                            <Skeleton key={size} shape="circle" size={size} />
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="loading" detail="shape=rect" fill>
                    <Skeleton shape="rect" size="sm" />
                </ShowcaseState>
                <ShowcaseState state="sizes" fill>
                    <Stack gap="tight">
                        {threeSizes.map((size) => (
                            <Skeleton key={size} size={size} />
                        ))}
                    </Stack>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Progress"
                layout="wide"
                notApplicable={['disabled', 'empty', 'noMedia']}
            >
                <ShowcaseState state="default" detail="0%" fill>
                    <Progress label={demo('uploadProgress')} value={0} />
                </ShowcaseState>
                <ShowcaseState state="pending" detail="45%" fill>
                    <Progress label={demo('uploadProgress')} value={45} />
                </ShowcaseState>
                <ShowcaseState state="indeterminate" fill>
                    <Progress label={demo('uploadProgress')} />
                </ShowcaseState>
                <ShowcaseState state="success" detail="100%" fill>
                    <Progress
                        label={demo('uploadProgress')}
                        value={100}
                        tone="success"
                    />
                </ShowcaseState>
                <ShowcaseState state="error" detail="70%" fill>
                    <Progress
                        label={demo('uploadProgress')}
                        value={70}
                        tone="danger"
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Alert"
                layout="wide"
                notApplicable={['disabled', 'pending', 'empty', 'noMedia']}
            >
                <ShowcaseState state="default" fill>
                    <Alert title={demo('alertInfoTitle')} />
                </ShowcaseState>
                <ShowcaseState state="withDescription" fill>
                    <Alert
                        title={demo('alertInfoTitle')}
                        description={demo('alertInfoDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="success" fill>
                    <Alert
                        tone="success"
                        title={demo('alertSuccessTitle')}
                        description={demo('alertSuccessDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <Alert
                        tone="danger"
                        title={demo('alertErrorTitle')}
                        description={demo('alertErrorDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="dismissible" fill>
                    {isAlertVisible ? (
                        <Alert
                            title={demo('alertInfoTitle')}
                            dismissLabel={demo('dismiss')}
                            onDismiss={() => setAlertVisible(false)}
                        />
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setAlertVisible(true)}
                        >
                            {demo('restoreAlert')}
                        </Button>
                    )}
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <Alert
                        tone="danger"
                        title={demo('longAlertTitle')}
                        description={demo('longAlertDescription')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="EmptyState"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="withAction" fill>
                    <EmptyState
                        icon={Inbox}
                        title={demo('emptyTitle')}
                        description={demo('emptyDescription')}
                        action={
                            <Button size="sm">
                                <Plus aria-hidden="true" />
                                {demo('add')}
                            </Button>
                        }
                    />
                </ShowcaseState>
                <ShowcaseState state="minimal" fill>
                    <EmptyState title={demo('emptyTitle')} />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <EmptyState
                        icon={Inbox}
                        title={demo('longEmptyTitle')}
                        description={demo('longEmptyDescription')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const foundationsFamily: ShowcaseFamily = {
    id: 'adm-01',
    titleKey: 'admin.designSystem.foundations.title',
    descriptionKey: 'admin.designSystem.foundations.description',
    Component: FoundationsSection,
};
