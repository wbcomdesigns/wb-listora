---
journey: type-services-toggle-saves
plugin: wb-listora
priority: high
roles: [administrator, subscriber]
covers: [type-editor, services, user-dashboard]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10331936497
---

# "Services enabled" on a listing type saves and hides services

Regression sentinel for the 1.8.0 QA bounce.

## Background

The Type Editor never sent `services_enabled`, and even an explicit false was stored as `''`, which `bool_meta()` reads as "never saved" and therefore ON. The editor now sends it, the registry stores `1`/`0`, and the member dashboard hides the services count, the Manage Services button and its panel for a type with services off.

## Steps

### 1. Switch off and reload
- **Action**: Listora > Listing Types > (a type) > untick Services enabled > Save Type. Reload.
- **Expect**: still unticked; term meta `_listora_services_enabled` is `0`.

### 2. Dashboard
- **Action**: as a member with a listing of that type, open the dashboard.
- **Expect**: that row has no services count and no wrench button; other types keep theirs.

### 3. Switch back on
- **Action**: tick it again, save, reload.
- **Expect**: ticked; services reappear, previously saved services intact.

### 4. Pre-1.8.0 types still default ON
- **Expect**: a type with no `_listora_services_enabled` meta reads as enabled.
