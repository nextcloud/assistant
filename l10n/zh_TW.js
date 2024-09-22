OC.L10N.register(
    "assistant",
    {
    "Failed to notify when ready; unknown user" : "就緒時通知失敗；未知的使用者",
    "User not logged in" : "使用者未登入",
    "User not found" : "找不到使用者",
    "Failed to create a chat session" : "建立聊天工作階段失敗",
    "Failed to update the chat session" : "更新聊天工作階段失敗",
    "Failed to delete the chat session" : "刪除聊天工作階段失敗",
    "Failed to get chat sessions" : "取得聊天工作階段失敗",
    "Session not found" : "找不到工作階段",
    "Message content is empty" : "留言內容為空",
    "Failed to add a chat message" : "新增聊天訊息失敗",
    "Failed to get chat messages" : "取得聊天訊息失敗",
    "Failed to delete a chat message" : "刪除聊天訊息失敗",
    "Failed to delete the last message" : "刪除最後一則訊息失敗",
    "Failed to add a chat message into DB" : "新增聊天訊息進入資料庫中失敗",
    "Failed to generate a title for the chat session" : "無法產生聊天工作階段的標題",
    "Nextcloud Assistant" : "Nextcloud 小幫手",
    "Assistant task" : "小幫手任務",
    "AI text generation" : "人工智慧文字產生",
    "AI image generation" : "AI 影像產生",
    "AI audio transcription" : "人工智慧音訊轉錄",
    "AI context writer" : "人工智慧情境寫作者",
    "Writing style: %1$s; Source material: %2$s" : "寫作風格：%1$s；來源資料：%2$s",
    "Task for \"%1$s\" has finished" : "「%1$s」任務已結束",
    "\"%1$s\" task for \"%2$s\" has finished" : "「%2$s」的「%1$s」任務已結束",
    "Input: %1$s" : "輸入：%1$s",
    "Result: %1$s" : "結果：%1$s",
    "View results" : "檢視結果",
    "Task for \"%1$s\" has failed" : "「%1$s」任務失敗",
    "\"%1$s\" task for \"%2$s\" has failed" : "「%2$s」的「%1$s」任務失敗",
    "View task" : "檢視任務",
    "Generate text" : "產生文字",
    "Send a request to the Assistant, for example: write a first draft of a presentation, give me suggestions for a presentation, write a draft reply to my colleague." : "向 Assistant 傳送請求，例如：編寫一份簡報的初稿，給我簡報的建議，編寫一份給我同事回覆的草稿。",
    "Chat with AI" : "與 AI 聊天",
    "Chat with an AI model." : "與 AI 模型聊天",
    "Artificial Intelligence" : "人工智慧",
    "Process and transform text" : "處理與轉換文字",
    "This app brings a user interface to use the Nextcloud text processing feature.\n\nIt allows users to launch AI tasks, be notified when they finish and see the results.\nThe assistant also appears in others apps like Text to easily process parts of a document.\n\nMore details on the assistant OCS API and frontend integration possibilities in the\n[developer doc](https://github.com/nextcloud/assistant/raw/main/docs/developer)\n\n### How to use it\n\nA new right header menu entry appears. Once clicked, the assistant is displayed and you can select and task type and\nset the input you want to process.\n\nThe task might run immediately or be scheduled depending on the time estimation given by the AI provider.\nOnce a task is scheduled, it will run as a background job. When it is finished, you will receive a notification\nfrom which the results can be displayed.\n\nOther apps can integrate with the assistant. For example, Text will display an inline button besides every paragraph\nto directly select a task type to process this paragraph. Selecting a task this way will open the assistant with the task\nbeing pre-selected and the input text set.\n\nMore details and screenshots in the [user doc](https://github.com/nextcloud/assistant/raw/main/docs/user).\n\n## Features\n\nIn the assistant, the list of available tasks depends on the available providers installed via other apps.\nThis means you have complete freedom over which service/software will actually run your AI tasks.\n\n### Text processing providers\n\nSo far, the [Local Large language model](https://github.com/nextcloud/llm2#readme)\nand the [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai) apps\ninclude text processing providers to:\n* Summarize\n* Extract topics\n* Generate a headline\n* Get an answer from a free prompt\n* Reformulate (OpenAi/LocalAi only)\n* Context writer: Generate text with a specified style. The style can be described or provided via an example text.\n\n### Text to image (Image generation)\n\nKnown providers:\n* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)\n* [Text2Image Stable Diffusion](https://apps.nextcloud.com/apps/text2image_stablediffusion)\n\n### Speech to text (Audio transcription)\n\nKnown providers:\n* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)\n* [Local Whisper Speech-To-Text](https://apps.nextcloud.com/apps/stt_whisper)\n\nMore details on how to set this up in the [admin docs](https://docs.nextcloud.com/server/latest/admin_manual/ai/index.html)" : "此應用程式帶來了一個使用 Nextcloud 文字處理功能的使用者介面。\n\n其讓使用者可以啟動文字處理任務，在任務完成時收到通知並檢視結果。\n小幫手也會出現在「文字」等其他應用程式中，可以輕鬆處理文件的某些部分。\n\n更多關於小幫手 OCS API 與前端整合可能性的詳細資訊請見\n[開發者文件](https://github.com/nextcloud/assistant/raw/main/docs/developer)\n\n### 如何使用它\n\n出現新的右標題選單條目。點擊後，將顯示小幫手，您可以選擇任務類型與\n設定要處理的輸入文字。\n\n規劃好任務後，其將作為背景作業執行。完成後，您將會收到其中顯示結果的通知。\n\n其他應用程式可以與小幫手應用程式整合。例如，文字將在每個段落旁邊顯示一個行內按鈕，直接選擇一個任務類型來處理該段落。以這種方式選擇任務將會開啟包含該任務的助手，且預先選擇並設定輸入文字。\n\n在[使用者文件](https://github.com/nextcloud/assistant/raw/main/docs/user)中有更多詳細資訊與螢幕截圖。\n\n## 功能\n\n在小幫手中，可用任務清單取決於透過其他應用程式安裝的可用提供者。\n這代表了您可以完全自由地決定哪些服務/軟體實際執行您的文字處理任務。\n\n### 文字處理提供者\n\n到目前為止，[本機大型語言模型](https://github.com/nextcloud/llm2#readme)以及 [OpenAi/LocalAI 整合](https://apps.nextcloud.com/apps/integration_openai) 應用程式包含了文字處理提供者：\n* 總結\n* 擷取主題\n* 產生標題\n* 從免費提示中取得答案\n* 重新表述（僅限 OpenAI/LocalAI）\n* 情境作家：產生指定樣式的文字。可以透過範例文字來描述或提供樣式。\n\n已知提供者：\n* [OpenAI/LocalAI 整合](https://apps.nextcloud.com/apps/integration_openai)\n* [文字轉影像 Stable Diffusion](https://apps.nextcloud.com/apps/text2image_stablediffusion)\n\n### 語音轉文字（音訊轉錄）\n\nKnown providers:\n* [OpenAI/LocalAI 整合](https://apps.nextcloud.com/apps/integration_openai)\n* [本機 Whisper 語音轉文字](https://apps.nextcloud.com/apps/stt_whisper)\n\n更多關於如何設定的資訊，請見[管理文件](https://docs.nextcloud.com/server/latest/admin_manual/ai/index.html)",
    "Find more details on how to set up Assistant and recommended backends in the Administration documentation." : "在管理文件中尋找更多關於如何設定小幫手與建議後端的資訊。",
    "Select which features you want to enable" : "選取要啟用的功能",
    "Top-right assistant" : "右上角的小幫手",
    "To be able to use this feature, please install at least one AI text processing provider." : "要使用此功能，請安裝至少一個人工智慧文字處理提供者。",
    "AI text generation smart picker" : "人工智慧文字產生智慧型挑選程式",
    "To enable this feature, please install an AI text processing provider for the free prompt task type:" : "要啟用此功能，請安裝免費提示任務類型的人工智慧文字處理提供者：",
    "Text-to-image smart picker" : "文字轉影像智慧挑選程式",
    "To enable this feature, please install a text-to-image provider:" : "要啟用此功能，請安裝文字轉影像提供者：",
    "Speech-to-text smart picker" : "語音轉文字智慧型挑選程式",
    "To enable this feature, please install a speech-to-text provider:" : "要啟用此功能，請安裝語音轉文字提供者：",
    "Chat User Instructions for Chat Completions" : "聊天使用者完成聊天的指令",
    "It is passed on to the LLM for it to better understand the context." : "傳遞給大型語言模型，讓它可以更好地了解脈絡。",
    "\"{user}\" is a placeholder for the user's display name." : "「{user}」是使用者顯示名稱的佔位字串。",
    "Chat User Instructions for Title Generation" : "標題產生的聊天使用者說明",
    "It is passed on to the LLMs to let it know what to do" : "傳遞給大型語言模型，以告知它該做些什麼",
    "\"{user}\" is a placeholder for the user's display name here as well." : "「{user}」也是此處使用者顯示名稱的佔位字串。",
    "Last N messages to consider for chat completions" : "聊天完成時要考慮的最後 N 則訊息",
    "Number of messages to consider for chat completions (excluding the user instructions, which is always considered)" : "聊天完成時要考慮的訊息數（不包括一律考慮的使用者指令）",
    "Assistant admin options saved" : "已儲存小幫手管理選項",
    "Failed to save assistant admin options" : "儲存小幫手管理選項失敗",
    "The task ran successfully but the result is identical to the input." : "任務執行成功，但結果與輸入相同。",
    "This output was generated by AI. Make sure to double-check and adjust." : "此輸出是由人工智慧產生的。請務必仔細檢查與調整。",
    "Back to the assistant" : "回到小幫手",
    "Previous \"{taskTypeName}\" tasks" : "先前的「{taskTypeName}」工作",
    "Show previous tasks" : "顯示先前的工作",
    "Hide advanced options" : "隱藏進階選項",
    "Show advanced options" : "顯示進階選項",
    "Try again" : "再試試",
    "Send request" : "傳送請求",
    "Launch this task again" : "再次啟動此任務",
    "Launch a task" : "啟動任務",
    "Generate a first draft for a blog post about privacy" : "為關於隱私的部落格文章產生初稿",
    "What is the venue for the team retreat this quarter?" : "本季團隊靜修地點在哪裡？",
    "Type or paste the text to summarize" : "輸入或貼上要總結的文字",
    "Type or paste the text to generate a headline for" : "輸入或貼上要產生標題的文字",
    "Type or paste the text to extract the topics from" : "輸入或貼上要從中擷取主題的文字",
    "landscape trees forest peaceful" : "風景 樹木 森林 寧靜",
    "a number" : "號碼",
    "Shakespeare or an example of the style" : "莎士比亞或類似風格的例子",
    "A description of what you need or some original content" : "您需要什麼或一些原創內容的描述",
    "Close" : "關閉",
    "Close Nextcloud Assistant" : "關閉 Nextcloud 小幫手",
    "New conversation" : "新對話",
    "No conversations yet" : "尚無對話",
    "Delete" : "刪除",
    "Conversation title" : "對話標題",
    "Edit title" : "編輯標題",
    "Generate title" : "產生標題",
    "Creating a new conversation" : "建立新對話",
    "Hello there! What can I help you with today?" : "嗨！我現在能怎麼協助您呢？",
    "Try sending a message to spark a conversation." : "嘗試傳送訊息來引發對話。",
    "Load older messages" : "載入較舊的訊息",
    "Retry response generation" : "重試回應產生",
    "Error updating title of conversation" : "更新對話標題時發生錯誤",
    "Untitled conversation" : "未命名對話",
    "Error generating a title for the conversation" : "產生對話的標題時發生錯誤",
    "Error deleting conversation" : "刪除對話時發生錯誤",
    "Error fetching conversations" : "擷取對話時發生錯誤",
    "Error deleting message" : "刪除訊息時發生錯誤",
    "Error fetching messages" : "擷取訊息時發生錯誤",
    "Error creating a new message" : "建立新訊息時發生錯誤",
    "Invalid response received for a new conversation request" : "收到無效的回應，無法建立新對話請求",
    "Error creating a new conversation" : "建立新對話時發生錯誤",
    "Error generating a response" : "產生回應時發生錯誤",
    "Error regenerating a response" : "重新產生回應時發生錯誤",
    "Error loading messages" : "載入訊息時發生錯誤",
    "The text must be shorter than or equal to {maxLength} characters, currently {length}" : "目前文字長度為 {length}，必須小於或等於 {maxLength} 個字元",
    "Cancel editing" : "取消編輯",
    "Submit" : "遞交",
    "You" : "您",
    "Message copied to clipboard" : "訊息已複製到剪貼簿",
    "Copy message" : "複製訊息",
    "Regenerate message" : "重新產生訊息",
    "Delete message" : "刪除訊息",
    "Selective context" : "選擇性脈絡",
    "Select Files/Folders" : "選取檔案/資料夾",
    "Select Providers" : "選取提供者",
    "Choose Files/Folders" : "選擇檔案/資料夾",
    "Choose" : "選擇",
    "Clear Selection" : "清除選取範圍",
    "Error fetching default provider key" : "擷取預設提供者金鑰時發生錯誤",
    "Error fetching providers" : "擷取提供者時發生錯誤",
    "No sources referenced" : "沒有引用來源",
    "No provider found" : "找不到提供者",
    "AI Providers need to be installed to use the Assistant" : "必須安裝人工智慧提供者以使用小幫手",
    "tool" : "工具",
    "integration" : "整合",
    "complete AI documentation" : "完整人工智慧文件",
    "AI provider apps can be found in the {toolLink} and {integrationLink} app settings sections." : "人工智慧提供者應用程式可在 {toolLink} 與 {integrationLink} 的應用程式設定區塊中找到。",
    "You can also check the {aiAdminDocLink}" : "您也可以看看 {aiAdminDocLink}",
    "AI image generation smart picker" : "人工智慧影像產生智慧型挑選程式",
    "AI transcription smart picker" : "人工智慧轉錄智慧型挑選程式",
    "No suitable providers are available. They must first be enabled by your administrator." : "沒有可用的合適提供者。它們必須先由您的管理緣起用。",
    "Assistant options saved" : "已儲存小幫手選項",
    "Failed to save assistant options" : "儲存小幫手選項失敗",
    "Getting results…" : "正在取得結果……",
    "Run in the background and get notified" : "在背景執行並收到通知",
    "Cancel" : "取消",
    "You will receive a notification when it has finished" : "完成後，您將會收到通知",
    "Your task has been scheduled" : "您的任務已排程",
    "Nothing yet" : "還沒有",
    "You have not submitted any \"{taskTypeName}\" task yet" : "您尚未遞交任何「{taskTypename}」任務",
    "Succeeded" : "成功了",
    "Cancelled" : "已取消",
    "Failed" : "失敗",
    "Running" : "跑步",
    "Scheduled" : "已安排",
    "Input" : "輸入",
    "Result" : "結果",
    "This task is scheduled" : "此工作已排程",
    "Unknown status" : "未知狀態",
    "_{n} image has been generated_::_{n} images have been generated_" : ["已產生了 {n} 個影像"],
    "_Generation of {n} image is scheduled_::_Generation of {n} images is scheduled_" : ["已排程產生 {n} 個影像"],
    "Start recording" : "開始錄音",
    "Dismiss recording" : "取消錄音",
    "End recording and send" : "結束錄製並傳送",
    "Error while recording audio" : "錄製音訊時發生錯誤",
    "Choose file" : "選擇檔案",
    "Choose a file" : "選擇檔案",
    "No file selected" : "未選擇任何檔案",
    "Choose a value" : "選擇值",
    "Upload from device" : "從裝置上傳",
    "Select from Nextcloud" : "從 Nextcloud 選取",
    "Pick one or multiple files" : "挑選一個或多個檔案",
    "Remove this media" : "移除此媒體",
    "Download this media" : "下載此媒體",
    "Share this media" : "分享此媒體",
    "Share" : "分享",
    "Could not upload the recorded file" : "無法上傳錄製的檔案",
    "Output file share link copied to clipboard" : "輸出檔案分享連結已分享至剪貼簿",
    "Could not copy to clipboard" : "無法複製到剪貼簿",
    "Pick a file" : "挑選檔案",
    "Clear value" : "清除值",
    "Type some number" : "輸入一些數字",
    "The current value is not a number" : "目前值不是數字",
    "Advanced" : "進階",
    "Copy output" : "複製輸出",
    "Copy" : "複製",
    "Choose a text file" : "選擇文字檔案",
    "Unexpected response from text parser" : "來自文字解析程式的意外回應",
    "Could not parse file" : "無法解析檔案",
    "Result could not be copied to clipboard" : "結果無法複製到剪貼簿",
    "Upload file" : "上傳檔案",
    "Could not upload the file" : "無法上傳檔案",
    "Could not upload the files" : "無法上傳檔案",
    "Your task has failed" : "您的任務已失敗",
    "Failed to schedule your task" : "無法安排您的任務",
    "Submit the current task's result" : "遞交目前任務的結果",
    "Assistant error" : "Assistant 錯誤",
    "Please log in to view the task result" : "請登入以檢視任務結果",
    "This task does not exist or has been cleaned up" : "此任務不存在或已被清除",
    "Failed to schedule the task" : "安排任務失敗",
    "Failed to get the last message" : "無法取得最後一則訊息",
    "Failed to process prompt; unknown user" : "處理提示失敗；未知的使用者",
    "Failed to get prompt history; unknown user" : "取得提示歷史紀錄失敗；未知的使用者",
    "Failed to get outputs; unknown user" : "取得輸出失敗；未知的使用者",
    "Failed to cancel generation; unknown user" : "取消產生失敗；未知的使用者",
    "Some internal error occurred. Contact your sysadmin for more info." : "遇到一些內部錯誤。聯絡您的系統管理員以取得更多資訊。",
    "No Speech-to-Text provider found, install one from the app store to use this feature." : "找不到語音轉文字提供者，請從應用程式商店安裝一個以使用此功能。",
    "Audio file not found." : "找不到音訊檔",
    "No permission to create recording file/directory, contact your sysadmin to resolve this issue." : "無權建立錄製檔案/目錄，聯絡您的系統管理員以姐此問題。",
    "Failed to set visibility of image files; unknown user" : "設定影像檔案能見度失敗；未知的使用者",
    "Unknown error while retrieving prompt history." : "擷取提示歷史紀錄時發生未知錯誤。",
    "Context write" : "寫作情境",
    "Writes text in a given style based on the provided source material." : "根據提供的來源資料以指定的風格編寫文字",
    "Transcribe" : "轉錄",
    "Transcribe audio to text" : "將音訊轉錄為文字",
    "Generate image" : "產生影像",
    "Generate an image from a text" : "從文字產生影像",
    "Canceled by user" : "已被使用者取消",
    "FreePromptTaskType not available" : "FreePromptTaskType 不可用",
    "Failed to run or schedule a task" : "執行或安排任務失敗",
    "Failed to get prompt history" : "取得提示歷史紀錄失敗",
    "Generation not found" : "找不到產生",
    "Multiple tasks found" : "找到多個任務",
    "Transcript not found" : "找不到轉錄",
    "No text to image processing provider available" : "無可用的文字轉影像處理提供者",
    "Image request error" : "影像請求錯誤",
    "Image generation not found." : "找不到影像產生。",
    "Retrieving the image generation failed." : "擷取產生的影像失敗。",
    "Image generation failed." : "影像產生失敗。",
    "Image file names could not be fetched from database" : "無法從資料庫擷取影像檔案名稱",
    "Image file not found in database" : "資料庫中找不到影像檔案",
    "Image file not found" : "找不到影像檔案",
    "This app brings a user interface to use the Nextcloud text processing feature.\n\nIt allows users to launch AI tasks, be notified when they finish and see the results.\nThe assistant also appears in others apps like Text to easily process parts of a document.\n\nMore details on the assistant OCS API and frontend integration possibilities in the\n[developer doc](https://github.com/nextcloud/assistant/raw/main/docs/developer)\n\n### How to use it\n\nA new right header menu entry appears. Once clicked, the assistant is displayed and you can select and task type and\nset the input you want to process.\n\nThe task might run immediately or be scheduled depending on the time estimation given by the AI provider.\nOnce a task is scheduled, it will run as a background job. When it is finished, you will receive a notification\nfrom which the results can be displayed.\n\nOther apps can integrate with the assistant. For example, Text will display an inline button besides every paragraph\nto directly select a task type to process this paragraph. Selecting a task this way will open the assistant with the task\nbeing pre-selected and the input text set.\n\nMore details and screenshots in the [user doc](https://github.com/nextcloud/assistant/raw/main/docs/user).\n\n## Features\n\nIn the assistant, the list of available tasks depends on the available providers installed via other apps.\nThis means you have complete freedom over which service/software will actually run your AI tasks.\n\n### Text processing providers\n\nSo far, the [Large language model](https://github.com/nextcloud/llm#readme)\nand the [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai) apps\ninclude text processing providers to:\n* Summarize\n* Extract topics\n* Generate a headline\n* Get an answer from a free prompt\n* Reformulate (OpenAi/LocalAi only)\n* Context writer: Generate text with a specified style. The style can be described or provided via an example text.\n\n### Text to image (Image generation)\n\nKnown providers:\n* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)\n* [Text2Image Stable Diffusion](https://apps.nextcloud.com/apps/text2image_stablediffusion)\n\n### Speech to text (Audio transcription)\n\nKnown providers:\n* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)\n* [Local Whisper Speech-To-Text](https://apps.nextcloud.com/apps/stt_whisper)" : "此應用程式帶來了一個使用 Nextcloud 文字處理功能的使用者介面。\n\n其讓使用者可以啟動文字處理任務，在任務完成時收到通知並檢視結果。\n小幫手也會出現在「文字」等其他應用程式中，可以輕鬆處理文件的某些部分。\n\n更多關於小幫手 OCS API 與前端整合可能性的詳細資訊請見\n[開發者文件](https://github.com/nextcloud/assistant/raw/main/docs/developer)\n\n### 如何使用它\n\n出現新的右標題選單條目。點擊後，將顯示小幫手，您可以選擇任務類型與\n設定要處理的輸入文字。\n\n規劃好任務後，其將作為背景作業執行。完成後，您將會收到其中顯示結果的通知。\n\n其他應用程式可以與小幫手應用程式整合。例如，文字將在每個段落旁邊顯示一個行內按鈕，直接選擇一個任務類型來處理該段落。以這種方式選擇任務將會開啟包含該任務的助手，且預先選擇並設定輸入文字。\n\n在[使用者文件](https://github.com/nextcloud/assistant/raw/main/docs/user)中有更多詳細資訊與螢幕截圖。\n\n## 功能\n\n在小幫手中，可用任務清單取決於透過其他應用程式安裝的可用提供者。\n這代表了您可以完全自由地決定哪些服務/軟體實際執行您的文字處理任務。\n\n### 文字處理提供者\n\n到目前為止，[大型語言模型](https://github.com/nextcloud/llm#readme)以及 [OpenAi/LocalAI 整合](https://apps.nextcloud.com/apps/integration_openai) 應用程式包含了文字處理提供者：\n* 總結\n* 擷取主題\n* 產生標題\n* 從免費提示中取得答案\n* 重新表述（僅限 OpenAI/LocalAI）\n* 情境作家：產生指定樣式的文字。可以透過範例文字來描述或提供樣式。\n\n已知提供者：\n* [OpenAI/LocalAI 整合](https://apps.nextcloud.com/apps/integration_openai)\n* [文字轉影像 Stable Diffusion](https://apps.nextcloud.com/apps/text2image_stablediffusion)\n\n### 語音轉文字（音訊轉錄）\n\nKnown providers:\n* [OpenAI/LocalAI 整合](https://apps.nextcloud.com/apps/integration_openai)\n* [本機 Whisper 語音轉文字](https://apps.nextcloud.com/apps/stt_whisper)",
    "To enable this feature, please install an AI text processing provider for the free prompt task type." : "要啟用此功能，請安裝免費提示任務類型的人工智慧文字處理提供者。",
    "To enable this feature, please install a text-to-image provider." : "要啟用此功能，請安裝文字轉影像提供者。",
    "To enable this feature, please install a speech-to-text provider." : "要啟用此功能，請安裝語音轉文字提供者。",
    "Image storage" : "影像儲存",
    "Image generation idle time (days)" : "影像產生閒置時間（天）",
    "Days until generated images are deleted if they are not viewed" : "直到影像產生後的天數，如果未被檢視，則將其刪除",
    " This includes the user instructions and the LLM's messages" : "這包含使用者指令與大型語言模型的訊息",
    "Writing style" : "寫作風格",
    "Describe the writing style you want to use or supply an example document." : "描述您想要使用的寫作風格或提供範例文件。",
    "Source material" : "來源資料",
    "Describe what you want the document to be written on." : "描述您希望文件寫入的內容。",
    "Type some text" : "輸入一些文字",
    "Output" : "輸出",
    "Copy output text to clipboard" : "複製輸出文字到剪貼簿",
    "Reset the output value to the originally generated one" : "重設輸出值為最初產生的值",
    "Reset" : "重設",
    "Text Generation" : "文字產生",
    "Audio transcription" : "音訊轉錄",
    "Unknown Result Type" : "未知的結果類型",
    "The task ran successfully but the generated text is empty." : "任務執行成功，但產生的文字為空。",
    "Run a task" : "執行任務",
    "Loading conversations..." : "正在載入對話……",
    "Edit Title" : "編輯標題",
    "Generate Title" : "產生標題",
    "Loading messages..." : "正在載入訊息……",
    "Type a message..." : "請輸入訊息……",
    "Thinking..." : "正在思考……",
    "Text generation content" : "文字產生內容",
    "The text generation task was scheduled to run in the background." : "文字產生任務已安排於背景執行。",
    "Estimated completion time: " : "預計補齊時間：",
    "This can take a while…" : "這可能需要一段時間……",
    "Some generations are still being processed in the background! Showing finished generations." : "部份產生仍在背景處理！顯示已完成的產生。",
    "Loading generations…" : "正在載入產生……",
    "Include prompt in the final result" : "在最終結果中包含提示詞",
    "Unexpected server response" : "未預期的伺服器回應",
    "The processing of generations failed." : "產生處理失敗。",
    "The processing of some generations failed." : "部份產生處理失敗。",
    "Text generation error" : "文字產生錯誤",
    "Unknown text generation API error" : "未知的文字產生 API 錯誤",
    "Prompt" : "提示詞",
    "Result {index}" : "結果 {index}",
    "Run in the background" : "背景執行",
    "Record Audio" : "錄製音訊",
    "Choose Audio File" : "選擇音訊檔",
    "Reset recorded audio" : "重設已錄製的音訊",
    "Stop recording" : "停止錄音",
    "No audio file selected" : "未選取音訊檔",
    "Selected Audio File:" : "選取的音訊檔：",
    "Choose audio file in your storage" : "選擇您儲存空間中的音訊檔",
    "Choose audio File" : "選擇音訊檔",
    "Copy result" : "複製結果",
    "Audio input" : "音訊輸入",
    "Unknown input" : "未知輸入",
    "Running…" : "正在執行……",
    "Unknown error" : "未知的錯誤",
    "Task result was copied to clipboard" : "工作結果已複製到剪貼簿",
    "Image generation" : "影像產生",
    "Edit visible images" : "編輯可見的影像",
    "Click to toggle generation visibility" : "點擊切換產生能見度",
    "Generated image" : "已產生影像",
    "This generation has no visible images" : "這次產生沒有可見的影像",
    "Estimated generation time left: " : "預計剩餘產生時間：",
    "The image(s) will be displayed here once generated." : "影像將在此處產生後顯示。",
    "This image generation was scheduled to run in the background." : "此影像產生已安排於背景執行。",
    "Image generation failed" : "影像產生失敗",
    "Rate limit reached. Please try again later." : "已達速率限制。請稍後再試。",
    "Unknown server query error" : "未知的伺服器查詢錯誤",
    "Failed to get images" : "取得影像失敗",
    "Include the prompt in the result" : "在結果中包含提示詞",
    "Number of results" : "結果數量",
    "Enter your question or task here:" : "在此輸入您的問題或任務：",
    "Preview text generation by AI" : "預覽人工智慧產生的文字",
    "Notify when ready" : "準備好時通知",
    "Submit text generated by AI" : "遞交人工智慧產生的文字",
    "Regenerate" : "重新產生",
    "Preview" : "預覽",
    "You will be notified when the text generation is ready." : "文字產生就緒時將會通知您。",
    "Notify when ready error" : "就緒時通知錯誤",
    "Unknown notify when ready error" : "未知的就緒時通知錯誤",
    "The task could not be found. It may have been deleted." : "找不到任務。可能已被刪除。",
    "Schedule Transcription" : "安排轉錄",
    "Successfully scheduled transcription" : "成功安排轉錄",
    "Failed to schedule transcription" : "安排轉錄失敗",
    "Unknown API error" : "未知的 API 錯誤",
    "Preview image generation by AI" : "預覽人工智慧影像產生",
    "Submit image(s) generated by AI" : "遞交由人工智慧產生的影像",
    "Send" : "傳送",
    "Show/hide advanced options" : "顯示/隱藏進階選項",
    "Advanced options" : "進階選項",
    "A description of the image you want to generate" : "描述您想要產生的影像",
    "Image generation cancel error" : "影像產生取消錯誤",
    "Unknown image generation cancel error" : "未知的影像產生取消錯誤",
    "Unexpected response from server." : "來自伺服器的非預期回應。",
    "Image generation error" : "影像產生錯誤",
    "Unknown image generation error" : "未知的產生影像錯誤",
    "You will be notified when the image generation is ready." : "影像產生就緒時將會通知您。",
    "Copy the link to this generation to clipboard" : "複製到此產生的連結至剪貼簿",
    "Copy link to clipboard" : "複製連結到剪貼簿",
    "Image link copied to clipboard" : "影像連結已複製到剪貼簿",
    "Image link could not be copied to clipboard" : "影像連結無法複製剪貼簿"
},
"nplurals=1; plural=0;");
