# Chat Bot Streaming Feature

## Overview

The Nextcloud Assistant Chat Bot now supports real-time streaming responses using Server-Sent Events (SSE). This provides immediate visual feedback as the LLM generates responses, similar to ChatGPT and Claude web interfaces.

## Features

- **Real-time streaming**: Tokens appear as they're generated
- **Automatic fallback**: Falls back to polling if streaming is unavailable or fails
- **Backward compatible**: Works alongside the existing polling system
- **Provider agnostic**: Supports any OpenAI-compatible streaming API

## Architecture

```
User sends message → Frontend tries streaming → EventSource connects to /chat/stream →
Backend streams chunks from LLM provider → Chunks appear in UI in real-time →
Complete message saved to database
```

If streaming fails or is not configured, the system automatically falls back to the traditional polling approach.

## Configuration

### Admin Settings

Streaming requires configuration of the streaming provider in the Nextcloud admin settings.

#### Required Settings

Add the following to your Nextcloud app config:

```bash
# Set the OpenAI-compatible API endpoint URL
php occ config:app:set assistant streaming_api_url --value="https://api.openai.com/v1/chat/completions"

# Set the API key
php occ config:app:set assistant streaming_api_key --value="sk-..."

# Set the model (optional, defaults to gpt-3.5-turbo)
php occ config:app:set assistant streaming_model --value="gpt-4"
```

#### Supported Providers

Any OpenAI-compatible API endpoint that supports streaming:

**OpenAI**:
```bash
php occ config:app:set assistant streaming_api_url --value="https://api.openai.com/v1/chat/completions"
php occ config:app:set assistant streaming_api_key --value="sk-..."
php occ config:app:set assistant streaming_model --value="gpt-4"
```

**LocalAI** (self-hosted):
```bash
php occ config:app:set assistant streaming_api_url --value="http://localhost:8080/v1/chat/completions"
php occ config:app:set assistant streaming_api_key --value="not-needed"
php occ config:app:set assistant streaming_model --value="gpt-3.5-turbo"
```

**Ollama** (via OpenAI compatibility):
```bash
php occ config:app:set assistant streaming_api_url --value="http://localhost:11434/v1/chat/completions"
php occ config:app:set assistant streaming_api_key --value="not-needed"
php occ config:app:set assistant streaming_model --value="llama2"
```

**Azure OpenAI**:
```bash
php occ config:app:set assistant streaming_api_url --value="https://<your-resource>.openai.azure.com/openai/deployments/<deployment-id>/chat/completions?api-version=2023-05-15"
php occ config:app:set assistant streaming_api_key --value="<your-azure-key>"
php occ config:app:set assistant streaming_model --value="gpt-4"
```

### Checking Configuration

To verify streaming is configured:

```bash
php occ config:app:get assistant streaming_api_url
php occ config:app:get assistant streaming_model
```

## How It Works

### Backend (PHP)

1. **StreamingService** (`lib/Service/StreamingService.php`):
   - Checks if streaming is configured
   - Makes direct HTTP requests to the LLM provider API
   - Parses Server-Sent Events from the provider
   - Yields chunks as they arrive

2. **ChattyLLMController** (`lib/Controller/ChattyLLMController.php`):
   - New `streamGenerate()` endpoint
   - Validates session and user permissions
   - Streams chunks via SSE to the frontend
   - Saves complete message to database when done

3. **Route** (`appinfo/routes.php`):
   - `/chat/stream` endpoint for SSE connection

### Frontend (Vue.js)

1. **ChattyLLMInputForm.vue**:
   - `streamGenerationTask()` method creates EventSource
   - Creates placeholder message in UI
   - Updates message content as chunks arrive
   - Fetches complete message when streaming completes
   - Falls back to polling on error

2. **Fallback Logic**:
   ```javascript
   // Try streaming first
   try {
       await this.streamGenerationTask(sessionId, messageId)
   } catch (error) {
       // Fall back to polling
       await this.pollGenerationTask(taskId, sessionId)
   }
   ```

## API Endpoints

### GET /chat/stream

Stream LLM response in real-time.

**Parameters**:
- `sessionId` (int): Chat session ID
- `messageId` (int): User's message ID

**Response**: Server-Sent Events stream

**Event Format**:
```javascript
// Chunk event
data: {"chunk": "Hello"}

// Completion event
data: {"done": true}

// Error event
data: {"error": "Error message"}
```

**Status Codes**:
- 200: Streaming response
- 401: Not logged in
- 404: Session not found
- 400: Streaming not available/configured

## Troubleshooting

### Streaming not working

1. **Check configuration**:
   ```bash
   php occ config:app:get assistant streaming_api_url
   php occ config:app:get assistant streaming_api_key
   ```

2. **Check logs**:
   ```bash
   tail -f /var/www/nextcloud/data/nextcloud.log | grep -i streaming
   ```

3. **Test provider endpoint manually**:
   ```bash
   curl -X POST https://api.openai.com/v1/chat/completions \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer sk-..." \
     -d '{
       "model": "gpt-3.5-turbo",
       "messages": [{"role": "user", "content": "Hello"}],
       "stream": true
     }'
   ```

### Browser console errors

- **EventSource error**: Check that the streaming endpoint is accessible
- **CORS errors**: Ensure your Nextcloud server allows SSE connections
- **Connection closed**: Check provider API status and rate limits

### Falls back to polling immediately

This is normal if:
- Streaming is not configured
- Provider doesn't support streaming
- Network issues prevent SSE connection

The system is designed to gracefully fall back to polling in these cases.

## Performance Considerations

### Benefits
- **Reduced latency**: No 0-2 second polling delay
- **Lower server load**: One SSE connection vs. polling every 2 seconds
- **Better UX**: Immediate visual feedback

### Network
- SSE keeps connection open during generation
- Bandwidth usage similar to polling (same total data)
- Works through most proxies and load balancers

### Nginx Configuration

If using nginx, you may need to disable buffering for SSE:

```nginx
location /apps/assistant/chat/stream {
    proxy_pass http://nextcloud;
    proxy_buffering off;
    proxy_cache off;
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    chunked_transfer_encoding on;
}
```

## Security

- Uses same authentication as existing chat endpoints
- Session ownership validated before streaming
- API keys stored securely in app config
- No additional attack surface (server→client only)

## Future Enhancements

Potential improvements:
- [ ] Admin UI for streaming configuration
- [ ] Provider auto-detection
- [ ] Support for streaming in regeneration
- [ ] Support for streaming in title generation
- [ ] Streaming progress indicators
- [ ] Token-per-second metrics

## Related Files

- **Backend**:
  - `lib/Service/StreamingService.php` - Streaming service
  - `lib/Controller/ChattyLLMController.php` - Streaming endpoint
  - `appinfo/routes.php` - Route configuration

- **Frontend**:
  - `src/components/ChattyLLM/ChattyLLMInputForm.vue` - EventSource implementation

- **Documentation**:
  - `STREAMING_PROPOSAL.md` - Original proposal
  - `docs/STREAMING.md` - This file

## Contributing

To contribute to the streaming feature:

1. Test with different providers (OpenAI, LocalAI, Ollama, etc.)
2. Report issues with specific error messages and logs
3. Submit PRs with improvements
4. Update documentation for new providers

## License

SPDX-License-Identifier: AGPL-3.0-or-later
