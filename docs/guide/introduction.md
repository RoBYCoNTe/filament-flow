# Introduction

**Filament Flow** is a powerful Business Process Manager for Filament that handles model state transitions and workflows with ease.

Filament Flow seamlessly integrates [Spatie Laravel Model States](https://spatie.be/docs/laravel-model-states) into your [FilamentPHP](https://filamentphp.com/) admin panel, providing a complete workflow management solution with visual state transitions, custom forms, and intuitive UI components.

**Key Capabilities:**

- **Code-First Workflows**: Define states and transitions in PHP using Spatie's State pattern
- **Database-Driven Workflows**: Configure workflows entirely through the database without PHP classes
- **Hybrid Approach**: Mix PHP state classes with database-only states for maximum flexibility

Perfect for order processing, publishing workflows, approval systems, and any application requiring well-defined state management.

## Overview

Filament Flow builds on [Spatie Laravel Model States](https://spatie.be/docs/laravel-model-states) to provide:

- **State Classes**: Each state is a separate class with its own behavior and logic
- **State Transitions**: Define which state changes are allowed with validation
- **Transition Classes**: Optional classes for complex transitions that need additional data or logic

**Example**: An order progresses through states like `Pending` → `Processing` → `Shipped` → `Delivered`, with validation ensuring only valid transitions occur.

## Features

**Rich State Management**

- Display model states with colors, icons, and descriptions
- Filter and group records by state
- Transition between states using intuitive UI components
- Bulk state transitions with validation
- Custom sort order for states in tables
- Mix PHP state classes with database-only states

**Developer Experience**

- Out-of-the-box support for Spatie Laravel Model States
- Database-driven workflow configuration (states, transitions, permissions)
- Custom transition forms for collecting additional data
- Automatic state validation and transition rules
- Field-level permissions per state
- Workflow assignments and notifications
- Transition history tracking
- Compatible with Filament v4 and dark mode
- DRY architecture with reusable traits

**Customizable Interface**

- Custom labels, colors, icons, and descriptions for states
- Custom transition forms and validation
- Flexible attribute mapping for complex models
- Confirmation dialogs for sensitive transitions
- Sortable state columns with workflow-based ordering

**Database-Driven Workflows** (Advanced)

- Define states and transitions entirely in the database
- Database-only states (no PHP classes required)
- Dynamic workflow configuration without code changes
- Field permissions per state and role
- Workflow assignments to users/teams
- Notification system for state transitions
- Complete transition history and audit trail

**State-Based Access Control**

- Define who can view, edit, or transition records based on state
- Flexible access rule tokens (`@authenticated`, `@owner`, `@assigned`, `role:`, `permission:`)
- Support for assignment-based access with type filtering
- Super admin bypass for full access
- Query scopes for retrieving accessible records
- Extensible with custom role and permission resolvers
- Compatible with Spatie Permission package

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `^11.0\|^12.0` |
| Filament | `^4.0` |
| Spatie Laravel Model States | `^2.12` |
