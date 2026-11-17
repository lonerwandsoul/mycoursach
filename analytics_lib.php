<?php
/** Общие функции аналитического модуля: метрики, графики, формы, защита. */

function one(string $sql, array $params = []): ?array
{
    global $pdo;
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function an_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function an_money($value, bool $short = false): string
{
    $value = (float) $value;
    if ($short && abs($value) >= 1000000) {
        return number_format($value / 1000000, 1, ',', ' ') . ' млн ₽';
    }
    return number_format($value, 0, ',', ' ') . ' ₽';
}

function an_num($value, int $decimals = 0): string
{
    return number_format((float) $value, $decimals, ',', ' ');
}

function an_month_label(string $period): string
{
    $months = [1 => 'Янв', 2 => 'Фев', 3 => 'Мар', 4 => 'Апр', 5 => 'Май', 6 => 'Июн',
        7 => 'Июл', 8 => 'Авг', 9 => 'Сен', 10 => 'Окт', 11 => 'Ноя', 12 => 'Дек'];
    [$year, $month] = explode('-', $period);
    return $months[(int) $month] ?? $period;
}

function an_periods(PDO $pdo): array
{
    $rows = $pdo->query('SELECT DISTINCT period FROM an_production ORDER BY period')->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: [];
}

function an_selected_period(PDO $pdo): string
{
    $periods = an_periods($pdo);
    $requested = (string) ($_GET['period'] ?? '');
    if (in_array($requested, $periods, true)) {
        return $requested;
    }
    return $periods ? end($periods) : date('Y-m');
}

/** Сводные значения по месяцам: объём, брак, часы, выручка, заказы. */
function an_monthly(PDO $pdo): array
{
    $production = $pdo->query('SELECT period, SUM(qty) AS qty, SUM(defects) AS defects, SUM(hours) AS hours, SUM(cost) AS cost
        FROM an_production GROUP BY period ORDER BY period')->fetchAll();
    $sales = $pdo->query('SELECT period, SUM(revenue) AS revenue, SUM(orders) AS orders
        FROM an_sales GROUP BY period ORDER BY period')->fetchAll();
    $salesByPeriod = [];
    foreach ($sales as $row) {
        $salesByPeriod[$row['period']] = $row;
    }
    $capacity = (float) an_target($pdo, 'capacity', 5400);
    $result = [];
    foreach ($production as $row) {
        $period = $row['period'];
        $sale = $salesByPeriod[$period] ?? ['revenue' => 0, 'orders' => 0];
        $qty = (int) $row['qty'];
        $defects = (int) $row['defects'];
        $result[] = [
            'period' => $period,
            'label' => an_month_label($period),
            'qty' => $qty,
            'defects' => $defects,
            'defect_rate' => $qty > 0 ? $defects / $qty * 100 : 0,
            'hours' => (float) $row['hours'],
            'load' => $capacity > 0 ? (float) $row['hours'] / $capacity * 100 : 0,
            'cost' => (float) $row['cost'],
            'revenue' => (float) $sale['revenue'],
            'orders' => (int) $sale['orders'],
            'profit' => (float) $sale['revenue'] - (float) $row['cost'],
        ];
    }
    return $result;
}

function an_target(PDO $pdo, string $metric, float $default = 0): float
{
    $statement = $pdo->prepare('SELECT target_value FROM an_targets WHERE metric = ?');
    $statement->execute([$metric]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (float) $value;
}

function scalar_orders(PDO $pdo, ?string $period = null): int
{
    $statement = $period === null
        ? $pdo->prepare("SELECT COUNT(*) FROM an_orders WHERE status IN ('new','in_progress')")
        : $pdo->prepare('SELECT COUNT(*) FROM an_orders WHERE period = ?');
    $statement->execute($period === null ? [] : [$period]);
    return (int) $statement->fetchColumn();
}

function scalar_defect_cost(PDO $pdo, string $period): float
{
    $statement = $pdo->prepare('SELECT COALESCE(SUM(cost),0) FROM an_defects WHERE period = ?');
    $statement->execute([$period]);
    return (float) $statement->fetchColumn();
}

function an_kpi(PDO $pdo, string $period): array
{
    $monthly = an_monthly($pdo);
    $row = null;
    foreach ($monthly as $item) {
        if ($item['period'] === $period) {
            $row = $item;
            break;
        }
    }
    if ($row === null) {
        $row = ['qty' => 0, 'defects' => 0, 'defect_rate' => 0, 'load' => 0, 'revenue' => 0,
            'orders' => 0, 'profit' => 0, 'cost' => 0, 'hours' => 0];
    }
    $row['avg_check'] = $row['orders'] > 0 ? $row['revenue'] / $row['orders'] : 0;
    $row['margin'] = $row['revenue'] > 0 ? $row['profit'] / $row['revenue'] * 100 : 0;
    return $row;
}

/** Предыдущий месяц для сравнения «к прошлому периоду». */
function an_previous(array $monthly, string $period): ?array
{
    $previous = null;
    foreach ($monthly as $item) {
        if ($item['period'] === $period) {
            return $previous;
        }
        $previous = $item;
    }
    return null;
}

function an_delta(?float $current, ?float $previous): array
{
    if ($previous === null || $previous == 0.0) {
        return ['text' => '—', 'class' => 'flat'];
    }
    $diff = ($current - $previous) / $previous * 100;
    return [
        'text' => ($diff >= 0 ? '+' : '') . number_format($diff, 1, ',', ' ') . '%',
        'class' => $diff > 0.5 ? 'up' : ($diff < -0.5 ? 'down' : 'flat'),
    ];
}


/* ------------------------------------------------------------------ роли */

function an_current_role(): string
{
    return (string) ($_SESSION['role'] ?? 'manager');
}

function an_is_admin(): bool
{
    return an_current_role() === 'admin';
}

/* ------------------------------------------------------------------ аналитика: топ, прогноз, инсайты */

function an_top_products(PDO $pdo, string $period, int $limit = 5): array
{
    $statement = $pdo->prepare('SELECT product, COUNT(*) AS orders, SUM(amount) AS revenue
        FROM an_orders WHERE period = ? GROUP BY product ORDER BY revenue DESC LIMIT ' . (int) $limit);
    $statement->execute([$period]);
    return $statement->fetchAll();
}

function an_channel_structure(PDO $pdo, string $period): array
{
    $statement = $pdo->prepare('SELECT channel, SUM(revenue) AS revenue FROM an_sales WHERE period = ? GROUP BY channel ORDER BY revenue DESC');
    $statement->execute([$period]);
    return $statement->fetchAll();
}

/** Линейный прогноз следующего месяца по историческим точкам. */
function an_forecast(array $monthly, string $field = 'revenue'): ?float
{
    $count = count($monthly);
    if ($count < 3) {
        return null;
    }
    $x = range(1, $count);
    $y = array_map(static fn ($row) => (float) $row[$field], $monthly);
    $n = $count;
    $sumX = array_sum($x);
    $sumY = array_sum($y);
    $sumXY = 0.0;
    $sumXX = 0.0;
    foreach ($x as $index => $value) {
        $sumXY += $value * $y[$index];
        $sumXX += $value * $value;
    }
    $denominator = $n * $sumXX - $sumX * $sumX;
    if (abs($denominator) < 1e-9) {
        return null;
    }
    $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
    $intercept = ($sumY - $slope * $sumX) / $n;
    return $slope * ($n + 1) + $intercept;
}

/** Автоматические выводы по данным — то, что показывает руководителю. */
function an_insights(PDO $pdo, array $monthly, array $kpi, ?array $previous): array
{
    $insights = [];
    if ($previous) {
        $growth = $previous['revenue'] > 0 ? ($kpi['revenue'] - $previous['revenue']) / $previous['revenue'] * 100 : 0;
        $insights[] = [
            'type' => $growth >= 0 ? 'good' : 'bad',
            'text' => 'Выручка к прошлому месяцу ' . ($growth >= 0 ? 'выросла на ' : 'упала на ')
                . number_format(abs($growth), 1, ',', ' ') . ' %.',
        ];
        if ($kpi['defect_rate'] > $previous['defect_rate']) {
            $insights[] = ['type' => 'bad', 'text' => 'Брак вырос: ' . number_format($kpi['defect_rate'], 1, ',', ' ')
                . ' % против ' . number_format($previous['defect_rate'], 1, ',', ' ') . ' % месяцем ранее.'];
        } else {
            $insights[] = ['type' => 'good', 'text' => 'Брак снизился до ' . number_format($kpi['defect_rate'], 1, ',', ' ') . ' %.'];
        }
        if ($kpi['load'] > 90) {
            $insights[] = ['type' => 'warn', 'text' => 'Загрузка мощностей ' . number_format($kpi['load'], 0, ',', ' ')
                . ' % — близко к пределу, новые заказы могут сорвать сроки.'];
        } elseif ($kpi['load'] < 75) {
            $insights[] = ['type' => 'warn', 'text' => 'Загрузка ' . number_format($kpi['load'], 0, ',', ' ')
                . ' % — есть свободные мощности, можно брать больше заказов.'];
        }
    }

    $reasons = $pdo->prepare('SELECT reason, SUM(cost) AS loss FROM an_defects WHERE period = ? GROUP BY reason ORDER BY loss DESC LIMIT 1');
    $reasons->execute([$kpi['period'] ?? date('Y-m')]);
    $top = $reasons->fetch();
    if ($top) {
        $share = $kpi['revenue'] > 0 ? (float) $top['loss'] / $kpi['revenue'] * 100 : 0;
        $insights[] = ['type' => 'warn', 'text' => 'Главный источник потерь — ' . mb_strtolower((string) $top['reason'])
            . ': ' . an_money((float) $top['loss']) . ' (' . number_format($share, 1, ',', ' ') . ' % выручки).'];
    }

    $forecast = an_forecast($monthly, 'revenue');
    if ($forecast !== null) {
        $insights[] = ['type' => 'info', 'text' => 'Прогноз выручки на следующий месяц — около ' . an_money($forecast) . ' (линейный тренд).'];
    }

    $channels = an_channel_structure($pdo, $kpi['period'] ?? date('Y-m'));
    if ($channels) {
        $best = $channels[0];
        $insights[] = ['type' => 'good', 'text' => 'Лучший канал — ' . $best['channel'] . ': ' . an_money((float) $best['revenue']) . '.'];
    }

    return array_slice($insights, 0, 5);
}

/** Мини-график для KPI-карточки. */
function an_sparkline(array $values, string $color = '#2f6fed', int $width = 120, int $height = 32): string
{
    $values = array_values(array_map('floatval', $values));
    if (count($values) < 2) {
        return '';
    }
    $min = min($values);
    $max = max($values);
    if ($max - $min < 1e-6) {
        $max = $min + 1;
    }
    $step = $width / (count($values) - 1);
    $points = [];
    foreach ($values as $index => $value) {
        $x = $index * $step;
        $y = $height - (($value - $min) / ($max - $min)) * ($height - 4) - 2;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    return '<svg class="an-spark" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '">'
        . '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . an_e($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/* ------------------------------------------------------------------ графики */

function an_line_chart(array $labels, array $series, int $width = 900, int $height = 300, ?float $yMax = null, $yFormat = null): string
{
    $padding = ['left' => 62, 'right' => 64, 'top' => 22, 'bottom' => 36];
    $innerWidth = $width - $padding['left'] - $padding['right'];
    $innerHeight = $height - $padding['top'] - $padding['bottom'];
    $labels = array_values($labels);
    $count = max(1, count($labels) - 1);

    // Шкала левой оси строится по первому ряду (у остальных свои единицы).
    $firstValues = $series ? array_map('floatval', array_values($series[0]['values'])) : [];
    $firstMin = $firstValues ? min($firstValues) : 0;
    $firstMax = $firstValues ? max($firstValues) : 1;
    if ($firstMax - $firstMin < 0.0001) {
        $firstMax = $firstMin + 1;
    }
    $firstFormat = $yFormat ?? ($series[0]['format'] ?? 'an_num');
    if ($yMax !== null && $yMax > 0) {
        // Единая шкала (например, 0…максимум прибыли) — нужна, когда ряды
        // выражены в одних единицах: выручка, себестоимость, прибыль.
        $firstMin = 0;
        $firstMax = $yMax;
    }

    $svg = '<svg class="an-chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="График показателей">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $padding['top'] + $innerHeight * $i / 4;
        $svg .= '<line x1="' . $padding['left'] . '" y1="' . $y . '" x2="' . ($width - $padding['right']) . '" y2="' . $y . '" stroke="#e6e9f2" stroke-width="1"/>';
        $tick = $firstMax - ($firstMax - $firstMin) * $i / 4;
        $svg .= '<text x="' . ($padding['left'] - 10) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="12" fill="#7b8194">'
            . an_e(is_callable($firstFormat) ? call_user_func($firstFormat, $tick) : an_num($tick)) . '</text>';
    }
    foreach ($labels as $index => $label) {
        $x = $padding['left'] + $innerWidth * ($count ? $index / $count : 0);
        $svg .= '<text x="' . $x . '" y="' . ($height - 12) . '" text-anchor="middle" font-size="12.5" fill="#7b8194">' . an_e($label) . '</text>';
    }
    foreach ($series as $line) {
        $values = array_map('floatval', $line['values']);
        // Ряды «axis => right» (%) рисуются на своей шкале 0…%max.
        $rightAxis = ($line['axis'] ?? '') === 'right';
        if ($rightAxis) {
            $min = 0.0;
            $max = max(1.0, (float) max($values) * 1.2);
        } else {
            $min = $yMax !== null && $yMax > 0 ? 0.0 : min($values);
            $max = $yMax !== null && $yMax > 0 ? (float) $yMax : max($values);
        }
        if ($max - $min < 0.0001) {
            $max = $min + 1;
        }
        $points = [];
        foreach ($values as $index => $value) {
            $x = $padding['left'] + $innerWidth * ($count ? $index / $count : 0);
            $y = $padding['top'] + $innerHeight - (($value - $min) / ($max - $min)) * $innerHeight;
            $points[] = [$x, $y];
        }
        $polyline = implode(' ', array_map(static fn ($point) => round($point[0], 1) . ',' . round($point[1], 1), $points));
        $svg .= '<polyline points="' . $polyline . '" fill="none" stroke="' . an_e($line['color']) . '" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';
        foreach ($points as [$x, $y]) {
            $svg .= '<circle cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="3.2" fill="#fff" stroke="' . an_e($line['color']) . '" stroke-width="2"/>';
        }
        // Подпись последнего значения — чтобы каждый ряд читался отдельно.
        if ($points) {
            $last = end($values);
            $lastPoint = end($points);
            $format = $line['format'] ?? 'an_num';
            $label = is_callable($format) ? call_user_func($format, $last) : an_num($last);
            $svg .= '<text x="' . round($lastPoint[0] + 8, 1) . '" y="' . round($lastPoint[1] - 9, 1)
                . '" font-size="12.5" font-weight="700" fill="' . an_e($line['color']) . '">' . an_e($label) . '</text>';
        }
    }
    $svg .= '</svg>';

    $legend = '<div class="an-legend">';
    foreach ($series as $line) {
        $values = array_map('floatval', $line['values']);
        $last = end($values);
        $legend .= '<span class="an-legend-item"><i style="background:' . an_e($line['color']) . '"></i>'
            . an_e($line['name']) . ' <b>' . an_e(($line['format'] ?? 'an_num')($last)) . '</b></span>';
    }
    $legend .= '</div>';
    return '<div class="an-chart-wrap">' . $svg . $legend . '</div>';
}

function an_bar_chart(array $labels, array $values, string $color = '#2f6fed', $format = 'an_num'): string
{
    $width = 900;
    $height = 260;
    $padding = ['left' => 64, 'right' => 22, 'top' => 22, 'bottom' => 46];
    $innerWidth = $width - $padding['left'] - $padding['right'];
    $innerHeight = $height - $padding['top'] - $padding['bottom'];
    $labels = array_values($labels);
    $values = array_values($values);
    if (!$values) {
        return '<div class="an-chart-wrap"><p class="an-note">Нет данных за выбранный период.</p></div>';
    }
    $max = max(1.0, max(array_map('floatval', $values)));
    $count = max(1, count($values));
    $step = $innerWidth / $count;
    $barWidth = min(64.0, $step * 0.55);

    $svg = '<svg class="an-chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Столбчатая диаграмма">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $padding['top'] + $innerHeight * $i / 4;
        $svg .= '<line x1="' . $padding['left'] . '" y1="' . $y . '" x2="' . ($width - $padding['right']) . '" y2="' . $y . '" stroke="#e6e9f2" stroke-width="1"/>';
        $svg .= '<text x="' . ($padding['left'] - 10) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="10" fill="#9aa1b3">'
            . an_e(is_callable($format) ? call_user_func($format, $max * (4 - $i) / 4) : an_num($max * (4 - $i) / 4)) . '</text>';
    }
    foreach ($values as $index => $value) {
        $barHeight = $innerHeight * ((float) $value / $max);
        $x = $padding['left'] + $step * $index + ($step - $barWidth) / 2;
        $y = $padding['top'] + $innerHeight - $barHeight;
        $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barWidth, 1) . '" height="' . round($barHeight, 1) . '" rx="6" fill="' . an_e($color) . '" opacity="0.9"/>';
        $svg .= '<text x="' . round($x + $barWidth / 2, 1) . '" y="' . ($height - 16) . '" text-anchor="middle" font-size="12.5" fill="#7b8194">' . an_e($labels[$index] ?? '') . '</text>';
    }
    $svg .= '</svg>';
    return '<div class="an-chart-wrap">' . $svg . '</div>';
}

/* ------------------------------------------------------------------ формы */

function an_csrf(): string
{
    if (empty($_SESSION['an_csrf'])) {
        $_SESSION['an_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['an_csrf'];
}

function an_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . an_e(an_csrf()) . '">';
}

function an_check_csrf(): void
{
    if (!hash_equals($_SESSION['an_csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Сессия истекла. Обновите страницу.');
    }
}

function an_can_edit(): bool
{
    return isset($_SESSION['user_id']);
}

function an_flash(string $message, string $type = 'ok'): void
{
    $_SESSION['an_flash'][] = ['text' => $message, 'type' => $type];
}

function an_flashes(): array
{
    $items = $_SESSION['an_flash'] ?? [];
    unset($_SESSION['an_flash']);
    return $items;
}

function an_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
