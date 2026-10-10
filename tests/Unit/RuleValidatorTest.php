<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;
use BiztechEG\EasyPdfWord\Validation\RuleValidator;
use LogicException;
use PHPUnit\Framework\TestCase;

/** The plain PHP validator without a Laravel app. Agreement with Laravel is in RuleValidatorParityTest. */
class RuleValidatorTest extends TestCase
{
    public function test_valid_data_passes(): void
    {
        (new RuleValidator)->validate(
            ['invoice' => ['number' => 'INV-1', 'date' => '2026-10-08'], 'items' => [['quantity' => '2', 'unit_price' => 10]]],
            ['invoice.number' => 'required|string', 'invoice.date' => ['required', 'date'], 'items' => ['required', 'array', 'min:1'], 'items.*.quantity' => ['required', 'numeric', 'min:0']],
        );

        $this->addToAssertionCount(1);
    }

    public function test_errors_are_collected_by_field(): void
    {
        try {
            (new RuleValidator)->validate(
                ['items' => [['quantity' => 'two'], []], 'due_date' => 'soon'],
                ['customer.name' => ['required'], 'items.*.quantity' => ['required', 'numeric'], 'due_date' => ['nullable', 'date']],
            );
            $this->fail('The data should not pass.');
        } catch (ValidationFailed $e) {
            $this->assertSame([
                'customer.name' => ['The customer.name field is required.'],
                'items.0.quantity' => ['The items.0.quantity field must be a number.'],
                'items.1.quantity' => ['The items.1.quantity field is required.'],
                'due_date' => ['The due date field must be a valid date.'],
            ], $e->errors());
            $this->assertSame('The customer.name field is required. (and 3 more errors)', $e->getMessage());
        }
    }

    public function test_rule_objects_need_laravel(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("[name] is an object or closure; that needs Laravel's validator");

        (new RuleValidator)->validate(['name' => 'x'], ['name' => [fn () => true]]);
    }

    public function test_unknown_rules_are_named(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The rule [email] on [email] needs Laravel's validator.");

        (new RuleValidator)->validate(['email' => 'x'], ['email' => 'email']);
    }

    public function test_messages_can_be_thrown_from_prepare(): void
    {
        $e = ValidationFailed::withMessages(['items.0.discount' => 'Too large.']);

        $this->assertSame(['items.0.discount' => ['Too large.']], $e->errors());
        $this->assertSame('Too large.', $e->getMessage());
    }
}
