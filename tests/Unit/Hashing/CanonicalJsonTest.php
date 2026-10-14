<?php

declare(strict_types=1);

namespace Caelorn\Tests\Unit\Hashing;

use Caelorn\Domain\Hashing\CanonicalJson;
use PHPUnit\Framework\TestCase;

final class CanonicalJsonTest extends TestCase
{
    public function test_object_keys_are_sorted_regardless_of_input_order(): void
    {
        $a = CanonicalJson::encode(['b' => 1, 'a' => 2]);
        $b = CanonicalJson::encode(['a' => 2, 'b' => 1]);

        self::assertSame($a, $b);
        self::assertSame('{"a":2,"b":1}', $a);
    }

    public function test_list_order_is_preserved(): void
    {
        self::assertSame('[3,1,2]', CanonicalJson::encode([3, 1, 2]));
    }

    public function test_nested_structures_are_normalized(): void
    {
        $value = ['z' => ['y' => 1, 'x' => 2], 'a' => [1, 2, 3]];

        self::assertSame('{"a":[1,2,3],"z":{"x":2,"y":1}}', CanonicalJson::encode($value));
    }

    public function test_floats_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CanonicalJson::encode(['x' => 1.5]);
    }
}
