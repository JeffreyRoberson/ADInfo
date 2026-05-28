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

        // Add click handler for computer rows
        if (this.table.id === 'computersTable') {
            this.allRows.forEach(row => {
                row.addEventListener('click', (e) => this.handleComputerRowClick(e));
                row.style.cursor = 'pointer';
            });
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

    handleComputerRowClick(e) {
        // Don't trigger on header click
        if (e.target.closest('.sortable-header')) return;

        const row = e.currentTarget;
        const computerName = row.getAttribute('data-computer-name');
        
        // Show ping status modal
        showPingModal(computerName);
    }
}

function showPingModal(computerName) {
    // Remove existing modal if any
    const existingModal = document.getElementById('pingModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Create modal
    const modal = document.createElement('div');
    modal.id = 'pingModal';
    modal.className = 'ping-modal';
    
    const modalContent = document.createElement('div');
    modalContent.className = 'ping-modal-content';
    
    const header = document.createElement('div');
    header.className = 'ping-modal-header';
    
    const title = document.createElement('h3');
    title.textContent = `Ping Status: ${computerName}`;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ping-close-btn';
    closeBtn.textContent = '✕';
    closeBtn.onclick = () => modal.remove();
    
    header.appendChild(title);
    header.appendChild(closeBtn);
    
    const body = document.createElement('div');
    body.className = 'ping-modal-body';
    body.innerHTML = '<p class="ping-loading">🔄 Checking host status...</p>';
    
    modalContent.appendChild(header);
    modalContent.appendChild(body);
    modal.appendChild(modalContent);
    
    // Click outside modal to close
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    document.body.appendChild(modal);
    
    // Fetch ping status
    fetchPingStatus(computerName, body);
}

function fetchPingStatus(computerName, bodyElement) {
    fetch(`/api/ping?hostname=${encodeURIComponent(computerName)}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayPingResult(bodyElement, data);
        } else {
            displayPingError(bodyElement, data.error || 'Unknown error occurred');
        }
    })
    .catch(error => {
        console.error('Ping error:', error);
        displayPingError(bodyElement, error.message);
    });
}

function displayPingResult(bodyElement, data) {
    const isOnline = data.online || data.status === 'online';
    const statusClass = isOnline ? 'ping-status-online' : 'ping-status-offline';
    const statusIcon = isOnline ? '✓' : '✗';
    const statusText = isOnline ? 'ONLINE' : 'OFFLINE';
    
    bodyElement.innerHTML = `
        <div class="ping-result">
            <div class="ping-status ${statusClass}">
                <span class="ping-icon">${statusIcon}</span>
                <span class="ping-text">${statusText}</span>
            </div>
            <div class="ping-details">
                <p><strong>Hostname:</strong> ${escapeHtml(data.hostname)}</p>
            </div>
            <div class="ping-timestamp">
                <small>Checked at: ${new Date().toLocaleTimeString()}</small>
            </div>
        </div>
    `;
}

function displayPingError(bodyElement, errorMessage) {
    bodyElement.innerHTML = `
        <div class="ping-error">
            <p class="ping-error-icon">⚠️</p>
            <p class="ping-error-message"><strong>Error:</strong> ${escapeHtml(errorMessage)}</p>
            <small>Unable to determine host status</small>
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('[App] Initializing');
    
    const usersManager = new TableManager('usersTable', 'userSearchInput');
    const computersManager = new TableManager('computersTable', 'computerSearchInput');

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
