<?php
/**
 * Endpoint AJAX wtyczki MP Lead Intake — realizacja zasady "1 AJAX".
 *
 * Jedno wywołanie: zbiera dane z formularza, buduje kontekst i uruchamia CAŁY
 * pipeline (11 działów) przez MP_Pipeline_Factory. Pełna walidacja pól i CSRF
 * (nonce) dzieją się WEWNĄTRZ pipeline (dział 2), z fail-fast powtórką nonce'a
 * i originu TU, w pre-gate. Antyspam (honeypot) i rate-limit również mają
 * fail-fast odpowiednik w pre-gate (dział 5 zostaje jako defense-in-depth) —
 * TU też jest jedyne miejsce inkrementu licznika rate-limit, żeby liczyła się
 * KAŻDA próba, nawet ta odrzucona później w pipeline.
 *
 * Oficjalne API: add_action wp_ajax_* / wp_ajax_nopriv_*
 *   https://developer.wordpress.org/reference/hooks/wp_ajax_action/
 *   wp_send_json_success / wp_send_json_error.
 *
 * @package MP_Lead_Intake
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obsługa żądania AJAX.
 */
class MP_Lead_Intake_Ajax {

	/** Nazwa akcji AJAX. */
	const ACTION = 'mp_lead_intake_submit';

	/**
	 * Komunikat dla NADAWCY formularza — jedyne miejsce, w którym powstaje.
	 *
	 * Do 1.3.6 każda odmowa kończyła się zdaniem „Nie udało się przetworzyć
	 * zgłoszenia. Sprawdź dane i spróbuj ponownie." Także ta, po której nadawca
	 * nie ma czego sprawdzać, bo jego dane są poprawne: powtórne zgłoszenie tej
	 * samej firmy. Odmowa jest wtedy słuszna (kryterium odbioru żąda braku
	 * duplikatów), ale człowiek po drugiej stronie poprawiał dane, które są dobre.
	 *
	 * Słownik jest ZAMKNIĘTY i to jest jego sens. Kod spoza słownika dostaje
	 * komunikat generyczny, więc żaden nowy kod wewnętrzny nie wycieknie do
	 * odpowiedzi przez samo powstanie. Świadomie NIE MA tu:
	 *
	 *  - odmów Działu 5 (antyspam, CSRF, limit tempa) rozbitych na powody —
	 *    powiedzenie botowi, która straż zadziałała, jest instrukcją obejścia;
	 *  - nazw pól i kodów wewnętrznych — szczegóły siedzą w dzienniku BD-3 pod
	 *    tym samym `request_id`, który nadawca dostaje w odpowiedzi.
	 *
	 * @param string $code Kod odmowy z pipeline'u albo z warstwy wejściowej.
	 * @return string
	 */
	public static function public_message( $code ) {
		switch ( (string) $code ) {
			case 'duplicate_company':
				return __( 'Ta firma jest już u nas zarejestrowana — nie zakładamy zgłoszenia drugi raz. Jeśli sprawa jest nowa, odpowiedz na naszą ostatnią wiadomość albo zadzwoń.', 'mp-lead-intake' );

			case 'nip_invalid':
				return __( 'Podany NIP jest nieprawidłowy — sprawdź, czy nie brakuje cyfry.', 'mp-lead-intake' );

			case 'required_missing':
				return __( 'Nie wszystkie wymagane pola zostały wypełnione.', 'mp-lead-intake' );

			case 'format_invalid':
				return __( 'Któreś z pól ma nieprawidłowy format — sprawdź adres e-mail i numer telefonu.', 'mp-lead-intake' );

			case 'consent_required':
				return __( 'Bez zgody na przetwarzanie danych nie możemy przyjąć zgłoszenia.', 'mp-lead-intake' );

			case 'payload_too_large':
				return __( 'Zgłoszenie jest zbyt duże.', 'mp-lead-intake' );

			case 'invalid_nonce':
				return __( 'Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.', 'mp-lead-intake' );

			case 'invalid_origin':
				return __( 'Żądanie z niedozwolonego źródła.', 'mp-lead-intake' );

			case 'rate_limited':
				return __( 'Nie udało się przetworzyć zgłoszenia. Spróbuj ponownie za chwilę.', 'mp-lead-intake' );

			default:
				return __( 'Nie udało się przetworzyć zgłoszenia. Sprawdź dane i spróbuj ponownie.', 'mp-lead-intake' );
		}
	}

	/**
	 * Rejestruje akcje AJAX (zalogowani i niezalogowani — formularz publiczny).
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Czyta jedno pole żądania jako łańcuch — cokolwiek nadawca przysłał.
	 *
	 * NADAWCA DECYDUJE O TYPIE, NIE TYLKO O TREŚCI. `email=a@b.test` daje łańcuch,
	 * ale `email[]=a@b.test` daje TABLICĘ — i to samo dotyczy każdego pola
	 * formularza. Dwanaście osobnych wyrażeń `isset() ? sanitize_*() : ''`
	 * zakładało łańcuch dwanaście razy, a żadne z nich tego nie sprawdzało.
	 *
	 * Uszło to na sucho jedenaście razy przez przypadek: `sanitize_text_field()`
	 * ma własnego strażnika (`is_array()` → pusty łańcuch), więc pola tekstowe
	 * kończyły się poprawną odmową. `sanitize_email()` takiego strażnika NIE MA
	 * i idzie prosto do `strlen()`. Pomiar na czystej instalacji: `email[]=x`
	 * dawało HTTP 500 i „Uncaught TypeError: strlen(): Argument #1 ($string)
	 * must be of type string, array given" — bez logowania, z publicznego
	 * formularza, jednym żądaniem.
	 *
	 * Sprawdzenie jest tutaj, a nie w każdym z dwunastu miejsc, bo bezpieczeństwo
	 * pola nie może zależeć od tego, którą funkcję czyszczącą ktoś akurat wybrał.
	 * Znalezione analizą statyczną (Psalm: `PossiblyInvalidArgument`), której ten
	 * projekt wcześniej nie uruchamiał.
	 *
	 * @param string $klucz       Nazwa pola w `$_POST`.
	 * @param string $sanityzator Funkcja czyszcząca WordPressa.
	 * @return string Zawsze łańcuch — pusty, gdy pola nie ma albo nie jest skalarem.
	 */
	private static function pole_tekstowe( $klucz, $sanityzator = 'sanitize_text_field' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce sprawdzany wyżej w handle().
		if ( ! isset( $_POST[ $klucz ] ) ) {
			return '';
		}

		/*
		 * Tablica, obiekt i null nie sa tekstem i nie ma czego z nich ratowac:
		 * formularz ma dwanascie pol jednowartosciowych, wiec wartosc zlozona
		 * moze byc albo pomylka nadawcy, albo proba. W obu razach odpowiedzia
		 * jest „pole puste" — a te wtyczka umie obsluzyc.
		 *
		 * Sprawdzamy PRZED `wp_unslash()`, bo ono na tablicy przechodzi
		 * bez slowa i problem wychodzi dopiero u sanityzatora.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- jw.
		if ( ! is_scalar( $_POST[ $klucz ] ) ) {
			return '';
		}

		/*
		 * Sanityzator jest podawany jako nazwa funkcji i PHPCS nie umie za tym
		 * pojsc — sniff `ValidatedSanitizedInput` zglasza „nie sanityzowano",
		 * mimo ze KAZDE wyjscie tej metody przechodzi przez jedna z funkcji
		 * czyszczacych WordPressa. Rzutowanie na `string` odbywa sie przed
		 * wywolaniem, wiec sanityzator zawsze dostaje typ, ktorego oczekuje.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$surowa = (string) wp_unslash( $_POST[ $klucz ] );

		return (string) call_user_func( $sanityzator, $surowa );
	}

	/**
	 * Obsługuje zgłoszenie: buduje kontekst i uruchamia pipeline.
	 *
	 * @return void
	 */
	public static function handle() {
		// Nagłówki bezpieczeństwa na NASZEJ odpowiedzi (scoped, bezpieczne).
		MP_Lead_Intake_Security::send_ajax_headers();

		// Correlation ID: łączy odpowiedź dla klienta z wpisem w logu BD-3 (diagnostyka
		// bez ujawniania wewnętrznych szczegółów).
		$request_id = MP_Lead_Intake_Security::request_id();

		// Twardy limit rozmiaru żądania (oversized payload / DoS) — formularz jest mały.
		$max_bytes = (int) apply_filters( 'mp_lead_intake_max_request_bytes', 64 * 1024 );
		if ( isset( $_SERVER['CONTENT_LENGTH'] ) && (int) $_SERVER['CONTENT_LENGTH'] > $max_bytes ) {
			wp_send_json_error(
				array(
					'code'       => 'payload_too_large',
					'message'    => self::public_message( 'payload_too_large' ),
					'request_id' => $request_id,
				),
				413
			);
		}

		// CSRF: weryfikacja nonce na wejściu (fail-fast). Dział 5 dodatkowo odnotowuje CSRF w pipeline.
		if ( ! check_ajax_referer( 'mp_lead_intake', 'mp_nonce', false ) ) {
			wp_send_json_error(
				array(
					'code'       => 'invalid_nonce',
					'message'    => self::public_message( 'invalid_nonce' ),
					'request_id' => $request_id,
				),
				403
			);
		}

		// Defense-in-depth vs CSRF: zgodność Origin/Referer z witryną.
		if ( ! MP_Lead_Intake_Security::verify_origin() ) {
			wp_send_json_error(
				array(
					'code'       => 'invalid_origin',
					'message'    => self::public_message( 'invalid_origin' ),
					'request_id' => $request_id,
				),
				403
			);
		}

		// Dane wejściowe — whitelist kluczy (nieoczekiwane pola POST są ignorowane).
		$input = array(
			'company_name'      => self::pole_tekstowe( 'company_name' ),
			'nip'               => self::pole_tekstowe( 'nip' ),
			'email'             => self::pole_tekstowe( 'email', 'sanitize_email' ),
			'phone'             => self::pole_tekstowe( 'phone' ),
			'segment'           => self::pole_tekstowe( 'segment' ),
			'country'           => self::pole_tekstowe( 'country' ),
			'products'          => self::pole_tekstowe( 'products', 'sanitize_textarea_field' ),
			'est_volume'        => self::pole_tekstowe( 'est_volume' ),
			'consent_marketing' => ! empty( $_POST['consent_marketing'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'consent_rodo'      => ! empty( $_POST['consent_rodo'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'mp_hp'             => self::pole_tekstowe( 'mp_hp' ),
			'mp_nonce'          => self::pole_tekstowe( 'mp_nonce' ),
			'request_id'        => $request_id,
		);

		// Fail-fast antyspam + rate-limit PRZED pipeline. Dział 3 wykonuje kosztowne
		// zapytania zewnętrzne (VIES, Biała lista); bez tej bramki atakujący z jednym
		// ważnym nonce mógłby je wymuszać bez limitu (DoS/amplifikacja, banowanie IP
		// serwera). Dział 5 zostaje jako druga warstwa (defense-in-depth) w pipeline.
		$ip = MP_D5_Agent_Rate_Limit::client_ip();
		if ( '' !== trim( (string) $input['mp_hp'] ) || MP_D5_Agent_Rate_Limit::over_limit( $ip ) ) {
			wp_send_json_error(
				array(
					'code'       => 'request_rejected',
					'message'    => self::public_message( 'rate_limited' ),
					'request_id' => $request_id,
				),
				429
			);
		}

		// Inkrement TU (nie w dziale 5) — inaczej zgłoszenia odrzucone wcześniej w
		// pipeline (np. zła suma kontrolna NIP w dziale 3, PRZED działem 5) nigdy by
		// się nie liczyły do limitu, co pozwalałoby ominąć rate-limit floodem błędnych
		// danych (wykryte w testach manualnych na żywym WP — scenariusz 8/10).
		MP_D5_Agent_Rate_Limit::increment( $ip );

		$context  = new MP_Context( $input );
		$pipeline = MP_Pipeline_Factory::make();

		try {
			$result = $pipeline->run( $context );
		} catch ( \Throwable $e ) {
			// Pipeline już zrobił ROLLBACK/log (patrz MP_Pipeline::run()) — TU tylko
			// gwarantujemy kontrakt "zawsze JSON" wobec klienta, niezależnie od tego,
			// co poszło nie tak (w tym w kodzie spoza tej wtyczki — subskrybenci
			// do_action('mp_lead_created') z przyszłej integracji plugin 2/3).
			wp_send_json_error(
				array(
					'code'       => 'processing_failed',
					'message'    => self::public_message( 'processing_failed' ),
					'request_id' => $request_id,
				),
				500
			);
		}

		if ( $result->is_ok() ) {
			$data = $result->get_data();
			wp_send_json_success(
				array(
					'lead_id'    => isset( $data['lead_id'] ) ? (int) $data['lead_id'] : null,
					'message'    => __( 'Dziękujemy! Zapytanie zostało zarejestrowane.', 'mp-lead-intake' ),
					'request_id' => $request_id,
				)
			);
		}

		// Bez ujawniania wewnętrznego kodu/pól klientowi — generyczny komunikat + correlation ID.
		// Szczegóły (kod działu, błędy pól) są w logu BD-3 pod tym samym request_id.
		wp_send_json_error(
			array(
				'code'       => 'processing_failed',
				'message'    => self::public_message( $result->get_code() ),
				'request_id' => $request_id,
			),
			400
		);
	}
}
