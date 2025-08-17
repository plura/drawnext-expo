<?php

// backend/lib/Validation.php

namespace Lib;

use InvalidArgumentException;

class Validation
{
	/**
	 * Validate email format only (no DB).
	 * @param string $email Email address to validate
	 * @return string Validated (trimmed) email
	 * @throws InvalidArgumentException On invalid format
	 */
	public static function email(string $email): string
	{
		$email = trim($email);
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new InvalidArgumentException("Invalid email format");
		}
		return $email;
	}

	/**
	 * Validates that a notebook exists and returns its ID
	 * @param int $notebook_id The notebook ID to validate
	 * @param Database $db Database connection
	 * @return int Valid notebook ID
	 * @throws InvalidArgumentException If notebook doesn't exist
	 */
	public static function notebook(int $notebook_id, Database $db): int
	{
		$id = $db->querySingle(
			"SELECT notebook_id FROM notebooks WHERE notebook_id = ?",
			[$notebook_id]
		)['notebook_id'] ?? 0;

		if (!$id) {
			throw new InvalidArgumentException("Invalid notebook ID: " . $notebook_id);
		}

		return (int)$id;
	}

	/**
	 * Validates that a section exists in the specified notebook and returns its ID
	 * @param int $section_id The section ID to validate
	 * @param int $notebook_id The parent notebook ID
	 * @param Database $db Database connection
	 * @return int Valid section ID
	 * @throws InvalidArgumentException If section doesn't exist or doesn't belong to notebook
	 */
	public static function section(int $section_id, int $notebook_id, Database $db): int
	{
		$id = $db->querySingle(
			"SELECT section_id FROM sections 
             WHERE section_id = ? AND notebook_id = ?",
			[$section_id, $notebook_id]
		)['section_id'] ?? 0;

		if (!$id) {
			throw new InvalidArgumentException(
				"Section $section_id not found in notebook $notebook_id"
			);
		}

		return (int)$id;
	}

	/**
	 * Validate page number is positive and within notebook page limit if set
	 * @param int $page Page number
	 * @param int $notebook_id Notebook ID
	 * @param Database $db Database instance
	 * @return int Validated page number
	 * @throws InvalidArgumentException
	 */
	public static function page(int $page, int $notebook_id, Database $db): int
	{
		if ($page <= 0) {
			throw new InvalidArgumentException("Page number must be greater than zero");
		}

		$notebook = $db->querySingle("SELECT pages FROM notebooks WHERE notebook_id = ?", [$notebook_id]);

		if (!$notebook) {
			throw new InvalidArgumentException("Notebook not found");
		}

		if (!empty($notebook['pages']) && is_numeric($notebook['pages'])) {
			if ($page > (int) $notebook['pages']) {
				throw new InvalidArgumentException("Page number exceeds notebook page limit");
			}
		}

		return $page;
	}
}
