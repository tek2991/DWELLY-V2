Certainly. I would actually consider this document **as important as the implementation plan itself**. The implementation plan defines **what** the system should be; this document defines **how every line of code should be written**.

For a project of Dwelly's size (Laravel 13 + Filament 5 + DDD + Event Driven), this becomes the engineering handbook.

---

# Development Standards & Technical Guidelines

## Dwelly V2

### Laravel 13 • Filament 5

---

# 1. Purpose

This document defines the engineering standards for Dwelly V2.

Every developer, contributor, and AI assistant **must follow these standards** to ensure:

* Consistency
* Maintainability
* Scalability
* Testability
* Predictable architecture

The implementation plan defines **what** the system should do.

This document defines **how it should be implemented.**

---

# 2. Technology Stack

Core

* Laravel 13
* PHP 8.4+
* Filament 5
* Livewire 4
* Alpine.js
* Tailwind CSS
* MySQL 8+

Packages

* Spatie Permission
* Spatie Activity Log
* Spatie Media Library
* tek2991/accounting
* Laravel Queue
* Laravel Scheduler
* Pest
* Laravel Pint

---

# 3. Architectural Principles

The application follows:

* Domain Driven Design
* Modular Monolith
* Event Driven Architecture
* Action Based Business Logic
* Repository-free Architecture (Eloquent is the repository)
* Thin Controllers
* Thin Filament Resources
* Explicit Domain Boundaries

Business logic belongs inside domains.

---

# 4. Domain Structure

Never organize code by technical type.

Do not use:

```
Models/

Controllers/

Services/

```

Instead organize by business domain.

```
app/

Domains/

    Property/

        Models/

        Actions/

        DTOs/

        Events/

        Listeners/

        Policies/

        States/

        Queries/

        Services/

        Resources/

        Notifications/

        Enums/

    Party/

    Agreement/

    Maintenance/

    Finance/

    Workflow/

    Notification/

    Administration/

Shared/

Infrastructure/

Support/

```

Every new feature belongs to exactly one domain.

---

# 5. Models

Models represent data.

Models may contain:

* Relationships
* Attribute casts
* Scopes
* Accessors
* Mutators

Models must NOT contain:

* Business calculations
* Workflow logic
* Notifications
* Complex validation
* Business orchestration

---

# 6. Controllers

Controllers should contain only:

```
Authorize

↓

Validate

↓

Execute Action

↓

Return Response
```

Maximum controller complexity should remain minimal.

---

# 7. Filament Resources

Resources are UI.

Resources should never contain business logic.

Allowed:

* Forms
* Tables
* Filters
* Actions

When a button is clicked

↓

Call Action

Not Service

Not Model

---

# 8. Action Classes

Every business operation becomes an Action.

Examples

```
CreatePropertyAction

AssignOwnerAction

SignAgreementAction

GenerateRentCycleAction

ApproveMaintenanceAction

GenerateOwnerStatementAction

```

Actions should:

* perform one business operation
* be independently testable
* dispatch Events

Actions should NOT call Filament.

---

# 9. Services

Services encapsulate reusable business logic.

Examples

```
CalculationService

NotificationService

DocumentGenerationService

AccountingBridgeService

```

Services should never become "God Classes."

Avoid:

```
PropertyService

FinanceService

```

with hundreds of methods.

---

# 10. Event Strategy

Business operations dispatch Events.

Naming

Always

Past tense.

Good

```
PropertyCreated

AgreementSigned

RentReceived

InvoicePosted

MaintenanceCompleted

```

Bad

```
CreatePropertyEvent

DoInvoice

```

Events describe completed business facts.

---

# 11. Listener Strategy

Listeners react to Events.

Example

```
AgreementSigned

↓

Generate Ledger Entries

↓

Notify Owner

↓

Log Activity

↓

Schedule Reminder

```

Listeners should remain independent.

Listeners must never call other listeners.

---

# 12. Workflow & State Management

Every workflow uses State classes.

Never:

```
$status = "completed";
```

Instead

```
MaintenanceState

↓

transitionTo()

↓

CompletedState

```

Every state transition must be validated.

---

# 13. Authorization

Authorization uses:

* Spatie Permission
* Laravel Policies

Never:

```
if($user->role=="manager")
```

Always

```
$user->can(...)
```

or

```
Gate::allows(...)
```

Module access

↓

Permission

Action

↓

Permission

Geographic restriction

↓

Policy

---

# 14. Validation

All validation belongs in:

Form Requests

Complex validation belongs inside Domain Rules.

Never validate directly in controllers.

---

# 15. Business Rules

Business rules belong inside Actions.

Never duplicate business rules.

If rent calculation exists

↓

Every module uses

CalculationService

---

# 16. Database Standards

Tables

Plural

```
properties

parties

agreements
```

Models

Singular

```
Property

Party

Agreement
```

Primary key

```
id
```

Foreign Keys

```
property_id

party_id

agreement_id
```

---

# 17. Money

Never

```
float

double
```

Always

```
decimal(15,2)
```

All monetary calculations should be centralized.

---

# 18. Date & Time

Always use Carbon.

Store timestamps in UTC.

Display according to application timezone.

Never manually manipulate timestamps.

---

# 19. Database Transactions

Wrap all multi-step business operations.

Example

```
Create Agreement

↓

Create Ledger

↓

Generate Number

↓

Store Documents

↓

Commit
```

Failure

↓

Rollback

---

# 20. Query Standards

Never load unnecessary data.

Always:

* eager load
* paginate
* chunk imports
* cursor large exports

Avoid N+1 queries.

---

# 21. Database Indexing

Every FK

↓

INDEX

Frequently searched fields

↓

INDEX

Examples

```
property_code

phone

email

pan

agreement_number

invoice_number

workflow_state

region_id
```

Composite indexes where appropriate.

---

# 22. Events vs Services

Business Flow

```
Action

↓

Event

↓

Listener

↓

Service
```

Services should not dispatch business Events unless they are explicitly orchestrating a business process.

This prevents hidden event chains.

---

# 23. Queue Strategy

Queues

```
critical

default

notifications

media

reports
```

Long-running work

↓

Queue

Never block UI.

---

# 24. Scheduler

All recurring jobs must be registered in one location.

Document:

* Frequency
* Queue
* Purpose

No hidden scheduled tasks.

---

# 25. Error Handling

Never swallow exceptions.

Create domain exceptions.

Examples

```
AgreementAlreadySigned

PropertyArchived

DuplicateParty

InvalidWorkflowTransition

```

---

# 26. Logging

Business

↓

Activity Log

Technical

↓

Laravel Log

Never log sensitive information.

---

# 27. Configuration

Business configuration belongs in the database.

Framework configuration belongs in config files.

Never mix the two.

---

# 28. Testing Strategy

Framework

Pest

Every Action

↓

Unit Test

Every Workflow

↓

Feature Test

Critical business processes

↓

Integration Test

Every bug fix

↓

Regression Test

Target:

* High coverage for business logic
* Moderate coverage for UI

---

# 29. Filament Guidelines

Resources should only describe the interface.

Forms

↓

DTO / Action

Tables

↓

Query

Bulk Actions

↓

Action

Widgets

↓

Read-only

Avoid business logic inside Filament components.

---

# 30. AI Development Guidelines

AI-generated code must follow the architecture.

AI must never:

* create duplicate services
* bypass Actions
* duplicate calculations
* bypass permissions
* bypass workflow states

If uncertain,

follow existing patterns.

Consistency is more important than cleverness.

---

# 31. Git Standards

Branch naming

```
feature/...

fix/...

refactor/...

docs/...
```

Commit messages

```
feat:

fix:

refactor:

docs:

test:

perf:
```

---

# 32. Code Style

* Follow PSR-12.
* Enforce formatting with Laravel Pint.
* Prefer constructor property promotion.
* Use typed properties and return types everywhere.
* Use PHP attributes where Laravel provides first-class support.
* Avoid static helper classes for business logic.
* Keep methods short and focused (generally under 30–40 lines).
* Favor composition over inheritance.

---

# 33. Performance Guidelines

* Eager load relationships.
* Queue expensive work.
* Cache only when measurements justify it.
* Avoid premature optimization.
* Profile before optimizing.
* Never sacrifice readability without measurable benefit.

---

# 34. Security Guidelines

* Always authorize before executing business actions.
* Validate all user input.
* Escape output by default.
* Store secrets only in environment variables.
* Never log passwords, tokens, OTPs, or personally sensitive data.
* Enforce CSRF protection for web requests.
* Apply rate limiting where appropriate.

---

# 35. Definition of Done

A feature is considered complete only when:

* Business requirements are satisfied.
* Authorization is implemented.
* Validation is complete.
* Activity logging is in place.
* Events are dispatched where appropriate.
* Unit and feature tests pass.
* Database indexes have been considered.
* No N+1 queries exist.
* Code follows Laravel Pint.
* Documentation is updated if architecture changes.

---

# 36. Guiding Principles

Every implementation should strive to satisfy these principles:

1. **Consistency over cleverness.**
2. **Explicit code over implicit behavior.**
3. **Small, focused classes over large abstractions.**
4. **Composition over inheritance.**
5. **Configuration over hardcoding.**
6. **Events for business facts, Services for reusable logic, Actions for business operations.**
7. **Preserve history—never rewrite finalized business records.**
8. **Write code for the next developer, not just for today.**

