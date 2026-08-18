<?php

namespace Tests\Unit;

use App\Support\MemberRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MemberRulesTest extends TestCase
{
    public function test_name_accepts_valid_and_rejects_blank_or_too_long(): void
    {
        $this->assertFalse(Validator::make(['name' => 'Jane Doe'], ['name' => MemberRules::name()])->fails());
        $this->assertTrue(Validator::make(['name' => ''], ['name' => MemberRules::name()])->fails());
        $this->assertTrue(Validator::make(['name' => str_repeat('a', 61)], ['name' => MemberRules::name()])->fails());
    }

    public function test_assigned_password_requires_mixed_case_number_and_symbol(): void
    {
        $this->assertFalse(Validator::make(['password' => 'Customer@123'], ['password' => MemberRules::assignedPassword()])->fails());
        $this->assertTrue(Validator::make(['password' => 'Vgg12345'], ['password' => MemberRules::assignedPassword()])->fails());
        $this->assertTrue(Validator::make(['password' => 'short1!'], ['password' => MemberRules::assignedPassword()])->fails());
        $this->assertTrue(Validator::make(['password' => 'alllowercase1!'], ['password' => MemberRules::assignedPassword()])->fails());
    }
}
