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
je podejmuje i jawnie zapisuje (5–10 linijek), zamiast brać pierwszy wzorzec.

**Warunek stopu:** brief trafia do odpowiedzi *przed* pierwszym plikiem,
markupem lub narzędziem, które tworzy UI. Jeśli go nie ma — zatrzymaj się
i napisz go teraz. Każda linia to decyzja: „czysty”, „nowoczesny” albo
„estetyczny” nie są decyzją; „precyzyjny”, „ciepły”, „drogi” są. Bez
nazwanego nastroju i rodziny układów wynik regresuje do średniej.

- **Odbiorca i jedno główne działanie** na ekranie (np. „umów rozmowę”).
- **Charakter**: 2–3 przymiotniki (np. „techniczny, spokojny, premium”), z czego
  jeden to **nastrój**, na który ekran musi zapracować, oraz czego unikamy
  („nie korporacyjny stock, nie neonowy startup”).
- **Głębia ruchu**: brak / subtelny feedback / ekspresyjna choreografia.
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
- Brak lidera: 3–4 równe kafle statystyk, planów cennika lub cech — oko nie
  ma gdzie wylądować. Jeden element dostaje większy rozmiar, wagę albo
  własny rząd; reszta jest cicha.
- Kolorowe obramowanie tylko z jednej strony karty, alertu lub callouta
  (pasek `border-left`) jako domyślny akcent.
- Puste „pudło”: duża karta lub kafel bento bez treści, martwe komórki
  siatki, pusty stan wiszący pod nagłówkiem zamiast zająć swoją przestrzeń;
  strona urywa się zamiast kończyć się celowo (CTA, stopka).
- Copy-wypełniacz: triady abstrakcyjnych przymiotników („szybki, intuicyjny
  i nowoczesny”), czasowniki marketingowe („odblokuj”, „wynieś na wyższy
  poziom”, „bezproblemowo”), fałszywe etykiety („SEKCJA 01”, „Twój nagłówek
  tutaj”), asekuracyjny microcopy („możesz rozważyć…”). Nazwij jedną
  konkretną, sprawdzalną korzyść; zaczynaj od czasownika.

## 3. Typografia i hierarchia

- Wyraźna skala (stosunek ~1.25–1.333); nagłówek display w hero co najmniej
  2,5–3× tekstu body na desktopie, z ciaśniejszym trackingiem i line-height
  (~1.05–1.15). 24 px nad 16 px to pogrubione body, nie display.
- Nagłówek display jest krótki i szeroki: nie łamie się na więcej niż ~3
  linie — skróć copy albo poszerz kontener.
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
  (bliskość = przynależność). Hierarchia nigdy się nie odwraca: padding
  sekcji > padding karty > odstępy elementów. Kontrolowane łamanie siatki (element wychodzący
  poza kontener, nakładanie warstw) jako akcent, nie norma.
- Głębia przez warstwy: tło sekcji (`surface` vs `background`), subtelne
  obramowania, cienie z tokenów `raised/overlay`, siatka/szum/gradient jako
  token tła — nigdy losowy hex w ekranie.
- Prawdziwa treść: konkretne liczby, nazwy, cytaty z seedera; obrazy
  o zarezerwowanych wymiarach (CLS ≤ 0,1).
- Bento tylko z gęstą treścią; siatka matematycznie równa, bez martwych
  komórek.
- Nowa sekcja dokładana do istniejącej strony zmienia **kompozycję**, nie
  **materiały**: te same tokeny, skala, promienie, gęstość, stany i zachowanie
  w dark mode co sąsiednie sekcje.

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
3. Nie podawaj liczby (kontrast, wynik a11y, „wszystkie stany OK”), której
   nie zmierzyłeś narzędziem w tej sesji — napisz „niezweryfikowane”.
   Sprawdzaj stany hover/focus/active, nie tylko spoczynek, oraz brak
   poziomego przewijania przy 320 px. Brak przeglądarki to nie PASS.
4. Poniżej 4 na którejkolwiek osi → popraw i powtórz (maks. 2 rundy, potem
   zgłoś przeszkodę). Sprawdź listę z sekcji 2 — każdy trafiony anty-wzorzec
   to obowiązkowa poprawka.
5. Przy większym zakresie (nowa strona, redesign sekcji) zleć
   `foundation-designer` brief przed kodem i krytykę screenshotów po nim.
   Ocena agenta nie zastępuje akceptacji wizualnej człowieka.

## 7. Granice

Ten standard nie uchyla ADR-019, budżetu JS, a11y ani limitów FAST. Lokalna
poprawka pozostaje lokalna — nie zamieniaj jej w redesign. Nowy token,
wariant lub technika ruchu wymaga propozycji i akceptacji przed kodem.

Źródło części reguł (warunek stopu briefu, brak lidera, jednostronne
obramowanie, puste pudło, copy-wypełniacz, uczciwość pomiaru): doktryna
smaku z `plugin87/ux-ui-agent-skills` v2.9.8 (MIT), zaadaptowana do ADR-019
bez jej systemu tokenów, bramek i hooków.
