<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Report;

/**
 * @internal
 * Shared visual identity for PhpSpec's HTML artifacts (the results report and
 * the coverage report). The header band follows the phpspec site identity
 * (dark bar, logo, monospace wordmark, green accent) with a pass-ratio meter
 * fused to its bottom edge; the body is a modernised take on the classic
 * RSpec TextMate look with its saturated green/red group bars. Everything is
 * inlined so reports stay self-contained.
 */
final class HtmlTheme
{
    /**
     * Wraps body content in a complete standalone HTML document carrying
     * the brand stylesheet.
     *
     * @param string $title the document title
     * @param string $body the body HTML
     * @return string the full HTML document
     */
    public static function page(string $title, string $body): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES);
        $css = self::css();

        return "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . "<title>$safeTitle</title>\n<style>$css</style>\n</head>\n<body>\n$body</body>\n</html>";
    }

    /**
     * The phpspec logo from phpspec-site, inlined so reports stay self-contained.
     */
    private const LOGO = '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';

    /**
     * Renders the dark header band in the phpspec site identity: logo and
     * monospace wordmark with the subtitle on the left, monospace meta
     * (counts, duration) on the right.
     *
     * @param string $subtitle the artifact name, e.g. "Results" or "Coverage"
     * @param string $meta plain-text meta shown right-aligned, e.g. counts
     * @return string the header HTML
     */
    public static function header(string $subtitle, string $meta): string
    {
        $safeSubtitle = htmlspecialchars($subtitle, ENT_QUOTES);
        $safeMeta = htmlspecialchars($meta, ENT_QUOTES);
        $logo = self::LOGO;

        return "<header class=\"band\">\n"
            . "<p class=\"wordmark\"><img src=\"data:image/png;base64,$logo\" alt=\"phpspec\" width=\"28\" height=\"28\">phpspec<span>$safeSubtitle</span></p>\n"
            . "<p class=\"meta\">$safeMeta</p>\n"
            . "</header>\n";
    }

    /**
     * Renders the pass-ratio meter strip that sits under the header band:
     * a green fill over a red track, so the verdict is visible before any
     * number is read.
     *
     * @param float $pct percentage of the bar to fill green (clamped to 0-100)
     * @return string the meter HTML
     */
    public static function meter(float $pct): string
    {
        $clamped = max(0.0, min(100.0, $pct));

        return sprintf(
            "<div class=\"meter\" role=\"img\" aria-label=\"%.1f%%\"><span style=\"width:%.1f%%\"></span></div>\n",
            $clamped,
            $clamped,
        );
    }

    /**
     * Returns the shared stylesheet: brand tokens and components for both
     * the results report and the coverage report.
     *
     * @return string the CSS rules
     */
    private static function css(): string
    {
        return <<<'CSS'
        :root{
        --ps-red:#C22015;--ps-red-tint:#FCEBE8;
        --ps-green:#58A302;--ps-green-ink:#38680A;--ps-green-tint:#EDF7E0;
        --ps-amber:#B57C00;--ps-amber-tint:#FBF3DC;
        --ps-slate:#667085;--ps-slate-tint:#F0F1F3;
        --ps-ink:#23282E;--ps-paper:#F7F7F5;--ps-card:#FFFFFF;--ps-line:#E3E5E8;
        --brand-bg:#0a0c10;--brand-border:#1e2535;--brand-heading:#f0f4f8;
        --brand-dim:#8b95a7;--brand-green:#3dd68c;
        --code-bg:#080a0e;--code-border:#161c28;--code-red:#ef4444;
        --mono:"IBM Plex Mono","Menlo","Consolas","Liberation Mono",monospace;
        --body-font:"DM Sans",system-ui,-apple-system,"Segoe UI",sans-serif;
        }
        *{box-sizing:border-box}
        body{margin:0;background:var(--ps-paper);color:var(--ps-ink);
        font:15px/1.5 var(--body-font)}
        .band{display:flex;align-items:center;justify-content:space-between;gap:1rem;
        flex-wrap:wrap;background:var(--brand-bg);color:var(--brand-heading);
        border-bottom:1px solid var(--brand-border);padding:.8rem 1.5rem}
        .wordmark{display:inline-flex;align-items:center;gap:.45rem;margin:0;
        font-family:var(--mono);font-size:1.15rem;font-weight:700;letter-spacing:-.02em}
        .wordmark img{width:28px;height:28px;border-radius:4px}
        .wordmark span{font-weight:400;color:var(--brand-dim);font-size:.8rem;
        letter-spacing:.14em;text-transform:uppercase;margin-left:.4rem}
        .meta{margin:0;font-family:var(--mono);font-size:.85rem;color:var(--brand-green);
        text-align:right;white-space:pre-line}
        .meter{height:4px;background:var(--brand-border)}
        .meter span{display:block;height:100%;background:var(--brand-green)}
        main{max-width:60rem;margin:0 auto;padding:1.25rem 1.5rem 3rem}
        .tabs{display:flex;gap:.25rem;margin:0 0 1rem;padding:0;list-style:none;
        border-bottom:2px solid var(--ps-line)}
        .tabs button{font-family:var(--mono);font-size:.8rem;letter-spacing:.1em;
        text-transform:uppercase;border:0;background:none;color:var(--ps-slate);
        padding:.6rem 1rem;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px}
        .tabs button[aria-selected="true"]{color:var(--ps-ink);border-bottom-color:var(--ps-green)}
        .tabs button:focus-visible{outline:2px solid var(--ps-green-ink);outline-offset:2px}
        .panel[hidden]{display:none}
        .toolbar{display:flex;justify-content:flex-end;gap:1rem;margin:0 0 .75rem}
        .toolbar button{font:inherit;font-size:.8rem;border:0;background:none;
        color:var(--ps-slate);cursor:pointer;text-decoration:underline}
        details.group{margin:0 0 .5rem;background:var(--ps-card);border:1px solid var(--ps-line);
        border-radius:6px;overflow:hidden}
        details.group>summary{list-style:none;cursor:pointer;display:flex;align-items:center;
        justify-content:space-between;gap:1rem;padding:.55rem .9rem;background:var(--ps-green);
        color:#fff;font-weight:600}
        details.group>summary::-webkit-details-marker{display:none}
        details.group>summary::after{content:"›";font-family:var(--mono);opacity:.8;
        transition:transform .15s ease}
        details.group[open]>summary::after{transform:rotate(90deg)}
        @media (prefers-reduced-motion:reduce){details.group>summary::after{transition:none}}
        details.group.failed>summary{background:var(--ps-red)}
        details.group>summary .count{font-family:var(--mono);font-weight:400;font-size:.8rem}
        details.group ul{margin:0;padding:.4rem 0;list-style:none}
        .example{padding:.35rem .9rem .35rem 1.4rem;border-left:4px solid transparent}
        .example.passed{border-left-color:var(--ps-green);background:var(--ps-green-tint);
        color:var(--ps-green-ink)}
        .example.failed,.example.failure,.example.error{border-left-color:var(--ps-red);
        background:var(--ps-red-tint);color:var(--ps-red)}
        .example.pending{border-left-color:var(--ps-amber);background:var(--ps-amber-tint);
        color:var(--ps-amber)}
        .example.skipped,.example.undefined{border-left-color:var(--ps-slate);
        background:var(--ps-slate-tint);color:var(--ps-slate)}
        .example+.example{margin-top:2px}
        li>details.example{padding:0}
        details.example>summary{list-style:none;cursor:pointer;padding:.35rem .9rem .35rem 1.4rem}
        details.example>summary::-webkit-details-marker{display:none}
        details.example>summary::after{content:" ›";font-family:var(--mono);opacity:.7}
        details.example[open]>summary::after{content:" ˅"}
        details.example .detail{padding:.2rem 1.4rem .8rem;border-top:1px dashed var(--ps-line)}
        .message{margin:.35rem 0 .1rem;font-family:var(--mono);font-size:.8rem;
        white-space:pre-wrap;color:var(--ps-red)}
        .kv{display:grid;grid-template-columns:max-content 1fr;gap:.1rem .6rem;
        margin:.4rem 0;font-family:var(--mono);font-size:.8rem}
        .kv dt{color:var(--ps-slate)}
        .kv dd{margin:0;color:var(--ps-ink)}
        table.snippet{border-collapse:collapse;margin:.4rem 0;width:100%;
        background:var(--code-bg);border:1px solid var(--code-border);border-radius:4px}
        table.snippet td{padding:0 .6rem;font-family:var(--mono);font-size:.78rem;line-height:1.6;
        color:#fff;font-weight:700}
        table.snippet td.ln{text-align:right;color:var(--brand-dim);font-weight:400;
        width:3rem;user-select:none;border-right:1px solid var(--code-border)}
        table.snippet tr.mark{background:rgba(239,68,68,.15)}
        table.snippet tr.mark td.ln{color:var(--code-red);font-weight:700}
        table.snippet pre{margin:0}
        .where{margin:.2rem 0 0;font-family:var(--mono);font-size:.75rem;color:var(--ps-slate)}
        section.subgroup{padding:.2rem .6rem .4rem}
        section.subgroup h3,section.subgroup h4,section.subgroup h5,section.subgroup h6{
        margin:.5rem 0 .25rem;font-size:.9rem;color:var(--ps-ink)}
        footer.summary{margin-top:1.5rem;border-top:1px solid var(--ps-line);padding-top:.9rem;
        font-family:var(--mono);font-size:.85rem;color:var(--ps-slate)}
        table.coverage{border-collapse:collapse;width:100%;background:var(--ps-card);
        border:1px solid var(--ps-line);border-radius:6px}
        table.coverage th{font-family:var(--mono);font-size:.72rem;letter-spacing:.14em;
        text-transform:uppercase;color:var(--ps-slate);text-align:left;padding:.6rem .9rem;
        border-bottom:2px solid var(--ps-line)}
        table.coverage td{padding:.45rem .9rem;border-bottom:1px solid var(--ps-line);
        font-size:.85rem}
        table.coverage td.num{font-family:var(--mono);text-align:right;white-space:nowrap}
        table.coverage a{color:var(--ps-ink);text-decoration:none}
        table.coverage a:hover{color:var(--ps-red);text-decoration:underline}
        .bar{background:var(--ps-slate-tint);height:8px;border-radius:4px;min-width:10rem}
        .bar span{display:block;height:100%;border-radius:4px}
        .bar .hi{background:var(--ps-green)}
        .bar .mid{background:var(--ps-amber)}
        .bar .lo{background:var(--ps-red)}
        table.source{border-collapse:collapse;width:100%;background:var(--ps-card);
        border:1px solid var(--ps-line)}
        table.source td{padding:0 .6rem;vertical-align:top;font-family:var(--mono);
        font-size:.8rem;line-height:1.5}
        table.source td.ln{text-align:right;color:var(--ps-slate);width:3.5rem;user-select:none}
        table.source tr.hit{background:var(--ps-green-tint)}
        table.source tr.miss{background:var(--ps-red-tint)}
        table.source pre{margin:0}
        a.back{font-family:var(--mono);font-size:.8rem;color:var(--ps-slate)}
        CSS;
    }
}
