<?php

// Rules based on sell-through rate (quantity_sold / beginning_stock):
//   rate >= 0.70  -> High Demand   (70% or more of stock sold)
//   rate >= 0.40  -> Medium Demand (40% – 69% sold)
//   rate <  0.40  -> Low Demand    (less than 40% sold)


if (!function_exists('calculate_demand_level')) {
    function calculate_demand_level($quantity_sold, $beginning_stock = null): string {
        $sold = (float)$quantity_sold;
        $beg  = ($beginning_stock !== null) ? (float)$beginning_stock : 0.0;

        // Fallback: if no beginning stock provided, use old quantity-based logic
        if ($beg <= 0) {
            if ($sold >= 5) return 'High Demand';
            if ($sold >= 3) return 'Medium Demand';
            return 'Low Demand';
        }

        $rate = $sold / $beg;

        if ($rate >= 0.70) return 'High Demand';
        if ($rate >= 0.40) return 'Medium Demand';
        return 'Low Demand';
    }
}

if (!function_exists('demand_badge_class')) {
    function demand_badge_class(?string $dl): string {
        $dl = trim((string)$dl);
        if ($dl === 'High Demand') return 'badge-high';
        if ($dl === 'Low Demand')  return 'badge-low';
        return 'badge-medium';
    }
}

// NEW: return sell-through percentage (0–100)
if (!function_exists('calculate_sell_through_percent')) {
    function calculate_sell_through_percent($quantity_sold, $beginning_stock): float {
        $sold = (float)$quantity_sold;
        $beg  = (float)$beginning_stock;
        if ($beg <= 0) return 0.0;
        $pct = ($sold / $beg) * 100;
        if ($pct < 0) $pct = 0;
        if ($pct > 100) $pct = 100;
        return round($pct, 1);
    }
}

// NEW: return pre-formatted string like "70.0%"
if (!function_exists('format_sell_through_percent')) {
    function format_sell_through_percent($quantity_sold, $beginning_stock): string {
        return number_format(calculate_sell_through_percent($quantity_sold, $beginning_stock), 1) . '%';
    }
}

?>