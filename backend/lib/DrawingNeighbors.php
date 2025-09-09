<?php
// backend/lib/DrawingNeighbors.php
namespace Lib;

use InvalidArgumentException;

class DrawingNeighbors
{
	private Database $db;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	/* ---------------------------------------------------------------------
     | Validation / Analysis
     |---------------------------------------------------------------------*/

	/**
	 * Max neighbors allowed for a notebook (section_count - 1).
	 */
	public static function maxAllowed(Database $db, int $notebookId): int
	{
		$sections = (int) Notebook::getSectionCount($db, $notebookId);
		return max(0, $sections - 1);
	}

	/**
	 * Validate neighbor list (throws on errors).
	 * - Enforce max neighbors
	 * - Disallow referencing the primary section
	 * - Validate shapes (section/page)
	 * - Require referenced slots to already exist
	 *
	 * @param array<int,array{section_id:int,page:int}> $neighbors
	 * @throws InvalidArgumentException
	 */
	public function validate(array $neighbors, int $notebookId, int $primarySectionId): void
	{
		$maxNeighbors = self::maxAllowed($this->db, $notebookId);
		if (count($neighbors) > $maxNeighbors) {
			throw new InvalidArgumentException("This notebook allows maximum $maxNeighbors neighbors");
		}

		foreach ($neighbors as $neighbor) {
			if (!isset($neighbor['section_id'], $neighbor['page'])) {
				throw new InvalidArgumentException('Invalid neighbor object');
			}

			$nSection = (int) $neighbor['section_id'];
			$nPage    = (int) $neighbor['page'];

			if ($nSection === $primarySectionId) {
				throw new InvalidArgumentException('Cannot reference own section as neighbor');
			}

			// Shape checks (against this notebook)
			Validation::section($nSection, $notebookId, $this->db);
			Validation::page($nPage, $notebookId, $this->db);

			// Must already exist (so references are real)
			if (!Drawing::isSlotTaken($this->db, $notebookId, $nSection, $nPage)) {
				throw new InvalidArgumentException(
					"Neighbor not found in section {$nSection} page {$nPage}"
				);
			}
		}
	}

	/**
	 * Non-throwing neighbor analysis (used by validation endpoint / admin UI).
	 *
	 * Returns:
	 * [
	 *   'valid'    => bool,
	 *   'warnings' => [{ code, message, section_id?, page? }, ...],
	 *   'accepted' => [{ section_id, page }, ...]
	 * ]
	 */
	public static function analyze(Database $db, int $notebookId, int $primarySectionId, array $neighbors): array
	{
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
			if ($nSection === 0 || $nPage === 0) continue;

			if ($nSection === $primarySectionId) {
				$warnings[] = [
					'code'       => 'neighbor_cannot_be_primary_section',
					'section_id' => $nSection,
					'page'       => $nPage,
					'message'    => 'Neighbor cannot be the primary section.',
				];
				// not fatal
			}

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

			if (!self::exists($db, $notebookId, $nSection, $nPage)) {
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


	public static function exists(Database $db, int $notebookId, int $sectionId, int $page): bool
	{
		// just defers to the canonical slot check
		return Drawing::isSlotTaken($db, $notebookId, $sectionId, $page);
	}



	/**
	 * Filter a raw neighbor list into a set that is safe to persist without throwing.
	 *
	 * Rules:
	 * - Cap to maxAllowed(notebookId)
	 * - Skip neighbors in the primary section
	 * - Validate section/page shapes (non-throwing)
	 * - Require that the neighbor slot already exists
	 * - De-duplicate [section_id,page] pairs
	 *
	 * @param array<int,array{section_id?:mixed,page?:mixed}> $neighbors
	 * @return array<int,array{section_id:int,page:int}>
	 */
	public static function filterSaveable(Database $db, int $notebookId, int $primarySectionId, array $neighbors): array
	{
		$max   = self::maxAllowed($db, $notebookId); // e.g., section_count - 1
		$out   = [];
		$seen  = [];

		foreach ($neighbors as $n) {
			$nSection = isset($n['section_id']) ? (int)$n['section_id'] : 0;
			$nPage    = isset($n['page'])       ? (int)$n['page']       : 0;

			// Skip empty/malformed rows
			if ($nSection <= 0 || $nPage <= 0) {
				continue;
			}

			// Cannot neighbor the primary section
			if ($nSection === $primarySectionId) {
				continue;
			}

			// Shape checks (non-throwing)
			try {
				Validation::section($nSection, $notebookId, $db);
				Validation::page($nPage, $notebookId, $db);
			} catch (InvalidArgumentException $e) {
				continue;
			}

			// Slot must already have a drawing
			if (!self::exists($db, $notebookId, $nSection, $nPage)) { // defers to Drawing::isSlotTaken
				continue;
			}

			// De-dupe
			$key = $nSection . ':' . $nPage;
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;

			$out[] = ['section_id' => $nSection, 'page' => $nPage];

			// Respect cap
			if (count($out) >= $max) {
				break;
			}
		}

		return $out;
	}






	/* ---------------------------------------------------------------------
     | Persistence
     |---------------------------------------------------------------------*/

	/**
	 * Insert neighbors for a drawing id.
	 * @return int number of rows inserted
	 */
	public function insert(int $drawingId, array $neighbors): int
	{
		$count = 0;
		foreach ($neighbors as $neighbor) {
			$ok = $this->db->execute(
				"INSERT INTO drawing_neighbors (drawing_id, neighbor_section_id, neighbor_page)
                 VALUES (?, ?, ?)",
				[$drawingId, (int)$neighbor['section_id'], (int)$neighbor['page']]
			);
			if ($ok !== false) $count++;
		}
		return $count;
	}

	/**
	 * Replace all neighbors for a drawing id.
	 * @return int number of rows inserted
	 */
	public function replace(int $drawingId, array $neighbors): int
	{
		$this->db->execute("DELETE FROM drawing_neighbors WHERE drawing_id = ?", [$drawingId]);
		return $this->insert($drawingId, $neighbors);
	}

	/**
	 * List neighbors for API output.
	 * @return array<int,array{section_id:int,page:int}>
	 */
	public function list(int $drawingId): array
	{
		return $this->db->query(
			"SELECT neighbor_section_id AS section_id, neighbor_page AS page
               FROM drawing_neighbors
              WHERE drawing_id = ?",
			[$drawingId]
		);
	}
}
