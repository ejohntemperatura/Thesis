<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';

// Auto-process emails when internet is available
require_once '../../../../app/core/services/auto_email_processor.php';

// Allow admin or manager (department head) to access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager'])) {
	header('Location: ../../../../auth/views/login.php');
	exit();
}

// Basic user info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

// Get department head's department for filtering
$dept_head_department = $me['department'] ?? null;

// Set page title
$page_title = "Department Dashboard";

// Include department header with modern design
include '../../../../includes/department_header.php';
?>
<script src="../../../../assets/libs/chartjs/chart.umd.min.js"></script>

<!-- Welcome Section with Clock -->
<div class="flex items-center justify-between mb-8">
    <div class="flex-1">
        <h1 class="text-3xl font-bold text-white mb-2">
            Welcome back, <?php echo htmlspecialchars($me['name'] ?? 'Department Head'); ?>!
        </h1>
        <p class="text-slate-400">Here's what's happening with your leave requests today.</p>
    </div>
    
    <!-- Live Clock -->
    <div class="text-right">
        <div style="display: inline-flex; align-items: baseline; gap: 0.5rem; justify-content: flex-end; margin-bottom: 0.25rem;">
            <span id="clockHM" style="color: white; font-size: 1.75rem; font-weight: 700; font-family: 'Courier New', monospace;">--:--</span>
            <span id="clockSec" style="color: #cbd5e1; font-size: 1rem; font-family: 'Courier New', monospace;">--</span>
            <span id="clockAmPm" style="color: #cbd5e1; font-size: 0.875rem; font-family: 'Courier New', monospace;">--</span>
        </div>
        <div style="color: #94a3b8; font-size: 0.75rem;">Today is</div>
        <div id="clockDateChip" style="margin-top: 0.25rem; display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; border: 1px solid rgba(51,65,85,0.6); background: rgba(51,65,85,0.4); color: #e5e7eb; font-size: 0.875rem;">Loading...</div>
    </div>
</div>

<script>
    // Update Department Head dashboard time (match Admin split-badge clock)
    function updateAdminDashboardTime() {
        const now = new Date();
        let h = now.getHours();
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12; h = h ? h : 12;
        const hm = `${String(h).padStart(2, '0')}:${m}`;
        const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const dateString = `${days[now.getDay()]}, ${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
        const elHM = document.getElementById('clockHM');
        const elS = document.getElementById('clockSec');
        const elAP = document.getElementById('clockAmPm');
        const elDate = document.getElementById('clockDateChip');
        if (elHM) elHM.textContent = hm;
        if (elS) elS.textContent = s;
        if (elAP) elAP.textContent = ampm;
        if (elDate) elDate.textContent = dateString;
    }
    updateAdminDashboardTime();
    setInterval(updateAdminDashboardTime, 1000);
</script>

<!-- Success Message -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
        <i class="fas fa-check-circle mr-3"></i>
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<!-- Error Message -->
<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
        <i class="fas fa-exclamation-circle mr-3"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- Pending Leave Requests -->
<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden mb-8">
	<div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
		<h3 class="text-xl font-semibold text-white flex items-center">
			<i class="fas fa-clock text-yellow-400 mr-3"></i>Pending Leave Requests
		</h3>
	</div>
	<div class="p-6">
							<?php
							// Get total count of pending leave requests for this department only
							$stmt = $pdo->prepare("
								SELECT COUNT(*) as total_count
								FROM leave_requests lr 
								JOIN employees e ON lr.employee_id = e.id 
								WHERE (lr.dept_head_approval IS NULL OR lr.dept_head_approval = 'pending')
								AND lr.status NOT IN ('rejected', 'cancelled')
								AND e.department = ?
							");
							$stmt->execute([$dept_head_department]);
							$total_pending = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
							
							// Get pending leave requests (only those not yet decided by department head)
							// Initially show only 5 requests - filtered by department
							$initial_limit = 5;
							$stmt = $pdo->prepare("
								SELECT lr.*, lr.late_justification, e.name as employee_name, e.position, e.department, e.service_credit_balance AS sc_balance
								FROM leave_requests lr 
								JOIN employees e ON lr.employee_id = e.id 
								WHERE (lr.dept_head_approval IS NULL OR lr.dept_head_approval = 'pending')
								AND lr.status NOT IN ('rejected', 'cancelled')
								AND e.department = ?
								ORDER BY lr.is_late DESC, lr.created_at DESC 
								LIMIT " . intval($initial_limit)
							);
							$stmt->execute([$dept_head_department]);
							$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
							
							// Get leave types configuration
							$leaveTypes = getLeaveTypes();
							?>
							
						<?php if (empty($pending_requests)): ?>
							<div class="text-center py-12">
								<i class="fas fa-check-circle text-4xl text-green-400 mb-4"></i>
								<p class="text-slate-400 text-lg">No pending leave requests</p>
							</div>
						<?php else: ?>
							<div class="overflow-x-auto">
								<table class="w-full">
									<thead class="bg-slate-700/30">
										<tr>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Employee</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Leave Type</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Leave Dates</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Days</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Reason</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Status</th>
											<th class="px-6 py-4 text-center text-xs font-semibold text-slate-300 uppercase tracking-wider">Actions</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-slate-700/50">
										<?php foreach ($pending_requests as $request): ?>
											<tr class="hover:bg-slate-700/30 transition-colors">
												<td class="px-6 py-4">
													<div>
														<div class="font-semibold text-white"><?php echo htmlspecialchars($request['employee_name']); ?></div>
														<div class="text-sm text-slate-400"><?php echo htmlspecialchars($request['position']); ?></div>
													</div>
												</td>
												<td class="px-6 py-4">
													<div class="flex flex-col gap-2">
														<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary/20 text-primary border border-primary/30">
															<?php 
																$disp = getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes, $request['other_purpose'] ?? null);
																if (!isset($disp) || trim($disp) === '') {
																	$base = $request['original_leave_type'] ?? ($request['leave_type'] ?? '');
																	$disp = getLeaveTypeDisplayName($base, null, $leaveTypes, $request['other_purpose'] ?? null);
																	if (!isset($disp) || trim($disp) === '') {
																		if (!empty($request['study_type'])) {
																			$disp = 'Study Leave (Without Pay)';
																		} elseif (!empty($request['medical_condition']) || !empty($request['illness_specify'])) {
																			$disp = 'Sick Leave (SL)';
																		} elseif (!empty($request['special_women_condition'])) {
																			$disp = 'Special Leave Benefits for Women';
																		} elseif (!empty($request['location_type'])) {
																			$disp = 'Vacation Leave (VL)';
																		} elseif (isset($request['sc_balance']) && (float)$request['sc_balance'] > 0) {
																			$disp = 'Service Credits';
																		} elseif (($request['pay_status'] ?? '') === 'without_pay' || ($request['leave_type'] ?? '') === 'without_pay') {
																			$disp = 'Without Pay Leave';
																		} else {
																			$disp = 'Service Credits';
																		}
																	}
																}
																echo $disp;
															?>
														</span>
														<?php if ($request['is_late'] == 1): ?>
															<span class="bg-orange-500/20 text-orange-400 px-2 py-1 rounded-full text-xs font-semibold flex items-center">
																<i class="fas fa-exclamation-triangle mr-1"></i>
																Late Application
															</span>
														<?php endif; ?>
													</div>
												</td>
												<td class="px-6 py-4 text-slate-300 text-sm">
													<?php if ($request['leave_type'] === 'other'): ?>
														<!-- For Terminal Leave / Monetization: Show only working days -->
														<div class="flex flex-wrap gap-1 items-center">
															<span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap border border-green-500/30">
																<?php echo $request['working_days_applied'] ?? $request['days_requested']; ?> working day(s)
															</span>
															<span class="text-xs text-slate-400">Leave credits</span>
														</div>
													<?php else: ?>
														<!-- For Regular Leave: Show calendar dates -->
														<div class="flex flex-wrap gap-1">
															<?php 
															if (!empty($request['selected_dates'])) {
																$dates = explode(',', $request['selected_dates']);
																foreach ($dates as $date): ?>
																	<span class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded-lg text-xs font-semibold whitespace-nowrap border border-blue-500/30">
																		<?php echo date('M d, Y', strtotime($date)); ?>
																	</span>
																<?php endforeach;
															} else {
																// Generate date range as badges for older records
																$start = new DateTime($request['start_date']);
																$end = new DateTime($request['end_date']);
																$current = clone $start;
																while ($current <= $end) {
																	$dayOfWeek = (int)$current->format('N');
																	if ($dayOfWeek >= 1 && $dayOfWeek <= 5): ?>
																		<span class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded-lg text-xs font-semibold whitespace-nowrap border border-blue-500/30">
																			<?php echo $current->format('M d, Y'); ?>
																		</span>
																	<?php endif;
																	$current->modify('+1 day');
																}
															}
															?>
														</div>
													<?php endif; ?>
												</td>
												<td class="px-6 py-4 text-slate-300 text-sm">
													<?php 
													// Use days_requested from database (excludes weekends)
													echo $request['days_requested'] ?? 0;
													?>
												</td>
												<td class="px-6 py-4 text-slate-300 text-sm max-w-xs truncate" title="<?php echo htmlspecialchars($request['is_late'] == 1 ? ($request['late_justification'] ?? '') : ($request['reason'] ?? '')); ?>">
													<?php 
													// For late leave applications, show late_justification instead of reason
													$displayText = $request['is_late'] == 1 ? ($request['late_justification'] ?? '') : ($request['reason'] ?? '');
													echo strlen($displayText) > 30 ? substr(htmlspecialchars($displayText), 0, 30) . '...' : htmlspecialchars($displayText); 
													?>
												</td>
												<td class="px-6 py-4">
													<div class="flex items-center gap-2">
														<span class="bg-yellow-500/20 text-yellow-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">Pending</span>
														<button type="button" onclick="showStatusInfo(<?php echo $request['id']; ?>)" title="View Status Details" class="text-slate-400 hover:text-white transition-colors">
															<i class="fas fa-info-circle"></i>
														</button>
													</div>
												</td>
												<td class="px-6 py-4 text-center">
													<div class="flex justify-center">
														<button onclick="openDepartmentApprovalModal(<?php echo $request['id']; ?>)" class="inline-flex items-center px-4 py-2 bg-primary hover:bg-primary/80 text-white text-sm font-medium rounded-lg transition-colors">
															<i class="fas fa-gavel mr-2"></i> Process Request
														</button>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php if ($total_pending > $initial_limit): ?>
								<div class="text-center mt-6">
									<button id="loadMoreBtn" class="bg-primary/20 hover:bg-primary/30 text-primary border border-primary/30 font-semibold py-3 px-6 rounded-xl transition-colors">
										<i class="fas fa-plus mr-2"></i>View More (<?php echo $total_pending - $initial_limit; ?> more)
									</button>
									<button id="showLessBtn" class="hidden bg-slate-600/20 hover:bg-slate-600/30 text-slate-400 border border-slate-600/30 font-semibold py-3 px-6 rounded-xl transition-colors ml-4">
										<i class="fas fa-minus mr-2"></i>Show Less
									</button>
								</div>
								<!-- Hidden container for additional requests -->
								<div id="additionalRequests" class="hidden mt-6"></div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- Leave Chart Quick Action Card -->
				<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden hover:border-slate-600/50 transition-all duration-300 group">
					<div class="p-6">
						<div class="flex items-center mb-4">
							<div class="w-12 h-12 bg-slate-700 rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
								<i class="fas fa-calendar text-primary text-lg"></i>
							</div>
							<h3 class="text-xl font-semibold text-white">Leave Chart</h3>
						</div>
						<p class="text-slate-400 mb-6">View comprehensive leave analytics and calendar overview across the department.</p>
						<a href="view_chart.php" class="inline-flex items-center px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl transition-colors">
							<i class="fas fa-calendar mr-2"></i>View Leave Chart
						</a>
					</div>
				</div>
			</div>
		</main>
	</div>

	<script>
		// Pass leave types data to JavaScript
		window.leaveTypes = <?php echo json_encode($leaveTypes); ?>;
		
		// Helper function to get leave type display name in JavaScript
		function getLeaveTypeDisplayNameJS(leaveType, originalLeaveType = null, otherPurpose = null) {
			const leaveTypes = window.leaveTypes;
			if (!leaveTypes) return leaveType;
			
			// Handle "other" leave type (Terminal Leave / Monetization)
			if (leaveType === 'other' && otherPurpose) {
				if (otherPurpose === 'terminal_leave') {
					return 'Terminal Leave';
				} else if (otherPurpose === 'monetization') {
					return 'Monetization of Leave Credits';
				}
			}
			
			// Check if leave is without pay
			let isWithoutPay = false;
			
			// If leave_type is explicitly 'without_pay', it's without pay
			if (leaveType === 'without_pay') {
				isWithoutPay = true;
			}
			// If original_leave_type exists and current type is 'without_pay' or empty, it was converted to without pay
			else if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
				isWithoutPay = true;
			}
			// Check if the current leave type is inherently without pay
			else if (leaveTypes[leaveType] && leaveTypes[leaveType].without_pay) {
				isWithoutPay = true;
			}
			// Check if the original leave type was inherently without pay
			else if (originalLeaveType && leaveTypes[originalLeaveType] && leaveTypes[originalLeaveType].without_pay) {
				isWithoutPay = true;
			}
			
			// Determine the base leave type to display
			let baseType = null;
			if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
				// Use original type if it was converted to without pay
				baseType = originalLeaveType;
			} else {
				// Use current type
				baseType = leaveType;
			}
			
			// Get the display name
			if (leaveTypes[baseType]) {
				const leaveTypeConfig = leaveTypes[baseType];
				
				// Use formal_name if available, otherwise fall back to name
				const displayName = leaveTypeConfig.formal_name || leaveTypeConfig.name;
				
				if (isWithoutPay) {
					// Show name with without pay indicator
					if (leaveTypeConfig.name_with_note) {
						return leaveTypeConfig.name_with_note;
					} else {
						return displayName + ' (Without Pay)';
					}
				} else {
					// Show formal name
					return displayName;
				}
			} else {
				// Fallback for unknown types
				const displayName = baseType.charAt(0).toUpperCase() + baseType.slice(1).replace(/_/g, ' ');
				return isWithoutPay ? displayName + ' (Without Pay)' : displayName;
			}
		}
		
		// User dropdown toggle function
		function toggleUserDropdown() {
			const dropdown = document.getElementById('userDropdown');
			dropdown.classList.toggle('hidden');
		}

		// Close dropdown when clicking outside
		document.addEventListener('click', function(event) {
			const userDropdown = document.getElementById('userDropdown');
			const userButton = event.target.closest('[onclick="toggleUserDropdown()"]');
			
			if (userDropdown && !userDropdown.contains(event.target) && !userButton) {
				userDropdown.classList.add('hidden');
			}
		});

		// Show status information modal
		function showStatusInfo(leaveId) {
			// Create modal HTML
			const modalHtml = `
				<div id="statusInfoModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 elms-modal-overlay">
					<div class="bg-slate-800 rounded-2xl border border-slate-700 max-w-2xl w-full max-h-[90vh] overflow-y-auto elms-modal">
						<div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
							<div class="flex items-center justify-between">
								<h3 class="text-xl font-semibold text-white flex items-center">
									<i class="fas fa-info-circle text-primary mr-3"></i>Leave Request Status
								</h3>
								<button type="button" class="text-slate-400 hover:text-white transition-colors" onclick="closeStatusModal()">
									<i class="fas fa-times text-xl"></i>
								</button>
							</div>
						</div>
						<div class="p-6">
							<div class="bg-blue-500/20 border border-blue-500/30 rounded-xl p-4 mb-6">
								<h4 class="text-lg font-semibold text-white mb-2 flex items-center">
									<i class="fas fa-clock text-blue-400 mr-2"></i>Current Status
								</h4>
								<p class="text-slate-300">This leave request is <strong class="text-white">pending your decision as Department Head</strong>. After your action, it will move to <strong class="text-white">Admin</strong> review, then to the <strong class="text-white">Director</strong> for final decision.</p>
							</div>
							<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-primary font-semibold mb-3 flex items-center">
										<i class="fas fa-user-tie mr-2"></i>Department Head (You)
									</h4>
									<p class="text-slate-300 mb-2"><strong class="text-white">Status:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 ml-2">Pending</span>
									</p>
									<p class="text-slate-400 text-sm">Action Required: Approve or Reject this request</p>
								</div>
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-slate-400 font-semibold mb-3 flex items-center">
										<i class="fas fa-user-shield mr-2"></i>Admin
									</h4>
									<p class="text-slate-300 mb-2"><strong class="text-white">Status:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-500/20 text-slate-400 border border-slate-500/30 ml-2">Waiting</span>
									</p>
									<p class="text-slate-400 text-sm">Will review after Department Head</p>
								</div>
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-slate-400 font-semibold mb-3 flex items-center">
										<i class="fas fa-crown mr-2"></i>Director
									</h4>
									<p class="text-slate-300 mb-2"><strong class="text-white">Status:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-500/20 text-slate-400 border border-slate-500/30 ml-2">Waiting</span>
									</p>
									<p class="text-slate-400 text-sm">Final decision after Admin</p>
								</div>
							</div>
							<div class="border-t border-slate-700 pt-6">
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-slate-400 font-semibold mb-3 flex items-center">
										<i class="fas fa-flag-checkered mr-2"></i>Final Status
									</h4>
									<p class="text-slate-300 mb-2"><strong class="text-white">Result:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-500/20 text-slate-400 border border-slate-500/30 ml-2">Pending</span>
									</p>
									<p class="text-slate-400 text-sm">Will be determined after Admin and Director approvals</p>
								</div>
							</div>
							<div class="flex justify-end mt-6">
								<button type="button" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl transition-colors" onclick="closeStatusModal()">Close</button>
							</div>
						</div>
					</div>
				</div>
			`;

			// Remove existing modal if any
			const existingModal = document.getElementById('statusInfoModal');
			if (existingModal) {
				existingModal.remove();
			}

			// Add modal to body
			document.body.insertAdjacentHTML('beforeend', modalHtml);
		}

		// Close status modal
		function closeStatusModal() {
			const modal = document.getElementById('statusInfoModal');
			if (modal) {
				modal.remove();
			}
		}


		// Department Approval Modal Functions
		function openDepartmentApprovalModal(requestId) {
			
			// First, get the leave request details via AJAX
			fetch(`../api/get_leave_request_details.php?id=${requestId}`)
				.then(response => {
					return response.json();
				})
				.then(data => {
					if (data.success) {
						const request = data.leave;
						const modalHtml = `
							<div id="departmentApprovalModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4 elms-modal-overlay">
								<div class="bg-slate-800 rounded-2xl border border-slate-700 w-full max-w-4xl max-h-[90vh] overflow-y-auto elms-modal">
									<div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
										<div class="flex items-center justify-between">
											<h3 class="text-2xl font-bold text-white flex items-center">
												<i class="fas fa-gavel text-primary mr-3"></i>
												Department Head Approval - Leave Request
											</h3>
											<button type="button" onclick="closeDepartmentApprovalModal()" class="text-slate-400 hover:text-white transition-colors">
												<i class="fas fa-times text-xl"></i>
											</button>
										</div>
									</div>
									
									<div class="p-6">
										<!-- Status Information -->
										${request.is_late == 1 || request.is_late === true ? `
										<div class="bg-orange-500/20 border border-orange-500/30 rounded-xl p-4 mb-6">
											<div class="flex items-center">
												<i class="fas fa-exclamation-triangle text-orange-400 mr-3 text-xl"></i>
												<div>
													<h4 class="text-lg font-semibold text-white mb-1">Late Leave Application</h4>
													<p class="text-orange-400">This is a late leave application that requires special consideration. Please review the late justification carefully.</p>
												</div>
											</div>
										</div>
										` : `
										<div class="bg-blue-500/20 border border-blue-500/30 rounded-xl p-4 mb-6">
											<div class="flex items-center">
												<i class="fas fa-info-circle text-blue-400 mr-3 text-xl"></i>
												<div>
													<h4 class="text-lg font-semibold text-white mb-1">Approval Status</h4>
													<p class="text-blue-400">This leave request is awaiting your decision as Department Head.</p>
												</div>
											</div>
										</div>
										`}
										
										<!-- Leave Request Details -->
										<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
											<!-- Employee Information -->
											<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
												<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
													<i class="fas fa-user-circle text-blue-500 mr-3"></i>
													Employee Information
												</h4>
												<div class="space-y-3">
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Employee Name</label>
														<p class="text-white font-medium">${request.employee_name}</p>
													</div>
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Department</label>
														<p class="text-white">${request.department || 'N/A'}</p>
													</div>
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Position</label>
														<p class="text-white">${request.position || 'N/A'}</p>
													</div>
												</div>
											</div>
											
											<!-- Leave Details -->
											<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
												<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
													<i class="fas fa-calendar-alt text-green-500 mr-3"></i>
													Leave
												</h4>
												<div class="space-y-3">
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Leave Type</label>
														<p class="text-white font-medium">${(() => {
															const base = request.leave_type_raw || request.leave_type;
															const lbl = getLeaveTypeDisplayNameJS(base, request.original_leave_type, request.other_purpose);
															return (lbl && String(lbl).trim() !== '') ? lbl : (request.leave_type || 'Service Credits');
														})()}</p>
													</div>
													${request.selected_dates && request.selected_dates.trim() !== '' ? `
														<div class="col-span-2">
															<label class="block text-sm font-semibold text-slate-300 mb-2">Selected Leave Days</label>
															<div class="bg-slate-700/50 p-3 rounded-lg">
																<div class="flex flex-wrap gap-2">
																	${request.selected_dates.split(',').map(date => `
																		<span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-xs font-semibold">
																			${new Date(date + 'T00:00:00').toLocaleDateString('en-US', { 
																				month: 'short', 
																				day: 'numeric',
																				year: 'numeric'
																			})}
																		</span>
																	`).join('')}
																</div>
															</div>
														</div>
													` : (request.leave_type_raw === 'other' || request.leave_type === 'other') ? `
														<!-- For Terminal Leave / Monetization: Show only working days applied -->
														<div class="col-span-2">
															<label class="block text-sm font-semibold text-slate-300 mb-1">Leave Credits to Convert</label>
															<p class="text-white text-lg font-semibold">${request.working_days_applied || request.days_requested} working day(s)</p>
															<p class="text-slate-400 text-sm mt-1">This represents leave credits to be converted to cash, not calendar dates.</p>
														</div>
													` : `
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Start Date</label>
															<p class="text-white">${request.start_date}</p>
														</div>
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">End Date</label>
															<p class="text-white">${request.end_date}</p>
														</div>
													`}
													${(request.leave_type_raw !== 'other' && request.leave_type !== 'other') ? `
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Days Requested</label>
															<p class="text-white">${request.days_requested} day(s)</p>
														</div>
													` : ''}
												</div>
											</div>
											
										</div>
										
										<!-- Conditional Details (Additional Information) - Full Width -->
										<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 mb-6" id="conditionalDetailsSection">
											<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
												<i class="fas fa-info-circle text-cyan-500 mr-3"></i>
												Additional Information
											</h4>
											<div id="conditionalDetailsContent">
													<!-- Vacation/Special Privilege Details -->
													<div id="vacationDetails" style="display: none;">
														<div class="space-y-3">
															<div>
																<label class="block text-sm font-semibold text-slate-300 mb-1">Location Type</label>
																<p class="text-white">${request.location_type ? (request.location_type === 'within_philippines' ? 'Within Philippines' : 'Outside Philippines') : 'N/A'}</p>
															</div>
															<div>
																<label class="block text-sm font-semibold text-slate-300 mb-1">Specific Address</label>
																<p class="text-slate-300">${request.location_specify || 'N/A'}</p>
															</div>
														</div>
													</div>
													
													<!-- Sick Leave Details -->
													<div id="sickDetails" style="display: none;">
														<div class="space-y-3">
															<div>
																<label class="block text-sm font-semibold text-slate-300 mb-1">Medical Condition</label>
																<p class="text-white">${request.medical_condition ? (request.medical_condition === 'in_hospital' ? 'In Hospital' : 'Out Patient') : 'N/A'}</p>
															</div>
															<div>
																<label class="block text-sm font-semibold text-slate-300 mb-1">Illness Specification</label>
																<p class="text-slate-300">${request.illness_specify || 'N/A'}</p>
															</div>
															${request.medical_certificate_path ? `
															<div>
																<label class="block text-sm font-semibold text-slate-300 mb-1">Medical Certificate</label>
																<div class="flex items-center space-x-3">
																	<a href="../../api/view_medical_certificate.php?file=${encodeURIComponent(request.medical_certificate_path.replace(/^.*uploads\/medical_certificates\//, ''))}" target="_blank" 
																	   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
																		<i class="fas fa-file-medical mr-2"></i>View Medical Certificate
																	</a>
																	<span class="text-slate-400 text-sm">Medical certificate attached</span>
																</div>
															</div>
															` : `
															<div>
																<label class="block text-sm font-semibold text-slate-300 mb-1">Medical Certificate</label>
																<p class="text-slate-400">No medical certificate provided</p>
															</div>
															`}
														</div>
													</div>
													
													<!-- Special Women Leave Details -->
													<div id="specialWomenDetails" style="display: none;">
														<div class="space-y-3">
															<div>
																<label class="block text-sm font-semibold text-slate-300 mb-1">Illness Specification</label>
																<p class="text-slate-300">${request.special_women_condition || 'N/A'}</p>
															</div>
														</div>
													</div>
													
													<!-- Study Leave Details -->
													<div id="studyDetails" style="display: none;">
														<div class="space-y-3">
															<div>
																<label class="block text-sm font-semibold text-slate-300 mb-1">Course/Program Type</label>
																<p class="text-white">${request.study_type ? (request.study_type === 'masters_degree' ? "Master's Degree" : 'BAR/Board Examination Review') : 'Not specified'}</p>
															</div>
														</div>
													</div>
												</div>
											</div>
										</div>
										
										<!-- Reason for Leave Section -->
										<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 mb-6" style="width: 100%;">
											<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
												<i class="fas fa-comment-alt text-purple-500 mr-3"></i>
												Reason for Leave
											</h4>
											<p class="text-slate-300 leading-relaxed mb-0">${request.reason}</p>
										</div>
										
										<!-- Late Justification (only for late applications) -->
										${request.is_late == 1 || request.is_late === true ? `
										<div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-6 mb-6">
											<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
												<i class="fas fa-exclamation-triangle text-orange-400 mr-3"></i>
												Late Justification
											</h4>
											<p class="text-slate-300 leading-relaxed bg-orange-500/5 p-4 rounded-lg">${request.late_justification || 'No justification provided'}</p>
										</div>
										` : ''}
										
										<!-- Rejection Options -->
										<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 mb-6" id="rejectionOptionsSection" style="display: none;">
											<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
												<i class="fas fa-times-circle text-red-500 mr-3"></i>
												Rejection Options
											</h4>
											
											<!-- Rejection Reason -->
											<div>
												<label class="block text-sm font-semibold text-slate-300 mb-2">Reason for Rejection:</label>
												<textarea id="rejectionReasonText" rows="3" 
														  class="w-full px-3 py-2 bg-slate-600 border border-slate-500 rounded-lg text-white focus:ring-2 focus:ring-red-500 focus:border-transparent"
														  placeholder="Please provide a reason for rejection..." required></textarea>
											</div>
										</div>
										
										<!-- Action Buttons -->
										<div class="flex justify-center space-x-4 pt-6 border-t border-slate-700/50">
											<button type="button" onclick="closeDepartmentApprovalModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-8 rounded-xl transition-colors">
												<i class="fas fa-times mr-2"></i>Cancel
											</button>
											
											<!-- Action Buttons -->
											<div id="initialActionButtons">
												<button onclick="approveRequest(${requestId})" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-8 rounded-xl transition-colors">
													<i class="fas fa-check mr-2"></i>Approve Request
												</button>
												<button onclick="showRejectionOptions(${requestId})" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-8 rounded-xl transition-colors">
													<i class="fas fa-times mr-2"></i>Reject Request
												</button>
											</div>
										</div>
									</div>
								</div>
							</div>
						`;

						// Remove existing modal if any
						const existingModal = document.getElementById('departmentApprovalModal');
						if (existingModal) {
							existingModal.remove();
						}

						// Add modal to body
						document.body.insertAdjacentHTML('beforeend', modalHtml);
						
						// Show conditional details based on leave type (use raw type for matching)
						const leaveTypeForMatching = request.leave_type_raw || request.leave_type;
						console.log('=== MODAL LOADED ===');
						console.log('Leave Type for matching:', leaveTypeForMatching);
						console.log('request.leave_type:', request.leave_type);
						console.log('request.leave_type_raw:', request.leave_type_raw);
						console.log('request.study_type:', request.study_type);
						console.log('Full request data:', request);
						
						// Call the function to show conditional details
						setTimeout(() => {
							showConditionalDetails(leaveTypeForMatching);
						}, 100);
					} else {
						console.error('API Error:', data);
						showStyledAlert('Error loading leave request details: ' + (data.message || 'Unknown error'), 'error');
					}
				})
				.catch(error => {
					console.error('Fetch Error:', error);
					showStyledAlert('Error loading leave request details: ' + error.message, 'error');
				});
		}

		function closeDepartmentApprovalModal() {
			const modal = document.getElementById('departmentApprovalModal');
			if (modal) {
				modal.remove();
			}
		}

		function showRejectionOptions(requestId) {
			// Close the details modal first
			closeDepartmentApprovalModal();
			
			// Small delay to let the modal close, then show styled rejection modal
			setTimeout(() => {
				showRejectionReasonModal('Department Head Rejection', function(reason) {
					if (reason) {
						submitDeptRejection(requestId, reason);
					}
				});
			}, 100);
		}
		
		function submitDeptRejection(requestId, reason) {
			// Create and show processing modal
			const processingModalHtml = `
				<div id="processingModal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
					<div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl max-w-md w-full mx-4">
						<div class="p-6 text-center">
							<div class="mb-4">
								<div class="inline-flex items-center justify-center w-16 h-16 bg-slate-700 rounded-full mb-4">
									<i class="fas fa-spinner fa-spin text-blue-400 text-2xl"></i>
								</div>
								<h3 class="text-lg font-semibold text-white mb-2">Processing Your Rejection</h3>
								<p class="text-slate-300 mb-4">Please wait while we process the leave request rejection...</p>
							</div>
							<div class="flex items-center justify-center space-x-2 text-sm text-slate-400">
								<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce"></div>
								<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
								<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
							</div>
						</div>
					</div>
				</div>
			`;
			
			// Remove existing processing modal if any
			const existingProcessingModal = document.getElementById('processingModal');
			if (existingProcessingModal) {
				existingProcessingModal.remove();
			}
			
			// Add processing modal to body
			document.body.insertAdjacentHTML('beforeend', processingModalHtml);
			
			// Submit the form after delay
			setTimeout(() => {
				const form = document.createElement('form');
				form.method = 'POST';
				form.action = 'approve_leave.php';
				
				const requestIdInput = document.createElement('input');
				requestIdInput.type = 'hidden';
				requestIdInput.name = 'request_id';
				requestIdInput.value = requestId;
				
				const actionInput = document.createElement('input');
				actionInput.type = 'hidden';
				actionInput.name = 'action';
				actionInput.value = 'reject';
				
				const reasonInput = document.createElement('input');
				reasonInput.type = 'hidden';
				reasonInput.name = 'reason';
				reasonInput.value = reason;
				
				form.appendChild(requestIdInput);
				form.appendChild(actionInput);
				form.appendChild(reasonInput);
				document.body.appendChild(form);
				form.submit();
			}, 500);
		}

		function approveRequest(requestId) {
			// Close the details modal first
			closeDepartmentApprovalModal();
			
			// Small delay to let the modal close, then show confirm
			setTimeout(() => {
				showStyledConfirm('Are you sure you want to approve this leave request?', function(confirmed) {
					if (confirmed) {
						// Create and show processing modal
					const processingModalHtml = `
						<div id="processingModal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50 elms-modal-overlay">
							<div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl max-w-md w-full mx-4 elms-modal">
								<div class="p-6 text-center">
									<div class="mb-4">
										<div class="inline-flex items-center justify-center w-16 h-16 bg-slate-700 rounded-full mb-4">
											<i class="fas fa-spinner fa-spin text-blue-400 text-2xl"></i>
										</div>
										<h3 class="text-lg font-semibold text-white mb-2">Processing Your Approval</h3>
										<p class="text-slate-300 mb-4">Please wait while we process the leave request approval...</p>
									</div>
									<div class="flex items-center justify-center space-x-2 text-sm text-slate-400">
										<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce"></div>
										<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
										<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
									</div>
								</div>
							</div>
						</div>
					`;
					
					// Remove existing processing modal if any
					const existingProcessingModal = document.getElementById('processingModal');
					if (existingProcessingModal) {
						existingProcessingModal.remove();
					}
					
					// Add processing modal to body
					document.body.insertAdjacentHTML('beforeend', processingModalHtml);
					
					// Add rendering delay: keep approval modal visible for 1.5 seconds
					setTimeout(() => {
						// Hide the approval modal
						closeDepartmentApprovalModal();
						
						// Submit the form after delay
						const form = document.createElement('form');
						form.method = 'POST';
						form.action = 'approve_leave.php';
						
						const requestIdInput = document.createElement('input');
						requestIdInput.type = 'hidden';
						requestIdInput.name = 'request_id';
						requestIdInput.value = requestId;
						
						const actionInput = document.createElement('input');
						actionInput.type = 'hidden';
						actionInput.name = 'action';
						actionInput.value = 'approve';
						
						form.appendChild(requestIdInput);
						form.appendChild(actionInput);
						document.body.appendChild(form);
						form.submit();
					}, 1500); // Keep approval modal visible for 1.5 seconds
				}
			}, 'info', 'Approve Leave Request', 'Yes, Approve', 'Cancel');
			}, 100);
		}

		
		// Function to show conditional details based on leave type
		function showConditionalDetails(leaveType) {
			console.log('=== showConditionalDetails called ===');
			console.log('Original leaveType:', leaveType);
			
			// Hide all conditional detail sections first
			const vacationDetails = document.getElementById('vacationDetails');
			const sickDetails = document.getElementById('sickDetails');
			const specialWomenDetails = document.getElementById('specialWomenDetails');
			const studyDetails = document.getElementById('studyDetails');
			const conditionalSection = document.getElementById('conditionalDetailsSection');
			
			console.log('studyDetails element exists:', !!studyDetails);
			console.log('conditionalSection element exists:', !!conditionalSection);
			
			if (vacationDetails) vacationDetails.style.display = 'none';
			if (sickDetails) sickDetails.style.display = 'none';
			if (specialWomenDetails) specialWomenDetails.style.display = 'none';
			if (studyDetails) studyDetails.style.display = 'none';
			
			// Normalize leave type to lowercase for comparison
			const normalizedType = leaveType.toLowerCase().replace(/\s+/g, '_').replace(/[()]/g, '');
			console.log('Normalized type:', normalizedType);
			
			// Also check if it contains the leave type name (fallback for formatted names)
			const leaveTypeLower = leaveType.toLowerCase();
			console.log('leaveTypeLower:', leaveTypeLower);
			console.log('Contains study?:', leaveTypeLower.includes('study'));
			
			// Show relevant section based on leave type
			if (normalizedType === 'vacation' || normalizedType === 'special_privilege' || leaveTypeLower.includes('vacation')) {
				if (vacationDetails) {
					vacationDetails.style.display = 'block';
					console.log('Showing vacation details');
				}
			} else if (normalizedType === 'sick' || leaveTypeLower.includes('sick')) {
				if (sickDetails) {
					sickDetails.style.display = 'block';
					console.log('Showing sick details');
				}
			} else if (normalizedType === 'special_women' || leaveTypeLower.includes('special') && leaveTypeLower.includes('women')) {
				if (specialWomenDetails) {
					specialWomenDetails.style.display = 'block';
					console.log('Showing special women details');
				}
			} else if (normalizedType === 'study' || normalizedType.includes('study') || leaveTypeLower.includes('study')) {
				if (studyDetails) {
					studyDetails.style.display = 'block';
					console.log('Showing study details for type:', leaveType);
				}
			}
			
			// Check which section is visible
			console.log('Visible sections:');
			if (vacationDetails && vacationDetails.style.display === 'block') console.log('- Vacation');
			if (sickDetails && sickDetails.style.display === 'block') console.log('- Sick');
			if (specialWomenDetails && specialWomenDetails.style.display === 'block') console.log('- Special Women');
			if (studyDetails && studyDetails.style.display === 'block') console.log('- Study');
			
			// Hide the entire conditional section if no relevant details
			const hasRelevantDetails = ['vacation', 'special_privilege', 'sick', 'special_women', 'study'].includes(normalizedType) ||
										leaveTypeLower.includes('vacation') || leaveTypeLower.includes('sick') || 
										leaveTypeLower.includes('study') || leaveTypeLower.includes('special');
			console.log('Has relevant details:', hasRelevantDetails);
			if (conditionalSection) {
				conditionalSection.style.display = hasRelevantDetails ? 'block' : 'none';
				console.log('Conditional section display:', conditionalSection.style.display);
			} else {
				console.log('!!! conditionalSection element NOT FOUND !!!');
			}
		}

		// View More and Show Less functionality
		document.addEventListener('DOMContentLoaded', function() {
			const loadMoreBtn = document.getElementById('loadMoreBtn');
			const showLessBtn = document.getElementById('showLessBtn');
			const additionalRequestsContainer = document.getElementById('additionalRequests');
			const tableBody = document.querySelector('tbody');
			
			if (loadMoreBtn && showLessBtn && additionalRequestsContainer && tableBody) {
				let currentOffset = 5; // Start from offset 5 (since we initially show 5)
				let isLoading = false;
				let totalAdditionalRows = 0; // Store count of additional rows for show less functionality
				
				loadMoreBtn.addEventListener('click', async function() {
					if (isLoading) return;
					
					isLoading = true;
					loadMoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
					loadMoreBtn.disabled = true;
					
					try {
						const response = await fetch(`../api/get_more_requests.php?offset=${currentOffset}`);
						const data = await response.json();
						
						if (data.success && data.html) {
							// Append new rows to the existing table
							tableBody.insertAdjacentHTML('beforeend', data.html);
							
							// Store the count of additional rows added
							totalAdditionalRows += data.count;
							
							// Update offset for next request
							currentOffset += data.count;
							
							// Show the Show Less button
							showLessBtn.classList.remove('hidden');
							
							// Hide the View More button if no more requests
							if (!data.hasMore || data.count < 10) {
								loadMoreBtn.style.display = 'none';
							} else {
								// Update button text with remaining count
								const remainingCount = data.hasMore ? 'more' : '0';
								loadMoreBtn.innerHTML = `<i class="fas fa-plus mr-2"></i>View More (${remainingCount} more)`;
								loadMoreBtn.disabled = false;
							}
						} else {
							console.error('Failed to load more requests:', data.message);
							loadMoreBtn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Error loading requests';
						}
					} catch (error) {
						console.error('Error:', error);
						loadMoreBtn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Error loading requests';
					}
					
					isLoading = false;
				});
				
				showLessBtn.addEventListener('click', function() {
					// Remove all additional rows by removing the last N rows
					const allRows = tableBody.querySelectorAll('tr');
					const rowsToRemove = totalAdditionalRows;
					
					// Remove rows from the end
					for (let i = allRows.length - 1; i >= allRows.length - rowsToRemove; i--) {
						if (allRows[i]) {
							allRows[i].remove();
						}
					}
					
					// Reset the counter
					totalAdditionalRows = 0;
					
					// Reset offset
					currentOffset = 5;
					
					// Hide Show Less button
					showLessBtn.classList.add('hidden');
					
					// Show View More button again
					loadMoreBtn.style.display = 'inline-flex';
					loadMoreBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>View More (<?php echo $total_pending - $initial_limit; ?> more)';
					loadMoreBtn.disabled = false;
				});
			}
		});

		// Live Clock Function
        function updateClock() {
            const now = new Date();
            let h = now.getHours();
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12; h = h ? h : 12;
            const hm = `${String(h).padStart(2, '0')}:${m}`;
            const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            const dateString = `${days[now.getDay()]}, ${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
            const elHM = document.getElementById('clockHM');
            const elS = document.getElementById('clockSec');
            const elAP = document.getElementById('clockAmPm');
            const elDate = document.getElementById('clockDateChip');
            if (elHM) elHM.textContent = hm;
            if (elS) elS.textContent = s;
            if (elAP) elAP.textContent = ampm;
            if (elDate) elDate.textContent = dateString;
        }
		
		// Update clock immediately and then every second
		updateClock();
		setInterval(updateClock, 1000);
	</script>
	<script src="../../../../assets/js/modal-alert.js"></script>

<?php include '../../../../includes/department_footer.php'; ?>
