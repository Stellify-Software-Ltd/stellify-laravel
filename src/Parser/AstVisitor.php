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
    private array $operatorMap;
    private array $laravelClassMap;
    private array $laravelMethodMap;

    public function __construct()
    {
        $this->loadPredefinedUuids();
    }

    private function loadPredefinedUuids()
    {
        $jsonPath = __DIR__ . '/../../Config/predefined-uuids.json';
        
        if (!file_exists($jsonPath)) {
            throw new \RuntimeException("Predefined UUIDs file not found at: {$jsonPath}");
        }
        
        $data = json_decode(file_get_contents($jsonPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to parse predefined UUIDs JSON: " . json_last_error_msg());
        }
        
        // Merge operators and keywords into single operatorMap
        $this->operatorMap = array_merge(
            $data['operators'] ?? [],
            $data['keywords'] ?? []
        );
        
        $this->laravelClassMap = $data['laravel_classes'] ?? [];
        $this->laravelMethodMap = $data['laravel_methods'] ?? [];
    }

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
                'type' => $paramType,
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
                'type' => $paramType,
            ];
            $parameterUuids[] = $paramUuid;
        }

        $methodData = [
            'uuid' => $uuid,
            'name' => $node->name->name,
            'type' => 'method',
            'scope' => $scope,
            'parameters' => $parameterUuids,
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
        $opUuid = $this->operatorMap['Expr_Assign']; // Use predefined UUID
        $valUuid = $this->generateUuid();

        // Create statement with just uuid and data array
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

        // Value clause (or statement reference)
        $valueClause = $this->processValue($node->expr, $valUuid);
        if ($valueClause !== null) {
            $this->clauses[$valUuid] = $valueClause;
        }
        // If null, processValue created a statement with $valUuid, so we just reference it

        // Add to current function if exists
        if ($this->currentFunction !== null) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processIfStatement(Node\Stmt\If_ $node) {
        $stmtUuid = $this->generateUuid();
        $ifUuid = $this->operatorMap['if']; // Use predefined UUID for 'if' keyword
        $openParenUuid = $this->operatorMap['T_OPEN_PARENTHESIS'];
        $closeParenUuid = $this->operatorMap['T_CLOSE_PARENTHESIS'];

        // Process condition recursively - this returns array of clause UUIDs
        $conditionUuids = $this->processCondition($node->cond);

        // Create statement with if keyword + ( + condition + )
        $this->statements[] = [
            'uuid' => $stmtUuid,
            'data' => array_merge([$ifUuid, $openParenUuid], $conditionUuids, [$closeParenUuid])
        ];

        if ($this->currentFunction !== null) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    /**
     * Recursively process condition expressions
     * Returns array of clause UUIDs that make up the condition
     */
    private function processCondition(Node\Expr $node): array
    {
        // Handle binary operations (==, !=, &&, ||, >, <, etc.)
        if ($node instanceof Node\Expr\BinaryOp) {
            $clauseUuids = [];
            
            // Process left side recursively
            $leftUuids = $this->processConditionPart($node->left);
            $clauseUuids = array_merge($clauseUuids, $leftUuids);
            
            // Add operator using predefined UUID
            $operatorType = $node->getType();
            $operatorUuid = $this->operatorMap[$operatorType] ?? null;
            
            if (!$operatorUuid) {
                // Fallback: create a new operator clause if not in map
                $operatorUuid = $this->generateUuid();
                $operatorSymbol = $this->getOperatorSymbol($operatorType);
                $this->clauses[$operatorUuid] = [
                    'uuid' => $operatorUuid,
                    'type' => 'operator',
                    'name' => $operatorSymbol
                ];
            }
            
            $clauseUuids[] = $operatorUuid;
            
            // Process right side recursively
            $rightUuids = $this->processConditionPart($node->right);
            $clauseUuids = array_merge($clauseUuids, $rightUuids);
            
            return $clauseUuids;
        }
        
        // Handle other expression types
        return $this->processConditionPart($node);
    }

    /**
     * Process a single part of a condition
     * Can be recursive for nested conditions
     */
    private function processConditionPart(Node\Expr $node): array
    {
        // If it's another binary operation, recurse
        if ($node instanceof Node\Expr\BinaryOp) {
            return $this->processCondition($node);
        }
        
        // For conditions, expand complex expressions inline
        return $this->processValueInline($node);
    }

    /**
     * Process a value inline, expanding complex expressions into clauses
     * Used for conditions where we want everything in one statement
     * Returns array of clause UUIDs
     */
    private function processValueInline(Node $node): array
    {
        // Handle static calls inline: Auth::id()
        if ($node instanceof Node\Expr\StaticCall) {
            $doubleColonUuid = $this->operatorMap['T_DOUBLE_COLON'];
            $openParenUuid = $this->operatorMap['T_OPEN_PARENTHESIS'];
            $closeParenUuid = $this->operatorMap['T_CLOSE_PARENTHESIS'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $uuids = [];
            
            // Class clause - use predefined UUID if available
            $className = $node->class->toString();
            $classUuid = $this->laravelClassMap[$className] ?? $this->generateUuid();
            
            $this->clauses[$classUuid] = [
                'uuid' => $classUuid,
                'type' => 'class',
                'name' => $className
            ];
            $uuids[] = $classUuid;
            $uuids[] = $doubleColonUuid;
            
            // Method clause - use predefined UUID if available
            $methodName = $node->name->toString();
            $methodUuid = $this->laravelMethodMap[$methodName] ?? $this->generateUuid();
            
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $methodName
            ];
            $uuids[] = $methodUuid;
            
            // Open parenthesis
            $uuids[] = $openParenUuid;
            
            // Process arguments inline with commas
            $argCount = count($node->args);
            foreach ($node->args as $index => $arg) {
                $argUuids = $this->processValueInline($arg->value);
                $uuids = array_merge($uuids, $argUuids);
                
                // Add comma between arguments (but not after the last one)
                if ($index < $argCount - 1) {
                    $uuids[] = $commaUuid;
                }
            }
            
            // Close parenthesis
            $uuids[] = $closeParenUuid;
            
            return $uuids;
        }
        
        // Handle method calls inline: $user->save()
        if ($node instanceof Node\Expr\MethodCall) {
            $arrowUuid = $this->operatorMap['T_OBJECT_OPERATOR'];
            $openParenUuid = $this->operatorMap['T_OPEN_PARENTHESIS'];
            $closeParenUuid = $this->operatorMap['T_CLOSE_PARENTHESIS'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $uuids = [];
            
            // Target object
            $targetUuids = $this->processValueInline($node->var);
            $uuids = array_merge($uuids, $targetUuids);
            $uuids[] = $arrowUuid;
            
            // Method name - use predefined UUID if available
            $methodName = $node->name->toString();
            $methodUuid = $this->laravelMethodMap[$methodName] ?? $this->generateUuid();
            
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $methodName
            ];
            $uuids[] = $methodUuid;
            
            // Open parenthesis
            $uuids[] = $openParenUuid;
            
            // Arguments with commas
            $argCount = count($node->args);
            foreach ($node->args as $index => $arg) {
                $argUuids = $this->processValueInline($arg->value);
                $uuids = array_merge($uuids, $argUuids);
                
                if ($index < $argCount - 1) {
                    $uuids[] = $commaUuid;
                }
            }
            
            // Close parenthesis
            $uuids[] = $closeParenUuid;
            
            return $uuids;
        }
        
        // Handle property fetch inline: $user->name
        if ($node instanceof Node\Expr\PropertyFetch) {
            $arrowUuid = $this->operatorMap['T_OBJECT_OPERATOR'];
            $propertyUuid = $this->generateUuid();
            $uuids = [];
            
            // Object
            $objectUuids = $this->processValueInline($node->var);
            $uuids = array_merge($uuids, $objectUuids);
            $uuids[] = $arrowUuid;
            
            // Property
            $this->clauses[$propertyUuid] = [
                'uuid' => $propertyUuid,
                'type' => 'property',
                'name' => $node->name->toString()
            ];
            $uuids[] = $propertyUuid;
            
            return $uuids;
        }
        
        // Handle function calls inline: count($items)
        if ($node instanceof Node\Expr\FuncCall) {
            $functionUuid = $this->generateUuid();
            $openParenUuid = $this->operatorMap['T_OPEN_PARENTHESIS'];
            $closeParenUuid = $this->operatorMap['T_CLOSE_PARENTHESIS'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $uuids = [];
            
            // Function name
            $functionName = '';
            if ($node->name instanceof Node\Name) {
                $functionName = $node->name->toString();
            } elseif ($node->name instanceof Node\Expr\Variable) {
                $functionName = '$' . $node->name->name;
            }
            
            $this->clauses[$functionUuid] = [
                'uuid' => $functionUuid,
                'type' => 'function',
                'name' => $functionName
            ];
            $uuids[] = $functionUuid;
            
            // Open parenthesis
            $uuids[] = $openParenUuid;
            
            // Arguments with commas
            $argCount = count($node->args);
            foreach ($node->args as $index => $arg) {
                $argUuids = $this->processValueInline($arg->value);
                $uuids = array_merge($uuids, $argUuids);
                
                if ($index < $argCount - 1) {
                    $uuids[] = $commaUuid;
                }
            }
            
            // Close parenthesis
            $uuids[] = $closeParenUuid;
            
            return $uuids;
        }
        
        // Handle simple values
        $uuid = $this->generateUuid();
        
        if ($node instanceof Node\Scalar\String_) {
            $this->clauses[$uuid] = ['uuid' => $uuid, 'type' => 'string', 'name' => $node->value];
        } elseif ($node instanceof Node\Scalar\LNumber) {
            $this->clauses[$uuid] = ['uuid' => $uuid, 'type' => 'integer', 'name' => (string) $node->value];
        } elseif ($node instanceof Node\Scalar\DNumber) {
            $this->clauses[$uuid] = ['uuid' => $uuid, 'type' => 'float', 'name' => (string) $node->value];
        } elseif ($node instanceof Node\Expr\Variable) {
            $this->clauses[$uuid] = ['uuid' => $uuid, 'type' => 'variable', 'name' => $node->name];
        } elseif ($node instanceof Node\Expr\Array_) {
            // Handle arrays inline: ['key' => 'value']
            $openBracketUuid = $this->operatorMap['T_OPEN_BRACKET'];
            $closeBracketUuid = $this->operatorMap['T_CLOSE_BRACKET'];
            $arrowUuid = $this->operatorMap['T_DOUBLE_ARROW'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $uuids = [$openBracketUuid];
            
            $itemCount = count($node->items);
            foreach ($node->items as $index => $item) {
                // Handle key if exists
                if ($item->key !== null) {
                    $keyUuids = $this->processValueInline($item->key);
                    $uuids = array_merge($uuids, $keyUuids);
                    $uuids[] = $arrowUuid;
                }
                
                // Handle value
                $valueUuids = $this->processValueInline($item->value);
                $uuids = array_merge($uuids, $valueUuids);
                
                // Add comma between items
                if ($index < $itemCount - 1) {
                    $uuids[] = $commaUuid;
                }
            }
            
            $uuids[] = $closeBracketUuid;
            return $uuids;
        } else {
            $this->clauses[$uuid] = ['uuid' => $uuid, 'type' => 'unknown', 'name' => ''];
        }
        
        return [$uuid];
    }

    /**
     * Get operator symbol from node type
     */
    private function getOperatorSymbol(string $nodeType): string
    {
        $operators = [
            'Expr_BinaryOp_Equal' => '==',
            'Expr_BinaryOp_NotEqual' => '!=',
            'Expr_BinaryOp_Identical' => '===',
            'Expr_BinaryOp_NotIdentical' => '!==',
            'Expr_BinaryOp_Greater' => '>',
            'Expr_BinaryOp_GreaterOrEqual' => '>=',
            'Expr_BinaryOp_Smaller' => '<',
            'Expr_BinaryOp_SmallerOrEqual' => '<=',
            'Expr_BinaryOp_BooleanAnd' => '&&',
            'Expr_BinaryOp_BooleanOr' => '||',
            'Expr_BinaryOp_LogicalAnd' => 'and',
            'Expr_BinaryOp_LogicalOr' => 'or',
            'Expr_BinaryOp_Plus' => '+',
            'Expr_BinaryOp_Minus' => '-',
            'Expr_BinaryOp_Mul' => '*',
            'Expr_BinaryOp_Div' => '/',
            'Expr_BinaryOp_Mod' => '%',
            'Expr_BinaryOp_Concat' => '.',
        ];
        
        return $operators[$nodeType] ?? '?';
    }

    private function processLoop($node) {
        $stmtUuid = $this->generateUuid();

        $loopType = 'for';
        if ($node instanceof Node\Stmt\Foreach_) {
            $loopType = 'foreach';
        } elseif ($node instanceof Node\Stmt\While_) {
            $loopType = 'while';
        }

        // Use predefined UUID for loop keyword
        $loopUuid = $this->operatorMap[$loopType];

        // Create statement
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
        
        // Use predefined UUID for return keyword
        $returnUuid = $this->operatorMap['return'];

        $clauseUuids = [$returnUuid];

        // Process return value if exists
        if ($node->expr) {
            $exprUuid = $this->generateUuid();
            $valueClause = $this->processValue($node->expr, $exprUuid);
            if ($valueClause !== null) {
                $this->clauses[$exprUuid] = $valueClause;
            }
            // If null, processValue created a statement with $exprUuid
            $clauseUuids[] = $exprUuid;
        }

        // Create statement
        $this->statements[] = [
            'uuid' => $stmtUuid,
            'data' => $clauseUuids
        ];

        if ($this->currentFunction !== null) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processValue(Node $node, string $uuid): ?array {
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
        if ($node instanceof Node\Expr\Array_) {
            // Handle arrays as statements: ['key' => 'value']
            $openBracketUuid = $this->operatorMap['T_OPEN_BRACKET'];
            $closeBracketUuid = $this->operatorMap['T_CLOSE_BRACKET'];
            $arrowUuid = $this->operatorMap['T_DOUBLE_ARROW'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $dataUuids = [$openBracketUuid];
            
            $itemCount = count($node->items);
            foreach ($node->items as $index => $item) {
                // Handle key if exists
                if ($item->key !== null) {
                    $keyUuid = $this->generateUuid();
                    $keyClause = $this->processValue($item->key, $keyUuid);
                    if ($keyClause !== null) {
                        $this->clauses[$keyUuid] = $keyClause;
                    }
                    $dataUuids[] = $keyUuid;
                    $dataUuids[] = $arrowUuid;
                }
                
                // Handle value
                $valueUuid = $this->generateUuid();
                $valueClause = $this->processValue($item->value, $valueUuid);
                if ($valueClause !== null) {
                    $this->clauses[$valueUuid] = $valueClause;
                }
                $dataUuids[] = $valueUuid;
                
                // Add comma between items
                if ($index < $itemCount - 1) {
                    $dataUuids[] = $commaUuid;
                }
            }
            
            $dataUuids[] = $closeBracketUuid;
            
            // Create a statement for the array
            $this->statements[] = [
                'uuid' => $uuid,
                'data' => $dataUuids
            ];
            
            // Return null since we created a statement
            return null;
        }
        if ($node instanceof Node\Expr\PropertyFetch) {
            // Handle $object->property
            $objectUuid = $this->generateUuid();
            $arrowUuid = $this->operatorMap['T_OBJECT_OPERATOR']; // ->
            $propertyUuid = $this->generateUuid();
            
            // Process object as clause
            $objectClause = $this->processValue($node->var, $objectUuid);
            if ($objectClause !== null) {
                $this->clauses[$objectUuid] = $objectClause;
            }
            // If null, processValue created a statement with $objectUuid
            
            // Store property as clause
            $this->clauses[$propertyUuid] = [
                'uuid' => $propertyUuid,
                'type' => 'property',
                'name' => $node->name->toString()
            ];
            
            // Create a statement for the property fetch using the passed-in UUID
            $this->statements[] = [
                'uuid' => $uuid,
                'data' => [$objectUuid, $arrowUuid, $propertyUuid]
            ];
            
            // Return null to indicate no clause should be created
            return null;
        }
        if ($node instanceof Node\Expr\MethodCall) {
            $targetUuid = $this->generateUuid();
            $arrowUuid = $this->operatorMap['T_OBJECT_OPERATOR']; // ->
            $openParenUuid = $this->operatorMap['T_OPEN_PARENTHESIS'];
            $closeParenUuid = $this->operatorMap['T_CLOSE_PARENTHESIS'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $argsUuids = [];
            
            // Process the target object
            $targetClause = $this->processValue($node->var, $targetUuid);
            if ($targetClause !== null) {
                $this->clauses[$targetUuid] = $targetClause;
            }
            
            // Process arguments
            $argCount = count($node->args);
            foreach ($node->args as $index => $arg) {
                $argUuid = $this->generateUuid();
                $argClause = $this->processValue($arg->value, $argUuid);
                if ($argClause !== null) {
                    $this->clauses[$argUuid] = $argClause;
                }
                $argsUuids[] = $argUuid;
                
                if ($index < $argCount - 1) {
                    $argsUuids[] = $commaUuid;
                }
            }
    
            // Store method name as clause - use predefined UUID if available
            $methodName = $node->name->toString();
            $methodUuid = $this->laravelMethodMap[$methodName] ?? $this->generateUuid();
            
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $methodName
            ];
    
            // Create a statement for the method call using the passed-in UUID
            // Structure: target -> method ( args )
            $this->statements[] = [
                'uuid' => $uuid,
                'data' => array_merge([$targetUuid, $arrowUuid, $methodUuid, $openParenUuid], $argsUuids, [$closeParenUuid])
            ];
            
            // Return null to indicate no clause should be created
            return null;
        }
        if ($node instanceof Node\Expr\StaticCall) {
            $doubleColonUuid = $this->operatorMap['T_DOUBLE_COLON']; // ::
            $openParenUuid = $this->operatorMap['T_OPEN_PARENTHESIS'];
            $closeParenUuid = $this->operatorMap['T_CLOSE_PARENTHESIS'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $argsUuids = [];
            
            // Process arguments
            $argCount = count($node->args);
            foreach ($node->args as $index => $arg) {
                $argUuid = $this->generateUuid();
                $argClause = $this->processValue($arg->value, $argUuid);
                if ($argClause !== null) {
                    $this->clauses[$argUuid] = $argClause;
                }
                $argsUuids[] = $argUuid;
                
                // Add comma between arguments
                if ($index < $argCount - 1) {
                    $argsUuids[] = $commaUuid;
                }
            }
    
            // Store class reference as clause - use predefined UUID if available
            $className = $node->class->toString();
            $classUuid = $this->laravelClassMap[$className] ?? $this->generateUuid();
            
            $this->clauses[$classUuid] = [
                'uuid' => $classUuid,
                'type' => 'class',
                'name' => $className
            ];
    
            // Store method name as clause - use predefined UUID if available
            $methodName = $node->name->toString();
            $methodUuid = $this->laravelMethodMap[$methodName] ?? $this->generateUuid();
            
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $methodName
            ];
    
            // Create a statement for the static call using the passed-in UUID
            // Structure: Class :: method ( args )
            $this->statements[] = [
                'uuid' => $uuid,
                'data' => array_merge([$classUuid, $doubleColonUuid, $methodUuid, $openParenUuid], $argsUuids, [$closeParenUuid])
            ];
            
            // Return null to indicate no clause should be created
            // The statement already exists and uses the passed-in UUID
            return null;
        }
        if ($node instanceof Node\Expr\FuncCall) {
            $functionUuid = $this->generateUuid();
            $openParenUuid = $this->operatorMap['T_OPEN_PARENTHESIS'];
            $closeParenUuid = $this->operatorMap['T_CLOSE_PARENTHESIS'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $argsUuids = [];
            
            // Get function name
            $functionName = '';
            if ($node->name instanceof Node\Name) {
                $functionName = $node->name->toString();
            } elseif ($node->name instanceof Node\Expr\Variable) {
                $functionName = '$' . $node->name->name;
            }
            
            // Process arguments
            $argCount = count($node->args);
            foreach ($node->args as $index => $arg) {
                $argUuid = $this->generateUuid();
                $argClause = $this->processValue($arg->value, $argUuid);
                if ($argClause !== null) {
                    $this->clauses[$argUuid] = $argClause;
                }
                $argsUuids[] = $argUuid;
                
                if ($index < $argCount - 1) {
                    $argsUuids[] = $commaUuid;
                }
            }
            
            // Store function reference as clause
            $this->clauses[$functionUuid] = [
                'uuid' => $functionUuid,
                'type' => 'function',
                'name' => $functionName
            ];
            
            // Create a statement for the function call using the passed-in UUID
            // Structure: function ( args )
            $this->statements[] = [
                'uuid' => $uuid,
                'data' => array_merge([$functionUuid, $openParenUuid], $argsUuids, [$closeParenUuid])
            ];
            
            // Return null to indicate no clause should be created
            return null;
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