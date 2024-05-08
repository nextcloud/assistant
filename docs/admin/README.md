# Admin documentation

## Admin settings

The Assistant admin settings can be found under the "Artificial intelligence" section.
You can disable the assistant top menu entry there. You can also disable the AI-related smart pickers.
The commands to change the options are also listed in.

## Assistant configuration

1. Top-right Assistant

```
occ config:app:set assistant assistant_enabled --value=1
```

To enable/disable the assistant button from the top-right corner for all the users.

2. AI text generation smart picker

```
occ config:app:set assistant free_prompt_picker_enabled --value=1
```

To enable/disable the AI text generation smart picker for all the users.

3. Text-to-image smart picker

```
occ config:app:set assistant text_to_image_picker_enabled --value=1
```

To enable/disable the text-to-image smart picker for all the users.

4. Speech-to-text smart picker

```
occ config:app:set assistant speech_to_text_picker_enabled --value=1
```

To enable/disable the speech-to-text smart picker for all the users.

### Image storage

Days until generated images are deleted if they are not viewed.

```
occ config:app:set assistant max_image_generation_idle_time --value=90
```

### Chat with AI

1. Chat User Instructions for Chat Completions

```
occ config:app:set assistant chat_user_instructions --value="hello world"
```

The user instructions that are prepended before the chat messages for the AI model to understand the context of the block of text. This is a good place not only to instruct the AI model to be polite and kind but also to for example answer all the queries in a particular language or better yet, follow the user's language. The sky is the limit.

2. Chat User Instructions for Title Generation

```
occ config:app:set assistant chat_user_instructions_title --value="hello title"
```

This field is appended to the block of chat messages, i.e. attached after the messages. It is done this way to allow it to be used even with text completion models which could have the instructions as "The title for the above conversation could be \"".

3. Last N messages to consider for chat completions

```
occ config:app:set assistant chat_last_n_messages --value=10
```

The number of latest messages to consider for generating the next message. This does not include the user instructions, which is always considered in addition to this. This value should be adjusted in case you are hitting the token limit in your conversations too often.
The AI text generation provider should ideally handle the max token limit case.
