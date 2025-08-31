<?php
// backend/lib/Drawing.php
namespace Lib;

use Lib\FileHandlerException;
use RuntimeException;
use InvalidArgumentException;
use PDOException;

/**
 * Drawing domain model
 *
 * Responsibilities:
 * - Create/update a drawing row (and its file + neighbors) transactionally
 * - Validate neighbor references (shapes, limit, and existence)
 * - Provide reusable helpers for slot validation and neighbor analysis
 */
class Drawing
{
    private Database $db;
    private ?int $id = null;         // set on create()
    private ?array $fileMeta = null; // set if image is processed/attached

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /* ---------------------------------------------------------------------
     | Public API
     |---------------------------------------------------------------------*/

    /**
     * Create a new drawing with optional uploaded file and neighbors.
     * Transactional; rolls back on any failure.
     *
     * @param int         $userId
     * @param int         $notebookId
     * @param int         $sectionId
     * @param int         $page
     * @param array|null  $uploadedFile   $_FILES['drawing']-style array (legacy single-shot)
     * @param array       $neighbors      [{section_id:int, page:int}, ...]
     * @param bool        $isTest
     * @param string|null $uploadToken    Token from /api/images/temp (two-phase flow)
     *
     * @throws InvalidArgumentException on invalid shapes / neighbor rules
     * @throws RuntimeException         on file/db failures (user-safe message)
     */
    public function create(
        int $userId,
        int $notebookId,
        int $sectionId,
        int $page,
        ?array $uploadedFile,
        array $neighbors = [],
        bool $isTest = false,
        ?string $uploadToken = null
    ): void {
        // Validate neighbor references BEFORE any DB writes
        $this->validateNeighbors($neighbors, $notebookId, $sectionId);

        $this->db->beginTransaction();
        try {
            $this->createDrawingRecord($userId, $notebookId, $sectionId, $page);

            if ($uploadedFile) {
                $this->processFileUpload($uploadedFile, $isTest);
            } elseif (!empty($uploadToken)) {
                $meta = FileHandler::finalizeFromToken($uploadToken);
                $this->insertFileRow($meta);
                $this->fileMeta = $meta;
            }

            $this->saveNeighbors($this->id, $neighbors);

            $this->db->commit();
        } catch (FileHandlerException $e) {
            $this->handleRollback();
            throw new RuntimeException("File processing failed: " . $e->getMessage());
        } catch (PDOException $e) {
            $this->handleRollback();
            throw new RuntimeException("Database operation failed");
        }
    }

    /**
     * Update base fields + neighbors for an existing drawing.
     * Transactional; rolls back on any failure.
     *
     * @param int   $drawingId
     * @param int   $notebookId
     * @param int   $sectionId
     * @param int   $page
     * @param array $neighbors  [{section_id:int, page:int}, ...]
     *
     * @throws InvalidArgumentException on invalid shapes / neighbor rules
     * @throws \Throwable               bubbles up DB errors
     */
    public function update(
        int $drawingId,
        int $notebookId,
        int $sectionId,
        int $page,
        array $neighbors = []
    ): void {
        // Validate neighbor references BEFORE any DB writes
        $this->validateNeighbors($neighbors, $notebookId, $sectionId);

        try {
            $this->db->beginTransaction();

            // Update base fields (also sets updated_at = NOW())
            $this->updateDrawingRecord($drawingId, $notebookId, $sectionId, $page);

            // Replace neighbors atomically
            $this->replaceNeighbors($drawingId, $neighbors);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Minimal API representation after creation.
     *
     * @return array{
     *   drawing_id:int|null,
     *   preview_url:?string,
     *   neighbors:array<int,array{section_id:int,page:int}>
     * }
     */
    public function toApiResponse(): array
    {
        return [
            'drawing_id'  => $this->id,
            'preview_url' => $this->fileMeta
                ? Config::get('uploads.directory') . '/' . rawurlencode($this->fileMeta['stored_filename'])
                : null,
            'neighbors'   => $this->getNeighbors(),
        ];
    }

    /* ---------------------------------------------------------------------
     | Static helpers (shared by endpoints)
     |---------------------------------------------------------------------*/

    /**
     * Check if a (notebook_id, section_id, page) slot is already taken.
     *
     * @param Database $db
     * @param int      $notebookId
     * @param int      $sectionId
     * @param int      $page
     * @param int|null $excludeDrawingId  When updating, ignore this drawing's own row
     * @return bool
     */
    public static function isSlotTaken(
        Database $db,
        int $notebookId,
        int $sectionId,
        int $page,
        ?int $excludeDrawingId = null
    ): bool {
        if ($excludeDrawingId) {
            return (bool)$db->querySingle(
                "SELECT 1 FROM drawings
                 WHERE notebook_id = ? AND section_id = ? AND page = ?
                   AND drawing_id <> ?
                 LIMIT 1",
                [$notebookId, $sectionId, $page, $excludeDrawingId]
            );
        }

        return (bool)$db->querySingle(
            "SELECT 1 FROM drawings
             WHERE notebook_id = ? AND section_id = ? AND page = ?
             LIMIT 1",
            [$notebookId, $sectionId, $page]
        );
    }

    /**
     * Validate the primary slot (notebook/section/page).
     * - Normalizes & validates shapes (Validation::notebook/section/page)
     * - Enforces uniqueness (optionally excluding a drawing id)
     * - Throws RuntimeException(code=409) on conflict
     *
     * @return array{0:int,1:int,2:int} [notebookId, sectionId, page]
     *
     * @throws InvalidArgumentException on invalid shapes
     * @throws RuntimeException         on conflict (code 409)
     */
    public static function validateSlotOrThrow(
        Database $db,
        int $notebookId,
        int $sectionId,
        int $page,
        ?int $excludeDrawingId = null
    ): array {
        $notebookId = Validation::notebook($notebookId, $db);
        $sectionId  = Validation::section($sectionId, $notebookId, $db);
        $page       = Validation::page($page, $notebookId, $db);

        if (self::isSlotTaken($db, $notebookId, $sectionId, $page, $excludeDrawingId)) {
            throw new RuntimeException('Drawing slot already taken', 409);
        }
        return [$notebookId, $sectionId, $page];
    }

    /**
     * Non-throwing analysis of neighbors, returning structured feedback.
     *
     * Returns:
     *  [
     *    'valid'    => bool,          // false if any invalid or not_found
     *    'warnings' => array<array>,  // [{ code, message, section_id?, page? }, ...]
     *    'accepted' => array<array>,  // neighbors that passed shape validation
     *  ]
     *
     * @param Database $db
     * @param int      $notebookId
     * @param int      $primarySectionId
     * @param array    $neighbors
     * @return array{valid:bool,warnings:array,accepted:array}
     */
    public static function analyzeNeighbors(
        Database $db,
        int $notebookId,
        int $primarySectionId,
        array $neighbors
    ): array {
        $warnings = [];
        $accepted = [];
        $valid    = true;

        $sectionCount = (int) Notebook::getSectionCount($db, $notebookId);
        $maxNeighbors = max(0, $sectionCount - 1);

        if (count($neighbors) > $maxNeighbors) {
            $warnings[] = [
                'code'    => 'too_many_neighbors',
                'message' => "Only {$maxNeighbors} neighbor(s) allowed for this notebook.",
            ];
            $neighbors = array_slice($neighbors, 0, $maxNeighbors);
        }

        foreach ($neighbors as $n) {
            $nSection = isset($n['section_id']) ? (int)$n['section_id'] : 0;
            $nPage    = isset($n['page'])       ? (int)$n['page']       : 0;

            // Skip completely empty rows
            if ($nSection === 0 || $nPage === 0) {
                continue;
            }

            if ($nSection === $primarySectionId) {
                $warnings[] = [
                    'code'       => 'neighbor_cannot_be_primary_section',
                    'section_id' => $nSection,
                    'page'       => $nPage,
                    'message'    => 'Neighbor cannot be the primary section.',
                ];
                // not fatal; continue
            }

            // Validate shapes (non-throwing in analysis)
            try {
                Validation::section($nSection, $notebookId, $db);
                Validation::page($nPage, $notebookId, $db);
            } catch (InvalidArgumentException $e) {
                $valid = false;
                $warnings[] = [
                    'code'       => 'invalid_neighbor',
                    'section_id' => $nSection,
                    'page'       => $nPage,
                    'message'    => $e->getMessage(),
                ];
                continue;
            }

            $accepted[] = ['section_id' => $nSection, 'page' => $nPage];

            // Must already exist
            if (!self::isSlotTaken($db, $notebookId, $nSection, $nPage)) {
                $valid = false;
                $warnings[] = [
                    'code'       => 'neighbor_not_found',
                    'section_id' => $nSection,
                    'page'       => $nPage,
                    'message'    => 'No drawing found at that neighbor slot.',
                ];
            }
        }

        return [
            'valid'    => $valid,
            'warnings' => $warnings,
            'accepted' => $accepted,
        ];
    }

    /* ---------------------------------------------------------------------
     | Private helpers
     |---------------------------------------------------------------------*/

    /**
     * Validate neighbor list:
     * - Enforce max neighbors (section_count - 1)
     * - Disallow referencing the primary section
     * - Validate neighbor section/page shapes
     * - Require neighbor slots to already exist (so references are real)
     *
     * @param array<int,array{section_id:int,page:int}> $neighbors
     * @param int $notebookId
     * @param int $currentSectionId
     *
     * @throws InvalidArgumentException
     */
    private function validateNeighbors(
        array $neighbors,
        int $notebookId,
        int $currentSectionId
    ): void {
        $maxNeighbors = Notebook::getSectionCount($this->db, $notebookId) - 1;
        if (count($neighbors) > $maxNeighbors) {
            throw new InvalidArgumentException(
                "This notebook allows maximum $maxNeighbors neighbors"
            );
        }

        foreach ($neighbors as $neighbor) {
            // If you want to be stricter on payload shape, uncomment:
            // if (!isset($neighbor['section_id'], $neighbor['page'])) {
            //     throw new InvalidArgumentException('Invalid neighbor object');
            // }

            if (($neighbor['section_id'] ?? null) == $currentSectionId) {
                throw new InvalidArgumentException("Cannot reference own section as neighbor");
            }

            // Validate section + page shapes against this notebook
            Validation::section((int)$neighbor['section_id'], $notebookId, $this->db);
            Validation::page((int)$neighbor['page'], $notebookId, $this->db);

            // Require neighbor slot to already have a drawing
            $exists = self::isSlotTaken(
                $this->db,
                $notebookId,
                (int)$neighbor['section_id'],
                (int)$neighbor['page']
            );
            if (!$exists) {
                throw new InvalidArgumentException(
                    "Neighbor not found in section {$neighbor['section_id']} page {$neighbor['page']}"
                );
            }
        }
    }

    /**
     * Insert the main drawing row (sets authored_at & updated_at to NOW()).
     */
    private function createDrawingRecord(
        int $userId,
        int $notebookId,
        int $sectionId,
        int $page
    ): void {
        $this->db->execute(
            "INSERT INTO drawings 
             (notebook_id, section_id, page, user_id, authored_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())",
            [$notebookId, $sectionId, $page, $userId]
        );
        $this->id = $this->db->lastInsertId();
    }

    /**
     * Update base fields (and updated_at = NOW()) for a drawing.
     */
    private function updateDrawingRecord(
        int $drawingId,
        int $notebookId,
        int $sectionId,
        int $page
    ): void {
        $this->db->execute(
            "UPDATE drawings
               SET notebook_id = ?, section_id = ?, page = ?, updated_at = NOW()
             WHERE drawing_id = ?",
            [$notebookId, $sectionId, $page, $drawingId]
        );
    }

    /**
     * Process an uploaded file via FileHandler and store file metadata.
     */
    private function processFileUpload(array $uploadedFile, bool $isTest): void
    {
        $this->fileMeta = FileHandler::processUpload($uploadedFile, $isTest);
        $this->insertFileRow($this->fileMeta);
    }

    /**
     * Persist file metadata row linked to this drawing.
     */
    private function insertFileRow(array $meta): void
    {
        $this->db->execute(
            "INSERT INTO files 
             (drawing_id, stored_filename, original_filename, 
              filesize, mime_type, width, height, test) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->id,
                $meta['stored_filename'],
                $meta['original_filename'],
                $meta['filesize'],
                $meta['mime_type'],
                $meta['width'],
                $meta['height'],
                (int)($meta['is_test_copy'] ?? 0),
            ]
        );
    }

    /**
     * Insert neighbor references for a drawing id.
     *
     * @param int   $drawingId
     * @param array<int,array{section_id:int,page:int}> $neighbors
     */
    private function saveNeighbors(int $drawingId, array $neighbors): void
    {
        foreach ($neighbors as $neighbor) {
            $this->db->execute(
                "INSERT INTO drawing_neighbors 
                 (drawing_id, neighbor_section_id, neighbor_page) 
                 VALUES (?, ?, ?)",
                [$drawingId, (int)$neighbor['section_id'], (int)$neighbor['page']]
            );
        }
    }

    /**
     * Replace all neighbor references for a drawing id.
     *
     * @param int   $drawingId
     * @param array<int,array{section_id:int,page:int}> $neighbors
     */
    private function replaceNeighbors(int $drawingId, array $neighbors): void
    {
        $this->db->execute(
            "DELETE FROM drawing_neighbors WHERE drawing_id = ?",
            [$drawingId]
        );
        $this->saveNeighbors($drawingId, $neighbors);
    }

    /**
     * Fetch neighbor list for API response (uses $this->id).
     *
     * @return array<int,array{section_id:int,page:int}>
     */
    private function getNeighbors(): array
    {
        return $this->db->query(
            "SELECT neighbor_section_id AS section_id, neighbor_page AS page
               FROM drawing_neighbors
              WHERE drawing_id = ?",
            [$this->id]
        );
        // (Note: safe for create()->toApiResponse(); not used by update().)
    }

    /**
     * Roll back DB transaction and clean up any processed files.
     */
    private function handleRollback(): void
    {
        $this->db->rollBack();
        if ($this->fileMeta) {
            FileHandler::cleanup($this->fileMeta);
        }
    }
}
