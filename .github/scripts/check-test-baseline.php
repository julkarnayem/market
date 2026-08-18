<?php

declare(strict_types=1);

/**
 * Gate the test suite on a pinned set of known failures instead of on its exit code.
 *
 * This repository ships 13 tests that have never passed — model factories that were
 * never wired up, plus one users-table assertion (see the Testing section of the
 * README). Waiting for those to be fixed before adding CI would mean no CI at all,
 * and ignoring the suite's exit code would mean no signal. So compare the *set* of
 * failing tests against a checked-in baseline instead:
 *
 *   - a failure not in the baseline is a regression      -> fail
 *   - a baseline entry that now passes means it is stale -> fail, asking for an update
 *
 * Comparing the set rather than the count matters: a regression landing in the same
 * commit as a fix would keep the count at 13 and slip through unnoticed.
 *
 * Usage: php check-test-baseline.php <junit.xml> <baseline.txt>
 */

/**
 * A bootstrap failure or a stray --filter can produce a plausible-looking run of
 * almost no tests. Refuse to pass anything that did not run roughly the whole suite.
 */
const MINIMUM_TESTS = 200;

/**
 * GitHub renders `::error::` workflow commands as annotations on the run page. That is the
 * only place the reason for a red build is visible without opening the raw log — which
 * needs an authenticated token even on a public repository. Newlines must be encoded or
 * only the first line of a multi-line message survives.
 */
function annotate(string $level, string $message): void
{
    if (getenv('GITHUB_ACTIONS') !== 'true') {
        return;
    }

    // Escape `%` first, so the %0A/%0D introduced below are not double-escaped.
    echo "::{$level}::" . str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $message) . "\n";
}

/** The job summary panel: markdown, and the friendliest place to read a set difference. */
function summarize(string $markdown): void
{
    $path = (string) getenv('GITHUB_STEP_SUMMARY');

    if ($path !== '') {
        file_put_contents($path, $markdown, FILE_APPEND);
    }
}

/** `- ` list, or an explicit "none" so an empty section is not mistaken for missing data. */
function bullets(array $lines): string
{
    return $lines === []
        ? "_none_\n"
        : '- `' . implode("`\n- `", $lines) . "`\n";
}

/**
 * The human half of a JUnit <failure>: PHPUnit writes the test identifier, then the
 * assertion message, then a stack trace, all into one text node. Keep the middle — a
 * red build is only actionable if the reason travels with the test name, and on a
 * public repository the raw log needs an authenticated token to read.
 */
function reason(SimpleXMLElement $case): string
{
    foreach (['failure', 'error'] as $tag) {
        foreach ($case->$tag as $node) {
            $message = [];

            foreach (preg_split('/\R/', trim((string) $node)) ?: [] as $i => $line) {
                $line = trim($line);

                // Line 0 repeats the test name; the first trace line ends the message.
                if ($i === 0 || $line === '') {
                    continue;
                }

                if (preg_match('#^([A-Za-z]:\\\\|/).*\.php:\d+$#', $line)) {
                    break;
                }

                $message[] = $line;

                if (count($message) >= 4) {
                    break;
                }
            }

            $type = (string) $node['type'];
            $text = implode(' ', $message);

            // PHPUnit often repeats the exception class inside the body; don't say it twice.
            return trim($type !== '' && ! str_starts_with($text, $type) ? $type . ': ' . $text : $text);
        }
    }

    return '(no failure detail in the report)';
}

/**
 * 72 tests failing for one reason is one problem, not 72. Grouping by message turns an
 * unreadable wall of test names into the handful of distinct causes behind them.
 *
 * @return array<string, list<string>>
 */
function groupByReason(array $ids, array $reasons): array
{
    $groups = [];

    foreach ($ids as $id) {
        $groups[$reasons[$id] ?? '(unknown)'][] = $id;
    }

    // Biggest cause first — that is usually the one worth reading.
    uasort($groups, static fn (array $a, array $b): int => count($b) <=> count($a));

    return $groups;
}

[$script, $junitPath, $baselinePath] = array_pad(array_slice($argv, 0, 3), 3, null);

if ($junitPath === null || $baselinePath === null) {
    fwrite(STDERR, "usage: php {$script} <junit.xml> <baseline.txt>\n");
    exit(2);
}

foreach ([$junitPath, $baselinePath] as $required) {
    if (! is_file($required)) {
        fwrite(STDERR, "error: {$required} does not exist.\n");
        fwrite(STDERR, "If the JUnit report is missing, the suite crashed before writing it — read the test output above.\n");
        exit(1);
    }
}

$xml = @simplexml_load_file($junitPath);

if ($xml === false) {
    fwrite(STDERR, "error: could not parse {$junitPath}. The suite probably died mid-run.\n");
    exit(1);
}

/** Every testcase, and the subset carrying a <failure> or <error> child. */
$all = $xml->xpath('//testcase') ?: [];
$failed = [];
$reasons = [];

foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $case) {
    // `class` is the FQCN; `name` includes the data-set label for provider cases.
    $id = ((string) $case['class']) . '::' . ((string) $case['name']);
    $failed[] = $id;
    $reasons[$id] = reason($case);
}

sort($failed);
$failed = array_values(array_unique($failed));

$baseline = array_values(array_filter(array_map(
    'trim',
    file($baselinePath, FILE_IGNORE_NEW_LINES) ?: []
), static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#')));

sort($baseline);

$total = count($all);
$regressions = array_values(array_diff($failed, $baseline));
$stale = array_values(array_diff($baseline, $failed));

printf("Ran %d tests: %d failed, %d expected to fail.\n", $total, count($failed), count($baseline));

$problems = [];
$groups = groupByReason($regressions, $reasons);

if ($total < MINIMUM_TESTS) {
    $problems[] = sprintf(
        "Only %d tests ran, expected at least %d. The suite did not run in full.",
        $total,
        MINIMUM_TESTS
    );
}

if ($regressions !== []) {
    $detail = [];

    foreach ($groups as $why => $ids) {
        $detail[] = sprintf(
            "%d test(s) — %s\n      e.g. %s",
            count($ids),
            $why,
            implode("\n           ", array_slice($ids, 0, 3))
        );
    }

    $problems[] = sprintf(
        "%d test(s) failed that are not in the baseline — this is a regression. By cause:\n  - %s",
        count($regressions),
        implode("\n  - ", $detail)
    );
}

if ($stale !== []) {
    $problems[] = sprintf(
        "%d baseline entry/entries no longer fail. Nice — now remove them from %s:\n  - %s",
        count($stale),
        $baselinePath,
        implode("\n  - ", $stale)
    );
}

if ($problems !== []) {
    fwrite(STDERR, "\n" . implode("\n\n", $problems) . "\n");

    // One annotation per distinct cause, not one per problem: GitHub truncates a long
    // message, and the first cause is worth more than 70 truncated test names.
    foreach ($groups as $why => $ids) {
        annotate('error', sprintf("%d unlisted failure(s) — %s\n  e.g. %s", count($ids), $why, $ids[0]));
    }

    if ($total < MINIMUM_TESTS) {
        annotate('error', $problems[0]);
    }

    if ($stale !== []) {
        annotate('error', end($problems));
    }

    $summary = "## Test baseline mismatch\n\n"
        . sprintf("Ran **%d** tests: **%d** failed, **%d** expected to fail.\n\n", $total, count($failed), count($baseline))
        . "### Regressions — failing but not in the baseline\n\n";

    if ($groups === []) {
        $summary .= "_none_\n";
    }

    foreach ($groups as $why => $ids) {
        $summary .= sprintf("**%d test(s)** — %s\n\n", count($ids), $why) . bullets($ids) . "\n";
    }

    $summary .= "### Stale — in the baseline but now passing\n\n" . bullets($stale) . "\n"
        . sprintf("Baseline: `%s`\n", $baselinePath);

    summarize($summary);

    exit(1);
}

echo "Failures match the baseline exactly.\n";

summarize(sprintf(
    "## Test baseline\n\nRan **%d** tests. All **%d** failures match `%s` exactly.\n",
    $total,
    count($failed),
    $baselinePath
));

exit(0);
