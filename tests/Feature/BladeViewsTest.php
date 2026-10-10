<?php

use Illuminate\Support\Facades\Blade;

/**
 * Every Blade view of the package compiles to valid PHP. Blade does not see a directive that sits right after the end of another
 * ("@endcan@can"): it prints it as text and the page fails to compile, but only without Livewire's precompiler, so a host application
 * without Livewire is the one that finds out. The adjacency check catches that here.
 */
it('compiles every view to valid PHP and keeps directives apart', function (): void {
    $views = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views')))
        ->filter(fn (SplFileInfo $file) => str_ends_with($file->getFilename(), '.blade.php'));

    expect($views->count())->toBeGreaterThan(100);

    foreach ($views as $file) {
        $source = (string) file_get_contents($file->getPathname());

        $touching = preg_match('/@end[A-Za-z]+@[A-Za-z]|[A-Za-z0-9_]@(?:accountingFeature|can|cannot|if|foreach|forelse|php)\b/', $source, $match) === 1 ? $match[0] : null;

        expect($touching)->toBeNull("{$file->getFilename()}: a directive touches the previous one ({$touching})");

        try {
            token_get_all(Blade::compileString($source), TOKEN_PARSE);
        } catch (ParseError $error) {
            throw new RuntimeException("{$file->getFilename()} does not compile: {$error->getMessage()} (line {$error->getLine()})");
        }
    }
});
