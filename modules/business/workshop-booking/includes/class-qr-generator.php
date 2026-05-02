<?php
/**
 * QR-Code-Generator.
 *
 * Wrapper um endroid/qr-code (Composer-Dependency, siehe composer.json).
 * Bei fehlender Library: graceful Fallback (liefert null + Log-Eintrag),
 * damit der Buchungsfluss nicht bricht.
 *
 * Output: Data-URI mit base64-PNG, einbettbar als <img src="data:..."> in HTML/PDF.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_QR_Generator {

    /**
     * Erzeugt einen QR-Code als Data-URI (PNG, base64).
     *
     * @param string $payload     Inhalt des QR-Codes (z.B. Ticketnummer)
     * @param int    $size        Kantenlaenge in Pixel (Default 256)
     * @return string|null  Data-URI oder null bei Fehler
     */
    public static function as_data_uri($payload, $size = 256) {
        if (empty($payload)) return null;

        // endroid/qr-code v4 API
        if (class_exists('Endroid\\QrCode\\Builder\\Builder')) {
            try {
                $result = \Endroid\QrCode\Builder\Builder::create()
                    ->writer(new \Endroid\QrCode\Writer\PngWriter())
                    ->data((string) $payload)
                    ->size((int) $size)
                    ->margin(10)
                    ->build();
                return $result->getDataUri();
            } catch (\Throwable $e) {
                self::log_error('endroid_v4_failed', $e->getMessage());
                return null;
            }
        }

        // endroid/qr-code v3 API (Fallback)
        if (class_exists('Endroid\\QrCode\\QrCode')) {
            try {
                $qr = new \Endroid\QrCode\QrCode((string) $payload);
                $qr->setSize((int) $size);
                return 'data:image/png;base64,' . base64_encode($qr->writeString());
            } catch (\Throwable $e) {
                self::log_error('endroid_v3_failed', $e->getMessage());
                return null;
            }
        }

        self::log_error('endroid_missing', 'endroid/qr-code library not loaded — composer install nicht ausgefuehrt?');
        return null;
    }

    private static function log_error($code, $message) {
        if (function_exists('error_log')) {
            error_log('[DGPTM_WSB_QR] ' . $code . ': ' . $message);
        }
    }
}
