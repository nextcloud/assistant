<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 2.7.0 – 2025-09-05

### Changed

- Rename ai settings to assistant @lukasdotcom [#333](https://github.com/nextcloud/assistant/pull/333)
- Improve sticker generation @lukasdotcom [#331](https://github.com/nextcloud/assistant/pull/331)
- Replace mdi download icon with Material Symbols variant @AndyScherzinger [#348](https://github.com/nextcloud/assistant/pull/348)
- Improve style and use new assistant components @julien-nc [#349](https://github.com/nextcloud/assistant/pull/349)

### Fixed

- Only show the group file action if necessary @julien-nc [#342](https://github.com/nextcloud/assistant/pull/342)

## 2.6.1 – 2025-08-09

### Fixed

- Fix adding attachments and sources with notNull=true and no default value making the migration crash for postgres @julien-nc [#335](https://github.com/nextcloud/assistant/pull/335)

## 2.6.0 – 2025-08-07

### Added
- Audio chat for the Chat UI @julien-nc [#292] (https://github.com/nextcloud/assistant/pull/292)
- Param in openAssistantForm to restrict which task types are displayed @julien-nc [#287] (https://github.com/nextcloud/assistant/pull/287)
- Categories and icons for task types @lukasdotcom [#311] (https://github.com/nextcloud/assistant/pull/311)
- File actions for text, audio and markdown files @julien-nc [#312] (https://github.com/nextcloud/assistant/pull/312)
- UI hint when task is taking too long @lukasdotcom [#315] (https://github.com/nextcloud/assistant/pull/315)
- Generate image action in "New file" menu @andrey18106 [#322] (https://github.com/nextcloud/assistant/pull/322)
- Support for more file types to file actions @lukasdotcom [#326] (https://github.com/nextcloud/assistant/pull/326)
- Sticker generation smart picker @lukasdotcom [#323] (https://github.com/nextcloud/assistant/pull/323)
- AI hint in Chat with AI @lukasdotcom [#329] (https://github.com/nextcloud/assistant/pull/329)

### Changed
- Migrate to Vue 3 and nextcloud/vue 9 @julien-nc [#258] (https://github.com/nextcloud/assistant/pull/258)
- Use outline icons where it makes sense @julien-nc [#288] (https://github.com/nextcloud/assistant/pull/288)
- Improve error messages @marcelklehr [#242] (https://github.com/nextcloud/assistant/pull/242)
- updated php dependencies

### Fixed
- UI adjustments @julien-nc [#291] (https://github.com/nextcloud/assistant/pull/291)
- Chat user instruction handling @janepie [#297] (https://github.com/nextcloud/assistant/pull/297)
- explicitly set default sources when creating new session @hamza221 [295] (https://github.com/nextcloud/assistant/pull/295)
- Reset the assistant form when pollTask response is 404 @julien-nc [#320] (https://github.com/nextcloud/assistant/pull/320)
- do not set a default for longtext db fields @kyteinsky [#309] (https://github.com/nextcloud/assistant/pull/309)




## 2.5.0 – 2025-06-26

### Added

- Add warning if Context chat indexing is not complete yet @marcelklehr [#199](https://github.com/nextcloud/assistant/pull/199)
- Support importing the content of PDF files in text inputs @julien-nc [#204](https://github.com/nextcloud/assistant/pull/204)
- Task output file download link reference, open files in the Viewer @julien-nc [#228](https://github.com/nextcloud/assistant/pull/228) [#236](https://github.com/nextcloud/assistant/pull/236)
- Display expected task runtime when running @edward-ly [#220](https://github.com/nextcloud/assistant/pull/220)
- Information Source info in Chat UI @janepie [#230](https://github.com/nextcloud/assistant/pull/230)
- Custom translation input/output form @edward-ly [#232](https://github.com/nextcloud/assistant/pull/232)
- New task form layout with the history in a sidebar @edward-ly [#227](https://github.com/nextcloud/assistant/pull/227)
- Support for Context chat search (custom input/output form) @julien-nc [#241](https://github.com/nextcloud/assistant/pull/241)
- More agency tool descriptions @janepie [#261](https://github.com/nextcloud/assistant/pull/261)

### Changed

- Convert all chat endpoints to OCS ones @julien-nc [#207](https://github.com/nextcloud/assistant/pull/207)
- Use Psalm 6 @julien-nc [#219](https://github.com/nextcloud/assistant/pull/219)
- Automatic naming of output files based on mime type detection @julien-nc [#218](https://github.com/nextcloud/assistant/pull/218)
- Chatty UI: replace loading spinner with skeleton message @edward-ly [#226](https://github.com/nextcloud/assistant/pull/226)
- Schedule task on text prompt input submit event @edward-ly [#222](https://github.com/nextcloud/assistant/pull/222)
- Keep input prompt when switching task types @edward-ly [#238](https://github.com/nextcloud/assistant/pull/238)
- Change how small number inputs are rendered @julien-nc [#237](https://github.com/nextcloud/assistant/pull/237)
- Refactor information display in personal settings @edward-ly [#254](https://github.com/nextcloud/assistant/pull/254)
- Show loading screen in task area only @edward-ly [#256](https://github.com/nextcloud/assistant/pull/256)

### Fixed

- Use session id in retry generation in chatty llm @kyteinsky [#201](https://github.com/nextcloud/assistant/pull/201)
- Save Chat with AI as last task type @janepie [#206](https://github.com/nextcloud/assistant/pull/206)
- Do not overwrite default translation origin language @janepie [#205](https://github.com/nextcloud/assistant/pull/205)
- Override NcRichText link resolving to support markdown links in the chatty UI @julien-nc [#221](https://github.com/nextcloud/assistant/pull/221)
- Hide empty chat messages @janepie [#231](https://github.com/nextcloud/assistant/pull/231)
- Disappearing messages in the chatty UI @marcelklehr [#234](https://github.com/nextcloud/assistant/pull/234)
- Flashing loading placeholder when switching chat conversations @edward-ly [#235](https://github.com/nextcloud/assistant/pull/235)
- Style issues in personal settings @julien-nc [#244](https://github.com/nextcloud/assistant/pull/244)
- Issues when mixing the smart picker + the assistant modal + the viewer @julien-nc [#246](https://github.com/nextcloud/assistant/pull/246) [#253](https://github.com/nextcloud/assistant/pull/253)
- Assistant failed to load when text2text disabled but other task exists @lukasdotcom [#255](https://github.com/nextcloud/assistant/pull/255)
- Preserve inputs when switching task type only if it was text and it goes to a text field @julien-nc [#262](https://github.com/nextcloud/assistant/pull/262)

## 2.4.0 – 2025-02-25

### Changed

- Show image results full width @julien-nc [#183](https://github.com/nextcloud/assistant/pull/183)
- Use new assistant endpoint to download task output files with mimetype detection, use preview endpoint to display image results @julien-nc [#184](https://github.com/nextcloud/assistant/pull/184)
- Improve agency action confirmation style+design @julien-nc [#194](https://github.com/nextcloud/assistant/pull/194)

### Fixed

- Update npm pkgs to fix NcSelect with right-to-left languages @janepie [#185](https://github.com/nextcloud/assistant/pull/185)
- Make assistant modal 'dark' @janepie [#180](https://github.com/nextcloud/assistant/pull/180)
- Fix choose file button position with RTL languages @julien-nc [#186](https://github.com/nextcloud/assistant/pull/186)
- Fix UI issues for Context Chat @kyteinsky [#190](https://github.com/nextcloud/assistant/pull/190)
- Fix(regeneration): store task id in messages so the check endpoint can return a message ID @julien-nc [#188](https://github.com/nextcloud/assistant/pull/188)

## 2.3.0 – 2025-01-22

### Changed

- Save default target language & set detect_language as origin default @janepie @julien-nc [#167](https://github.com/nextcloud/assistant/pull/167)
- Hide ChatWithTools and ContextAgentInteraction task types @julien-nc [#172](https://github.com/nextcloud/assistant/pull/172)
- Add reuse compliance @AndyScherzinger [#163](https://github.com/nextcloud/assistant/pull/163)

### Fixed

- Fix config value types for some bools and ints we store @julien-nc [#174](https://github.com/nextcloud/assistant/pull/174)

## 2.2.0 – 2025-01-07

### Added

- Agency support @julien-nc [#169](https://github.com/nextcloud/assistant/pull/169)

### Changed

- Use Text2TextChat in the chatty UI when the Agency task type is not available @julien-nc [#169](https://github.com/nextcloud/assistant/pull/169)
- Switch to IAppConfig @julien-nc [#147](https://github.com/nextcloud/assistant/pull/147)
- Avoid anthropomorphism @marcelklehr [#148](https://github.com/nextcloud/assistant/pull/148)
- Add confirmation dialog before deleting a conversation @julien-nc [#160](https://github.com/nextcloud/assistant/pull/160)
- Allow to pass file ID or path as initial input text field value @julien-nc [#161](https://github.com/nextcloud/assistant/pull/161)

### Fixed

- Fix polling when switching sessions in the frontend, prevent scheduling multiple llm tasks for one session @julien-nc [#156](https://github.com/nextcloud/assistant/pull/156)
- Fix(notifier): check if task type is there before getting its name @julien-nc [#166](https://github.com/nextcloud/assistant/pull/166)

## 2.1.1 – 2024-10-09

### Added

- Add a personal settings section listing the configured backends @marcelklehr [#134](https://github.com/nextcloud/assistant/pull/134)

### Changed

- Report task ID in more failure messages @marcelklehr [#137](https://github.com/nextcloud/assistant/pull/137)

### Fixed

- Missing worker policy when launching assistant @smarinier [#139](https://github.com/nextcloud/assistant/pull/139)
- Incorrect task type prop set when trying again @julien-nc [#144](https://github.com/nextcloud/assistant/pull/144)
- Fix navigation icon color @julien-nc [#145](https://github.com/nextcloud/assistant/pull/145)

## 2.1.0 – 2024-09-30

### Added

- Add back buttons after launching a task @julien-nc [#131](https://github.com/nextcloud/assistant/pull/131)
- Close the task history item menu when canceling a task @julien-nc [#131](https://github.com/nextcloud/assistant/pull/131)
- Render context chat's the referenced source items @kyteinsky [#124](https://github.com/nextcloud/assistant/pull/124)

### Changed

- Migrate to vite build system @julien-nc [#125](https://github.com/nextcloud/assistant/pull/125)
- Switch audio recorder to extendable-media-recorder @julien-nc [#125](https://github.com/nextcloud/assistant/pull/125)

### Fixed

- Enable submit button without scope in context chat @kyteinsky [#128](https://github.com/nextcloud/assistant/pull/128)

## 2.0.4 – 2024-09-04

### Fixed

- Do not send the user's display name in the chat instruct prompts by default @julien-nc [#116](https://github.com/nextcloud/assistant/pull/116)
- Only show 2 digits for the task progress @julien-nc [#119](https://github.com/nextcloud/assistant/pull/119)
- Missing destructured param when calling openAssistantTask @julien-nc [#120](https://github.com/nextcloud/assistant/pull/120)
- Adjust to html header structure change in 31

## 2.0.3 – 2024-08-13

### Changed

- Show all task types inline @julien-nc [#109](https://github.com/nextcloud/assistant/pull/109)
- More links to docs @marcelklehr [#108](https://github.com/nextcloud/assistant/pull/108)

### Fixed

- Populate scopeList with scopeListMeta on select event @kyteinsky [#105](https://github.com/nextcloud/assistant/pull/105)
- More error log @marcelklehr [#107](https://github.com/nextcloud/assistant/pull/107)
- Don't require Admin access for Chat with AI @marcelklehr [#106](https://github.com/nextcloud/assistant/pull/106)
- Trim text values @julien-nc [#110](https://github.com/nextcloud/assistant/pull/110)
- Avoid colon in uploaded file names @julien-nc [#111](https://github.com/nextcloud/assistant/pull/111)

## 2.0.2 – 2024-07-26

### Added

- Support for Enum field type @julien-nc
- Support action buttons when opening a task with OCA.Assistant.openAssistantTask @julien-nc

### Changed

- Hide 'choose file' button in context chat input form @julien-nc

### Fixed

- Fix short input displayed when a task is scheduled @julien-nc
- Fix style issues in standalone page @julien-nc

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
