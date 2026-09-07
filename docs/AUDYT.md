# Audyt projektu — narzędzie własne

Ten projekt ma **własne narzędzie audytujące cały pakiet naraz**: trzy wtyczki
i trzy zestawy tabel. Nie jest wtyczką WordPressa i nie trafia do klienta, więc
żyje na osobnej gałęzi [`audyt-projektu`](https://github.com/krzysiek2115op/mp-offer-automation-suite/tree/audyt-projektu), w katalogu `audyt/`.

Opis mieszkał wcześniej w README. README ma sprzedać projekt w trzydzieści sekund,
a nie opowiedzieć historię jego kontroli — stąd osobny plik.

---

## Po co powstało

Audyt końcowy z 29.07.2026 wykazał **8 błędów krytycznych** w kodzie, który
przechodził komplet testów końcowych. To nie był przypadek: test potwierdza to,
co autorowi przyszło do głowy sprawdzić, i nic ponadto. Narzędzie powstało po to,
żeby szukać rzeczy, o których nikt nie pomyślał — i żeby dało się to powtórzyć
przy każdej kolejnej zmianie, zamiast czytać 20 tysięcy linii od nowa.

## Jak działa

**37 par „agent + krytyk"** w dwóch działach:

| Dział | Rola |
|---|---|
| 1 — Audyt (26 par) | szuka problemów; celowo nadgorliwy, woli zgłosić za dużo niż przeoczyć |
| 2 — Re-audyt (11 par) | weryfikuje **każde** zgłoszenie drugą metodą, odsiewa fałszywe alarmy, ocenia sam werdykt |

Agent zbiera dowody, krytyk je ocenia. Ustalenie, którego Dział 2 nie potwierdzi
niezależnie, nie trafia do raportu jako fakt — dostaje status hipotezy razem
z uzasadnieniem obu stron. Narzędzie wystawia `git worktree` i audytuje
**stan w repozytorium**, a nie to, co akurat leży na dysku — domyślnie trzy
gałęzie wtyczek, a z przełącznikiem `--ref=refs/heads/main` scalonego `main`,
czyli kod, który faktycznie idzie do wydania.

## Trzy głębokości

| Poziom | Co dochodzi | Czas na tym repo |
|---|---|---|
| `szybki` | 22 pary analizy statycznej | ~1 s |
| `pelny` | `php -l`, PHPCS/WPCS, archeologia gitowa, powtórzony przebieg Działu 1 | ~90 s |
| `gleboki` | ocena modelu w parach 1.25, 1.26, 2.9 i 2.11 | ~45 min |

```sh
php audyt/bin/audyt.php --repo=/sciezka/do/repo --glebokosc=pelny
```

Kod wyjścia `1` przy ustaleniach krytycznych — nadaje się do CI. **Pominięcie pary
nie jest jej zaliczeniem:** werdykt po skróconym przebiegu dostaje dopisek „audyt
skrócony", żeby „GO" nie czytało się identycznie w obu przypadkach.

## Rejestr znanych błędów

`audyt/rejestr/znane-bledy.json` — **38 błędów, które w tym projekcie naprawdę
wystąpiły**, każdy z klasą pomyłki, dowodem z kodu, skutkiem dla użytkownika
i wskazaniem testu regresji (35 z 38 ma taki test; pozostałe 3 to pozycje
otwarte i narzędziowe, wymienione w raporcie z nazwy). Para 1.15 sprawdza, czy
każdy wpis nadal ma pokrycie — a para 2.6, czy test przyszedł razem z naprawą.

## Wynik

Ostatni przebieg (`--glebokosc=pelny`, 33 z 37 par): **WERDYKT GO** — zero ustaleń
krytycznych, średnich i drobnych; 8 obserwacji, wszystkie z pary 2.7 („żadna para
nie otworzyła tego pliku" — narzędzia testowe poza zakresem reguł).

Uczciwa uwaga o ograniczeniu narzędzia, wynikająca z pomiaru na pełnych
przebiegach głębokich: pary **1.25** i **1.26** pytają model, więc każdy przebieg
próbkuje inny wycinek kodu (43 i 55 ustaleń, wspólnych 17). Są **generatorem
hipotez**, nie listą defektów — każde ich zgłoszenie wymaga ręcznej weryfikacji.
Bramką odbioru są pary deterministyczne, których wynik był identyczny w każdym
przebiegu.

Warto wiedzieć, do czego to prowadzi w praktyce. Ustalenia ze statusem
`prawdopodobne` z tej warstwy zostały początkowo uznane za szum wynikający
z owej niepowtarzalności — błędnie. Niepowtarzalne jest to, **czy** ustalenie się
pojawi, a nie to, czy jest prawdziwe. Przegląd tej warstwy przeprowadzony
31.07.2026 potwierdził testem sześć realnych defektów, w tym niepełną
anonimizację RODO i możliwość dwukrotnej wysyłki tej samej oferty. Wszystkie
zamknięte w wydaniu 1.3.3, a ostatnie cztery ustalenia z tej fali — w **1.3.4**
(01.08.2026): krytyk maszyny statusów, który sprawdzał wynik agenta zamiast jego
wejścia; dedup czytający brak danych jako potwierdzoną unikalność; komunikat
panelu brany z parametru adresu; kolumna zadeklarowana w schemacie, której kod
nigdy nie używał.

Wydanie **1.3.5** (01.08.2026) zamyka kolejnych siedem ustaleń, w tym dwa
o ciężarze prawnym: wtyczka 3 realizowała prawo do usunięcia danych, ale nie
prawo do ich wydania — żądanie „Eksportuj dane osobowe" pomijało ją w ciszy,
więc raport dla klienta wyglądał na kompletny; do tego jej teksty nie były
w ogóle przygotowywane do tłumaczenia. Reszta to odczyt archiwum firm mylący
brak wpisu z awarią zapytania, strażnik VIES pytający o obecność pola zamiast
o werdykt, dwie strefy czasowe w jednym wierszu bazy oraz dwa testy, które
zaliczały się także przy zepsutej funkcji.

Przy tej okazji wyszła rzecz o samym narzędziu i trzeba ją powiedzieć wprost:
para weryfikująca ustalenia sprawdzała ich miejsca funkcją rozpoznającą wyłącznie
pliki, więc **każde ustalenie wskazujące katalog było z góry odrzucane jako
fałszywy alarm**. Milczały przez to całe rodziny kontroli — między innymi ta od
RODO. Bezpiecznik na taki wypadek istnieje, ale odpala się powyżej 50%
nieodnalezionych miejsc, a odrzuceń było 42%: przeszło tuż pod progiem. Po
naprawie treść raportu urosła z 14 do 35 ustaleń przy tym samym kodzie. Raport
podaje teraz bilans (zgłoszone = odrzucone + w treści), żeby takiej różnicy nie
dało się już przeoczyć.

Wydanie **1.3.6** (01.08.2026) domyka falę ustaleń średniej wagi z tego samego
przebiegu. Najszerszą zmianą jest przyjmowanie numerów VAT z **całej Unii**:
formularz od początku pozwalał wybrać kraj, ale sprawdzanie żądało dziesięciu
cyfr, czyli reguły polskiej, więc firma z Niemiec czy Czech odbijała się od
komunikatu o błędnym numerze — poprawnie przepisanym z własnej faktury. Reszta
architektury była już wielokrajowa (VIES pytany osobno dla każdego kraju, klucz
niepowtarzalności `(kraj, numer)`, przydział handlowca po kraju); zamknięta była
sama bramka wejścia. Świadomie **nie** powstała tablica formatów 27 państw —
byłaby drugim, konkurencyjnym źródłem prawdy obok VIES, który i tak orzeka
ostatecznie. Subtelniejsza połowa naprawy dotyczyła czyszczenia numeru: w siedmiu
miejscach usuwało ono wszystko poza cyframi, więc numer niderlandzki czy irlandzki
tracił litery i do VIES szedł numer, którego nikt nie wpisał.

Poza tym: cena ujemna, która przy cenniku **brutto** trafiała na dokument
handlowy (kontrola stała za przeliczeniem, a to nie zachowuje znaku); podstawa
opodatkowania niepilnowana przy odwrotnym obciążeniu, eksporcie i zwolnieniu;
doba Białej listy MF liczona strefą ustawioną w panelu zamiast polską; dwa
komunikaty administratora mówiące o czynnościach, których nie było; oraz
znaczniki powiadomień wyszukiwane w tekście **po** podstawieniu zmiennych, przez
co klamry w nazwie firmy wstrzymywały wysyłkę i blokowały zmianę statusu.

Wniosek, który wart jest zapamiętania bardziej niż sam werdykt: **„GO" znaczy
„nie wróciło nic, co już znamy"** — nie „nie ma błędów". Deterministyczne pary
to siatka regresyjna zbudowana z historii pomyłek tego projektu; nie zastąpią
czytania kodu ze zrozumieniem. A jak pokazały 1.3.5 i 1.3.6 — potrafią też
milczeć albo krzyczeć z powodu własnej wady, więc i one bywają audytowane.
W 1.3.6 zawężono dwie reguły produkujące fałszywe alarmy: para cyklu życia
czytała z `wp_schedule_event()` **częstotliwość** zamiast nazwy zadania — żądała
więc sprzątnięcia haka „daily" i zarazem nie widziała haków prawdziwych, czyli
przeoczyłaby zadanie faktycznie niesprzątnięte; a para RODO żądała rozpoznania
anonimizacji od wtyczki, która kolumnę adresu **zeruje**, czyli nie zostawia nic,
co dałoby się wziąć za prawdziwy adres.

Raporty z kolejnych przebiegów leżą w `audyt/raport-*.txt` na gałęzi
`audyt-projektu`, a szczegółowy opis narzędzia — w `audyt/README.md`.

---
