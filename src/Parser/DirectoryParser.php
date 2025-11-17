<?php

namespace Stellify\Laravel\Parser;

use Illuminate\Support\Str;

class DirectoryParser
{
    /**
     * Parse directory structure and return data for directories table
     *
     * @param string $basePath
     * @param array $includePaths
     * @return array
     */
    public function parseDirectories(string $basePath, array $includePaths): array
    {
        $directories = [];
        $processedPaths = [];

        foreach ($includePaths as $path) {
            $fullPath = $basePath . '/' . $path;
            
            if (!is_dir($fullPath)) {
                continue;
            }

            $this->scanDirectory($fullPath, $basePath, $directories, $processedPaths);
        }

        return $directories;
    }

    /**
     * Recursively scan directory and add to results
     */
    private function scanDirectory(string $directory, string $basePath, array &$directories, array &$processedPaths): void
    {
        // Get relative path from base
        $relativePath = str_replace($basePath . '/', '', $directory);

        // Skip if already processed
        if (in_array($relativePath, $processedPaths)) {
            return;
        }

        $processedPaths[] = $relativePath;

        // Add this directory
        $directories[] = [
            'uuid' => Str::uuid()->toString(),
            'user_id' => null,
            'project_id' => null,
            'name' => basename($directory),
            'type' => $this->getDirectoryType($relativePath),
            'data' => json_encode([
                'uuid' => $uuid,
                'name' => basename($directory),
                'type' => $this->getDirectoryType($relativePath),
                'data' => []
            ])
        ];

        // Scan subdirectories
        $items = scandir($directory);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . '/' . $item;
            if (is_dir($itemPath)) {
                $this->scanDirectory($itemPath, $basePath, $directories, $processedPaths);
            }
        }
    }

    /**
     * Determine directory type based on Laravel conventions
     */
    private function getDirectoryType(string $path): string
    {
        if (str_starts_with($path, 'app/Http/Controllers')) {
            return 'controllers';
        } elseif (str_starts_with($path, 'app/Models')) {
            return 'models';
        } elseif (str_starts_with($path, 'app/Http/Middleware')) {
            return 'middleware';
        } elseif (str_starts_with($path, 'database/migrations')) {
            return 'migrations';
        } elseif (str_starts_with($path, 'routes')) {
            return 'routes';
        } elseif (str_starts_with($path, 'config')) {
            return 'config';
        } elseif (str_starts_with($path, 'resources/views')) {
            return 'views';
        } elseif (str_starts_with($path, 'app')) {
            return 'app';
        }

        return 'directory';
    }
}
