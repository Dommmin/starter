# Katalog komponentów panelu, aplikacji i stron

## Status i sposób realizacji

Wymaganie użytkownika z 2026-09-11: zbudować we własnym repo szeroki zestaw komponentów wielokrotnego użycia, obejmujący funkcje panelu Filament, marketplace i gotowych zestawów frontendowych. Zewnętrzne produkty są wyłącznie punktem odniesienia dla zachowania, zakresu i jakości interakcji. Nie kupujemy ich, nie kopiujemy ich kodu, nie instalujemy pełnych kitów i nie uzależniamy publicznego API od zewnętrznego dostawcy. To katalog docelowy i backlog, nie deklaracja wdrożenia ani zgoda na instalację nowych zależności. Etapy poniżej są proponowaną kolejnością realizacji.

Zachowujemy React + TypeScript + Inertia oraz publiczne API design systemu z [05](05-design-system-and-frontend.md). Filament, Shadcn Admin Kit, Shopify Polaris i pluginy marketplace są punktami odniesienia funkcjonalnego, nie źródłem kodu. Każdy element budujemy jako własny komponent i kompozycję React, z własnymi tokenami, tłumaczeniami, routingiem i kontraktami. Nie budujemy drugiego renderera ani klona PHP API Filament.

P0 oznacza zestaw potrzebny w pilotażu; P1 — rozbudowę biblioteki po fundamencie; P2 — specjalistyczne elementy uruchamiane przez potrzebę konkretnej aplikacji. Pełny katalog powstaje w dokumentacji od początku, kod etapami. P2 pozostaje widocznym zakresem do oceny, a nie obietnicą wdrożenia przed pierwszym klientem. Nazwy poniżej są propozycjami publicznego API; przed implementacją należy zmapować istniejące odpowiedniki.

## Kolejność wdrażania

| Klasa | Znaczenie | Komponenty i kompozycje |
| --- | --- | --- |
| **Obowiązkowe — P0** | Budujemy w starterze przed uznaniem design systemu za używalny dla panelu i stron publicznych. | Podstawy UI (ADM-01), układ i nawigacja (ADM-02, ADM-15), podstawowe pola tekstowe i wybór wartości (ADM-03–04), formularz jako całość (ADM-08), lista zasobów z wyszukiwaniem, filtrowaniem, sortowaniem i paginacją (ADM-09), podstawowe akcje (ADM-12), podstawowy widok rekordu (ADM-11), feedback i stany błędów (ADM-16, APP-09), podstawowe sekcje publiczne i kontakt (WEB-01, WEB-02, WEB-05). |
| **Planowane — P1** | Budujemy jako kolejną warstwę wspólnej biblioteki, gdy P0 ma zamknięte API i odbiór. | Zaawansowany wybór danych, daty i zakresy (ADM-04–05), rich text i media po polityce bezpieczeństwa (ADM-06), pola złożone i wizard (ADM-07–08), formatery i zapisane widoki tabel (ADM-09–10), operacje zbiorcze/import/eksport (ADM-13), relacje zasobów (ADM-14), command palette, centrum powiadomień i dashboard (ADM-15–17), onboarding, historia aktywności, drzewo/listy, pliki, raporty, ustawienia, wyszukiwanie i portal (APP-01–02, APP-04–05, APP-07–08, APP-11, WEB-03–04, WEB-06–07, WEB-09–10). |
| **Opcjonalne — P2** | Rozpoczynamy tylko dla potwierdzonej potrzeby produktu; wymagają modelu domenowego, backendu i kryteriów odbioru dla danego wdrożenia. | Custom fields i ogólny builder (ADM-07), ciężka tabela i personalizowany dashboard (ADM-10, ADM-17), multi-tenancy (ADM-18), rezerwacje i scheduler (APP-03), Kanban/Gantt (APP-04), image cropper i wersje plików (APP-05), mapy (APP-06), billing/handel (APP-10, WEB-08), realtime collaboration oraz zaawansowane multimedia i edytory. |

P0 nie oznacza „wszystkie warianty”. Przykładowo obowiązkowy `DataTable` ma filtrowanie, sortowanie, paginację i akcje wiersza; przypinanie kolumn, agregaty i edycja komórek należą do P1/P2. Analogicznie P0 obejmuje zwykły Select i podstawową walidację formularza, natomiast AsyncCombobox, MultiSelect, DateRangePicker i Wizard należą do P1.

## Panel: mapa funkcji Filament i nasze odpowiedniki

Punkt odniesienia zweryfikowany 2026-09-10: [Filament 5 — rodziny funkcji](https://filamentphp.com/docs/5.x/introduction/overview), [pola formularzy](https://filamentphp.com/docs/5.x/forms/overview), [tabele](https://filamentphp.com/docs/5.x/tables/overview), [infolists](https://filamentphp.com/docs/5.x/infolists/overview). Katalog obejmuje rodziny funkcji; nie stanowi gwarancji zgodności wszystkich opcji API ani wszystkich przyszłych pluginów.

| ID | Rodzina / odpowiedniki | Docelowy zakres | Etap |
| --- | --- | --- | --- |
| ADM-01 | Podstawy UI | Button, IconButton, Link, Avatar, Badge, Icon, Separator, Tooltip, Spinner, Skeleton, Progress, Alert/Callout, EmptyState. | P0 podstawy; P1 uzupełnienia |
| ADM-02 | Układy i schematy | Stack, Grid, Container, Section, Fieldset, Card, Tabs, Accordion/Collapsible, SplitLayout, Wizard/Stepper; responsywne sekcje i opis kroku. | P0 układ; P1 wizard |
| ADM-03 | Pola tekstowe | Field z label/help/error, TextInput, Textarea, PasswordInput, NumberInput, EmailInput, UrlInput, PhoneInput, MoneyInput, maski, prefiks/sufiks, kopiowanie wartości. | P0 podstawy; P1 specjalizacje |
| ADM-04 | Wybór wartości | Select, AsyncCombobox, MultiSelect, Checkbox, CheckboxGroup, RadioGroup, Switch, ToggleGroup, TagsInput; zależne pola i wyszukiwanie relacji. | P0 podstawy; P1 pozostałe |
| ADM-05 | Data i zakresy | DatePicker, TimePicker, DateTimePicker, DateRangePicker, Slider/Range; format locale, strefa czasowa i granice wartości. | P1 |
| ADM-06 | Treść i media | RichTextEditor, MediaPicker; później FileUpload/Dropzone, MarkdownEditor, CodeEditor, ColorPicker. Kolor edytowanej treści nie daje dostępu do stylowania UI. | P0 edytor i DAM; P1/P2 specjalizacje |
| ADM-07 | Dane złożone | Repeater, KeyValueEditor, pola warunkowe, zależne selekty, tablice błędów, kontrolowane bloki treści; hidden jako mechanizm danych, nigdy autoryzacja. | P1; ogólny Builder P2 |
| ADM-08 | Formularz jako całość | FormSection, FormActions, ErrorSummary, walidacja podczas edycji, create/edit, pending, niezapisane zmiany, reset, konflikt zapisu; w P1 formularz wieloetapowy. | P0/P1 |
| ADM-09 | Tabela | DataTable, SearchInput, FilterBar, FilterChips, Pagination; tekst, liczby, waluty, daty, status, ikona, obraz i kolor w komórkach; serwerowe filtrowanie i sortowanie. | P0 podstawy; P1 formatery |
| ADM-10 | Zaawansowana tabela | ColumnPicker, zapisane widoki, wiele sortowań, grupowanie, agregaty, przypinanie i kolejność kolumn, rozwijane wiersze, edycja komórek, reorder; osobna ocena wirtualizacji dużych zbiorów. | P1 widoki/akcje; P2 ciężkie warianty |
| ADM-11 | Widok rekordu / infolist | RecordDetails, DescriptionList, TextEntry, IconEntry, ImageEntry, ColorEntry, CodeEntry, KeyValueEntry, RepeatableEntry; link, kopiowanie, placeholder brakującej wartości. | P0 podstawowy widok; P1 reszta |
| ADM-12 | Akcje | ActionMenu, ConfirmDialog, FormDialog, Drawer/SlideOver; create/edit/view/delete, duplicate, restore, trwałe usunięcie; potwierdzenia i stan wykonania. | P0 podstawy; P1 rozszerzenia |
| ADM-13 | Operacje na zbiorach | BulkActions, zaznaczenie strony lub całego wyniku, ImportWizard, ExportDialog, mapowanie kolumn, podgląd i raport błędów, JobProgress, pobranie wyniku. | P1 po zamówieniu importu/eksportu |
| ADM-14 | Zasoby i relacje | ResourceList/Create/Edit/Show, RelationManager, attach/detach, zasoby zagnieżdżone, pojedynczy ekran ustawień, sloty akcji i widgetów, generator sprawdzonego CRUD. | P0 wzorzec; P1 generator/relacje |
| ADM-15 | Nawigacja panelu | AdminShell, Sidebar, Topbar, Breadcrumbs, UserMenu, grupy nawigacji, GlobalSearch/CommandPalette, własne strony; przełącznik panelu lub organizacji dopiero z modelem dostępu. | P0 shell; P1 search; P2 organizacje |
| ADM-16 | Powiadomienia | Toast, InlineFeedback, NotificationCenter, unread badge, oznaczanie przeczytanych; trwałe i realtime powiadomienia, preferencje kanałów. | P0 feedback; P1 centrum; P2 realtime |
| ADM-17 | Widgety | StatCard, ChartCard, TableWidget, filtry zakresu, trend i opis metryki; konfigurowalna siatka dashboardu po potrzebie. | P1 metryki; P2 personalizacja |
| ADM-18 | Konto i dostęp | Kompozycje auth, profil, sesje, MFA/passkeys, macierz uprawnień; multi-tenancy jako oddzielny zakres backendu, nie sam przełącznik UI. | Obecny plan auth P0; role dynamiczne P1; tenancy P2 |

Przed uznaniem pokrycia danej wersji Filament za kompletne wykonujemy porównanie jej indeksu dokumentacji z ADM-01–18. Każda nowa funkcja otrzymuje mapowanie do istniejącego elementu, nową pozycję backlogu albo jawne uzasadnienie pominięcia. Nie kopiujemy mechanizmów frameworka, takich jak render hooks i generowanie PHP, gdy ich rolę pełni kompozycja React oraz nasz generator.

## Dodatkowe komponenty często potrzebne w aplikacjach

Poniższy zakres to propozycja startera, niezależna od tego, czy odpowiednik występuje w core lub pluginie Filament.

| ID | Zestaw | Komponenty / kompozycje | Etap i warunek |
| --- | --- | --- | --- |
| APP-01 | Onboarding | Checklist, WelcomePanel, SetupWizard, kontekstowa pomoc, stan ukończenia konfiguracji. | P1 |
| APP-02 | Aktywność i współpraca | Timeline, AuditDiff, ActivityFeed, Comments, Mentions, Attachments, avatar grupy uczestników. | P1 historia; P2 współpraca |
| APP-03 | Kalendarz i rezerwacje | Calendar, Agenda, TimeSlotPicker, BookingSummary; strefy czasowe, konflikty i niedostępne terminy. | P2 dla rezerwacji |
| APP-04 | Organizacja pracy | Kanban, SortableList, TreeView/TreeSelect, status procesu, harmonogram/Gantt. | P1 drzewo/lista; P2 Kanban/Gantt |
| APP-05 | Pliki | FileBrowser, FilePreview, Gallery, ImageCropper, UploadQueue, wersje pliku i wybór istniejącego zasobu. | DAM P0; rozszerzenia P1/P2 |
| APP-06 | Dane lokalizacyjne | AddressField, AddressAutocomplete, MapPicker, mapa wyników i lista lokalizacji. | P2; osobna decyzja o danych geograficznych i źródle map |
| APP-07 | Dokumenty i raporty | ReportFilters, PDFPreview, DownloadList, podsumowanie eksportu, widok dokumentu do wydruku. | P1/P2 po potrzebie |
| APP-08 | Ustawienia i integracje | SettingsSection, PreferenceSwitch, IntegrationCard, ConnectionStatus, CredentialField z maskowaniem. | P1; sekrety i połączenia wymagają backendu |
| APP-09 | Stany procesu | OfflineBanner, RetryPanel, ConflictDialog, SessionExpired, PermissionDenied, NotFound, Maintenance, AsyncJobStatus. | P0 podstawowe błędy; P1 reszta |
| APP-10 | Rozliczenia i handel | PlanSelector, UsageMeter, InvoiceList, SubscriptionSummary, CheckoutSummary, OrderStatus. | P2; UI nie oznacza wdrożenia płatności |
| APP-11 | Wyszukiwanie i pomoc | SearchResults, FacetedFilters, HelpCenter, CommandPalette, skróty klawiaturowe. | P1/P2 |

## Wzorce funkcjonalne z Filament i innych paneli

Źródła sprawdzone 2026-09-11 służą tylko do zdefiniowania kryteriów odbioru własnego kodu. Nie są shortlistą zakupową ani techniczną zależnością.

| Wzorzec | Własny zakres, który realizujemy |
| --- | --- |
| Advanced Tables | Zapisane widoki, szybkie filtry, widoczność i kolejność kolumn, wiele sortowań, selekcja oraz akcje zbiorcze w ADM-10. |
| Custom Dashboards | Układ widgetów, wybór dashboardu i współdzielenie widoku w ADM-17; dane i uprawnienia zapewnia nasz backend. |
| Custom Fields | Definicje pól, renderer, warunkowość, walidacja, zapis i filtrowanie w ADM-07; bez Filament/Livewire. |
| Media, kalendarze, Kanban, drzewo, mapy, podpisy, zaawansowane edytory | Własne interakcje dla ADM-05–07 i APP-03–06, uruchamiane według kolejności katalogu. |
| Uprawnienia, audyt, tłumaczenia, SEO, ustawienia, import/eksport | Własne ekrany i kontrakty nad już planowanym backendem, bez dublowania frameworka. |

## Frontend: gotowe sekcje i kompozycje

Publiczna strona i portal klienta korzystają z tych samych prymitywów, ale mają własne kompozycje. Docelowo wybieramy wariant sekcji i przekazujemy typowane dane zamiast kopiować JSX całej strony. Każda sekcja określa wymagane treści, obrazy, akcje i dostępne warianty; dowolny HTML/CSS z CMS nie jest częścią tego kontraktu.

| ID | Rodzina | Gotowe kompozycje | Etap |
| --- | --- | --- | --- |
| WEB-01 | Nawigacja | Header, MobileNav, MegaMenu, Breadcrumbs, Footer, LanguageSwitcher, AnnouncementBar. | P0 podstawa; P1 warianty |
| WEB-02 | Landing / oferta | Hero z tekstem/obrazem/wideo, FeatureGrid, FeatureSplit, Benefits, LogoCloud, CTA, Comparison, Pricing, statystyki z prawdziwych danych. | P0 elementy pilota; P1 katalog |
| WEB-03 | Wiarygodność | Testimonials, CaseStudyCard/Detail, Team, Partners, certyfikaty i logotypy referencyjne. | P1 |
| WEB-04 | Treści | ArticleCard/List/Detail, AuthorBio, TableOfContents, RelatedContent, kategorie/tagi, wyniki wyszukiwania, paginacja. | P0 artykuły; P1 reszta |
| WEB-05 | Usługi i kontakt | ServiceCard/Detail, ProcessSteps, FAQ, ContactSection, formularz zapytania, lokalizacje i godziny pracy. | P0 kontakt; P1 reszta |
| WEB-06 | Media | Gallery, Lightbox, VideoEmbed, Downloads, kontrolowana karuzela, porównanie obrazów. | P1; bez zbędnego JS na stronach bez tych sekcji |
| WEB-07 | Konwersja i komunikaty | NewsletterForm, LeadForm, SuccessPanel, komunikat błędu, ConsentPreferences, CookieBanner. | P1 według integracji i ustalonego modelu zgód |
| WEB-08 | Katalog i handel | ProductCard/Grid/Detail, filtry, warianty, dostępność, CartDrawer, koszyk, podsumowanie zamówienia. | P2 dla e-commerce |
| WEB-09 | Portal użytkownika | AccountLayout, profil, ustawienia, dokumenty, historia zamówień/rezerwacji, wsparcie i powiadomienia. | P1/P2 dla portalu |
| WEB-10 | Całe szablony stron | Strona firmowa, usługa, landing kampanii, cennik, kontakt, blog, case study, katalog, portal. | P0 pilot; P1 kompozycje zatwierdzonych sekcji |

## Własne kompozycje frontendowe

WEB-01–10 powstają od podstaw w naszym design systemie. Wzorce z Shadcn Admin Kit, Shopify Polaris, Filament, Shadcnblocks i Tailwind Plus można oglądać wyłącznie w celu analizy problemu, hierarchii informacji, stanów i dostępności. Nie wolno kopiować ich kodu, assetów, nazw handlowych ani wprowadzać ich routingów, providerów danych czy licencjonowanych fragmentów do startera.

Każda inspiracja zewnętrzna zostaje opisana w zadaniu implementacyjnym jako link referencyjny i lista obserwowalnych zachowań. Do wspólnego repo trafia wyłącznie kod napisany w tym repo, z publicznym API naszego DS.

## Rejestr gotowości i łatwe używanie

Wykonawczy rejestr per ID: [13 — rejestr komponentów](13-component-registry.md). Aktualizowany podczas realizacji; nie zastępuje tego dokumentu jako źródła zakresu.

Każdy ID rozwijamy podczas realizacji na osobne rekordy komponentów. Rekord zawiera: nazwę i aliasy wyszukiwania, rodzinę, zastosowanie, etap, status, linki do wzorców funkcjonalnych, właściciela, publiczny import, typowane props i warianty, wymagania backendu, przykład użycia, demo stanów, dowody odbioru i datę przeglądu. Statusy: `planned`, `implemented`, `verified`, `deprecated`. Wpisy tego dokumentu mają status `planned`; istniejący plik shadcn nie otrzymuje automatycznie statusu `verified`.

P0: zinwentaryzować istniejący kod, przypisać go do ID i udostępnić demonstrację gotowego podzbioru local/staging. P1: rozbudować przeszukiwalny katalog z kategoriami panel/aplikacja/strona, podglądem wariantów, gotowym przykładem importu i listą zależności. Storybook jest opcjonalny zgodnie z 05.

Odbiór `verified` wymaga: działającego przykładu i zamkniętego API DS, tłumaczeń, light/dark, RWD od 360 px, obsługi klawiatury i focus, potrzebnych stanów loading/empty/error/disabled, odpowiednich testów zachowania oraz SSR bez błędu hydratacji dla elementów publicznych. Ciężkie edytory, mapy i wykresy ładować tylko tam, gdzie są używane. Dla kompozycji zapewnić warianty krótkich/długich treści i brakujących mediów. Kryteria backendu, bezpieczeństwa i jakości z 02/03/05 nadal obowiązują.

Przed tworzeniem ekranu: wyszukać ID/nazwę/zastosowanie → użyć `verified` komponentu → dobrać kompozycję → w razie luki rozszerzyć katalog i uzgodnić wariant zgodnie z 05. Poprawkę wspólnego zachowania wprowadzać w komponencie, nie w kopiach ekranów. Generator CRUD i szablony stron mają korzystać z tego samego publicznego API.
