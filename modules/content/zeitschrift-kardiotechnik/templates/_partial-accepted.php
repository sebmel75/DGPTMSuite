<?php
/**
 * Partial: Akzeptierte Einreichungen-Tab
 * Zeigt die Liste akzeptierter Einreichungen mit initialem Ladezustand.
 *
 * @package Zeitschrift_Kardiotechnik
 */

if (!defined('ABSPATH')) exit;
?>

<div class="zk-section-wrap" data-section="accepted">
    <div class="zk-accepted-list" id="zk-accepted-list">
        <div class="zk-loading">
            <div class="zk-spinner"></div>
            <span>Lade Einreichungen...</span>
        </div>
    </div>
</div>
