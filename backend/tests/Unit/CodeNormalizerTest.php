<?php

namespace Tests\Unit;

use App\Support\CodeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CodeNormalizerTest extends TestCase
{
    #[DataProvider('zones')]
    public function test_zone(string $input, string $expected): void
    {
        $this->assertSame($expected, CodeNormalizer::zone($input));
    }

    public static function zones(): array
    {
        return [
            'zéros manquants' => ['FAY03', 'FAY003'],
            'déjà normalisé' => ['FAY003', 'FAY003'],
            'espace' => ['AGC 01', 'AGC001'],
            'minuscules' => ['aca03', 'ACA003'],
            'tiret' => ['ACA-003', 'ACA003'],
        ];
    }

    #[DataProvider('pcos')]
    public function test_pco(string $input, string $expected): void
    {
        $this->assertSame($expected, CodeNormalizer::pco($input));
    }

    public static function pcos(): array
    {
        return [
            'tiret et un zéro' => ['ACA03_PCO-001', 'ACA003_PCO001'],
            'tiret et deux zéros' => ['ACA003_PCO-001', 'ACA003_PCO001'],
            'sans tiret' => ['FAY03_PCO001', 'FAY003_PCO001'],
            'numéro sans zéros' => ['FAY03_PCO1', 'FAY003_PCO001'],
            'format inconnu' => ['xyz-12', 'XYZ12'],
        ];
    }

    public function test_generic_handles_null_and_spacing(): void
    {
        $this->assertSame('', CodeNormalizer::generic(null));
        $this->assertSame('SPL001', CodeNormalizer::generic(' spl 001 '));
    }
}
