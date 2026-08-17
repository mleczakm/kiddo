<?php

declare(strict_types=1);

if (3 !== $argc) {
    fwrite(STDERR, "Usage: coverage-summary.php <clover.xml> <badge.svg>\n");
    exit(1);
}

$coverage = simplexml_load_file($argv[1]);

if (false === $coverage) {
    fwrite(STDERR, "Unable to read coverage report from {$argv[1]}.\n");
    exit(1);
}

$metrics = $coverage->project->metrics;

if (null === $metrics) {
    fwrite(STDERR, "Unable to read project coverage metrics from {$argv[1]}.\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];
$percentage = 0 === $statements ? 100.0 : 100 * $coveredStatements / $statements;
$formattedPercentage = number_format($percentage, 1, '.', '');
$color = match (true) {
    $percentage >= 80 => '#4c1',
    $percentage >= 50 => '#dfb317',
    default => '#e05d44',
};

$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="116" height="20" role="img" aria-label="coverage: {$formattedPercentage}%">
  <title>coverage: {$formattedPercentage}%</title>
  <linearGradient id="s" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient>
  <clipPath id="r"><rect width="116" height="20" rx="3" fill="#fff"/></clipPath>
  <g clip-path="url(#r)"><rect width="63" height="20" fill="#555"/><rect x="63" width="53" height="20" fill="{$color}"/><rect width="116" height="20" fill="url(#s)"/></g>
  <g fill="#fff" text-anchor="middle" font-family="Verdana,DejaVu Sans,sans-serif" font-size="11"><text x="31.5" y="15" fill="#010101" fill-opacity=".3">coverage</text><text x="31.5" y="14">coverage</text><text x="89.5" y="15" fill="#010101" fill-opacity=".3">{$formattedPercentage}%</text><text x="89.5" y="14">{$formattedPercentage}%</text></g>
</svg>
SVG;

if (false === file_put_contents($argv[2], $svg."\n")) {
    fwrite(STDERR, "Unable to write coverage badge to {$argv[2]}.\n");
    exit(1);
}

$summary = sprintf(
    "### Unit and functional test coverage\n\n| Covered lines | Total lines | Coverage |\n| ---: | ---: | ---: |\n| %d | %d | **%s%%** |\n",
    $coveredStatements,
    $statements,
    $formattedPercentage,
);

if (false !== ($summaryPath = getenv('GITHUB_STEP_SUMMARY'))) {
    file_put_contents($summaryPath, $summary, FILE_APPEND);
}

if (false !== ($outputPath = getenv('GITHUB_OUTPUT'))) {
    file_put_contents($outputPath, "percentage={$formattedPercentage}\n", FILE_APPEND);
}

fwrite(STDOUT, "Unit and functional test line coverage: {$formattedPercentage}% ({$coveredStatements}/{$statements})\n");
