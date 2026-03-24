<?php
/**
 * Report Builder - Entscheidet ob historisch (statisch) oder dynamisch (API).
 * <= 2024: Statische Daten aus DGPTM_FB_Historical_Data
 * >= 2025: Live-Daten aus Zoho Books API
 */
if (!defined('ABSPATH')) exit;

class DGPTM_FB_Report_Builder {

    const DYNAMIC_START_YEAR = 2025;

    // JT-Zeitraum: Juli bis Juni Folgejahr
    // SKK/Zeitschrift: Kalenderjahr
    const PERIOD_RULES = [
        'jahrestagung'  => ['start_month' => 7, 'cross_year' => true],
        'sachkundekurs' => ['start_month' => 1, 'cross_year' => false],
        'zeitschrift'   => ['start_month' => 1, 'cross_year' => false],
    ];

    public function get_report(string $report, int $year): array {
        // Verfuegbare Jahre
        $years = $this->get_available_years($report);

        // Statisch oder dynamisch?
        if ($year < self::DYNAMIC_START_YEAR) {
            $data = DGPTM_FB_Historical_Data::get($report, $year);
            if (!$data) {
                return ['error' => "Keine Daten fuer $report $year", 'years' => $years];
            }
            $data['source'] = 'static';
            $data['years'] = $years;
            return $data;
        }

        // Dynamisch ab 2025
        try {
            $data = $this->build_dynamic($report, $year);
            $data['source'] = 'live';
            $data['years'] = $years;
            return $data;
        } catch (\Throwable $e) {
            return [
                'error'  => 'API-Fehler: ' . $e->getMessage(),
                'years'  => $years,
                'source' => 'error',
            ];
        }
    }

    public function get_available_years(string $report): array {
        $static = DGPTM_FB_Historical_Data::available_years($report);
        $current = intval(date('Y'));
        $current_month = intval(date('m'));
        $rule = self::PERIOD_RULES[$report] ?? [];

        // Dynamische Jahre: 2025 bis aktuelles Jahr
        // Bei cross_year (JT): aktuelles Jahr nur zeigen wenn Startmonat erreicht
        $max_year = $current;
        if (!empty($rule['cross_year']) && $current_month < ($rule['start_month'] ?? 1)) {
            $max_year = $current - 1;
        }

        $dynamic = range(self::DYNAMIC_START_YEAR, max(self::DYNAMIC_START_YEAR, $max_year));
        return array_unique(array_merge($static, $dynamic));
    }

    /* ------------------------------------------------------------ */
    /* Dynamische Berichte (ab 2025)                                 */
    /* ------------------------------------------------------------ */

    private function build_dynamic(string $report, int $year): array {
        $client = new DGPTM_FB_Zoho_Books_Client();
        $period = $this->get_date_range($report, $year);

        // Zeitraum liegt komplett in der Zukunft
        if (!empty($period['future'])) {
            return [
                'title'    => self::PERIOD_RULES[$report] ? ucfirst($report) . " $year" : $report,
                'period'   => $period['label'],
                'income'   => ['total' => 0, 'count' => 0],
                'expenses' => ['total' => 0, 'count' => 0],
                'net_result' => 0,
                'note'     => 'Zeitraum hat noch nicht begonnen.',
            ];
        }

        switch ($report) {
            case 'jahrestagung':
                return $this->build_jahrestagung($client, $year, $period);
            case 'sachkundekurs':
                return $this->build_sachkundekurs($client, $year, $period);
            case 'zeitschrift':
                return $this->build_zeitschrift($client, $year, $period);
            default:
                throw new \InvalidArgumentException("Unbekannter Report: $report");
        }
    }

    private function get_date_range(string $report, int $year): array {
        $rule = self::PERIOD_RULES[$report];
        $start_month = $rule['start_month'];
        $today = date('Y-m-d');

        if ($rule['cross_year']) {
            // Juli YEAR bis Juni YEAR+1 (oder bis heute)
            $start = sprintf('%04d-%02d-01', $year, $start_month);
            $end_year = $year + 1;
            $end = sprintf('%04d-06-30', $end_year);

            if ($end > $today) {
                $end = $today;
            }

            // Start liegt in der Zukunft? → noch keine Daten
            if ($start > $today) {
                return ['start' => $start, 'end' => $start, 'label' => 'Noch nicht gestartet', 'future' => true];
            }

            $label = sprintf('Juli %d - %s', $year, ($end === $today) ? 'heute' : "Juni $end_year");
        } else {
            $start = "$year-01-01";
            $end = "$year-12-31";

            if ($end > $today) {
                $end = $today;
            }

            if ($start > $today) {
                return ['start' => $start, 'end' => $start, 'label' => 'Noch nicht gestartet', 'future' => true];
            }

            $label = "Januar - Dezember $year";
        }

        return ['start' => $start, 'end' => $end, 'label' => $label, 'future' => false];
    }

    private function build_jahrestagung($client, int $year, array $period): array {
        $income = $client->get_jt_income($period['start'], $period['end']);
        $expenses = $client->get_jt_expenses($period['start'], $period['end']);

        $jt_names = [
            2025 => '54. Internationale Jahrestagung der DGPTM und 17. Fokustagung Herz der DGTHG & DGPTM',
            2026 => '55. Internationale Jahrestagung der DGPTM und 18. Fokustagung Herz der DGTHG & DGPTM',
        ];

        return [
            'title'      => $jt_names[$year] ?? "Jahrestagung $year",
            'period'     => $period['label'],
            'income'     => $income,
            'expenses'   => $expenses,
            'net_result' => $income['total'] - $expenses['total'],
        ];
    }

    private function build_sachkundekurs($client, int $year, array $period): array {
        $income = $client->get_skk_income($period['start'], $period['end']);
        $expenses = $client->get_skk_expenses($period['start'], $period['end']);

        return [
            'title'      => "Sachkundekurse ECLS $year",
            'period'     => $period['label'],
            'income'     => $income,
            'expenses'   => $expenses,
            'net_result' => $income['total'] - $expenses['total'],
        ];
    }

    private function build_zeitschrift($client, int $year, array $period): array {
        $income = $client->get_zeitschrift_income($period['start'], $period['end']);
        $expenses = $client->get_zeitschrift_expenses($period['start'], $period['end']);

        return [
            'title'      => "Zeitschrift Die Perfusiologie $year",
            'period'     => $period['label'],
            'income'     => $income,
            'expenses'   => $expenses,
            'net_result' => $income['total'] - $expenses['total'],
        ];
    }
}
