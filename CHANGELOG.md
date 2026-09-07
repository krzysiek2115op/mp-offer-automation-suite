# Dziennik zmian

Wszystkie istotne zmiany w pakiecie. Format: jedno wydanie = jedna sekcja,
od najnowszego. Wersjonowanie [SemVer](https://semver.org/lang/pl/); trzy wtyczki
wydawane są razem i noszą ten sam numer.

Wpisy opisują **skutek dla użytkownika**, a nie listę zmienionych plików — od tego
jest `git log`. Wydania starsze niż 1.3.7 mają opisy wyłącznie w
[Releases](https://github.com/krzysiek2115op/mp-offer-automation-suite/releases);
przeniesione tu zostały te, które niosą ustalenia warte zapamiętania.

Ten plik powstał z podziału README: notatki wydań zajmowały w nim 387 z 698 linii,
więc pierwszy ekran wizytówki projektu pokazywał changelog zamiast tego, co projekt robi.

---

## 1.3.14 — dwa błędy znalezione przez narzędzia, których nie uruchamialiśmy

*2026-08-12 · [tag `v1.3.14`](https://github.com/krzysiek2115op/mp-offer-automation-suite/releases/tag/v1.3.14)*

Sesja z zewnętrznymi analizatorami: **PHPStan**, **Psalm**, **PHPMD**,
**OSV-Scanner** (Google), **Lighthouse** (Google), **axe-core** i oficjalne
**WordPress Plugin Check**. Znalazły dwa realne błędy — oba niewidoczne dla
100 plików testowych, które ten projekt już miał.

**1. Pole formularza wysłane jako tablica wywracało wtyczkę.** Nadawca decyduje
nie tylko o treści pola, ale i o jego **typie**: `email[]=a@b.test` daje tablicę.
`sanitize_email()` szło z nią prosto do `strlen()` → `TypeError`. Bez logowania,
z publicznego formularza, jednym żądaniem: **HTTP 500**. Jedenaście pozostałych
pól było bezpiecznych *przypadkiem* — `sanitize_text_field()` ma własnego
strażnika przed tablicą, a jedyne pole bez strażnika było polem **wymaganym**.

**2. Lista wyboru statusu nie miała dostępnej nazwy.** Czytnik ekranu ogłaszał
samo „lista rozwijana", a takich list jest tyle, ile wierszy — użytkownik
niewidomy słyszał osiem identycznych kontrolek. Waga `critical`.

### Dwa razy z rzędu narzędzia tej samej klasy się rozjechały

| Błąd | Znalazł | Przeoczył | Dlaczego |
|---|---|---|---|
| `email[]` → 500 | Psalm | PHPStan | inny algorytm wnioskowania o typach |
| `select` bez nazwy | axe-core | Lighthouse | Lighthouse nie umie się **zalogować** |

Drugi rozjazd jest ciekawszy: Lighthouse dał **100/100** za dostępność stron
publicznych i miał rację. Błąd siedział na ekranie, do którego nie ma dostępu.
**Jedno narzędzie danej klasy to za mało** — raz decyduje algorytm, raz zasięg.

### Wyniki pozostałych narzędzi

| Narzędzie | Wynik |
|---|---|
| Lighthouse — strona główna | wydajność 97 · dostępność **100** · praktyki **100** · SEO 91 |
| Lighthouse — formularz | wydajność 97 · dostępność **100** · praktyki **100** · SEO **100** |
| OSV-Scanner (Google) | **0** znanych podatności w wysyłanym kodzie |
| axe-core po naprawie | **0** naruszeń na wszystkich trzech ekranach wtyczek |
| WP Plugin Check | 0 błędów poza językiem `readme.txt` — patrz niżej |

Każde zgłoszenie Lighthouse z sekcji wydajności wskazuje na WooCommerce, motyw
albo jQuery z rdzenia WordPressa — **żadne na kod tych wtyczek**.

**Uwaga o Plugin Check:** narzędzie zgłasza 6 błędów „readme must be written in
standard English". Pliki `readme.txt` są po polsku **świadomie** — produkt jest
dla polskiego klienta i cała dokumentacja jest w jego języku. To wymóg
repozytorium WordPress.org, do którego ta dostawa nie trafia.

**Wtyczka 2 zostaje na 1.3.12** — nie miała w tym wydaniu żadnej zmiany.

---

## 1.3.13 — dwie z trzech ról nie docierały do własnych ekranów

*2026-08-05 · [tag `v1.3.13`](https://github.com/krzysiek2115op/mp-offer-automation-suite/releases/tag/v1.3.13)*

Znalezione **przy odbiorze 1.3.12** — nie czytaniem kodu, tylko wejściem na ekran
prawdziwym logowaniem, na czystej instalacji postawionej z **pobranych** paczek.

`GET /wp-admin/admin.php?page=mp-sales-workflow` zalogowanym handlowcem kończyło
się **302 na `/my-account/`**. Powód stał piętro wyżej niż nasz kod: WooCommerce
filtrem `woocommerce_prevent_admin_access` wyrzuca z panelu każdego bez
`edit_posts` i `manage_woocommerce`. Role sprzedażowe mają `read` i uprawnienia
własne — i tak ma zostać. Skutek: na **każdej** instalacji zgodnej z wymaganiami
produktu (wtyczka 2 wymaga WooCommerce) handlowiec i manager nie mieli dostępu do
jedynego ekranu, który wtyczka dla nich robi.

Naprawa jest wąska i odpowiada tylko na cudze „tak": posiadaczom uprawnień tej
wtyczki mówimy WooCommerce, że mają w panelu robotę. Nikomu niczego nie dodajemy —
subskrybent nadal jest wypychany, a użytkownik bez uprawnienia dostaje `403`.

**Dlaczego żadne z naszych narzędzi tego nie złapało.** Regresja *woła funkcje*,
nie klika — `current_user_can()` odpowiadało poprawnie, bo uprawnienia były
poprawne. Audyt czyta trzy nasze wtyczki i nie ogląda cudzych filtrów. Bramka repo
porównuje wersje i paczki. Żaden z tych przyrządów nie zadaje pytania „czy człowiek
w tej roli dojdzie do tego ekranu".

**Wtyczki 1 i 2 zostają na 1.3.12** — nie miały żadnej zmiany. To pierwsze wydanie,
w którym reguła *„wtyczka bez zmian nie dostaje numeru"* naprawdę się wykonuje;
do 1.3.12 istniała w kodzie i nie została użyta ani razu.

Zapis odbioru: [raporty/ODBIOR-1.3.12.md](raporty/ODBIOR-1.3.12.md) — przejście
przez HTTP 42/42, dziesięć scenariuszy odbioru 102/102, demo z opublikowanego
blueprintu 27/27.

---

## 1.3.12 — trzy komunikaty, jedno sprostowanie i luka w narzędziu wydania

*2026-08-05 · [tag `v1.3.12`](https://github.com/krzysiek2115op/mp-offer-automation-suite/releases/tag/v1.3.12)*

Bramka audytu, która miała potwierdzić gotowość 1.3.11, ruszyła **po** zbudowaniu
paczek — i znalazła trzy usterki w kodzie napisanym tego samego dnia. Wszystkie
tej samej rodziny: **komunikat opisuje operację, która nie zaszła.** Żadna nie
dotyka pieniędzy ani danych; dotykają tego, co czyta człowiek, gdy coś poszło nie tak.

- **„NIP jest wymagany" dla pola, które klient wypełnił.** Kanonizacja dla Polski
  zostawia same cyfry, więc wpis `---` albo `brak` znikał do pustego łańcucha,
  nieodróżnialny od pola nietkniętego. Rozróżnienie bierzemy z wartości surowej.
- **Krytyk opisywał przeliczenie, którego nie było.** `normalize_failed` padało
  dla pól nigdy niewypełnionych. Od braku pola jest `required_missing`.
- **Komunikat o nieudanym założeniu strony milkł w połowie** — podawał przyczynę
  bez instrukcji, choć bliźniaczy komunikat obok zawsze ją podaje.

Czwarte ustalenie wyszło z samego wydawania. Reguła *„wtyczka bez zmian nie
dostaje nowego numeru"* powstała w 1.3.9, ale przez trzy wydania nie została użyta
ani razu — każde ruszało wszystkie trzy wtyczki. Skrypt budujący paczki regułę
miał; [publikuj.py](tools/wydanie/publikuj.py) i [demo-assety.py](tools/wydanie/demo-assety.py)
nadal chodziły po sztywnej liście czterech wydań. Pierwsze wydanie **jednej**
wtyczki zatrzymałoby się na „brak paczki `mp-offer-builder-1.3.12.zip`" — dopiero
po wypchnięciu tagów, czyli w połowie publikacji. Reguła mieszka teraz
w [wydania.py](tools/wydanie/wydania.py), a osobny test przechodzi całą tę drogę
bez sieci i bez gita.

Piąte — i najbardziej niewygodne. Wydanie 1.3.11 zameldowało **„PHPCS: kod
wyjścia 0"**, a PHPCS na tagu `v1.3.11` kończy się kodem **2**: pięć błędów stylu
we wszystkich trzech wtyczkach, wszystkie w kodzie napisanym w rundach napraw tego
samego dnia. Kod działa — to uchybienia stylistyczne — ale zdanie w raporcie
było nieprawdziwe, a jego sprawdzenie kosztowało jedno polecenie, którego nikt nie
uruchomił po ostatniej zmianie. Poprawione tutaj; w rejestrze jako `NARZ-F9`.

Właśnie dlatego **wszystkie trzy wtyczki idą na 1.3.12**, choć wtyczki 2 i 3 nie
dostały żadnej zmiany w działaniu: ich pliki wysyłane klientowi różnią się od tych
z 1.3.11. Dwa różne zestawy plików pod jednym numerem wersji to gorszy problem niż
wydanie o drobnym zakresie. Reguła „wtyczka bez zmian nie dostaje numeru" mówi
o **braku zmiany**, a nie o braku zmiany istotnej.

Ubocznym skutkiem jest to, że nowa ścieżka w narzędziu wydania — ta z pominiętą
wtyczką — **nie jest w tym wydaniu wykonywana**. Sprawdza ją wyłącznie jej test.

---

## 1.3.11 — pełna weryfikacja przed oceną: jedenaście ustaleń

*2026-08-05 · [tag `v1.3.11`](https://github.com/krzysiek2115op/mp-offer-automation-suite/releases/tag/v1.3.11)*

Runda uruchomiona z założeniem, że **błędy nadal są**, i z pytaniem, którego
dotąd nie zadawaliśmy wprost: gdzie nasze narzędzia *z definicji* nie patrzą.
Odpowiedź okazała się powtarzalna — poza zasięgiem audytu kodu leżą artefakty
(treść PDF, treść maila, wiersz w bazie), materiały dla klienta i demo jako
strona. Trzy z jedenastu ustaleń pochodzą właśnie stamtąd.

**Cztery poważne — psuły zachowanie produktu u klienta:**

| Ustalenie | Skutek |
|---|---|
| `segment` gubiony między działem zapisu a działem odczytu | dobór handlowca i treść powiadomień na pustym segmencie przy każdym nowym procesie |
| adres strony z formularzem zwracany także dla wpisu w koszu | gość widział w menu wejście do procesu i trafiał na 404 |
| opis w dzienniku niósł sam kod maszynowy | powód awarii zapisany w kolumnie, której panel nie pokazuje |
| stary kształt cache VIES tłumaczony na twardy werdykt | dobę po aktualizacji legalne zgłoszenia odrzucane jako `vat_invalid` |

**Sześć drobnych z audytu głębokiego:** martwa metoda z niezgodnym słownictwem
alarmu, czas SLA liczony strefą domyślną PHP zamiast GMT, fallback stawki VAT
szerszy niż jego uzasadnienie, komunikat mówiący „i" przy warunku „albo", dane
policzone przez agenta i nieczytane przez krytyka, krótki kod wpisany z ręki
mimo istniejącej stałej.

**Jedno z oglądania dostawy:** materiały dla klienta miały w stopkach numery
sprzed czterech wydań, a wtyczka 1 nie miała **żadnych źródeł** tych materiałów —
dziewięć plików PDF i draw.io, których nie dało się odtworzyć ani poprawić.
Katalog `materialy-src/` odtworzony, numer wersji czytany z nagłówka wtyczki
przy budowaniu.

Dwie obserwacje warte zapamiętania. Pierwsza: naprawa `segment` wymagała **dwóch**
zmian — dział zapisujący i dział czytający — a dział czytający biegnie *przed*
zapisującym, więc naprawa samego zapisu nic by nie dała. Druga: kontr-asercja
pilnująca fallbacku VAT deklarowała w komentarzu odwrotne obciążenie, a
uruchamiała kontekst krajowy — czyli test **chronił** błąd, którego miała
pilnować.

Każda naprawa poprzedzona testem udowodnionym uruchomieniem: 65 nowych asercji
w czterech plikach. Regresja **89/89**, PHPCS **0**.

---

## 1.3.10 — po ocenie zewnętrznej: konfiguracja, tłumaczenia, paczka

*2026-08-04 · [tag `v1.3.10`](https://github.com/krzysiek2115op/mp-offer-automation-suite/releases/tag/v1.3.10)*

Recenzent sprawdził **demo, repozytorium i paczkę ZIP** i naliczył sześć błędów
w rozkładzie 1 duży / 1 średni / 3 małe / 1 w demo — bez listy. Rozkład sam
w sobie był przesłanką: liczba dużych i średnich **nie spadła** względem
poprzedniej oceny, mimo trzech wydań napraw. Skoro nie dodawaliśmy w tym czasie
funkcjonalności, musiały to być te same błędy — rzeczy, których albo nigdy nie
znaleźliśmy, albo znaleźliśmy i odrzuciliśmy. Najniższa ocena dotyczyła gotowości
produkcyjnej i to tam siedział duży.

**SYSTEMU NIE DAŁO SIĘ SKONFIGUROWAĆ Z PANELU, W KTÓRYM SIĘ GO INSTALUJE.**
Dział 4 dobiera właściciela procesu po usermeta `mp_sw_country`, `mp_sw_langs`
i `mp_sw_active`. Konto z samą rolą „Handlowiec", bez tych pól, nie jest
kandydatem dla żadnego procesu — pipeline kończy się kodem `no_owner` i proces
nie powstaje. Ustawić je dało się **wyłącznie** przez `wp user meta update` albo
wprost w bazie. Klient, któremu `PRZECZYTAJ-MNIE.txt` każe wgrać wtyczki przez
„Wtyczki → Dodaj nową", nie miał jak dokończyć konfiguracji tam, gdzie ją zaczął:
system po instalacji przyjmował zgłoszenia i po cichu nie robił z nimi nic.
Nazywaliśmy to „konfiguracją wdrożeniową" — z punktu widzenia kogoś, kto ma to
uruchomić, była to konfiguracja bez interfejsu.

**PACZKA WGRYWAŁA NA PRODUKCJĘ KOD URUCHAMIALNY.** Cztery pliki PHP nie miały
zabezpieczenia przed bezpośrednim wywołaniem, w tym **dwa harnessy działające bez
WordPressa** — wejście na ich adres po prostu je wykonywało. Bliźniaczy plik
wtyczki 2 dostał tę ochronę przy SR5-03; wtyczka 1 nie. Kolejna połowa naprawy
zrobiona tam, gdzie się patrzyło.

**A STRAŻNIKI OKAZAŁY SIĘ POŁOWĄ ODPOWIEDZI.** `ABSPATH` chroni **pliki PHP** —
serwer ich nie uruchamia i oddaje pustkę. Na `.md` nie robi nic, bo markdown nie
jest wykonywany, tylko serwowany jako tekst. Sprawdzone żądaniem HTTP:
`wp-content/plugins/mp-lead-intake/docs/SECURITY.md` odpowiadał **200 i 9966 B
typu `text/markdown`**. Paczka wtyczki 1 miała 101 plików, z czego **47
deweloperskich** — testy, `AUDYT.md`, `DEBUG-RAPORT.md`, dokumentacja wszystkich
działów — wszystkie w katalogu, o którym `PRZECZYTAJ-MNIE.txt` sam pisze, że jest
publiczny. Najgorsze, że projekt to wiedział: `mp-offer-builder/.distignore`
wymieniał te katalogi od początku, tylko nikt tego pliku nie czytał, bo paczki
składaliśmy ręcznie zamiast `wp dist-archive`. **Reguła zapisana w repozytorium,
której nic nie egzekwuje, jest komentarzem.** Budowanie słucha teraz deklaracji
wtyczki, a jej brak przerywa pracę — milczenie nie może znaczyć „wyślij
wszystko". Paczki: 101 → 54, 758 → 684, 117 → 52 pliki. Dokumenty pisane **dla
klienta** (wzór polityki prywatności, `SECURITY.md`, `security.txt`, przykłady
konfiguracji serwera, instrukcja wdrożenia) nie zginęły — przeszły do paczki
materiałów. Wycięcie sprawdzone tam, gdzie boli: generowaniem PDF-a z odchudzonej
paczki wtyczki 2.

**ZERO INFRASTRUKTURY TŁUMACZEŃ PRZY 367 CIĄGACH.** Wszystkie trzy wtyczki wołały
`load_plugin_textdomain( …, '/languages' )`, a katalogu nie było w żadnej, pliku
`.pot` nie było w żadnej i nagłówka `Domain Path` nie deklarowała żadna.
WordPress szukał katalogu, którego nie dostarczono, a tłumacz nie miał z czym
usiąść — mimo że kod był „przygotowany do tłumaczenia" w każdej linii.

**TEKST DLA KLIENTA ŻYŁ W KATALOGU TYMCZASOWYM.** `PRZECZYTAJ-MNIE.txt` — pierwsza
strona, którą widzi klient po rozpakowaniu — nie istniał w repozytorium wcale:
przepisywany był z paczki poprzedniego wydania przez skrypt w `/tmp`. Nie dało się
go zrecenzować w commicie ani odtworzyć po restarcie maszyny. Źródła leżą teraz
w `tools/wydanie/przeczytaj-mnie/`, a skrypt pakujący w `tools/wydanie/` —
sprawdzone: odtwarza opublikowane paczki 1.3.9 co do znaku.

**DEMO ŁAMAŁO WŁASNĄ POLITYKĘ PRYWATNOŚCI.** Strona „Kontakt" osadzała ramkę
Google Maps przy każdym wejściu, a polityka prywatności deklarowała „wyłącznie
niezbędne pliki cookies" — zdanie nieprawdziwe na stronie, która je głosi.
W produkcie, którego osią sprzedażową jest RODO. Mapa wczytuje się teraz dopiero
po kliknięciu i wskazuje adres, który strona podaje, zamiast ogólnego „Warszawa
Centrum"; polityka wymienia Google Fonts, Unsplash i Mapy Google.

Do tego `Tested up to` mówiło 6.6 (wtyczka 1) i 6.8 (wtyczki 2 i 3), podczas gdy
regresja chodzi na WordPressie **7.0**.

Przebieg: regresja **79/79** (trzy nowe pliki testowe), PHPCS kod wyjścia 0.

## 1.3.9 — druga połowa własnych napraw

*2026-08-03 · [tag `v1.3.9`](https://github.com/krzysiek2115op/mp-offer-automation-suite/releases/tag/v1.3.9)*

Audyt głęboki uruchomiony **po** wydaniu 1.3.8 — trzy przebiegi, bo pary modelowe
nie są powtarzalne — dał w każdym z nich werdykt „GO WITH MINOR FIXES". Dziesięć
ustaleń wypłynęło niezależnie w co najmniej dwóch przebiegach i to one były
prawdziwym wynikiem. Siedem z tych dziesięciu miało wspólną cechę, którą trzeba
powiedzieć wprost: **to były niedokończone połówki napraw z 1.3.8**.

Normalizacja „brak właściciela = NULL" objęła nagłówek oferty i pominęła wiersz
historii wersji. Strażnik „promocja bez ceny promocyjnej" łapał rozjazd danych
z pustą ceną, a przepuszczał rozjazd z ceną równą regularnej — czyli dokładnie
przypadek opisany w komentarzu przy nim samym. `intdiv()` trafiło do ścieżki
głównej i ominęło zapasową. Wniosek na przyszłość jest prosty i niewygodny:
naprawa domyka lukę tylko tam, gdzie się patrzyło, a pojęcie żyje zwykle
w kilku miejscach naraz.

**Dwie rzeczy warte zapamiętania poza samą listą.**

Pierwsza: ustalenie zgłoszone w **dwóch przebiegach z trzech okazało się
fałszywe**. „Nagłówek ustawia status na szkic bezwarunkowo, więc zapis cofnie
zatwierdzoną ofertę" — nieprawda, bo kilkaset linii niżej stoi wartownik
w zdaniu WHERE i zapis fizycznie nie trafi w wiersz o innym statusie. Obie próby
czytały ten sam fragment, więc powtarzalność mówiła o zgodności próbek, a nie
o prawdziwości. Drugie odrzucenie dotyczyło statusu podatkowego „tylko wysyłka",
zwolnionego z VAT od wcześniejszej rundy. Oba są zapisane w rejestrze wraz
z powodem, a ich asercje zostały w repozytorium jako straż.

Druga: **regresja złapała porażkę, której nie było widać przez pół roku** —
i to nie w produkcie, tylko w sondzie. Test Białej listy liczył „dzisiejszą datę"
w UTC, a wtyczka liczy dobę tego rejestru po polsku, bo tak stanowi prawo.
Przez dwadzieścia dwie godziny na dobę obie daty są identyczne. Przebieg wypadł
o 00:20 — w jedynym oknie, w którym ten test potrafił paść. Groźniejszy jest
wariant odwrotny: sonda licząca czas inaczej niż produkt równie dobrze może
**przeoczyć** realną wadę przez pozostałe dwadzieścia dwie godziny.

Wtyczka 3 nie dostała w tym wydaniu żadnej zmiany, więc **zostaje na 1.3.8**.
Podbicie numeru przy identycznym kodzie byłoby tym samym błędem, który to
wydanie zamyka: obietnicą, której kod nie dotrzymuje.

---

## 1.3.8 — audyt głęboki, czyli pary, które pytają model

*2026-08-02 · [tag `v1.3.8`](https://github.com/krzysiek2115op/mp-offer-automation-suite/releases/tag/v1.3.8)*

Wydanie 1.3.7 zamknęło to, co dało się znaleźć czytaniem zlecenia zdanie po
zdaniu. Zostało pytanie, na które ta metoda nie odpowiada: **czy kod robi to, co
sam o sobie mówi**. Odpowiadają na nie dwie pary audytu, których żaden wcześniejszy
przebieg nie uruchomił — 1.25 (semantyka działów) i 1.26 (komunikaty dla
człowieka). Są niedostępne na poziomie `pełny`, bo pytają model, a nie wzorzec;
w zamian nie są powtarzalne: każdy przebieg próbkuje inny wycinek kodu. Stąd trzy
przebiegi i suma, a nie jeden werdykt.

Dwadzieścia ustaleń, po rozpisaniu na pliki **trzydzieści siedem napraw i jedno
odrzucenie**. Wszystkie w jednym gatunku: kod, który **melduje coś innego, niż
zrobił**.

**Najgroźniejsze.** Wyczyszczenie tabeli reguł rabatowych zapisywało konfigurację
„0% dla każdej oferty" — z zielonym komunikatem o sukcesie. Rabaty znikały ze
sklepu, a udokumentowany powrót do reguł wbudowanych był tą drogą nieosiągalny.
Obok tego bramka K5.2 wtyczki 3 sprawdzała zgodność przejścia ze zdarzeniem
**jednostronnie**: gdy koperta nie przyznawała się do zmiany statusu, nie badał
tego nikt — wywołujący dostawał „przyjęte", a status zostawał na miejscu.
I trzecie: nierozstrzygnięta Biała lista uchodziła za sprawdzoną, bo warunek
patrzył wyłącznie na `vat_valid`, a odczytany `company_status` nie był używany do
niczego. Lead nigdy nie wracał do weryfikatora, a nieznany status firmy i tak
wchodził do punktacji.

**Jedno ustalenie okazało się fałszywe** i jest odnotowane jako odrzucone
(wpis U-24 w rejestrze): „kontrola podstawy VAT tylko w gałęzi krajowej".
Gałąź niekrajowa ma własną kontrolę, świadomie zawężoną do pozycji niepustych,
z komentarzem tłumaczącym różnicę. Model zobaczył pierwszą i orzekł o całym pliku.
Asercje zostały w repozytorium jako straż — dokumentują, dlaczego te dwie gałęzie
mają wyglądać różnie.

Metoda bez zmian: każda naprawa poprzedzona testem, który **padał** przed nią —
udowodnionym uruchomieniem, nie deklaracją — i kontr-asercjami pilnującymi
zachowania, którego ruszać nie wolno. Siatka regresyjna urosła z 66 do 71 plików.
Jedna z napraw wywróciła rusztowanie testowe wtyczki 2: twarda odmowa przy braku
ustawienia „ceny zawierają podatek" obnażyła, że atrapa WooCommerce tej funkcji
w ogóle nie miała. Dołożyliśmy brakującą atrapę, zamiast cofać naprawę —
rusztowanie ma modelować sklep, a nie ukrywać jego brak.

---

## 1.3.7 — po recenzji zewnętrznej

*2026-08-01 · [tag `v1.3.7`](https://github.com/krzysiek2115op/mp-offer-automation-suite/releases/tag/v1.3.7)*

Recenzent zgłosił jedenaście usterek **bez listy**: jedną dużą, jedną średnią,
sześć drobnych i trzy w stronie pokazowej. Nasze narzędzia dawały wtedy wynik
czysty — komplet testów bez porażki, PHPCS bez błędu, bramka audytu „GO". To
znaczyło jedno: znalazł rzeczy, których te narzędzia **z definicji nie widzą**.
Siatka regresyjna sprawdza wyłącznie to, co już kiedyś w tym projekcie
zepsuliśmy; bramka audytu porównuje kod z rejestrem własnych pomyłek, a nie
z treścią zlecenia; żaden test nie oceniał demo ani tego, czy produkt robi to,
co zamówiono.

Zamiast zgadywać, rozpisaliśmy zlecenie **zdanie po zdaniu** i każdemu wymogowi
przypisaliśmy werdykt z dowodem `plik:linia`. Znalazło się siedemnaście ustaleń
w kodzie i pięć w demo — więcej, niż zgłosił recenzent, więc część na pewno się
nie pokrywa. Naprawione są wszystkie realne; przy braku listy to jedyna sensowna
reakcja.

**Duże.** Jeden lead miał DWÓCH różnych handlowców. Wtyczka 1 wybierała go sama,
haszem `crc32(NIP) % liczba_handlowców` — bez kraju, języka, zespołu i obciążenia,
czyli bez niczego, co przesądza, czy handlowiec jest *odpowiedni*. Równolegle
wtyczka 3 dobierała właściciela procesu naprawdę. Niemiecka firma trafiała do
polskiego handlowca w BD-3 i do niemieckiego w BD-1 — z tego samego zgłoszenia,
w tym samym żądaniu. Żaden test tego nie widział, bo każda wtyczka **z osobna**
zachowywała się spójnie. Zlecenie mówi to samo trzy razy niezależnie: cel
biznesowy żąda „odpowiedniego" handlowca, tabela zakresu umieszcza przypisanie
we wtyczce 3, a BD-1 opisuje jako powiązanie użytkownika z krajem, zespołem
i językiem. Naprawa nie przenosi kodu — zamienia decyzję w **pytanie**.

**Średnie.** Punktacja leada była liczona przy każdym zgłoszeniu i nie pokazywał
jej żaden ekran w żadnej z trzech wtyczek. Zlecenie wymienia scoring jako element
kwalifikacji; liczba, której nikt nie widzi, niczego nie kwalifikuje.

**Znalezione przy okazji, a niewidoczne wcześniej dla nikogo:**

- **CI była czerwona od chwili powstania**, z wydaniem 1.3.6 włącznie. Brama
  wydania czytała z PHPCS liczbę błędów — zgadzała się — i nigdy jego **kodu
  wyjścia**. PHPCS kończy się jedynką także przy samych ostrzeżeniach.
- **Wtyczka 2 obiecywała PHP 7.4**, na którym jej autoloader kończy się fatalem
  (dołączony dompdf wymaga 8.1). Komentarz przy macierzy CI mówił wprost, że bez
  tego sprawdzenia deklaracja zgodności jest gołosłowna. Była.
- **Dwie wtyczki definiowały te same role.** `add_role()` przy istniejącej roli
  nie robi nic, więc uprawnienia dostawała tylko ta aktywowana pierwsza —
  handlowiec czytał „Brak uprawnień" albo nie, zależnie od kolejności instalacji.
- **Motyw strony pokazowej istniał wyłącznie na maszynie autora.** Archiwum
  w wydaniu powstawało z katalogu spoza repozytorium; nikt poza autorem nie mógł
  odtworzyć tego, co ogląda recenzent.
- **Pierwsze zgłoszenie po instalacji ginęło.** Dopóki administrator nie założył
  kont handlowców, wtyczka 3 nie miała komu przypisać procesu — i zamiast zapisać
  proces bez właściciela, odrzucała całe zdarzenie. Lead zostawał w BD-3, oferta
  szła do BD-2, a procesu w BD-1 nie było w ogóle: ani wiersza, ani wpisu
  w dzienniku. Jedyny ślad trafiał do `error_log` PHP i tylko przy włączonym
  `WP_DEBUG`. Nie było tego widać, bo testy uruchamiano na bazie, w której konta
  handlowców zostawały po **wcześniejszych przebiegach innych testów** — dopiero
  ich skasowanie pokazało prawdę. Bramka pilnuje teraz nie „zawsze ktoś
  przypisany", lecz „brak właściciela musi mieć podany powód".

Dwa zarzuty **odrzuciliśmy z uzasadnieniem**, bo były fałszywymi alarmami — i to
też jest wynik, zapisany po to, żeby nikt nie wracał do nich drugi raz. Jedno
z naszych własnych ustaleń okazało się przy naprawie odwrotne do prawdy: zarzut
o łamaniu zasady „jeden plik na dział" przez wtyczkę 3 wziął się z błędu
w liczeniu (`ls | wc -l` liczył też `index.php`). To ona jedyna zasady
przestrzegała; łamały ją wtyczki 1 i 2.

Pełny rozpis: [`audyt/zgodnosc-ze-zleceniem.md`](https://github.com/krzysiek2115op/mp-offer-automation-suite/blob/audyt-projektu/audyt/zgodnosc-ze-zleceniem.md)
(gałąź `audyt-projektu`). Zapis z przebiegu testów:
[`raporty/PRZEBIEG-TESTOW.md`](raporty/PRZEBIEG-TESTOW.md).
