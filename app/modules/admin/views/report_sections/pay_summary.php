<?php
/**
 * Leave Summary Report Section
 * Displays leave usage summary by employee with all leave types
 */

// Get leave summary data
$leaveSummary = $reportData['pay_summary'] ?? [];
?>

<!-- Leave Summary Report -->
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-700 bg-gradient-to-r from-cyan-600/20 to-blue-600/20">
        <h3 class="text-xl font-semibold text-white flex items-center">
            <i class="fas fa-file-invoice text-cyan-400 mr-3"></i>Leave Summary Report
        </h3>
        <p class="text-slate-400 text-sm mt-1">Summary of leave usage by employee for the selected period</p>
    </div>
    
    <div class="p-6">
        <?php if (!empty($leaveSummary)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700">
                            <th class="text-left py-3 px-4 text-slate-400 font-semibold">Employee</th>
                            <th class="text-left py-3 px-4 text-slate-400 font-semibold">Department</th>
                            <th class="text-center py-3 px-4 text-slate-400 font-semibold">VL</th>
                            <th class="text-center py-3 px-4 text-slate-400 font-semibold">SL</th>
                            <th class="text-center py-3 px-4 text-slate-400 font-semibold">SLP</th>
                            <th class="text-center py-3 px-4 text-slate-400 font-semibold">Maternity</th>
                            <th class="text-center py-3 px-4 text-slate-400 font-semibold">Paternity</th>
                            <th class="text-center py-3 px-4 text-slate-400 font-semibold">Solo Parent</th>
                            <th class="text-center py-3 px-4 text-slate-400 font-semibold">CTO</th>
                            <th class="text-center py-3 px-4 text-slate-400 font-semibold">Without Pay</th>
                            <th class="text-center py-3 px-4 text-slate-400 font-semibold">Total Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grandTotalVL = 0;
                        $grandTotalSL = 0;
                        $grandTotalSLP = 0;
                        $grandTotalMaternity = 0;
                        $grandTotalPaternity = 0;
                        $grandTotalSoloParent = 0;
                        $grandTotalCTO = 0;
                        $grandTotalWithoutPay = 0;
                        $grandTotal = 0;
                        
                        foreach ($leaveSummary as $row): 
                            $vl = $row['vacation_days'] ?? 0;
                            $sl = $row['sick_days'] ?? 0;
                            $slp = $row['special_privilege_days'] ?? 0;
                            $maternity = $row['maternity_days'] ?? 0;
                            $paternity = $row['paternity_days'] ?? 0;
                            $soloParent = $row['solo_parent_days'] ?? 0;
                            $cto = $row['cto_days'] ?? 0;
                            $withoutPay = $row['without_pay_days'] ?? 0;
                            $total = $vl + $sl + $slp + $maternity + $paternity + $soloParent + $cto + $withoutPay;
                            
                            $grandTotalVL += $vl;
                            $grandTotalSL += $sl;
                            $grandTotalSLP += $slp;
                            $grandTotalMaternity += $maternity;
                            $grandTotalPaternity += $paternity;
                            $grandTotalSoloParent += $soloParent;
                            $grandTotalCTO += $cto;
                            $grandTotalWithoutPay += $withoutPay;
                            $grandTotal += $total;
                        ?>
                        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-white"><?php echo htmlspecialchars($row['employee_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td class="py-3 px-4 text-slate-300"><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="<?php echo $vl > 0 ? 'text-blue-400 font-semibold' : 'text-slate-500'; ?>">
                                    <?php echo number_format($vl, 1); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="<?php echo $sl > 0 ? 'text-green-400 font-semibold' : 'text-slate-500'; ?>">
                                    <?php echo number_format($sl, 1); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="<?php echo $slp > 0 ? 'text-purple-400 font-semibold' : 'text-slate-500'; ?>">
                                    <?php echo number_format($slp, 1); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="<?php echo $maternity > 0 ? 'text-pink-400 font-semibold' : 'text-slate-500'; ?>">
                                    <?php echo number_format($maternity, 1); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="<?php echo $paternity > 0 ? 'text-cyan-400 font-semibold' : 'text-slate-500'; ?>">
                                    <?php echo number_format($paternity, 1); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="<?php echo $soloParent > 0 ? 'text-orange-400 font-semibold' : 'text-slate-500'; ?>">
                                    <?php echo number_format($soloParent, 1); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="<?php echo $cto > 0 ? 'text-yellow-400 font-semibold' : 'text-slate-500'; ?>">
                                    <?php echo number_format($cto, 1); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="<?php echo $withoutPay > 0 ? 'text-red-400 font-semibold' : 'text-slate-500'; ?>">
                                    <?php echo number_format($withoutPay, 1); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="bg-slate-700 px-3 py-1 rounded-full text-white font-bold">
                                    <?php echo number_format($total, 1); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-700/50 font-bold">
                            <td class="py-3 px-4 text-white" colspan="2">Grand Total</td>
                            <td class="py-3 px-4 text-center text-blue-400"><?php echo number_format($grandTotalVL, 1); ?></td>
                            <td class="py-3 px-4 text-center text-green-400"><?php echo number_format($grandTotalSL, 1); ?></td>
                            <td class="py-3 px-4 text-center text-purple-400"><?php echo number_format($grandTotalSLP, 1); ?></td>
                            <td class="py-3 px-4 text-center text-pink-400"><?php echo number_format($grandTotalMaternity, 1); ?></td>
                            <td class="py-3 px-4 text-center text-cyan-400"><?php echo number_format($grandTotalPaternity, 1); ?></td>
                            <td class="py-3 px-4 text-center text-orange-400"><?php echo number_format($grandTotalSoloParent, 1); ?></td>
                            <td class="py-3 px-4 text-center text-yellow-400"><?php echo number_format($grandTotalCTO, 1); ?></td>
                            <td class="py-3 px-4 text-center text-red-400"><?php echo number_format($grandTotalWithoutPay, 1); ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="bg-cyan-500 px-3 py-1 rounded-full text-white">
                                    <?php echo number_format($grandTotal, 1); ?>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Legend -->
            <div class="mt-6 p-4 bg-slate-700/30 rounded-xl">
                <h4 class="text-sm font-semibold text-slate-300 mb-3">Legend</h4>
                <div class="flex flex-wrap gap-4 text-xs">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 bg-blue-400 rounded"></span> VL = Vacation Leave</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 bg-green-400 rounded"></span> SL = Sick Leave</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 bg-purple-400 rounded"></span> SLP = Special Leave Privilege</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 bg-pink-400 rounded"></span> Maternity Leave</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 bg-cyan-400 rounded"></span> Paternity Leave</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 bg-orange-400 rounded"></span> Solo Parent Leave</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 bg-yellow-400 rounded"></span> CTO = Compensatory Time Off</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 bg-red-400 rounded"></span> Without Pay</span>
                </div>
            </div>
            
        <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-file-invoice text-5xl text-slate-600 mb-4"></i>
                <p class="text-slate-400 text-lg">No leave data found for the selected period</p>
                <p class="text-slate-500 text-sm mt-2">Try adjusting the date range or filters</p>
            </div>
        <?php endif; ?>
    </div>
</div>
