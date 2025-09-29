<?php

namespace App\Service;

use App\Entity\User;

class LoyaltyPointsService
{
    public const POINT_VALUE = 0.01; // 1 point = 0.01 currency units

    /**
     * Calculate how many points can be applied and what discount that yields.
     * Caps by user's balance and by the maximum amount allowed (in currency units).
     *
     * @return array{appliedPoints:int, discount:string}
     */
    public function calculateRedemption(int $userBalance, int $requestedPoints, float $maxAmount): array
    {
        $requestedPoints = max(0, (int)$requestedPoints);
        $userBalance = max(0, (int)$userBalance);

        $capByBalance = min($requestedPoints, $userBalance);
        $capByAmount = (int) floor($maxAmount / self::POINT_VALUE);
        $applied = max(0, min($capByBalance, $capByAmount));

        $discount = number_format($applied * self::POINT_VALUE, 2, '.', '');
        return [
            'appliedPoints' => $applied,
            'discount' => $discount,
        ];
    }
}
