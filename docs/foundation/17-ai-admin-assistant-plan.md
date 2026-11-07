# Plan asystenta AI w panelu admina — 2026-11-04

Status: **wdrożone (etapy 0–6 na `develop`, PR #120–#127, 2026-11-06)**. Cel: tanie funkcje AI, które oszczędzają adminowi czas przy budowie i utrzymaniu stron, zgodne z celem startera (małe aplikacje w tydzień). Przy okazji ma to być nauka wdrażania `laravel/ai` (oficjalny Laravel AI SDK, Laravel 13). Poniższe sekcje etapów opisują pierwotny plan; aktualny sposób użycia jest w sekcji „Jak używać”.

## Jak używać

Stan na 2026-11-07. Funkcje nie były jeszcze sprawdzone w przeglądarce ani z prawdziwym kluczem API: przed włączeniem na produkcji przejdź poniższe kroki lokalnie.

### Włączenie

W lokalnym `.env` (na serwerze w jego `.env`/sekretach):

```dotenv
AI_FEATURES_ENABLED=true
ANTHROPIC_API_KEY=sk-ant-...
```

Potem `make restart`. Klucz API to sekret: nigdy w Git, logach ani zgłoszeniach. Bez klucza albo z `AI_FEATURES_ENABLED=false` panel działa jak wcześniej, przyciski są ukryte, a endpointy zwracają 404.

| Zmienna | Domyślnie | Funkcja |
|---|---|---|
| `AI_PROVIDER` | `anthropic` | dostawca: `anthropic`, `openai`, `gemini` lub inny z `config/ai.php` |
| `AI_FEATURE_SEO_META` | `true` | propozycja meta description i sluga |
| `AI_FEATURE_MEDIA_ALT_TEXT` | `true` | alt-text obrazów (ręcznie i automatycznie) |
| `AI_FEATURE_TRANSLATION` | `true` | tłumaczenie artykułów i stron |
| `AI_FEATURE_TEXT_REWRITE` | `true` | „Popraw tekst” |
| `AI_FEATURE_FAQ_GENERATOR` | `true` | generator FAQ |
| `AI_FEATURE_MESSAGE_CLASSIFICATION` | `false` | klasyfikacja wiadomości kontaktowych (PII) |
| `AI_MONTHLY_TOKEN_BUDGET` | `2000000` | wspólny miesięczny limit tokenów, `0` wyłącza asystenta |
| `AI_RATE_LIMIT_PER_MINUTE` | `20` | prośby na użytkownika na minutę |
| `AI_MESSAGE_CLASSIFICATION_DAILY_LIMIT` | `200` | klasyfikacje na dobę, `0` zatrzymuje |
| `AI_TIMEOUT` | `30` | timeout żądania do dostawcy w sekundach |

Pełna lista z komentarzami: `config/assistant.php`, dostawcy: `config/ai.php`.

**Inny dostawca.** Agenci nie są związani z Anthropic: używają domyślnego dostawcy (`AI_PROVIDER`) i jego najtańszego modelu (`#[UseCheapestModel]`), a flaga dostępności sprawdza klucz właśnie tego dostawcy. Dla OpenAI ustaw `AI_PROVIDER=openai` i `OPENAI_API_KEY`, dla Google `AI_PROVIDER=gemini` i `GEMINI_API_KEY`. Testy działają na fake'ach, więc każdego dostawcę sprawdź ręcznie, zwłaszcza alt-text (wymaga modelu z vision), tłumaczenie długich treści i limit długości meta description. Przy klasyfikacji wiadomości zmiana dostawcy zmienia też to, komu przekazujesz treść wiadomości (zgoda właściciela z 2026-11-05 dotyczyła Anthropic). Przyciski widzi tylko użytkownik z prawem edycji danego zasobu (admin, edytor); nowych ról nie ma.

### Funkcje

AI tylko proponuje: wynik trafia do podglądu z akcjami „Użyj” / „Odrzuć”, a zapis to zwykłe „Zapisz” formularza.

- **SEO meta.** Formularz artykułu lub strony, przyciski **„Zaproponuj”** przy *Slug* i *Meta description*. Propozycja z tytułu i treści, w języku zakładki.
- **„Popraw tekst”.** Artykuł: *Zajawka* i *Meta description*; strona: *Meta description*. Menu trybów: Skróć, Uprość, Popraw błędy, Ton formalny, Ton swobodny. Przy meta description „Zaproponuj” i „Popraw tekst” dzielą jeden podgląd.
- **Tłumaczenia.** Zakładka języka innego niż domyślny, przycisk **„Przetłumacz z …”**. Wypełnia tytuł, treść i meta description jako szkic, z zachowaniem formatowania i linków. Wymaga tytułu w języku źródłowym; istniejąca treść zakładki jest nadpisywana dopiero po potwierdzeniu.
- **Alt-text.** Edycja medium, przycisk **„Zaproponuj opis”**. Dodatkowo job w kolejce uzupełnia brakujący alt obrazu po statusie *clean* (plik z kwarantanny nie trafia do dostawcy) i nigdy nie nadpisuje altu wpisanego przez człowieka. Kolejkę obsługuje Horizon (lokalnie startuje z `make up`).
- **Generator FAQ.** Lista FAQ, przycisk **„Generuj FAQ z AI”**: wklej treść strony, oferty lub notatki, dostaniesz do 8 pytań. Rekordy powstają dopiero z zaznaczonych pozycji.
- **Klasyfikacja wiadomości (domyślnie wyłączona).** Nowa wiadomość z formularza kontaktowego dostaje w tle kategorię lead / pytanie / spam / inne; lista wiadomości ma kolumnę **„Kategoria (AI)”** i filtr. Do dostawcy trafiają tylko temat i treść z wyciętymi e-mailami i numerami telefonów; imię, e-mail i pozostałe pola nie są wysyłane. Spam jest tylko oznaczany, błąd AI nie blokuje maila ani webhooka. Włączaj świadomie i uwzględnij to w polityce prywatności.

### Koszty i audyt

- Każde wywołanie zostawia wpis w **Audycie** (akcja *assistant suggested*): funkcja, model i liczba tokenów, bez treści promptu.
- Po wyczerpaniu limitu miesięcznego UI pokazuje komunikat o limicie, po przekroczeniu limitu na minutę prośbę o odczekanie.
- Licznik tokenów w cache to miękki bezpiecznik, nie źródło rozliczeń; koszty sprawdzaj w konsoli dostawcy.

### Produkcja

1. Deploy z `develop` uruchamia migrację `contact_messages` (`category`, `classified_at`): wcześniej świeży backup, zgodnie z procedurą releasu.
2. Klucz i `AI_FEATURES_ENABLED=true` ustawia człowiek w konfiguracji serwera.
3. Po włączeniu sprawdź jedną funkcję i wpis w Audycie, zanim udostępnisz ją klientowi.

## Zasady

- **AI podpowiada, człowiek zatwierdza.** Każdy wynik trafia do formularza jako propozycja („Użyj” / „Odrzuć”). AI nic nie publikuje ani nie usuwa samodzielnie.
- **Domyślnie tani model** dostawcy (Anthropic, `Lab::Anthropic`), mocniejszy tylko tam, gdzie test pokaże taką potrzebę.
- **Structured output** (`HasStructuredOutput` + `schema()`) zamiast wolnego tekstu. Wynik walidujemy jak każde wejście.
- **Kolejka** (Horizon) dla operacji dłuższych lub automatycznych. Joby mają timeout, retry i idempotencję jak `ScanMediaAsset`.
- **Flaga i limit kosztów.** Bez klucza API lub z wyłączoną flagą panel działa jak dziś, a przyciski AI są ukryte.
- **Bez PII w promptach** poza etapem 6, który wymaga osobnej decyzji. Audyt zapisuje metadane (funkcja, model, tokeny), nie treść promptu.
- **Testy bez sieci:** `Agent::fake()` i `preventStrayPrompts`, zero prawdziwych wywołań w CI.

## Stan zastany (fakty)

- Brak jakiejkolwiek integracji AI w `composer.json` i `config/`.
- `article_translations` i `page_translations` mają `title` (200) i `meta_description` (320). Brak osobnego `meta_title`.
- `media_assets.alt` (500) istnieje. Upload przechodzi kwarantannę i `ScanMediaAsset` przed statusem clean.
- `contact_messages.status` opisuje dostarczenie wiadomości, nie jej kategorię.
- Audyt: `App\Actions\Audit\RecordAuditEvent` z enumem `AuditAction`.
- Inertia v3 udostępnia `useHttp` do wywołań, które nie są nawigacją.

## Kolejność etapów

| Etap | Funkcja | Czego uczy | Migracja |
|---|---|---|---|
| 0 | Fundament | instalacja, config, flagi, limity, audyt, fake'i | nie |
| 1 | SEO meta | agent ze structured output, wywołanie synchroniczne | nie |
| 2 | Alt-text obrazów | vision, job w kolejce, idempotencja | nie |
| 3 | Tłumaczenia | długie wejście, wiele pól, języki | nie |
| 4 | „Popraw tekst” | jeden agent z trybem z allowlisty | nie |
| 5 | Generator FAQ | lista rekordów jako szkic do zatwierdzenia | nie |
| 6 | Klasyfikacja wiadomości | wysyłanie PII do dostawcy, nowe kolumny | **tak** |

Każdy etap to osobny, mały PR. Etap 0 wchodzi razem z etapem 1, żeby fundament od razu miał prawdziwe użycie.

## Etap 0 — fundament

Zakres:

- `composer require laravel/ai` i publikacja `config/ai.php`. **Bez publikowania migracji** `agent_conversations`, bo pamięć rozmów nie jest potrzebna.
- `ANTHROPIC_API_KEY` pusty w `.env.example`, wartość tylko w lokalnym `.env` i sekretach serwera.
- Config funkcji: globalny `AI_FEATURES_ENABLED`, przełącznik dla każdej funkcji i miesięczny limit tokenów.
- `app/Ai/Agents/*` (z `make:agent --structured`) i akcja `RunAiSuggestion`:
  - sprawdza flagę i limit,
  - wywołuje agenta z timeoutem,
  - zapisuje audyt przez `RecordAuditEvent` z nowym `AuditAction::AiSuggested` (funkcja, model, `usage->inputTokens`, `usage->outputTokens`, bez treści),
  - zamienia błąd dostawcy na czytelny komunikat w UI.
- Named rate limiter `ai` per użytkownik, np. 20 żądań na minutę.
- Autoryzacja bez nowych ról: prośba o podpowiedź wymaga istniejącej policy `update` danego zasobu.
- UI: wspólny wzorzec „Zaproponuj” (przycisk przy polu, loading, podgląd, „Użyj” / „Odrzuć”). To **rozszerzenie DS**, więc przed implementacją powstaje propozycja API prymitywu do akceptacji właściciela DS (ADR-019).

AC:

- Wyłączona flaga lub brak klucza: brak przycisków, endpointy zwracają 404/403.
- Przekroczony limit: 429 i komunikat w UI.
- Każde wywołanie zostawia wpis w audycie z liczbą tokenów.

Testy: flaga wyłączona, brak uprawnień, rate limit, limit miesięczny, błąd dostawcy, wpis w audycie.

## Etap 1 — SEO meta

- `SeoMetaAgent` ze schematem `{meta_description ≤160, slug_suggestion}`. Wejście to tytuł i treść tłumaczenia, wyjście w języku tego tłumaczenia.
- Endpoint w adminie dla `ArticleTranslation` i `PageTranslation`, wywołanie synchroniczne.
- Propozycja wypełnia pole formularza. Zapis zostaje zwykłym zapisem formularza.

AC: wynik mieści się w limicie kolumny. Pusta treść daje komunikat zamiast wywołania.

Testy: sukces z fake'iem, walidacja długości, brak uprawnień do zasobu.

## Etap 2 — alt-text obrazów

- `MediaAltTextAgent` z vision (`Files\Image::fromStorage`) i schematem `{alt, caption?}` w domyślnym języku serwisu.
- Job `GenerateMediaAltText` (`ShouldQueue`, `ShouldBeUnique`, timeout, retry):
  - uruchamia się dopiero po statusie clean, plik z kwarantanny nigdy nie trafia do dostawcy,
  - jest idempotentny i nie nadpisuje alt-tekstu wpisanego przez człowieka.
- Ręczny przycisk „Wygeneruj alt” w edycji medium.

AC: obraz w kwarantannie lub plik niebędący obrazem oznacza brak wywołania. Istniejący alt zostaje nietknięty.

Testy: job z fake'iem, pominięcie kwarantanny i ręcznego altu, ponowne uruchomienie bez duplikatu.

## Etap 3 — tłumaczenia

- `TranslationAgent` ze schematem pól tłumaczenia (`title`, `excerpt`, `body`, `meta_description`) oraz parametrami języka źródłowego i docelowego. Instrukcja: zachowaj formatowanie i linki, nie tłumacz nazw własnych.
- Przycisk „Przetłumacz z PL” w zakładce języka formularza artykułu lub strony. Wynik wypełnia formularz jako szkic do przeczytania i zapisania.
- Długie treści przez kolejkę ze stanem „w trakcie”, krótkie synchronicznie. Próg ustalany podczas implementacji.
- Później ten sam wzorzec dla FAQ i ustawień per język.

AC: formatowanie treści przetrwa bez zmian (test na fixture). Brak treści źródłowej daje komunikat. Istniejące tłumaczenie nie jest nadpisywane bez potwierdzenia.

## Etap 4 — „Popraw tekst”

- Jeden `TextRewriteAgent` z trybem z enuma: `shorten`, `simplify`, `fix`, `tone_formal`, `tone_casual`. Brak dowolnego promptu od użytkownika zmniejsza powierzchnię prompt injection.
- Wejście to zaznaczony fragment albo całe pole. Wynik jest pokazywany obok oryginału, z akcjami „Zastąp” / „Anuluj”.

Testy: każdy tryb przechodzi przez allowlistę, nieznany tryb daje 422.

Wdrożenie (2026-11-05): wejściem jest całe pole (excerpt artykułu oraz meta description artykułu i strony). Rozszerzenie DS zaakceptowane przez właściciela: `FieldAssist.modes` otwiera menu trybów (Radix ładowany leniwie), a `assist` przyjmuje też listę helperów, więc „Zaproponuj” i „Popraw tekst” działają przy jednym polu ze wspólnym podglądem. Zaznaczony fragment i pola rich text pozostają poza zakresem.

## Etap 5 — generator FAQ

- `FaqGeneratorAgent` ze schematem `{items: [{question, answer}]}`, maksymalnie 8 pozycji, generowany z treści strony lub artykułu.
- Wynik to lista do odznaczenia. Zaznaczone pozycje tworzą rekordy przez istniejące akcje z `app/Actions/Faqs`.

AC: nic nie zapisuje się bez kliknięcia. Liczba pozycji jest ograniczona.

## Etap 6 — klasyfikacja wiadomości (HIGH-RISK)

Decyzje właściciela (2026-11-05, zakres zamknięty):

1. Do dostawcy AI trafia **wyłącznie temat i treść** wiadomości. Imię, e-mail, telefon i pozostałe pola kontaktowe/meta nigdy nie trafiają do promptu. Adresy e-mail i numery telefonów w treści są przed wysłaniem zamieniane na `[email]` / `[phone]` (`ContactDetailsRedactor`). Formularz nie ma stałej kolumny tematu: tematem jest dodatkowe pole o kluczu `subject`, jeśli formularz je ma.
2. Migracja `contact_messages` dodaje **tylko** `category` (nullable, enum `ContactMessageCategory`: `lead`/`question`/`spam`/`other`) i `classified_at` (nullable). **Bez kolumny `summary`.**
3. Funkcja `message_classification` jest domyślnie wyłączona (`AI_FEATURE_MESSAGE_CLASSIFICATION=false`), bo przetwarza PII.

Wdrożenie:

- Job `ClassifyContactMessage` kolejkowany po zapisie wiadomości (after commit, tylko gdy funkcja dostępna). Unikalny per wiadomość, timeout, retry z backoff, idempotentny (pomija sklasyfikowane, zapis warunkowy `classified_at IS NULL`). Nie blokuje maila ani webhooka i niczego nie usuwa; spam jest tylko oznaczany. Błąd dostawcy, przekroczony limit lub odpowiedź spoza enumu zostawiają wiadomość bez kategorii; logi bez treści.
- Audyt jak w etapie 0 (funkcja, model, tokeny), aktor `null` (proces systemowy).
- Lista wiadomości: badge kategorii i filtr `category` z allowlisty (nieznana wartość = błąd walidacji jak przy `status`).

Testy: zakres promptu i redakcja, wyłączona funkcja, błąd klasyfikacji nie wpływa na wysyłkę, spam nie kasuje rekordu, ponowne uruchomienie bez drugiego promptu, limit budżetu, filtr i odmowa dostępu, migracja up/down.

## Wymagane zgody

| Etap | Co wymaga zgody |
|---|---|
| 0 | zależność `laravel/ai` (wpis w [rejestrze](07-package-decision-record.md)), sekret `ANTHROPIC_API_KEY`, rozszerzenie DS „Zaproponuj”, nowy `AuditAction` |
| 6 | migracja i decyzja o wysyłaniu PII do dostawcy |

Etapy 1–5 nie wymagają migracji ani zmian ról.

## Poza zakresem

Szkic całej strony z briefu, wyszukiwanie semantyczne (embeddingi), czat z asystentem i pamięć rozmów. Wracamy do nich po etapach 0–5, jeśli koszt i jakość się sprawdzą.
