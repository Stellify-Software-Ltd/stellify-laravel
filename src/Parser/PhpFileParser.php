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
    private array $coreFiles;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser();
        
        // Predefined UUIDs for core Laravel framework files
        // These match the UUIDs in FileController's DEFAULT_INCLUDES/EXTENDS/IMPLEMENTS
        $this->coreFiles = [
            // Migrations
            'Illuminate\Database\Migrations\Migration' => '797cce30-8b01-44e7-a0b2-c26a641a17e0',
            'Illuminate\Support\Facades\Schema' => '08e4dfa8-b927-450f-8104-616b586d8696',
            'Illuminate\Database\Schema\Blueprint' => '93c290ea-c225-4cd7-9884-7de50dbaf2e3',
            
            // Models
            'Illuminate\Database\Eloquent\Model' => 'd0fe7b2c-63e8-466d-8e1b-6ca0062a45c7',
            
            // Factories
            'Illuminate\Database\Eloquent\Factories\Factory' => '75c7673e-3ece-441a-8405-ca7802b98090',
            
            // Controllers
            'App\Http\Controllers\Controller' => '6aaf556a-f2c1-43d0-a833-7db187868ada',
            
            // Tests
            'Tests\TestCase' => 'da0dd8c8-8255-473c-9ad1-662116e0ed5e',
            
            // Middleware
            'Illuminate\Auth\Middleware\Authenticate' => '2520e62a-dccf-42fb-a78c-56471b4dabac',
            'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken' => 'b8f5653b-fc8b-4163-b731-343d2b5d6950',
            'Illuminate\Routing\Middleware\SubstituteBindings' => '8636dac7-7459-41ef-b812-f148e1b8c976',
            
            // Rules
            'Illuminate\Contracts\Validation\Rule' => '773c0655-240b-4a03-b727-5e4d3ea07944',
            
            // Events
            'Illuminate\Foundation\Events\Dispatchable' => 'fa2584fd-0c8f-47fa-b5de-f9196b56fff4',
            'Illuminate\Broadcasting\InteractsWithSockets' => 'b681be9a-c26e-403d-a7bc-84548203dfad',
            'Illuminate\Queue\SerializesModels' => '62a60b43-6460-4335-9722-ed57047e214d',
            'Illuminate\Broadcasting\PrivateChannel' => 'e4e01384-6ad3-4425-963f-c4430c6d5ae3',
            'Illuminate\Broadcasting\PresenceChannel' => '0f7ad11f-7a62-4435-b6fa-7b3deb9b9645',
            'Illuminate\Contracts\Broadcasting\ShouldBroadcast' => 'b3796872-3b8c-491f-a3ca-f7e245d1291d',
            'Illuminate\Contracts\Queue\ShouldQueue' => '9f25210d-f321-4e4c-878a-4351906c3531',
            
            // Jobs
            'Illuminate\Bus\Queueable' => '3fcf7072-9f9a-4cd4-b2f7-eebe4e1b3fb6',
            'Illuminate\Contracts\Queue\ShouldQueue' => 'af19260c-4f37-404f-985f-ca73ee70977e',
            'Illuminate\Queue\InteractsWithQueue' => '9f25210d-f321-4e4c-878a-4351906c3531',
            
            // Requests
            'Illuminate\Foundation\Http\FormRequest' => '6edb0af2-39ec-4b87-9bf4-29fd44fff1a1',
            
            // Notifications
            'Illuminate\Notifications\Notification' => '3620e6d9-68cd-47d3-9a54-aa6d40d730a6',
            'Illuminate\Bus\Queueable' => '3fcf7072-9f9a-4cd4-b2f7-eebe4e1b3fb6',
            'Illuminate\Contracts\Queue\ShouldQueue' => 'af19260c-4f37-404f-985f-ca73ee70977e',
            'Illuminate\Notifications\Messages\MailMessage' => 'b47ae7b6-7ac5-46a5-9b0a-fe8b319242e2',
            
            // Mail
            'Illuminate\Mail\Mailable' => '9e6ffaa7-68e1-4afd-ba9c-7daabefa3467',
            'Illuminate\Mail\Mailables\Content' => '004fb22b-f352-4125-8621-ac550156e892',
            'Illuminate\Mail\Mailables\Envelope' => '8c0701f1-6891-4976-90ad-ed5ff23e475e',
        ];
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
        $uses = []; // Track use statements

        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name ? $node->name->toString() : null;
                
                // Check for class declaration inside namespace
                foreach ($node->stmts as $stmt) {
                    // Track use statements
                    if ($stmt instanceof Node\Stmt\Use_) {
                        foreach ($stmt->uses as $use) {
                            $fullName = $use->name->toString();
                            $alias = $use->alias ? $use->alias->name : basename(str_replace('\\', '/', $fullName));
                            $uses[$alias] = $fullName;
                        }
                    } elseif ($stmt instanceof Node\Stmt\Class_) {
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

        // Build full class name
        $fullClassName = $namespace ? $namespace . '\\' . $className : $className;
        
        // Check if this is a core Laravel file with a predefined UUID
        $uuid = $this->coreFiles[$fullClassName] ?? Str::uuid()->toString();

        // Resolve extends to full class name and check for predefined UUID
        $extendsUuid = null;
        if ($extends) {
            $extendsFullName = $this->resolveClassName($extends, $namespace, $uses);
            $extendsUuid = $this->coreFiles[$extendsFullName] ?? null;
        }

        // Resolve implements to full class names and check for predefined UUIDs
        $implementsUuids = [];
        foreach ($implements as $interface) {
            $interfaceFullName = $this->resolveClassName($interface, $namespace, $uses);
            if ($predefinedUuid = $this->coreFiles[$interfaceFullName] ?? null) {
                $implementsUuids[] = $predefinedUuid;
            }
        }

        // Build flat structure
        $fileData = [
            'uuid' => $uuid,
            'namespace' => $namespace,
            'name' => $className,
            'type' => $classType,
            'public' => true,
            'path' => $filePath,
            'extends' => $extends,
            'implements' => $implements,
        ];

        // Add UUIDs if they exist
        if ($extendsUuid) {
            $fileData['extends'] = $extendsUuid;
        }
        
        if (!empty($implementsUuids)) {
            $fileData['implements_uuids'] = $implementsUuids;
        }

        return $fileData;
    }

    /**
     * Resolve a class name to its full namespace
     */
    private function resolveClassName(string $className, ?string $currentNamespace, array $uses): string
    {
        // Already fully qualified
        if ($className[0] === '\\') {
            return ltrim($className, '\\');
        }

        // Check if it's an alias from use statement
        $parts = explode('\\', $className);
        $firstPart = $parts[0];
        
        if (isset($uses[$firstPart])) {
            // Replace alias with full namespace
            $parts[0] = $uses[$firstPart];
            return implode('\\', $parts);
        }

        // If no namespace parts, assume it's in current namespace
        if (!str_contains($className, '\\') && $currentNamespace) {
            return $currentNamespace . '\\' . $className;
        }

        return $className;
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
