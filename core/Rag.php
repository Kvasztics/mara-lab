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
    private Embedding $embedding;
/**
 * Construct
 * @access public
 * @return void
 */
public function __construct()
  {
    $this->db = new mRag();
    $this->embedding = new Embedding();
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
    if (User::isAdmin())
      {
        return $this->db->deleteDocument($ragId);
      }
    return $this->db->deleteDocument(
        $ragId,
        User::id()
    );
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
            $embedding = $this->embedding->embed(
                $chunkContent
            );

            if (empty($embedding))
              {
                throw new \RuntimeException(
                    'Embedding generation failed for chunk '
                    .$chunkIndex.'.'
                );
              }

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

    /*
     * Create embedding for user query
     */
    $queryEmbedding = $this->embedding->embed($query);

    if (empty($queryEmbedding))
      {
        return [];
      }

    /*
     * Load selected RAG chunks
     */
    $chunks = $this->db->getChunks(
        User::id(),
        $ragIds
    );

    $results = [];

    /*
     * Calculate similarity
     */
    foreach ($chunks as $chunk)
      {
        $score = $this->embedding->similarity(
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