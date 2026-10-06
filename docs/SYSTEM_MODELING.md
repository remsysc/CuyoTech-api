# CuyoTech SSIS — System Modeling & Architecture Specification

> **Target Platform:** Notion & Internal Engineering Docs  
> **System:** CuyoTech University Student Services Information System (SSIS)  
> **Tech Stack:** Laravel 13, PHP 8.3+, React (Inertia.js), Laravel Sanctum, SQLite / Relational DB  
> **Source of Truth:** Aligned with `docs/PRD.md` and `docs/SPEC.md` v1.2

---

## 1. Executive Summary & Context (C4 Level 1)

CuyoTech SSIS is an integrated academic and student services information platform designed to automate university operations across student enrollment, grading, cashiering, departmental clearances, and official document issuance.

```mermaid
flowchart TD
    subgraph Actors["Stakeholders & Actors"]
        Student["🎓 Student\n(Self-service portal)"]
        Registrar["📋 Registrar Staff\n(Enrollment & Records)"]
        Cashier["💰 Cashier\n(Tuition & Receipts)"]
        DeptStaff["🏢 Department Staff\n(Clearance Evaluator)"]
        Admin["⚙️ Administrator\n(User & Role Management)"]
    end

    subgraph SystemBoundary["CuyoTech SSIS Boundary"]
        SSIS["CuyoTech SSIS Application\n(Unified Laravel 13 + Inertia/React SPA)"]
    end

    Student -->|View grades, subjects, request records| SSIS
    Registrar -->|Manage enrollments, encode grades, process TOR/COR| SSIS
    Cashier -->|Record payments, generate ORs, issue receipts| SSIS
    DeptStaff -->|Approve/deny department clearances| SSIS
    Admin -->|Provision accounts, assign roles, inspect audit logs| SSIS
```

---

## 2. Container & Subsystem Architecture (C4 Level 2)

The application follows a monolithic architecture combining client-side Inertia.js React components and server-side Laravel 13 API endpoints within a single deployment unit.

```mermaid
flowchart TB
    Browser["Web Browser Client\n(React + Inertia SPA)"]

    subgraph AppMonolith["Laravel Application Container"]
        Router["HTTP Router\n(/api/* and /receipts/*)"]

        subgraph SecurityLayer["Security & Auth Pipeline"]
            Sanctum["Sanctum Token Guard"]
            ActiveCheck["Live is_active Validator"]
            RoleCheck["Live Role & Ownership Gate"]
        end

        subgraph CoreModules["Modular Domain Controllers"]
            AuthModule["Auth Controller\n(Login / Logout)"]
            StudentModule["Student Portal Controller\n(Profile, Subjects, Grades)"]
            RegistrarModule["Registrar Controller\n(Enrollments, Rosters, Grades)"]
            CashierModule["Cashier Controller\n(Payments, Receipts)"]
            ClearanceModule["Clearance Controller\n(Dept Approvals)"]
            DocModule["Document Request Controller\n(TOR, COR, Certification)"]
            AdminModule["Admin Controller\n(User CRUD, Audit Logs)"]
        end

        subgraph BusinessServices["Domain Services & Support"]
            MoneyEngine["Money & Currency Support\n(Integer Centavo Calculations)"]
            DocGate["Document Guard Engine\n(Clearance & Zero-Balance Verifier)"]
        end
    end

    Database[(Relational Database\nSQLite / MySQL / PostgreSQL)]

    Browser -->|HTTPS JSON & Page Visits| Router
    Router --> SecurityLayer
    SecurityLayer --> CoreModules
    RegistrarModule --> MoneyEngine
    CashierModule --> MoneyEngine
    DocModule --> DocGate
    CoreModules -->|ACID Transactions & Eloquent ORM| Database
```

---

## 3. Actor & Role-Based Access Control (RBAC) Matrix

| Module / Operation     | Route / Action                                |     Student      | Registrar | Cashier | Department Staff | Admin |
| :--------------------- | :-------------------------------------------- | :--------------: | :-------: | :-----: | :--------------: | :---: |
| **Authentication**     | `POST /api/login`, `POST /api/logout`         |        ✅        |    ✅     |   ✅    |        ✅        |  ✅   |
| **Student Profile**    | `GET /api/student/profile`                    | ✅ _(Self only)_ |    ❌     |   ❌    |        ❌        |  ❌   |
| **Subjects & Grades**  | `GET /api/student/subjects`, `/grades`        | ✅ _(Self only)_ |    ❌     |   ❌    |        ❌        |  ❌   |
| **Enrollment**         | `POST /api/registrar/enrollments`             |        ❌        |    ✅     |   ❌    |        ❌        |  ❌   |
| **Grade Encoding**     | `PATCH /api/registrar/enrollments/{id}/grade` |        ❌        |    ✅     |   ❌    |        ❌        |  ❌   |
| **Class Roster**       | `GET /api/registrar/courses/{id}/roster`      |        ❌        |    ✅     |   ❌    |        ❌        |  ❌   |
| **Record Payment**     | `POST /api/cashier/payments`                  |        ❌        |    ❌     |   ✅    |        ❌        |  ❌   |
| **Payment History**    | `GET /api/cashier/students/{id}/payments`     |        ❌        |    ❌     |   ✅    |        ❌        |  ✅   |
| **Printable Receipt**  | `GET /receipts/{or_number}`                   |    ✅ _(Own)_    |    ❌     |   ✅    |        ❌        |  ✅   |
| **Dept Clearances**    | `GET /api/department/clearances`              |        ❌        |    ❌     |   ❌    | ✅ _(Own Dept)_  |  ❌   |
| **Student Clearances** | `GET /api/students/{id}/clearances`           | ✅ _(Self only)_ |    ✅     |   ❌    |        ✅        |  ✅   |
| **Review Clearance**   | `PATCH /api/department/clearances/{id}`       |        ❌        |    ❌     |   ❌    | ✅ _(Own Dept)_  |  ❌   |
| **Request Document**   | `POST /api/documents/requests`                |  ✅ _(Guarded)_  |    ❌     |   ❌    |        ❌        |  ❌   |
| **Process Document**   | `PATCH /api/documents/requests/{id}/status`   |        ❌        |    ✅     |   ❌    |        ❌        |  ❌   |
| **Manage Users**       | `POST /api/admin/users`, `PATCH ...`          |        ❌        |    ❌     |   ❌    |        ❌        |  ✅   |
| **Audit Logs**         | `GET /api/admin/audit-logs`                   |        ❌        |    ❌     |   ❌    |        ❌        |  ✅   |

> **Security Invariant:** Roles and `is_active` status are evaluated dynamically on **every HTTP request** from the database record, not from claims cached in bearer tokens. A deactivated account is cut off immediately mid-session.

---

## 4. Domain Data Model & Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    User ||--o| Student : "has profile"
    User ||--o{ AuditLog : "records actions"
    User ||--o{ Payment : "cashier issues"
    User ||--o{ Clearance : "staff reviews"
    User ||--o{ DocumentRequest : "registrar processes"
    Department ||--o{ User : "assigns staff"
    Department ||--o{ Course : "offers"
    Department ||--o{ Clearance : "evaluates"
    Student ||--o{ Enrollment : "takes"
    Course ||--o{ Enrollment : "contains"
    Student ||--o{ Payment : "makes"
    Student ||--o{ Clearance : "clears"
    Student ||--o{ DocumentRequest : "submits"

    User {
        bigint id PK
        string name
        string email UK
        string password
        enum role "student, registrar, cashier, department_staff, admin"
        bigint department_id FK "nullable (for dept staff)"
        boolean is_active "default true"
        timestamp created_at
        timestamp updated_at
    }

    Student {
        bigint id PK
        bigint user_id FK,UK "cascades on delete"
        string student_number UK
        string program
        tinyint year_level "1 to 5"
        enum status "active, on_leave, graduated"
        bigint balance_centavos "cents owed, default 0"
        timestamp created_at
        timestamp updated_at
    }

    Department {
        bigint id PK
        string name
        string code UK
        timestamp created_at
        timestamp updated_at
    }

    Course {
        bigint id PK
        bigint department_id FK
        string code UK
        string title
        tinyint units "1 to 6"
        timestamp created_at
        timestamp updated_at
    }

    Enrollment {
        bigint id PK
        bigint student_id FK
        bigint course_id FK
        string school_year "e.g. 2026-2027"
        tinyint semester "1=1st, 2=2nd, 3=Summer"
        decimal grade "1.00 to 5.00, nullable"
        enum status "enrolled, completed, dropped"
        timestamp created_at
        timestamp updated_at
    }

    Payment {
        bigint id PK
        bigint student_id FK
        bigint cashier_id FK
        bigint amount_centavos "strictly > 0"
        string or_number UK "e.g. OR-YYYYMMDD-XXXX"
        enum payment_type "tuition, misc_fee, document_fee"
        timestamp paid_at
        timestamp created_at
        timestamp updated_at
    }

    Clearance {
        bigint id PK
        bigint student_id FK
        bigint department_id FK
        string school_year
        tinyint semester
        enum status "pending, approved, denied"
        string remarks "nullable"
        bigint reviewed_by FK "nullable"
        timestamp reviewed_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    DocumentRequest {
        bigint id PK
        bigint student_id FK
        enum type "tor, cor, certification"
        string purpose "required, non-blank"
        enum status "pending, processing, ready, released, rejected"
        timestamp requested_at
        timestamp released_at "nullable"
        bigint processed_by FK "nullable"
        timestamp created_at
        timestamp updated_at
    }

    AuditLog {
        bigint id PK
        bigint actor_id FK
        string action "e.g. user.created"
        string target_type "e.g. user"
        bigint target_id
        json changes "before/after payload"
        timestamp created_at
    }
```

### Data Dictionary & Integrity Rules

1. **Monetary Precision Rule:** Floating point numbers are prohibited for financial calculations. All balances, charges, and payments are stored and manipulated as 64-bit integer centavos (`amount_centavos`, `balance_centavos`). UI formatting (`₱1,500.00`) is handled exclusively via formatting accessors.
2. **Tuition Charge Formula:** `charge_applied_centavos = course.units × config('fees.rate_per_unit_centavos')`.
3. **Composite Uniqueness Constraints:**
    - `Enrollment`: `UNIQUE(student_id, course_id, school_year, semester)`
    - `Clearance`: `UNIQUE(student_id, department_id, school_year, semester)`
    - `Student`: `UNIQUE(student_number)`, `UNIQUE(user_id)`
    - `Payment`: `UNIQUE(or_number)`

---

## 5. Dynamic Workflows & Sequence Models

### Workflow A: Live Authentication & Immediate Revocation

```mermaid
sequenceDiagram
    autonumber
    actor Client as User / Browser
    participant Router as Laravel Route & Sanctum
    participant DB as User Database
    participant App as Protected Controller

    Client->>Router: GET /api/... (Header: Bearer Token)
    Router->>DB: Resolve PersonalAccessToken
    alt Invalid or Expired Token
        Router-->>Client: 401 UNAUTHENTICATED
    else Token Found
        Router->>DB: SELECT is_active, role FROM users WHERE id = user_id
        alt is_active == false
            Router-->>Client: 403 ACCOUNT_DEACTIVATED (Immediate Kickout)
        else Role != Required Route Role
            Router-->>Client: 403 UNAUTHORIZED_ROLE
        else Verified
            Router->>App: Forward Request
            App-->>Client: 200 OK (Domain Payload)
        end
    end
```

---

### Workflow B: Course Enrollment & Balance Assessment Transaction

```mermaid
sequenceDiagram
    autonumber
    actor Registrar as Registrar Staff
    participant API as Enrollment Controller
    participant DB as Database Transaction

    Registrar->>API: POST /api/registrar/enrollments {student_id, course_id, school_year, semester}
    API->>DB: BEGIN TRANSACTION
    API->>DB: Verify student exists and course exists
    API->>DB: Check UNIQUE(student_id, course_id, school_year, semester)
    alt Already Enrolled
        API->>DB: ROLLBACK
        API-->>Registrar: 409 ALREADY_ENROLLED
    else Valid New Enrollment
        API->>DB: INSERT INTO enrollments (status = 'enrolled')
        Note over API,DB: Calculate charge: course.units * rate_per_unit_centavos
        API->>DB: UPDATE students SET balance_centavos = balance_centavos + charge WHERE id = student_id
        API->>DB: COMMIT TRANSACTION
        API-->>Registrar: 201 Created {id, status: "enrolled", charge_applied_centavos}
    end
```

---

### Workflow C: Cashier Payment & OR Receipt Generation

```mermaid
sequenceDiagram
    autonumber
    actor Cashier as Cashier Staff
    actor Student as Student
    participant API as Payment Controller
    participant DB as Database Transaction
    participant Web as Receipt Viewer (/receipts/{or_number})

    Cashier->>API: POST /api/cashier/payments {student_id, amount_centavos, payment_type}
    alt amount_centavos <= 0
        API-->>Cashier: 400 INVALID_AMOUNT
    else Valid Amount
        API->>DB: BEGIN TRANSACTION
        API->>DB: Lock student row & generate sequential unique or_number
        API->>DB: INSERT INTO payments (or_number, amount_centavos, cashier_id, ...)
        API->>DB: UPDATE students SET balance_centavos = balance_centavos - amount_centavos
        API->>DB: COMMIT TRANSACTION
        API-->>Cashier: 201 Created {or_number, receipt_url: "/receipts/{or_number}"}
    end

    Note over Student,Web: Printable HTML Receipt Access
    Student->>Web: GET /receipts/{or_number} (Bearer Token)
    Web->>DB: SELECT payment, student WHERE or_number = ?
    alt Student is Owner OR Caller is Cashier/Admin
        Web-->>Student: 200 OK (Printable HTML Document)
    else Other Student
        Web-->>Student: 403 Forbidden
    end
```

---

### Workflow D: Document Request & Clearance Verification Pipeline

```mermaid
sequenceDiagram
    autonumber
    actor Student as Student
    actor Registrar as Registrar
    participant DocAPI as Document Controller
    participant DB as Database

    Student->>DocAPI: POST /api/documents/requests {type: "tor", purpose: "Employment"}
    DocAPI->>DB: Check open requests for same type (status: pending/processing/ready)
    alt Open Request Exists
        DocAPI-->>Student: 409 DUPLICATE_REQUEST
    else No Duplicate
        DocAPI->>DB: SELECT balance_centavos FROM students WHERE id = student_id
        alt balance_centavos > 0
            DocAPI-->>Student: 422 OUTSTANDING_BALANCE
        else Balance is 0
            DocAPI->>DB: SELECT status FROM clearances WHERE student_id = ? AND term = current
            alt Any clearance is NOT 'approved'
                DocAPI-->>Student: 422 CLEARANCE_INCOMPLETE
            else All Clearances Approved
                DocAPI->>DB: INSERT INTO document_requests (status = 'pending')
                DocAPI-->>Student: 201 Created {id, status: "pending"}
            end
        end
    end

    Note over Registrar,DocAPI: Fulfill Document Request
    Registrar->>DocAPI: PATCH /api/documents/requests/{id}/status {status: "released"}
    DocAPI->>DB: Re-verify balance == 0 AND all clearances == 'approved'
    alt Conditions Violated
        DocAPI-->>Registrar: 422 OUTSTANDING_BALANCE / CLEARANCE_INCOMPLETE
    else Conditions Met
        DocAPI->>DB: UPDATE document_requests SET status = 'released', released_at = NOW()
        DocAPI-->>Registrar: 200 OK {id, status: "released"}
    end
```

---

## 6. State Machine & Lifecycle Models

### 6.1 Document Request State Lifecycle (FR-19)

```mermaid
stateDiagram-v2
    [*] --> Pending : Student submits request\n(Guarded by balance=0 & all clearances approved)

    Pending --> Processing : Registrar starts preparation
    Pending --> Rejected : Registrar rejects request

    Processing --> Ready : Document ready for pickup/dispatch
    Processing --> Rejected : Registrar rejects request

    Ready --> Released : Released to student\n(Re-checks balance=0 & all clearances approved)

    Released --> [*] : Terminal State
    Rejected --> [*] : Terminal State
```

_State Rules:_

- Once in `ready`, `released`, or `rejected`, transitions to any previous state return `409 INVALID_TRANSITION`.
- Transition into `released` unconditionally re-runs clearance and balance invariants.

---

### 6.2 Student Enrollment Lifecycle (FR-5, FR-6)

```mermaid
stateDiagram-v2
    [*] --> Enrolled : Registrar enrolls student\n(Atomic tuition charge applied)
    Enrolled --> Completed : Registrar encodes final grade\n(Grade: 1.00 - 5.00)
    Enrolled --> Dropped : Enrollment dropped\n(Future scope: requires tuition refund reversal)
    Completed --> Completed : Grade revised by Registrar\n(Last-write-wins)
    Completed --> [*]
    Dropped --> [*]
```

---

### 6.3 Department Clearance Lifecycle (FR-11)

```mermaid
stateDiagram-v2
    [*] --> Pending : Generated for student & department
    Pending --> Approved : Department staff approves
    Pending --> Denied : Department staff denies
    Approved --> Denied : Re-evaluated by Department staff\n(Last-write-wins)
    Denied --> Approved : Re-evaluated by Department staff\n(Last-write-wins)
```

---

## 7. Key Architectural Invariants & Failure Modes

| Subsystem          | Critical Invariant                                                                    | Failure Mode Prevented                                                                        | Architectural Mitigation                                                                                        |
| :----------------- | :------------------------------------------------------------------------------------ | :-------------------------------------------------------------------------------------------- | :-------------------------------------------------------------------------------------------------------------- |
| **Authentication** | Session validation checks live database row every request.                            | Deactivated user or demoted admin retains access until token expires.                         | Custom middleware checks `users.is_active` and `users.role` on every pipeline cycle.                            |
| **Finance**        | Tuition charge and enrollment record must occur in the exact same DB transaction.     | Student is enrolled for free if app crashes before charge, or charged without being enrolled. | Enclosed in `DB::transaction()`. Any failure rolls back both the enrollment and the balance update.             |
| **Payments**       | OR numbers must be strictly unique and sequential under concurrent cashier writes.    | Race condition where concurrent payments produce conflicting OR numbers.                      | Database transaction with row lock / pessimistic locking on the sequence counter.                               |
| **Documents**      | Students can never initiate or receive documents with debts or unresolved clearances. | Premature release of transcript to delinquent student.                                        | Double-gating: evaluated on `POST /requests` and re-evaluated on `PATCH /status` (`released`).                  |
| **Clearance**      | Staff can only modify clearance records matching their own assigned `department_id`.  | Staff in College of Science approving Library or Accounting clearances.                       | Server-side query scoping: `department_id` is derived from `auth()->user()->department_id`, never from payload. |

---

## 8. Notion Import Guide

To paste this document into **Notion**:

1. Copy the raw markdown content of this file.
2. In Notion, create a new page named **"CuyoTech SSIS — System Modeling"**.
3. Paste directly into the Notion page body.
4. Notion will automatically convert:
    - Headings to H1, H2, and H3 blocks.
    - Tables to native interactive Notion database/tables.
    - Code blocks with `mermaid` syntax to **native interactive visual Mermaid diagram blocks**.
