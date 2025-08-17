<?php
// backend/lib/Drawing.php
namespace Lib;

use Lib\FileHandlerException;
use RuntimeException;
use InvalidArgumentException;
use PDOException;

class Drawing {
    private Database $db;
    private ?int $id = null;
    private ?array $fileMeta = null;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Checks if a drawing slot is already occupied
     */
    public static function isSlotTaken(
        Database $db,
        int $notebookId,
        int $sectionId,
        int $page
    ): bool {
        return (bool)$db->querySingle(
            "SELECT 1 FROM drawings 
             WHERE notebook_id = ? AND section_id = ? AND page = ? 
             LIMIT 1",
            [$notebookId, $sectionId, $page]
        );
    }

    /**
     * Creates a new drawing with optional file upload and neighbors
     */
    public function create(
        int $userId,
        int $notebookId,
        int $sectionId,
        int $page,
        ?array $uploadedFile,
        array $neighbors = [],
        bool $isTest = false
    ): void {
        $this->validateNeighbors($neighbors, $notebookId, $sectionId);
        
        $this->db->beginTransaction();
        try {
            $this->createDrawingRecord($userId, $notebookId, $sectionId, $page);
            
            if ($uploadedFile) {
                $this->processFileUpload($uploadedFile, $isTest);
            }
            
            $this->saveNeighbors($neighbors);
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
     * Formats the drawing data for API responses
     */
    public function toApiResponse(): array {
        return [
            'drawing_id' => $this->id,
            'preview_url' => $this->fileMeta 
                ? '/uploads/' . rawurlencode($this->fileMeta['stored_filename'])
                : null,
            'neighbors' => $this->getNeighbors()
        ];
    }

    // ===== PRIVATE METHODS ===== //

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
            if ($neighbor['section_id'] == $currentSectionId) {
                throw new InvalidArgumentException(
                    "Cannot reference own section as neighbor"
                );
            }
            Validation::section($neighbor['section_id'], $notebookId, $this->db);
            Validation::page($neighbor['page'], $notebookId, $this->db);
        }
    }

    private function createDrawingRecord(
        int $userId,
        int $notebookId,
        int $sectionId,
        int $page
    ): void {
        $this->db->execute(
            "INSERT INTO drawings 
             (user_id, notebook_id, section_id, page, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [$userId, $notebookId, $sectionId, $page]
        );
        $this->id = $this->db->lastInsertId();
    }

    private function processFileUpload(array $uploadedFile, bool $isTest): void {
        $this->fileMeta = FileHandler::processUpload($uploadedFile, $isTest);
        
        $this->db->execute(
            "INSERT INTO files 
             (drawing_id, stored_filename, original_filename, 
              filesize, mime_type, width, height, test) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->id,
                $this->fileMeta['stored_filename'],
                $this->fileMeta['original_filename'],
                $this->fileMeta['filesize'],
                $this->fileMeta['mime_type'],
                $this->fileMeta['width'],
                $this->fileMeta['height'],
                (int)$this->fileMeta['is_test_copy']
            ]
        );
    }

    private function saveNeighbors(array $neighbors): void {
        foreach ($neighbors as $neighbor) {
            $this->db->execute(
                "INSERT INTO drawing_neighbors 
                 (drawing_id, neighbor_section_id, neighbor_page) 
                 VALUES (?, ?, ?)",
                [$this->id, $neighbor['section_id'], $neighbor['page']]
            );
        }
    }

    private function getNeighbors(): array {
        return $this->db->query(
            "SELECT neighbor_section_id as section_id, neighbor_page as page
             FROM drawing_neighbors
             WHERE drawing_id = ?",
            [$this->id]
        );
    }

    private function handleRollback(): void {
        $this->db->rollBack();
        if ($this->fileMeta) {
            FileHandler::cleanup($this->fileMeta);
        }
    }
}