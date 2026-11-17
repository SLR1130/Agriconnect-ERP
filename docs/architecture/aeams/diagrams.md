# AEAMS focused diagrams

## Use case

```mermaid
flowchart LR
    Farmer[Verified farmer]
    Staff[MAO / equipment personnel]
    Procurement[ABPMS procurement personnel]
    Auditor[Authorized viewer]
    subgraph AEAMS["AEAMS inside AgriConnect ERP"]
        Catalog((Register and view assets))
        Request((Request equipment))
        Review((Approve / reject request))
        Issue((Issue equipment))
        Return((Return equipment))
        Maintenance((Manage maintenance))
        History((View equipment history))
    end
    Farmer --> Catalog
    Farmer --> Request
    Farmer --> History
    Staff --> Catalog
    Staff --> Review
    Staff --> Issue
    Staff --> Return
    Staff --> Maintenance
    Auditor --> History
    Procurement --> Catalog
    Procurement --> Link[Link physically received item]
    Link --> Catalog
```

## Context and Level 1 DFD

```mermaid
flowchart LR
    Farmer[Verified farmer]
    Staff[Equipment staff]
    Procurement[ABPMS]
    P1[Check verified farmer and asset availability]
    P2[Create / review reservation]
    P3[Issue and return asset]
    P4[Record maintenance]
    D1[(farmers)]
    D2[(equipment assets and categories)]
    D3[(requests and transactions)]
    D4[(maintenance records)]
    Farmer --> P1 --> D1
    P1 <--> D2
    Farmer --> P2 --> D3
    Staff --> P2
    Staff --> P3
    P3 <--> D2
    P3 <--> D3
    Staff --> P4 --> D4
    P4 --> D2
    Procurement --> D2
```

## Focused ERD

```mermaid
erDiagram
    EQUIPMENT_CATEGORIES ||--o{ EQUIPMENT_ASSETS : classifies
    USERS ||--o{ EQUIPMENT_ASSETS : supervises
    FARMERS ||--o{ EQUIPMENT_REQUESTS : submits
    USERS ||--o{ EQUIPMENT_REQUESTS : requests
    EQUIPMENT_ASSETS ||--o{ EQUIPMENT_REQUESTS : reserved
    USERS ||--o{ EQUIPMENT_REQUESTS : reviews
    EQUIPMENT_ASSETS ||--o{ EQUIPMENT_MAINTENANCES : needs
    USERS ||--o{ EQUIPMENT_MAINTENANCES : reports
    EQUIPMENT_ASSETS ||--o{ EQUIPMENT_TRANSACTIONS : logs
    EQUIPMENT_REQUESTS ||--o{ EQUIPMENT_TRANSACTIONS : explains
    USERS ||--o{ EQUIPMENT_TRANSACTIONS : performs
    EQUIPMENT_CATEGORIES {
        bigint id PK
        string name UK
        boolean is_active
    }
    EQUIPMENT_ASSETS {
        bigint id PK
        string asset_code UK
        string name
        bigint category_id FK
        date acquired_on
        decimal acquisition_cost
        string condition
        string availability_status
    }
    EQUIPMENT_REQUESTS {
        bigint id PK
        string reference UK
        bigint farmer_id FK
        bigint requested_by FK
        bigint equipment_asset_id FK
        datetime schedule_start
        datetime schedule_end
        string status
        bigint reviewed_by FK
        string idempotency_key UK
    }
    EQUIPMENT_MAINTENANCES {
        bigint id PK
        bigint equipment_asset_id FK
        bigint reported_by FK
        string status
        decimal cost
        datetime completed_at
    }
    EQUIPMENT_TRANSACTIONS {
        bigint id PK
        bigint equipment_asset_id FK
        bigint equipment_request_id FK
        bigint performed_by FK
        string action
        string condition
    }
```

## UML class view

```mermaid
classDiagram
    class AEAMSController {
        +index()
        +show(asset)
        +storeAsset()
        +storeRequest()
        +review()
        +issue()
        +returnEquipment()
        +startMaintenance()
        +completeMaintenance()
    }
    class EquipmentService {
        +registerAsset(data, actor)
        +requestForUser(actor, data)
        +review(request, status, notes, actor)
        +issue(request, condition, actor)
        +return(request, condition, maintenance, notes, actor)
        +startMaintenance(asset, data, actor)
        +completeMaintenance(maintenance, condition, actor)
    }
    class EquipmentAsset
    class EquipmentRequest
    class EquipmentTransaction
    AEAMSController --> EquipmentService
    EquipmentService --> EquipmentAsset
    EquipmentService --> EquipmentRequest
    EquipmentService --> EquipmentTransaction
```

## Activity — request, issue, return

```mermaid
flowchart TD
    S([Start]) --> Verify{Verified farmer?}
    Verify -- No --> Deny[Reject request]
    Verify -- Yes --> Available{Asset serviceable and schedule free?}
    Available -- No --> Error[Validation error]
    Available -- Yes --> Pending[Create pending request]
    Pending --> Review[MAO review]
    Review --> Approved{Approved?}
    Approved -- No --> Closed[Rejected request]
    Approved -- Yes --> Issue[Issue asset and set issued]
    Issue --> Return[Return asset]
    Return --> Maintenance{Needs maintenance?}
    Maintenance -- Yes --> Open[Set maintenance status]
    Maintenance -- No --> AvailableAgain[Set asset available]
    Open --> End([End])
    AvailableAgain --> End
```

## Sequence

```mermaid
sequenceDiagram
    actor Farmer
    actor Staff as MAO equipment staff
    participant Controller as AEAMSController
    participant Service as EquipmentService
    participant DB as Shared database
    Farmer->>Controller: POST modules.aeams.requests.store
    Controller->>Service: requestForUser(user, data)
    Service->>DB: Verify farmer and lock asset
    Service->>DB: Check overlapping reservations
    Service->>DB: Create request and requested transaction
    Staff->>Controller: POST requests/{id}/review approved
    Controller->>Service: review(request, approved)
    Service->>DB: Update request and write transaction
    Staff->>Controller: POST requests/{id}/issue
    Controller->>Service: issue(request, condition)
    Service->>DB: Set request issued and asset issued
    Staff->>Controller: POST requests/{id}/return
    Controller->>Service: return(request, condition, maintenance)
    Service->>DB: Set returned and asset available/maintenance
```
