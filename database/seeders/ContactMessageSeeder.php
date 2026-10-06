<?php

namespace Database\Seeders;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Database\Seeder;

/**
 * Local sample contact form messages: pending, sent and failed deliveries,
 * in every public language, short and very long, enough for two pages of
 * the admin list. Timestamps are spread over the last
 * weeks. Failed messages have used up every delivery attempt, so
 * `contact:retry-failed` leaves them failed; pending ones are picked up by
 * the regular recovery and delivered to the local Mailpit. Idempotent: a
 * message is keyed by its (sample) e-mail address.
 */
class ContactMessageSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, locale: string, status: ContactMessageStatus, days: int, message: string}>
     */
    public const array MESSAGES = [
        'marta.lewandowska@example.org' => ['name' => 'Marta Lewandowska', 'locale' => 'pl', 'status' => ContactMessageStatus::Sent, 'days' => 21, 'message' => 'Dzień dobry, proszę o wycenę strony dla gabinetu fizjoterapii.'],
        'tomasz.wojcik@example.org' => ['name' => 'Tomasz Wójcik', 'locale' => 'pl', 'status' => ContactMessageStatus::Sent, 'days' => 18, 'message' => "Cześć!\n\nPiszę w imieniu stowarzyszenia miłośników kolei wąskotorowej z Żnina. Chcielibyśmy przygotować stronę z kalendarzem przejazdów, galerią zdjęć i krótką historią linii. Najważniejsze dla nas jest to, żeby wolontariusze mogli sami dodawać aktualności — większość z nas nie zna się na komputerach, więc panel musi być naprawdę prosty.\n\nCzy moglibyście przesłać orientacyjny koszt i czas realizacji? Budżet mamy ograniczony, ale możemy rozłożyć płatność na raty. Chętnie też umówimy się na rozmowę telefoniczną w dowolny wtorek lub czwartek po 16:00.\n\nPozdrawiam serdecznie,\nTomasz Wójcik"],
        'ewa.kaminska@example.org' => ['name' => 'Ewa Kamińska', 'locale' => 'pl', 'status' => ContactMessageStatus::Failed, 'days' => 14, 'message' => 'Czy strona może mieć wersję angielską i niemiecką?'],
        'jakub.zielinski@example.org' => ['name' => 'Jakub Zieliński', 'locale' => 'pl', 'status' => ContactMessageStatus::Pending, 'days' => 0, 'message' => 'Test.'],
        'agnieszka.szymanska@example.org' => ['name' => 'Agnieszka Szymańska-Dąbrowska', 'locale' => 'pl', 'status' => ContactMessageStatus::Sent, 'days' => 10, 'message' => 'Dziękuję za szybką odpowiedź, wszystko jasne.'],
        'pawel.kozlowski@example.org' => ['name' => 'Paweł Kozłowski', 'locale' => 'pl', 'status' => ContactMessageStatus::Pending, 'days' => 1, 'message' => 'Proszę o kontakt w sprawie zmiany danych na stronie „O nas”.'],
        'john.smith@example.org' => ['name' => 'John Smith', 'locale' => 'en', 'status' => ContactMessageStatus::Sent, 'days' => 9, 'message' => 'Hello, do you build websites for clients abroad?'],
        'sarah.connor@example.org' => ['name' => 'Sarah Connor', 'locale' => 'en', 'status' => ContactMessageStatus::Failed, 'days' => 5, 'message' => "Hi,\n\nI tried to reach you by phone twice this week. Could you please call me back regarding the redesign of our online shop? We would like to migrate the product catalogue, keep the existing URLs for SEO and add a blog.\n\nThanks,\nSarah"],
        'hans.mueller@example.org' => ['name' => 'Hans Müller', 'locale' => 'de', 'status' => ContactMessageStatus::Sent, 'days' => 3, 'message' => 'Guten Tag, bieten Sie auch Wartungsverträge an?'],
        'klara.becker@example.org' => ['name' => 'Klara Becker', 'locale' => 'de', 'status' => ContactMessageStatus::Pending, 'days' => 2, 'message' => 'Können Sie unsere Vereinsseite auf Deutsch und Polnisch umsetzen?'],
        'lukas.fischer@example.org' => ['name' => 'Lukas Fischer', 'locale' => 'de', 'status' => ContactMessageStatus::Failed, 'days' => 7, 'message' => 'Bitte rufen Sie mich zurück – es geht um ein Angebot für eine Zahnarztpraxis in Görlitz.'],
        'emma.johnson@example.org' => ['name' => 'Emma Johnson', 'locale' => 'en', 'status' => ContactMessageStatus::Pending, 'days' => 1, 'message' => 'Could you send me the price list for a small portfolio website?'],
        'oliver.brown@example.org' => ['name' => 'Oliver Brown', 'locale' => 'en', 'status' => ContactMessageStatus::Sent, 'days' => 12, 'message' => 'Thanks for the quick call yesterday — looking forward to the proposal.'],
        'krzysztof.mazur@example.org' => ['name' => 'Krzysztof Mazur', 'locale' => 'pl', 'status' => ContactMessageStatus::Sent, 'days' => 16, 'message' => 'Czy możecie przenieść naszą starą stronę z zachowaniem adresów podstron?'],
        'joanna.krawczyk@example.org' => ['name' => 'Joanna Krawczyk', 'locale' => 'pl', 'status' => ContactMessageStatus::Sent, 'days' => 25, 'message' => 'Dzień dobry, interesuje mnie strona dla przedszkola z galerią zdjęć i aktualnościami dla rodziców.'],
        'stanislaw.grabowski@example.org' => ['name' => 'Stanisław Grabowski', 'locale' => 'pl', 'status' => ContactMessageStatus::Failed, 'days' => 4, 'message' => 'Proszę o fakturę za ostatni miesiąc utrzymania strony.'],
        'barbara.wozniak@example.org' => ['name' => 'Barbara Woźniak', 'locale' => 'pl', 'status' => ContactMessageStatus::Pending, 'days' => 0, 'message' => 'Zażółć gęślą jaźń — sprawdzam, czy polskie znaki docierają poprawnie.'],
    ];

    public function run(): void
    {
        foreach (self::MESSAGES as $email => $definition) {
            if (ContactMessage::query()->where('email', $email)->exists()) {
                continue;
            }

            $createdAt = now()->subDays($definition['days'])->subHours(3);
            $message = new ContactMessage;
            $message->forceFill([
                'name' => $definition['name'],
                'email' => $email,
                'message' => $definition['message'],
                'locale' => $definition['locale'],
                'status' => $definition['status'],
                'attempts' => match ($definition['status']) {
                    ContactMessageStatus::Pending => 0,
                    ContactMessageStatus::Sent => 1,
                    ContactMessageStatus::Failed => (int) config('contact.max_total_attempts'),
                },
                'last_error' => $definition['status'] === ContactMessageStatus::Failed ? 'Connection could not be established with host "smtp.example.test:587".' : null,
                'sent_at' => $definition['status'] === ContactMessageStatus::Sent ? $createdAt->copy()->addMinute() : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }
    }
}
