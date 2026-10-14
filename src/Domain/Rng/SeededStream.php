<?php

declare(strict_types=1);

namespace Caelorn\Domain\Rng;

/**
 * A deterministic, domain-separated pseudorandom byte stream for one
 * (seed, turn, phase, event key) tuple (spec §5.4). Keying each stream's
 * HMAC on the secret seed, with the turn/phase/event key folded into the
 * message, means two streams with different keys are independent and the
 * same tuple always reproduces the same sequence — the properties the host
 * needs, without depending on iteration order.
 *
 * This is a counter-mode HMAC-SHA256 stream, not a certified NIST SP 800-90A
 * HMAC_DRBG implementation; it satisfies the spec's determinism and
 * domain-separation requirements without claiming that certification.
 */
final class SeededStream
{
    private int $counter = 0;
    private string $buffer = '';

    public function __construct(
        private readonly string $seed,
        private readonly int $turnNumber,
        private readonly string $phase,
        private readonly string $eventKey,
    ) {}

    public function bytes(int $length): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Requested byte length must be positive.');
        }

        while (strlen($this->buffer) < $length) {
            $this->buffer .= $this->nextBlock();
        }

        $out = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);

        return $out;
    }

    /** Uniformly distributed integer in [0, $exclusiveUpperBound), via rejection sampling. */
    public function uniformInt(int $exclusiveUpperBound): int
    {
        if ($exclusiveUpperBound < 1) {
            throw new \InvalidArgumentException('Upper bound must be positive.');
        }

        if ($exclusiveUpperBound === 1) {
            return 0;
        }

        $byteLength = (int) ceil(log($exclusiveUpperBound, 2) / 8);
        $limit = 256 ** $byteLength;
        $threshold = $limit - ($limit % $exclusiveUpperBound);

        do {
            $value = 0;
            foreach (str_split($this->bytes($byteLength)) as $byte) {
                $value = ($value << 8) | ord($byte);
            }
        } while ($value >= $threshold);

        return $value % $exclusiveUpperBound;
    }

    private function nextBlock(): string
    {
        $message = sprintf('%d|%s|%s|%d', $this->turnNumber, $this->phase, $this->eventKey, $this->counter);
        ++$this->counter;

        return hash_hmac('sha256', $message, $this->seed, true);
    }
}
