# Praca w tym repo

Konkrety specyficzne dla tego projektu. Ogólny standard higieny repozytoriów
opisuje osobny dokument w repo `zlecenia-automatyzacja`; tutaj jest to, co
wynika z tego, że produktem są **wtyczki WordPressa**, a nie aplikacja.

## Zanim cokolwiek zmienisz

```bash
composer install          # PHPCS + WPCS — narzędzia dev, NIE zależności wtyczek
composer exec phpcs       # reguły z .phpcs.xml.dist, trzy katalogi naraz
```

`composer.json` w korzeniu opisuje **wyłącznie narzędzia deweloperskie**.
Zależności produkcyjne wtyczki 2 (dompdf i spółka) leżą zacommitowane
w `mp-offer-builder/vendor/`, bo klient wgrywa paczkę ZIP, a nie uruchamia
Composera na hostingu współdzielonym.

## Workflow

```
branch → commit(y) → push → PR → CI zielone → merge → tag + release
```

1. **Branch** od `main`. Nazwa: `fix/<opis>` dla napraw, `feat/<opis>` dla nowych
   rzeczy, `docs/<opis>` dla samej dokumentacji.
2. **Commity** po polsku, przy każdym większym kroku.
3. **PR** z opisem CO i PO CO. CI musi być zielone przed scaleniem.
4. **Merge** — squash dla drobnicy, merge commit dla większych całości.
5. **Release** — po każdym większym kroku: wpis w [`CHANGELOG.md`](CHANGELOG.md),
   podbicie `Version:` w nagłówku każdej zmienionej wtyczki, tag `vX.Y.Z`
   i release na GitHubie z opisem z changeloga.

**Wersja w nagłówku wtyczki i wpis w changelogu muszą się zgadzać.** WordPress
czyta `Version:` z komentarza nagłówkowego i po nim decyduje o aktualizacji —
rozjazd znaczy, że klient nie dostanie poprawki, którą właśnie wydałeś.

## Wersjonowanie

[SemVer](https://semver.org/lang/pl/). Trzy wtyczki wydawane są **razem** i noszą
ten sam numer, bo rozmawiają ze sobą zdarzeniami — wersja pakietu opisuje stan
kontraktu między nimi, a nie stan pojedynczego pliku.

Zmiana nazwy albo argumentów któregokolwiek z czterech haków
(`mp_lead_created`, `mp_lead_verified`, `mp_offer_created`, `mp_offer_approved`)
łamie zgodność i wymaga wpisu w changelogu **wszystkich** stron, których dotyczy.

## Konwencja commitów

**Temat commita opisuje SKUTEK, nie czynność** — po polsku, jedno zdanie, tak żeby
historia dała się czytać jak dziennik projektu.

Dobrze:

```
Pole formularza wyslane jako tablica przestaje wywracac wtyczke
Firma z Niemiec moze wreszcie podac swoj numer VAT
Oferta przestaje obiecywac gwarancje, ktorej nie realizujemy
```

Źle: `fix`, `poprawki`, `update README`, `dodano walidacje`.

**Ciało commita niesie dowody:** co sprawdzone i czym. Kody wyjścia **bez potoku** —
`skrypt | tail` maskuje kod wyjścia i zielony ogon zasłania czerwony wynik.

## Testy

| Warstwa | Gdzie | W CI? |
|---|---|---|
| Składnia (`php -l`) | wszystkie trzy wtyczki | ✅ PHP 7.4 i 8.3 |
| PHPCS/WPCS | `.phpcs.xml.dist` | ✅ |
| Harness procesu wtyczki 1 | `mp-lead-intake/tests/process-harness/` | ✅ 7 scenariuszy |
| Harness procesu wtyczki 2 | `mp-offer-builder/tests/process-harness/` | ✅ 110 niezmienników |
| Testy wtyczki 3 i testy końcowe 1–2 | wymagają żywego WordPressa z WooCommerce | ❌ — `tools/test-env/` |

Testy spoza CI nie są opcjonalne, tylko **uruchamiane ręcznie**; PR, który ich
dotyczy, ma wkleić wynik w opisie.

## Audyt

Narzędzie audytujące żyje na gałęzi `audyt-projektu`
([opis](docs/AUDYT.md)). Nie jest częścią dostawy dla klienta i świadomie nie ma
go w `main`. Przy większych zmianach warto przepuścić kod przez `--glebokosc=pelny`
— to ~90 sekund.

## Czego nie robimy

- **Nie wyłączamy kontroli, żeby przepchnąć zmianę.** Czerwone PHPCS to informacja
  o błędzie, nie przeszkoda do obejścia.
- **Nie dodajemy zależności produkcyjnych przez Composera.** Klient wgrywa ZIP;
  wszystko, czego wtyczka potrzebuje, musi być w paczce.
- **Nie zmieniamy nazw czterech haków publicznych** bez wpisu w changelogu i podbicia
  wersji minor — integratorzy się na nich opierają (`mp-*/docs/HAKI.md`).
