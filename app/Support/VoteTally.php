<?php

namespace App\Support;

/**
 * Pure decision rule for a report vote (no database, easy to test).
 *
 * Plurality wins. Too few votes, or a tie for first place, means "no_consensus"
 * and nobody is punished.
 */
final class VoteTally
{
    public const AGREE = 'agree';

    public const FALSE_REPORT = 'false_report';

    public const WRONG_TAG = 'wrong_tag';

    public const CHOICES = [self::AGREE, self::FALSE_REPORT, self::WRONG_TAG];

    public const UPHELD = 'upheld';

    public const NO_CONSENSUS = 'no_consensus';

    /**
     * @param  array<string, int>  $counts  e.g. ['agree' => 3, 'false_report' => 1]
     */
    public static function decide(array $counts, int $minVotes): string
    {
        $tally = [];
        foreach (self::CHOICES as $choice) {
            $tally[$choice] = (int) ($counts[$choice] ?? 0);
        }

        if (array_sum($tally) < $minVotes) {
            return self::NO_CONSENSUS;
        }

        arsort($tally);
        $values = array_values($tally);

        if ($values[0] === $values[1]) {
            return self::NO_CONSENSUS;
        }

        return match (array_key_first($tally)) {
            self::AGREE => self::UPHELD,
            self::FALSE_REPORT => self::FALSE_REPORT,
            self::WRONG_TAG => self::WRONG_TAG,
        };
    }
}
