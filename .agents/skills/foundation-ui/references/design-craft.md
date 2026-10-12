# Standard rzemiosła UI/UX

Kontrakt DS (ADR-019) mówi, czego nie wolno. Ten dokument mówi, jak ma
wyglądać dobry wynik. Poprawny technicznie, ale nijaki ekran jest błędem
jakości, nie „wersją 1”. Celem jest wrażenie produktu dopracowanego przez
zespół produktowy: pewna hierarchia, rytm, ruch z intencją i detale, których
użytkownik nie nazwie, ale je poczuje.

Stosuj przy ekranach publicznych (landing, wizytówka, sekcje home, artykuły)
w pełni; w panelu admina priorytetem jest gęstość, czytelność i szybkość —
z tej listy bierz typografię, stany, mikrointerakcje i pętlę krytyki.

## 1. Brief zanim powstanie kod

Krótkie polecenie użytkownika nie zwalnia z decyzji projektowych — to agent
je podejmuje i jawnie zapisuje (5–10 linijek), zamiast brać pierwszy wzorzec:

- **Odbiorca i jedno główne działanie** na ekranie (np. „umów rozmowę”).
- **Charakter**: 2–3 przymiotniki (np. „techniczny, spokojny, premium”) oraz
  czego unikamy („nie korporacyjny stock, nie neonowy startup”).
- **Referencje**: 2–3 konkretne klasy wzorców (np. Linear — precyzyjna siatka
  i subtelne gradienty; Stripe — warstwowa głębia i animowane detale;
  Vercel — kontrast typograficzny i oszczędność; Apple — sticky storytelling;
  Framer/Raycast — dopracowane hover i mikroruch). Nazwij, *co* bierzesz,
  nie kopiuj marki.
- **Moment „wow”**: jedna, najwyżej dwie sygnaturowe rzeczy na stronę
  (np. hero z warstwową kompozycją i reveal, sekcja liczb z licznikami,
  sticky sekcja procesu, bento z interaktywnymi kafelkami). Reszta spokojna.
- **Mapa sekcji**: kolejność i rola każdej sekcji w narracji
  (problem → dowód → oferta → zaufanie → CTA), nie lista komponentów.
- **Luki DS**: czego brakuje w prymitywach, by to zrealizować → propozycja
  rozszerzenia według `workflow.md` (zatrzymaj implementację do akceptacji).

## 2. Anty-wzorce „AI slop” — każdy to finding

- Wszystkie sekcje o identycznej wysokości, wyśrodkowane, z tym samym
  nagłówkiem + akapitem + 3 kartami w rzędzie.
- Siatka 3 identycznych kart z ikoną w kółku jako domyślna odpowiedź na
  „pokaż usługi/cechy”.
- Hero = wyśrodkowany H1, podtytuł, dwa przyciski, nic więcej.
- Brak kontrastu skali: nagłówki ledwo większe od tekstu, jedna grubość fontu.
- Generyczne copy („Innowacyjne rozwiązania dla Twojego biznesu”),
  lorem ipsum, emoji jako ikony, przypadkowe gradienty fioletowo-różowe.
- Animacja „fade-in wszystkiego” z tym samym opóźnieniem; ruch bez celu.
- Równe, gęste odstępy wszędzie — brak oddechu i grupowania.
- Domyślny wygląd shadcn jako finalna estetyka.
- Brak stanów hover/focus/active/disabled/loading; martwe elementy.
- Mobile jako zgnieciony desktop.

## 3. Typografia i hierarchia

- Wyraźna skala (stosunek ~1.25–1.333); nagłówek display w hero co najmniej
  2,5–3× tekstu body na desktopie, z ciaśniejszym trackingiem i line-height.
- Max. 2 rodziny fontów; różnicuj wagą, rozmiarem i kolorem `muted`, nie
  kolejnymi fontami. Długość linii tekstu 60–75 znaków.
- Eyebrow / overline nad nagłówkiem sekcji, liczby tabelaryczne w statystykach.
- Jedna rzecz najważniejsza na ekran — test zmrużonych oczu: co widać pierwsze?

## 4. Kompozycja i rytm

- Różnicuj układy sekcji: split asymetryczny (7/5), bento o różnych
  rozmiarach kafli, pełna szerokość z mocnym tłem, lista z dużymi numerami,
  sticky kolumna z przewijaną treścią. Sąsiednie sekcje nie mają tej samej
  struktury.
- Rytm pionowy: duże odstępy między sekcjami, małe wewnątrz grup
  (bliskość = przynależność). Kontrolowane łamanie siatki (element wychodzący
  poza kontener, nakładanie warstw) jako akcent, nie norma.
- Głębia przez warstwy: tło sekcji (`surface` vs `background`), subtelne
  obramowania, cienie z tokenów `raised/overlay`, siatka/szum/gradient jako
  token tła — nigdy losowy hex w ekranie.
- Prawdziwa treść: konkretne liczby, nazwy, cytaty z seedera; obrazy
  o zarezerwowanych wymiarach (CLS ≤ 0,1).

## 5. Ruch i mikrointerakcje (to robi wrażenie „trudnego”)

- **Choreografia, nie efekty**: wejście sekcji jako sekwencja (nagłówek →
  treść → media) ze staggerem 40–80 ms; czas 150–250 ms dla UI, 400–700 ms
  dla dużych reveal; easing „out” (np. `cubic-bezier(0.22, 1, 0.36, 1)`),
  nie liniowy.
- Animuj tylko `transform` i `opacity`; zero layout shift; nic nie blokuje
  interakcji ani czytania.
- Sygnaturowe techniki do wyboru (1–2 na stronę): scroll-linked reveal,
  sticky storytelling, licznik liczb przy wejściu w viewport, nieskończony
  marquee logotypów, hover z podniesieniem i przesunięciem światła/obramowania,
  kursorowo-świadome podświetlenie kafli bento, view transitions między
  stronami, płynne rozwijanie akordeonu/FAQ, magnetyczny CTA.
- Każdy interaktywny element ma hover, focus-visible, active i disabled;
  przyciski reagują w < 100 ms; formularz ma pending i success z przejściem.
- `prefers-reduced-motion`: pełna treść bez ruchu (natychmiastowy stan
  końcowy), nie ukryta treść.
- Ruch realizuj przez istniejące prymitywy/tokeny motion (np. reveal
  w sekcjach home); brakującą technikę zgłoś jako rozszerzenie DS. Nowa
  biblioteka animacji = decyzja zależności (dokument 07) i budżet JS.

## 6. Pętla krytyki — obowiązkowa przed raportem

1. Uruchom stronę (`make up`, Vite) i zrób screenshoty: desktop 1440,
   mobile 375, light i dark; dla ruchu — stan przed/po scrollu.
2. Oceń każdą oś w skali 1–5 i zapisz w raporcie:
   hierarchia · kompozycja/rytm · typografia · jakość treści · ruch
   i mikrointerakcje · spójność z marką/tokenami · mobile · a11y (kontrast,
   focus, klawiatura).
3. Poniżej 4 na którejkolwiek osi → popraw i powtórz (maks. 2 rundy, potem
   zgłoś przeszkodę). Sprawdź listę z sekcji 2 — każdy trafiony anty-wzorzec
   to obowiązkowa poprawka.
4. Przy większym zakresie (nowa strona, redesign sekcji) zleć
   `foundation-designer` brief przed kodem i krytykę screenshotów po nim.
   Ocena agenta nie zastępuje akceptacji wizualnej człowieka.

## 7. Granice

Ten standard nie uchyla ADR-019, budżetu JS, a11y ani limitów FAST. Lokalna
poprawka pozostaje lokalna — nie zamieniaj jej w redesign. Nowy token,
wariant lub technika ruchu wymaga propozycji i akceptacji przed kodem.
