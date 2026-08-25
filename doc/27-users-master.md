# 27. Users Master Page

## Overview
Provides administrative management of system users directly within the **Master Data** module. Administrators can create, view, edit, soft-delete, and toggle the active/inactive status of users, with support for role assignment, department linkage, and audit logging.

---

## 1. Where it lives

- **Sidebar Navigation:** Master Data sidebar (`/master/users`)
- **Secondary Sidebar Link:** Added to `resources/views/masters/_sidebar.blade.php` under "Users"
- **Access Control:** Restricted to Admin / Super Admin roles

---

## 2. List Screen (`/master/users`)

**Columns:**
1. **Avatar / Initial:** User profile photo or initials badge
2. **Name:** Full Name (`users.name`)
3. **Email:** User email address (`users.email`)
4. **Role:** Assigned system role (e.g. `Admin`, `Manager`, `Accountant`, `Staff`, `Viewer`)
5. **Department:** Linked Department name (`departments.name` or "All / Global")
6. **Phone:** Contact phone number
7. **Status:** Active / Inactive badge with quick status toggle switch
8. **Created At:** Registration / Created date
9. **Actions:**
   - **Edit:** Opens the Edit User modal
   - **Toggle Status:** Instant activation/deactivation action
   - **Delete:** Soft-deletes the user (with confirmation modal)

**Filters & Search:**
- **Search:** Search by Name, Email, or Phone
- **Role Filter:** Dropdown filter by Role (`All`, `Admin`, `Manager`, `Accountant`, `Staff`, `Viewer`)
- **Department Filter:** Dropdown filter by Department
- **Status Filter:** Dropdown filter (`All`, `Active`, `Inactive`)

---

## 3. Create / Add User

**Trigger:** "Add New User" button on header (`openCreateModal()`)

**Form Fields:**
- **Name:** *(Text, Required)* — Full name of the user
- **Email:** *(Email, Required, Unique)* — Valid email address for login
- **Password:** *(Password, Required)* — Minimum 8 characters
- **Confirm Password:** *(Password, Required)* — Must match password
- **Role:** *(Dropdown, Required)* — `Admin`, `Manager`, `Accountant`, `Staff`, `Viewer`
- **Department:** *(Dropdown, Optional)* — Select department or leave as `Global / Company-wide`
- **Phone:** *(Text, Optional)* — Contact phone number
- **Status:** *(Toggle / Select, Default: `Active`)* — `Active` or `Inactive`

**Validation Rules:**
```php
[
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email|max:255',
    'password' => 'required|string|min:8|confirmed',
    'role' => 'required|string|in:admin,manager,accountant,staff,viewer',
    'department_id' => 'nullable|exists:departments,id',
    'phone' => 'nullable|string|max:20',
    'is_active' => 'boolean',
]
```

---

## 4. Edit User

**Trigger:** "Edit" button in row actions (`openEditModal(user)`)

**Form Fields:**
- **Name:** *(Text, Required)*
- **Email:** *(Email, Required, Unique except current user ID)*
- **Role:** *(Dropdown, Required)*
- **Department:** *(Dropdown, Optional)*
- **Phone:** *(Text, Optional)*
- **Status:** *(Select / Toggle)* — `Active` / `Inactive`
- **New Password:** *(Password, Optional)* — Leave blank to keep current password
- **Confirm New Password:** *(Password, Optional)* — Required if new password is provided

**Validation Rules:**
```php
[
    'name' => 'required|string|max:255',
    'email' => 'required|email|max:255|unique:users,email,' . $id,
    'password' => 'nullable|string|min:8|confirmed',
    'role' => 'required|string|in:admin,manager,accountant,staff,viewer',
    'department_id' => 'nullable|exists:departments,id',
    'phone' => 'nullable|string|max:20',
    'is_active' => 'boolean',
]
```

---

## 5. Status Change (Activate / Deactivate)

**Trigger:** Status toggle switch on list table or via status change button/action.

**Behavior & Business Rules:**
- **Immediate Effect:** If a user is marked `Inactive` (`is_active = 0`), they are immediately blocked from logging in. Any active sessions for that user should be invalidated.
- **Self-Deactivation Guard:** Logged-in users **cannot** deactivate their own account.
- **Sole Admin Guard:** The system prevents deactivating the last active `Admin` user.
- **Audit Logging:** Every status change is logged via `ActivityLogService::logUpdate()`.

---

## 6. Delete User

**Trigger:** "Delete" button in row actions.

**Rules & Protections:**
- **Soft Delete:** Uses `deleted_at` timestamp so historical audit trails, invoices created by the user, and transaction histories remain intact.
- **Self-Deletion Guard:** Users **cannot** delete their own account.
- **Sole Admin Guard:** Cannot delete the last remaining `Admin`.
- **Linked Data Check:** If the user is referenced as a Department Head or Project Manager, prompt with a confirmation warning to reassign those entities.

---

## 7. Data Model & Database Schema

Ensure `users` table includes necessary management columns:

```sql
ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'staff';
ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL;
ALTER TABLE users ADD COLUMN department_id BIGINT UNSIGNED NULL;
ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT 1;
ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL;
```

---

## 8. Technical Architecture & File Structure

| Layer | File Path | Responsibility |
|---|---|---|
| **Route** | `routes/web.php` | `Route::resource('master/users', UserController::class);`<br>`Route::patch('master/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);` |
| **Controller** | `app/Http/Controllers/UserController.php` | Handles `index`, `store`, `update`, `destroy`, and `toggleStatus` |
| **Model** | `app/Models/User.php` | Adds `SoftDeletes`, fillable attributes (`role`, `phone`, `department_id`, `is_active`, `avatar`), and relationships |
| **View** | `resources/views/masters/users.blade.php` | List view with Search, Filters, Modals (Create, Edit), and Status Toggles |
| **Sidebar** | `resources/views/masters/_sidebar.blade.php` | Adds `<a href="/master/users">Users</a>` navigation item |
| **Activity Log** | `App\Services\ActivityLogService` | Logs User create, update, delete, and status change events |
