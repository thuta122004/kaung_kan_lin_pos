<?php
require_once __DIR__ . '/dbconfig.php';

const EXPENSE_CATEGORIES = ['Raw Materials', 'Packaging', 'Marketing', 'Utilities', 'Salaries', 'Other'];
const INCOME_SOURCES     = ['Investment', 'Partner Capital', 'Grant', 'Other'];
const PERIOD_RANGES      = ['all' => 'All Time', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month'];

function period_from_request(): string
{
    $r = $_GET['range'] ?? $_POST['range'] ?? '';
    return is_string($r) && array_key_exists($r, PERIOD_RANGES) ? $r : 'all';
}

function period_condition(string $range, string $col): string
{
    switch ($range) {
        case 'today':
            return "DATE($col) = CURDATE()";
        case 'week':
            return "YEARWEEK($col, 1) = YEARWEEK(CURDATE(), 1)";
        case 'month':
            return "YEAR($col) = YEAR(CURDATE()) AND MONTH($col) = MONTH(CURDATE())";
        default:
            return '1=1';
    }
}

function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function parse_amount(string $raw): ?string
{
    $s = str_replace([',', ' '], '', trim($raw));
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $s)) {
        return null;
    }
    return (float)$s > 0 ? $s : null;
}

function parse_datetime_input(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return date('Y-m-d H:i:s');
    }
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d'] as $f) {
        $d = DateTime::createFromFormat('!' . $f, $raw);
        $err = DateTime::getLastErrors();
        if ($d && (!$err || ($err['warning_count'] === 0 && $err['error_count'] === 0))) {
            return $d->format('Y-m-d H:i:s');
        }
    }
    return null;
}

function money_round(float $n): float
{
    $n = round($n, 2);
    return $n == 0.0 ? 0.0 : $n;
}

function expense_badge(string $c): string
{
    $map = [
        'Raw Materials' => ['fa-solid fa-cubes', 'bg-amber-50 text-amber-800 border-amber-200'],
        'Packaging'     => ['fa-solid fa-box', 'bg-sky-50 text-sky-700 border-sky-200'],
        'Marketing'     => ['fa-solid fa-bullhorn', 'bg-purple-50 text-purple-700 border-purple-200'],
        'Utilities'     => ['fa-solid fa-bolt', 'bg-teal-50 text-teal-700 border-teal-200'],
        'Salaries'      => ['fa-solid fa-user-group', 'bg-orange-50 text-orange-700 border-orange-200'],
        'Other'         => ['fa-solid fa-ellipsis', 'bg-gray-50 text-gray-700 border-gray-200'],
    ];
    [$icon, $cls] = $map[$c] ?? ['fa-solid fa-circle', 'bg-gray-50 text-gray-700 border-gray-200'];
    return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border whitespace-nowrap ' . $cls . '"><i class="' . $icon . '"></i>' . e($c) . '</span>';
}

function source_badge(string $s): string
{
    $map = [
        'Investment'      => ['fa-solid fa-chart-line', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'Partner Capital' => ['fa-solid fa-handshake', 'bg-sky-50 text-sky-700 border-sky-200'],
        'Grant'           => ['fa-solid fa-award', 'bg-amber-50 text-amber-800 border-amber-200'],
        'Other'           => ['fa-solid fa-ellipsis', 'bg-gray-50 text-gray-700 border-gray-200'],
    ];
    [$icon, $cls] = $map[$s] ?? ['fa-solid fa-circle', 'bg-gray-50 text-gray-700 border-gray-200'];
    return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border whitespace-nowrap ' . $cls . '"><i class="' . $icon . '"></i>' . e($s) . '</span>';
}
