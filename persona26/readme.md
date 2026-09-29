# Persona26

Author: Techn
Version: 0.7.7
Status: Release
Branding: Author Branded (Techn settings); Extension Branded (Gravity Forms)

## Purpose

Persona26 extends Independent Analytics with visitor identity mapping, engagement dimensions, profile data, and front-end personalisation support.

## Key Features

- Tracks visitor identity alongside Independent Analytics.
- Configures engagement dimensions and content profiling scope.
- Stores Persona26 alignment data and queryable dimension metadata.
- Provides engagement and visitor matrices.
- Supports front-end personalisation data.
- Integrates Gravity Forms feeds and dynamic choice fields with Persona26 dimensions.

## Folder Structure

- `functions/` contains plugin behaviour and integrations.
- `scripts/` contains admin JavaScript.
- `styles/` contains admin styles.

## Important Notes

Persona dimension selections remain stored in `p26_alignment`. Persona26 also mirrors each selection as scalar post meta keyed by the exact registered post-type name for WordPress and AlphaBlock queries.

### Gravity Forms

When Gravity Forms is active, Persona26 adds a form feed that can map a submitted field value into the existing `p26_profile` persona store without changing the visitor identity cookie.

Radio and checkbox fields can be populated from Persona26 dimensions by adding a field CSS class such as `p26-dimension-d0`, `p26-dimension-audience`, or `p26-dimension-your_post_type`.

## Future Considerations

Large installations may benefit from moving alignment reporting to a dedicated indexed data model.

## Operations

See `readme.txt` for installation, browser storage, external services, data retention and caching considerations. Deactivation retains data. Clear dimension rows without reindexing later keys.

## Query-string dimension selection

Use the exact registered CPT name and a published post slug, for example
`?state=qld&__audience=parents` when those CPTs are configured as dimensions.
After normal page increments, each selected counter becomes the maximum in its
own dimension plus one, including its own existing count. Other counters remain.
The persona, localStorage, cookie and body classes are updated together. This
works on front-end pages without content targets too. Reloading reapplies it;
future browsing can change the winner. Unknown parameters, array values and
invalid, unpublished or wrong-CPT slugs are ignored. Supply one slug per CPT;
repeated scalar parameters follow PHP's last-value behaviour.

`persona` remains reserved for debugging: `get` and `clear` skip selections;
`show` applies them before displaying the profile. A CPT named `persona` cannot
use this query feature. Page caches must vary by these query parameters or bypass
caching for selection URLs, because WordPress validates selections per request.
