# 6. Budget Module (v1.1 Redesign)

The Budget Module supports a hierarchical, nested structure that allows organizing allocations by month, grouping them by expense categories, and setting individual line item limits.

## List Screen
**Columns:** Name (Period, dates), Allocated (Sum of items), Actual Spent (Sum of linked transactions), % Used (Overall progress), Remaining, Status (Under Limit/Near Limit/Over Budget), Actions (View Details / Clone / Delete)
- **Clone Action**: Allows copying an entire budget template (along with all groups and line items) into a new month/period. The user can adjust group names, item names, and allocations in a large modal before saving.

## Detailed View Screen (Tree-Grid)
- Displays the budget allocations as a nested tree:
  - **Group Name** (e.g., `server`, `hr`)
    - **Line Item** (e.g., `digital ocean-apptimus`, `salary`) — showing Allocated Amount, Actual Spent, Usage progress bar, and Remaining balance for each item.
- **Log Expense**: A quick record button next to each line item in the tree grid allows logging a transaction pre-linked to that specific budget item.
- **Transactions Log**: Displays a table list of all expense transactions linked to the budget items.
- **Dynamic Loading**: The detailed view relies on a JSON endpoint (`GET /budgets/{id}/json`) to load and render the hierarchical tree-grid data asynchronously. Linking a transaction is handled via dedicated endpoints (e.g., `POST /budgets/{id}/transactions`).
## Create / Clone Budget (Hierarchical Form Builder)
- **Name**: Budget container name (e.g., `2026/04`).
- **Currency & Period**: Budget currency (e.g., USD, LKR) and period (Monthly, Quarterly, etc.).
- **Start Date & End Date**: Effective range of the budget.
- **Allocation Builder**: An interactive builder:
  - **Add Group**: Creates a new group container (e.g., `server`).
  - **Add Item**: Creates a line item under a group, with fields for Item Name and Allocated Amount.

## Linking Transactions (Searchable Component)
- Transactions can be linked to a specific `budget_item_id` using the searchable `x-budget-item-selector` component. 
- It displays options in a hierarchical breadcrumb format: `[Budget Name] > [Group Name] > [Item Name]`.
- When a transaction is linked, the budget details are automatically appended to the transaction description (e.g., `Description (2026/04 > server > digital ocean-apptimus)`) for visibility on the main Ledger Transactions sheet.
- Supports light and dark mode styling.
