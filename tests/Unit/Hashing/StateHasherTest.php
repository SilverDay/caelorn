<?php

declare(strict_types=1);

namespace Caelorn\Tests\Unit\Hashing;

use Caelorn\Domain\Hashing\StateHasher;
use PHPUnit\Framework\TestCase;

final class StateHasherTest extends TestCase
{
    public function test_same_state_hashes_identically_regardless_of_key_order(): void
    {
        $hashA = StateHasher::hash(['turn' => 1, 'planets' => []]);
        $hashB = StateHasher::hash(['planets' => [], 'turn' => 1]);

        self::assertSame($hashA, $hashB);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hashA);
    }

    public function test_different_state_hashes_differently(): void
    {
        $hashA = StateHasher::hash(['turn' => 1]);
        $hashB = StateHasher::hash(['turn' => 2]);

        self::assertNotSame($hashA, $hashB);
    }
}
