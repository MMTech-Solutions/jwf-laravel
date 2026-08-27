<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Tests\Unit\ValidationProfiles;

use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;
use Mmt\JwfLaravel\Exceptions\UnsafeValidationRuleException;
use Mmt\JwfLaravel\ValidationProfiles\RuleCompiler;
use PHPUnit\Framework\TestCase;

final class RuleCompilerTest extends TestCase
{
    public function testItCompilesTheAllowedDeclarativeRules(): void
    {
        $compiled = (new RuleCompiler())->compile([
            new RuleDefinition('required'),
            new RuleDefinition('integer'),
            new RuleDefinition('between', ['min' => 1, 'max' => 5]),
            new RuleDefinition('in', ['values' => ['one', 'two']]),
        ]);

        self::assertSame(['required', 'integer', 'between:1,5', 'in:one,two'], $compiled);
    }

    public function testItRejectsUnknownOrUnsafeRules(): void
    {
        $this->expectException(UnsafeValidationRuleException::class);

        (new RuleCompiler())->compile([new RuleDefinition('regex', ['pattern' => '/.*/e'])]);
    }
}
