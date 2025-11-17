<?php

namespace Stellify\Laravel\Parser;

use Illuminate\Support\Str;
use DOMDocument;
use DOMNode;

class BladeParser
{
    private array $elements = [];
    private array $statements = [];
    private array $clauses = [];
    private array $bladeTypes;

    public function __construct()
    {
        // Hardcoded Blade directive UUIDs from ClauseController
        $this->bladeTypes = [
            '@if' => '4b19462a-3fc3-47dd-bd83-9a3635e4d20b',
            '@else' => '41a55d66-fdf8-4345-979b-3714453af961',
            '@elseif' => 'cc34e605-8390-4f18-a66f-4885621a1693',
            '@endif' => 'c3316cc6-f251-4586-aa73-ba781f298770',
            '@for' => '23951891-1b84-4fa9-9f53-fe99faabf08e',
            '@foreach' => '13a0ed23-5db6-411c-8efc-37313ae8b3b3',
            '@endforeach' => '44a80fed-29b6-4511-be67-e9875586021d',
            '@while' => 'ec038718-675a-4bd8-aa9d-47ff22be44c4',
            '@do' => '04ba7c42-51df-446b-aae4-0d7083c46572',
            '@unless' => 'fa2e820d-e63a-49f4-b924-9b8b6ac44e01',
            '@endunless' => '85174939-5818-43d8-bad5-a2e5deb85dfd',
            '@isset' => '23ca7d29-fb8b-43c3-af28-2cc6f45d97d3',
            '@endisset' => 'f4cba9f5-aba1-43b4-8e27-048de2face17',
            '@empty' => '09021a9e-0961-43ec-80de-0910acf85dc3',
            '@endempty' => '561e99aa-1779-4c16-b5f1-97b833f64ecb',
            '@auth' => 'e5d6e97e-c3ab-4e51-a025-e403fc0f5bec',
            '@endauth' => '9872a93a-69f3-4592-858e-7484ff1ff117',
            '@guest' => '3caf4535-0f13-44bc-b2c0-01e6e47f9e5b',
            '@endguest' => '6a80cfc3-884a-472c-bc57-98b9481660d7',
            '@production' => '953d7477-205f-4746-a359-3639a96ce019',
            '@endproduction' => 'be24e898-14ac-4a2e-b30f-e58b012fccb2',
            'placeholder_start' => '2f0c88fe-1c1d-48e9-9704-d6ae707525d7',
            'placeholder_end' => 'c71acac4-8524-452a-bd80-de08f97ee54b',
        ];
    }

    /**
     * Parse all Blade files in the views directory
     */
    public function parseBladeFiles(string $viewsPath): array
    {
        $this->elements = [];
        $this->statements = [];
        $this->clauses = [];
        
        if (!is_dir($viewsPath)) {
            return [
                'elements' => [],
                'statements' => [],
                'clauses' => []
            ];
        }

        $bladeFiles = $this->getBladeFiles($viewsPath);

        foreach ($bladeFiles as $filePath) {
            try {
                $this->parseBladeFile($filePath, $viewsPath);
            } catch (\Exception $e) {
                echo "Error parsing {$filePath}: {$e->getMessage()}\n";
            }
        }

        return [
            'elements' => array_values($this->elements),
            'statements' => array_values($this->statements),
            'clauses' => array_values($this->clauses)
        ];
    }

    /**
     * Parse a single Blade file
     */
    private function parseBladeFile(string $filePath, string $basePath): void
    {
        $content = file_get_contents($filePath);
        $viewName = $this->getViewName($filePath, $basePath);
        
        // Parse Blade directives first, creating statements and clauses
        $contentWithPlaceholders = $this->extractBladeDirectives($content, $viewName);
        
        // Now parse the HTML structure
        $this->parseHtmlStructure($contentWithPlaceholders, $viewName);
    }

    /**
     * Extract Blade directives and create statements/clauses for them
     */
    private function extractBladeDirectives(string $content, string $viewName): string
    {
        // Replace Blade directives with placeholder elements that track their statements
        
        // Handle @if, @elseif, @else, @endif
        $content = preg_replace_callback('/@if\s*\((.*?)\)/', function($matches) use ($viewName) {
            $statementUuid = $this->createBladeStatement('@if', $matches[1], $viewName);
            return "<blade-if data-statement=\"{$statementUuid}\">";
        }, $content);
        
        $content = preg_replace_callback('/@elseif\s*\((.*?)\)/', function($matches) use ($viewName) {
            $statementUuid = $this->createBladeStatement('@elseif', $matches[1], $viewName);
            return "</blade-if><blade-elseif data-statement=\"{$statementUuid}\">";
        }, $content);
        
        $content = preg_replace('/@else/', '</blade-if><blade-else>', $content);
        $content = preg_replace('/@endif/', '</blade-if>', $content);
        
        // Handle @foreach
        $content = preg_replace_callback('/@foreach\s*\((.*?)\)/', function($matches) use ($viewName) {
            $statementUuid = $this->createBladeStatement('@foreach', $matches[1], $viewName);
            return "<blade-foreach data-statement=\"{$statementUuid}\">";
        }, $content);
        $content = preg_replace('/@endforeach/', '</blade-foreach>', $content);
        
        // Handle @for
        $content = preg_replace_callback('/@for\s*\((.*?)\)/', function($matches) use ($viewName) {
            $statementUuid = $this->createBladeStatement('@for', $matches[1], $viewName);
            return "<blade-for data-statement=\"{$statementUuid}\">";
        }, $content);
        $content = preg_replace('/@endfor/', '</blade-for>', $content);
        
        // Handle @while
        $content = preg_replace_callback('/@while\s*\((.*?)\)/', function($matches) use ($viewName) {
            $statementUuid = $this->createBladeStatement('@while', $matches[1], $viewName);
            return "<blade-while data-statement=\"{$statementUuid}\">";
        }, $content);
        $content = preg_replace('/@endwhile/', '</blade-while>', $content);
        
        // Handle {{ }} outputs
        $content = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', function($matches) use ($viewName) {
            $statementUuid = $this->createOutputStatement($matches[1], $viewName);
            return "<blade-output data-statement=\"{$statementUuid}\"></blade-output>";
        }, $content);
        
        // Handle {!! !!} raw outputs
        $content = preg_replace_callback('/\{!!\s*(.*?)\s*!!\}/', function($matches) use ($viewName) {
            $statementUuid = $this->createOutputStatement($matches[1], $viewName, true);
            return "<blade-raw data-statement=\"{$statementUuid}\"></blade-raw>";
        }, $content);
        
        return $content;
    }

    /**
     * Create a statement for a Blade directive
     */
    private function createBladeStatement(string $directive, string $condition, string $viewName): string
    {
        $statementUuid = Str::uuid()->toString();
        $directiveClauseUuid = $this->bladeTypes[$directive] ?? Str::uuid()->toString();
        
        // Create clause for the directive itself (only if not a predefined type)
        if (!isset($this->bladeTypes[$directive])) {
            $this->clauses[] = [
                'uuid' => $directiveClauseUuid,
                'type' => 'directive',
                'name' => $directive,
            ];
        }
        
        // Create clause for the condition/expression
        $conditionUuid = Str::uuid()->toString();
        $this->clauses[] = [
            'uuid' => $conditionUuid,
            'type' => 'expression',
            'name' => trim($condition),
        ];
        
        // Create statement linking directive and condition
        $this->statements[] = [
            'uuid' => $statementUuid,
            'type' => 'blade_directive',
            'name' => $directive,
            'data' => [$directiveClauseUuid, $conditionUuid],
        ];
        
        return $statementUuid;
    }

    /**
     * Create a statement for Blade output {{ }} or {!! !!}
     */
    private function createOutputStatement(string $expression, string $viewName, bool $raw = false): string
    {
        $statementUuid = Str::uuid()->toString();
        
        // Use predefined placeholder UUIDs
        $startUuid = $this->bladeTypes['placeholder_start'];
        $endUuid = $this->bladeTypes['placeholder_end'];
        
        // Create clause for the expression
        $expressionUuid = Str::uuid()->toString();
        $this->clauses[] = [
            'uuid' => $expressionUuid,
            'type' => 'expression',
            'name' => trim($expression),
        ];
        
        // Create statement
        $this->statements[] = [
            'uuid' => $statementUuid,
            'type' => $raw ? 'blade_raw_output' : 'blade_output',
            'name' => $raw ? '{!! !!}' : '{{ }}',
            'data' => [$startUuid, $expressionUuid, $endUuid],
        ];
        
        return $statementUuid;
    }

    /**
     * Parse HTML structure and create elements
     */
    private function parseHtmlStructure(string $html, string $viewName): void
    {
        $wrappedHtml = "<!DOCTYPE html><html><body>{$html}</body></html>";
        
        $dom = new DOMDocument();
        @$dom->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $this->extractElementsFromDom($dom, null, $viewName);
        
        // Remove wrapper elements
        $removeParent = false;
        $firstElement = true;
        foreach ($this->elements as $key => $value) {
            if (empty($value['tag']) || $value['tag'] == 'html' || $value['tag'] == 'body') {
                unset($this->elements[$key]);
                $removeParent = true;
                continue;
            }
            if ($removeParent) {
                unset($this->elements[$key]['parent']);
                // Set view name on root element
                if ($firstElement) {
                    $this->elements[$key]['name'] = $viewName;
                    $firstElement = false;
                }
                $removeParent = false;
            }
        }
    }

    /**
     * Recursively extract elements from DOM
     */
    private function extractElementsFromDom(DOMNode $node, ?string $parentUuid, string $viewName): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $uuid = Str::uuid()->toString();
                $tag = strtolower($child->nodeName);
                
                // Check if this is a blade directive element
                $statementUuid = null;
                if (str_starts_with($tag, 'blade-')) {
                    $statementUuid = $child->getAttribute('data-statement');
                }
                
                // Determine element type
                $elementType = 's-layout'; // default
                if (str_starts_with($tag, 'blade-')) {
                    $elementType = 's-directive';
                } elseif ($tag === 'input' || $tag === 'textarea' || $tag === 'select') {
                    $elementType = 's-input';
                }
                
                $element = [
                    'uuid' => $uuid,
                    'tag' => $tag,
                    'type' => $elementType,
                ];

                // Extract attributes
                $attributes = [];
                if ($child->hasAttributes()) {
                    foreach ($child->attributes as $attr) {
                        if ($attr->nodeName !== 'data-statement') {
                            $attributes[$attr->nodeName] = $attr->nodeValue;
                        }
                        
                        if ($attr->nodeName === 'id') {
                            $element['id'] = $attr->nodeValue;
                        }
                    }
                }
                
                if (!empty($attributes)) {
                    $element['attributes'] = $attributes;
                }

                // Extract text content
                $textContent = '';
                foreach ($child->childNodes as $textNode) {
                    if ($textNode->nodeType === XML_TEXT_NODE) {
                        $text = trim($textNode->nodeValue);
                        if (!empty($text)) {
                            $textContent .= $text;
                        }
                    }
                }
                
                if (!empty($textContent)) {
                    $element['text'] = $textContent;
                }

                // Link to Blade statement if this is a directive element
                if ($statementUuid) {
                    $element['statement'] = $statementUuid;
                }

                // Set parent relationship
                if ($parentUuid) {
                    $element['parent'] = $parentUuid;
                }

                // Initialize children array
                $element['data'] = [];

                $this->elements[$uuid] = $element;

                // Recursively process children
                if ($child->hasChildNodes()) {
                    $this->extractElementsFromDom($child, $uuid, $viewName);
                    
                    // Collect child UUIDs
                    foreach ($this->elements as $stored) {
                        if (!empty($stored['parent']) && $stored['parent'] === $uuid) {
                            $element['data'][] = $stored['uuid'];
                        }
                    }
                    
                    $this->elements[$uuid]['data'] = array_unique($element['data']);
                }
            }
        }
    }

    /**
     * Get view name from file path
     */
    private function getViewName(string $filePath, string $basePath): string
    {
        $relativePath = str_replace($basePath . '/', '', $filePath);
        $relativePath = str_replace('.blade.php', '', $relativePath);
        $viewName = str_replace('/', '.', $relativePath);
        
        return $viewName;
    }

    /**
     * Get all Blade files recursively
     */
    private function getBladeFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
