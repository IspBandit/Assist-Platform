<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredFails(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function testEmailRule(): void
    {
        $this->assertTrue(Validator::make(['e' => 'not-an-email'], ['e' => 'email'])->fails());
        $this->assertTrue(Validator::make(['e' => 'a@b.com'], ['e' => 'email'])->passes());
    }

    public function testMinAndMax(): void
    {
        $this->assertTrue(Validator::make(['p' => 'short'], ['p' => 'min:10'])->fails());
        $this->assertTrue(Validator::make(['p' => 'a-long-enough-value'], ['p' => 'min:10|max:50'])->passes());
    }

    public function testAccepted(): void
    {
        $this->assertTrue(Validator::make(['t' => null], ['t' => 'accepted'])->fails());
        $this->assertTrue(Validator::make(['t' => '1'], ['t' => 'accepted'])->passes());
    }

    public function testMatches(): void
    {
        $data = ['password' => 'secret1234', 'password_confirmation' => 'secret1234'];
        $this->assertTrue(Validator::make($data, ['password_confirmation' => 'matches:password'])->passes());

        $bad = ['password' => 'a', 'password_confirmation' => 'b'];
        $this->assertTrue(Validator::make($bad, ['password_confirmation' => 'matches:password'])->fails());
    }
}
