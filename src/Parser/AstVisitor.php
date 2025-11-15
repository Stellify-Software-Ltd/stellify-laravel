<?php

namespace Stellify\Laravel\Parser;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Illuminate\Support\Str;

class AstVisitor extends NodeVisitorAbstract 
{
    private array $functions = [];
    private array $statements = [];
    private array $clauses = [];
    private $currentFunction = null;

    public function enterNode(Node $node) {
        if ($node instanceof Node\Stmt\Function_) {
            $this->processFunction($node);
        } elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->processClassMethod($node);
        } elseif ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\Assign) {
            $this->processAssignment($node->expr);
        } elseif ($node instanceof Node\Stmt\If_) {
            $this->processIfStatement($node);
        } elseif ($node instanceof Node\Stmt\For_ || $node instanceof Node\Stmt\Foreach_ || $node instanceof Node\Stmt\While_) {
            $this->processLoop($node);
        } elseif ($node instanceof Node\Stmt\Return_) {
            $this->processReturn($node);
        }
    }

    private function processFunction(Node\Stmt\Function_ $node) {
        $uuid = $this->generateUuid();
        $params = array_map(function($param) {
            $paramUuid = $this->generateUuid();
            return [
                'uuid' => $paramUuid,
                'name' => $param->var->name,
                'type' => 'variable',
            ];
        }, $node->params);

        $this->functions[] = [
            'uuid' => $uuid,
            'name' => $node->name->name,
            'type' => 'function',
            'params' => $params,
            'data' => [],
        ];

        // Store reference to current function
        $this->currentFunction = &$this->functions[count($this->functions) - 1];
    }

    private function processClassMethod(Node\Stmt\ClassMethod $node) {
        $uuid = $this->generateUuid();
        $params = array_map(function($param) {
            $paramUuid = $this->generateUuid();
            return [
                'uuid' => $paramUuid,
                'name' => $param->var->name,
                'type' => 'variable',
            ];
        }, $node->params);

        $this->functions[] = [
            'uuid' => $uuid,
            'name' => $node->name->name,
            'type' => 'method',
            'visibility' => $this->getVisibility($node),
            'static' => $node->isStatic(),
            'params' => $params,
            'data' => [],
        ];

        // Store reference to current function
        $this->currentFunction = &$this->functions[count($this->functions) - 1];
    }

    private function getVisibility(Node\Stmt\ClassMethod $node): string {
        if ($node->isPublic()) return 'public';
        if ($node->isProtected()) return 'protected';
        if ($node->isPrivate()) return 'private';
        return 'public';
    }

    private function processAssignment(Node\Expr\Assign $node) {
        $stmtUuid = $this->generateUuid();
        $varUuid = $this->generateUuid();
        $opUuid = $this->generateUuid();
        $valUuid = $this->generateUuid();

        $this->statements[] = [
            'uuid' => $stmtUuid,
            'type' => 'assignment',
            'data' => [$varUuid, $opUuid, $valUuid],
        ];

        if ($node->var instanceof Node\Expr\Variable) {
            $this->clauses[$varUuid] = [
                'uuid' => $varUuid,
                'type' => 'variable',
                'name' => $node->var->name,
            ];
        }

        $this->clauses[$opUuid] = [
            'uuid' => $opUuid,
            'type' => 'operator',
            'name' => '=',
        ];

        $this->clauses[$valUuid] = $this->processValue($node->expr, $valUuid);

        if ($this->currentFunction) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processReturn(Node\Stmt\Return_ $node) {
        $stmtUuid = $this->generateUuid();
        $returnUuid = $this->generateUuid();
        
        $data = [$returnUuid];
        
        $this->clauses[$returnUuid] = [
            'uuid' => $returnUuid,
            'type' => 'keyword',
            'name' => 'return',
        ];

        if ($node->expr !== null) {
            $exprUuid = $this->generateUuid();
            $data[] = $exprUuid;
            $this->clauses[$exprUuid] = $this->processValue($node->expr, $exprUuid);
        }

        $this->statements[] = [
            'uuid' => $stmtUuid,
            'type' => 'return',
            'data' => $data,
        ];

        if ($this->currentFunction) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processIfStatement(Node\Stmt\If_ $node) {
        $stmtUuid = $this->generateUuid();
        $condUuid = $this->generateUuid();

        $this->statements[] = [
            'uuid' => $stmtUuid,
            'type' => 'if',
            'data' => [$condUuid],
        ];

        $this->clauses[$condUuid] = $this->processValue($node->cond, $condUuid);

        if ($this->currentFunction) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processLoop(Node $node) {
        $stmtUuid = $this->generateUuid();
        $type = ($node instanceof Node\Stmt\For_) ? 'for' : (($node instanceof Node\Stmt\Foreach_) ? 'foreach' : 'while');

        $this->statements[] = [
            'uuid' => $stmtUuid,
            'type' => $type,
            'data' => [],
        ];

        if ($this->currentFunction) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processValue(Node $node, string $uuid): array {
        if ($node instanceof Node\Scalar\String_) {
            return ['uuid' => $uuid, 'type' => 'string', 'name' => $node->value];
        }
        if ($node instanceof Node\Scalar\LNumber) {
            return ['uuid' => $uuid, 'type' => 'integer', 'name' => (string) $node->value];
        }
        if ($node instanceof Node\Scalar\DNumber) {
            return ['uuid' => $uuid, 'type' => 'float', 'name' => (string) $node->value];
        }
        if ($node instanceof Node\Expr\Variable) {
            return ['uuid' => $uuid, 'type' => 'variable', 'name' => $node->name];
        }
        if ($node instanceof Node\Expr\MethodCall) {
            $targetUuid = $this->generateUuid();
            $methodUuid = $this->generateUuid();
            $argsUuids = [];
            
            // Process the target object
            $this->clauses[$targetUuid] = $this->processValue($node->var, $targetUuid);
            
            // Process arguments
            foreach ($node->args as $arg) {
                $argUuid = $this->generateUuid();
                $argsUuids[] = $argUuid;
                $this->clauses[$argUuid] = $this->processValue($arg->value, $argUuid);
            }
    
            // Store method call
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
            ];
    
            return [
                'uuid' => $uuid,
                'type' => 'method_call',
                'data' => array_merge([$targetUuid, $methodUuid], $argsUuids)
            ];
        }
        if ($node instanceof Node\Expr\StaticCall) {
            $classUuid = $this->generateUuid();
            $methodUuid = $this->generateUuid();
            $argsUuids = [];
            
            // Process arguments
            foreach ($node->args as $arg) {
                $argUuid = $this->generateUuid();
                $argsUuids[] = $argUuid;
                $this->clauses[$argUuid] = $this->processValue($arg->value, $argUuid);
            }
    
            // Store class reference
            $this->clauses[$classUuid] = [
                'uuid' => $classUuid,
                'type' => 'class',
                'name' => $node->class->toString()
            ];
    
            // Store method call
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
            ];
    
            // Return the static call structure
            return [
                'uuid' => $uuid,
                'type' => 'static_call',
                'data' => array_merge([$classUuid, $methodUuid], $argsUuids)
            ];
        }
        return ['uuid' => $uuid, 'type' => 'unknown', 'name' => ''];
    }

    private function generateUuid(): string {
        return Str::uuid()->toString();
    }

    public function getResults(): array {
        return [
            'methods' => $this->functions,
            'statements' => $this->statements,
            'clauses' => $this->clauses
        ];
    }
}
