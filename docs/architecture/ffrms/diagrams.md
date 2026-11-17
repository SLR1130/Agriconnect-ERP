# FFRMS focused diagrams

## Use case

```mermaid
flowchart LR
    Farmer[Farmer]
    Staff[MAO verification staff]
    subgraph FFRMS["FFRMS inside AgriConnect ERP"]
        Register((Register account))
        Profile((Maintain own profile))
        Farm((Maintain own farms))
        Review((Verify / return / reject registration))
        Association((Manage farmer associations))
        History((Record registration history))
    end
    Farmer --> Register
    Farmer --> Profile
    Farmer --> Farm
    Staff --> Review
    Staff --> Association
    Register --> History
    Review --> History
    Review --> APMS[Verified farmer available to APMS]
    Review --> AASMS[Verified farmer eligible for AASMS]
    Review --> AEAMS[Verified farmer eligible for AEAMS]
```

## Context and Level 1 DFD

```mermaid
flowchart LR
    Farmer[Farmer browser]
    Staff[MAO staff browser]
    P1[Register / update farmer]
    P2[Review registration]
    P3[Maintain farms and associations]
    D1[(users / roles)]
    D2[(farmers)]
    D3[(farms / associations)]
    D4[(registration histories)]
    N[(notifications / activity logs)]
    Farmer --> P1 --> D1
    P1 --> D2
    Farmer --> P3 --> D3
    Staff --> P2
    P2 <--> D2
    P2 --> D4
    P2 --> N
    D2 --> APMS[APMS verified-farmer queries]
    D2 --> AASMS[AASMS eligibility queries]
    D2 --> AEAMS[AEAMS request authorization]
```

## Focused ERD

```mermaid
erDiagram
    USERS ||--o| FARMERS : owns
    USERS ||--o{ FARMER_REGISTRATION_HISTORIES : processes
    FARMERS ||--o{ FARMER_REGISTRATION_HISTORIES : has
    FARMERS ||--o{ FARMS : registers
    USERS ||--o{ FARMER_ASSOCIATIONS : creates
    FARMER_ASSOCIATIONS ||--o{ FARMER_ASSOCIATION_MEMBERS : has
    FARMERS ||--o{ FARMER_ASSOCIATION_MEMBERS : joins
    USERS {
        bigint id PK
        string name
        string email UK
        boolean is_active
    }
    FARMERS {
        bigint id PK
        bigint user_id FK UK
        string farmer_code UK
        string registration_status
        bigint verified_by FK
        datetime verified_at
    }
    FARMS {
        bigint id PK
        bigint farmer_id FK
        string name
        string barangay
        decimal area
        string classification
        json agricultural_activities
    }
    FARMER_REGISTRATION_HISTORIES {
        bigint id PK
        bigint farmer_id FK
        string from_status
        string to_status
        bigint processed_by FK
        text reason
    }
    FARMER_ASSOCIATIONS {
        bigint id PK
        string name UK
        string status
        bigint created_by FK
    }
    FARMER_ASSOCIATION_MEMBERS {
        bigint id PK
        bigint association_id FK
        bigint farmer_id FK
        string membership_status
    }
```

## UML class view

```mermaid
classDiagram
    class Farmer {
        +user()
        +farms()
        +registrationHistory()
        +isVerified()
    }
    class Farm
    class FarmerPortalController {
        +create()
        +store()
        +dashboard()
        +updateProfile()
        +storeFarm()
        +updateFarm()
    }
    class FFRMSController {
        +registrations()
        +review()
        +storeAssociation()
        +addMember()
    }
    class FarmerRegistrationService {
        +register(data)
        +transition(farmer, status, reason, processor)
    }
    Farmer "1" --> "*" Farm : owns
    FarmerPortalController --> FarmerRegistrationService
    FFRMSController --> FarmerRegistrationService
    FarmerRegistrationService --> Farmer : writes status
```

## Activity

```mermaid
flowchart TD
    S([Start]) --> Submit[Submit public registration]
    Submit --> Create[Create user, Farmer role, pending farmer, history]
    Create --> Queue[Await MAO review]
    Queue --> Decision{MAO decision}
    Decision -->|verified| Verified[Set verified_at and verified_by]
    Decision -->|returned| Returned[Save reason and request correction]
    Decision -->|rejected| Rejected[Save reason and close registration]
    Verified --> Notify[Create status notification and activity log]
    Returned --> Notify
    Rejected --> Notify
    Notify --> End([End])
```

## Sequence

```mermaid
sequenceDiagram
    actor Farmer
    participant Portal as FarmerPortalController
    participant Service as FarmerRegistrationService
    participant DB as Shared database
    participant Staff as FFRMSController / MAO

    Farmer->>Portal: POST farmer.register.store
    Portal->>Service: register(validated data)
    Service->>DB: Create users, role_user, farmers, history
    Service-->>Portal: User
    Portal-->>Farmer: Redirect to farmer portal
    Staff->>Staff: POST modules.ffrms.review
    Staff->>Service: transition(farmer, verified, reason, processor)
    Service->>DB: Lock farmer and write history
    Service->>DB: Notify farmer and log activity
    Service-->>Staff: Updated Farmer
```
