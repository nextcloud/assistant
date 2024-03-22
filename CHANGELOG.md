# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 1.0.7 – 2024-03-22

### Added

- Custom input form for Scoped Context Chat @kyteinsky [#45](https://github.com/nextcloud/assistant/pull/45)

### Changed

- Improve discoverability @julien-nc [#47](https://github.com/nextcloud/assistant/pull/47)
- Task history list now replaces the input form @julien-nc [#49](https://github.com/nextcloud/assistant/pull/49)

## 1.0.6 – 2024-03-08

### Fixed

- Include Php dependencies in release archive

## 1.0.5 – 2024-03-08

### Added

- Copy Writer feature
- Image generation and speech-to-text smart pickers
- Image generation and speech-to-text as tasks in the assistant itself
- Task history

## 1.0.3 – 2023-12-12

### Changed

- Use new runOrSchedule when trying to run a sync task

### Fixed

- Avoid initially selected task to be hidden in the menu
- Set default selected task on first use: free prompt

## 1.0.2 – 2023-11-20

### Added

- Input labels and result warning [#8](https://github.com/nextcloud/assistant/pull/8) @julien-nc

### Changed

- Implement synchronous workflow and ability to schedule if it's too long [#13](https://github.com/nextcloud/assistant/pull/13) @julien-nc
- Use `@nextcloud/vue` 8 @julien-nc
- Reimplement the task selector with inline buttons and action menu for overflowing ones [#3](https://github.com/nextcloud/assistant/pull/3) @julien-nc
- Set submit button label to "Submit request" when Free prompt is selected @julien-nc

### Fixed

- Fix top-right menu entry style [#4](https://github.com/nextcloud/assistant/issues/4) @julien-nc

## 1.0.1 – 2023-08-21

### Fixed

- Casing of app name

## 1.0.0 – 2023-08-10
### Added
* the app
