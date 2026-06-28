<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $cloudinaryClassMap = [];
        $processedRoots = [];

        spl_autoload_register(function (string $class) use (&$cloudinaryClassMap, &$processedRoots): void {
            $packagePrefixes = [
                'Cloudinary\\',
                'Cloudinary\\TransformationBuilder\\',
            ];

            foreach ($packagePrefixes as $prefix) {
                if (! str_starts_with($class, $prefix)) {
                    continue;
                }

                $candidateRoots = [
                    base_path('vendor/cloudinary/cloudinary_php/src'),
                    base_path('vendor/cloudinary/transformation-builder-sdk/src'),
                ];

                foreach ($candidateRoots as $root) {
                    if (! isset($processedRoots[$root])) {
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
                        );

                        foreach ($iterator as $file) {
                            if (! $file->isFile() || $file->getExtension() !== 'php') {
                                continue;
                            }

                            $contents = file_get_contents($file->getPathname());
                            $tokens = token_get_all($contents);
                            $namespace = null;
                            $className = null;

                            for ($index = 0; $index < count($tokens); $index++) {
                                if (! is_array($tokens[$index])) {
                                    continue;
                                }

                                if ($tokens[$index][0] === T_NAMESPACE) {
                                    $namespace = '';
                                    $position = $index + 1;

                                    while (isset($tokens[$position])) {
                                        if (! is_array($tokens[$position])) {
                                            if ($tokens[$position] === ';') {
                                                break;
                                            }

                                            $namespace .= $tokens[$position];
                                        } else {
                                            $namespace .= $tokens[$position][1];
                                        }

                                        $position++;
                                    }
                                }

                                if (in_array($tokens[$index][0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                                    $classIndex = $index + 1;

                                    while (isset($tokens[$classIndex]) && is_array($tokens[$classIndex]) && $tokens[$classIndex][0] === T_WHITESPACE) {
                                        $classIndex++;
                                    }

                                    if (isset($tokens[$classIndex]) && is_array($tokens[$classIndex]) && $tokens[$classIndex][0] === T_STRING) {
                                        $className = $tokens[$classIndex][1];
                                        break;
                                    }
                                }
                            }

                            if ($namespace !== null && $className !== null) {
                                $normalizedNamespace = trim($namespace);
                                $fullClassName = $normalizedNamespace === '' ? $className : $normalizedNamespace . '\\' . $className;
                                $cloudinaryClassMap[$fullClassName] = $file->getPathname();
                            }
                        }

                        $processedRoots[$root] = true;
                    }

                    if (isset($cloudinaryClassMap[$class])) {
                        require_once $cloudinaryClassMap[$class];
                        return;
                    }
                }
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
