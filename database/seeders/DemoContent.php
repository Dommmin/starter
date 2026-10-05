<?php

namespace Database\Seeders;

use App\Contracts\DemoContent\DemoContentProvider;
use App\Support\DemoContent\DemoArticles;
use App\Support\DemoContent\DemoContactMessages;
use App\Support\DemoContent\DemoFaqs;
use App\Support\DemoContent\DemoHomeSections;
use App\Support\DemoContent\DemoMedia;
use App\Support\DemoContent\DemoMenuItems;
use App\Support\DemoContent\DemoNavigationItems;
use App\Support\DemoContent\DemoPages;
use App\Support\DemoContent\DemoUsers;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Carbon;

/**
 * Registry of the sample content created by the seeders. Seeders use these
 * values instead of literals and `app:init-project --remove-demo` removes
 * exactly these records. A module adding sample content registers its
 * provider in {@see self::PROVIDERS}.
 */
final class DemoContent
{
    /**
     * E-mail address of the seeded sample administrator.
     */
    public const string USER_EMAIL = 'test@example.com';

    public const string USER_NAME = 'Test User';

    /**
     * Seeded pages as `locale => slug` of all their translations.
     *
     * @var array<string, array<string, string>>
     */
    public const array PAGES = [
        'privacy' => ['en' => 'privacy-policy', 'pl' => 'polityka-prywatnosci'],
        'upcoming' => ['en' => 'upcoming-offer'],
        'about' => ['de' => 'ueber-uns', 'en' => 'about-us', 'pl' => 'o-nas'],
        'terms' => ['en' => 'terms-of-service', 'pl' => 'regulamin'],
        'cookies' => ['pl' => 'polityka-cookies'],
        'contact-details' => ['en' => 'contact-details', 'pl' => 'dane-kontaktowe'],
        'careers' => ['en' => 'careers', 'pl' => 'kariera'],
        'accessibility' => ['en' => 'accessibility-statement', 'pl' => 'deklaracja-dostepnosci'],
        'short' => ['en' => 'ok', 'pl' => 'ok-pl'],
        'services' => ['de' => 'leistungen', 'en' => 'services', 'pl' => 'uslugi'],
        'pricing' => ['en' => 'pricing', 'pl' => 'cennik'],
        'portfolio' => ['en' => 'portfolio', 'pl' => 'realizacje'],
        'imprint' => ['de' => 'impressum', 'en' => 'imprint'],
        'how-we-work' => ['en' => 'how-we-work', 'pl' => 'jak-pracujemy'],
        'partners' => ['pl' => 'partnerzy'],
        'press' => ['en' => 'press-kit', 'pl' => 'dla-prasy'],
        'workshops' => ['de' => 'workshops-fuer-vereine', 'en' => 'workshops-for-associations', 'pl' => 'warsztaty-dla-stowarzyszen'],
    ];

    /**
     * Seeded articles as `locale => slug` of all their translations.
     *
     * @var array<string, array<string, string>>
     */
    public const array ARTICLES = [
        'welcome' => ['de' => 'willkommen-in-unseren-neuigkeiten', 'en' => 'welcome-to-our-news', 'pl' => 'witamy-w-aktualnosciach'],
        'scheduled' => ['en' => 'coming-next-week'],
        'draft' => ['en' => 'draft-ideas'],
        'short' => ['en' => 'hi', 'pl' => 'hej'],
        'long-title' => ['en' => 'a-very-long-headline-that-checks-wrapping-in-lists-cards-and-the-browser-tab', 'pl' => 'bardzo-dlugi-naglowek-sprawdzajacy-zawijanie-na-listach-kartach-i-w-karcie-przegladarki'],
        'polish-characters' => ['pl' => 'zazolc-gesla-jazn'],
        'release-notes' => ['de' => 'versionshinweise-2-0', 'en' => 'release-notes-2-0', 'pl' => 'informacje-o-wydaniu-2-0'],
        'accessibility' => ['de' => 'barrierefreiheit-zuerst', 'en' => 'accessibility-first', 'pl' => 'dostepnosc-przede-wszystkim'],
        'performance' => ['en' => 'faster-pages-in-five-steps', 'pl' => 'szybsze-strony-w-pieciu-krokach'],
        'security' => ['en' => 'security-checklist', 'pl' => 'lista-kontrolna-bezpieczenstwa'],
        'team' => ['de' => 'lernen-sie-das-team-kennen', 'en' => 'meet-the-team', 'pl' => 'poznaj-zespol'],
        'case-study' => ['en' => 'case-study-local-bakery', 'pl' => 'studium-przypadku-lokalna-piekarnia'],
        'remote-work' => ['en' => 'remote-work-tools'],
        'local-market' => ['pl' => 'rynek-lokalny-w-liczbach'],
        'events' => ['de' => 'herbstveranstaltungen', 'en' => 'autumn-events', 'pl' => 'jesienne-wydarzenia'],
        'newsletter' => ['en' => 'newsletter-september', 'pl' => 'newsletter-wrzesien'],
        'faq-roundup' => ['en' => 'your-questions-answered', 'pl' => 'odpowiadamy-na-pytania'],
        'tips' => ['en' => 'ten-quick-tips', 'pl' => 'dziesiec-szybkich-porad'],
        'archive' => ['en' => 'from-the-archive', 'pl' => 'z-archiwum'],
        'launch' => ['de' => 'countdown-zum-start', 'en' => 'launch-countdown', 'pl' => 'odliczanie-do-startu'],
        'polish-draft' => ['pl' => 'szkic-artykulu'],
        'mixed-status' => ['en' => 'translation-in-progress', 'pl' => 'tlumaczenie-w-toku'],
        'history' => ['en' => 'our-history', 'pl' => 'nasza-historia'],
    ];

    /**
     * Providers of removable sample content, in removal order: menu items
     * first (they point at the sample pages and articles), then pages,
     * articles, media (article covers), FAQs, contact messages, the other
     * sample accounts and home sections. The sample administrator is removed
     * separately, after all of them.
     *
     * @var list<class-string<DemoContentProvider>>
     */
    public const array PROVIDERS = [
        DemoNavigationItems::class,
        DemoMenuItems::class,
        DemoPages::class,
        DemoArticles::class,
        DemoMedia::class,
        DemoFaqs::class,
        DemoContactMessages::class,
        DemoUsers::class,
        DemoHomeSections::class,
    ];

    /**
     * Creation moment of a sample page or article: shortly before its
     * earliest publication, or a few hours ago (offset by the key, so the
     * samples differ) when it is only a draft or scheduled.
     *
     * @param  array<string, array{0: string, 1: string|null, 2: string, 3: int}>  $translations
     */
    public static function createdAt(string $key, array $translations, CarbonInterface $now): CarbonInterface
    {
        $earliest = min(0, ...array_column($translations, 3));

        return $earliest < 0
            ? $now->copy()->addDays($earliest)->subHours(2)
            : $now->copy()->subHours(2 + strlen($key));
    }

    /**
     * Publication date of a sample translation relative to the real `$now`:
     * none for a draft, the start of a future day when scheduled, otherwise
     * `$days` from now.
     */
    public static function publishedAt(string $state, int $days, CarbonInterface $now): ?CarbonInterface
    {
        return match ($state) {
            'draft' => null,
            'scheduled' => $now->copy()->addDays($days)->startOfDay(),
            default => $now->copy()->addDays($days),
        };
    }

    /**
     * Run `$callback` with the clock frozen at `$moment`, so a sample record,
     * its timestamps and its audit entries carry that past date (created and
     * updated equal, so `--remove-demo` still sees it as unedited). The
     * previous test clock, if any, is restored.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function at(CarbonInterface $moment, Closure $callback): mixed
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($moment);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }
}
