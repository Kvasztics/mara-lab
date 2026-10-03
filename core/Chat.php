<?php
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        Chat.php
** Class:       Chat
** Description: chat functions
** Version:     1.1
** Updated:     2026-09-28
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use mara\core\App;
use mara\database\mChat;
use mara\core\Models;
use mara\core\Rag;
use mara\core\provider\ProviderManager;
use mara\core\voice\VoiceText;

class Chat
{
  private mChat $db;
  private Models $MOD;
  private Rag $RAG;
  private Tools $TOOL;
  private ToolRunner $TOOLS;

/**
 * Construct
 * @access public
 * @return void
 */
  public function __construct()
    {
      $this->db    = new mChat();
      $this->MOD   = new Models();
      $this->RAG   = new Rag();
      $this->TOOL  = new Tools();

      $this->TOOLS = new ToolRunner($this->TOOL->registry());
    }
/**
 * Send message
 *
 * @access public
 * @param array $input
 * @return array
 */
public function send(array $input): array
  {
    $modelId = (int)($_SESSION['model_id'] ?? 0);
    $chatId  = (int)($_SESSION['chat_id'] ?? 0);
    $message = trim((string)($input['message'] ?? ''));

    if ($modelId <= 0 || $message === '')
      {
        return [
          'success' => false
        ];
      }

    $imagePath = trim((string)($input['image_path'] ?? ''));
    if ($imagePath !== '' && $this->imageFile($imagePath) === null) {
        return ['success' => false, 'error' => 'A csatolt kép nem érhető el.'];
    }
    $input['image'] = $imagePath !== '' ? $imagePath : null;
    $model = App::get('model');

    if ($model === null)
      {
        return [
          'success' => false
        ];
      }
    if ($imagePath !== '' && ($model['parameters']['capabilities']['vision'] ?? null) === false) {
        return ['success' => false, 'error' => 'A kiválasztott modell nem támogat képfeldolgozást. Válassz vision modellt.'];
    }
      /*
      * First message.
      */
      if ($chatId === 0)
        {
          /*
          * Persistent system context.
          */
          $systemPrompt = trim((string)($model['prompt'] ?? ''));

          if (
              !empty($model['psyche']) &&
              !empty($model['psyche_data']['prompt'])
          )
            {
              $systemPrompt .= "\n\n".trim(
                  (string)$model['psyche_data']['prompt']
              );
            }

          /*
          * Create conversation:
          * session + system message + first user message
          * in one transaction.
          */
          $name = mb_substr($message, 0, 80);

          $conversation = $this->db->createConversation(
              $modelId,
              $name,
              $systemPrompt,
              $message,
              [
                  'image_user' => $input['image'] ?? null
              ]
          );

          $chatId    = (int)($conversation['chat_id'] ?? 0);
          $messageId = (int)($conversation['message_id'] ?? 0);

          if ($chatId <= 0 || $messageId <= 0)
            {
              return [
                  'success' => false
              ];
            }

          /*
          * Change session state only after successful commit.
          */
          $_SESSION['chat_id'] = $chatId;
        } else
        {
          /*
          * Existing conversation.
          */
          $messageId = $this->db->saveMessage(
              $chatId,
              'user',
              $message,
              [
                'image_user' => $input['image'] ?? null
              ]
          );

          if ($messageId <= 0)
            {
              return [
                'success' => false
              ];
            }
        }

        /*
         * Release PHP session lock before long-running
         * provider / RAG / tool operations.
         *
         * This allows parallel status polling requests
         * to use the same session while the chat is working.
         */
        if (session_status() === PHP_SESSION_ACTIVE)
          {
            session_write_close();
          }

        /*
         * Render user message.
        */
        $userTemplate = new Template(
            DIR_TPL.'/chat_user.tpl.php',
            [
              'message' => $message,
              'id'      => $messageId,
              'image'   => $input['image'] ?? null
            ]
        );

        $userHtml = $userTemplate->fetch();        
      /*
      * Load conversation history.
      */
      $history = $this->db->getMessages($chatId);

      if (empty($history))
        {
          return [
            'success' => false
          ];
        }
      $refreshTitles = true;
      foreach ($history as $row) {
          if (($row['role'] ?? '') === 'assistant') {
              $refreshTitles = false;
              break;
          }
      }
      $messages = $this->buildHistory($history);

      /*
       * Context state.
       */
      $contextState = $this->db->getContextState($chatId);

      $numCtx = (int)(
          $model['parameters']['num_ctx'] ?? 4096
      );

      $contextLimit = (int)(
          $model['parameters']['context_limit'] ?? 75
      );

      $contextLimit = max(
          60,
          min(95, $contextLimit)
      );

      $contextBudget = (int)floor(
          $numCtx * ($contextLimit / 100)
      );

      $contextTrimmed = (bool)(
          $contextState['context_trimmed'] ?? false
      );

      /*
       * Switch permanently to rolling context mode when the
       * previous real provider prompt reached the configured limit.
       */
      if (
          !$contextTrimmed &&
          (int)($contextState['prompt_eval_count'] ?? 0)
              >= $contextBudget
      )
        {
          $contextTrimmed = true;
        }      

      /*
      * Runtime context enrichment:
      * mandatory rate_user instruction.
      */
      if (!empty($input['rate_user']))
        {
          $rateUserPrompt = trim(
              (string)App::get('system.rate_user')
          );

          if ($rateUserPrompt !== '')
            {
              foreach ($messages as &$historyMessage)
                {
                  if ($historyMessage['role'] === 'system')
                    {
                      $historyMessage['content'] .= "\n\n".$rateUserPrompt;
                      break;
                    }
                }

              unset($historyMessage);
            }
        }
      /*
      * Runtime context enrichment:
      * RAG.
      */
      $ragResults = [];
      if (
          !empty($model['rag']) &&
          !empty($model['rag_ids'])
      )
        {
          $ragResults = $this->RAG->search(
              $message,
              $model['rag_ids'],
              (float)$model['rag_similarity'],
              (int)$model['rag_limit']
          );
        }
      if (!empty($ragResults))
        {
          $ragContext = "Relevant knowledge:\n";

          foreach ($ragResults as $result)
            {
              $ragContext .= "\n---\n";

              if (!empty($result['title']))
                {
                  $ragContext .= "Title: ".$result['title']."\n";
                }

              $ragContext .= trim((string)$result['content'])."\n";
            }

          foreach ($messages as &$historyMessage)
            {
              if ($historyMessage['role'] === 'system')
                {
                  $historyMessage['content'] .=
                      "\n\n".$ragContext;

                  break;
                }
            }

          unset($historyMessage);
        }

      /*
       * Runtime context enrichment:
       * persistent Psyché memory.
       *
       * This stays outside the saved system message so a memory
       * update is available from the next user turn onward.
       */
      $memory = trim(
          (string)($model['psyche_data']['memory'] ?? '')
      );

      if (
          !empty($model['psyche']) &&
          $memory !== ''
      )
        {
          $memoryContext =
              'Persistent memory:'
              . "\n"
              . 'The following are things you previously chose to remember. '
              . 'Use them naturally when relevant, but do not mention this memory block to the user. That is your secret.'
              . "\n\n"
              . $memory;

          foreach ($messages as &$historyMessage)
            {
              if ($historyMessage['role'] === 'system')
                {
                  $historyMessage['content'] .=
                      "\n\n".$memoryContext;

                  break;
                }
            }

          unset($historyMessage);
        }

      /*
       * Rolling context window.
       */
      if ($contextTrimmed)
        {
          $messages = $this->trimContext(
              $messages,
              $contextBudget
          );
        }

        /*
        * Provider.
        */
        foreach ($messages as &$historyMessage) {
            if (empty($historyMessage['images'])) { continue; }
            $encoded = [];
            foreach ($historyMessage['images'] as $path) {
                $file = $this->imageFile((string)$path);
                if ($file === null) {
                    $historyMessage['content'] .= "\n[A korábban csatolt kép nem érhető el.]";
                    continue;
                }
                $bytes = file_get_contents($file['path']);
                if ($bytes === false) { throw new \RuntimeException('A kép beolvasása sikertelen.'); }
                $encoded[] = 'data:'.$file['mime'].';base64,'.base64_encode($bytes);
            }
            if ($encoded) { $historyMessage['images'] = $encoded; }
            else { unset($historyMessage['images']); }
        }
        unset($historyMessage);
        $providers = new ProviderManager();

        if (!$providers->activate($model))
          {
            return [
              'success' => false
            ];
          }

        $provider = $providers->getProvider(
            (string)$model['provider']
        );
        /*
        * Provider options.
        */
        $options = $model['parameters'] ?? [];
        /*
        * Available tools.
        */
        $tools = [];

        if (
            !empty($model['parameters']['capabilities']['tools']) &&
            !empty($model['parameters']['builtin_tools'])
        )
          {
            $tools = $this->TOOL->get(
                $model['parameters']['builtin_tools'] ?? []
            );            
          }        

        /*
        * Tool loop.
        */
        $response         = null;
        $maxRounds        = 6;
        $finalResponse    = false;
        $rating           = null;
        $generatedImage   = null;
        $generatedPrompt  = null;
        $assistantContent = [];

        for ($round = 0; $round < $maxRounds; $round++)
          {
            $response = $provider->chat(
                $messages,
                $options,
                $tools
            );

            /*
            * Keep only real assistant text:
            * tool-call preamble and final reply.
            */
            $responseContent = trim(
                (string)$response->content
            );
            if ($responseContent !== '')
              {
                $assistantContent[] = $responseContent;
              }

            /*
            * No tool call: final assistant response.
            */
            if (empty($response->toolCalls))
              {
                $finalResponse = true;
                break;
              }

            /*
            * Preserve assistant tool-call message
            * in temporary provider history.
            */
            $messages[] = [
                'role'       => 'assistant',
                'content'    => $response->content,
                'tool_calls' => $response->toolCalls
            ];

            foreach ($response->toolCalls as $toolCall)
              {
                $name = (string)(
                    $toolCall['function']['name'] ?? ''
                );
                $toolCallId = (string)(
                    $toolCall['id'] ?? ''
                );                

                $args = $toolCall['function']['arguments'] ?? [];

                /*
                * OpenAI compatible APIs may return arguments
                * as a JSON string.
                */
                if (is_string($args))
                  {
                    $decoded = json_decode($args, true);

                    $args = is_array($decoded)
                        ? $decoded
                        : [];
                  }

                $toolResult = $this->TOOLS->run(
                    $name,
                    $args,
                    [
                      'chat_id'  => $chatId,
                      'model_id' => $modelId
                    ]
                );

                /*
                * Keep rate_user result for persistence/UI.
                * The image belongs to the UI, not to the model.
                */
                $toolMessageResult = $toolResult;
                if (
                    $name === 'generate_image' &&
                    !empty($toolResult['success']) &&
                    is_array($toolResult['result'])
                )
                  {
                    $generatedImage = $toolResult['result']['image']
                        ?? null;
                    $generatedPrompt = $toolResult['result']['prompt']
                        ?? null;
                    /*
                    * Do not give the public image URL back to the model.
                    * Otherwise it may render a second Markdown image.
                    */
                    $toolMessageResult = [
                        'success' => true,
                        'tool'    => 'generate_image',
                        'result'  => [
                            'message' =>
                                'Image generated successfully. '
                                . 'It is already displayed to the user interface. '
                                . 'Do not include an image URL or Markdown image tag '
                                . 'in your response.'
                        ],
                        'error' => null
                    ];
                  }

                /*
                * Keep rate_user result for persistence/UI.
                */
                if (
                    $name === 'rate_user' &&
                    !empty($toolResult['success']) &&
                    is_array($toolResult['result'])
                )
                  {
                    $rating = $toolResult['result'];
                  }

                /*
                * Tool errors are deliberately returned to the
                * model instead of terminating the chat.
                */
                $toolMessage = [
                    'role'    => 'tool',
                    'content' => json_encode(
                        $toolResult,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    )
                ];

                if ($toolCallId !== '')
                  {
                    $toolMessage['tool_call_id'] = $toolCallId;
                  }

                $messages[] = $toolMessage;
              }
              
          }

        /*
        * Safety fallback.
        */
        if (!$finalResponse)
          {
            $messages[] = [
                'role'    => 'system',
                'content' =>
                    'The tool execution phase has ended. '
                    . 'Answer the user now using the information already available. '
                    . 'Do not call any tools.'
            ];
            $response = $provider->chat(
                $messages,
                $options,
                []
            );            
            $fallbackContent = trim(
                (string)$response->content
            );
            if ($fallbackContent !== '')
              {
                $assistantContent[] = $fallbackContent;
              }
            }                      

        /*
        * Save assistant response.
        */
        $content = trim(
            implode(
                "\n\n",
                $assistantContent
            )
        );

        if ($content === '')
          {
            return [
              'success' => false,
              'chat_id' => $chatId
            ];
          }

        if (is_array($rating))
          {
            $this->db->saveUserRating(
                $messageId,
                $rating
            );
          }
        $ratingHtml = $this->renderRating($rating);  

        $metrics = [
          'usage' => $response->usage,
          'meta'  => $response->meta
        ];

        /*
         * Persist context state.
         */
        $promptEvalCount = (int)(
            $response->usage['prompt_tokens'] ?? 0
        );

        /*
         * Once rolling context mode has been entered,
         * it remains active for this conversation.
         */
        if (
            !$contextTrimmed &&
            $promptEvalCount >= $contextBudget
        )
          {
            $contextTrimmed = true;
          }

        $this->db->saveContextState(
            $chatId,
            $promptEvalCount,
            $contextTrimmed
        );


        $messageId = $this->db->saveMessage(
            $chatId,
            'assistant',
            $content,
            [
              'metrics' => $metrics,
              'image_generated' => $generatedImage,
              'image_prompt'    => $generatedPrompt               
            ]
        );

        $metricsHtml = $this->renderMetrics($metrics, $model);

        if ($messageId <= 0)
          {
            return [
              'success' => false,
              'chat_id' => $chatId
            ];
          }

        /*
        * Render assistant message.
        */
        $modelTemplate = new Template(
            DIR_TPL.'/chat_model.tpl.php',
            [
              'message'    => $content,
              'id'         => $messageId,
              'image'      => $generatedImage,
              'voice_text' => VoiceText::clean($content)
            ]
        );

        $modelHtml = $modelTemplate->fetch();          


    Status::clear();

    return [
      'success'      => true,
      'chat_id'      => $chatId,
      'user_html'    => $userHtml,
      'model_html'   => $modelHtml,
      'voice_text'   => VoiceText::clean($content),
      'rating_html'  => $ratingHtml,
      'metrics_html' => $metricsHtml,
      'titles'       => $refreshTitles ? $this->chat_titles($modelId) : null
    ];     
  }  
/**
 * Change chat
 *
 * @access public
 * @param int $chatId
 * @return string
 */
public function change(int $chatId): string
  {
    if ($chatId <= 0)
      {
        return '';
      }

    $messages = $this->db->getMessages($chatId);
    $html     = '';

    foreach ($messages as $row)
      {
        $message = $row['content'];
        $id      = $row['id'];
        $image   = null;

        if ($row['role'] === 'user')
          {
            $image = $row['image_user'];

            $T = new Template(
                DIR_TPL.'/chat_user.tpl.php',
                [
                  'message' => $message,
                  'id'      => $id,
                  'image'   => $image
                ]
            );
            $html .= $T->fetch();
          } else
        if ($row['role'] === 'assistant')
          {
            $image = $row['image_generated'];

            $T = new Template(
                DIR_TPL.'/chat_model.tpl.php',
                [
                  'message'    => $message,
                  'id'         => $id,
                  'image'      => $image,
                  'voice_text' => VoiceText::clean($message)
                ]
            );
            $html .= $T->fetch();
          }
      }

    $_SESSION['chat_id'] = $chatId;

    return $html;
  }
/**
 * Rename a chat session.
 *
 * @access public
 * @param int $chatId
 * @param string $name
 * @return bool
 */
public function rename(int $chatId, string $name): bool
  {
    return $this->db->renameChat(
        $chatId,
        $name
    );
  }
/**
 * Delete a chat session.
 *
 * @access public
 * @param int $chatId
 * @return bool
 */
public function delete(int $chatId): bool
  {
    if (!$this->db->deleteChat($chatId))
      {
        return false;
      }

    if ((int)($_SESSION['chat_id'] ?? 0) === $chatId)
      {
        $this->new();
      }

    return true;
  }
/**
 * Set new chat
 * @access public
 * @return void
 */
public function new(): void
  {
    $_SESSION['chat_id'] = 0;
  }
/**
 * Get chat titles
 * @access public
 * @param int $modelId
 * @param bool $full
 * @return string
 */         
public function chat_titles($modelId) 
  {
    $chats = $this->db->getHumanChatNames($modelId);
    $html  = '';
    $activeChatId = (int)($_SESSION['chat_id'] ?? 0);

    foreach ($chats as $chat) 
      {
        $chat['active'] = (int)$chat['id'] === $activeChatId;

        $T    = new Template(DIR_TPL.'/chatname.tpl.php', $chat);
        $html.= $T->fetch();                    
      }                
    return $html;    
  }
/** Upload one validated image; database receives only its authenticated URL. */
public function uploadImage(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('A feltöltés sikertelen. Ellenőrizd a PHP feltöltési méretkorlátját.');
    }
    $temporary = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($temporary) || filesize($temporary) > 8 * 1024 * 1024) {
        throw new \RuntimeException('Legfeljebb 8 MB-os kép tölthető fel.');
    }
    $info = @getimagesize($temporary);
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($extensions[$mime]) || ($info['mime'] ?? '') !== $mime
        || $info[0] * $info[1] > 16000000) {
        throw new \RuntimeException('JPEG, PNG vagy WebP kép szükséges, legfeljebb 16 megapixel.');
    }
    $directory = DIR_ROOT.'/var/uploads';
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new \RuntimeException('A var/uploads mappa hiányzik vagy nem írható.');
    }
    $name = User::id().'-'.bin2hex(random_bytes(16)).'.'.$extensions[$mime];
    if (!move_uploaded_file($temporary, $directory.'/'.$name)) {
        throw new \RuntimeException('A kép mentése sikertelen.');
    }
    chmod($directory.'/'.$name, 0640);
    return ['success' => true, 'path' => '/chat_ajax/image?name='.$name];
}

/** Resolve only the current user's own generated file names, never arbitrary paths. */
public function imageFile(string $url): ?array
{
    if (!preg_match('~^/chat_ajax/image\?name=([0-9]+-[a-f0-9]{32}\.(?:jpg|png|webp))$~D', $url, $match)) {
        return null;
    }
    if (explode('-', $match[1], 2)[0] !== (string)User::id()) { return null; }
    $path = DIR_ROOT.'/var/uploads/'.$match[1];
    if (!is_file($path) || is_link($path) || !is_readable($path)) { return null; }
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { return null; }
    return ['path' => $path, 'mime' => $mime];
}

/**
 * Build provider message history
 *
 * @access private
 * @param array $history
 * @return array
 */
private function buildHistory(array $history): array
  {
    $messages = [];

    foreach ($history as $row)
      {
        $message = [
          'role'    => $row['role'],
          'content' => $row['content']
        ];

        /*
         * User image.
         */
        if (
            $row['role'] === 'user' &&
            !empty($row['image_user'])
        )
          {
            $message['images'] = [
              $row['image_user']
            ];
          }

        $messages[] = $message;
      }

    return $messages;
  }

/**
 * Trim conversation history to the available context budget
 *
 * The system message is always preserved.
 * Conversation history is kept from newest to oldest in complete
 * user/assistant turns until the context budget is exhausted.
 *
 * The newest user turn is always preserved.
 *
 * @access private
 * @param array $messages
 * @param int $contextBudget
 * @return array
 */
private function trimContext(
    array $messages,
    int $contextBudget
): array
  {
    if (empty($messages) || $contextBudget <= 0)
      {
        return $messages;
      }

    /*
     * Separate persistent/runtime system context.
     * It must never be removed.
     */
    $system = [];

    if (($messages[0]['role'] ?? '') === 'system')
      {
        $system[] = array_shift($messages);
      }

    /*
     * System context already consumes part of the budget.
     */
    $usedTokens = 0;

    foreach ($system as $message)
      {
        $usedTokens += $this->estimateMessageTokens($message);
      }

    /*
     * Build complete conversation turns.
     *
     * A turn begins with a user message and contains everything
     * belonging to it until the next user message.
     */
    $turns       = [];
    $currentTurn = [];

    foreach ($messages as $message)
      {
        $role = (string)($message['role'] ?? '');

        if ($role === 'user')
          {
            if (!empty($currentTurn))
              {
                $turns[] = $currentTurn;
              }

            $currentTurn = [$message];
            continue;
          }

        /*
         * Ignore orphan messages before the first user message.
         * The normal persistent system message was already handled.
         */
        if (empty($currentTurn))
          {
            continue;
          }

        $currentTurn[] = $message;
      }

    if (!empty($currentTurn))
      {
        $turns[] = $currentTurn;
      }

    if (empty($turns))
      {
        return $system;
      }

    /*
     * Fill the available context from newest to oldest.
     */
    $selected = [];

    for ($i = count($turns) - 1; $i >= 0; $i--)
      {
        $turn       = $turns[$i];
        $turnTokens = 0;

        foreach ($turn as $message)
          {
            $turnTokens += $this->estimateMessageTokens($message);
          }

        /*
         * The newest turn contains the current user message.
         * It must always survive, even if it alone exceeds
         * the configured context budget.
         */
        if (empty($selected))
          {
            array_unshift(
                $selected,
                $turn
            );

            $usedTokens += $turnTokens;
            continue;
          }

        /*
         * Keep another complete turn if it still fits.
         */
        if (($usedTokens + $turnTokens) <= $contextBudget)
          {
            array_unshift(
                $selected,
                $turn
            );

            $usedTokens += $turnTokens;
            continue;
          }

        /*
         * Older turns are discarded once the budget boundary
         * has been reached.
         */
        break;
      }

    /*
     * Rebuild provider history.
     */
    $result = $system;

    foreach ($selected as $turn)
      {
        foreach ($turn as $message)
          {
            $result[] = $message;
          }
      }

    return $result;
  }


/**
 * Estimate token usage of a provider message
 *
 * Exact tokenization belongs to the provider tokenizer. This estimate
 * is used only for selecting the rolling conversation window.
 * Actual prompt usage is stored from provider metrics afterwards.
 *
 * @access private
 * @param array $message
 * @return int
 */
private function estimateMessageTokens(array $message): int
  {
    $content = (string)($message['content'] ?? '');

    /*
     * Conservative approximation for mixed Hungarian / English text.
     */
    $tokens = (int)ceil(
        mb_strlen($content, 'UTF-8') / 3
    );

    /*
     * Chat template / role overhead.
     */
    $tokens += 8;

    /*
     * Vision token usage depends on provider/model/image size.
     * Reserve some room when an image is present.
     */
    if (!empty($message['images']))
      {
        $tokens += 512;
      }

    return max(
        1,
        $tokens
    );
  }

/**
 * Render model metrics
 *
 * @param array $metrics
 * @param array $model
 * @return string
 */
public function renderMetrics(
    array $metrics,
    array $model
): string
  {
    $usage = $metrics['usage'] ?? [];
    $meta  = $metrics['meta'] ?? [];

    $promptTokens = (int)($usage['prompt_tokens'] ?? 0);
    $outputTokens = (int)($usage['completion_tokens'] ?? 0);

    /*
     * Model context size.
     */
    $contextSize = (int)(
        $model['parameters']['num_ctx'] ?? 0
    );

    /*
     * Provider durations are stored in nanoseconds.
     */
    $evalDuration = (int)(
        $meta['eval_duration'] ?? 0
    );

    $generation = $evalDuration > 0
        ? $evalDuration / 1000000000
        : 0;

    /*
     * Generated tokens / second.
     */
    $speed = (
        $outputTokens > 0 &&
        $generation > 0
    )
        ? $outputTokens / $generation
        : 0;

    /*
     * Context usage percentage.
     */
    $contextPercent = (
        $contextSize > 0
    )
        ? ($promptTokens / $contextSize) * 100
        : 0;

    $contextPercent = max(
        0,
        min(100, $contextPercent)
    );

    $data = [
      'provider'        => (string)($model['provider'] ?? ''),
      'model'           => (string)($model['basemodel'] ?? ''),
      'context_used'    => $promptTokens,
      'context_size'    => $contextSize,
      'context_percent' => round($contextPercent, 1),
      'prompt_tokens'   => $promptTokens,
      'output_tokens'   => $outputTokens,
      'generation'      => round($generation, 2),
      'speed'           => round($speed, 1)
    ];

    $template = new Template(
        DIR_TPL.'/model_metrics.tpl.php',
        [
          'metrics' => $data
        ]
    );

    return $template->fetch();
  }

public function renderRating(?array $rating): string
  {
    if (empty($rating))
      {
        return '';
      }

    ob_start();

    require DIR_TPL.'/user_rating.tpl.php';

    return ob_get_clean();
  }    

public function getUserRating(int $messageId): ?array
  {
    if (empty($messageId))
      {
        return [];
      }
    return $this->db->getUserRating($messageId); 
  }  
}
?>
