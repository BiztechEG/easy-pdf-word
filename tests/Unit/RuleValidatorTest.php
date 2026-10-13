<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;
use BiztechEG\EasyPdfWord\Validation\RuleValidator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** Each case was checked against Laravel 12's validator, which gives the same result. */
    public static function laravelCases(): array
    {
        return [
            'required_if names the value the other field has' => [['method' => 'transfer'], ['cheque.number' => 'required_if:method,cheque,transfer'], ['cheque.number' => ['The cheque.number field is required when method is transfer.']]],
            'required_if with a true other field' => [['method' => true], ['x' => 'required_if:method,true'], ['x' => ['The x field is required when method is true.']]],
            'required_if with a null other field' => [['method' => null], ['x' => 'required_if:method,null'], ['x' => ['The x field is required when method is empty.']]],
            'required_if names the matching row' => [['items' => [['a' => 1], ['a' => 2]]], ['items.*.b' => 'required_if:items.*.a,2'], ['items.1.b' => ['The items.1.b field is required when items.1.a is 2.']]],
            'required_if skips a missing other field' => [[], ['x' => 'required_if:method,null'], []],
            'in does not take false for 0' => [['b' => false], ['b' => 'in:0,1'], ['b' => ['The selected b is invalid.']]],
            'date_format takes any of its formats' => [['p' => '2026/09'], ['p' => 'date_format:Y-m,Y/m'], []],
            'after_or_equal refuses what is not a date' => [['to' => ['x']], ['to' => 'after_or_equal:from'], ['to' => ['The to field must be a date after or equal to from.']]],
            'gt on text counts characters' => [['a' => 'abc'], ['a' => 'gt:5'], ['a' => ['The a field must be greater than 5 characters.']]],
            'gt on another field shows its size' => [['a' => 'ab', 'b' => 'abcd'], ['a' => 'string|gt:b'], ['a' => ['The a field must be greater than 4 characters.']]],
            'gt on lists counts items' => [['a' => [1], 'b' => [1, 2]], ['a' => 'array|gt:b'], ['a' => ['The a field must have more than 2 items.']]],
        ];
    }

    #[DataProvider('laravelCases')]
    public function test_rules_behave_as_in_laravel(array $data, array $rules, array $errors): void
    {
        try {
            (new RuleValidator)->validate($data, $rules);
            $this->assertSame([], $errors);
        } catch (ValidationFailed $e) {
            $this->assertSame($errors, $e->errors());
        }
    }
}
