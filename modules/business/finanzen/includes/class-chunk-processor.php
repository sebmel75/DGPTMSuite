<?php
/**
 * Chunk Processor fuer chunk-basierte AJAX-Operationen
 *
 * Generischer Prozessor, der grosse Datenmengen in Chunks verarbeitet.
 * Nutzt WordPress Transients fuer Session-State und Concurrency-Lock.
 */

if (!defined('ABSPATH')) exit;

class DGPTM_FIN_Chunk_Processor {

    const LOCK_PREFIX = 'dgptm_fin_lock_';
    const SESSION_PREFIX = 'dgptm_fin_session_';
    const TTL = 1800; // 30 Minuten
    const DEFAULT_CHUNK_SIZE = 20;

    /**
     * Startet eine neue Chunk-Verarbeitungs-Session.
     *
     * @param string $type   Operationstyp (z.B. 'billing', 'export')
     * @param array  $items  Zu verarbeitende Elemente
     * @param array  $caches Vorab geladene Caches (Kontakte, Rechnungen etc.)
     * @param array  $config Zusaetzliche Konfiguration fuer die Verarbeitung
     * @return string Session-ID
     * @throws \RuntimeException Wenn ein Lock fuer diesen Typ bereits existiert
     */
    public function start(string $type, array $items, array $caches, array $config): string {

        if (self::is_locked($type)) {
            throw new \RuntimeException(
                sprintf('Operation "%s" ist bereits gesperrt. Bitte warten Sie, bis der laufende Vorgang abgeschlossen ist.', $type)
            );
        }

        $session_id = uniqid($type . '_' . get_current_user_id() . '_');

        set_transient(self::LOCK_PREFIX . $type, $session_id, self::TTL);

        set_transient(self::SESSION_PREFIX . $session_id, [
            'type'       => $type,
            'items'      => $items,
            'caches'     => $caches,
            'config'     => $config,
            'results'    => [],
            'processed'  => 0,
            'total'      => count($items),
            'started_at' => current_time('mysql'),
            'user_id'    => get_current_user_id(),
        ], self::TTL);

        return $session_id;
    }

    /**
     * Verarbeitet den naechsten Chunk einer Session.
     *
     * Offset wird serverseitig aus session['processed'] ermittelt (nicht vom Frontend).
     *
     * @param string   $session_id Session-ID
     * @param callable $callback   Callback fuer jedes Element: callback($item) => mixed
     * @param int      $chunk_size Anzahl Elemente pro Chunk
     * @return array ['processed' => int, 'total' => int, 'chunk_results' => array, 'done' => bool]
     */
    public function process_next_chunk(string $session_id, callable $callback, int $chunk_size = self::DEFAULT_CHUNK_SIZE): array {

        $session = get_transient(self::SESSION_PREFIX . $session_id);

        if (!$session) {
            return [
                'error'     => true,
                'message'   => 'Session nicht gefunden oder abgelaufen.',
                'processed' => 0,
                'total'     => 0,
                'done'      => true,
            ];
        }

        $offset = $session['processed'];
        $chunk  = array_slice($session['items'], $offset, $chunk_size);

        $chunk_results = [];
        foreach ($chunk as $item) {
            $chunk_results[] = $callback($item);
        }

        $session['processed'] += count($chunk);
        $session['results'] = array_merge($session['results'], $chunk_results);

        set_transient(self::SESSION_PREFIX . $session_id, $session, self::TTL);

        return [
            'processed'     => $session['processed'],
            'total'         => $session['total'],
            'chunk_results' => $chunk_results,
            'done'          => $session['processed'] >= $session['total'],
        ];
    }

    /**
     * Schliesst eine Session ab und gibt die gesammelten Ergebnisse zurueck.
     *
     * Entfernt Session-Transient und Lock.
     *
     * @param string $session_id Session-ID
     * @return array Gesammelte Ergebnisse der gesamten Verarbeitung
     */
    public function finalize(string $session_id): array {

        $session = get_transient(self::SESSION_PREFIX . $session_id);

        if (!$session) {
            return [];
        }

        $results = $session['results'];

        delete_transient(self::SESSION_PREFIX . $session_id);
        delete_transient(self::LOCK_PREFIX . $session['type']);

        return $results;
    }

    /**
     * Bricht eine laufende Session ab.
     *
     * Entfernt Session-Transient und Lock.
     *
     * @param string $session_id Session-ID
     * @return void
     */
    public function cancel(string $session_id): void {

        $session = get_transient(self::SESSION_PREFIX . $session_id);

        if ($session) {
            delete_transient(self::LOCK_PREFIX . $session['type']);
        }

        delete_transient(self::SESSION_PREFIX . $session_id);
    }

    /**
     * Gibt den Status einer Session zurueck (ohne Items/Caches/Results — lightweight).
     *
     * @param string $session_id Session-ID
     * @return array|null Status-Array oder null wenn Session nicht existiert
     */
    public function get_status(string $session_id): ?array {

        $session = get_transient(self::SESSION_PREFIX . $session_id);

        if (!$session) {
            return null;
        }

        return [
            'type'       => $session['type'],
            'processed'  => $session['processed'],
            'total'      => $session['total'],
            'started_at' => $session['started_at'],
            'config'     => $session['config'],
        ];
    }

    /**
     * Prueft ob ein Operationstyp aktuell gesperrt ist.
     *
     * @param string $type Operationstyp (Standard: 'billing')
     * @return bool True wenn gesperrt
     */
    public static function is_locked(string $type = 'billing'): bool {
        return (bool) get_transient(self::LOCK_PREFIX . $type);
    }
}
