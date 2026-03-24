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
        // Dynamische Jahre: 2025 bis aktuelles Jahr
        $current = intval(date('Y'));
        $dynamic = range(self::DYNAMIC_START_YEAR, $current);
        return array_unique(array_merge($static, $dynamic));
    }

    /* ------------------------------------------------------------ */
    /* Dynamische Berichte (ab 2025)                                 */
    /* ------------------------------------------------------------ */

    private function build_dynamic(string $report, int $year): array {
        $client = new DGPTM_FB_Zoho_Books_Client();
        $period = $this->get_date_range($report, $year);

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

        if ($rule['cross_year']) {
            // Juli YEAR bis Juni YEAR+1 (oder bis heute)
            $start = sprintf('%04d-%02d-01', $year, $start_month);
            $end_year = $year + 1;
            $end = sprintf('%04d-06-30', $end_year);
            // Nicht in die Zukunft
            $today = date('Y-m-d');
            if ($end > $today) {
                $end = $today;
            }
            $label = sprintf('Juli %d - %s', $year, ($end === $today) ? 'heute' : "Juni $end_year");
        } else {
            $start = "$year-01-01";
            $end = "$year-12-31";
            $today = date('Y-m-d');
            if ($end > $today) {
                $end = $today;
            }
            $label = "Januar - Dezember $year";
        }

        return ['start' => $start, 'end' => $end, 'label' => $label];
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
