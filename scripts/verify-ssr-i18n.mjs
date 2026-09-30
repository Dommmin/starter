import assert from 'node:assert';

async function runSsrVerification() {
    console.log('Rozpoczynam weryfikację SSR i izolacji i18n...');

    const ssrModule = await import('../bootstrap/ssr/app.js');
    const render = ssrModule.default;

    assert.strictEqual(
        typeof render,
        'function',
        'renderPage musi być funkcją',
    );

    const seo = {
        siteName: 'Starter',
        canonical: 'http://localhost/',
        defaultImage: null,
        defaultTitle: 'Starter — domyślny tytuł',
        defaultDescription: 'Domyślny opis strony z ustawień',
        organization: { name: 'Starter', url: 'http://localhost', logo: null },
    };

    const site = {
        name: 'Nazwa z panelu',
        isCustomized: true,
        logo: null,
        tagline: null,
        footerText: 'Stopka z ustawień',
        contact: {
            email: 'kontakt@example.test',
            phone: null,
            address: null,
        },
        social: [
            {
                network: 'github',
                label: 'GitHub',
                url: 'https://github.com/example',
            },
        ],
    };

    const makePage = (locale, heroTitle) => ({
        component: 'welcome',
        props: {
            name: 'Starter',
            auth: { user: null },
            seo,
            site,
            contactForm: { token: 'ssr-fixture-token' },
            i18n: {
                area: 'public',
                locale,
                defaultLocale: 'en',
                fallback: 'en',
                dir: 'ltr',
                availableLocales: [
                    {
                        code: 'en',
                        name: 'English',
                        native: 'English',
                        dir: 'ltr',
                    },
                    {
                        code: 'pl',
                        name: 'Polish',
                        native: 'Polski',
                        dir: 'ltr',
                    },
                    {
                        code: 'de',
                        name: 'German',
                        native: 'Deutsch',
                        dir: 'ltr',
                    },
                ],
                messages: {
                    brand: {
                        name: 'Punkt Startowy',
                        firstPart: 'Punkt',
                        secondPart: 'Startowy',
                    },
                    nav: {
                        login: locale === 'pl' ? 'Zaloguj się' : 'Log in',
                        register: locale === 'pl' ? 'Rejestracja' : 'Register',
                    },
                    a11y: {
                        languageSelector: 'Choose language',
                        userMenu: 'User menu',
                        themeSwitcher: 'Theme',
                    },
                    landing: {
                        heroTitle,
                        badge: 'Badge',
                        heroDescription: 'Hero description',
                        ctaPrimaryGuest: 'Start',
                        statsArchitecture: 'Architecture',
                        statsArchitectureDesc: 'Architecture desc',
                        statsQuality: 'Quality',
                        statsQualityDesc: 'Quality desc',
                        statsPerformance: 'Performance',
                        statsPerformanceDesc: 'Performance desc',
                        featuresHeading: 'Features',
                        featuresSubheading: 'Features sub',
                        feature1Title: 'F1',
                        feature1Desc: 'F1 desc',
                        feature2Title: 'F2',
                        feature2Desc: 'F2 desc',
                        feature3Title: 'F3',
                        feature3Desc: 'F3 desc',
                        feature4Title: 'F4',
                        feature4Desc: 'F4 desc',
                        demoBadge: 'Demo',
                        demoTitle: 'Demo title',
                        demoDescription: 'Demo description',
                        demoQuote: 'Demo quote',
                        envStatus: 'Env status',
                        envReady: 'Ready',
                        stackLabel: 'Stack',
                        stackValue: 'Stack value',
                        ctaBottomTitle: 'Bottom title',
                        ctaBottomDescription: 'Bottom description',
                    },
                },
            },
        },
        url: locale === 'en' ? '/' : `/${locale}`,
        version: '',
    });

    // 1. Render EN
    const en1 = await render(makePage('en', 'Hero EN Initial'));
    assert.ok(
        en1.body.includes('Hero EN Initial'),
        'SSR EN musi zawierać angielski tytuł',
    );
    assert.ok(
        !en1.body.includes('landing.heroTitle') &&
            !en1.body.includes('brand.name'),
        'SSR EN nie może przeciekać surowych kluczy i18n zamiast tłumaczeń',
    );
    const en1Head = en1.head.join('\n');
    assert.ok(
        /<title[^>]*>Starter — domyślny tytuł - Nazwa z panelu<\/title>/.test(
            en1Head,
        ) &&
            en1Head.includes(
                'name="description" content="Domyślny opis strony z ustawień"',
            ),
        'SSR strony głównej musi zawierać tytuł (z sufiksem nazwy z panelu) i opis z ustawień strony',
    );
    assert.ok(
        en1.body.includes('Stopka z ustawień') &&
            en1.body.includes('>Nazwa z panelu</span>') &&
            en1.body.includes('kontakt@example.test') &&
            en1.body.includes('https://github.com/example'),
        'SSR nagłówka i stopki musi zawierać nazwę, tekst, kontakt i social z ustawień strony',
    );
    assert.ok(
        en1.body.includes('English'),
        'Przełącznik języka (trigger) musi być widoczny i pokazywać aktywny język, gdy availableLocales > 1',
    );

    // 2. Render PL
    const pl = await render(makePage('pl', 'Hero PL Zlokalizowany'));
    assert.ok(
        pl.body.includes('Hero PL Zlokalizowany'),
        'SSR PL musi zawierać polski tytuł',
    );
    assert.ok(
        !pl.body.includes('Hero EN Initial'),
        'SSR PL nie może zawierać tekstu z poprzedniego renderowania EN',
    );
    assert.ok(
        !pl.body.includes('landing.heroTitle'),
        'SSR PL nie może przeciekać surowych kluczy i18n',
    );
    assert.ok(
        pl.body.includes('Polski'),
        'Przełącznik języka musi pokazywać aktywny język PL',
    );

    // 3. Render DE
    const de = await render(makePage('de', 'Hero DE Startseite'));
    assert.ok(
        de.body.includes('Hero DE Startseite'),
        'SSR DE musi zawierać niemiecki tytuł',
    );
    assert.ok(
        !de.body.includes('landing.heroTitle'),
        'SSR DE nie może przeciekać surowych kluczy i18n',
    );
    assert.ok(
        de.body.includes('Deutsch'),
        'Przełącznik języka musi pokazywać aktywny język DE',
    );

    // 4. Render EN ponownie (sprawdzenie izolacji i braku wycieku z DE/PL)
    const en2 = await render(makePage('en', 'Hero EN Secondary'));
    assert.ok(
        en2.body.includes('Hero EN Secondary'),
        'SSR EN2 musi zawierać poprawny angielski tytuł',
    );
    assert.ok(
        !en2.body.includes('Hero DE Startseite'),
        'SSR EN2 nie może przeciekać z DE',
    );
    assert.ok(
        !en2.body.includes('Hero PL Zlokalizowany'),
        'SSR EN2 nie może przeciekać z PL',
    );

    // 5. Test współbieżnych renderowań (Promise.all)
    const [concurrentEn, concurrentPl, concurrentDe] = await Promise.all([
        render(makePage('en', 'Concurrent EN')),
        render(makePage('pl', 'Concurrent PL')),
        render(makePage('de', 'Concurrent DE')),
    ]);

    assert.ok(
        concurrentEn.body.includes('Concurrent EN') &&
            !concurrentEn.body.includes('Concurrent PL'),
    );
    assert.ok(
        concurrentPl.body.includes('Concurrent PL') &&
            !concurrentPl.body.includes('Concurrent EN'),
    );
    assert.ok(
        concurrentDe.body.includes('Concurrent DE') &&
            !concurrentDe.body.includes('Concurrent EN'),
    );

    // 6. Pierwszy HTML publicznej strony treści zawiera meta SEO w <head>.
    const basePage = makePage('pl', 'Hero PL');
    const contentPage = await render({
        ...basePage,
        component: 'pages/show',
        props: {
            ...basePage.props,
            title: 'O nas & zespół',
            metaDescription: 'Opis strony o nas',
            bodyHtml: '<p>Treść strony</p>',
            locale: 'pl',
            publishedAt: '2026-09-01T10:00:00+00:00',
            alternates: {
                en: 'http://localhost/about-us',
                pl: 'http://localhost/pl/o-nas',
                'x-default': 'http://localhost/about-us',
            },
        },
        url: '/pl/o-nas',
    });
    const head = contentPage.head.join('\n');

    assert.ok(
        /<title[^>]*>O nas &amp; zespół - Nazwa z panelu<\/title>/.test(head),
        'SSR strony treści musi zawierać <title> z escapowanym tytułem',
    );
    assert.ok(
        head.includes('name="description" content="Opis strony o nas"'),
        'SSR strony treści musi zawierać meta description',
    );
    assert.ok(
        head.includes('rel="canonical" href="http://localhost/pl/o-nas"'),
        'SSR strony treści musi zawierać canonical',
    );
    assert.ok(
        /rel="alternate" hreflang="en" href="http:\/\/localhost\/about-us"/i.test(
            head,
        ) && /hreflang="x-default"/i.test(head),
        'SSR strony treści musi zawierać hreflang z x-default',
    );
    assert.ok(
        head.includes('application/ld+json'),
        'SSR strony treści musi zawierać JSON-LD',
    );
    assert.ok(
        contentPage.body.includes('Treść strony'),
        'SSR strony treści musi zawierać treść',
    );

    console.log(
        'PASS: Weryfikacja SSR i izolacji i18n zakończona pełnym sukcesem!',
    );
    process.exit(0);
}

runSsrVerification().catch((err) => {
    console.error('BŁĄD WERYFIKACJI SSR:', err);
    process.exit(1);
});
