import {
    RichTextContent,
    Stack,
    Text,
    serializeJsonLd,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from '../../admin/design-system/sections/showcase';

/**
 * Stand-in for server output: the same element set the tiptap-php
 * allowlist produces. A static constant, never user or client input.
 */
const demoRichTextHtml = [
    '<h2>Zażółć gęślą jaźń</h2>',
    '<p>Akapit z <strong>pogrubieniem</strong>, <em>kursywą</em>, <s>przekreśleniem</s>, <code>kodem</code> i <a href="https://laravel.com/docs" rel="noopener noreferrer nofollow">linkiem zewnętrznym</a>.</p>',
    '<h3>Lista punktowana</h3>',
    '<ul><li><p>Pierwszy punkt</p></li><li><p>Drugi punkt z dłuższą treścią, która zawija się w wąskim kontenerze na telefonie.</p></li></ul>',
    '<h4>Lista numerowana</h4>',
    '<ol><li><p>Krok pierwszy</p></li><li><p>Krok drugi</p></li></ol>',
    '<blockquote><p>Cytat: „Źdźbło trawy ugina się pod ciężarem rosy”.</p></blockquote>',
    '<hr>',
    '<p>Bardzodługiesłowobezmiejscanazawinięciektóreniepowinnorozepchnąćukładuanipowodowaćpoziomegoprzewijaniacałejstrony https://example.test/bardzo/dlugi/adres/url/bez/spacji/ktory/musi/sie/zawinac</p>',
].join('');

const demoJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: '</script> & „Zażółć”',
};

/** WEB-04 — content: server-sanitised rich text. */
function ContentSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.web.content.${key}`);

    return (
        <ShowcaseComponent
            name="RichTextContent"
            layout="full"
            notApplicable={['loading', 'error', 'disabled', 'pending']}
        >
            <ShowcaseState state="longContent" detail={demo('allowlist')} fill>
                <RichTextContent html={demoRichTextHtml} />
            </ShowcaseState>
            <ShowcaseState state="empty" fill>
                <Stack gap="tight">
                    <RichTextContent html="" />
                    <Text variant="caption" tone="muted">
                        {demo('emptyNote')}
                    </Text>
                </Stack>
            </ShowcaseState>
        </ShowcaseComponent>
    );
}

export const contentFamily: ShowcaseFamily = {
    id: 'web-04',
    titleKey: 'admin.designSystem.web.content.title',
    descriptionKey: 'admin.designSystem.web.content.description',
    Component: ContentSection,
};

/** WEB-SEO — the `Seo` head of this page and JSON-LD escaping. */
function SeoSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.web.seo.${key}`);

    return (
        <ShowcaseComponent
            name="Seo · serializeJsonLd"
            layout="wide"
            notApplicable={['loading', 'empty', 'error', 'disabled', 'noMedia']}
        >
            <ShowcaseState
                state="inContext"
                detail="robots=noindex,nofollow"
                fill
            >
                <Text>{demo('inContext')}</Text>
            </ShowcaseState>
            <ShowcaseState state="longContent" detail="JSON-LD" fill>
                <Stack gap="tight">
                    <Text>{demo('jsonLd')}</Text>
                    <Text variant="caption" tone="muted" as="p">
                        {/* Spaces after commas only let the demo line wrap. */}
                        {serializeJsonLd(demoJsonLd).replaceAll(',', ', ')}
                    </Text>
                </Stack>
            </ShowcaseState>
        </ShowcaseComponent>
    );
}

export const seoFamily: ShowcaseFamily = {
    id: 'web-seo',
    titleKey: 'admin.designSystem.web.seo.title',
    descriptionKey: 'admin.designSystem.web.seo.description',
    Component: SeoSection,
};
