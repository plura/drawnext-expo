<?php

// backend/lib/Validation.php

class Validation {

	/**
	 * Validate email format and check existence in database
	 * @param string $email Email address to validate
	 * @param Database $db Database instance
	 * @return int Validated user ID
	 * @throws InvalidArgumentException On validation failure
	 */
	public static function email(string $email, Database $db): int {
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new InvalidArgumentException("Invalid email format");
		}

		$user = $db->querySingle("SELECT user_id FROM users WHERE email = ?", [$email]);

		if (!$user) {
			throw new InvalidArgumentException("Email not registered");
		}

		return (int) $user['user_id'];
	}

	/**
	 * Validate notebook ID exists and return notebook data
	 * @param int $notebook_id Notebook ID
	 * @param Database $db Database instance
	 * @return array Notebook data
	 * @throws InvalidArgumentException
	 */
	public static function notebook(int $notebook_id, Database $db): array {
		$notebook = $db->querySingle("SELECT * FROM notebooks WHERE notebook_id = ?", [$notebook_id]);

		if (!$notebook) {
			throw new InvalidArgumentException("Invalid notebook ID");
		}

		return $notebook;
	}

	/**
	 * Validate section ID exists and belongs to notebook, return section data
	 * @param int $section_id Section ID
	 * @param int $notebook_id Notebook ID
	 * @param Database $db Database instance
	 * @return array Section data
	 * @throws InvalidArgumentException
	 */
	public static function section(int $section_id, int $notebook_id, Database $db): array {
		$section = $db->querySingle(
			"SELECT * FROM sections WHERE section_id = ? AND notebook_id = ?",
			[$section_id, $notebook_id]
		);

		if (!$section) {
			throw new InvalidArgumentException("Invalid section for the selected notebook");
		}

		return $section;
	}

	/**
	 * Validate page number is positive and within notebook page limit if set
	 * @param int $page Page number
	 * @param int $notebook_id Notebook ID
	 * @param Database $db Database instance
	 * @return int Validated page number
	 * @throws InvalidArgumentException
	 */
	public static function page(int $page, int $notebook_id, Database $db): int {
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
