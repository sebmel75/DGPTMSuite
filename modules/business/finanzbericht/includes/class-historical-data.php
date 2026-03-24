<?php
/**
 * Historische Finanzdaten 2023 und 2024
 * Aus Zoho Books CSV-Exporten extrahiert, statisch hinterlegt.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_FB_Historical_Data {

    /**
     * Liefert statische Daten fuer ein Jahr/Berichtstyp.
     * @return array|null  null wenn kein statischer Datensatz vorhanden
     */
    public static function get(string $report, int $year): ?array {
        $method = "get_{$report}_{$year}";
        if (method_exists(self::class, $method)) {
            return self::$method();
        }
        return null;
    }

    public static function available_years(string $report): array {
        $years = [];
        foreach ([2023, 2024] as $y) {
            if (method_exists(self::class, "get_{$report}_{$y}")) {
                $years[] = $y;
            }
        }
        return $years;
    }

    /* ================================================================ */
    /*  JAHRESTAGUNG                                                     */
    /* ================================================================ */

    private static function get_jahrestagung_2023(): array {
        return [
            'title' => '52. Internationale Jahrestagung der DGfK und 15. Fokustagung Herz der DGTHG',
            'period' => 'Juli 2023 - Juni 2024',
            'location' => 'Berlin',
            'income' => [
                'total' => 278722.72,
                'categories' => [
                    'Sponsoring'             => ['count' => 12, 'total' => 63200.00],
                    'Ausstellungsflaeche'    => ['count' => 18, 'total' => 48469.00],
                    'Kongresstickets'        => ['count' => 95, 'total' => 36176.27],
                    'Werbung & Programmheft' => ['count' => 15, 'total' => 36915.00],
                    'Symposien & Sessions'   => ['count' => 4,  'total' => 12810.00],
                    'Workshops'              => ['count' => 22, 'total' => 8730.00],
                    'Sonstige Einnahmen'     => ['count' => 30, 'total' => 72422.45],
                ],
            ],
            'expenses' => [
                'total' => 33371.46,
                'categories' => [
                    'Redaktion & Organisation' => ['count' => 8,  'total' => 12659.53],
                    'Veranstaltungsort'        => ['count' => 3,  'total' => 6420.00],
                    'Grafik & Design'          => ['count' => 5,  'total' => 2220.00],
                    'Druck & Programmheft'     => ['count' => 4,  'total' => 2157.47],
                    'IT & Software'            => ['count' => 3,  'total' => 4095.00],
                    'Sonstige Ausgaben'        => ['count' => 10, 'total' => 5819.46],
                ],
            ],
            'net_result' => 245351.26,
        ];
    }

    private static function get_jahrestagung_2024(): array {
        return [
            'title' => '53. Internationale Jahrestagung der DGfK und 16. Fokustagung Herz der DGTHG & DGfK',
            'period' => 'Juli 2024 - Juni 2025',
            'location' => 'Leipzig',
            'income' => [
                'total' => 304623.12,
                'categories' => [
                    'Ausstellungsflaeche'    => ['count' => 25, 'total' => 107630.00],
                    'Sponsoring'             => ['count' => 10, 'total' => 82000.00],
                    'Werbung & Programmheft' => ['count' => 18, 'total' => 39540.00],
                    'Workshops'              => ['count' => 15, 'total' => 19650.00],
                    'Sonstige Einnahmen'     => ['count' => 29, 'total' => 55803.12],
                ],
            ],
            'expenses' => [
                'total' => 27313.70,
                'categories' => [
                    'Catering'                 => ['count' => 5,  'total' => 13955.09],
                    'Sonstige Ausgaben'        => ['count' => 8,  'total' => 9639.41],
                    'Anwaltliche Beratung'     => ['count' => 2,  'total' => 2023.00],
                    'Logistik & Versand'       => ['count' => 3,  'total' => 1190.00],
                    'Sonstiges'                => ['count' => 4,  'total' => 506.20],
                ],
            ],
            'net_result' => 277309.42,
        ];
    }

    /* ================================================================ */
    /*  SACHKUNDEKURS ECLS                                               */
    /* ================================================================ */

    private static function get_sachkundekurs_2023(): array {
        return [
            'title' => 'Sachkundekurse ECLS 2023',
            'period' => 'Januar - Dezember 2023',
            'income' => [
                'total' => 79330.67,
                'categories' => [
                    'Kursgebuehren April'     => ['count' => 25, 'total' => 22150.00],
                    'Kursgebuehren Dezember'  => ['count' => 22, 'total' => 19500.00],
                    'SKK Sponsoring'          => ['count' => 8,  'total' => 36680.67],
                    'Sonstige SKK-Einnahmen'  => ['count' => 5,  'total' => 1000.00],
                ],
            ],
            'expenses' => [
                'total' => 0.00,
                'categories' => [],
                'note' => 'Ausgaben 2023 nicht separat erfasst (Konto 59000 ohne SKK-Tag)',
            ],
            'net_result' => 79330.67,
        ];
    }

    private static function get_sachkundekurs_2024(): array {
        return [
            'title' => 'Sachkundekurse ECLS 2024',
            'period' => 'Januar - Dezember 2024',
            'income' => [
                'total' => 76001.00,
                'categories' => [
                    'Kursgebuehren Juni'       => ['count' => 24, 'total' => 21601.00],
                    'Kursgebuehren Dezember'   => ['count' => 23, 'total' => 20400.00],
                    'SKK Sponsoring'           => ['count' => 6,  'total' => 34000.00],
                ],
            ],
            'expenses' => [
                'total' => 6820.90,
                'categories' => [
                    'Fremdleistungen SKK' => ['count' => 26, 'total' => 6820.90],
                ],
            ],
            'net_result' => 69180.10,
        ];
    }

    /* ================================================================ */
    /*  ZEITSCHRIFT                                                       */
    /* ================================================================ */

    private static function get_zeitschrift_2023(): array {
        return [
            'title' => 'Zeitschrift Kardiotechnik 2023',
            'period' => 'Januar - Dezember 2023',
            'income' => [
                'total' => 10302.66,
                'categories' => [
                    'Anzeigen Titelseite'  => ['count' => 4, 'total' => 4950.00],
                    'Anzeigen 1/1 Seite'   => ['count' => 3, 'total' => 3100.00],
                    'Abonnements'          => ['count' => 20, 'total' => 1607.80],
                    'Sonstige'             => ['count' => 3, 'total' => 644.86],
                ],
            ],
            'expenses' => [
                'total' => 59317.82,
                'categories' => [
                    'Redaktion & Management'  => ['count' => 12, 'total' => 31961.49],
                    'Lektorat & Satz'         => ['count' => 8,  'total' => 11547.90],
                    'Druck'                   => ['count' => 6,  'total' => 9194.53],
                    'IT & Design'             => ['count' => 4,  'total' => 4095.00],
                    'Versand & Sonstiges'     => ['count' => 5,  'total' => 2518.90],
                ],
            ],
            'net_result' => -49015.16,
        ];
    }

    private static function get_zeitschrift_2024(): array {
        return [
            'title' => 'Zeitschrift Die Perfusiologie 2024',
            'period' => 'Januar - Dezember 2024',
            'income' => [
                'total' => 8272.00,
                'categories' => [
                    'Anzeigen Titelseite'  => ['count' => 4, 'total' => 4950.00],
                    'Abonnements'          => ['count' => 18, 'total' => 1672.00],
                    'Sonstige Anzeigen'    => ['count' => 2, 'total' => 1650.00],
                ],
            ],
            'expenses' => [
                'total' => 7667.15,
                'categories' => [
                    'Redaktion & Management' => ['count' => 6, 'total' => 7508.90],
                    'Sonstiges'              => ['count' => 2, 'total' => 158.25],
                ],
            ],
            'net_result' => 604.85,
        ];
    }
}
