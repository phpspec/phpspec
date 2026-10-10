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

namespace PhpSpec\Report\Formatter;

use PhpSpec\Report\AbstractFormatter;
use PhpSpec\Report\CountLines;
use PhpSpec\Report\HtmlTheme;
use PhpSpec\Report\ReportedObject;
use PhpSpec\Result\Counts;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\Result;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;

/**
 * @internal
 * Renders spec results as a self-contained HTML document in the PhpSpec brand
 * (see HtmlTheme): collapsible group bars per spec/feature, tabs separating
 * spec and story results when a run mixes them, and a pass-ratio meter.
 * Suitable for saving with `phpspec run --format=html > report.html`.
 */
final class Html extends AbstractFormatter
{
    /**
     * No-op; the HTML document is non-streamable, all output happens in end().
     */
    public function begin(): void
    {
        // HTML is non-streamable; all output happens in end()
    }

    /**
     * No-op; the HTML document is non-streamable, all output happens in end().
     */
    public function printResult(Results $result): void
    {
        // HTML is non-streamable; all output happens in end()
    }

    /**
     * Builds and outputs the complete HTML document from all collected results.
     */
    public function end(SuiteResult $results): void
    {
        $specs = [];
        $stories = [];

        foreach ($results->getResults() as $result) {
            if ($result instanceof FeatureResult) {
                $stories[] = $result;
            } else {
                $specs[] = $result;
            }
        }

        $counts = (new Counts($results))->toArray();
        $total = $counts['examples'] + $counts['steps'];
        $passed = $counts['passes'] + $counts['stepPasses'];

        $body = HtmlTheme::header('Results', $this->summaryLine($counts))
            . HtmlTheme::meter($total > 0 ? ($passed / $total) * 100 : 100.0)
            . "<main>\n"
            . $this->renderPanels($specs, $stories)
            . $this->renderFooter($counts, $results->getDuration())
            . "</main>\n"
            . $this->script();

        $this->output->writeln(HtmlTheme::page('PhpSpec Results', $body));
    }

    /**
     * Renders the spec and story panels, with a tab bar when both are present.
     *
     * @param array<int, Results> $specs top-level spec results
     * @param array<int, Results> $stories top-level feature results
     * @return string the panels HTML
     */
    private function renderPanels(array $specs, array $stories): string
    {
        $html = '';
        $mixed = $specs !== [] && $stories !== [];

        if ($mixed) {
            $html .= "<div class=\"tabs\" role=\"tablist\">\n"
                . "<button role=\"tab\" aria-selected=\"true\" data-panel=\"specs\">Specs</button>\n"
                . "<button role=\"tab\" aria-selected=\"false\" data-panel=\"stories\">Stories</button>\n"
                . "</div>\n";
        }

        $html .= "<div class=\"toolbar\">\n"
            . "<button type=\"button\" data-act=\"expand\">Expand all</button>\n"
            . "<button type=\"button\" data-act=\"collapse\">Collapse all</button>\n"
            . "</div>\n";

        if ($specs !== []) {
            $html .= "<section class=\"panel\" data-panel=\"specs\">\n"
                . $this->renderGroups($specs, 'example')
                . "</section>\n";
        }

        if ($stories !== []) {
            $hidden = $mixed ? ' hidden' : '';
            $html .= "<section class=\"panel\" data-panel=\"stories\"$hidden>\n"
                . $this->renderGroups($stories, 'step')
                . "</section>\n";
        }

        return $html;
    }

    /**
     * Renders top-level results as collapsible group bars: groups containing
     * failures open, all-passing groups collapsed.
     *
     * @param array<int, Results> $groups the top-level results
     * @param string $leafNoun the leaf label for the count, "example" or "step"
     * @return string the groups HTML
     */
    private function renderGroups(array $groups, string $leafNoun): string
    {
        $html = '';

        foreach ($groups as $group) {
            $failed = $this->hasFailure($group);
            $leaves = $this->countLeaves($group);
            $title = method_exists($group, 'getTitle') ? (string) $group->getTitle() : '';
            $count = $leaves . ' ' . $leafNoun . ($leaves !== 1 ? 's' : '');

            if ($failed) {
                $count .= ' · ' . $this->countFailures($group) . ' failed';
            }

            $raised = (new Counts($group))->toArray();
            foreach ($this->notesCounted($raised['warnings'] + $raised['stepWarnings'], $raised['deprecations'] + $raised['stepDeprecations'], $raised['notices'] + $raised['stepNotices']) as $notes) {
                $count .= ' · ' . $notes;
            }

            $html .= sprintf(
                "<details class=\"group %s\"%s>\n<summary>%s <span class=\"count\">%s</span></summary>\n<ul>\n%s</ul>\n</details>\n",
                $failed ? 'failed' : 'passed',
                $failed ? ' open' : '',
                $this->escape($title),
                $this->escape($count),
                $this->renderChildren($group, 3),
            );
        }

        return $html;
    }

    /**
     * Renders a result subtree: leaves become list items, nested groups
     * become subsections with a heading and a nested list.
     *
     * @param Results $result the result node to render
     * @param int $level heading level for nested group titles (capped at h6)
     * @return string the rendered HTML fragment
     */
    private function renderChildren(Results $result, int $level): string
    {
        $html = '';

        foreach ($result->getResults() as $child) {
            if ($child instanceof ExampleResult) {
                $html .= $this->renderExample($child);
                continue;
            }

            if ($child instanceof StepResult) {
                $html .= $this->renderStep($child);
                continue;
            }

            $title = method_exists($child, 'getTitle') ? (string) $child->getTitle() : '';
            $html .= sprintf(
                "<section class=\"subgroup\">\n<h%d>%s</h%d>\n<ul>\n%s</ul>\n</section>\n",
                $level,
                $this->escape($title),
                $level,
                $this->renderChildren($child, min($level + 1, 6)),
            );
        }

        return $html;
    }

    /**
     * Renders a single example: a failure or an error collapses its full
     * detail (message, expected/got, code snippet, location) under the title,
     * as does any example that raised a PHP note; every other is a plain row.
     *
     * @param ExampleResult $example the example result to render
     * @return string the rendered HTML fragment
     */
    private function renderExample(ExampleResult $example): string
    {
        $state = match (true) {
            $example->isError() => 'error',
            $example->isFailure() => 'failed',
            $example->isPending() => 'pending',
            $example->isSkipped() => 'skipped',
            $example->isRisky() => 'risky',
            default => 'passed',
        };

        $detail = '';

        if ($state === 'failed') {
            foreach ($example->getResults() as $match) {
                if ($match->getResult() === Result::Failed) {
                    $detail .= $this->renderFailureDetail($match);
                }
            }
        }

        if ($state === 'error') {
            $detail = sprintf(
                "<p class=\"message\">%s</p>\n",
                $this->escape($example->getError()?->getMessage() ?? ''),
            );
        }

        $reason = $state === 'risky' ? 'no expectation' : $example->getReason();

        return $this->leaf($state, $example->getTitle(), $reason, $example, $detail);
    }

    /**
     * Renders a single step: a failed step collapses its error message under
     * the title, as does any step that raised a PHP note; every other is a
     * plain row.
     *
     * @param StepResult $step the step result to render
     * @return string the rendered HTML fragment
     */
    private function renderStep(StepResult $step): string
    {
        $detail = '';

        if (($step->isFailure() || $step->isError()) && $step->getError() !== null) {
            $detail = sprintf(
                "<p class=\"message\">%s</p>\n",
                $this->escape($step->getError()->getMessage()),
            );
        }

        return $this->leaf($step->getState(), $step->getTitle(), $step->getReason(), $step, $detail);
    }

    /**
     * One row: the title with the reason it gave and how many notes it
     * raised, collapsing over the detail and the notes when there are any.
     *
     * @param string $state the outcome class, e.g. "passed" or "failed"
     * @param string $detail the pre-rendered detail HTML, empty when there is none
     * @return string the rendered HTML fragment
     */
    private function leaf(string $state, string $title, ?string $reason, ExampleResult|StepResult $result, string $detail): string
    {
        $label = $this->escape($title);
        $counted = $this->notesCounted(count($result->getWarnings()), count($result->getDeprecations()), count($result->getNotices()));

        foreach ([...($reason === null ? [] : [$reason]), ...$counted] as $aside) {
            $label .= ' <span class="reason">' . $this->escape($aside) . '</span>';
        }

        $detail .= $this->renderNotes($result);

        if ($detail === '') {
            return sprintf("<li class=\"example %s\">%s</li>\n", $this->escape($state), $label);
        }

        return sprintf(
            "<li><details class=\"example %s\">\n<summary>%s</summary>\n<div class=\"detail\">\n%s</div>\n</details></li>\n",
            $this->escape($state),
            $label,
            $detail,
        );
    }

    /**
     * Each PHP note an example or a step raised: its kind, what PHP said and
     * the line that raised it.
     */
    private function renderNotes(ExampleResult|StepResult $result): string
    {
        $items = '';
        $kinds = [
            ['warning', '⚠', $result->getWarnings()],
            ['deprecation', '⛔', $result->getDeprecations()],
            ['notice', 'ℹ', $result->getNotices()],
        ];

        foreach ($kinds as [$kind, $glyph, $notes]) {
            foreach ($notes as $note) {
                $items .= sprintf(
                    "<li class=\"note %s\">%s %s <span class=\"where\">at %s:%d</span></li>\n",
                    $kind,
                    $glyph,
                    $this->escape($note['message']),
                    $this->escape($note['file']),
                    $note['line'],
                );
            }
        }

        return $items === '' ? '' : "<ul class=\"notes\">\n" . $items . "</ul>\n";
    }

    /**
     * Each kind of note raised, counted, as "2 warnings"; none for a kind
     * never raised.
     *
     * @return list<string>
     */
    private function notesCounted(int $warnings, int $deprecations, int $notices): array
    {
        $counted = [];

        foreach (['warning' => $warnings, 'deprecation' => $deprecations, 'notice' => $notices] as $noun => $count) {
            if ($count > 0) {
                $counted[] = $count . ' ' . $noun . ($count !== 1 ? 's' : '');
            }
        }

        return $counted;
    }

    /**
     * Renders one failed expectation: message, expected/got values, the
     * surrounding code snippet with the failing line marked, and location.
     *
     * @param MatchResult $match the failed match
     * @return string the rendered HTML fragment
     */
    private function renderFailureDetail(MatchResult $match): string
    {
        $html = sprintf("<p class=\"message\">%s</p>\n", $this->escape((string) $match->getMessage()));

        // The match's accessors are crossed: getExpected() is the value the code
        // produced and getActual() the value the matcher wanted.
        $html .= sprintf(
            "<dl class=\"kv\"><dt>expected:</dt><dd>%s</dd><dt>got:</dt><dd>%s</dd></dl>\n",
            $this->escape($this->formatValue($match->getActual())),
            $this->escape($this->formatValue($match->getExpected())),
        );

        $code = $match->getCode();
        $failingLine = $match->getLine();

        if ($code !== []) {
            $rows = '';

            foreach ($code as $lineNo => $source) {
                $rows .= sprintf(
                    "<tr%s><td class=\"ln\">%d</td><td><pre>%s</pre></td></tr>\n",
                    $lineNo === $failingLine ? ' class="mark"' : '',
                    $lineNo,
                    $this->escape(rtrim($source)),
                );
            }

            $html .= "<table class=\"snippet\">$rows</table>\n";
        }

        if ($match->getFile() !== null) {
            $html .= sprintf(
                "<p class=\"where\">at %s:%d</p>\n",
                $this->escape($match->getFile()),
                $failingLine ?? 0,
            );
        }

        return $html;
    }

    /**
     * Formats a matcher value for the expected/got display.
     *
     * @param mixed $value the value to format
     * @return string a short human-readable representation
     */
    private function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => '"' . $value . '"',
            is_scalar($value) => (string) $value,
            is_array($value) => 'Array(' . count($value) . ')',
            $value instanceof ReportedObject => $value->shown,
            is_object($value) => $value::class . '#' . spl_object_id($value),
            default => get_debug_type($value),
        };
    }

    /**
     * Builds the header meta line from the suite counts.
     *
     * @param array<string, int> $counts the tallied suite counts
     * @return string e.g. "4 examples · 2 failed"
     */
    private function summaryLine(array $counts): string
    {
        $parts = [];

        if ($counts['examples'] > 0 || $counts['steps'] === 0) {
            $parts[] = $counts['examples'] . ' example' . ($counts['examples'] !== 1 ? 's' : '');
        }

        if ($counts['steps'] > 0) {
            $parts[] = $counts['steps'] . ' step' . ($counts['steps'] !== 1 ? 's' : '');
        }

        $failed = $counts['failures'] + $counts['errors'] + $counts['stepFailures'] + $counts['stepErrors'];

        if ($failed > 0) {
            $parts[] = $failed . ' failed';
        }

        $parts = [...$parts, ...$this->notesCounted($counts['warnings'] + $counts['stepWarnings'], $counts['deprecations'] + $counts['stepDeprecations'], $counts['notices'] + $counts['stepNotices'])];

        return implode(' · ', $parts);
    }

    /**
     * Renders the summary footer: the run counted in the console's words, and
     * how long it took.
     *
     * @param array<string, int> $counts the tallied suite counts
     * @param float $duration the suite duration in seconds
     * @return string the rendered HTML fragment
     */
    private function renderFooter(array $counts, float $duration): string
    {
        $lines = '';

        foreach ((new CountLines($counts))->lines() as $line) {
            $parts = array_column($line['parts'], 'text');
            $lines .= sprintf("<p>%s</p>\n", $this->escape($line['heading'] . ($parts === [] ? '' : ' (' . implode(', ', $parts) . ')')));
        }

        return sprintf("<footer class=\"summary\">\n%s<p>Finished in %.4f seconds</p>\n</footer>\n", $lines, $duration);
    }

    /**
     * Checks whether the result subtree contains any failed or errored leaf.
     *
     * @param Results $result the result node to inspect
     * @return bool true when a failure or error exists in the subtree
     */
    private function hasFailure(Results $result): bool
    {
        foreach ($result->getResults() as $child) {
            if ($child instanceof ExampleResult) {
                if ($child->isFailure() || $child->isError()) {
                    return true;
                }
            } elseif ($child instanceof StepResult) {
                if ($child->isFailure() || $child->isError()) {
                    return true;
                }
            } elseif ($this->hasFailure($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Counts example and step leaves in a result subtree.
     *
     * @param Results $result the result node to count
     * @return int the number of leaves
     */
    private function countLeaves(Results $result): int
    {
        $count = 0;

        foreach ($result->getResults() as $child) {
            if ($child instanceof ExampleResult || $child instanceof StepResult) {
                $count++;
            } elseif ($child instanceof Results) {
                $count += $this->countLeaves($child);
            }
        }

        return $count;
    }

    /**
     * Counts failed and errored leaves in a result subtree.
     *
     * @param Results $result the result node to count
     * @return int the number of failing leaves
     */
    private function countFailures(Results $result): int
    {
        $count = 0;

        foreach ($result->getResults() as $child) {
            if ($child instanceof ExampleResult) {
                $count += $child->isFailure() || $child->isError() ? 1 : 0;
            } elseif ($child instanceof StepResult) {
                $count += $child->isFailure() || $child->isError() ? 1 : 0;
            } else {
                $count += $this->countFailures($child);
            }
        }

        return $count;
    }

    /**
     * Returns the inline script powering the tabs and the expand/collapse
     * toolbar; both no-op when their elements are absent.
     *
     * @return string the script tag
     */
    private function script(): string
    {
        return <<<'HTML'
        <script>
        document.querySelectorAll('.tabs [role="tab"]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.tabs [role="tab"]').forEach(function (other) {
                    other.setAttribute('aria-selected', String(other === tab));
                });
                document.querySelectorAll('.panel').forEach(function (panel) {
                    panel.hidden = panel.dataset.panel !== tab.dataset.panel;
                });
            });
        });
        document.querySelectorAll('.toolbar [data-act]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('details.group').forEach(function (group) {
                    group.open = button.dataset.act === 'expand';
                });
            });
        });
        </script>
        HTML;
    }

    /**
     * Escapes text for safe embedding in the HTML document.
     *
     * @param string $text the raw text
     * @return string the escaped text
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES);
    }
}
