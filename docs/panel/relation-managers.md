# Relation Manager Permissions

Filament Flow can discover **RelationManagers** from your Filament Resources and treat them as fields for permission purposes. This allows you to control visibility of RM sections and individual actions (create, delete, edit) per state and per role — all configured in the database.

## How RelationManagers Are Discovered

When you open the field permissions dropdown in the workflow admin UI, `ModelDiscovery` automatically discovers:

1. **The RM itself** (e.g., `claimAttachments`) — controls section visibility
2. **Sub-fields for each action** (e.g., `claimAttachments.create`, `claimAttachments.delete`) — controls individual action buttons

Discovery works via reflection:
- The `$relationship` property is read from each RelationManager class
- Action classes (`CreateAction`, `DeleteAction`, `EditAction`) are detected in the RM source

## Using Permissions in RelationManagers

Use `isFieldVisible()` from the `HasStateAccess` trait on the owner record:

```php
// In your RelationManager's table() method:
CreateAction::make()
    ->visible(fn () => $this->getOwnerRecord()->isFieldVisible('claimAttachments.create')),

DeleteAction::make()
    ->visible(fn () => $this->getOwnerRecord()->isFieldVisible('claimAttachments.delete')),
```

## Sub-Field Naming Convention

Sub-fields follow the pattern `{relationshipName}.{action}`:

| Sub-field | Controls |
|---|---|
| `attachments` | Section/tab visibility |
| `attachments.create` | Create button in header |
| `attachments.delete` | Delete button per record |
| `attachments.edit` | Edit button per record |
