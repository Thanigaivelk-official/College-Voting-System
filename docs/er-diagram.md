# ER Diagram — College Voting System

## Privacy-Enhanced Ballot Design

The schema separates voter identity from ballot choices:

- **`voter_participation`** records *who* voted in *which* election (unique constraint enforces one vote per student per election)
- **`ballots`** records *which candidate* received a vote — with **no student_id foreign key**

There is no direct join between a specific student and their candidate choice. An opaque `ballot_token` links the ballot record to no particular voter.

> ⚠️ **Limitation:** Even without a direct link, correlation through timestamps, sequential IDs, or application logs may still reveal voter choices. True ballot secrecy requires reviewed cryptographic design.

---

## Mermaid ER Diagram

```mermaid
erDiagram
    users {
        int id PK
        varchar email UK
        varchar password_hash
        enum role
        enum status
        datetime created_at
        datetime updated_at
    }

    administrators {
        int id PK
        int user_id FK
        varchar full_name
        varchar staff_id UK
        varchar department
        varchar phone
        datetime created_at
    }

    students {
        int id PK
        int user_id FK
        varchar full_name
        varchar registration_no UK
        int department_id FK
        varchar course
        int academic_year
        tinyint is_verified
        datetime verified_at
        int verified_by FK
        datetime created_at
        datetime updated_at
    }

    departments {
        int id PK
        varchar code UK
        varchar name
        datetime created_at
    }

    whitelist_registration_numbers {
        int id PK
        varchar registration_no UK
        varchar full_name
        varchar department_code
        int academic_year
        tinyint is_registered
        datetime created_at
    }

    elections {
        int id PK
        varchar title
        text description
        varchar academic_year
        enum election_type
        datetime start_time
        datetime end_time
        enum status
        enum eligibility_type
        int created_by FK
        datetime created_at
        datetime updated_at
    }

    election_eligibility {
        int id PK
        int election_id FK
        int department_id FK
        int academic_year
        datetime created_at
    }

    candidates {
        int id PK
        int election_id FK
        int student_id FK
        varchar name
        varchar position
        int department_id FK
        text manifesto
        varchar photo_url
        enum status
        datetime created_at
        datetime updated_at
    }

    voter_participation {
        int id PK
        int election_id FK
        int student_id FK
        datetime voted_at
        varchar ip_address
        varchar user_agent
        varchar receipt_hash
    }

    ballots {
        int id PK
        int election_id FK
        int candidate_id FK
        varchar ballot_token UK
        datetime cast_at
    }

    election_results {
        int id PK
        int election_id FK
        int total_eligible_voters
        int total_votes_cast
        decimal turnout_percentage
        int winner_candidate_id FK
        varchar winner_name
        tinyint is_tie
        text tie_details
        json candidate_breakdown
        datetime published_at
        int published_by FK
    }

    notifications {
        int id PK
        int user_id FK
        varchar title
        text message
        enum type
        tinyint is_read
        datetime created_at
    }

    audit_logs {
        int id PK
        int user_id FK
        varchar actor_email
        varchar action
        varchar object_type
        int object_id
        text summary
        varchar ip_address
        datetime created_at
    }

    login_attempts {
        int id PK
        varchar ip_address
        varchar email
        datetime attempted_at
        tinyint is_successful
    }

    users ||--o| administrators : "has profile"
    users ||--o| students : "has profile"
    users ||--o{ elections : "creates"
    users ||--o{ notifications : "receives"
    users ||--o{ audit_logs : "generates"
    students }o--|| departments : "belongs to"
    students ||--o{ voter_participation : "casts"
    students ||--o{ candidates : "nominated as"
    elections ||--o{ candidates : "has"
    elections ||--o{ election_eligibility : "defines rules"
    elections ||--o{ voter_participation : "tracks"
    elections ||--o{ ballots : "contains"
    elections ||--o| election_results : "produces"
    candidates ||--o{ ballots : "receives"
    departments ||--o{ election_eligibility : "restricted to"
    departments ||--o{ candidates : "from"
```

---

## Relationship Summary

| Relationship | Type | Notes |
|---|---|---|
| users → administrators | 1:0..1 | Admin profile (optional) |
| users → students | 1:0..1 | Student profile (optional) |
| students → voter_participation | 1:many | One participation per election |
| elections → voter_participation | 1:many | UNIQUE(election_id, student_id) |
| elections → ballots | 1:many | No student FK — privacy model |
| candidates → ballots | 1:many | How candidate votes are counted |
| elections → election_results | 1:0..1 | Frozen on publication |

---

## Key Constraints

```sql
-- One vote per student per election
UNIQUE KEY uq_election_student (election_id, student_id)  -- voter_participation

-- Unique anonymous ballot token
UNIQUE KEY (ballot_token)  -- ballots

-- One published result per election  
UNIQUE KEY (election_id)  -- election_results

-- Unique user email
UNIQUE KEY (email)  -- users

-- Unique student registration number
UNIQUE KEY (registration_no)  -- students
```
