To NIE jest wydanie produktu. Wydania produktu maja tagi `v1.3.14`, `mp-lead-intake/v1.3.14` i `mp-sales-workflow/v1.3.14`. Wtyczka 2 nie miala w tym wydaniu zmian i zostaje na `mp-offer-builder/v1.3.12`.

Tutaj leza wylacznie pliki potrzebne stronie pokazowej uruchamianej w WordPress Playground: trzy wtyczki jako ZIP-y gotowe do instalacji oraz motyw Kredyt Kompas.

## Uruchomienie

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/krzysiek2115op/egzamin-koncowy/main/tools/strona-pokazowa/blueprint.json

Instalacja w przegladarce trwa **2-4 minuty**. Ekran „Preparing WordPress" z paskiem postepu to normalny stan, nie zawieszenie. Playground loguje automatycznie jako administrator.

## Konta do sprawdzenia rol

Wyloguj sie z konta administratora (menu w prawym gornym rogu panelu), zeby wejsc na ktores z ponizszych.

| Login | Rola | Kim jest | Haslo |
|---|---|---|---|
| `admin` | Administrator | widzi wszystko | (zalogowany automatycznie) |
| `handlowiec` | Handlowiec | Anna Kowalska, rynek PL — do niej trafil proces demonstracyjny | `demo-egzamin-2026` |
| `handlowiec_de` | Handlowiec | Markus Weber, rynek DE — celowo NIE dostal polskiego procesu | `demo-egzamin-2026` |
| `manager` | Manager sprzedazy | Marian Nowak, widok zespolu | `demo-egzamin-2026` |

Haslo jest jawne swiadomie: instalacja pokazowa zyje w przegladarce ogladajacego i znika z zamknieciem karty. Na wdrozeniu produkcyjnym takie konta nie powstaja — zaklada je administrator recznie (`docs/WDROZENIE.md`).

## Czego demo z natury nie pokaze

- **Poczty** — Playground nie ma serwera SMTP.
- **Follow-upow d+3 / d+7** — wymagaja uplywu czasu i systemowego crona.
- **Podpisanych linkow do oferty** — wymagaja stalej `MP_SW_LINK_KEY` w `wp-config.php`.

Wszystkie trzy sa sprawdzone testami na zywym WordPressie; zapis w `raporty/ODBIOR-1.3.12.md`.
