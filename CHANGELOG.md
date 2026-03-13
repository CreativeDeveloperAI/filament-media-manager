# Changelog

All notable changes to `filament-media-manager` will be documented in this file.

## v0.2.0 - 2026-03-13

### Added
- Integrated `codewithdennis/filament-select-tree` for hierarchical folder selection in the "Move" action.
- Added file count badges in the folder tree selection.
- Enabled branch node selection in the folder tree.

### Fixed
- Fixed asset loading for `filament-select-tree` to ensure styles and scripts are available in the Media Manager browser.
- Resolved "Call to a member function parent() on null" error in `SelectTree` when used within the component action context.

## v0.1.0 - 2026-03-13

- Initial release of the standalone Filament v5 Media Manager plugin.
