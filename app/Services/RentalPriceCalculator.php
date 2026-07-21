<?php

namespace App\Services;

use App\Models\Property;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calculates rental totals from a stay length using price_per_* rates.
 *
 * Breakdown starts from the listing's primary unit (price_duration):
 * - day  → days only
 * - week → weeks + leftover days
 * - month → months + weeks + leftover days
 * - year → years + months + weeks + leftover days
 */
class RentalPriceCalculator
{
    public const UNIT_ORDER = ['year', 'month', 'week', 'day'];

    /**
     * @return array{
     *   nights: int,
     *   total: float,
     *   primary_unit: string,
     *   rates: array{day: float, week: float, month: float, year: float},
     *   units: array<string, array{count: int, rate: float, subtotal: float}>,
     *   label: string
     * }
     */
    public function calculate(Property $property, CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lte($start)) {
            throw new \InvalidArgumentException('Check-out date must be after check-in date.');
        }

        $nights = (int) $start->diffInDays($end);
        $rates = $this->ratesFor($property);
        $primary = $this->normalizeUnit($property->price_duration ?? 'month');
        $allowed = $this->unitsFromPrimary($primary);

        $counts = $this->decomposeStay($start, $end, $allowed);

        $units = [];
        $total = 0.0;

        foreach (self::UNIT_ORDER as $unit) {
            $count = (int) ($counts[$unit] ?? 0);
            $rate = (float) ($rates[$unit] ?? 0);
            $subtotal = round($count * $rate, 2);
            $units[$unit] = [
                'count' => $count,
                'rate' => $rate,
                'subtotal' => $subtotal,
            ];
            $total += $subtotal;
        }

        $total = round($total, 2);

        return [
            'nights' => $nights,
            'total' => $total,
            'primary_unit' => $primary,
            'rates' => $rates,
            'units' => $units,
            'label' => $this->formatLabel($units),
        ];
    }

    /**
     * @return array{day: float, week: float, month: float, year: float}
     */
    public function ratesFor(Property $property): array
    {
        $dayFactors = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365];
        $baseUnit = $this->normalizeUnit($property->price_duration ?? 'month');
        $basePrice = (float) ($property->price ?? 0);
        $divisor = $dayFactors[$baseUnit] ?? 30;
        $perDay = $divisor > 0 ? ($basePrice / $divisor) : 0.0;

        return [
            'day' => round((float) ($property->price_per_day ?? $perDay), 2),
            'week' => round((float) ($property->price_per_week ?? ($perDay * 7)), 2),
            'month' => round((float) ($property->price_per_month ?? ($perDay * 30)), 2),
            'year' => round((float) ($property->price_per_year ?? ($perDay * 365)), 2),
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @return array{year: int, month: int, week: int, day: int}
     */
    public function decomposeStay(CarbonInterface $start, CarbonInterface $end, array $allowed): array
    {
        $counts = ['year' => 0, 'month' => 0, 'week' => 0, 'day' => 0];
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        foreach (['year', 'month', 'week'] as $unit) {
            if (!in_array($unit, $allowed, true)) {
                continue;
            }

            while (true) {
                $next = $cursor->copy();
                match ($unit) {
                    'year' => $next->addYear(),
                    'month' => $next->addMonth(),
                    'week' => $next->addWeek(),
                };

                if ($next->gt($endDay)) {
                    break;
                }

                $cursor = $next;
                $counts[$unit]++;
            }
        }

        if (in_array('day', $allowed, true)) {
            $counts['day'] = max(0, (int) $cursor->diffInDays($endDay));
        }

        // Safety: if nothing was counted (e.g. odd config), charge remaining nights as days.
        if (array_sum($counts) === 0) {
            $counts['day'] = max(0, (int) $start->diffInDays($end));
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    public function unitsFromPrimary(string $primary): array
    {
        return match ($this->normalizeUnit($primary)) {
            'day' => ['day'],
            'week' => ['week', 'day'],
            'month' => ['month', 'week', 'day'],
            default => ['year', 'month', 'week', 'day'],
        };
    }

    /**
     * @param  array<string, array{count: int, rate?: float, subtotal?: float}>  $units
     */
    public function formatLabel(array $units): string
    {
        $parts = [];

        foreach (self::UNIT_ORDER as $unit) {
            $count = (int) ($units[$unit]['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $label = $unit . ($count === 1 ? '' : 's');
            $parts[] = $count . ' ' . $label;
        }

        return $parts === [] ? '0 days' : implode(' + ', $parts);
    }

    public function normalizeUnit(?string $unit): string
    {
        $unit = strtolower(trim((string) $unit));

        return in_array($unit, self::UNIT_ORDER, true) ? $unit : 'month';
    }
}
