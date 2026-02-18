<?php

// Catch missing write rights
if (ini_get('phar.readonly') == 1) {
    echo 'Run »php --define phar.readonly=0 build-phar.php« to build the phar' . PHP_EOL;
    exit(1);
}

$exclude = [
    '.claude',
    '.editorconfig',
    '.env',
    '.env.example',
    '.git',
    '.gitattributes',
    '.gitignore',
    '.gitlab-ci.example.yml',
    '.gitlab-ci.yml',
    '.idea',
    '.notes',
    '.php-version',
    '.php_cs.cache',
    'CLAUDE.md',
    'CONTRIBUTING.md',
    'build',
    'build-phar.php',
    'composer.phar',
    'docs',
    'patches',
    'phpacker.json',
    'repositories.json',
    'tests',
];

$baseDir = realpath(__DIR__);
$filter = function ($file, $key, $iterator) use ($exclude, $baseDir) {
    // Only check exclude list for root-level entries
    if (realpath($file->getPath()) === $baseDir && in_array($file->getFilename(), $exclude)) {
        return false;
    }
    return $iterator->hasChildren() || $file->isFile() || $file->isLink();
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
        $filter
    )
);

// Inject version from Git tag
exec('git describe --tags --dirty --always', $gitVersion);
$version = trim($gitVersion[0] ?? 'dev');
file_put_contents(__DIR__ . '/.version', $version);
echo 'Building version: ' . $version . PHP_EOL;

// Create entry script to avoid shebang duplicates
$file = file(__DIR__ . '/bin/patchbot');
unset($file[0]);
file_put_contents(__DIR__ . '/bin/patchbot.php', $file);

// Create phar
$phar = new \Phar('patchbot.phar');
$phar->setSignatureAlgorithm(\Phar::SHA1);
$phar->startBuffering();
$phar->buildFromIterator($iterator, __DIR__);
//default executable
$phar->setStub(
    '#!/usr/bin/env php ' . PHP_EOL . $phar->createDefaultStub('bin/patchbot.php')
);
$phar->stopBuffering();

// Make phar executable
chmod(__DIR__ . '/patchbot.phar', 0770);

// Remove generated entry script and version file
unlink(__DIR__ . '/bin/patchbot.php');
unlink(__DIR__ . '/.version');

echo 'Done';
