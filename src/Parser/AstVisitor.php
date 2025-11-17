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
        
        // Process parameters as clauses
        $parameterUuids = [];
        foreach ($node->params as $param) {
            $paramUuid = $this->generateUuid();
            
            // Determine parameter type
            $paramType = 'mixed'; // default
            if ($param->type) {
                if ($param->type instanceof Node\Name) {
                    $paramType = $param->type->toString();
                } elseif ($param->type instanceof Node\Identifier) {
                    $paramType = $param->type->name;
                }
            }
            
            $this->clauses[$paramUuid] = [
                'uuid' => $paramUuid,
                'name' => $param->var->name,
                'type' => $paramType,  // Use actual type (string, int, User, etc.)
            ];
            $parameterUuids[] = $paramUuid;
        }

        $functionData = [
            'uuid' => $uuid,
            'name' => $node->name->name,
            'type' => 'function',
            'parameters' => $parameterUuids,
            'data' => [],
        ];

        $this->functions[] = $functionData;
        $this->currentFunction = &$this->functions[count($this->functions) - 1];
    }

    private function processClassMethod(Node\Stmt\ClassMethod $node) {
        $uuid = $this->generateUuid();
        
        // Determine scope
        $scope = 'public';
        if ($node->isPrivate()) {
            $scope = 'private';
        } elseif ($node->isProtected()) {
            $scope = 'protected';
        }

        // Process parameters as clauses
        $parameterUuids = [];
        foreach ($node->params as $param) {
            $paramUuid = $this->generateUuid();
            
            // Determine parameter type
            $paramType = 'mixed'; // default
            if ($param->type) {
                if ($param->type instanceof Node\Name) {
                    $paramType = $param->type->toString();
                } elseif ($param->type instanceof Node\Identifier) {
                    $paramType = $param->type->name;
                } elseif ($param->type instanceof Node\NullableType) {
                    // Handle nullable types like ?string
                    $innerType = $param->type->type;
                    if ($innerType instanceof Node\Name) {
                        $paramType = '?' . $innerType->toString();
                    } elseif ($innerType instanceof Node\Identifier) {
                        $paramType = '?' . $innerType->name;
                    }
                }
            }
            
            $this->clauses[$paramUuid] = [
                'uuid' => $paramUuid,
                'name' => $param->var->name,
                'type' => $paramType,  // Use actual type (string, int, User, etc.)
            ];
            $parameterUuids[] = $paramUuid;
        }

        $methodData = [
            'uuid' => $uuid,
            'name' => $node->name->name,
            'type' => 'method',
            'scope' => $scope,  // Changed from 'visibility'
            'parameters' => $parameterUuids,  // Changed from 'params'
            'data' => [],
        ];

        // Only add static if true
        if ($node->isStatic()) {
            $methodData['static'] = true;
        }

        $this->functions[] = $methodData;
        $this->currentFunction = &$this->functions[count($this->functions) - 1];
    }

    private function processAssignment(Node\Expr\Assign $node) {
        $stmtUuid = $this->generateUuid();
        $varUuid = $this->generateUuid();
        $opUuid = $this->generateUuid();
        $valUuid = $this->generateUuid();

        // Create statement with just uuid and data array (no type field)
        $this->statements[] = [
            'uuid' => $stmtUuid,
            'data' => [$varUuid, $opUuid, $valUuid]
        ];

        // Variable clause
        $this->clauses[$varUuid] = [
            'uuid' => $varUuid,
            'type' => 'variable',
            'name' => $node->var->name ?? ''
        ];

        // Operator clause
        $this->clauses[$opUuid] = [
            'uuid' => $opUuid,
            'type' => 'operator',
            'name' => '='
        ];

        // Value clause
        $this->clauses[$valUuid] = $this->processValue($node->expr, $valUuid);

        // Add to current function if exists
        if ($this->currentFunction !== null) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processIfStatement(Node\Stmt\If_ $node) {
        $stmtUuid = $this->generateUuid();
        $ifUuid = $this->generateUuid();
        $condUuid = $this->generateUuid();

        // If keyword clause
        $this->clauses[$ifUuid] = [
            'uuid' => $ifUuid,
            'type' => 'keyword',
            'name' => 'if'
        ];

        // Condition clause (simplified)
        $this->clauses[$condUuid] = [
            'uuid' => $condUuid,
            'type' => 'condition',
            'name' => ''
        ];

        // Create statement (no type field)
        $this->statements[] = [
            'uuid' => $stmtUuid,
            'data' => [$ifUuid, $condUuid]
        ];

        if ($this->currentFunction !== null) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processLoop($node) {
        $stmtUuid = $this->generateUuid();
        $loopUuid = $this->generateUuid();

        $loopType = 'for';
        if ($node instanceof Node\Stmt\Foreach_) {
            $loopType = 'foreach';
        } elseif ($node instanceof Node\Stmt\While_) {
            $loopType = 'while';
        }

        // Loop keyword clause
        $this->clauses[$loopUuid] = [
            'uuid' => $loopUuid,
            'type' => 'keyword',
            'name' => $loopType
        ];

        // Create statement (no type field)
        $this->statements[] = [
            'uuid' => $stmtUuid,
            'data' => [$loopUuid]
        ];

        if ($this->currentFunction !== null) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processReturn(Node\Stmt\Return_ $node) {
        $stmtUuid = $this->generateUuid();
        $returnUuid = $this->generateUuid();

        // Return keyword clause
        $this->clauses[$returnUuid] = [
            'uuid' => $returnUuid,
            'type' => 'keyword',
            'name' => 'return'
        ];

        $clauseUuids = [$returnUuid];

        // Process return value if exists
        if ($node->expr) {
            $exprUuid = $this->generateUuid();
            $this->clauses[$exprUuid] = $this->processValue($node->expr, $exprUuid);
            $clauseUuids[] = $exprUuid;
        }

        // Create statement (no type field)
        $this->statements[] = [
            'uuid' => $stmtUuid,
            'data' => $clauseUuids
        ];

        if ($this->currentFunction !== null) {
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
        if ($node instanceof Node\Expr\FuncCall) {
            $functionUuid = $this->generateUuid();
            $argsUuids = [];
            
            // Get function name
            $functionName = '';
            if ($node->name instanceof Node\Name) {
                $functionName = $node->name->toString();
            } elseif ($node->name instanceof Node\Expr\Variable) {
                $functionName = '$' . $node->name->name;
            }
            
            // Process arguments
            foreach ($node->args as $arg) {
                $argUuid = $this->generateUuid();
                $argsUuids[] = $argUuid;
                $this->clauses[$argUuid] = $this->processValue($arg->value, $argUuid);
            }
            
            // Store function reference
            $this->clauses[$functionUuid] = [
                'uuid' => $functionUuid,
                'type' => 'function',
                'name' => $functionName
            ];
            
            // Return the function call structure
            return [
                'uuid' => $uuid,
                'type' => 'function_call',
                'data' => array_merge([$functionUuid], $argsUuids)
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
