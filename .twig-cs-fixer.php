<?php

declare(strict_types=1);

use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;

$finder = new Finder();
$finder->in(__DIR__ . '/templates');
// Help-article bodies embed a `{#--- ... ---#}` YAML frontmatter block parsed by
// App\Application\Help\DocFrontmatter via a literal regex; the fixer's delimiter
// spacing rule rewrites `{#---`/`---#}` and breaks that regex, so these are excluded.
$finder->exclude('admin/help/articles');

$config = new Config();
$config->setFinder($finder);

return $config;
