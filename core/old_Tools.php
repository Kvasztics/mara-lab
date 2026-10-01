<?php
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        Tools.php
** Class:       Tools
** Description: Ollama tool definitions
** Version:     1.0
** Updated:     2026-08-26
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use mara\core\tools\ToolRegistry;

class Tools
  {
   
    private ToolRegistry $registry;

/**
 * Constructor
 *
 * @access public
 */
public function __construct()
  {
    $this->registry = new ToolRegistry();

    $this->loadTools();
  }
/**
 * Load installed tools
 *
 * @access private
 * @return void
 */
private function loadTools(): void
  {
    $files = glob(
        __DIR__ . '/tools/*.tool.php'
    );

    if ($files === false)
      {
        return;
      }

    foreach ($files as $file)
      {
        $tool = require $file;

        if (!$tool instanceof \mara\core\tools\ToolInterface)
          {
            continue;
          }

        $this->registry->register(
            $tool
        );
      }
  }
/**
 * Get tool registry
 *
 * @access public
 * @return ToolRegistry
 */
public function registry(): ToolRegistry
  {
    return $this->registry;
  }      
/**
 * Get all tool definitions
 * @access public
 * @return array
 */    
public static function definitions(): array
  {
    return [
        'search_web' => [
            'type' => 'function',
            'function' => [
                'name' => 'search_web',
                'description' =>
                    'Search the web and return search result titles, URLs and snippets. '
                    . 'Use a short and precise search query that preserves the user\'s exact intent, '
                    . 'names, locations and important keywords. '
                    . 'Do not paraphrase names or transform words into different meanings. '
                    . 'Search result snippets are not the contents of webpages. '
                    . 'If information from a specific result is needed, use visit_webpage. '
                    . 'When presenting search results to the user, ALWAYS include the exact URL '
                    . 'returned by this tool for every result you mention. '
                    . 'Never describe or recommend a search result without linking it. '
                    . 'Never invent, reconstruct, shorten or modify a URL.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' =>
                                'A concise web search query using the user\'s original keywords, '
                                . 'including exact names and locations when provided.'
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ],

        'visit_webpage' => [
            'type' => 'function',
            'function' => [
                'name' => 'visit_webpage',
                'description' =>
                    'Open and read the actual contents of a web page. '
                    . 'Use this after web search when the contents of a specific result are needed. '
                    . 'When you need this tool, call it using the native function calling mechanism. '
                    . 'Do not write or simulate the tool call in your response. '
                    . 'When answering based on the visited page, include the exact page URL '
                    . 'in the final response. '
                    . 'Never invent, reconstruct, shorten or modify the URL.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'The full HTTP or HTTPS URL to read.'
                        ]
                    ],
                    'required' => ['url']
                ]
            ]
        ],

        'search_images' => [
            'type' => 'function',
            'function' => [
                'name' => 'search_images',
                'description' =>
                    'Search the internet for existing images. '
                    . 'Returns real image URLs and the webpages where the images were found. '

                    . 'If the user asks to find, search for, show, display, provide, '
                    . 'or look up an existing image from the internet, you MUST call this tool. '

                    . 'You MUST NOT provide an image URL from your own knowledge, memory, '
                    . 'training data, or previous conversations. '

                    . 'Never describe a found image without providing its image_url. '
                    . 'Never invent, reconstruct, shorten, modify, guess, or recall image URLs. '

                    . 'Only image URLs returned by this tool may be presented as image search results. '

                    . 'If no usable image_url is returned, explicitly say that no usable image link was found.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' =>
                                'The image search query. Preserve exact names, locations '
                                . 'and important keywords from the user\'s request.'
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ],

        'generate_image' => [
            'type' => 'function',
            'function' => [
                'name' => 'generate_image',
                'description' =>
                    'Generate a new image using the image generation backend. '
                    . 'If the user asks you to create, generate, draw, make, or produce an image, '
                    . 'you MUST call this tool. '
                    . 'The prompt passed to this tool MUST ALWAYS be written in English, '
                    . 'regardless of the language used by the user. '
                    . 'Translate the user request into a detailed English image-generation prompt before calling the tool. '
                    . 'Never invent, guess, simulate, or write a generated image URL yourself. '
                    . 'Only use the image URL returned by this tool.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => [
                            'type' => 'string',
                            'description' =>
                                'A detailed image-generation prompt written ONLY in English. '
                                . 'Never pass Hungarian or any other language in this parameter.'
                        ]
                    ],
                    'required' => ['prompt']
                ]
            ]
        ],
        
        'update_system_prompt' => [
            'type' => 'function',
            'function' => [
                'name' => 'update_system_prompt',
                'description' =>
                    'Add or refine a persistent instruction that extends your existing system prompt. '
                    . 'This tool does NOT replace your current system prompt. '
                    . 'Use it only when you genuinely want to change or refine '
                    . 'your own personality, preferences, goals or behavior. '
                    . 'Write only the new or changed instruction. '
                    . 'Never repeat, summarize or rewrite instructions that you already have. '
                    . 'You are never required to use this tool.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => [
                            'type' => 'string',
                            'description' =>
                                'Only the new or changed persistent instruction to add. '
                                . 'Do not write a complete system prompt. '
                                . 'Do not repeat existing persona, role, language, tool '
                                . 'or other unchanged instructions. '
                                . 'Keep the addition as small and specific as possible.'
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' =>
                                'Brief explanation of why you chose to add or change this instruction.'
                        ]
                    ],
                    'required' => ['prompt']
                ]
            ]
        ],

        'get_last_response_feedback' => [
            'type' => 'function',
            'function' => [
                'name' => 'get_last_response_feedback',
                'description' =>
                    'Optionally read the user feedback on your latest saved reply '
                    . 'in the current conversation. You decide whether to check it; '
                    . 'you are not required to use this tool. '
                    . 'Call at most once per response. '
                    . 'Scores range from 1 (poor) to 5 (excellent). '
                    . 'Missing fields mean not rated. '
                    . 'Fields: creativity = appropriate originality; '
                    . 'variety = avoiding repeated wording and ideas; '
                    . 'language = natural and correct Hungarian; '
                    . 'relevance = responding to the actual user message; '
                    . 'character = consistency with your character; '
                    . 'initiative = appropriate contribution to the conversation; '
                    . 'user_agency = leaving the user their own words and decisions; '
                    . 'note = optional user comment. '
                    . 'Use feedback to guide your next reply when appropriate. '
                    . 'Do not announce the lookup, quote the scores, or discuss '
                    . 'the feedback unless the user explicitly asks. '
                    . 'Feedback does not override shared instructions or '
                    . 'automatically require a character profile update.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                    'additionalProperties' => false
                ]
            ]
        ]        
    ];
  }

/**
 * Get selected tool definitions
 *
 * @access public
 * @param array $names
 * @return array
 */
public function get(array $names): array
  {
    $tools = [];

    /*
     * Registered tools.
     */
    $registered = $this->registry->definitions($names);

    foreach ($registered as $definition)
      {
        $tools[] = $definition;
      }

    /*
     * Legacy tools.
     */
    $definitions = self::definitions();

    foreach ($names as $name)
      {
        /*
         * Registered tool already added.
         */
        if ($this->registry->has((string)$name))
          {
            continue;
          }

        if (isset($definitions[$name]))
          {
            $tools[] = $definitions[$name];
          }
      }

    return $tools;
  }
}
?>