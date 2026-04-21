<?php
/**
 * Vordefinierte HTML/CSS-Templates für den Zertifikat-Designer.
 * Werden im Admin als Starter geladen.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_VW_Certificate_Presets')) {

    class DGPTM_VW_Certificate_Presets {

        public static function get_all(): array {
            return [
                'classic'   => self::classic(),
                'corporate' => self::corporate(),
                'elegant'   => self::elegant(),
                'minimal'   => self::minimal(),
            ];
        }

        private static function classic(): array {
            return [
                'html' => <<<'HTML'
<div class="certificate">
    <div class="border">
        <div class="header">{{header_text}}</div>
        <p class="intro">Hiermit wird bescheinigt, dass</p>
        <div class="name">{{user_name}}</div>
        <p class="body">erfolgreich am Webinar</p>
        <div class="title">{{webinar_title}}</div>
        <p class="body">am {{date}} teilgenommen hat.</p>
        <div class="meta">
            <span>{{ebcp_points}} EBCP-Punkte</span>
            <span>VNR: {{vnr}}</span>
        </div>
        <div class="footer">
            <div class="signature">{{signature_text}}</div>
            <div class="site">{{footer_text}}</div>
        </div>
    </div>
</div>
HTML
                ,
                'css' => <<<'CSS'
@page { margin: 0; }
body { margin: 0; font-family: "DejaVu Serif", serif; color: #1d2327; }
.certificate { padding: 30px; }
.border { border: 6px double #005792; padding: 40px 50px; text-align: center; }
.header { font-size: 32px; font-weight: bold; color: #005792; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 30px; }
.intro { font-size: 14px; color: #646970; margin: 20px 0 10px; }
.name { font-size: 36px; font-weight: bold; color: #bd1622; font-style: italic; margin: 14px 0 20px; }
.body { font-size: 14px; color: #646970; margin: 8px 0; }
.title { font-size: 22px; font-weight: bold; color: #005792; margin: 14px 0; }
.meta { margin: 36px 0 16px; font-size: 13px; color: #1d2327; }
.meta span { margin: 0 16px; }
.footer { margin-top: 40px; display: table; width: 100%; }
.signature { display: table-cell; text-align: left; font-size: 12px; border-top: 1px solid #1d2327; padding-top: 8px; width: 45%; }
.site { display: table-cell; text-align: right; font-size: 12px; color: #646970; }
CSS
                ,
            ];
        }

        private static function corporate(): array {
            return [
                'html' => <<<'HTML'
<div class="cert">
    <div class="bar-top"></div>
    <div class="content">
        <div class="label">Fortbildungsnachweis</div>
        <h1>{{header_text}}</h1>
        <p class="lead">{{user_name}}</p>
        <p class="subline">hat erfolgreich am {{fortbildung_type}}</p>
        <p class="topic">{{webinar_title}}</p>
        <p class="date">am {{date}} teilgenommen.</p>
        <table class="info">
            <tr>
                <td><strong>EBCP-Punkte</strong><br>{{ebcp_points}}</td>
                <td><strong>VNR</strong><br>{{vnr}}</td>
                <td><strong>Ort</strong><br>{{location}}</td>
            </tr>
        </table>
        <div class="sig">{{signature_text}}</div>
    </div>
    <div class="bar-bottom">{{footer_text}}</div>
</div>
HTML
                ,
                'css' => <<<'CSS'
@page { margin: 0; }
body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1d2327; }
.cert { position: relative; }
.bar-top { height: 18px; background: #005792; }
.content { padding: 40px 60px 30px; }
.label { font-size: 11px; letter-spacing: 4px; text-transform: uppercase; color: #646970; margin-bottom: 8px; }
h1 { font-size: 28px; margin: 0 0 32px; color: #005792; font-weight: 600; }
.lead { font-size: 32px; font-weight: bold; margin: 0 0 4px; color: #1d2327; }
.subline { font-size: 14px; color: #646970; margin: 0; }
.topic { font-size: 20px; font-weight: 600; margin: 12px 0 6px; color: #005792; }
.date { font-size: 14px; color: #646970; margin: 0 0 24px; }
.info { width: 100%; margin: 24px 0; border-top: 1px solid #e2e6ea; border-bottom: 1px solid #e2e6ea; }
.info td { padding: 14px 0; font-size: 13px; color: #1d2327; }
.info strong { color: #646970; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
.sig { margin-top: 30px; font-size: 13px; color: #1d2327; }
.bar-bottom { background: #f5f7fa; padding: 14px 60px; font-size: 11px; color: #646970; text-align: right; letter-spacing: 1px; }
CSS
                ,
            ];
        }

        private static function elegant(): array {
            return [
                'html' => <<<'HTML'
<div class="cert">
    <div class="ornament">❧</div>
    <h1>{{header_text}}</h1>
    <div class="divider">— verliehen an —</div>
    <div class="name">{{user_name}}</div>
    <div class="desc">
        für die Teilnahme an
        <em>{{webinar_title}}</em>
        am {{date}}.
    </div>
    <div class="points">{{ebcp_points}} EBCP-Punkte · {{fortbildung_type}}</div>
    <div class="footer">
        <div class="sig">{{signature_text}}</div>
        <div class="org">{{footer_text}}</div>
    </div>
</div>
HTML
                ,
                'css' => <<<'CSS'
@page { margin: 0; }
body { margin: 0; font-family: "DejaVu Serif", serif; color: #2c2c2c; }
.cert { padding: 60px 80px; text-align: center; background: #fdfcf8; height: 100%; }
.ornament { font-size: 42px; color: #bd1622; margin-bottom: 10px; }
h1 { font-family: "DejaVu Serif", serif; font-size: 40px; font-weight: normal; font-style: italic; color: #005792; margin: 0 0 20px; letter-spacing: 2px; }
.divider { font-size: 12px; color: #999; letter-spacing: 3px; margin: 20px 0; }
.name { font-size: 44px; font-weight: bold; color: #2c2c2c; margin: 10px 0 20px; font-family: "DejaVu Serif", serif; }
.desc { font-size: 15px; color: #555; line-height: 1.8; margin: 20px 40px; }
.desc em { color: #005792; font-weight: 600; font-style: normal; }
.points { font-size: 13px; color: #bd1622; letter-spacing: 2px; text-transform: uppercase; margin: 30px 0; }
.footer { margin-top: 50px; display: table; width: 100%; }
.sig, .org { display: table-cell; width: 50%; font-size: 12px; color: #777; padding-top: 10px; border-top: 1px solid #ccc; }
.sig { text-align: left; }
.org { text-align: right; }
CSS
                ,
            ];
        }

        private static function minimal(): array {
            return [
                'html' => <<<'HTML'
<div class="cert">
    <div class="top">{{site_name}}</div>
    <div class="kicker">{{header_text}}</div>
    <div class="name">{{user_name}}</div>
    <div class="rule"></div>
    <div class="title">{{webinar_title}}</div>
    <div class="date">{{date}}</div>
    <div class="meta">{{ebcp_points}} Punkte · VNR {{vnr}}</div>
    <div class="foot">{{footer_text}}</div>
</div>
HTML
                ,
                'css' => <<<'CSS'
@page { margin: 0; }
body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1d2327; }
.cert { padding: 70px 90px; }
.top { font-size: 10px; letter-spacing: 6px; text-transform: uppercase; color: #646970; margin-bottom: 60px; }
.kicker { font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #005792; margin-bottom: 12px; }
.name { font-size: 42px; font-weight: 300; color: #1d2327; margin-bottom: 40px; letter-spacing: -1px; }
.rule { height: 1px; background: #005792; width: 60px; margin: 40px 0; }
.title { font-size: 22px; font-weight: 600; color: #1d2327; margin-bottom: 8px; }
.date { font-size: 14px; color: #646970; margin-bottom: 40px; }
.meta { font-size: 12px; color: #646970; letter-spacing: 1px; text-transform: uppercase; }
.foot { position: absolute; bottom: 30px; right: 90px; font-size: 10px; color: #646970; letter-spacing: 2px; }
CSS
                ,
            ];
        }
    }
}
