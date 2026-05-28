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
