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

    public function __construct()
    {
        $this->initializeOperatorMap();
    }

    private function initializeOperatorMap()
    {
        // Map PHP-Parser operators to predefined UUIDs from ClauseController
        $this->operatorMap = [
            // Comparison operators
            'Expr_BinaryOp_Equal' => '849068cf-b00a-41ae-98b3-14b7c3bb3225',           // ==  (T_IS_EQUAL)
            'Expr_BinaryOp_NotEqual' => '6cd10031-a0df-4744-9d52-1c678edfdff5',        // !=  (T_IS_NOT_EQUAL)
            'Expr_BinaryOp_Identical' => 'e84f1d91-d473-4780-a49e-a8fa182c9f79',       // === (T_IS_IDENTICAL)
            'Expr_BinaryOp_NotIdentical' => '139bca06-3cf3-44c4-8194-ec45066af6d3',    // !== (T_IS_NOT_IDENTICAL)
            'Expr_BinaryOp_Greater' => 'b2657fdf-e6c3-4139-ad1e-05e49731e8b2',         // >   (T_GREATER)
            'Expr_BinaryOp_GreaterOrEqual' => 'f1391f5c-7f63-49c1-ad25-95d1bcd703b1',  // >=  (T_GREATER_EQUAL)
            'Expr_BinaryOp_Smaller' => 'c86ecbe7-ad2c-4350-81ab-4632f2955894',         // <   (T_LESS)
            'Expr_BinaryOp_SmallerOrEqual' => '256a078b-6eb4-4537-8cea-2769f0eddaa6',  // <=  (T_LESS_EQUAL)
            
            // Logical operators
            'Expr_BinaryOp_BooleanAnd' => '349643cd-9f3f-4803-aa6c-4539b74fd959',      // &&  (logical_and)
            'Expr_BinaryOp_BooleanOr' => '4a500c21-4e98-4d68-9042-7ac7755f0b0e',       // ||  (logical_or)
            'Expr_BinaryOp_LogicalAnd' => '349643cd-9f3f-4803-aa6c-4539b74fd959',      // and
            'Expr_BinaryOp_LogicalOr' => '4a500c21-4e98-4d68-9042-7ac7755f0b0e',       // or
            
            // Arithmetic operators
            'Expr_BinaryOp_Plus' => '970957d7-e538-4484-9e7f-1bf7339b742e',            // +   (T_PLUS)
            'Expr_BinaryOp_Minus' => 'd958ffb7-2113-4b19-aaae-baa747ae85fb',           // -   (T_MINUS)
            'Expr_BinaryOp_Mul' => 'f9ecc67c-2ae4-4be1-9f06-b6f1eff56ab9',             // *   (T_MUL)
            'Expr_BinaryOp_Div' => '0d51cde0-fb14-4a8a-a533-bacae104914e',             // /   (T_DIV)
            'Expr_BinaryOp_Mod' => '00d4f849-5321-4592-931e-04d4feff0062',             // %   (T_MOD)
            'Expr_BinaryOp_Pow' => '85fb1131-8197-49e1-9556-f28a1610b946',             // **  (T_POW)
            
            // Assignment operators
            'Expr_Assign' => 'a42f25f4-e5c0-40ea-9446-d209e226f9c3',                   // =   (T_EQUALS)
            'Expr_AssignOp_Plus' => 'f5e6941b-4c62-46b2-9ccc-0a3780c6f28d',            // +=  (T_PLUS_EQUAL)
            'Expr_AssignOp_Minus' => '15db64fc-f5db-44a4-aa6a-2a4af68eb4a1',           // -=  (T_MINUS_EQUAL)
            'Expr_AssignOp_Mul' => '43b8c336-867e-4b54-a898-7703969527bc',             // *=  (T_MUL_EQUAL)
            'Expr_AssignOp_Div' => '4c68f7e2-d1dc-4ef3-85f4-bc53e6014037',             // /=  (T_DIV_EQUAL)
            'Expr_AssignOp_Mod' => '612b4f29-84c8-460c-bd06-c5be03e687a9',             // %=  (T_MOD_EQUAL)
            'Expr_AssignOp_Pow' => '1fd9032c-ae1c-4bee-a456-ccad23c5dee7',             // **= (T_POW_EQUAL)
            
            // Other operators
            'Expr_BinaryOp_Concat' => '19e37355-a3ea-408e-bce0-44377f75ce37',          // .   (T_CONCAT)
            'Expr_BinaryOp_Coalesce' => 'ce413e0e-1d6e-46f2-8499-2fb6ee64ee66',        // ??  (T_COALESCE)
            'Expr_BinaryOp_Spaceship' => 'bc4b9cd5-1476-4ab6-9a1c-03b36a3165d4',       // <=> (T_SPACESHIP)
            'Expr_Instanceof' => 'a41be7e3-ff7d-4afc-9cf4-622cff71ab84',               // instanceof (T_INSTANCEOF)

            // Special operators
            'T_DOUBLE_COLON' => '666adff5-adee-41d4-af81-09ab3624af76',                // ::
            'T_OBJECT_OPERATOR' => '8209a1b5-42a0-44da-a6de-7fe9dfe81a26',             // ->
            'T_DOUBLE_ARROW' => 'a83be3b6-ad5a-48df-86a7-d136e71ca16b',                // =>
            'T_OPEN_PARENTHESIS' => '742b50c0-f142-4e02-8951-8cf5a42419e5',            // (
            'T_CLOSE_PARENTHESIS' => '81d91855-e13f-4eb4-adab-11d30899ffcc',           // )
            'T_OPEN_BRACKET' => '45d7b573-4065-4bf5-8642-06e1de248099',                // [
            'T_CLOSE_BRACKET' => '167be16c-67f5-4d57-bccf-7a7fbd4de3d8',               // ]
            'T_OPEN_BRACE' => '151ba8d4-80f6-4410-97f1-1d247381eaac',                  // {
            'T_CLOSE_BRACE' => 'd312a5d3-2abd-4283-ba85-793a74d64dfb',                 // }
            'T_COMMA' => '90fde00b-665d-4662-af40-bbfb931ed379',                        // ,
            'T_END_LINE' => 'dac4e03b-4f93-4218-aa36-7cb772552095',                    // ;

            // Keywords
            'if' => '4b19462a-3fc3-47dd-bd83-9a3635e4d20b',          // T_IF
            'else' => '41a55d66-fdf8-4345-979b-3714453af961',        // T_ELSE
            'elseif' => 'cc34e605-8390-4f18-a66f-4885621a1693',      // T_ELSEIF
            'for' => '23951891-1b84-4fa9-9f53-fe99faabf08e',         // T_FOR
            'foreach' => '13a0ed23-5db6-411c-8efc-37313ae8b3b3',     // T_FOREACH
            'while' => 'ec038718-675a-4bd8-aa9d-47ff22be44c4',       // T_WHILE
            'do' => '04ba7c42-51df-446b-aae4-0d7083c46572',          // T_DO
            'return' => 'a8cfb8af-76ed-4dc9-974b-8e4c98749125',      // T_RETURN
            'break' => '6d2bbd61-b650-4c8e-a29b-def43dbedbcf',       // T_BREAK
            'continue' => '95cce1b1-5d35-4ae2-a0e8-14597de5c93b',    // T_CONTINUE
            'throw' => 'a76c1423-7160-4dd8-bba2-57ca96a0b0b8',       // T_THROW
            'try' => '1bd336ac-ceaf-43d0-8c69-e22907cf94f2',         // T_TRY
            'catch' => '83a7ae87-7da2-444a-97f9-2282f0d9754a',       // T_CATCH
            'finally' => 'f43da096-7924-442e-9207-cb37d1af231f',    // T_FINALLY
            'new' => 'd9b6fc21-6dd2-4b9e-ac72-344f1e08bca1',         // T_NEW
            'this' => '92223eed-6a85-4044-a25a-aacc61b23fdd',        // T_THIS
            'function' => 'bba06fdb-d55e-45fa-b121-07ed156a10bf',    // T_FUNCTION
            'class' => 'f5ffc4a0-837d-4a09-b32e-e63888f5a1d8',       // T_CLASS
            'public' => 'c08c4016-34fb-4ce8-9af3-47f47160f265',      // T_PUBLIC
            'protected' => 'f8aa05c4-b07f-4471-9fa0-6b78a49c3ef2',   // T_PROTECTED
            'private' => '0d2c710c-e4ae-4ec5-b0c3-12557560cb3c',     // T_PRIVATE
            'static' => '1359d451-a9e6-4680-bc8f-f609ce637843',      // T_STATIC
        ];
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
            $classUuid = $this->generateUuid();
            $doubleColonUuid = $this->operatorMap['T_DOUBLE_COLON'];
            $methodUuid = $this->generateUuid();
            $openParenUuid = $this->operatorMap['T_OPEN_PARENTHESIS'];
            $closeParenUuid = $this->operatorMap['T_CLOSE_PARENTHESIS'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $uuids = [];
            
            // Class clause
            $this->clauses[$classUuid] = [
                'uuid' => $classUuid,
                'type' => 'class',
                'name' => $node->class->toString()
            ];
            $uuids[] = $classUuid;
            $uuids[] = $doubleColonUuid;
            
            // Method clause
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
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
            $methodUuid = $this->generateUuid();
            $openParenUuid = $this->operatorMap['T_OPEN_PARENTHESIS'];
            $closeParenUuid = $this->operatorMap['T_CLOSE_PARENTHESIS'];
            $commaUuid = $this->operatorMap['T_COMMA'];
            $uuids = [];
            
            // Target object
            $targetUuids = $this->processValueInline($node->var);
            $uuids = array_merge($uuids, $targetUuids);
            $uuids[] = $arrowUuid;
            
            // Method name
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
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
            $methodUuid = $this->generateUuid();
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
    
            // Store method name as clause
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
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
            $classUuid = $this->generateUuid();
            $doubleColonUuid = $this->operatorMap['T_DOUBLE_COLON']; // ::
            $methodUuid = $this->generateUuid();
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
    
            // Store class reference as clause
            $this->clauses[$classUuid] = [
                'uuid' => $classUuid,
                'type' => 'class',
                'name' => $node->class->toString()
            ];
    
            // Store method name as clause
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
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
