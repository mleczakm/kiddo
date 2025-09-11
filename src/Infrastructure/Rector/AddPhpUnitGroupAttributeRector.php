<?php

declare(strict_types=1);

namespace App\Infrastructure\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds missing PHPUnit #[Group(...)] attribute to test classes based on simple heuristics:
 * - If the classname contains "Smoke" -> #[Group('smoke')]
 * - Else if it extends PHPUnit\\Framework\\TestCase -> #[Group('unit')]
 * - Else -> #[Group('functional')]
 *
 * The rule only targets classes whose name ends with "Test" to avoid touching non-test classes.
 */
final class AddPhpUnitGroupAttributeRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Ensure test classes have a #[\\PHPUnit\\Framework\\Attributes\\Group(...)] attribute: smoke/unit/functional',
            [
                new CodeSample(
                    <<<'CODE'
                        <?php
                        use PHPUnit\\Framework\\TestCase;
                        final class ExampleTest extends TestCase
                        {
                        }
                        CODE
                    ,
                    <<<'CODE'
                        <?php
                        use PHPUnit\\Framework\\Attributes\\Group;
                        use PHPUnit\\Framework\\TestCase;

                        #[Group('unit')]
                        final class ExampleTest extends TestCase
                        {
                        }
                        CODE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        // Only handle classes named *Test
        if (! $node->name instanceof Identifier) {
            return null;
        }

        $className = $node->name->toString();
        if (! str_ends_with($className, 'Test')) {
            return null;
        }

        $group = $this->resolveDesiredGroup($node, $className);
        if ($group === null) {
            return null;
        }

        // If already has Group attribute with the same value, skip
        if ($this->hasGroupAttributeWithValue($node, $group)) {
            return null;
        }

        // If it has any Group attribute (different value), do not override
        if ($this->hasAnyGroupAttribute($node)) {
            return null;
        }

        // Add attribute #[\PHPUnit\Framework\Attributes\Group('<group>')]
        $attribute = new Attribute(new Name('\\' . Group::class), [new Arg(new String_($group))]);
        $attributeGroup = new AttributeGroup([$attribute]);
        // Prepend for visibility and stability
        array_unshift($node->attrGroups, $attributeGroup);

        return $node;
    }

    private function resolveDesiredGroup(Class_ $node, string $className): ?string
    {
        // If class name contains Smoke, treat as smoke test
        if (str_contains($className, 'Smoke')) {
            return 'smoke';
        }

        // If extends PHPUnit TestCase => unit
        if ($node->extends instanceof Name) {
            if ($this->nodeNameResolver->isName($node->extends, TestCase::class)) {
                return 'unit';
            }
        }

        // Otherwise classify as functional by default
        return 'functional';
    }

    private function hasAnyGroupAttribute(Class_ $class): bool
    {
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->nodeNameResolver->isName($attr->name, Group::class)
                    || $this->nodeNameResolver->isName($attr->name, 'Group')) {
                    return true;
                }
            }
        }
        return false;
    }

    private function hasGroupAttributeWithValue(Class_ $class, string $expected): bool
    {
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (! ($this->nodeNameResolver->isName($attr->name, Group::class)
                    || $this->nodeNameResolver->isName($attr->name, 'Group'))) {
                    continue;
                }

                if ($attr->args === []) {
                    continue;
                }

                $firstArg = $attr->args[0]->value;
                if ($firstArg instanceof String_ && $firstArg->value === $expected) {
                    return true;
                }
            }
        }

        return false;
    }
}
