<?php
// backend/lib/Drawing.php
namespace Lib;

use Lib\Config;
use Lib\Database;
use Lib\DrawingNeighbors;
use Lib\FileHandlerException;
use RuntimeException;
use PDOException;

class Drawing
{
	private Database $db;
	private ?int $id = null;
	private ?array $fileMeta = null;

	private ?array $neighborsInfo = null; // ['policy'=>string, 'attempted'=>int, 'saved'=>int]

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	/* ---------------------------------------------------------------------
	 | Public API
	 |---------------------------------------------------------------------*/

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
		$neighborsSvc = new DrawingNeighbors($this->db);

		// Read the effective policy (Config::get now guarantees a valid typed value)
		$neighbors_policy = Config::get('submissions.neighbors.policy'); // 'strict'|'permissive'|'ignore'

		// Decide which neighbors we will actually save
		$neighborsToSave = [];
		switch ($neighbors_policy) {
			case 'ignore':
				// Skip any neighbor checks/saves entirely
				$neighborsToSave = [];
				break;

			case 'permissive':
				// Filter to a set that's safe to persist (no throws)
				// - skips primary section / invalid shapes / non-existent slots / duplicates
				// - caps to max allowed neighbors
				$neighborsToSave = DrawingNeighbors::filterSaveable(
					$this->db,
					$notebookId,
					$sectionId,
					$neighbors
				);
				break;

			case 'strict':
			default:
				// Existing behavior — validate or throw before doing anything
				$neighborsSvc->validate($neighbors, $notebookId, $sectionId);
				$neighborsToSave = $neighbors;
				break;
		}

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

			// Insert neighbors per policy (empty array is fine)
			$inserted = $neighborsSvc->insert($this->id, $neighborsToSave);

			// Stash meta for toApiResponse()
			$this->neighborsInfo = [
				'policy'    => $neighbors_policy,   // 'strict' | 'permissive' | 'ignore'
				'attempted' => count($neighbors),   // how many the client sent
				'saved'     => (int)$inserted,      // how many rows actually inserted
			];

			$this->db->commit();
		} catch (FileHandlerException $e) {
			$this->handleRollback();
			throw new RuntimeException("File processing failed: " . $e->getMessage());
		} catch (\PDOException $e) {
			$this->handleRollback();
			throw new RuntimeException("Database operation failed");
		}
	}


	public function update(
		int $drawingId,
		int $notebookId,
		int $sectionId,
		int $page,
		array $neighbors = []
	): void {
		// Validate neighbors before the TX
		$neighborsSvc = new DrawingNeighbors($this->db);
		$neighborsSvc->validate($neighbors, $notebookId, $sectionId);

		try {
			$this->db->beginTransaction();

			$this->updateDrawingRecord($drawingId, $notebookId, $sectionId, $page);
			$neighborsSvc->replace($drawingId, $neighbors);

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function toApiResponse(): array
	{
		$neighborsSvc = new DrawingNeighbors($this->db);

		$resp = [
			'drawing_id'  => $this->id,
			'preview_url' => $this->fileMeta
				? Config::get('uploads.directory') . '/' . rawurlencode($this->fileMeta['stored_filename'])
				: null,
			'neighbors'   => $neighborsSvc->list($this->id),
		];

		if ($this->neighborsInfo) {
			$attempted = (int)$this->neighborsInfo['attempted'];
			$saved     = (int)$this->neighborsInfo['saved'];
			$resp['neighbors_meta'] = [
				'policy'   => (string)$this->neighborsInfo['policy'],
				'attempted' => $attempted,
				'saved'    => $saved,
				'dropped'  => max(0, $attempted - $saved),
			];
		}

		return $resp;
	}

	/* ---------------------------------------------------------------------
	 | Static helpers (shared by endpoints)
	 |---------------------------------------------------------------------*/

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
	 * Backward-compat wrapper so existing callers keep working.
	 */
	public static function analyzeNeighbors(
		Database $db,
		int $notebookId,
		int $primarySectionId,
		array $neighbors
	): array {
		return DrawingNeighbors::analyze($db, $notebookId, $primarySectionId, $neighbors);
	}

	/* ---------------------------------------------------------------------
	 | Private helpers
	 |---------------------------------------------------------------------*/

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

	private function processFileUpload(array $uploadedFile, bool $isTest): void
	{
		$this->fileMeta = FileHandler::processUpload($uploadedFile, $isTest);
		$this->insertFileRow($this->fileMeta);
	}

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

	private function handleRollback(): void
	{
		$this->db->rollBack();
		if ($this->fileMeta) {
			FileHandler::cleanup($this->fileMeta);
		}
	}
}
