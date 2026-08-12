<?php
/**
 * Ustalenie z analizy statycznej: pole formularza wyslane jako TABLICA wywracalo wtyczke.
 *
 * Uruchamianie: wp eval-file tests/naprawy/pole-formularza-jako-tablica.php
 *
 * ZNALEZIONE PRZEZ PSALMA, POTWIERDZONE ZADANIEM HTTP. Psalm zglosil
 * `PossiblyInvalidArgument: Argument 1 of sanitize_email expects string, but
 * possibly different type non-empty-array ... provided` — bo `$_POST['email']`
 * moze byc tablica, gdy nadawca wysle `email[]=a`. PHPStan tego NIE zglosil,
 * mimo ze analizowal ten sam plik.
 *
 * Pomiar na czystej instalacji:
 *   POST email[]=b@b.test  ->  HTTP 500, „There has been a critical error"
 *   PHP Fatal error: Uncaught TypeError: strlen(): Argument #1 ($string)
 *   must be of type string, array given, wywolane z sanitize_email( Array ).
 *
 * Skutek: kazdy, bez logowania, jednym zadaniem do publicznego formularza
 * wywracal obsluge zgloszenia. Odpowiedz 500 zamiast komunikatu, wpis „Fatal
 * error" w dzienniku serwera, a przy `WP_DEBUG_DISPLAY` — sciezki na ekranie.
 *
 * DLACZEGO PADLO AKURAT NA E-MAILU. `sanitize_text_field()` ma wlasnego
 * straznika (`is_array()` -> pusty lancuch), wiec pola tekstowe konczyly sie
 * poprawna odmowa „nie wszystkie pola wypelnione". `sanitize_email()` takiego
 * straznika NIE MA i idzie prosto do `strlen()`. Jedno pole na dwanascie
 * zachowywalo sie inaczej niz pozostale jedenascie — i wlasnie ono bylo
 * wymagane.
 *
 * @package MP_Lead_Intake
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['mp_pt'] = array(
	'pass'  => 0,
	'fail'  => 0,
	'lines' => array(),
);

/**
 * Asercja.
 *
 * @param bool   $w     Warunek.
 * @param string $opis  Opis.
 * @param string $detal Szczegol przy porazce.
 * @return bool
 */
function pt_ok( $w, $opis, $detal = '' ) {
	if ( $w ) {
		++$GLOBALS['mp_pt']['pass'];
		$GLOBALS['mp_pt']['lines'][] = '  [PASS] ' . $opis;
		return true;
	}

	++$GLOBALS['mp_pt']['fail'];
	$GLOBALS['mp_pt']['lines'][] = '  [FAIL] ' . $opis . ( '' !== $detal ? ' -- ' . $detal : '' );
	return false;
}

/**
 * Wypisuje wynik.
 *
 * @return void
 */
function pt_koniec() {
	if ( empty( $GLOBALS['mp_pt']['lines'] ) ) {
		return;
	}

	$r    = $GLOBALS['mp_pt'];
	$out  = implode( "\n", $r['lines'] );
	$out .= "\n\n----- PASS: " . $r['pass'] . ' / FAIL: ' . $r['fail'] . " -----\n";
	$out .= 0 === $r['fail'] ? "VERDICT_ALL_PASS\n" : "VERDICT_HAS_FAILURES\n";

	$GLOBALS['mp_pt']['lines'] = array();
	echo $out; // phpcs:ignore
}
register_shutdown_function( 'pt_koniec' );

/* ==================================================================== A */

$GLOBALS['mp_pt']['lines'][] = '=== A. wtyczka ma jedno miejsce, ktore czyta pole zadania ===';

pt_ok(
	method_exists( 'MP_Lead_Intake_Ajax', 'pole_tekstowe' ),
	'A1: jest wspolny odczyt pola, a nie dwanascie osobnych wyrazen'
);

if ( ! method_exists( 'MP_Lead_Intake_Ajax', 'pole_tekstowe' ) ) {
	return;
}

$pt_metoda = new ReflectionMethod( 'MP_Lead_Intake_Ajax', 'pole_tekstowe' );
$pt_metoda->setAccessible( true );

/**
 * Wywoluje odczyt pola dla podanej zawartosci `$_POST`.
 *
 * @param mixed  $wartosc     Co przyszlo w zadaniu.
 * @param string $sanityzator Nazwa funkcji czyszczacej.
 * @return string
 */
function pt_czytaj( $wartosc, $sanityzator = 'sanitize_text_field' ) {
	$_POST['pt_pole'] = $wartosc; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	$m = new ReflectionMethod( 'MP_Lead_Intake_Ajax', 'pole_tekstowe' );
	$m->setAccessible( true );
	$wynik = $m->invoke( null, 'pt_pole', $sanityzator );

	unset( $_POST['pt_pole'] );

	return $wynik;
}

/* ==================================================================== B */

$GLOBALS['mp_pt']['lines'][] = '';
$GLOBALS['mp_pt']['lines'][] = '=== B. tablica zamiast lancucha nie wywraca niczego ===';

$pt_tablice = array(
	'lista dwoch wartosci' => array( 'a@b.test', 'c@d.test' ),
	'tablica pusta'        => array(),
	'tablica zagniezdzona' => array( array( 'a@b.test' ) ),
	'tablica z kluczami'   => array( 'x' => 'a@b.test' ),
);

foreach ( $pt_tablice as $opis => $wartosc ) {
	foreach ( array( 'sanitize_email', 'sanitize_text_field', 'sanitize_textarea_field' ) as $san ) {
		$wynik = pt_czytaj( $wartosc, $san );

		pt_ok(
			'' === $wynik,
			'B (' . $opis . ', ' . $san . '): pusty lancuch zamiast awarii',
			'zwrocono ' . gettype( $wynik ) . ': ' . wp_json_encode( $wynik )
		);
	}
}

pt_ok( is_string( pt_czytaj( array( 'a@b.test' ), 'sanitize_email' ) ), 'B: wynik jest ZAWSZE lancuchem' );

/* ==================================================================== C */

$GLOBALS['mp_pt']['lines'][] = '';
$GLOBALS['mp_pt']['lines'][] = '=== C. kontr-asercje: zwykle dane dzialaja jak dotad ===';

pt_ok( 'a@b.test' === pt_czytaj( 'a@b.test', 'sanitize_email' ), 'C1: poprawny adres przechodzi bez zmian' );
pt_ok( 'Firma' === pt_czytaj( 'Firma', 'sanitize_text_field' ), 'C2: zwykly tekst przechodzi bez zmian' );
pt_ok( '' === pt_czytaj( '', 'sanitize_text_field' ), 'C3: pusty lancuch zostaje pusty' );

/*
 * Odbarwianie ma nadal dzialac — helper nie moze przy okazji przepuscic tego,
 * co sanityzator ma odciac.
 */
pt_ok(
	false === strpos( pt_czytaj( '<script>alert(1)</script>Firma', 'sanitize_text_field' ), '<script' ),
	'C4: sanityzator nadal czysci znaczniki'
);

/* Ukoszone cudzyslowy: `wp_unslash` ma zostac w torze. */
pt_ok(
	"O'Brien" === pt_czytaj( "O\\'Brien", 'sanitize_text_field' ),
	'C5: ukosnik dodany przez WordPressa jest zdejmowany',
	pt_czytaj( "O\\'Brien", 'sanitize_text_field' )
);

/* Pole nieobecne w zadaniu. */
unset( $_POST['pt_brak'] );
pt_ok( '' === $pt_metoda->invoke( null, 'pt_brak', 'sanitize_text_field' ), 'C6: brak pola daje pusty lancuch' );

/* ==================================================================== D */

$GLOBALS['mp_pt']['lines'][] = '';
$GLOBALS['mp_pt']['lines'][] = '=== D. skalary inne niz lancuch tez nie wywracaja ===';

foreach ( array( 'liczba' => 42, 'liczba zmiennoprzecinkowa' => 1.5, 'prawda' => true, 'null' => null ) as $opis => $w ) {
	$wynik = pt_czytaj( $w, 'sanitize_text_field' );
	pt_ok( is_string( $wynik ), 'D (' . $opis . '): wynik jest lancuchem', gettype( $wynik ) );
}
