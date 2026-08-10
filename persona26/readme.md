# Persona26

Author: Techn
Version: 0.3
Status: Production

## Purpose

Persona26 extends Independent Analytics with visitor identity mapping, engagement dimensions, profile data, and front-end personalisation support.

## Key Features

- Tracks visitor identity alongside Independent Analytics.
- Configures engagement dimensions and content profiling scope.
- Stores Persona26 alignment data and queryable dimension metadata.
- Provides engagement and visitor matrices.
- Supports front-end personalisation data.

## Folder Structure

- `functions/` contains plugin behaviour and integrations.
- `scripts/` contains admin JavaScript.
- `styles/` contains admin styles.

## Important Notes

Persona dimension selections remain stored in `p26_alignment`. Version 0.3 also mirrors each selection as scalar post meta keyed by the exact registered post-type name for WordPress and AlphaBlock queries.

## Future Considerations

Large installations may benefit from moving alignment reporting to a dedicated indexed data model.
