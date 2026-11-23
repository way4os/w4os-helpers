<?php
// Compiles every template of the helpers with Twig 1.44 (the one of Grav 1.7), the functions and filters of View known by their
// names only; prints each one Twig 1.44 refuses, with why.
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/vendor/autoload.php';
$root = dirname(__DIR__, 2) . '/templates';
$twig = new Twig\Environment(new Twig\Loader\FilesystemLoader($root));
$twig->registerUndefinedFunctionCallback(fn($name) => new Twig\TwigFunction($name, fn() => null));
$twig->registerUndefinedFilterCallback(fn($name) => new Twig\TwigFilter($name, fn($value) => $value));
$refused = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (substr((string) $file, -5) !== '.twig') {
        continue;
    }
    $name = substr((string) $file, strlen($root) + 1);
    try {
        $twig->compileSource($twig->getLoader()->getSourceContext($name));
    } catch (Twig\Error\Error $e) {
        echo "$name: " . $e->getMessage() . "\n";
        $refused++;
    }
}
exit($refused === 0 ? 0 : 1);
