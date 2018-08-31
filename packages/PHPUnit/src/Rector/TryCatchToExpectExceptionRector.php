<?php declare(strict_types=1);

namespace Rector\PHPUnit\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\TryCatch;
use Rector\NodeAnalyzer\MethodCallAnalyzer;
use Rector\Rector\AbstractPHPUnitRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class TryCatchToExpectExceptionRector extends AbstractPHPUnitRector
{
    /**
     * @var Expression[]
     */
    private $newExpressions = [];

    /**
     * @var MethodCallAnalyzer
     */
    private $methodCallAnalyzer;

    public function __construct(MethodCallAnalyzer $methodCallAnalyzer)
    {
        $this->methodCallAnalyzer = $methodCallAnalyzer;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Turns try/catch to expectException() call', [
            new CodeSample(
                <<<'CODE_SAMPLE'
try {
	$someService->run();
} catch (Throwable $exception) {
    $this->assertInstanceOf(RuntimeException::class, $e);
    $this->assertContains('There was an error executing the following script', $e->getMessage());
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$this->expectException(RuntimeException::class);
$this->expectExceptionMessage('There was an error executing the following script');
$someService->run();
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $classMethodNode
     */
    public function refactor(Node $classMethodNode): ?Node
    {
        if (! $this->isInTestClass($classMethodNode)) {
            return $classMethodNode;
        }

        if (! $classMethodNode->stmts) {
            return $classMethodNode;
        }

        $proccesed = [];
        foreach ($classMethodNode->stmts as $key => $stmt) {
            if (! $stmt instanceof TryCatch) {
                continue;
            }

            $proccesed = $this->processTryCatch($stmt);
            if ($proccesed === null) {
                continue;
            }

            unset($classMethodNode->stmts[$key]);
        }

        $classMethodNode->stmts = array_merge($classMethodNode->stmts, $proccesed);

        return $classMethodNode;
    }

    private function processAssertInstanceOf(Node $node, Variable $exceptionVariableNode): void
    {
        if (! $this->methodCallAnalyzer->isThisMethodCallWithNames($node, ['assertInstanceOf'])) {
            return;
        }

        /** @var MethodCall $node */
        $argumentVariableName = $node->args[1]->value->name;

        // is na exception variable
        if ($exceptionVariableNode->name !== $argumentVariableName) {
            return;
        }

        $this->newExpressions[] = new Expression(new MethodCall($node->var, 'expectException', [$node->args[0]]));
    }

    private function processExceptionMessage(Node $node, Variable $exceptionVariable): void
    {
        if (! $this->methodCallAnalyzer->isThisMethodCallWithNames(
            $node,
            ['assertContains', 'assertSame', 'assertEquals']
        )) {
            return;
        }

        /** @var MethodCall $node */
        $secondArgument = $node->args[1]->value;
        // looking for "$exception->getMessage()"
        if (! $secondArgument instanceof MethodCall) {
            return;
        }

        if (! $secondArgument->var instanceof Variable) {
            return;
        }

        if ((string) $secondArgument->name !== 'getMessage') {
            return;
        }

        if ($exceptionVariable->name !== $secondArgument->var->name) {
            return;
        }

        $this->newExpressions[] = new Expression(new MethodCall($node->var, 'expectExceptionMessage', [
            $node->args[0],
        ]));
    }

    private function processExceptionCode(Node $node, Variable $exceptionVariable): void
    {
        if (! $this->methodCallAnalyzer->isThisMethodCallWithNames($node, ['assertSame', 'assertEquals'])) {
            return;
        }

        /** @var MethodCall $node */
        $secondArgument = $node->args[1]->value;
        // looking for "$exception->getCode()"
        if (! $secondArgument instanceof MethodCall) {
            return;
        }

        if (! $secondArgument->var instanceof Variable) {
            return;
        }

        if ((string) $secondArgument->name !== 'getCode') {
            return;
        }

        if ($exceptionVariable->name !== $secondArgument->var->name) {
            return;
        }

        $this->newExpressions[] = new Expression(new MethodCall($node->var, 'expectExceptionCode', [
            $node->args[0],
        ]));
    }

    /**
     * @return Expression[]|null
     */
    private function processTryCatch(TryCatch $tryCatchNode): ?array
    {
        if (count($tryCatchNode->catches) !== 1) {
            return null;
        }

        $this->newExpressions = [];

        $exceptionVariable = $tryCatchNode->catches[0]->var;

        // we look for:
        // - instance of $exceptionVariableName
        // - assert same string to $exceptionVariableName->getMessage()
        foreach ($tryCatchNode->catches[0]->stmts as $catchedStmt) {
            // not a match
            if (! $catchedStmt instanceof Expression) {
                return null;
            }

            $this->processAssertInstanceOf($catchedStmt->expr, $exceptionVariable);
            $this->processExceptionMessage($catchedStmt->expr, $exceptionVariable);
            $this->processExceptionCode($catchedStmt->expr, $exceptionVariable);
        }

        // return all statements
        foreach ($tryCatchNode->stmts as $stmt) {
            if (! $stmt instanceof Expression) {
                return null;
            }

            $this->newExpressions[] = $stmt;
        }

        return $this->newExpressions;
    }
}
