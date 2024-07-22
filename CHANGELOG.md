# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 2.0.1 – 2024-07-22

### Added

- Ability to cancel a task while waiting for results in the assistant @julien-nc [#97](https://github.com/nextcloud/assistant/pull/97)

### Changed

- Set min and max NC version to 30
- Switch from text processing to task processing API @julien-nc [#97](https://github.com/nextcloud/assistant/pull/97)
- UI/UX improvements @julien-nc [#97](https://github.com/nextcloud/assistant/pull/97)
- Avoid blocking a Php runner waiting for ChattyUI tasks to finish, poll in the frontend like the other task types @julien-nc [#97](https://github.com/nextcloud/assistant/pull/97)
- Get rid of the smart picker custom components, open the assistant instead @julien-nc [#97](https://github.com/nextcloud/assistant/pull/97)
- Simplify image generation, let users download or share result images directly in the assistant @julien-nc [#97](https://github.com/nextcloud/assistant/pull/97)
- Simplify the audio recorder and make it look more like the one in Talk @julien-nc [#97](https://github.com/nextcloud/assistant/pull/97)

## 1.1.0 – 2024-06-19

### Added

- Chatty UI @kyteinsky @julien-nc @marcelklehr [#86](https://github.com/nextcloud/assistant/pull/86)


## 1.0.9 – 2024-05-06

### Added

- support RTF files @julien-nc [#66](https://github.com/nextcloud/assistant/pull/66)
- new assistant standalone page @julien-nc [#72](https://github.com/nextcloud/assistant/pull/72)

### Changed

- use ITempManager instead of manually handling temp files @kyteinksy [#71](https://github.com/nextcloud/assistant/pull/71)

### Fixed

- fix audio transcription smart picker not setting appId and identifier params in the schedule request

## 1.0.8 – 2024-04-15

### Added

- OpenAPI specs
- Support for NC 30
- Developer and user docs @julien-nc [#57](https://github.com/nextcloud/assistant/pull/57) [#61](https://github.com/nextcloud/assistant/pull/61)
- Node and eslint workflows @kyteinsky [#60](https://github.com/nextcloud/assistant/pull/60)
- Add empty content when history is empty @julien-nc [#63](https://github.com/nextcloud/assistant/pull/63)

### Changed

- reset input/output form if the task type is changed by the user @julien-nc [#54](https://github.com/nextcloud/assistant/pull/54)

### Fixed

- create tmp folder to store docs as 0700 @kyteinsky [#64](https://github.com/nextcloud/assistant/pull/64)

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
