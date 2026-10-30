# APMS focused diagrams

## Use case

```mermaid
flowchart LR
    Agriculturist[Municipal agriculturist / module personnel]
    Auditor[Authorized viewer]
    subgraph APMS["APMS inside AgriConnect ERP"]
        Season((Manage agricultural seasons))
        Crop((Record crop production))
        Livestock((Record livestock))
        Fishery((Record fisheries))
        Incident((Report pest / disease incident))
        Monitor((Record farm monitoring))
        Report((View APMS dashboard statistics))
    end
    Agriculturist --> Season
    Agriculturist --> Crop
    Agriculturist --> Livestock
    Agriculturist --> Fishery
    Agriculturist --> Incident
    Agriculturist --> Monitor
    Agriculturist --> Report
    Auditor --> Report
    FFRMS[Verified FFRMS farmer and farm] --> Crop
    FFRMS --> Livestock
    FFRMS --> Fishery
    FFRMS --> Incident
    FFRMS --> Monitor
```

## Context and Level 1 DFD

```mermaid
flowchart LR
    Staff[Authorized APMS staff]
    P1[Validate verified farmer/farm]
    P2[Record production]
    P3[Record incidents and monitoring]
    P4[Render dashboard and statistics]
    D1[(farmers / farms)]
    D2[(seasons)]
    D3[(crop / livestock / fishery records)]
    D4[(incidents / monitoring records)]
    Staff --> P1 --> D1
    Staff --> P2
    Staff --> P3
    P2 <--> D2
    P2 <--> D3
    P3 <--> D4
    P4 --> D1
    P4 --> D2
    P4 --> D3
    P4 --> D4
```

## Focused ERD

```mermaid
erDiagram
    FARMERS ||--o{ FARMS : owns
    AGRICULTURAL_SEASONS ||--o{ CROP_PRODUCTION_RECORDS : scopes
    FARMERS ||--o{ CROP_PRODUCTION_RECORDS : records
    FARMS ||--o{ CROP_PRODUCTION_RECORDS : hosts
    FARMERS ||--o{ LIVESTOCK_RECORDS : records
    FARMS ||--o{ LIVESTOCK_RECORDS : hosts
    FARMERS ||--o{ FISHERY_RECORDS : records
    FARMS ||--o{ FISHERY_RECORDS : hosts
    FARMERS ||--o{ PEST_DISEASE_INCIDENTS : reports
    FARMS ||--o{ PEST_DISEASE_INCIDENTS : affects
    FARMERS ||--o{ FARM_MONITORING_RECORDS : receives
    FARMS ||--o{ FARM_MONITORING_RECORDS : visits
    FARMERS {
        bigint id PK
        string farmer_code UK
        string registration_status
    }
    FARMS {
        bigint id PK
        bigint farmer_id FK
        string name
        string barangay
        string classification
    }
    AGRICULTURAL_SEASONS {
        bigint id PK
        string name
        date starts_on
        date ends_on
        string status
    }
    CROP_PRODUCTION_RECORDS {
        bigint id PK
        bigint farmer_id FK
        bigint farm_id FK
        bigint season_id FK
        string crop_type
        decimal yield_quantity
        string status
    }
    LIVESTOCK_RECORDS {
        bigint id PK
        bigint farmer_id FK
        bigint farm_id FK
        string animal_type
        integer quantity
    }
    FISHERY_RECORDS {
        bigint id PK
        bigint farmer_id FK
        bigint farm_id FK
        string fishery_type
        decimal production_quantity
    }
    PEST_DISEASE_INCIDENTS {
        bigint id PK
        bigint farmer_id FK
        bigint farm_id FK
        string incident_type
        string severity
        string status
    }
    FARM_MONITORING_RECORDS {
        bigint id PK
        bigint farmer_id FK
        bigint farm_id FK
        date visited_on
        text findings
        string status
    }
```

## UML class view

```mermaid
classDiagram
    class APMSController {
        +index()
        +season()
        +crop()
        +livestock()
        +fishery()
        +incident()
        +monitoring()
        -validateFarmOwner(farmerId, farmId)
    }
    class Farmer
    class Farm
    class AgriculturalSeason
    class CropProductionRecord
    class LivestockRecord
    class FisheryRecord
    class PestDiseaseIncident
    class FarmMonitoringRecord
    APMSController --> Farmer : verifies
    APMSController --> Farm : verifies owner
    APMSController --> AgriculturalSeason : creates / reads
    APMSController --> CropProductionRecord : creates
    APMSController --> LivestockRecord : creates
    APMSController --> FisheryRecord : creates
    APMSController --> PestDiseaseIncident : creates
    APMSController --> FarmMonitoringRecord : creates
```

## Activity

```mermaid
flowchart TD
    S([Start]) --> Select[Select verified farmer and farm]
    Select --> Owner{Farm belongs to verified farmer?}
    Owner -- No --> Reject[Return validation error]
    Owner -- Yes --> Type{Record type}
    Type -->|crop| Crop[Validate dates, area, yield, status]
    Type -->|livestock| Live[Validate animal and quantity]
    Type -->|fishery| Fish[Validate fishery and quantity]
    Type -->|incident| Incident[Validate severity and description]
    Type -->|monitoring| Monitor[Validate visit and findings]
    Crop --> Save[Persist record with recorded_by]
    Live --> Save
    Fish --> Save
    Incident --> Save
    Monitor --> Save
    Save --> End([Dashboard reflects record])
```

## Sequence

```mermaid
sequenceDiagram
    actor Staff as APMS staff
    participant Controller as APMSController
    participant DB as Shared database
    Staff->>Controller: POST modules.apms.crops.store
    Controller->>DB: Validate farmer, farm and verified status
    DB-->>Controller: Valid owner relationship
    Controller->>DB: Create crop_production_records
    Controller-->>Staff: Redirect with success status
    Staff->>Controller: GET modules.apms.dashboard
    Controller->>DB: Load farms, seasons, records and statistics
    DB-->>Controller: APMS data
    Controller-->>Staff: Render dashboard
```
