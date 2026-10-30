# AASMS focused diagrams

## Use case

```mermaid
flowchart LR
    Farmer[Verified farmer]
    MAO[MAO reviewer / distributor]
    subgraph AASMS["AASMS inside AgriConnect ERP"]
        Programs((Manage assistance programs))
        Apply((Submit eligible application))
        Review((Approve / reject / return))
        Allocate((Confirm approved quantity and item))
        Distribute((Distribute approved assistance))
        History((View status and distribution history))
        Report((Export distribution report))
    end
    MAO --> Programs
    Farmer --> Apply
    Farmer --> History
    MAO --> Review
    MAO --> Allocate
    MAO --> Distribute
    MAO --> Report
    FFRMS[Verified FFRMS record] --> Apply
    AISMS[Available AISMS batch] --> Distribute
```

## Context and Level 1 DFD

```mermaid
flowchart LR
    Farmer[Verified farmer]
    MAO[MAO staff]
    P1[Evaluate eligibility]
    P2[Create application]
    P3[Review and allocate]
    P4[Distribute approved quantity]
    D1[(programs and eligibility rules)]
    D2[(applications and status history)]
    D3[(distributions)]
    D4[(AISMS batches and movements)]
    N[(notifications)]
    Farmer --> P1 --> D1
    P1 --> D2
    Farmer --> P2 --> D2
    MAO --> P3
    P3 <--> D2
    P3 --> N
    MAO --> P4
    P4 <--> D2
    P4 <--> D4
    P4 --> D3
    P4 --> N
```

## Focused ERD

```mermaid
erDiagram
    ASSISTANCE_PROGRAMS ||--o{ ASSISTANCE_APPLICATIONS : receives
    SUPPLY_ITEMS ||--o{ ASSISTANCE_PROGRAMS : supplies
    FARMERS ||--o{ ASSISTANCE_APPLICATIONS : submits
    USERS ||--o{ ASSISTANCE_APPLICATIONS : reviews
    ASSISTANCE_APPLICATIONS ||--o{ ASSISTANCE_STATUS_HISTORIES : changes
    USERS ||--o{ ASSISTANCE_STATUS_HISTORIES : changes
    ASSISTANCE_APPLICATIONS ||--o{ ASSISTANCE_DISTRIBUTIONS : fulfills
    SUPPLY_BATCHES ||--o{ ASSISTANCE_DISTRIBUTIONS : releases
    USERS ||--o{ ASSISTANCE_DISTRIBUTIONS : processes
    ASSISTANCE_DISTRIBUTIONS }o--|| INVENTORY_MOVEMENTS : references
    ASSISTANCE_PROGRAMS {
        bigint id PK
        string name
        string category
        json eligibility_rules
        bigint supply_item_id FK
        decimal allocation_limit
        string status
        date opens_on
        date closes_on
    }
    ASSISTANCE_APPLICATIONS {
        bigint id PK
        bigint program_id FK
        bigint farmer_id FK
        boolean eligibility_passed
        string status
        bigint reviewed_by FK
        decimal approved_quantity
        bigint approved_supply_item_id FK
        datetime allocation_confirmed_at
    }
    ASSISTANCE_STATUS_HISTORIES {
        bigint id PK
        bigint application_id FK
        string from_status
        string to_status
        bigint changed_by FK
        text notes
    }
    ASSISTANCE_DISTRIBUTIONS {
        bigint id PK
        bigint application_id FK
        bigint supply_batch_id FK
        string reference UK
        string operation_key UK
        decimal quantity
        string status
        bigint processed_by FK
    }
```

## UML class view

```mermaid
classDiagram
    class AASMSController {
        +index()
        +storeProgram()
        +review()
        +distribute()
        +apply()
        +resubmit()
        +farmerPrograms()
    }
    class ProgramEligibilityService {
        +check(program, farmer)
        +requireEligible(program, farmer)
    }
    class AssistanceService {
        +status(application, status, userId, notes, quantity, itemId)
        +distribute(application, data, userId, inventory)
    }
    class AssistanceApplication
    class AssistanceDistribution
    class InventoryService
    AASMSController --> ProgramEligibilityService : checks
    AASMSController --> AssistanceService : transitions
    AssistanceService --> AssistanceApplication : updates
    AssistanceService --> AssistanceDistribution : creates
    AssistanceService --> InventoryService : release
```

## Activity — approved distribution

```mermaid
flowchart TD
    S([Start]) --> Verify{Farmer verified?}
    Verify -- No --> Deny[403 / no application access]
    Verify -- Yes --> Rules{Eligibility rules pass?}
    Rules -- No --> Error[Validation error]
    Rules -- Yes --> Submitted[Create submitted application]
    Submitted --> Review[MAO reviews]
    Review --> Decision{Decision}
    Decision -- rejected/returned --> Notify[Persist history and notify]
    Decision -- approved --> Allocation[Store approved item and quantity]
    Allocation --> Match{Matching, unexpired batch available?}
    Match -- No --> Hold[Reject distribution]
    Match -- Yes --> Release[Call InventoryService.release]
    Release --> Once{Existing operation key?}
    Once -- Yes --> Existing[Return existing distribution]
    Once -- No --> Save[Create distribution; complete application]
    Save --> Notify
```

## Sequence

```mermaid
sequenceDiagram
    actor Farmer
    actor MAO
    participant Controller as AASMSController
    participant Eligibility as ProgramEligibilityService
    participant Service as AssistanceService
    participant Inventory as InventoryService
    participant DB as Shared database

    Farmer->>Controller: POST farmer.assistance.apply
    Controller->>Eligibility: requireEligible(program, farmer)
    Eligibility->>DB: Read farmer, farms and rules
    Controller->>DB: Insert submitted application
    MAO->>Controller: POST modules.aasms.review(approved)
    Controller->>Service: status(application, approved, allocation)
    Service->>DB: Update application and write status history
    MAO->>Controller: POST modules.aasms.distribute
    Controller->>Service: distribute(application, batch, quantity, key)
    Service->>Inventory: release(batch, quantity, key)
    Inventory->>DB: Decrement batch and create release movement
    Service->>DB: Insert distribution and set application completed
    Controller-->>MAO: Redirect with confirmation
```
