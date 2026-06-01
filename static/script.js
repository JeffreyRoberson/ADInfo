class TableManager {
    constructor(tableId, searchInputId) {
        this.table = document.getElementById(tableId);
        this.tableBody = document.getElementById(tableId + 'Body');
        this.searchInput = document.getElementById(searchInputId);
        this.sortState = {};
        this.allRows = [];
        
        this.init();
    }

    init() {
        this.allRows = Array.from(this.tableBody.querySelectorAll('tr'));

        this.table.querySelectorAll('.sortable-header').forEach(header => {
            header.addEventListener('click', (e) => this.handleSort(e));
        });

        if (this.searchInput) {
            this.searchInput.addEventListener('keyup', () => this.handleSearch());
        }
    }

    handleSort(event) {
        const header = event.target.closest('.sortable-header');
        if (!header) return;

        const column = header.getAttribute('data-column');
        const type = header.getAttribute('data-type');

        const currentSort = header.getAttribute('data-sort') || 'asc';
        const newSort = currentSort === 'asc' ? 'desc' : 'asc';

        this.table.querySelectorAll('.sortable-header').forEach(h => {
            h.classList.remove('sort-asc', 'sort-desc');
            h.removeAttribute('data-sort');
        });

        header.classList.add(`sort-${newSort}`);
        header.setAttribute('data-sort', newSort);

        this.sortTable(column, type, newSort);
    }

    sortTable(column, type, direction) {
        const sortedRows = [...this.allRows].sort((rowA, rowB) => {
            const attrName = column.replace(/_/g, '-');
            let valueA = rowA.getAttribute(`data-${attrName}`);
            let valueB = rowB.getAttribute(`data-${attrName}`);

            if (type === 'number') {
                valueA = parseFloat(valueA) || 0;
                valueB = parseFloat(valueB) || 0;
                return direction === 'asc' ? valueA - valueB : valueB - valueA;
            } else if (type === 'date') {
                valueA = new Date(valueA).getTime() || 0;
                valueB = new Date(valueB).getTime() || 0;
                return direction === 'asc' ? valueA - valueB : valueB - valueA;
            } else {
                valueA = (valueA || '').toLowerCase();
                valueB = (valueB || '').toLowerCase();
                return direction === 'asc' 
                    ? valueA.localeCompare(valueB) 
                    : valueB.localeCompare(valueA);
            }
        });

        sortedRows.forEach(row => this.tableBody.appendChild(row));
        this.handleSearch();
    }

    handleSearch() {
        const searchTerm = this.searchInput.value.toLowerCase();
        let visibleCount = 0;

        this.allRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matches = text.includes(searchTerm);

            row.classList.toggle('hidden', !matches);
            if (matches) visibleCount++;
        });

        this.updateRowCount(visibleCount);
    }

    updateRowCount(visibleCount) {
        const prefix = this.table.id === 'usersTable' ? 'user' : 'computer';
        const showingElement = document.getElementById(`${prefix}-showing`);
        const totalElement = document.getElementById(`${prefix}-total`);

        if (showingElement) showingElement.textContent = visibleCount;
        if (totalElement) totalElement.textContent = this.allRows.length;
    }
}

/**
 * Server-side Ping Manager
 * Fetches ping status for all computers from the server
 */
class PingManager {
    constructor() {
        this.computerRows = new Map();
        this.basePath = this.getBasePath();
        this.apiEndpoint = this.basePath + 'api.php';
    }

    getBasePath() {
        // Get the pathname and extract the base directory
        const pathname = window.location.pathname;
        console.log(`[PingManager] Full pathname: ${pathname}`);
        
        // If pathname is /adinfo/index.php or /adinfo/, we want /adinfo/
        const match = pathname.match(/^(.*?\/adinfo\/)/) || pathname.match(/^(.*?\/[^/]+\/?)$/);
        const basePath = match ? match[1] : '/';
        console.log(`[PingManager] Base path: ${basePath}`);
        return basePath;
    }

    init() {
        // Collect all computer rows
        const computersTable = document.getElementById('computersTable');
        if (!computersTable) return;

        const rows = computersTable.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const computerName = row.getAttribute('data-computer-name');
            if (computerName) {
                this.computerRows.set(computerName, row);
            }
        });

        console.log(`[PingManager] Initialized with ${this.computerRows.size} computers`);
        console.log(`[PingManager] API Endpoint: ${this.apiEndpoint}`);

        // Start pinging all computers
        this.pingAllComputers();
    }

    pingAllComputers() {
        console.log(`[PingManager] Starting ping checks for ${this.computerRows.size} computers`);
        
        this.computerRows.forEach((row, computerName) => {
            this.pingHost(computerName, row);
        });
    }

    pingHost(computerName, row) {
        const statusCell = row.querySelector('.host-status-cell');
        if (!statusCell) {
            console.warn(`[PingManager] No status cell found for ${computerName}`);
            return;
        }

        // Build API URL using dedicated api.php endpoint
        const apiUrl = `${this.apiEndpoint}?action=ping&hostname=${encodeURIComponent(computerName)}`;
        console.log(`[PingManager] Pinging ${computerName} at ${apiUrl}`);

        fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        })
        .then(response => {
            console.log(`[PingManager] Response status for ${computerName}: ${response.status}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log(`[PingManager] Ping result for ${computerName}:`, data);
            if (data.success) {
                this.updateStatusDisplay(row, statusCell, data);
            } else {
                this.updateStatusError(row, statusCell, data.error || 'Ping failed');
            }
        })
        .catch(error => {
            console.error(`[PingManager] Ping error for ${computerName}:`, error);
            this.updateStatusError(row, statusCell, error.message);
        });
    }

    updateStatusDisplay(row, statusCell, data) {
        const isOnline = data.online || data.status === 'online';
        const statusClass = isOnline ? 'online' : 'offline';
        const statusIcon = isOnline ? '✓' : '✗';
        const statusText = isOnline ? 'ONLINE' : 'OFFLINE';
        
        // Update data attribute for sorting
        row.setAttribute('data-online-status', statusText.toLowerCase());
        
        // Update status badge with cursor pointer
        statusCell.innerHTML = `
            <span class="status-badge ${statusClass}" style="cursor: pointer;" title="Click for details">${statusIcon} ${statusText}</span>
        `;
    }

    updateStatusError(row, statusCell, errorMessage) {
        // Update data attribute
        row.setAttribute('data-online-status', 'error');
        
        // Update status badge
        statusCell.innerHTML = `
            <span class="status-badge error" title="${escapeHtml(errorMessage)}" style="cursor: pointer;">? ERROR</span>
        `;
    }
}

/**
 * Click-to-Ping Manager
 * Handles click events on computer names and status badges to show detailed ping info
 */
class ClickPingManager {
    constructor(basePath) {
        this.basePath = basePath;
        this.apiEndpoint = this.basePath + 'api.php';
        this.modal = document.getElementById('pingModal');
        this.closeBtn = document.querySelector('.close');
        this.init();
    }

    init() {
        // Set up close button
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', () => this.closeModal());
        }

        // Set up click handlers for computer names
        document.addEventListener('click', (e) => {
            const computerNameCell = e.target.closest('.clickable-computer');
            if (computerNameCell) {
                const computerName = computerNameCell.textContent.trim();
                this.handleComputerClick(computerName);
            }

            // Also handle status badge clicks
            const statusBadge = e.target.closest('.status-badge');
            if (statusBadge && statusBadge.closest('#computersTable')) {
                const row = statusBadge.closest('tr');
                const computerName = row.getAttribute('data-computer-name');
                this.handleComputerClick(computerName);
            }
        });

        // Close modal when clicking outside of it
        if (this.modal) {
            window.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.closeModal();
                }
            });
        }
    }

    handleComputerClick(computerName) {
        console.log(`[ClickPingManager] Clicked on computer: ${computerName}`);
        this.pingComputerDetailed(computerName);
    }

    pingComputerDetailed(computerName) {
        const apiUrl = `${this.apiEndpoint}?action=ping-detailed&hostname=${encodeURIComponent(computerName)}`;
        console.log(`[ClickPingManager] Requesting detailed ping: ${apiUrl}`);

        const modalBody = document.getElementById('modalBody');
        if (modalBody) {
            modalBody.innerHTML = '<p class="loading">🔄 Pinging ' + escapeHtml(computerName) + '...</p>';
        }

        fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        })
        .then(response => {
            console.log(`[ClickPingManager] Response status: ${response.status}`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log(`[ClickPingManager] Detailed ping result:`, data);
            this.showPingResults(computerName, data);
        })
        .catch(error => {
            console.error(`[ClickPingManager] Error:`, error);
            this.showPingError(computerName, error.message);
        });
    }

    showPingResults(computerName, data) {
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');

        if (modalTitle) {
            modalTitle.textContent = `Ping Details: ${escapeHtml(computerName)}`;
        }

        if (modalBody) {
            let html = '<div class="ping-details">';

            if (data.success) {
                const status = data.online ? 'ONLINE ✓' : 'OFFLINE ✗';
                const statusClass = data.online ? 'online' : 'offline';
                
                html += `<div class="detail-row">
                    <strong>Status:</strong>
                    <span class="status-badge ${statusClass}">${status}</span>
                </div>`;

                if (data.response_time !== undefined) {
                    html += `<div class="detail-row">
                        <strong>Response Time:</strong>
                        <span>${data.response_time}ms</span>
                    </div>`;
                }

                if (data.ttl !== undefined) {
                    html += `<div class="detail-row">
                        <strong>TTL:</strong>
                        <span>${data.ttl}</span>
                    </div>`;
                }

                if (data.bytes !== undefined) {
                    html += `<div class="detail-row">
                        <strong>Bytes:</strong>
                        <span>${data.bytes}</span>
                    </div>`;
                }

                if (data.timestamp) {
                    html += `<div class="detail-row">
                        <strong>Timestamp:</strong>
                        <span>${data.timestamp}</span>
                    </div>`;
                }

                if (data.raw_output) {
                    html += `<div class="detail-row full-width">
                        <strong>Raw Output:</strong>
                        <pre>${escapeHtml(data.raw_output)}</pre>
                    </div>`;
                }
            } else {
                html += `<div class="detail-row error">
                    <strong>Error:</strong>
                    <span>${escapeHtml(data.error || 'Ping failed')}</span>
                </div>`;

                if (data.raw_output) {
                    html += `<div class="detail-row full-width">
                        <strong>Output:</strong>
                        <pre>${escapeHtml(data.raw_output)}</pre>
                    </div>`;
                }
            }

            html += `<div class="detail-row">
                <button class="retry-btn" onclick="clickPingManager.pingComputerDetailed('${escapeHtml(computerName)}')">🔄 Retry Ping</button>
            </div>`;

            html += '</div>';
            modalBody.innerHTML = html;
        }

        this.openModal();
    }

    showPingError(computerName, errorMessage) {
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');

        if (modalTitle) {
            modalTitle.textContent = `Ping Details: ${escapeHtml(computerName)}`;
        }

        if (modalBody) {
            modalBody.innerHTML = `
                <div class="ping-details">
                    <div class="detail-row error">
                        <strong>Error:</strong>
                        <span>${escapeHtml(errorMessage)}</span>
                    </div>
                    <div class="detail-row">
                        <button class="retry-btn" onclick="clickPingManager.pingComputerDetailed('${escapeHtml(computerName)}')">🔄 Retry Ping</button>
                    </div>
                </div>
            `;
        }

        this.openModal();
    }

    openModal() {
        if (this.modal) {
            this.modal.style.display = 'block';
        }
    }

    closeModal() {
        if (this.modal) {
            this.modal.style.display = 'none';
        }
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Global reference to ClickPingManager for retry button
let clickPingManager;

document.addEventListener('DOMContentLoaded', function() {
    console.log('[App] Initializing');
    
    const usersManager = new TableManager('usersTable', 'userSearchInput');
    const computersManager = new TableManager('computersTable', 'computerSearchInput');

    // Initialize server-side ping manager
    const pingManager = new PingManager();
    pingManager.init();

    // Initialize click-to-ping manager
    clickPingManager = new ClickPingManager(pingManager.basePath);

    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');

            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            this.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });

    setInterval(function() {
        location.reload();
    }, 300000);
});
