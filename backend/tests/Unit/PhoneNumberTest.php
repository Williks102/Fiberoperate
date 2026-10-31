<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_numbers_are_normalized_to_plus_225_and_ten_digits_and_every_correction_is_reported(): void
    {
        $cases = [
            ['0707123456', '+2250707123456', null],
            ['07 07 12 34 56', '+2250707123456', null],
            ['*0707123456', '+2250707123456', 'Préfixe « * » retiré.'],
            ['+225 07 07 12 34 56', '+2250707123456', null],
            ['002250707123456', '+2250707123456', null],
            ['2250707123456', '+2250707123456', null],
            ['225707123456', '+2250707123456', 'Zéro initial ajouté (numéro à 9 chiffres).'],
            [707123456, '+2250707123456', 'Zéro initial ajouté (numéro à 9 chiffres).'],
            ['707123456.0', '+2250707123456', 'Zéro initial ajouté (numéro à 9 chiffres).'],
            [707123456.0, '+2250707123456', 'Zéro initial ajouté (numéro à 9 chiffres).'],
            [212345678901.0, null, 'Longueur anormale (12 chiffres) : numéro à vérifier.'],
        ];

        foreach ($cases as [$raw, $normalized, $note]) {
            $result = PhoneNumber::normalize($raw);
            $this->assertSame($normalized, $result['normalized'], 'numéro : '.var_export($raw, true));
            $this->assertSame($note, $result['note'], 'note : '.var_export($raw, true));
        }
    }

    public function test_empty_values_give_nothing_without_a_note(): void
    {
        foreach ([null, '', '   ', '*'] as $raw) {
            $this->assertSame(['normalized' => null, 'note' => null], PhoneNumber::normalize($raw));
        }
        $this->assertSame('Longueur anormale (5 chiffres) : numéro à vérifier.', PhoneNumber::normalize('07123')['note']);
    }
}
