<?php

namespace Stellify\Laravel\Parser;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\Node;
use Illuminate\Support\Str;

class PhpFileParser
{
    private $parser;
    private $traverser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser();
    }

    /**
     * Parse a PHP file and return structured data for database insertion
     *
     * @param string $filePath
     * @return array
     */
    public function parseFile(string $filePath): array
    {
        $code = file_get_contents($filePath);
        
        try {
            $ast = $this->parser->parse($code);
        } catch (Error $error) {
            throw new \Exception("Parse error in {$filePath}: {$error->getMessage()}");
        }

        // Extract file metadata
        $fileData = $this->extractFileMetadata($ast, $filePath);

        // Parse methods, statements, clauses
        $visitor = new AstVisitor();
        $this->traverser->addVisitor($visitor);
        $this->traverser->traverse($ast);
        $results = $visitor->getResults();

        return [
            'file' => $fileData,
            'methods' => $results['methods'],
            'statements' => $results['statements'],
            'clauses' => $results['clauses']
        ];
    }

    /**
     * Extract file metadata from AST
     */
    private function extractFileMetadata(array $ast, string $filePath): array
    {
        $namespace = null;
        $className = null;
        $classType = null;
        $extends = null;
        $implements = [];

        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name ? $node->name->toString() : null;
                
                // Check for class declaration inside namespace
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Class_) {
                        $className = $stmt->name->toString();
                        $classType = 'class';
                        $extends = $stmt->extends ? $stmt->extends->toString() : null;
                        $implements = array_map(fn($i) => $i->toString(), $stmt->implements);
                    } elseif ($stmt instanceof Node\Stmt\Interface_) {
                        $className = $stmt->name->toString();
                        $classType = 'interface';
                    } elseif ($stmt instanceof Node\Stmt\Trait_) {
                        $className = $stmt->name->toString();
                        $classType = 'trait';
                    }
                }
            } elseif ($node instanceof Node\Stmt\Class_) {
                $className = $node->name->toString();
                $classType = 'class';
                $extends = $node->extends ? $node->extends->toString() : null;
                $implements = array_map(fn($i) => $i->toString(), $node->implements);
            }
        }

        return [
            'uuid' => Str::uuid()->toString(),
            'namespace' => $namespace,
            'name' => $className,
            'type' => $classType,
            'public' => true,
            'data' => json_encode([
                'path' => $filePath,
                'extends' => $extends,
                'implements' => $implements,
            ])
        ];
    }

    /**
     * Parse all PHP files in a directory
     *
     * @param string $directory
     * @param array $exclude
     * @return array
     */
    public function parseDirectory(string $directory, array $exclude = []): array
    {
        $results = [
            'files' => [],
            'methods' => [],
            'statements' => [],
            'clauses' => []
        ];

        $files = $this->getPhpFiles($directory, $exclude);

        foreach ($files as $file) {
            try {
                $parsed = $this->parseFile($file);
                
                $results['files'][] = $parsed['file'];
                $results['methods'] = array_merge($results['methods'], $parsed['methods']);
                $results['statements'] = array_merge($results['statements'], $parsed['statements']);
                $results['clauses'] = array_merge($results['clauses'], array_values($parsed['clauses']));
            } catch (\Exception $e) {
                // Log error but continue parsing other files
                echo "Error parsing {$file}: {$e->getMessage()}\n";
            }
        }

        return $results;
    }

    /**
     * Get all PHP files in directory recursively
     */
    private function getPhpFiles(string $directory, array $exclude = []): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getPathname();
                
                // Check if file should be excluded
                $shouldExclude = false;
                foreach ($exclude as $excludePath) {
                    if (str_contains($filePath, $excludePath)) {
                        $shouldExclude = true;
                        break;
                    }
                }

                if (!$shouldExclude) {
                    $files[] = $filePath;
                }
            }
        }

        return $files;
    }
}
