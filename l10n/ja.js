OC.L10N.register(
    "assistant",
    {
    "Failed to notify when ready; unknown user" : "準備完了時の通知に失敗しました;不明なユーザーです",
    "Send an email" : "メールを送信",
    "User not logged in" : "ユーザーがログインしていません",
    "User not found" : "ユーザーが見つかりません",
    "Failed to create a chat session" : "チャットセッションの作成に失敗しました ",
    "Failed to update the chat session" : "チャットセッションの更新に失敗しました",
    "Failed to delete the chat session" : "チャットセッションの削除に失敗しました",
    "Failed to get chat sessions" : "チャットセッションの取得に失敗しました",
    "Session not found" : "セッションが見つかりません",
    "Message content is empty" : "メッセージの内容が空です",
    "Failed to add a chat message" : "チャットメッセージを追加できませんでした",
    "Failed to get chat messages" : "チャットメッセージの取得に失敗しました",
    "Failed to delete a chat message" : "チャットメッセージの削除に失敗しました",
    "Failed to delete the last message" : "最後のメッセージの削除に失敗しました",
    "Failed to add a chat message into DB" : "チャットメッセージをデータベースに追加することに失敗しました",
    "Failed to generate a title for the chat session" : "チャットセッションのためのタイトルの生成に失敗しました",
    "Nextcloud Assistant" : "Nextcloud アシスタント",
    "Assistant task" : "アシスタントタスク",
    "AI text generation" : "AIテキスト生成",
    "AI image generation" : "AIによる画像生成",
    "AI audio transcription" : "音声文字起こしAI ",
    "AI context writer" : "文章作成AI",
    "Writing style: %1$s; Source material: %2$s" : "例文体: %1$s;  元ドキュメント: %2$s",
    "Task for \"%1$s\" has finished" : "タスク \"%1$s\" は完了しました",
    "\"%1$s\" task for \"%2$s\" has finished" : "%2$sの%1$sタスクが完了しました",
    "Input: %1$s" : "入力: %1$s",
    "Result: %1$s" : "結果 %1$s",
    "View results" : "結果を見る",
    "Task for \"%1$s\" has failed" : "タスク \"%1$s\" が失敗しました",
    "\"%1$s\" task for \"%2$s\" has failed" : "%2$sの%1$sタスクがしっぱい失敗しました",
    "View task" : "タスクを表示",
    "Nextcloud Deck" : "Nextcloud Deck",
    "Nextcloud Mail" : "Nextcloud メール",
    "Nextcloud Talk" : "Nextcloud Talk",
    "Chat with AI" : "AIとチャット",
    "Chat with an AI model." : "AIモデルとチャット",
    "Generate text" : "テキスト生成",
    "Send a request to the Assistant, for example: write a first draft of a presentation, give me suggestions for a presentation, write a draft reply to my colleague." : "アシスタントのリクエストを送信する、例:「プレゼンテーションの最初稿を書いてください」「プレゼンテーションの提案をください」「私の同僚への返信の下書きを書いてください」",
    "Artificial Intelligence" : "人工知能",
    "System prompt" : "システムプロンプト",
    "Define rules and assumptions that the assistant should follow during the conversation." : "会話中にアシスタントが従うべきルールと前提条件を定義します。",
    "Describe a task that you want the assistant to do or ask a question" : "アシスタントに実行してほしいタスクまたは質問を記述します",
    "Chat history" : "チャット履歴",
    "The history of chat messages before the current message, starting with a message by the user" : "ユーザーのメッセージから始まる、現在のメッセージより前のチャットメッセージの履歴",
    "Assistant" : "アシスタント",
    "Process and transform text" : "テキストの処理と変換",
    "This app brings a user interface to use the Nextcloud text processing feature.\n\nIt allows users to launch AI tasks, be notified when they finish and see the results.\nThe assistant also appears in others apps like Text to easily process parts of a document.\n\nMore details on the assistant OCS API and frontend integration possibilities in the\n[developer doc](https://github.com/nextcloud/assistant/raw/main/docs/developer)\n\n### How to use it\n\nA new right header menu entry appears. Once clicked, the assistant is displayed and you can select and task type and\nset the input you want to process.\n\nThe task might run immediately or be scheduled depending on the time estimation given by the AI provider.\nOnce a task is scheduled, it will run as a background job. When it is finished, you will receive a notification\nfrom which the results can be displayed.\n\nOther apps can integrate with the assistant. For example, Text will display an inline button besides every paragraph\nto directly select a task type to process this paragraph. Selecting a task this way will open the assistant with the task\nbeing pre-selected and the input text set.\n\nMore details and screenshots in the [user doc](https://github.com/nextcloud/assistant/raw/main/docs/user).\n\n## Features\n\nIn the assistant, the list of available tasks depends on the available providers installed via other apps.\nThis means you have complete freedom over which service/software will actually run your AI tasks.\n\n### Text processing providers\n\nSo far, the [Local Large language model](https://github.com/nextcloud/llm2#readme)\nand the [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai) apps\ninclude text processing providers to:\n* Summarize\n* Extract topics\n* Generate a headline\n* Get an answer from a free prompt\n* Reformulate (OpenAi/LocalAi only)\n* Context writer: Generate text with a specified style. The style can be described or provided via an example text.\n\n### Text to image (Image generation)\n\nKnown providers:\n* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)\n* [Text2Image Stable Diffusion](https://apps.nextcloud.com/apps/text2image_stablediffusion)\n\n### Speech to text (Audio transcription)\n\nKnown providers:\n* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)\n* [Local Whisper Speech-To-Text](https://apps.nextcloud.com/apps/stt_whisper)\n\nMore details on how to set this up in the [admin docs](https://docs.nextcloud.com/server/latest/admin_manual/ai/index.html)" : "このアプリは、Nextcloudのテキスト処理機能を使用するためのユーザーインターフェースを提供します。\n\nユーザーはAIタスクを起動し、完了時に通知を受け取り、結果を確認することができます。アシスタントは、他のアプリ（例えば、テキスト）でも表示され、ドキュメントの一部を簡単に処理することが可能です。\n\nアシスタントのOCS APIやフロントエンド統合の詳細については[開発者向けドキュメント](https://github.com/nextcloud/assistant/raw/main/docs/developer)をご覧ください。\n\n### 使い方\n\n右上のメニューに新しい項目が表示されます。クリックすると、アシスタントが表示され、タスクの種類を選び、処理したい入力内容を設定することができます。\n\nタスクは、AIプロバイダーが提供する時間予測に応じて、即座に実行されるか、スケジュールに従って実行されます。タスクがスケジュールされると、バックグラウンドジョブとして実行されます。完了すると通知が届き、そこから結果を確認することができます。\n\n他のアプリでもアシスタントと連携できます。例えば、テキストアプリでは、各段落の横にインラインボタンが表示され、その段落に対するタスクの種類を直接選ぶことができます。この方法でタスクを選択すると、アシスタントが開き、あらかじめ選択されたタスクと入力テキストが設定されます。\n\n詳細やスクリーンショットについては[ユーザードキュメント](https://github.com/nextcloud/assistant/raw/main/docs/user)をご覧ください。\n\n## 機能\n\nアシスタントでは、利用可能なタスクのリストは他のアプリを通じてインストールされたプロバイダーに依存します。これにより、どのサービスやソフトウェアでAIタスクを実行するかを自由に選択することができます。\n\n### テキスト処理プロバイダー\n\n現在のところ、[Local Large Language Model](https://github.com/nextcloud/llm2#readme)や[OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)アプリがテキスト処理プロバイダーとして以下を提供しています：\n* 要約\n* トピックの抽出\n* 見出しの生成\n* フリープロンプトからの回答取得\n* リフォーム（OpenAi/LocalAi限定）\n* コンテキストライター: 指定されたスタイルでテキストを生成します。スタイルは説明するか、サンプルテキストを提供することで指定できます。\n\n### テキストから画像へ（画像生成）\n\n既知のプロバイダー:\n* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)\n* [Text2Image Stable Diffusion](https://apps.nextcloud.com/apps/text2image_stablediffusion)\n\n### 音声からテキストへ（音声の文字起こし）\n\n既知のプロバイダー:\n* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)\n* [Local Whisper Speech-To-Text](https://apps.nextcloud.com/apps/stt_whisper)\n\n設定方法の詳細については[管理者ドキュメント](https://docs.nextcloud.com/server/latest/admin_manual/ai/index.html)をご覧ください。",
    "Assistant admin options saved" : "アシスタントの管理者オプションを保存しました",
    "Failed to save assistant admin options" : "アシスタント管理者オプションの保存に失敗しました",
    "Find more details on how to set up Assistant and recommended backends in the Administration documentation." : "アシスタントの設定方法と推奨されるバックエンドの詳細については、管理ドキュメントを参照してください。",
    "Select which features you want to enable" : "あなたは有効したい機能を選択してください",
    "To enable this feature, please install a text-to-image provider:" : "この機能を有効にする場合は、text-to-imageプロバイダーをインストールしてください:",
    "To enable this feature, please install a speech-to-text provider:" : "この機能を有効にする場合は speech-to-textプロバイダーをインストールしてください:",
    "Chat User Instructions for Chat Completions" : "チャットでの生成の為のユーザーインストラクション",
    "It is passed on to the LLM for it to better understand the context." : "これは、その文脈をよりよく理解するためにLLMに渡されます。",
    "\"{user}\" is a placeholder for the user's display name." : "「{user}」はユーザーの表示名のプレースホルダーです。",
    "Chat User Instructions for Title Generation" : "タイトル生成のためのチャットユーザーインストラクション",
    "It is passed on to the LLMs to let it know what to do" : "これは、LLMが何をすべきかを知らせるためにLLMに渡されます。",
    "\"{user}\" is a placeholder for the user's display name here as well." : "「{user}」はこれもユーザーの表示名のプレースホルダーです。",
    "Last N messages to consider for chat completions" : "最後からN個のメッセージがチャットでの生成に利用されます",
    "This includes the user instructions and the LLM's messages" : "ここには、ユーザーの指示とLLMのメッセージの両方が含まれます",
    "Number of messages to consider for chat completions (excluding the user instructions, which is always considered)" : "チャットでの生成に利用されるメッセージ数（ユーザーの指示を除く。）",
    "The task ran successfully but the result is identical to the input." : "タスクは正常に実行されましたが、結果は入力と同じでした。",
    "This output was generated by AI. Make sure to double-check and adjust." : "このデータはAIにより生成されました。必ずダブルチェックして調整してください",
    "Hide advanced options" : "詳細オプションを隠す",
    "Show advanced options" : "詳細オプションを表示する",
    "Try again" : "もう一度試してください",
    "Send request" : "リクエストを送信",
    "Launch this task again" : "このタスクを再度実行する",
    "Launch a task" : "タスクを実行",
    "Generate a first draft for a blog post about privacy" : "プライバシーに関するブログ投稿の初稿を生成してください",
    "What is the venue for the team retreat this quarter?" : "今四半期のチーム合宿の会場はどこですか？",
    "Type or paste the text to summarize" : "要約するためのテキストを入力または貼り付けてください",
    "Type or paste the text to generate a headline for" : "見出しを生成するためのテキストを入力または貼り付けてください",
    "Type or paste the text to extract the topics from" : "トピックを抽出するためのテキストを入力または貼り付けてください",
    "landscape trees forest peaceful" : "風景の木々、森、静けさ",
    "a number" : "数字",
    "Shakespeare or an example of the style" : "シェイクスピア、またはそのスタイルの例",
    "A description of what you need or some original content" : "必要なものの説明、またはオリジナルコンテンツ",
    "Close" : "閉じる",
    "Close Nextcloud Assistant" : "Nextcloudアシスタントを閉じる",
    "Less" : "以下",
    "More" : "さらに表示",
    "Cancel" : "キャンセル",
    "Error generating a response" : "応答の生成中にエラーが発生しました",
    "Error updating title of conversation" : "会話のタイトルの更新中にエラーが発生しました",
    "Untitled conversation" : "未タイトルの会話",
    "Error generating a title for the conversation" : "会話のタイトル生成中にエラーが発生しました",
    "Error deleting conversation" : "会話の削除中にエラーが発生しました",
    "Error fetching conversations" : "会話の取得中にエラーが発生しました",
    "Error deleting message" : "メッセージの削除中にエラーが発生しました",
    "Error fetching messages" : "メッセージの取得中にエラーが発生しました",
    "Error creating a new message" : "新しいメッセージの作成中にエラーが発生しました",
    "Invalid response received for a new conversation request" : "新しい会話リクエストに対して無効な応答が受信されました",
    "Error creating a new conversation" : "新しい会話の作成中にエラーが発生しました",
    "Error regenerating a response" : "応答の再生成中にエラーが発生しました",
    "New conversation" : "新しい会話",
    "Loading conversations…" : "会話を読み込んでいます...",
    "No conversations yet" : "まだ会話はありません",
    "Delete" : "削除",
    "Conversation title" : "会話のタイトル",
    "Edit title" : "タイトルを編集",
    "Generate title" : "タイトルを生成",
    "Creating a new conversation" : "新しい会話を作成しています",
    "Hello there! What can I help you with today?" : " こんにちは！何かお手伝いできることはありますか？",
    "Try sending a message to spark a conversation." : "メッセージを送って会話を始めてみましょう。",
    "Load older messages" : "以前のメッセージを読み込む",
    "Retry response generation" : "応答の生成を再試行",
    "Error loading messages" : "メッセージの読み込み中にエラーが発生しました",
    "Loading messages…" : "メッセージを読み込んでいます...",
    "The text must be shorter than or equal to {maxLength} characters, currently {length}" : "テキストは {maxLength} 文字以下でなければなりません。現在の文字数は {length} です　",
    "Cancel editing" : "編集をキャンセル",
    "Submit" : "提出する",
    "Type a message…" : "メッセージを入力してください...",
    "Processing…" : "処理中...",
    "Could not upload the recorded file" : "録音されたファイルをアップロードできませんでした",
    "You" : "自分",
    "Message copied to clipboard" : "メッセージがクリップボードにコピーされました",
    "Copy message" : "メッセージをコピー",
    "Regenerate message" : "メッセージを再生成",
    "Delete message" : "メッセージを削除",
    "Select Files/Folders" : "ファイル/フォルダーを選択",
    "Select Providers" : "プロバイダーを選択",
    "Choose Files/Folders" : "ファイル/フォルダーを選択",
    "Choose" : "選択",
    "Clear Selection" : "セッションクリア",
    "Error fetching default provider key" : "デフォルトのプロバイダーキーの取得エラー",
    "Error fetching providers" : "プロバイダの取得エラー",
    "Selective context" : "選択できるコンテキスト",
    "No sources referenced" : "参照されたソースがありません",
    "tool" : "ツール",
    "integration" : "統合",
    "complete AI documentation" : " AI ドキュメントが完成しました",
    "AI provider apps can be found in the {toolLink} and {integrationLink} app settings sections." : "AIプロバイダーアプリは{toolLink}と{integrationLink}のアプリ設定セクションで見つかりました",
    "You can also check the {aiAdminDocLink}" : " {aiAdminDocLink}からチェックできます",
    "No provider found" : "プロバイダが見つかりません",
    "AI Providers need to be installed to use the Assistant" : "アシスタントを使用するには AI プロバイダーをインストールする必要があります",
    "Assistant options saved" : "アシスタントオプションを保存しました",
    "Failed to save assistant options" : "アシスタントオプションが保存できませんでした",
    "No suitable providers are available. They must first be enabled by your administrator." : "適切なプロバイダーがありません。最初にあなたの管理者に有効してもらってください",
    "The following services are used as backends for Nextcloud Assistant:" : "Nextcloud Assistantには以下のサービスがバックエンドとして使用されます：",
    "This may take a few seconds…" : "これには数秒かかることがあります…",
    "Getting results…" : "結果を取得",
    "Cancel task" : "タスクをキャンセル",
    "You have not submitted any \"{taskTypeName}\" task yet" : "あなたの{taskTypeName}タスクはまだ提出されていません",
    "Nothing yet" : "まだなにもありません",
    "Succeeded" : "成功",
    "Cancelled" : "キャンセル済",
    "Failed" : "失敗しました",
    "Running" : "ランニング",
    "Scheduled" : "予定済み",
    "Unknown status" : "不明なステータス",
    "Audio input" : "音声入力",
    "Audio output" : "音声の出力",
    "Translate" : "翻訳",
    "Other" : "その他",
    "Error while recording audio" : "音声録音中にエラーが発生しました",
    "Start recording" : "レコーディングを開始する",
    "Dismiss recording" : "録音をキャンセルする",
    "End recording and send" : "録音を終了して送信する",
    "Choose file" : "ファイルを選択",
    "Choose a file" : "ファイルを選択する",
    "No file selected" : "選択したファイルが見つかりません",
    "Choose a value" : "値を選ぶ",
    "Output file share link copied to clipboard" : "出力ファイルの共有リンクがクリップボードにコピーされました",
    "Could not copy to clipboard" : "クリップボードにコピーできませんでした",
    "Upload from device" : "デバイスからアップロード",
    "Select from Nextcloud" : "Nextcloud から選択",
    "Pick one or multiple files" : "1つ以上のファイルを選ぶ",
    "Remove this media" : "このメディアを削除",
    "Download this media" : "このメディアをダウンロード",
    "Share this media" : "このメディアを共有",
    "Pick a file" : "ファイルを選択",
    "Clear value" : "値をクリア",
    "Type some number" : "数字を入力してください",
    "The current value is not a number" : "現在の値は数字ではありません",
    "Advanced" : "高度な",
    "Choose a text file" : "テキストファイルを選択する",
    "Unexpected response from text parser" : "テキストパーサーから予期されていない応答がありました",
    "Could not parse file" : "ファイルを解析ができなかったです",
    "Result could not be copied to clipboard" : "結果をクリップボードにコピーできませんでした",
    "Copy output" : "出力のコピー",
    "Copy" : "コピー",
    "Upload file" : "ファイルをアップロード",
    "Could not upload the file" : "ファイルをアップロードできませんでした",
    "Could not upload the files" : "ファイルをアップロードできませんでした",
    "Your task with ID {id} has failed" : "ID {id} のタスクが失敗しました",
    "Failed to schedule your task" : "あなたのタスクの予定が失敗しました",
    "Submit the current task's result" : "現在のタスクの結果を提出",
    "Assistant error" : "アシスタントエラー",
    "Please log in to view the task result" : "タスクの結果を表示するにはログインしてください",
    "This task does not exist or has been cleaned up" : "このタスクは存在していません。または削除されています。",
    "Summarize" : "要約する",
    "Transcribe audio" : "音声の書き起こし"
},
"nplurals=1; plural=0;");
