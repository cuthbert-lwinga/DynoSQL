<?php

function loadClasses($directory = __DIR__) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getRealPath();
            
            // Skip the current file to avoid infinite recursion
            if ($filePath === __FILE__) {
                continue;
            }

            require_once $filePath;
            //echo "Loaded: " . $filePath . PHP_EOL;
        }
    }
}


loadClasses();


?>