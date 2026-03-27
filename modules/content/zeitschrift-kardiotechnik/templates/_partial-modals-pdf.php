<?php
/**
 * Partial: Modals für PDF-Import
 * Enthält 1 Modal: zk-modal-ai-settings (KI-Einstellungen für den PDF-Import-Wizard).
 *
 * @package Zeitschrift_Kardiotechnik
 */

if (!defined('ABSPATH')) exit;
?>

<div class="zk-section-wrap" data-section="modals-pdf">

    <!-- Modal: KI-Einstellungen -->
    <div class="zk-modal" id="zk-modal-ai-settings">
        <div class="zk-modal-overlay"></div>
        <div class="zk-modal-content">
            <div class="zk-modal-header">
                <h3>KI-Einstellungen</h3>
                <button type="button" class="zk-modal-close">&times;</button>
            </div>
            <div class="zk-modal-body">
                <form id="zk-ai-settings-form">
                    <div class="zk-form-group">
                        <label for="zk-ai-provider">KI-Provider</label>
                        <select id="zk-ai-provider" class="zk-select">
                            <option value="anthropic">Anthropic (Claude)</option>
                            <option value="openai">OpenAI (GPT-4)</option>
                        </select>
                    </div>
                    <div class="zk-form-group">
                        <label for="zk-ai-model">Modell</label>
                        <select id="zk-ai-model" class="zk-select">
                            <option value="claude-sonnet-4-20250514">Claude Sonnet 4 (empfohlen)</option>
                            <option value="claude-opus-4-5-20251101">Claude Opus 4.5</option>
                            <option value="claude-3-5-sonnet-20241022">Claude 3.5 Sonnet</option>
                            <option value="claude-3-5-haiku-20241022">Claude 3.5 Haiku (schnell)</option>
                            <option value="gpt-4o">GPT-4o</option>
                        </select>
                    </div>
                    <div class="zk-form-group">
                        <label for="zk-ai-key">API-Key</label>
                        <input type="password" id="zk-ai-key" class="zk-input" placeholder="sk-...">
                        <small id="zk-ai-key-status"></small>
                    </div>
                </form>
            </div>
            <div class="zk-modal-footer">
                <button type="button" class="zk-btn zk-btn-secondary zk-modal-cancel">Abbrechen</button>
                <button type="button" class="zk-btn zk-btn-primary" id="zk-save-ai-settings">Speichern</button>
            </div>
        </div>
    </div>

</div>
