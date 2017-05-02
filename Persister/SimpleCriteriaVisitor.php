<?php

namespace Bankiru\Api\Doctrine\Persister;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor;
use Doctrine\Common\Collections\Expr\Value;

final class SimpleCriteriaVisitor extends ExpressionVisitor
{
    /**
     * Converts a comparison expression into the target query language output.
     *
     * @param Comparison $comparison
     *
     * @return mixed
     */
    public function walkComparison(Comparison $comparison)
    {
        switch ($comparison->getOperator()) {
            case Comparison::EQ:
            case Comparison::IN:
                return [$comparison->getField() => $this->walkValue($comparison->getValue())];
            default:
                throw new \InvalidArgumentException('Simple API queries support only EQ and IN operators');
        }
    }

    /**
     * Converts a value expression into the target query language part.
     *
     * @param Value $value
     *
     * @return mixed
     */
    public function walkValue(Value $value)
    {
        return $value->getValue();
    }

    /**
     * Converts a composite expression into the target query language output.
     *
     * @param CompositeExpression $expr
     *
     * @return mixed
     */
    public function walkCompositeExpression(CompositeExpression $expr)
    {
        $expressions = [];
        switch ($expr->getType()) {
            case CompositeExpression::TYPE_AND:
                foreach ($expr->getExpressionList() as $expression) {
                    $expressions[] = $this->dispatch($expression);
                }
                break;
            default:
                throw new \InvalidArgumentException('Simple API queries support only AND composite expression');
        }

        return call_user_func_array('array_replace', $expressions);
    }
}
