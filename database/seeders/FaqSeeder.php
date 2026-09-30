<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Local sample FAQs in Polish and English (one for every language), short
 * and long answers, published and hidden. Idempotent: a question already
 * present in its language is skipped.
 */
class FaqSeeder extends Seeder
{
    /**
     * @var list<array{locale: string|null, question: string, answer: string, published: bool}>
     */
    public const array FAQS = [
        ['locale' => 'pl', 'question' => 'Ile trwa przygotowanie strony?', 'answer' => 'Zwykle od tygodnia do dwóch.', 'published' => true],
        ['locale' => 'pl', 'question' => 'Czy mogę samodzielnie edytować treści?', 'answer' => 'Tak. Panel pozwala edytować strony, artykuły, menu i sekcje strony głównej bez znajomości programowania. Każda zmiana trafia do dziennika zdarzeń, a wersje robocze nie są widoczne dla odwiedzających, dopóki ich nie opublikujesz. Możesz też zaplanować publikację na konkretny dzień i godzinę.', 'published' => true],
        ['locale' => 'pl', 'question' => 'Jak działa wersja językowa?', 'answer' => 'Każda treść może mieć tłumaczenia w aktywnych językach serwisu.', 'published' => true],
        ['locale' => 'pl', 'question' => 'Gdzie są przechowywane zdjęcia?', 'answer' => 'W bibliotece mediów; każdy plik jest skanowany przed publikacją.', 'published' => true],
        ['locale' => 'pl', 'question' => 'Czy oferujecie wsparcie po wdrożeniu?', 'answer' => 'Tak, w ramach abonamentu.', 'published' => false],
        ['locale' => 'en', 'question' => 'How long does it take to build a website?', 'answer' => 'Usually one to two weeks.', 'published' => true],
        ['locale' => 'en', 'question' => 'Can I edit the content myself?', 'answer' => 'Yes. The panel lets you edit pages, articles, menus and home page sections without any coding. Every change is recorded in the audit log, and drafts stay hidden from visitors until you publish them. You can also schedule a publication for a specific day and time.', 'published' => true],
        ['locale' => 'en', 'question' => 'Which languages are supported?', 'answer' => 'Every public language configured for the site.', 'published' => true],
        ['locale' => 'en', 'question' => 'Is there a mobile app?', 'answer' => 'Not yet.', 'published' => false],
        ['locale' => null, 'question' => 'Kontakt / Contact: hello@example.com', 'answer' => 'Odpowiadamy w ciągu jednego dnia roboczego. / We reply within one business day.', 'published' => true],
    ];

    public function run(): void
    {
        foreach (self::FAQS as $position => $definition) {
            Faq::query()->firstOrCreate(
                ['locale' => $definition['locale'], 'question' => $definition['question']],
                ['answer' => $definition['answer'], 'published' => $definition['published'], 'position' => $position + 1],
            );
        }
    }
}
