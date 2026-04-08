<?php
// modules/valuation_tracker/views/admin_report.php
/** @var array $report The valuation data for all items */

$totalValuation = 0;
$totalInvested = 0;
foreach ($report as $row) {
    if ($row['currency'] === 'INR') { // Simple sum for now, assuming base currency is INR
        $totalValuation += (float)($row['current_value'] ?? 0);
        $totalInvested += (float)($row['purchase_price'] ?? 0);
    }
}

$netGain = $totalValuation - $totalInvested;
$roi = $totalInvested > 0 ? ($netGain / $totalInvested) * 100 : 0;
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Collection Wealth Report</h1>
    <p class="text-gray-500 mt-1">Financial overview of your tracked assets.</p>
</div>

<!-- Stats Overview -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Total Items</p>
        <p class="text-3xl font-black text-gray-900"><?= count($report) ?></p>
    </div>
    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Current Value (Est.)</p>
        <p class="text-3xl font-black text-emerald-600">₹<?= number_format($totalValuation, 2) ?></p>
    </div>
    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Total Invested</p>
        <p class="text-3xl font-black text-blue-600">₹<?= number_format($totalInvested, 2) ?></p>
    </div>
    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Unrealized Gain</p>
        <p class="text-3xl font-black <?= $netGain >= 0 ? 'text-emerald-500' : 'text-red-500' ?>">
            <?= $netGain >= 0 ? '+' : '' ?>₹<?= number_format($netGain, 2) ?>
            <span class="text-xs font-bold ml-1 opacity-60">(<?= number_format($roi, 1) ?>%)</span>
        </p>
    </div>
</div>

<!-- Item Breakdown Table -->
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Item / Reg</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Acquired</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Purchase</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Current Value</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Growth</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-sm">
            <?php foreach ($report as $row): ?>
                <?php 
                    $p = (float)($row['purchase_price'] ?? 0);
                    $v = (float)($row['current_value'] ?? 0);
                    $diff = $v - $p;
                    $growth = $p > 0 ? ($diff / $p) * 100 : 0;
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-bold text-gray-900"><?= htmlspecialchars($row['title']) ?></div>
                        <div class="text-[10px] text-gray-400 font-mono tracking-tighter uppercase"><?= htmlspecialchars($row['reg_number']) ?></div>
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        <?= $row['purchase_date'] ? date('M Y', strtotime($row['purchase_date'])) : '—' ?>
                    </td>
                    <td class="px-6 py-4 font-medium text-gray-600">
                        <?= $row['purchase_price'] ? $row['currency'] . ' ' . number_format($row['purchase_price'], 2) : '—' ?>
                    </td>
                    <td class="px-6 py-4 font-bold text-gray-900">
                        <?= $row['current_value'] ? $row['currency'] . ' ' . number_format($row['current_value'], 2) : '—' ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($p > 0): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold <?= $diff >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' ?>">
                                <?= $diff >= 0 ? '↑' : '↓' ?> <?= number_format(abs($growth), 1) ?>%
                            </span>
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="edit_item.php?id=<?= $row['id'] ?>" class="text-blue-600 hover:text-blue-800 font-bold uppercase text-[10px] tracking-widest">Update</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
