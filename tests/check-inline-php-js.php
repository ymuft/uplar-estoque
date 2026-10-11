<?php
/**
 * Extract raw inline JavaScript from PHP templates for syntax checking.
 * PHP output expressions become literal null for parser compatibility.
 * Does not execute the application or require a database.
 */
declare(strict_types=1);
$files = ['produtos.php', 'vitrine.php', 'site.php'];
foreach ($files as $file) {
    $input = file_get_contents(__DIR__ . '/../' . $file);
    if ($input === false) throw new RuntimeException('Cannot read ' . $file);
    preg_match_all('~<script\\b[^>]*>(.*?)</script>~is', $input, $match);
    $chunks = array_filter($match[1], static fn(string $v): bool => trim($v) !== '');
    if (!$chunks) throw new RuntimeException('No inline JS found in ' . $file);
    $code = implode("\n;\n", $chunks);
    $code = preg_replace('~<\\?(?:php|=).*?\\?>~s', 'null', $code);
    if (!is_string($code)) throw new RuntimeException('Could not parse template tags in ' . $file);
    $name = pathinfo($file, PATHINFO_FILENAME);
    $target = '/tmp/uplar-' . $name . '-inline.js';
    if (file_put_contents($target, $code) === false) throw new RuntimeException('Cannot write ' . $target);
    echo $target . "\n";
}
