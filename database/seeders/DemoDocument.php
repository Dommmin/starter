<?php

namespace Database\Seeders;

/**
 * Rich text documents (the editor's JSON) for sample pages and articles:
 * a short one-paragraph body or a long one with headings, lists and a quote,
 * in Polish, German or English (other locales use English copy).
 */
final class DemoDocument
{
    /**
     * Body of a sample translation: the long document when requested or when
     * there is no summary to show (so no body merely repeats its title),
     * otherwise one paragraph with the summary.
     *
     * @return array<string, mixed>
     */
    public static function for(string $locale, string $title, ?string $summary, bool $long): array
    {
        return $long || $summary === null
            ? self::long($locale, $summary ?? $title)
            : self::short($summary);
    }

    /**
     * @return array<string, mixed>
     */
    public static function short(string $text): array
    {
        return ['type' => 'doc', 'content' => [self::paragraph($text)]];
    }

    /**
     * @return array<string, mixed>
     */
    public static function long(string $locale, string $lead): array
    {
        $copy = match ($locale) {
            'pl' => self::POLISH,
            'de' => self::GERMAN,
            default => self::ENGLISH,
        };

        return [
            'type' => 'doc',
            'content' => [
                self::paragraph($lead),
                self::heading(2, $copy['why']),
                self::paragraph($copy['whyText']),
                self::list('bulletList', $copy['bullets']),
                self::heading(3, $copy['how']),
                self::list('orderedList', $copy['steps']),
                ['type' => 'blockquote', 'content' => [self::paragraph($copy['quote'])]],
                ['type' => 'horizontalRule'],
                self::heading(2, $copy['summary']),
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $copy['summaryText'].' '],
                        ['type' => 'text', 'text' => $copy['bold'], 'marks' => [['type' => 'bold']]],
                        ['type' => 'text', 'text' => ' '],
                        ['type' => 'text', 'text' => 'laravel.com', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://laravel.com']]]],
                    ],
                ],
            ],
        ];
    }

    /**
     * @var array{why: string, whyText: string, bullets: list<string>, how: string, steps: list<string>, quote: string, summary: string, summaryText: string, bold: string}
     */
    private const array POLISH = [
        'why' => 'Dlaczego to ważne',
        'whyText' => 'Dobrze przygotowana treść pomaga odwiedzającym szybko znaleźć odpowiedź. Źródło: doświadczenie zespołu, który od lat wdraża małe serwisy dla klientów z całej Polski — od Gdańska po Zakopane.',
        'bullets' => ['Krótkie akapity i czytelne śródtytuły', 'Zdjęcia z opisem alternatywnym', 'Linki opisujące cel, a nie „kliknij tutaj”'],
        'how' => 'Jak zacząć',
        'steps' => ['Zaplanuj strukturę strony', 'Przygotuj treści w obu językach', 'Opublikuj i sprawdź podgląd na telefonie'],
        'quote' => '„Najlepsza strona to taka, której nie trzeba tłumaczyć” — Małgorzata Źdźbło, redaktorka',
        'summary' => 'Podsumowanie',
        'summaryText' => 'Wróć do tego artykułu, gdy będziesz przygotowywać kolejną publikację.',
        'bold' => 'Więcej informacji znajdziesz w dokumentacji:',
    ];

    /**
     * @var array{why: string, whyText: string, bullets: list<string>, how: string, steps: list<string>, quote: string, summary: string, summaryText: string, bold: string}
     */
    private const array ENGLISH = [
        'why' => 'Why it matters',
        'whyText' => 'Well-prepared content helps visitors find answers quickly. It reflects the experience of a team that has shipped small websites for clients across Europe for years.',
        'bullets' => ['Short paragraphs and clear subheadings', 'Images with alternative text', 'Links that describe their target instead of "click here"'],
        'how' => 'How to start',
        'steps' => ['Plan the page structure', 'Prepare the copy in both languages', 'Publish and check the preview on a phone'],
        'quote' => '"The best website is one that needs no explanation" — Małgorzata Źdźbło, editor',
        'summary' => 'Summary',
        'summaryText' => 'Come back to this article when you prepare the next publication.',
        'bold' => 'Find more in the documentation:',
    ];

    /**
     * @var array{why: string, whyText: string, bullets: list<string>, how: string, steps: list<string>, quote: string, summary: string, summaryText: string, bold: string}
     */
    private const array GERMAN = [
        'why' => 'Warum es wichtig ist',
        'whyText' => 'Gut vorbereitete Inhalte helfen Besuchern, schnell Antworten zu finden. Das zeigt die Erfahrung eines Teams, das seit Jahren kleine Websites für Kunden in ganz Europa umsetzt.',
        'bullets' => ['Kurze Absätze und klare Zwischenüberschriften', 'Bilder mit Alternativtext', 'Links, die ihr Ziel beschreiben statt „hier klicken“'],
        'how' => 'So fangen Sie an',
        'steps' => ['Seitenstruktur planen', 'Texte in allen Sprachen vorbereiten', 'Veröffentlichen und die Vorschau auf dem Handy prüfen'],
        'quote' => '„Die beste Website ist eine, die keine Erklärung braucht“ — Małgorzata Źdźbło, Redakteurin',
        'summary' => 'Zusammenfassung',
        'summaryText' => 'Kommen Sie auf diesen Artikel zurück, wenn Sie die nächste Veröffentlichung vorbereiten.',
        'bold' => 'Mehr dazu in der Dokumentation:',
    ];

    /**
     * @return array<string, mixed>
     */
    private static function paragraph(string $text): array
    {
        return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function heading(int $level, string $text): array
    {
        return ['type' => 'heading', 'attrs' => ['level' => $level], 'content' => [['type' => 'text', 'text' => $text]]];
    }

    /**
     * @param  'bulletList'|'orderedList'  $type
     * @param  list<string>  $items
     * @return array<string, mixed>
     */
    private static function list(string $type, array $items): array
    {
        return [
            'type' => $type,
            'content' => array_map(
                fn (string $item): array => ['type' => 'listItem', 'content' => [self::paragraph($item)]],
                $items,
            ),
        ];
    }
}
