<?php

/**
 * Async dispatch of submissions to Clientify (Fase 5.2).
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/crm
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Listens to `acelera_form_completed` and syncs submissions via WP-Cron.
 *
 * Kept in its own file (instead of merged into Acelera_Clientify) so the
 * HTTP/payload concerns (client) stay separate from the scheduling /
 * retry / status concerns (this class) — same one-class-per-file layout
 * used across the plugin.
 *
 * Flow: the listener marks the row 'pending' and schedules a one-off
 * `acelera_send_to_clientify` event. The user's submit NEVER waits for
 * Clientify. The cron handler creates the contact (falling back to a
 * lookup by email on 4xx duplicates) and attaches the answers note; on
 * success the row becomes 'sent'. The contact ID is persisted as soon as
 * it is known, so a retry after a note-only failure skips the contact
 * creation step.
 *
 * Retry/backoff: attempts 2/3/4 run +5 min, +30 min and +2 h after the
 * respective failure; after attempt 4 the row is marked 'error'.
 *
 * Error storage decision: the last error message is kept in a transient
 * (`acelera_clientify_err_{submission_id}`, 30-day TTL) instead of inside
 * the `flags` JSON column — `flags` holds scoring output exclusively and
 * mixing transport diagnostics into it would require read-modify-write
 * cycles on data other features consume. The transient is shown in the
 * "Sumisiones" admin list and cleared on success/resend.
 *
 * `clientify_status` values written here: pending | sent | error | skipped.
 *
 * @since      1.0.0
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/crm
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Clientify_Dispatcher {

	/**
	 * Cron hook name for the one-off send events.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const CRON_HOOK = 'acelera_send_to_clientify';

	/**
	 * Total attempts before giving up (1 initial + 3 retries).
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const MAX_ATTEMPTS = 4;

	/**
	 * Transient name prefix for the last error of a submission.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const ERROR_TRANSIENT_PREFIX = 'acelera_clientify_err_';

	/**
	 * Lifetime of the last-error transient, in seconds (30 days).
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const ERROR_TRANSIENT_TTL = 2592000;

	/**
	 * `acelera_form_completed` listener — schedule the async send.
	 *
	 * @since  1.0.0
	 * @param  int   $user_id       Submitting user ID (unused, row has it).
	 * @param  int   $submission_id New acelera_form_submissions row ID.
	 * @param  array $answers       Sanitized answers (unused, row has them).
	 * @param  array $scoring       Scoring result (unused, row has it).
	 * @return void
	 */
	public function on_form_completed( $user_id, $submission_id, $answers = array(), $scoring = array() ) {

		$submission_id = (int) $submission_id;

		if ( $submission_id <= 0 ) {
			return;
		}

		$repo = new Acelera_Submissions_Repo();
		$repo->update_clientify( $submission_id, null, 'pending' );

		self::schedule( $submission_id, 1, 0 );

	}

	/**
	 * Schedule (or reschedule) a send event.
	 *
	 * Public so the admin "Reenviar" AJAX handler can reuse it.
	 *
	 * @since  1.0.0
	 * @param  int $submission_id Submission row ID.
	 * @param  int $attempt       Attempt number the event will run as.
	 * @param  int $delay         Seconds from now.
	 * @return void
	 */
	public static function schedule( $submission_id, $attempt = 1, $delay = 0 ) {

		wp_schedule_single_event(
			time() + max( 0, (int) $delay ),
			self::CRON_HOOK,
			array( (int) $submission_id, (int) $attempt )
		);

	}

	/**
	 * Backoff delay (seconds) before running a given attempt.
	 *
	 * Attempt 1 is immediate; attempts 2/3/4 follow the plan's backoff
	 * (5 min, 30 min, 2 h). Unknown attempts reuse the largest delay.
	 *
	 * @since  1.0.0
	 * @param  int $attempt Attempt number (1-based).
	 * @return int Delay in seconds.
	 */
	public static function delay_for_attempt( $attempt ) {

		$map = array(
			1 => 0,
			2 => 5 * MINUTE_IN_SECONDS,
			3 => 30 * MINUTE_IN_SECONDS,
			4 => 2 * HOUR_IN_SECONDS,
		);

		$attempt = (int) $attempt;

		return isset( $map[ $attempt ] ) ? $map[ $attempt ] : 2 * HOUR_IN_SECONDS;

	}

	/**
	 * Cron handler — create the contact + note for a submission.
	 *
	 * @since  1.0.0
	 * @param  int $submission_id Submission row ID.
	 * @param  int $attempt       Optional. Attempt number (default 1).
	 * @return void
	 */
	public function handle_cron( $submission_id, $attempt = 1 ) {

		$submission_id = (int) $submission_id;
		$attempt       = max( 1, (int) $attempt );

		$repo = new Acelera_Submissions_Repo();
		$row  = $repo->get_by_id( $submission_id );

		if ( ! $row ) {
			error_log( '[acelera-clientify] Submission #' . $submission_id . ' not found, dropping send.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return;
		}

		// Duplicate-send guard.
		if ( 'sent' === (string) $row->clientify_status ) {
			return;
		}

		$client = new Acelera_Clientify();

		// No API key configured → the form works without CRM. Skip silently.
		if ( ! $client->has_api_key() ) {
			$repo->update_clientify( $submission_id, null, 'skipped' );
			return;
		}

		$answers = json_decode( (string) $row->answers, true );
		$answers = is_array( $answers ) ? $answers : array();

		$scores = json_decode( (string) $row->scores, true );
		$flags  = json_decode( (string) $row->flags, true );

		$scoring = array(
			'scores'       => is_array( $scores ) ? $scores : array(),
			'module_order' => (string) $row->module_order,
			'flags'        => is_array( $flags ) ? $flags : array(),
		);

		// --- Step 1: contact (skipped when a previous attempt created it). --.
		$contact_id = (int) $row->clientify_contact_id;

		if ( $contact_id <= 0 ) {
			$contact_id = $this->resolve_contact( $client, $answers );

			if ( is_wp_error( $contact_id ) ) {
				$this->fail( $repo, $submission_id, $attempt, null, $contact_id );
				return;
			}

			// Persist immediately: a note-only failure must not re-create
			// the contact on retry.
			$repo->update_clientify( $submission_id, $contact_id, 'pending' );
		}

		// --- Step 2: note. ---------------------------------------------------.
		$note_html = Acelera_Clientify::build_note_html( $answers, $scoring, (string) $row->cv_url );
		$note      = $client->create_note( $contact_id, $note_html );

		if ( is_wp_error( $note ) ) {
			$this->fail( $repo, $submission_id, $attempt, $contact_id, $note );
			return;
		}

		$repo->update_clientify( $submission_id, $contact_id, 'sent' );
		delete_transient( self::ERROR_TRANSIENT_PREFIX . $submission_id );

	}

	/**
	 * Create the contact, falling back to a lookup by email on 4xx.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Acelera_Clientify $client  API client.
	 * @param  array             $answers Sanitized answers keyed by question ID.
	 * @return int|WP_Error Contact ID or WP_Error.
	 */
	private function resolve_contact( Acelera_Clientify $client, array $answers ) {

		$payload = Acelera_Clientify::build_contact_payload( $answers );
		$created = $client->create_contact( $payload );

		if ( ! is_wp_error( $created ) ) {
			if ( isset( $created['id'] ) && (int) $created['id'] > 0 ) {
				return (int) $created['id'];
			}

			return new WP_Error(
				'acelera_clientify_no_id',
				__( 'Clientify no devolvió un ID de contacto.', 'formulario-acelara-ai-daniel' )
			);
		}

		// Duplicate fallback: a 4xx usually means the email already exists.
		$error_data = $created->get_error_data();
		$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 0;

		if ( $status >= 400 && $status < 500 && '' !== $payload['email'] ) {
			$existing = $client->find_contact_by_email( $payload['email'] );

			if ( ! is_wp_error( $existing ) && is_array( $existing ) && isset( $existing['id'] ) && (int) $existing['id'] > 0 ) {
				return (int) $existing['id'];
			}
		}

		return $created;

	}

	/**
	 * Handle a failed attempt: log, store the error and retry or give up.
	 *
	 * The API key is never part of the logged/stored messages.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  Acelera_Submissions_Repo $repo          Repository.
	 * @param  int                      $submission_id Submission row ID.
	 * @param  int                      $attempt       Attempt that just failed.
	 * @param  int|null                 $contact_id    Contact ID when known.
	 * @param  WP_Error                 $error         Failure detail.
	 * @return void
	 */
	private function fail( Acelera_Submissions_Repo $repo, $submission_id, $attempt, $contact_id, WP_Error $error ) {

		$message = sprintf(
			'attempt %d/%d failed for submission #%d: [%s] %s',
			$attempt,
			self::MAX_ATTEMPTS,
			$submission_id,
			$error->get_error_code(),
			$error->get_error_message()
		);

		error_log( '[acelera-clientify] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		set_transient(
			self::ERROR_TRANSIENT_PREFIX . $submission_id,
			$message,
			self::ERROR_TRANSIENT_TTL
		);

		if ( $attempt >= self::MAX_ATTEMPTS ) {
			$repo->update_clientify( $submission_id, $contact_id, 'error' );
			return;
		}

		$next = $attempt + 1;

		self::schedule( $submission_id, $next, self::delay_for_attempt( $next ) );

	}

}
