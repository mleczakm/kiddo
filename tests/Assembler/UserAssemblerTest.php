<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use PHPUnit\Framework\TestCase;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;

class UserAssemblerTest extends TestCase
{
    public function testAssembleWithoutPhone(): void
    {
        $user = UserAssembler::new()
            ->withEmail('test@example.com')
            ->withName('Test User')
            ->assemble();
        $this->assertNull($user->getPhone());
    }

    public function testAssembleWithValidPhone(): void
    {
        $user = UserAssembler::new()
            ->withEmail('test2@example.com')
            ->withName('Test User')
            ->withPhone('+48 123 456 789')
            ->assemble();
        $this->assertNotNull($user->getPhone());
        $phoneUtil = PhoneNumberUtil::getInstance();
        $this->assertSame('+48123456789', $phoneUtil->format($user->getPhone(), PhoneNumberFormat::E164));
    }

    public function testAssembleWithInvalidPhone(): void
    {
        $user = UserAssembler::new()
            ->withEmail('test3@example.com')
            ->withName('Test User')
            ->withPhone('invalid-phone')
            ->assemble();
        $this->assertNull($user->getPhone());
    }

    public function testAssembleWithVariousPhoneFormats(): void
    {
        $user = UserAssembler::new()
            ->withEmail('test4@example.com')
            ->withName('Test User')
            ->withPhone('123-456-789')
            ->assemble();
        $this->assertNotNull($user->getPhone());
        $phoneUtil = PhoneNumberUtil::getInstance();
        $this->assertSame('+48123456789', $phoneUtil->format($user->getPhone(), PhoneNumberFormat::E164));
    }
}
