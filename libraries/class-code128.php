<?php
/**
 * Code128 Barcode Generator für FPDF
 * Korrekte Implementation nach Code128-Spezifikation
 */

if (!class_exists('DGPTM_Code128')) {
class DGPTM_Code128 {
    /**
     * Code128 Pattern-Tabelle (Bar-Space-Sequenzen)
     * Index 0-105: Daten, 106: Stop-Pattern
     */
    protected static $T128 = array(
        // 0-9
        array(2,1,2,2,2,2),array(2,2,2,1,2,2),array(2,2,2,2,2,1),array(1,2,1,2,2,3),
        array(1,2,1,3,2,2),array(1,3,1,2,2,2),array(1,2,2,2,1,3),array(1,2,2,3,1,2),
        array(1,3,2,2,1,2),array(2,2,1,2,1,3),
        // 10-19
        array(2,2,1,3,1,2),array(2,3,1,2,1,2),array(1,1,2,2,3,2),array(1,2,2,1,3,2),
        array(1,2,2,2,3,1),array(1,1,3,2,2,2),array(1,2,3,1,2,2),array(1,2,3,2,2,1),
        array(2,2,3,2,1,1),array(2,2,1,1,3,2),
        // 20-29
        array(2,2,1,2,3,1),array(2,1,3,2,1,2),array(2,2,3,1,1,2),array(3,1,2,1,3,1),
        array(3,1,1,2,2,2),array(3,2,1,1,2,2),array(3,2,1,2,2,1),array(3,1,2,2,1,2),
        array(3,2,2,1,1,2),array(3,2,2,2,1,1),
        // 30-39
        array(2,1,2,1,2,3),array(2,1,2,3,2,1),array(2,3,2,1,2,1),array(1,1,1,3,2,3),
        array(1,3,1,1,2,3),array(1,3,1,3,2,1),array(1,1,2,3,1,3),array(1,3,2,1,1,3),
        array(1,3,2,3,1,1),array(2,1,1,3,1,3),
        // 40-49
        array(2,3,1,1,1,3),array(2,3,1,3,1,1),array(1,1,2,1,3,3),array(1,1,2,3,3,1),
        array(1,3,2,1,3,1),array(1,1,3,1,2,3),array(1,1,3,3,2,1),array(1,3,3,1,2,1),
        array(3,1,3,1,2,1),array(2,1,1,3,3,1),
        // 50-59
        array(2,3,1,1,3,1),array(2,1,3,1,1,3),array(2,1,3,3,1,1),array(2,1,3,1,3,1),
        array(3,1,1,1,2,3),array(3,1,1,3,2,1),array(3,3,1,1,2,1),array(3,1,2,1,1,3),
        array(3,1,2,3,1,1),array(3,3,2,1,1,1),
        // 60-69
        array(3,1,4,1,1,1),array(2,2,1,4,1,1),array(4,3,1,1,1,1),array(1,1,1,2,2,4),
        array(1,1,1,4,2,2),array(1,2,1,1,2,4),array(1,2,1,4,2,1),array(1,4,1,1,2,2),
        array(1,4,1,2,2,1),array(1,1,2,2,1,4),
        // 70-79
        array(1,1,2,4,1,2),array(1,2,2,1,1,4),array(1,2,2,4,1,1),array(1,4,2,1,1,2),
        array(1,4,2,2,1,1),array(2,4,1,2,1,1),array(2,2,1,1,1,4),array(4,1,3,1,1,1),
        array(2,4,1,1,1,2),array(1,3,4,1,1,1),
        // 80-89
        array(1,1,1,2,4,2),array(1,2,1,1,4,2),array(1,2,1,2,4,1),array(1,1,4,2,1,2),
        array(1,2,4,1,1,2),array(1,2,4,2,1,1),array(4,1,1,2,1,2),array(4,2,1,1,1,2),
        array(4,2,1,2,1,1),array(2,1,2,1,4,1),
        // 90-99
        array(2,1,4,1,2,1),array(4,1,2,1,2,1),array(1,1,1,1,4,3),array(1,1,1,3,4,1),
        array(1,3,1,1,4,1),array(1,1,4,1,1,3),array(1,1,4,3,1,1),array(4,1,1,1,1,3),
        array(4,1,1,3,1,1),array(1,1,3,1,4,1),
        // 100-105
        array(1,1,4,1,3,1),array(3,1,1,1,4,1),array(4,1,1,1,3,1),array(2,1,1,4,1,2),
        array(2,1,1,2,1,4),array(2,1,1,2,3,2),
        // 106: STOP (inkl. Terminator-Bar)
        array(2,3,3,1,1,1,2)
    );

    /**
     * Kodiert einen String in Code128
     * Optimiert für EFN (15 Ziffern): Verwendet Code C für Zahlenpaare
     *
     * @param string $code Der zu kodierende String
     * @return array Array von Pattern-Indizes
     */
    public static function encode($code) {
        $code = (string)$code;
        if ($code === '') return array(103, 106); // Start B + Stop

        $enc = array();
        $len = strlen($code);

        // Prüfe ob nur Ziffern (EFN-Fall: 15 Ziffern)
        if (preg_match('/^[0-9]+$/', $code) && strlen($code) >= 2) {
            // Start C für reine Zahlenketten
            $enc[] = 105; // Start C

            // Kodiere Zahlenpaare
            for ($i = 0; $i < $len; $i += 2) {
                if ($i + 1 < $len) {
                    // Zwei Ziffern als Zahlenpaar (00-99)
                    $pair = intval(substr($code, $i, 2));
                    $enc[] = $pair;
                } else {
                    // Ungerade Anzahl: Wechsel zu Code B für letzte Ziffer
                    $enc[] = 100; // Code B
                    $enc[] = ord($code[$i]) - 32;
                }
            }
        } else {
            // Start B für alphanumerische Strings
            $enc[] = 104; // Start B

            for ($i = 0; $i < $len; $i++) {
                // Code B: ASCII 32-127 → Pattern 0-95
                $ascii = ord($code[$i]);
                if ($ascii >= 32 && $ascii <= 127) {
                    $enc[] = $ascii - 32;
                } else {
                    $enc[] = 0; // Space als Fallback
                }
            }
        }

        // Berechne Prüfziffer (Checksum)
        $sum = $enc[0]; // Start-Code
        for ($k = 1; $k < count($enc); $k++) {
            $sum += $enc[$k] * $k;
        }
        $checksum = $sum % 103;
        $enc[] = $checksum;

        // Stop-Pattern
        $enc[] = 106;

        return $enc;
    }

    /**
     * Zeichnet einen Code128-Barcode in ein FPDF-Dokument
     *
     * @param object $pdf FPDF-Instanz
     * @param float $x X-Position (mm)
     * @param float $y Y-Position (mm)
     * @param float $w Breite (mm)
     * @param float $h Höhe (mm)
     * @param string $code Zu kodierender String
     * @param float $quiet_zone Quiet Zone Breite in mm (Standard: 2.5mm = ~10 Module)
     */
    public static function draw($pdf, $x, $y, $w, $h, $code, $quiet_zone = 2.5) {
        $seq = self::encode($code);

        // Berechne Gesamtzahl der Module
        $modules = 0;
        foreach ($seq as $idx) {
            if (isset(self::$T128[$idx])) {
                foreach (self::$T128[$idx] as $v) {
                    $modules += $v;
                }
            }
        }

        if ($modules == 0) return; // Fehlerfall

        // Abzug der Quiet Zones links und rechts von der Gesamtbreite
        $barcode_width = $w - (2 * $quiet_zone);
        if ($barcode_width <= 0) {
            // Fallback: Keine Quiet Zone wenn zu klein
            $barcode_width = $w;
            $quiet_zone = 0;
        }

        // Modul-Breite in mm
        $moduleWidth = $barcode_width / $modules;
        $currentX = $x + $quiet_zone;

        // Optional: Zeichne weißen Hintergrund für Quiet Zone (für bessere Lesbarkeit)
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $w, $h, 'F');
        $pdf->SetFillColor(0, 0, 0); // Zurück zu Schwarz für Balken

        // Zeichne jeden Code als Bar-Space-Sequenz
        foreach ($seq as $idx) {
            if (!isset(self::$T128[$idx])) continue;

            $pattern = self::$T128[$idx];
            $isBar = true; // Startet immer mit Bar (schwarz)

            foreach ($pattern as $width) {
                $segmentWidth = $width * $moduleWidth;

                if ($isBar) {
                    // Zeichne schwarzen Balken
                    $pdf->Rect($currentX, $y, $segmentWidth, $h, 'F');
                }
                // Space (weiß) wird nicht gezeichnet

                $currentX += $segmentWidth;
                $isBar = !$isBar;
            }
        }
    }
}
}
?>
