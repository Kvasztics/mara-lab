<?php
declare(strict_types=1);
namespace mara\core;
/*------------------------------------------------------------------------------
** File:        Rag.class.php
** Class:       Rag
** Description: Manage knowledge data
** Version:     1.1
** Updated:     2026-09-27
** Author:      Soós Tamás
** Homepage:    www.digiwood.hu
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 - 2026 SOOS TAMAS
**------------------------------------------------------------------------------*/
use mara\database\mRag;
use mara\core\Template;
use mara\core\User;
use mara\core\Embedding;

final class Rag
{
    private mRag $db;
/**
 * Construct
 * @access public
 * @return void
 */
public function __construct()
  {
    $this->db = new mRag();
  }
public function embeddingConfig(): array
  {
    $settings = (new Settings())->values('system');
    return [
        'provider' => strtolower(trim((string)($settings['embedding_provider'] ?? 'ollama'))),
        'model' => trim((string)($settings['embedding_model'] ?? '')),
        'port' => (int)($settings['embedding_port'] ?? 8083),
    ];
  }

public function configureEmbedding(string $provider, string $model, int $port): int
  {
    $provider = trim($provider);
    $model = trim($model);
    if (!in_array($provider, ['ollama', 'llamacpp'], true) || $model === '' ||
        strlen($model) > 255 || ($provider === 'llamacpp' && ($port < 1 || $port > 65535))) {
        throw new EmbeddingException(LANG['RAG_EMBEDDING_CONFIG_ERROR']);
    }

    return $this->db->withEmbeddingIndex(function () use ($provider, $model, $port): int {
        $previous = $this->embeddingConfig();
        $config = [
            'provider' => $provider,
            'model' => $model,
            'port' => $provider === 'llamacpp' ? $port : $previous['port'],
        ];
        if ($config === $previous) {
            return 0;
        }

        $embedding = new Embedding($config);
        $dimensions = count($embedding->embed('Mara RAG'));
        $this->db->begin();

        try {
            $count = 0;
            foreach ($this->db->chunksForReindex() as $chunk) {
                $vector = $embedding->embed($chunk['content']);
                if (count($vector) !== $dimensions) {
                    throw new EmbeddingException(LANG['RAG_EMBEDDING_DIMENSIONS']);
                }
                $this->db->updateChunkEmbedding((int)$chunk['id'], $vector);
                ++$count;
            }

            $this->db->saveEmbeddingConfig($config);
            $this->db->commit();
            return $count;
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    });
  }

/**
 * Get all RAG documents for user
 * @return array
 */
public function getDocuments(): array
  {
    if (User::isAdmin()) 
      {return $this->db->getDocuments();}
    return $this->db->getDocuments(User::id());
  }
/**
 * Get model data by userId
 * @access public
 * @return string
 */
public function getList(): ?string
  {
    $html = '';
    $rags = $this->getDocuments();
    foreach ($rags as $rag) 
      {
        if ($rag['user_id'] == 0) {$rag['info'] = LANG['KNOW_PUBLIC'];} else {$rag['info'] = LANG['KNOW_PERSONAL'];}
        $rag['content'] = substr($rag['content'], 0, 300).' ...';
        $T = new Template(DIR_TPL.'/rag_subpage.tpl.php', $rag);
        $html.= $T->fetch();
      }
    return $html;
  }  
/**
 * Delete rag data
 *  * @access public
 * @return bool
 */  
public function delete(int $ragId): bool
  {
    if ($ragId <= 0)
      {
        return false;
      }
    $userId = User::isAdmin() ? null : User::id();
    return $this->db->withEmbeddingIndex(fn(): bool => $this->db->deleteDocument($ragId, $userId));
  }
/**
 * Add knowledge item
 *
 * @param string $title
 * @param string $content
 * @param int $userId
 * @param string|null $source
 * @return int
 */
public function add(
    string $title,
    string $content,
    int $userId,
    ?string $source = null
): int
  {
    $title   = trim($title);
    $content = trim($content);

    if ($title === '')
      {
        throw new \RuntimeException(
            'RAG title is empty.'
        );
      }

    $chunks = $this->chunkText(
        $content,
        1200
    );

    if (empty($chunks))
      {
        throw new \RuntimeException(
            'RAG content is empty.'
        );
      }

    return $this->db->withEmbeddingIndex(function () use ($userId, $title, $source, $chunks): int {
      $embeddingService = new Embedding($this->embeddingConfig());
      $this->db->begin();

      try
      {
        $documentId = $this->db->createDocument(
            $userId,
            $title,
            $source
        );

        foreach ($chunks as $chunkIndex => $chunkContent)
          {
            $embedding = $embeddingService->embed($chunkContent);

            $this->db->saveChunk(
                $documentId,
                $chunkIndex,
                $chunkContent,
                $embedding
            );
          }

        $this->db->commit();

        return $documentId;
      }
      catch (\Throwable $e)
      {
        $this->db->rollback();

        throw $e;
      }
    });
  }
/** 
 * Split text into overlapping chunks on sentence boundaries.
 *
 * - Never cuts a sentence in half.
 * - No sentence is skipped.
 * - The last sentence of the previous chunk is repeated
 *   at the beginning of the next chunk as overlap.
 */
private function chunkText(
    string $text,
    int $maxLength = 1200
): array
{
    $text = trim($text);

    if ($text === '')
    {
        return [];
    }

    if (mb_strlen($text) <= $maxLength)
    {
        return [$text];
    }

    // Split only after sentence-ending punctuation.
    $sentences = preg_split(
        '/(?<=[.!?])\s+/u',
        $text,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    if (empty($sentences))
    {
        return [$text];
    }

    $sentences = array_values(
        array_filter(
            array_map('trim', $sentences),
            fn($sentence) => $sentence !== ''
        )
    );

    $chunks = [];
    $currentSentences = [];

    foreach ($sentences as $sentence)
    {
        $candidateSentences = $currentSentences;
        $candidateSentences[] = $sentence;

        $candidate = implode(' ', $candidateSentences);

        // Still fits: keep building this chunk.
        if (
            empty($currentSentences)
            || mb_strlen($candidate) <= $maxLength
        )
        {
            $currentSentences[] = $sentence;
            continue;
        }

        // Save completed chunk.
        $chunks[] = implode(
            ' ',
            $currentSentences
        );

        /*
         * Overlap:
         * repeat the LAST COMPLETE SENTENCE
         * of the previous chunk.
         */
        $lastSentence = end($currentSentences);

        $currentSentences = [
            $lastSentence,
            $sentence
        ];

        /*
         * Exceptional case:
         * if two unusually long sentences together already exceed
         * maxLength, keep only the new sentence.
         *
         * We prefer exceeding maxLength for one long sentence
         * rather than cutting that sentence in half.
         */
        if (
            mb_strlen(
                implode(' ', $currentSentences)
            ) > $maxLength
        )
        {
            $currentSentences = [$sentence];
        }
    }

    if (!empty($currentSentences))
    {
        $chunks[] = implode(
            ' ',
            $currentSentences
        );
    }

    return $chunks;
}
/**
 * Search relevant RAG chunks
 *
 * @param string $query
 * @param array $ragIds
 * @param float $similarity
 * @param int $limit
 * @return array
 */
public function search(
    string $query,
    array $ragIds,
    float $similarity = 0.5,
    int $limit = 5
): array
  {
    $query = trim($query);

    if ($query === '' || empty($ragIds) || $limit <= 0)
      {
        return [];
      }

    [$config, $chunks] = $this->db->withEmbeddingIndex(fn(): array => [
        $this->embeddingConfig(),
        $this->db->getChunks(User::id(), $ragIds),
    ]);
    if ($chunks === []) {
        return [];
    }

    $embedding = new Embedding($config);
    $queryEmbedding = $embedding->embed($query);

    $results = [];

    /*
     * Calculate similarity
     */
    foreach ($chunks as $chunk)
      {
        if (count($chunk['embedding']) !== count($queryEmbedding)) {
            throw new EmbeddingException(LANG['RAG_EMBEDDING_DIMENSIONS']);
        }

        $score = $embedding->similarity(
            $queryEmbedding,
            $chunk['embedding']
        );

        if ($score < $similarity)
          {
            continue;
          }

        $chunk['similarity'] = $score;
        $results[] = $chunk;
      }

    /*
     * Best matches first
     */
    usort(
        $results,
        static fn(array $a, array $b): int =>
            $b['similarity'] <=> $a['similarity']
    );

    return array_slice(
        $results,
        0,
        $limit
    );
  }
}
?>  