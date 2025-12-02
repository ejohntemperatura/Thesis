/**
 * Styled Modal Alert System - Replaces browser alert() with styled modals
 */

// Create modal container on page load
document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('styledAlertModal')) {
        const modalHTML = `
            <div id="styledAlertModal" class="fixed inset-0 z-[9999] hidden">
                <div class="fixed inset-0 bg-black/70 backdrop-blur-md" onclick="closeStyledAlert()"></div>
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="bg-slate-800 rounded-2xl border border-slate-700 shadow-2xl w-full max-w-md transform transition-all">
                        <div class="p-6">
                            <div id="styledAlertHeader" class="flex items-start gap-4">
                                <div id="styledAlertIcon" class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center">
                                    <i class="fas fa-info-circle text-xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 id="styledAlertTitle" class="text-lg font-semibold text-white mb-2">Alert</h3>
                                    <div id="styledAlertMessage" class="text-slate-300 text-sm whitespace-pre-line"></div>
                                </div>
                            </div>
                            <div id="styledAlertContentOnly" class="hidden">
                                <h3 id="styledAlertTitleCentered" class="text-lg font-semibold text-white mb-4 text-center">Alert</h3>
                                <div id="styledAlertMessageCentered" class="text-slate-300 text-sm"></div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-slate-700/30 border-t border-slate-700 rounded-b-2xl flex justify-end">
                            <button onclick="closeStyledAlert()" class="px-4 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg font-medium transition-colors">
                                OK
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
});

/**
 * Show styled alert modal
 * @param {string} message - The message to display (can contain \n for line breaks)
 * @param {string} type - Type of alert: 'info', 'success', 'warning', 'error'
 * @param {string} title - Optional custom title
 * @param {boolean} isHtml - If true, message is treated as HTML
 */
function showStyledAlert(message, type = 'info', title = null, isHtml = false) {
    const modal = document.getElementById('styledAlertModal');
    const headerSection = document.getElementById('styledAlertHeader');
    const contentOnlySection = document.getElementById('styledAlertContentOnly');
    const iconContainer = document.getElementById('styledAlertIcon');
    const titleEl = document.getElementById('styledAlertTitle');
    const messageEl = document.getElementById('styledAlertMessage');
    const titleCenteredEl = document.getElementById('styledAlertTitleCentered');
    const messageCenteredEl = document.getElementById('styledAlertMessageCentered');
    
    if (!modal) {
        // Fallback to native alert if modal not found
        alert(message);
        return;
    }
    
    // Set icon and colors based on type
    const types = {
        info: {
            icon: 'fa-info-circle',
            bg: 'bg-blue-500/20',
            color: 'text-blue-400',
            title: 'Information'
        },
        success: {
            icon: 'fa-check-circle',
            bg: 'bg-green-500/20',
            color: 'text-green-400',
            title: 'Success'
        },
        warning: {
            icon: 'fa-exclamation-triangle',
            bg: 'bg-yellow-500/20',
            color: 'text-yellow-400',
            title: 'Warning'
        },
        error: {
            icon: 'fa-times-circle',
            bg: 'bg-red-500/20',
            color: 'text-red-400',
            title: 'Error'
        }
    };
    
    const config = types[type] || types.info;
    
    // Handle message - use centered layout for HTML content, icon layout for plain text
    if (isHtml) {
        // Use centered layout without icon for rich HTML content
        headerSection.classList.add('hidden');
        contentOnlySection.classList.remove('hidden');
        titleCenteredEl.textContent = title || config.title;
        messageCenteredEl.innerHTML = message;
    } else {
        // Use icon layout for plain text
        headerSection.classList.remove('hidden');
        contentOnlySection.classList.add('hidden');
        iconContainer.className = `flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center ${config.bg}`;
        iconContainer.innerHTML = `<i class="fas ${config.icon} text-xl ${config.color}"></i>`;
        titleEl.textContent = title || config.title;
        // Convert newlines to <br> and escape HTML
        const escaped = message.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        messageEl.innerHTML = escaped.replace(/\n/g, '<br>');
    }
    
    modal.classList.remove('hidden');
}

/**
 * Close styled alert modal
 */
function closeStyledAlert() {
    const modal = document.getElementById('styledAlertModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeStyledAlert();
        closeStyledConfirm(false);
    }
});

/**
 * Styled Confirm Modal - Replaces browser confirm() with styled modals
 */

// Store callback for confirm modal
let confirmCallback = null;

// Create confirm modal on page load
document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('styledConfirmModal')) {
        const confirmModalHTML = `
            <div id="styledConfirmModal" class="fixed inset-0 z-[9999] hidden">
                <div class="fixed inset-0 bg-black/70 backdrop-blur-md" onclick="closeStyledConfirm(false)"></div>
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="bg-slate-800 rounded-2xl border border-slate-700 shadow-2xl w-full max-w-md transform transition-all">
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div id="styledConfirmIcon" class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center bg-yellow-500/20">
                                    <i class="fas fa-question-circle text-xl text-yellow-400"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 id="styledConfirmTitle" class="text-lg font-semibold text-white mb-2">Confirm</h3>
                                    <div id="styledConfirmMessage" class="text-slate-300 text-sm whitespace-pre-line"></div>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-slate-700/30 border-t border-slate-700 rounded-b-2xl flex justify-end gap-3">
                            <button onclick="closeStyledConfirm(false)" class="px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white rounded-lg font-medium transition-colors">
                                Cancel
                            </button>
                            <button onclick="closeStyledConfirm(true)" id="styledConfirmOkBtn" class="px-4 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg font-medium transition-colors">
                                OK
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', confirmModalHTML);
    }
});

/**
 * Show styled confirm modal
 * @param {string} message - The message to display
 * @param {function} callback - Callback function that receives true (OK) or false (Cancel)
 * @param {string} type - Type of confirm: 'warning', 'danger', 'info'
 * @param {string} title - Optional custom title
 * @param {string} okText - Optional custom OK button text
 * @param {string} cancelText - Optional custom Cancel button text
 */
function showStyledConfirm(message, callback, type = 'warning', title = null, okText = 'OK', cancelText = 'Cancel') {
    const modal = document.getElementById('styledConfirmModal');
    const iconContainer = document.getElementById('styledConfirmIcon');
    const titleEl = document.getElementById('styledConfirmTitle');
    const messageEl = document.getElementById('styledConfirmMessage');
    const okBtn = document.getElementById('styledConfirmOkBtn');
    const cancelBtn = modal.querySelector('button:first-of-type');
    
    if (!modal) {
        // Fallback to native confirm if modal not found
        const result = confirm(message);
        if (callback) callback(result);
        return;
    }
    
    // Store callback
    confirmCallback = callback;
    
    // Set icon and colors based on type
    const types = {
        warning: {
            icon: 'fa-exclamation-triangle',
            bg: 'bg-yellow-500/20',
            color: 'text-yellow-400',
            title: 'Confirm Action',
            btnClass: 'bg-yellow-600 hover:bg-yellow-700'
        },
        danger: {
            icon: 'fa-exclamation-circle',
            bg: 'bg-red-500/20',
            color: 'text-red-400',
            title: 'Are you sure?',
            btnClass: 'bg-red-600 hover:bg-red-700'
        },
        info: {
            icon: 'fa-question-circle',
            bg: 'bg-blue-500/20',
            color: 'text-blue-400',
            title: 'Confirm',
            btnClass: 'bg-primary hover:bg-primary/90'
        }
    };
    
    const config = types[type] || types.warning;
    
    iconContainer.className = `flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center ${config.bg}`;
    iconContainer.innerHTML = `<i class="fas ${config.icon} text-xl ${config.color}"></i>`;
    titleEl.textContent = title || config.title;
    messageEl.textContent = message;
    
    // Update button text and style
    okBtn.textContent = okText;
    okBtn.className = `px-4 py-2 ${config.btnClass} text-white rounded-lg font-medium transition-colors`;
    cancelBtn.textContent = cancelText;
    
    modal.classList.remove('hidden');
}

/**
 * Close styled confirm modal and execute callback
 * @param {boolean} result - true if OK was clicked, false if Cancel
 */
function closeStyledConfirm(result) {
    const modal = document.getElementById('styledConfirmModal');
    if (modal) {
        modal.classList.add('hidden');
    }
    
    if (confirmCallback) {
        confirmCallback(result);
        confirmCallback = null;
    }
}

/**
 * Styled Rejection Reason Modal - Shows a modal with textarea for rejection reason
 */

// Store callback for rejection modal
let rejectionCallback = null;

/**
 * Show styled rejection reason modal
 * @param {string} title - Title for the modal (e.g., "Department Head Rejection", "Director Rejection")
 * @param {function} callback - Callback function that receives the reason text or null if cancelled
 */
function showRejectionReasonModal(title, callback) {
    // Remove existing modal if any
    const existingModal = document.getElementById('styledRejectionModal');
    if (existingModal) existingModal.remove();
    
    rejectionCallback = callback;
    
    const modalHtml = `
        <div id="styledRejectionModal" class="fixed inset-0 z-[9999]">
            <div class="fixed inset-0 bg-black/70 backdrop-blur-md" onclick="closeRejectionModal(false)"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="bg-slate-800 rounded-2xl border border-slate-700 shadow-2xl w-full max-w-md">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center bg-red-500/20">
                                <i class="fas fa-times-circle text-xl text-red-400"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-semibold text-white mb-2">${title}</h3>
                                <p class="text-slate-300 text-sm mb-4">Please provide a reason for rejecting this leave request:</p>
                                <textarea id="styledRejectionReason" rows="3" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-400 focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Enter rejection reason..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-slate-700/30 border-t border-slate-700 rounded-b-2xl flex justify-end gap-3">
                        <button onclick="closeRejectionModal(false)" class="px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white rounded-lg font-medium transition-colors">
                            Cancel
                        </button>
                        <button onclick="submitRejectionReason()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                            Reject
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Focus the textarea
    setTimeout(() => {
        const textarea = document.getElementById('styledRejectionReason');
        if (textarea) textarea.focus();
    }, 100);
}

/**
 * Close rejection modal
 * @param {boolean} submit - Whether to submit or cancel
 */
function closeRejectionModal(submit) {
    const modal = document.getElementById('styledRejectionModal');
    if (modal) modal.remove();
    
    if (!submit && rejectionCallback) {
        rejectionCallback(null);
        rejectionCallback = null;
    }
}

/**
 * Submit rejection reason
 */
function submitRejectionReason() {
    const textarea = document.getElementById('styledRejectionReason');
    const reason = textarea ? textarea.value.trim() : '';
    
    if (!reason) {
        showStyledAlert('Please provide a reason for rejection.', 'warning');
        return;
    }
    
    const modal = document.getElementById('styledRejectionModal');
    if (modal) modal.remove();
    
    if (rejectionCallback) {
        rejectionCallback(reason);
        rejectionCallback = null;
    }
}

// Close rejection modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const rejectionModal = document.getElementById('styledRejectionModal');
        if (rejectionModal) {
            closeRejectionModal(false);
        }
    }
});
