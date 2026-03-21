<?php
/**
 * Plugin Name: DGPTM - Formelsammlung
 * Description: Medizinische und technische Formelsammlung für die Perfusionstechnik mit KaTeX-Darstellung und Auto-Berechnung.
 * Version: 1.0.0
 * Author: Sebastian Melzer / DGPTM
 * Text Domain: dgptm-formelsammlung
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DGPTM_Formelsammlung' ) ) {

    class DGPTM_Formelsammlung {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;
        private $version = '1.0.0';

        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path( __FILE__ );
            $this->plugin_url  = plugin_dir_url( __FILE__ );

            add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
            add_shortcode( 'dgptm_formelsammlung', [ $this, 'render_shortcode' ] );
        }

        /**
         * Assets registrieren (noch nicht einbinden — erst im Shortcode)
         */
        public function register_assets() {
            // KaTeX CDN
            wp_register_style( 'katex',
                'https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css',
                [], '0.16.11'
            );
            wp_register_script( 'katex',
                'https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js',
                [], '0.16.11', true
            );

            // Modul-Assets
            wp_register_style( 'dgptm-formelsammlung',
                $this->plugin_url . 'assets/css/formelsammlung.css',
                [ 'katex' ], $this->version
            );
            wp_register_script( 'dgptm-formelsammlung',
                $this->plugin_url . 'assets/js/formelsammlung.js',
                [ 'katex' ], $this->version, true
            );
        }

        /**
         * Shortcode [dgptm_formelsammlung]
         */
        public function render_shortcode( $atts ) {
            if ( ! is_user_logged_in() ) {
                return '<div class="mc-login-required">'
                     . '<span class="dashicons dashicons-lock" style="font-size:48px;width:48px;height:48px;margin-bottom:15px;"></span>'
                     . '<p>Diese Seite ist nur für angemeldete Benutzer zugänglich.</p>'
                     . '<p><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Zum Login</a></p>'
                     . '</div>';
            }

            wp_enqueue_style( 'dgptm-formelsammlung' );
            wp_enqueue_script( 'dgptm-formelsammlung' );

            ob_start();
            ?>
            <div class="mc-calculator">

                <!-- Header -->
                <div class="mc-header">
                    <h2>Medizinische Formelsammlung</h2>
                    <button type="button" class="mc-reset-btn" id="mc-reset-all">Alle zurücksetzen</button>
                </div>

                <!-- Globale Eingaben -->
                <div class="mc-global-inputs">
                    <div class="mc-global-grid">
                        <div class="mc-input-group">
                            <label for="mc-global-gender">Geschlecht</label>
                            <select id="mc-global-gender" data-global="gender">
                                <option value="">-- Wählen --</option>
                                <option value="m">Männlich</option>
                                <option value="f">Weiblich</option>
                            </select>
                        </div>
                        <div class="mc-input-group">
                            <label for="mc-global-height">Größe (cm)</label>
                            <input type="number" id="mc-global-height" data-global="height" step="1" min="50" max="250" inputmode="numeric">
                        </div>
                        <div class="mc-input-group">
                            <label for="mc-global-weight">Gewicht (kg)</label>
                            <input type="number" id="mc-global-weight" data-global="weight" step="0.1" min="1" max="300" inputmode="decimal">
                        </div>
                        <div class="mc-input-group">
                            <label for="mc-global-hb">Hb (g/dl)</label>
                            <input type="number" id="mc-global-hb" data-global="hb" step="0.1" min="1" max="25" inputmode="decimal">
                        </div>
                    </div>
                </div>

                <!-- Kategorie-Filter -->
                <div class="mc-filter-bar">
                    <button type="button" class="mc-filter-btn mc-filter-active" data-filter="all">Alle</button>
                    <button type="button" class="mc-filter-btn" data-filter="gdp">GDP</button>
                    <button type="button" class="mc-filter-btn" data-filter="medical">Medizinisch</button>
                    <button type="button" class="mc-filter-btn" data-filter="technical">Technisch</button>
                </div>

                <!-- ==================== GOAL DIRECTED PERFUSION ==================== -->
                <div class="mc-section" data-category="gdp">
                <h3 class="mc-section-title">Goal Directed Perfusion (GDP)</h3>
                <div class="mc-cards-grid">

                    <!-- GDP: Soll-Flussrate -->
                    <?php echo $this->card( 'gdp-flow', 'Soll-Flussrate', 'Q_{soll} = BSA \times CI_{Ziel}', [
                        [ 'bsa', 'KOF (m²)', 'number', [ 'step' => '0.01' ] ],
                        [ 'ciTarget', 'CI Ziel (l/min/m²)', 'number', [ 'step' => '0.1', 'value' => '2.4' ] ],
                    ], 'l/min', 'Standard-CI: 2,4 l/min/m² (Hypothermie: 1,8–2,0)' ); ?>

                    <!-- GDP: Soll-DO2 -->
                    <?php echo $this->card( 'gdp-do2', 'Soll-DO₂ (kritische Grenze)', 'DO_{2,soll} = BSA \times DO_{2I,Ziel}', [
                        [ 'bsa', 'KOF (m²)', 'number', [ 'step' => '0.01' ] ],
                        [ 'do2Target', 'DO₂I Ziel (ml/min/m²)', 'number', [ 'step' => '1', 'value' => '280' ] ],
                    ], 'ml/min', 'Kritisch: &lt; 272 ml/min/m². Empfohlen: &ge; 280 ml/min/m²' ); ?>

                    <!-- GDP: Aktueller DO2I -->
                    <?php echo $this->card( 'gdp-do2i-actual', 'Aktueller DO₂I', 'DO_{2I} = \frac{Hb \times 1{,}34 \times SaO_2 \times CI \times 10}{100}', [
                        [ 'hb', 'Hb (g/dl)', 'number', [ 'step' => '0.1' ] ],
                        [ 'sat', 'SaO₂ (%)', 'number', [ 'step' => '0.1', 'value' => '100' ] ],
                        [ 'ciActual', 'CI (l/min/m²)', 'number', [ 'step' => '0.1' ] ],
                    ], 'ml/min/m²', 'Ziel: &ge; 280. Kritisch: &lt; 272' ); ?>

                    <!-- GDP: Minimaler Hb -->
                    <?php echo $this->card( 'gdp-min-hb', 'Minimaler Hb für Ziel-DO₂', 'Hb_{min} = \frac{DO_{2I,Ziel}}{CI \times 1{,}34 \times SaO_2 \times 10}', [
                        [ 'do2iTarget', 'DO₂I Ziel (ml/min/m²)', 'number', [ 'step' => '1', 'value' => '280' ] ],
                        [ 'ciActual', 'Aktueller CI (l/min/m²)', 'number', [ 'step' => '0.1' ] ],
                        [ 'sat', 'SaO₂ (%)', 'number', [ 'step' => '0.1', 'value' => '100' ] ],
                    ], 'g/dl', 'Nadir-Hb unter dem DO₂ kritisch wird' ); ?>

                    <!-- GDP: Nötiger Fluss -->
                    <?php echo $this->card( 'gdp-flow-for-hb', 'Nötiger Fluss für Ziel-DO₂I', 'Q = \frac{DO_{2I,Ziel} \times BSA}{Hb \times 1{,}34 \times \frac{SaO_2}{100} \times 10}', [
                        [ 'do2iTarget', 'DO₂I Ziel (ml/min/m²)', 'number', [ 'step' => '1', 'value' => '280' ] ],
                        [ 'bsa', 'KOF (m²)', 'number', [ 'step' => '0.01' ] ],
                        [ 'hb', 'Aktueller Hb (g/dl)', 'number', [ 'step' => '0.1' ] ],
                        [ 'sat', 'SaO₂ (%)', 'number', [ 'step' => '0.1', 'value' => '100' ] ],
                    ], 'l/min', 'Benötigter Pumpenfluss um DO₂I-Ziel zu erreichen' ); ?>

                    <!-- GDP: Transfusionstrigger -->
                    <?php echo $this->card( 'gdp-transfusion', 'Transfusionstrigger', 'Hb_{trigger} = \frac{272}{CI \times 1{,}34 \times SaO_2 \times 10}', [
                        [ 'ciActual', 'Max. CI (l/min/m²)', 'number', [ 'step' => '0.1' ] ],
                        [ 'sat', 'SaO₂ (%)', 'number', [ 'step' => '0.1', 'value' => '100' ] ],
                    ], 'g/dl', 'Hb unter dem bei max. Fluss DO₂I &lt; 272 → Transfusion nötig' ); ?>

                    <!-- GDP: Hb nach Hämodilution -->
                    <?php echo $this->card( 'hb-dilution', 'Hb nach Hämodilution', 'Hb_{neu} = \frac{Hb \times BV}{BV + V_{priming}}', [
                        [ 'hb', 'Hb aktuell (g/dl)', 'number', [ 'step' => '0.1' ] ],
                        [ 'bloodVolume', 'Blutvolumen (ml)', 'number', [ 'step' => '1' ] ],
                        [ 'primingVol', 'Priming-Vol. (ml)', 'number', [ 'step' => '1' ] ],
                    ], 'g/dl' ); ?>

                    <!-- GDP: Sauerstoffgehalt -->
                    <?php echo $this->card( 'o2-content', 'Sauerstoffgehalt (CaO₂)', 'CaO_2 = (Hb \times 1{,}34 \times \frac{SaO_2}{100}) + (PaO_2 \times 0{,}003)', [
                        [ 'hb', 'Hb (g/dl)', 'number', [ 'step' => '0.1' ] ],
                        [ 'sat', 'Sättigung (%)', 'number', [ 'step' => '0.1' ] ],
                        [ 'po2', 'PO₂ (mmHg)', 'number', [ 'step' => '0.1' ] ],
                    ], 'ml/dl', 'Hüfner-Zahl: 1,34 ml O₂/g Hb' ); ?>

                    <!-- GDP: AV-Differenz -->
                    <?php echo $this->card( 'avdo2', 'AV-Sauerstoffdifferenz (avDO₂)', 'avDO_2 = CaO_2 - CvO_2', [
                        [ 'cao2', 'CaO₂ (ml/dl)', 'number', [ 'step' => '0.1' ] ],
                        [ 'cvo2', 'CvO₂ (ml/dl)', 'number', [ 'step' => '0.1' ] ],
                    ], 'ml/dl', 'Normal: 4–6 ml/dl' ); ?>

                    <!-- GDP: DO2 -->
                    <?php echo $this->card( 'do2', 'Sauerstoffangebot (DO₂ / DO₂I)', 'DO_2 = CaO_2 \times HZV \times 10', [
                        [ 'cao2', 'CaO₂ (ml/dl)', 'number', [ 'step' => '0.1' ] ],
                        [ 'co', 'HZV (l/min)', 'number', [ 'step' => '0.1' ] ],
                        [ 'bsa', 'KOF (m²)', 'number', [ 'step' => '0.01' ] ],
                    ], 'ml/min', 'DO₂I = DO₂ / KOF. Normal: 520–720 ml/min/m²' ); ?>

                    <!-- GDP: VO2 -->
                    <?php echo $this->card( 'vo2', 'Sauerstoffverbrauch (VO₂ / VO₂I)', 'VO_2 = avDO_2 \times HZV \times 10', [
                        [ 'avdo2', 'avDO₂ (ml/dl)', 'number', [ 'step' => '0.1' ] ],
                        [ 'co', 'HZV (l/min)', 'number', [ 'step' => '0.1' ] ],
                        [ 'bsa', 'KOF (m²)', 'number', [ 'step' => '0.01' ] ],
                    ], 'ml/min', 'VO₂I = VO₂ / KOF. Normal: 110–160 ml/min/m²' ); ?>

                    <!-- GDP: O2ER -->
                    <?php echo $this->card( 'o2er', 'Sauerstoffextraktionsrate (O₂ER)', 'O_2ER = \frac{VO_2}{DO_2} \times 100', [
                        [ 'vo2', 'VO₂ (ml/min)', 'number', [ 'step' => '1' ] ],
                        [ 'do2', 'DO₂ (ml/min)', 'number', [ 'step' => '1' ] ],
                    ], '%', 'Normal: 22–32 %' ); ?>

                    <!-- GDP: Herzindex -->
                    <?php echo $this->card( 'ci', 'Herzindex (CI)', 'CI = \frac{HZV}{BSA}', [
                        [ 'co', 'HZV (l/min)', 'number', [ 'step' => '0.1' ] ],
                        [ 'bsa', 'KOF (m²)', 'number', [ 'step' => '0.01' ] ],
                    ], 'l/min/m²', 'Normal: 2,5–4,0' ); ?>

                </div>
                </div><!-- /gdp -->

                <!-- ==================== MEDIZINISCHE FORMELN ==================== -->
                <div class="mc-section" data-category="medical">
                <h3 class="mc-section-title">Medizinische Formeln</h3>
                <div class="mc-cards-grid">

                    <!-- 1. Blutvolumen nach Nadler -->
                    <?php echo $this->card( 'blood-volume', 'Blutvolumen nach Nadler', 'BV_{m} = 0{,}3669 \cdot H^3 + 0{,}03219 \cdot W + 0{,}6041', [
                        [ 'gender', 'Geschlecht', 'select', [ 'm' => 'Männlich', 'f' => 'Weiblich' ] ],
                        [ 'height', 'Größe (cm)', 'number', [ 'step' => '1' ] ],
                        [ 'weight', 'Gewicht (kg)', 'number', [ 'step' => '0.1' ] ],
                    ], 'l', 'Größe in Metern. ♂ / ♀ unterschiedliche Koeffizienten.' ); ?>

                    <!-- 2. BSA Mosteller -->
                    <?php echo $this->card( 'bsa-mosteller', 'KOF nach Mosteller', 'BSA = \sqrt{\frac{H \times W}{3600}}', [
                        [ 'height', 'Größe (cm)', 'number', [ 'step' => '1' ] ],
                        [ 'weight', 'Gewicht (kg)', 'number', [ 'step' => '0.1' ] ],
                    ], 'm²' ); ?>

                    <!-- 3. BSA DuBois -->
                    <?php echo $this->card( 'bsa-dubois', 'KOF nach DuBois & DuBois', 'BSA = 0{,}007184 \times H^{0{,}725} \times W^{0{,}425}', [
                        [ 'height', 'Größe (cm)', 'number', [ 'step' => '1' ] ],
                        [ 'weight', 'Gewicht (kg)', 'number', [ 'step' => '0.1' ] ],
                    ], 'm²' ); ?>

                    <!-- 4. Ideales Körpergewicht -->
                    <?php echo $this->card( 'ibw-devine', 'Ideales Körpergewicht (Devine)', 'iBW_m = 50 + 2{,}3 \times (H_{in} - 60)', [
                        [ 'gender', 'Geschlecht', 'select', [ 'm' => 'Männlich', 'f' => 'Weiblich' ] ],
                        [ 'height', 'Größe (cm)', 'number', [ 'step' => '1' ] ],
                    ], 'kg', '1 inch = 2,54 cm' ); ?>

                    <!-- 5. Angepasstes Körpergewicht -->
                    <?php echo $this->card( 'abw', 'Angepasstes Körpergewicht (aBW)', 'aBW = iBW + 0{,}4 \times (W_{akt} - iBW)', [
                        [ 'ibw', 'Ideales KG (kg)', 'number', [ 'step' => '0.1' ] ],
                        [ 'weight', 'Aktuelles KG (kg)', 'number', [ 'step' => '0.1' ] ],
                    ], 'kg' ); ?>

                    <!-- 7. MAP -->
                    <?php echo $this->card( 'map', 'Mittlerer Arterieller Druck (MAP)', 'MAP = \frac{2 \times P_{dia} + P_{sys}}{3}', [
                        [ 'systolic', 'Systolisch (mmHg)', 'number', [ 'step' => '1' ] ],
                        [ 'diastolic', 'Diastolisch (mmHg)', 'number', [ 'step' => '1' ] ],
                    ], 'mmHg' ); ?>

                    <!-- 8. SVR -->
                    <?php echo $this->card( 'svr', 'Systemisch Vaskulärer Widerstand (SVR)', 'SVR = \frac{(MAP - ZVD) \times 80}{HZV}', [
                        [ 'map', 'MAP (mmHg)', 'number', [ 'step' => '0.1' ] ],
                        [ 'cvp', 'ZVD (mmHg)', 'number', [ 'step' => '0.1' ] ],
                        [ 'co', 'HZV (l/min)', 'number', [ 'step' => '0.1' ] ],
                    ], 'dyn·s·cm⁻⁵', 'Normal: 800–1200' ); ?>

                    <!-- 9. PVR -->
                    <?php echo $this->card( 'pvr', 'Pulmonal Vaskulärer Widerstand (PVR)', 'PVR = \frac{(MPAP - PCWP) \times 80}{HZV}', [
                        [ 'mpap', 'MPAP (mmHg)', 'number', [ 'step' => '0.1' ] ],
                        [ 'pcwp', 'PCWP (mmHg)', 'number', [ 'step' => '0.1' ] ],
                        [ 'co', 'HZV (l/min)', 'number', [ 'step' => '0.1' ] ],
                    ], 'dyn·s·cm⁻⁵', 'Normal: 100–250' ); ?>

                    <!-- 10. Kalium-Substitution -->
                    <?php echo $this->card( 'potassium', 'Kalium-Substitution', 'K^+ = (K_{Ziel} - K_{Ist}) \times W \times 0{,}4', [
                        [ 'kTarget', 'K⁺ Ziel (mmol/l)', 'number', [ 'step' => '0.1' ] ],
                        [ 'kActual', 'K⁺ Ist (mmol/l)', 'number', [ 'step' => '0.1' ] ],
                        [ 'weight', 'Gewicht (kg)', 'number', [ 'step' => '0.1' ] ],
                    ], 'mmol' ); ?>

                    <!-- 11. NaBic -->
                    <?php echo $this->card( 'nabic', 'NaBic-Substitution', 'NaBic = |BE| \times W \times 0{,}3', [
                        [ 'be', 'Base Excess', 'number', [ 'step' => '0.1' ] ],
                        [ 'weight', 'Gewicht (kg)', 'number', [ 'step' => '0.1' ] ],
                    ], 'mmol' ); ?>

                    <!-- 12. TRIS -->
                    <?php echo $this->card( 'tris', 'TRIS-Puffer Substitution', 'TRIS = |BE| \times W \times 0{,}5', [
                        [ 'be', 'Base Excess', 'number', [ 'step' => '0.1' ] ],
                        [ 'weight', 'Gewicht (kg)', 'number', [ 'step' => '0.1' ] ],
                    ], 'mmol' ); ?>

                    <!-- VCO2 (bleibt in Medizin) -->
                    <?php echo $this->card( 'vco2', 'CO₂-Produktion (VCO₂)', 'VCO_2 = HZV \times (CvCO_2 - CaCO_2) \times 10', [
                        [ 'co', 'HZV (l/min)', 'number', [ 'step' => '0.1' ] ],
                        [ 'cvco2', 'CvCO₂ (ml/dl)', 'number', [ 'step' => '0.1' ] ],
                        [ 'caco2', 'CaCO₂ (ml/dl)', 'number', [ 'step' => '0.1' ] ],
                        [ 'bsa', 'KOF (m²)', 'number', [ 'step' => '0.01' ] ],
                    ], 'ml/min' ); ?>

                    <!-- HZV nach Fick -->
                    <?php echo $this->card( 'co-fick', 'Herzzeitvolumen nach Fick', 'HZV = \frac{VO_2}{avDO_2 \times 10}', [
                        [ 'vo2', 'VO₂ (ml/min)', 'number', [ 'step' => '1' ] ],
                        [ 'avdo2', 'avDO₂ (ml/dl)', 'number', [ 'step' => '0.1' ] ],
                    ], 'l/min' ); ?>

                </div>
                </div><!-- /medical -->

                <!-- ==================== TECHNISCHE FORMELN ==================== -->
                <div class="mc-section" data-category="technical">
                <h3 class="mc-section-title">Technische Formeln</h3>
                <div class="mc-cards-grid">

                    <!-- 21. Hagen-Poiseuille -->
                    <?php echo $this->card( 'hagen-poiseuille', 'Hagen-Poiseuille', 'Q = \frac{\pi \cdot \Delta P \cdot r^4}{8 \cdot \eta \cdot L}', [
                        [ 'dp', 'ΔP Druckdiff. (Pa)', 'number', [ 'step' => '0.1' ] ],
                        [ 'r', 'Radius r (m)', 'number', [ 'step' => '0.0001' ] ],
                        [ 'eta', 'Viskosität η (Pa·s)', 'number', [ 'step' => '0.0001' ] ],
                        [ 'l', 'Länge L (m)', 'number', [ 'step' => '0.01' ] ],
                    ], 'ml/s' ); ?>

                    <!-- 22. Reynolds -->
                    <?php echo $this->card( 'reynolds', 'Reynolds-Zahl', 'Re = \frac{\rho \cdot v \cdot d}{\eta}', [
                        [ 'rho', 'Dichte ρ (kg/m³)', 'number', [ 'step' => '0.1' ] ],
                        [ 'v', 'Geschw. v (m/s)', 'number', [ 'step' => '0.01' ] ],
                        [ 'd', 'Durchmesser d (m)', 'number', [ 'step' => '0.001' ] ],
                        [ 'eta', 'Viskosität η (Pa·s)', 'number', [ 'step' => '0.0001' ] ],
                    ], '', 'Re &lt; 2000: laminar · Re &gt; 4000: turbulent' ); ?>

                    <!-- 23. LaPlace -->
                    <?php echo $this->card( 'laplace', 'LaPlace (Kugelform)', 'T = \frac{P \cdot r}{2 \cdot h}', [
                        [ 'p', 'Druck P (Pa)', 'number', [ 'step' => '0.1' ] ],
                        [ 'r', 'Radius r (m)', 'number', [ 'step' => '0.001' ] ],
                        [ 'h', 'Wanddicke h (m)', 'number', [ 'step' => '0.0001' ] ],
                    ], 'Pa' ); ?>

                    <!-- 24. TMP -->
                    <?php echo $this->card( 'tmp', 'Transmembrandruck (TMP)', 'TMP = \frac{P_{art} + P_{ven}}{2} - P_{dial}', [
                        [ 'pArt', 'P arteriell (mmHg)', 'number', [ 'step' => '0.1' ] ],
                        [ 'pVen', 'P venös (mmHg)', 'number', [ 'step' => '0.1' ] ],
                        [ 'pDial', 'P Dialysat (mmHg)', 'number', [ 'step' => '0.1' ] ],
                    ], 'mmHg' ); ?>

                    <!-- 25. Schlauchvolumen -->
                    <div class="mc-card" data-formula="tube-volume">
                        <div class="mc-card-header">
                            <h4>Schlauchvolumen</h4>
                            <button type="button" class="mc-card-reset" title="Zurücksetzen">&#8634;</button>
                        </div>
                        <div class="mc-formula-display" data-katex="V = \pi \cdot r^2 \cdot L"></div>
                        <div class="mc-card-inputs">
                            <div class="mc-input-group">
                                <label>Radius r (mm)</label>
                                <input type="number" data-param="r" step="0.1" inputmode="decimal">
                                <div class="mc-inch-ref">
                                    <span class="mc-inch-btn" data-r="3.175">¼″ (⌀ 6,35 mm)</span>
                                    <span class="mc-inch-btn" data-r="4.7625">⅜″ (⌀ 9,53 mm)</span>
                                    <span class="mc-inch-btn" data-r="6.35">½″ (⌀ 12,7 mm)</span>
                                </div>
                            </div>
                            <div class="mc-input-group">
                                <label>Länge L (cm)</label>
                                <input type="number" data-param="l" step="0.1" inputmode="decimal">
                            </div>
                        </div>
                        <div class="mc-card-result">
                            <span class="mc-result-label">Ergebnis:</span>
                            <span class="mc-result-value mc-result-placeholder" id="result-tube-volume">--</span>
                            <span class="mc-result-unit">ml</span>
                        </div>
                        <div class="mc-note">Klick auf Zollmaß übernimmt den Radius</div>
                    </div>

                </div>
                </div><!-- /technical -->
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Einzelne Formel-Card rendern
         */
        private function card( $id, $title, $katex, $inputs, $unit, $note = '' ) {
            $html  = '<div class="mc-card" data-formula="' . esc_attr( $id ) . '">';
            $html .= '<div class="mc-card-header">';
            $html .= '<h4>' . esc_html( $title ) . '</h4>';
            $html .= '<button type="button" class="mc-card-reset" title="Zurücksetzen">&#8634;</button>';
            $html .= '</div>';

            // KaTeX Formel
            $html .= '<div class="mc-formula-display" data-katex="' . esc_attr( $katex ) . '"></div>';

            // Eingabefelder
            $html .= '<div class="mc-card-inputs">';
            foreach ( $inputs as $input ) {
                $param = $input[0];
                $label = $input[1];
                $type  = $input[2];
                $opts  = isset( $input[3] ) ? $input[3] : [];

                $html .= '<div class="mc-input-group">';
                $html .= '<label>' . esc_html( $label ) . '</label>';

                if ( $type === 'select' ) {
                    $html .= '<select data-param="' . esc_attr( $param ) . '">';
                    $html .= '<option value="">--</option>';
                    foreach ( $opts as $val => $text ) {
                        $html .= '<option value="' . esc_attr( $val ) . '">' . esc_html( $text ) . '</option>';
                    }
                    $html .= '</select>';
                } else {
                    $attrs = '';
                    foreach ( $opts as $k => $v ) {
                        $attrs .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
                    }
                    $html .= '<input type="number" data-param="' . esc_attr( $param ) . '" inputmode="decimal"' . $attrs . '>';
                }

                $html .= '</div>';
            }
            $html .= '</div>';

            // Ergebnis
            $html .= '<div class="mc-card-result">';
            $html .= '<span class="mc-result-label">Ergebnis:</span> ';
            $html .= '<span class="mc-result-value mc-result-placeholder" id="result-' . esc_attr( $id ) . '">--</span>';
            if ( $unit ) {
                $html .= ' <span class="mc-result-unit">' . esc_html( $unit ) . '</span>';
            }
            $html .= '</div>';

            // Hinweis
            if ( $note ) {
                $html .= '<div class="mc-note">' . wp_kses_post( $note ) . '</div>';
            }

            $html .= '</div>';
            return $html;
        }
    }
}

// Initialisierung
if ( ! isset( $GLOBALS['dgptm_formelsammlung_initialized'] ) ) {
    $GLOBALS['dgptm_formelsammlung_initialized'] = true;
    DGPTM_Formelsammlung::get_instance();
}
