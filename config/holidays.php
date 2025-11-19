<?php
// Central holiday configuration for calendar display
// Returns an array of [date => ['title' => ..., 'type' => 'regular'|'special']]
function getHolidays($year) {
    // Fixed-date Philippines holidays with types
    $fixed = [
        sprintf('%d-01-01', $year) => ['title' => "New Year's Day", 'type' => 'regular'],
        sprintf('%d-04-09', $year) => ['title' => 'Araw ng Kagitingan', 'type' => 'regular'],
        sprintf('%d-05-01', $year) => ['title' => 'Labor Day', 'type' => 'regular'],
        sprintf('%d-06-12', $year) => ['title' => 'Independence Day', 'type' => 'regular'],
        sprintf('%d-08-21', $year) => ['title' => 'Ninoy Aquino Day', 'type' => 'special'],
        sprintf('%d-11-01', $year) => ['title' => "All Saints' Day", 'type' => 'special'],
        sprintf('%d-11-02', $year) => ['title' => "All Souls' Day", 'type' => 'special'],
        sprintf('%d-11-30', $year) => ['title' => 'Bonifacio Day', 'type' => 'regular'],
        sprintf('%d-12-08', $year) => ['title' => 'Feast of the Immaculate Conception of Mary', 'type' => 'special'],
        sprintf('%d-12-24', $year) => ['title' => 'Christmas Eve', 'type' => 'special'],
        sprintf('%d-12-25', $year) => ['title' => 'Christmas Day', 'type' => 'regular'],
        sprintf('%d-12-30', $year) => ['title' => 'Rizal Day', 'type' => 'regular'],
        sprintf('%d-12-31', $year) => ['title' => 'Last Day of the Year', 'type' => 'special'],
        sprintf('%d-02-25', $year) => ['title' => 'EDSA People Power Anniversary', 'type' => 'special'],
    ];

    // Computed movable holidays
    $computed = [];

    // National Heroes Day: last Monday of August
    $lastDayAug = new DateTime(sprintf('%d-08-31', $year));
    while ((int)$lastDayAug->format('N') !== 1) { // 1 = Monday
        $lastDayAug->modify('-1 day');
    }
    $computed[$lastDayAug->format('Y-m-d')] = ['title' => 'National Heroes Day', 'type' => 'regular'];

    // Holy Week based on Easter Sunday
    $easter = easter_date($year); // timestamp
    $easterDate = (new DateTime('@'.$easter))->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $maundyThursday = (clone $easterDate)->modify('-3 days');
    $goodFriday     = (clone $easterDate)->modify('-2 days');
    $blackSaturday  = (clone $easterDate)->modify('-1 day');
    $computed[$maundyThursday->format('Y-m-d')] = ['title' => 'Maundy Thursday', 'type' => 'regular'];
    $computed[$goodFriday->format('Y-m-d')]     = ['title' => 'Good Friday', 'type' => 'regular'];
    $computed[$blackSaturday->format('Y-m-d')]  = ['title' => 'Black Saturday', 'type' => 'special'];

    // Year-specific overrides (e.g., Chinese New Year, additional specials)
    $overrides = [];
    if ($year === 2024) {
        $overrides['2024-02-10'] = ['title' => 'Chinese New Year', 'type' => 'special'];
    } elseif ($year === 2025) {
        $overrides['2025-01-29'] = ['title' => 'Chinese New Year', 'type' => 'special'];
    } elseif ($year === 2026) {
        $overrides['2026-02-17'] = ['title' => 'Chinese New Year', 'type' => 'special'];
    }

    // Note: Islamic holidays (Eid al-Fitr/Eid al-Adha) vary yearly and depend on proclamations.
    // Add them to $overrides when official dates are available.

    return $fixed + $computed + $overrides;
}
