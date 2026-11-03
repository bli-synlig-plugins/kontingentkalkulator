<?php

if (!defined('ABSPATH')) {
    exit;
}

class KK_Calc
{
    /**
     * @param array{mode?: string, monthly?: mixed, hourly?: mixed, hours?: mixed, sats: float, fradragssats: float, arsgrense: float, insurance?: float} $input
     * @return array{salary: float, dues: float, deduction: float, insurance: float, reell: float, yearly: float, hourly: float|null, hours_month: float|null}|null
     */
    public static function compute(array $input): ?array
    {
        $mode = ($input['mode'] ?? 'monthly') === 'hourly' ? 'hourly' : 'monthly';
        $sats = (float) $input['sats'];
        $fradragssats = (float) $input['fradragssats'];
        $arsgrense = max(0, (float) $input['arsgrense']);
        $insurance = max(0, (float) ($input['insurance'] ?? 0));

        $hoursMonth = null;
        if ($mode === 'hourly') {
            $hourly = self::number($input['hourly'] ?? null);
            $hours = self::number($input['hours'] ?? null);
            if ($hourly === null || $hours === null || $hourly <= 0 || $hours <= 0) {
                return null;
            }
            $hoursMonth = $hours * 52 / 12;
            $salary = $hourly * $hoursMonth;
        } else {
            $salary = self::number($input['monthly'] ?? null);
            if ($salary === null || $salary <= 0) {
                return null;
            }
        }

        $dues = $salary * $sats;
        $yearlyDues = $dues * 12;
        $yearlyDeduction = min($yearlyDues, $arsgrense) * $fradragssats;
        $reell = ($yearlyDues - $yearlyDeduction) / 12 - $insurance;

        return array(
            'salary' => $salary,
            'dues' => $dues,
            'deduction' => $yearlyDeduction / 12,
            'insurance' => $insurance,
            'reell' => $reell,
            'yearly' => $reell * 12,
            'hourly' => ($hoursMonth && $hoursMonth > 0) ? $reell / $hoursMonth : null,
            'hours_month' => $hoursMonth,
        );
    }

    public static function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $raw = trim((string) $value);
        $raw = str_replace(array("\xC2\xA0", ' '), '', $raw);
        $raw = str_replace(',', '.', $raw);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        $number = (float) $raw;

        return is_finite($number) ? $number : null;
    }

    public static function format(mixed $value, int $decimals = 0, bool $emptyDash = true): string
    {
        $number = is_float($value) || is_int($value) ? (float) $value : self::number($value);
        if ($number === null) {
            return $emptyDash ? '–' : '';
        }

        return number_format($number, $decimals, ',', ' ');
    }

    public static function format_percent(float $value): string
    {
        $decimals = 0;
        $scaled = $value;
        while ($decimals < 2 && abs($scaled - round($scaled)) >= 0.05) {
            $scaled *= 10;
            $decimals++;
        }

        return self::format($value, $decimals, false);
    }

    public static function format_signed(mixed $value, int $decimals = 2): string
    {
        $number = is_float($value) || is_int($value) ? (float) $value : self::number($value);
        if ($number === null) {
            return '–';
        }

        $formatted = self::format(abs($number), $decimals);

        return $number > 0 ? '−' . $formatted : $formatted;
    }
}
