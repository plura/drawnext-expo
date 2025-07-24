<?php

// backend/lib/Config.php

class Config {
	protected array $settings = [];

	public function __construct(private Database $db) {
		$this->load();
	}

	protected function load(): void {
		$rows = $this->db->query("SELECT `key`, `value` FROM config");
		foreach ($rows as $row) {
			$this->settings[$row['key']] = $row['value'];
		}
	}

	public function get(string $key, mixed $default = null): mixed {
		return $this->settings[$key] ?? $default;
	}
}

