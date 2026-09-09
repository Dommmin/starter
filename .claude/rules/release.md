# Reguły releasu

## Cel

Produkcyjne zmiany wykonuje człowiek na podstawie zweryfikowanych dowodów. Szczegóły: [DevOps/release](../../docs/foundation/06-devops-observability-and-release.md).

## Decyzje

AI nie merge’uje do chronionego brancha, nie zatwierdza własnej pracy, nie deployuje i nie rollbackuje produkcji. Nie obchodzi review, tests, approval ani zabezpieczeń platformy. Release agent przygotowuje manifest i checklistę; człowiek zatwierdza i inicjuje produkcję. Staging tylko w zakresie osobno autoryzowanego zadania.

## Uzasadnienie

Promuj to samo archiwum release’u z manifestem, commit SHA i sumą SHA-256 przez staging i production; nie używaj ruchomego brancha jako dowodu wydania. Chronione workflows, osobne tożsamości i brak credentiali u agenta muszą wymuszać ograniczenia. Sprawdź rzeczywistą dostępność required reviewers dla planu GitHub; samo workflow_dispatch nie jest approval.

## Ryzyka

Migracje wymagają wyraźnej zgody przed zmianą, kompatybilności wstecz i planu expand/contract. Przed ryzykowną migracją świeży backup i dowód restore. Rollback kodu nie cofa automatycznie schematu. Nie uruchamiaj migrate:rollback ani key:generate jako zwykłej procedury wdrożenia.

## Checklista

- [ ] CI i niezależny reviewer człowiek zaakceptowali zmianę.
- [ ] Staging sprawdził manifest i sumę archiwum; approval nadal dotyczy tego artefaktu.
- [ ] Backup, kompatybilność starego kodu/jobów i runbook są dostępne.
- [ ] Człowiek wdraża; smoke i alerty potwierdzają wynik.
- [ ] Brak prod credentiali i możliwości obejścia dla konta AI.

## Otwarte pytania

Nieznany operator, niedostępny approval, brak restore lub nierozstrzygnięty wpływ migracji blokuje produkcję. Zgłoś konkretny brak i przygotuj dowody możliwe w bezpiecznym środowisku.

Automatyczny rollback w ograniczonym oknie zatwierdzonego deployu wykonuje deterministyczna recepta Deployer zgodnie z ADR-022 w `docs/foundation/10-local-environment-deployment-and-logs.md`. Nie jest to samodzielna decyzja agenta. Poza tym oknem decyzję podejmuje operator; danych i migracji automat nie cofa.
