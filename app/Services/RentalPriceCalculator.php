<?php

namespace App\Services;

use App\Models\Property;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calculates rental totals from a stay length using accepted price_per_* rates.
 *
 * Only units checked in rent_duration_units are used.
 * Example: if days/weeks are unchecked, stays must fit months/years exactly.
 */
class RentalPriceCalculator
{
    public const UNIT_ORDER = ['year', 'month', 'week', 'day'];

    /**
     * @return array{
     *   nights: int,
     *   total: float,
     *   primary_unit: string,
     *   allowed_units: list<string>,
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
        $allowed = $this->acceptedUnits($property);
        $rates = $this->ratesFor($property);
        $primary = $this->normalizeUnit($property->price_duration ?? 'month');

        $decomposition = $this->decomposeStayWithRemainder($start, $end, $allowed);
        $counts = $decomposition['counts'];
        $leftoverDays = $decomposition['leftover_days'];

        if ($leftoverDays > 0) {
            $labels = implode(', ', $allowed);
            throw new \InvalidArgumentException(
                "This stay does not fit the landlord's accepted rent durations ({$labels}). "
                . 'Please choose check-in/check-out dates that match whole accepted units only.'
            );
        }

        if (array_sum($counts) === 0) {
            throw new \InvalidArgumentException('Unable to calculate rental price for the selected dates.');
        }

        $units = [];
        $total = 0.0;

        foreach (self::UNIT_ORDER as $unit) {
            $count = (int) ($counts[$unit] ?? 0);
            $rate = in_array($unit, $allowed, true) ? (float) ($rates[$unit] ?? 0) : 0.0;
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
            'allowed_units' => $allowed,
            'rates' => $rates,
            'units' => $units,
            'label' => $this->formatLabel($units),
        ];
    }

    /**
     * @return list<string>
     */
    public function acceptedUnits(Property $property): array
    {
        $raw = $property->rent_duration_units;
        $units = [];

        if (is_array($raw)) {
            $units = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $units = array_filter(array_map('trim', explode(',', $raw)));
        }

        $units = array_values(array_intersect(self::UNIT_ORDER, array_map(
            fn ($unit) => $this->normalizeUnit((string) $unit),
            $units
        )));

        // Legacy fallback: if nothing stored, derive from primary duration.
        if ($units === []) {
            $units = $this->unitsFromPrimary($property->price_duration ?? 'month');
        }

        // Drop units that are explicitly zeroed (not accepted).
        $rates = $this->rawRatesFor($property);
        $units = array_values(array_filter(
            $units,
            fn (string $unit) => ($rates[$unit] ?? 0) > 0 || $unit === $this->normalizeUnit($property->price_duration ?? 'month')
        ));

        if ($units === []) {
            $units = [$this->normalizeUnit($property->price_duration ?? 'month')];
        }

        return $units;
    }

    /**
     * @return array{day: float, week: float, month: float, year: float}
     */
    public function ratesFor(Property $property): array
    {
        $raw = $this->rawRatesFor($property);
        $allowed = $this->acceptedUnits($property);

        return [
            'day' => in_array('day', $allowed, true) ? $raw['day'] : 0.0,
            'week' => in_array('week', $allowed, true) ? $raw['week'] : 0.0,
            'month' => in_array('month', $allowed, true) ? $raw['month'] : 0.0,
            'year' => in_array('year', $allowed, true) ? $raw['year'] : 0.0,
        ];
    }

    /**
     * @return array{day: float, week: float, month: float, year: float}
     */
    protected function rawRatesFor(Property $property): array
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
     * @return array{counts: array{year: int, month: int, week: int, day: int}, leftover_days: int}
     */
    public function decomposeStayWithRemainder(CarbonInterface $start, CarbonInterface $end, array $allowed): array
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

        $leftoverDays = max(0, (int) $cursor->diffInDays($endDay));

        if (in_array('day', $allowed, true)) {
            $counts['day'] = $leftoverDays;
            $leftoverDays = 0;
        }

        return [
            'counts' => $counts,
            'leftover_days' => $leftoverDays,
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @return array{year: int, month: int, week: int, day: int}
     */
    public function decomposeStay(CarbonInterface $start, CarbonInterface $end, array $allowed): array
    {
        return $this->decomposeStayWithRemainder($start, $end, $allowed)['counts'];
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
