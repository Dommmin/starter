<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Support\Content\LegalPages;
use Illuminate\Database\Seeder;

/**
 * Legal pages every client site needs: privacy policy, cookie policy and
 * terms of service in Polish, English and German. They are templates, not
 * legal advice: every `[[PLACEHOLDER]]` must be replaced and the text
 * reviewed before launch. Drafts outside the local environment (the System
 * page launch checklist asks to publish them); published in the local demo.
 *
 * Idempotent: a page with any of its slugs already present is skipped. The
 * cookie and privacy slugs match `consent.policy_slugs`, so the cookie
 * banner links to them once they are published.
 */
class LegalPageSeeder extends Seeder
{
    /**
     * Slugs per page and locale.
     *
     * @var array<string, array<string, string>>
     */
    public const array SLUGS = LegalPages::SLUGS;

    /**
     * Title, meta description and sections (`heading => paragraphs`) per
     * page and locale.
     *
     * @var array<string, array<string, array{0: string, 1: string, 2: array<string, list<string>>}>>
     */
    private const array PAGES = [
        'privacy' => [
            'pl' => ['Polityka prywatności', 'Jak [[NAZWA FIRMY]] przetwarza dane osobowe odwiedzających stronę.', [
                'Administrator danych' => ['Administratorem danych jest [[NAZWA FIRMY]], [[ADRES]], NIP [[NIP]]. Kontakt w sprawach danych osobowych: [[E-MAIL]].'],
                'Jakie dane przetwarzamy' => ['Dane z formularza kontaktowego (imię, adres e-mail, treść wiadomości) oraz dane techniczne niezbędne do działania strony (adres IP w logach serwera).'],
                'Cel i podstawa przetwarzania' => ['Odpowiedź na wiadomość — prawnie uzasadniony interes administratora (art. 6 ust. 1 lit. f RODO). Bezpieczeństwo strony — art. 6 ust. 1 lit. f RODO. Analityka — wyłącznie po zgodzie (art. 6 ust. 1 lit. a RODO).'],
                'Okres przechowywania' => ['Wiadomości z formularza przechowujemy przez [[OKRES, np. 90 dni]] od zakończenia sprawy, logi serwera przez [[OKRES, np. 14 dni]].'],
                'Odbiorcy danych' => ['Dostawca hostingu [[NAZWA DOSTAWCY]] i dostawca poczty [[NAZWA DOSTAWCY]], wyłącznie na podstawie umów powierzenia.'],
                'Twoje prawa' => ['Masz prawo dostępu do danych, ich sprostowania, usunięcia, ograniczenia przetwarzania, przeniesienia oraz sprzeciwu, a także skargi do Prezesa UODO.'],
            ]],
            'en' => ['Privacy policy', 'How [[COMPANY NAME]] processes the personal data of visitors.', [
                'Data controller' => ['The controller is [[COMPANY NAME]], [[ADDRESS]], tax ID [[TAX ID]]. Contact for data protection matters: [[E-MAIL]].'],
                'Data we process' => ['Data from the contact form (name, e-mail address, message) and technical data needed to run the website (IP address in server logs).'],
                'Purposes and legal basis' => ['Replying to your message — legitimate interest (Art. 6(1)(f) GDPR). Website security — Art. 6(1)(f) GDPR. Analytics — only with your consent (Art. 6(1)(a) GDPR).'],
                'Retention' => ['Contact form messages are kept for [[PERIOD, e.g. 90 days]] after the matter is closed, server logs for [[PERIOD, e.g. 14 days]].'],
                'Recipients' => ['Hosting provider [[PROVIDER NAME]] and e-mail provider [[PROVIDER NAME]], only under data processing agreements.'],
                'Your rights' => ['You may request access, rectification, erasure, restriction, portability and object to processing, and lodge a complaint with a supervisory authority.'],
            ]],
            'de' => ['Datenschutzerklärung', 'Wie [[FIRMENNAME]] personenbezogene Daten der Besucher verarbeitet.', [
                'Verantwortlicher' => ['Verantwortlich ist [[FIRMENNAME]], [[ADRESSE]], USt-IdNr. [[USt-IdNr.]]. Kontakt für Datenschutzfragen: [[E-MAIL]].'],
                'Verarbeitete Daten' => ['Daten aus dem Kontaktformular (Name, E-Mail-Adresse, Nachricht) und technische Daten für den Betrieb der Website (IP-Adresse in Serverprotokollen).'],
                'Zwecke und Rechtsgrundlage' => ['Beantwortung Ihrer Nachricht — berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO). Sicherheit der Website — Art. 6 Abs. 1 lit. f DSGVO. Analyse — nur mit Einwilligung (Art. 6 Abs. 1 lit. a DSGVO).'],
                'Speicherdauer' => ['Nachrichten aus dem Kontaktformular speichern wir [[ZEITRAUM, z. B. 90 Tage]] nach Abschluss, Serverprotokolle [[ZEITRAUM, z. B. 14 Tage]].'],
                'Empfänger' => ['Hosting-Anbieter [[ANBIETER]] und E-Mail-Anbieter [[ANBIETER]], nur auf Grundlage von Auftragsverarbeitungsverträgen.'],
                'Ihre Rechte' => ['Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit und Widerspruch sowie auf Beschwerde bei einer Aufsichtsbehörde.'],
            ]],
        ],
        'cookies' => [
            'pl' => ['Polityka cookies', 'Jakie pliki cookies wykorzystuje strona [[NAZWA FIRMY]].', [
                'Czym są cookies' => ['Cookies to małe pliki zapisywane w przeglądarce. Część z nich jest niezbędna do działania strony, pozostałe zapisujemy tylko po Twojej zgodzie.'],
                'Niezbędne' => ['Sesja i ochrona formularzy (CSRF), zapamiętany wybór motywu i zgody na cookies. Nie wymagają zgody.'],
                'Analityczne' => ['[[NARZĘDZIE, np. Plausible]] — tylko po zgodzie, do anonimowych statystyk odwiedzin.'],
                'Zmiana decyzji' => ['Wybór zmienisz w każdej chwili linkiem „Ustawienia cookies” w stopce strony.'],
            ]],
            'en' => ['Cookie policy', 'Which cookies the [[COMPANY NAME]] website uses.', [
                'What cookies are' => ['Cookies are small files stored in your browser. Some are necessary for the website to work; the others are set only with your consent.'],
                'Necessary' => ['Session and form protection (CSRF), the remembered theme and your cookie choice. No consent required.'],
                'Analytics' => ['[[TOOL, e.g. Plausible]] — only with consent, for anonymous visit statistics.'],
                'Changing your choice' => ['Change your choice at any time with the “Cookie settings” link in the footer.'],
            ]],
            'de' => ['Cookie-Richtlinie', 'Welche Cookies die Website von [[FIRMENNAME]] verwendet.', [
                'Was Cookies sind' => ['Cookies sind kleine Dateien in Ihrem Browser. Einige sind für den Betrieb der Website erforderlich, die übrigen setzen wir nur mit Ihrer Einwilligung.'],
                'Erforderlich' => ['Sitzung und Formularschutz (CSRF), das gewählte Design und Ihre Cookie-Auswahl. Keine Einwilligung erforderlich.'],
                'Analyse' => ['[[TOOL, z. B. Plausible]] — nur mit Einwilligung, für anonyme Besuchsstatistiken.'],
                'Auswahl ändern' => ['Ändern Sie Ihre Auswahl jederzeit über den Link „Cookie-Einstellungen“ im Footer.'],
            ]],
        ],
        'terms' => [
            'pl' => ['Regulamin', 'Zasady korzystania ze strony [[NAZWA FIRMY]].', [
                'Postanowienia ogólne' => ['Właścicielem strony jest [[NAZWA FIRMY]], [[ADRES]], NIP [[NIP]]. Regulamin określa zasady korzystania ze strony i formularza kontaktowego.'],
                'Formularz kontaktowy' => ['Formularz służy do przesłania zapytania. Odpowiadamy w ciągu [[CZAS, np. 2 dni roboczych]].'],
                'Odpowiedzialność' => ['Treści na stronie mają charakter informacyjny i nie stanowią oferty w rozumieniu Kodeksu cywilnego, chyba że wyraźnie wskazano inaczej.'],
                'Reklamacje i kontakt' => ['Uwagi dotyczące działania strony prosimy kierować na adres [[E-MAIL]].'],
                'Zmiany regulaminu' => ['Regulamin obowiązuje od [[DATA]]. O zmianach informujemy na tej stronie.'],
            ]],
            'en' => ['Terms of service', 'Rules for using the [[COMPANY NAME]] website.', [
                'General' => ['The website is operated by [[COMPANY NAME]], [[ADDRESS]], tax ID [[TAX ID]]. These terms govern the use of the website and its contact form.'],
                'Contact form' => ['The form is used to send an enquiry. We reply within [[TIME, e.g. 2 business days]].'],
                'Liability' => ['Content on this website is for information only and is not a binding offer unless stated otherwise.'],
                'Complaints and contact' => ['Please send remarks about the website to [[E-MAIL]].'],
                'Changes' => ['These terms apply from [[DATE]]. Changes are announced on this page.'],
            ]],
            'de' => ['Allgemeine Geschäftsbedingungen', 'Regeln für die Nutzung der Website von [[FIRMENNAME]].', [
                'Allgemeines' => ['Betreiber der Website ist [[FIRMENNAME]], [[ADRESSE]], USt-IdNr. [[USt-IdNr.]]. Diese Bedingungen regeln die Nutzung der Website und des Kontaktformulars.'],
                'Kontaktformular' => ['Das Formular dient zum Senden einer Anfrage. Wir antworten innerhalb von [[ZEIT, z. B. 2 Werktagen]].'],
                'Haftung' => ['Die Inhalte dieser Website dienen der Information und sind kein verbindliches Angebot, sofern nicht anders angegeben.'],
                'Beschwerden und Kontakt' => ['Hinweise zur Website senden Sie bitte an [[E-MAIL]].'],
                'Änderungen' => ['Diese Bedingungen gelten ab [[DATUM]]. Änderungen kündigen wir auf dieser Seite an.'],
            ]],
        ],
    ];

    public function run(): void
    {
        $isDemo = DemoContent::samplesEnabled();

        foreach (self::PAGES as $key => $translations) {
            if (self::seeded($key)) {
                continue;
            }

            $page = Page::query()->create();

            foreach ($translations as $locale => [$title, $description, $sections]) {
                $page->translations()->create([
                    'locale' => $locale,
                    'title' => $title,
                    'slug' => self::SLUGS[$key][$locale],
                    'meta_description' => $description,
                    'body' => self::document($sections),
                    'status' => $isDemo ? PublicationStatus::Published : PublicationStatus::Draft,
                    'published_at' => $isDemo ? now() : null,
                ]);
            }
        }
    }

    /**
     * Whether any translation of the page already exists.
     */
    private static function seeded(string $key): bool
    {
        foreach (self::SLUGS[$key] as $locale => $slug) {
            if (PageTranslation::query()->where('locale', $locale)->where('slug', $slug)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<string>>  $sections
     * @return array<string, mixed>
     */
    private static function document(array $sections): array
    {
        $content = [];

        foreach ($sections as $heading => $paragraphs) {
            $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => $heading]]];

            foreach ($paragraphs as $text) {
                $content[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
            }
        }

        return ['type' => 'doc', 'content' => $content];
    }
}
