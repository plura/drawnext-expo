#!/usr/bin/env php
<?php
/**
 * plura-codebase-concat-tree.php
 *
 * STRUCTURE MAP
 * ----------------------------------------------------------------------
 * 1) CLI Entry & Router
 * 2) Mode: CONCAT
 * 3) Mode: TREE
 * 4) Shared Defaults & Help Text
 * 5) Shared Helpers (DRY)
 *
 * ----------------------------------------------------------------------
 * DDEV USAGE (examples)
 * ----------------------------------------------------------------------
 * CONCAT (single dir):
 *   ddev php plura-codebase-concat-tree.php --mode=concat --source ./backend --output ./export/backend.txt
 *
 * CONCAT (multiple jobs):
 *   ddev php plura-codebase-concat-tree.php --mode=concat \
 *     --job "backend=>export/backend.txt" \
 *     --job "frontend/src=>export/frontend.txt;exclude-paths=features/admin,features/submit"
 *
 * CONCAT (explicit files + chunking):
 *   ddev php plura-codebase-concat-tree.php --mode=concat \
 *     --job "vite.config.js,tailwind.config.js=>export/configs.txt;split-bytes=60000"
 *
 * TREE (single source):
 *   ddev php plura-codebase-concat-tree.php --mode=tree --source ./frontend/src --output ./export/frontend-tree.txt
 *
 * TREE (batch JSON):
 *   ddev php plura-codebase-concat-tree.php --mode=tree --batch ./plura-codebase-tree.json
 *
 * TREE (dirs only, depth 3, follow symlinks):
 *   ddev php plura-codebase-concat-tree.php --mode=tree \
 *     --source ./backend --output ./export/backend-dirs.txt \
 *     --dirs-only=true --max-depth=3 --follow-symlinks=true
 */

ini_set('memory_limit', '-1');

/* =======================================================================
 * (4) Shared Defaults & Help Text
 * ======================================================================= */

$DEFAULT_EXTS = [
	'js','jsx','ts','tsx','php','css','scss','sass','html','htm','md','markdown',
	'json','sql','env','sh','bash','zsh','bat','ps1','xml','yml','yaml','txt','csv',
	'ini','conf','config','toml','lock','mjs','cjs','eslintrc','prettierrc','tsconfig','editorconfig'
];
$DEFAULT_EXCLUDES = [
	'node_modules','vendor','.git','dist','build','.ddev','.idea','.vscode','.next','.turbo','.cache','.pnpm-store'
];

function print_global_help(): void {
	echo "plura-codebase-concat-tree.php — unified tools for concatenation and directory trees\n\n";
	echo "Modes:\n";
	echo "  --mode=concat   Concatenate files into text output (with chunking)\n";
	echo "  --mode=tree     Print an ASCII directory tree to a text file\n\n";
	echo "Use --help together with --mode for details:\n";
	echo "  ddev php plura-codebase-concat-tree.php --mode=concat --help\n";
	echo "  ddev php plura-codebase-concat-tree.php --mode=tree --help\n";
}

function print_concat_help(string $self = 'plura-codebase-concat-tree.php'): void {
	echo "Concatenate Files (mode=concat)\n\n";
	echo "Single job:\n";
	echo "  ddev php {$self} --mode=concat --source ./backend --output ./export/backend.txt [options]\n\n";
	echo "Multi-job (--job repeated):\n";
	echo "  ddev php {$self} --mode=concat \\\n";
	echo "    --job \"backend=>export/backend.txt\" \\\n";
	echo "    --job \"frontend/src=>export/frontend.txt;exclude-paths=features/admin,features/submit\" \\\n";
	echo "    --job \"frontend/src/App.jsx,frontend/src/main.jsx=>export/entry.txt;split-bytes=60000\" [options]\n\n";
	echo "Batch JSON:\n";
	echo "  ddev php {$self} --mode=concat --batch ./concat-jobs.json [options]\n\n";
	echo "Global options:\n";
	echo "  --ext              Comma-separated file extensions to include (no dots)\n";
	echo "  --exclude          Comma-separated DIR NAMES to exclude (match anywhere)\n";
	echo "  --exclude-paths    Comma-separated PATHS relative to a DIR source (e.g., features/admin)\n";
	echo "  --follow-symlinks  Follow symlinks (default: false)\n";
	echo "  --max-bytes        Skip files larger than N bytes (default: 2000000)\n";
	echo "  --allow-binary     Include files that look binary (default: false)\n";
	echo "  --split-bytes      Max bytes per output chunk. 0 = no splitting. (default: 0)\n\n";
	echo "Per-job overrides (append to --job after ';'):\n";
	echo "  ;ext=js,php               ;exclude=foo,bar\n";
	echo "  ;exclude-paths=a/b,c/d    ;max-bytes=500000\n";
	echo "  ;allow-binary=true        ;follow-symlinks=true\n";
	echo "  ;split-bytes=1000000\n";
}

function print_tree_help(string $self = 'plura-codebase-concat-tree.php'): void {
	echo "Directory Tree (mode=tree)\n\n";
	echo "Basic:\n";
	echo "  ddev php {$self} --mode=tree --source ./frontend/src --output ./export/frontend-tree.txt\n\n";
	echo "Batch JSON:\n";
	echo "  ddev php {$self} --mode=tree --batch ./structure-files-tree.json\n\n";
	echo "Options:\n";
	echo "  --ext               Comma-separated file extensions to include (no dots). Default: common set\n";
	echo "  --all-files         true/false. If true, ignore extension filtering. Default: false\n";
	echo "  --exclude           Comma-separated DIR NAMES to exclude (match anywhere) — additive to defaults\n";
	echo "  --exclude-paths     Comma-separated PATHS (relative to source) to exclude (prefix match) — additive\n";
	echo "  --follow-symlinks   true/false. Follow directory symlinks. Default: false\n";
	echo "  --dirs-only         true/false. List only directories. Default: false\n";
	echo "  --max-depth         Integer >= 1. Limit tree depth. Default: 0 (no limit)\n";
	echo "Notes:\n";
	echo "  - 'exclude' adds directory names to built-in defaults (e.g., node_modules, vendor).\n";
	echo "  - 'exclude_paths' are relative to each source; can target files OR folders (prefix match).\n";
}

/* =======================================================================
 * (1) CLI Entry & Router
 * ======================================================================= */

$argvAll = $_SERVER['argv'] ?? [];
$mode = null;
$self = basename($argvAll[0] ?? 'plura-codebase-concat-tree.php');
$wantHelp = in_array('--help', $argvAll, true) || in_array('-h', $argvAll, true);

foreach ($argvAll as $arg) {
	if (strpos($arg, '--mode=') === 0) {
		$mode = substr($arg, 7);
		break;
	}
}

if ($mode === null) {
	if ($wantHelp) {
		print_global_help();
		exit(0);
	}
	fwrite(STDERR, "Error: Please specify --mode=concat or --mode=tree (use --help for help).\n");
	exit(1);
}

if ($mode === 'concat') {
	if ($wantHelp) { print_concat_help($self); exit(0); }
	exit(run_concat_mode($DEFAULT_EXTS, $DEFAULT_EXCLUDES));
}
if ($mode === 'tree') {
	if ($wantHelp) { print_tree_help($self); exit(0); }
	exit(run_tree_mode($DEFAULT_EXTS, $DEFAULT_EXCLUDES));
}

fwrite(STDERR, "Error: Unknown mode '{$mode}'. Use --mode=concat or --mode=tree.\n");
exit(1);

/* =======================================================================
 * (2) Mode: CONCAT
 * ======================================================================= */

function run_concat_mode(array $DEFAULT_EXTS, array $DEFAULT_EXCLUDES): int {
	$args = getopt('', [
		'source:',
		'output:',
		'job::',
		'batch:',
		'ext::',
		'exclude::',
		'exclude-paths::',
		'follow-symlinks::',
		'max-bytes::',
		'allow-binary::',
		'split-bytes::',
	], $optind);

	$global = [
		'ext' => isset($args['ext']) ? parse_csv($args['ext']) : $DEFAULT_EXTS,
		'exclude' => isset($args['exclude']) ? parse_csv_raw($args['exclude']) : $DEFAULT_EXCLUDES,
		'exclude_paths' => isset($args['exclude-paths']) ? normalize_paths_array(parse_csv_raw($args['exclude-paths'])) : [],
		'follow_symlinks' => filter_bool($args['follow-symlinks'] ?? false),
		'max_bytes' => isset($args['max-bytes']) ? (int)$args['max-bytes'] : 2000000,
		'allow_binary' => filter_bool($args['allow-binary'] ?? false),
		'split_bytes' => isset($args['split-bytes']) ? max(0, (int)$args['split-bytes']) : 0,
	];

	$jobs = [];

	/* Batch JSON */
	if (!empty($args['batch'])) {
		$batchPath = normalize_path($args['batch']);
		if (!is_file($batchPath)) {
			fwrite(STDERR, "Error: Batch file not found: {$batchPath}\n");
			return 1;
		}
		[$batchDefaults, $batchJobs] = parse_batch_json($batchPath);

		$mergedDefaults = [
			'ext' => isset($batchDefaults['ext']) ? array_map('strtolower', $batchDefaults['ext']) : $global['ext'],
			'exclude' => $batchDefaults['exclude'] ?? $global['exclude'],
			'exclude_paths' => isset($batchDefaults['exclude_paths']) ? normalize_paths_array($batchDefaults['exclude_paths']) : $global['exclude_paths'],
			'follow_symlinks' => isset($batchDefaults['follow_symlinks']) ? (bool)$batchDefaults['follow_symlinks'] : $global['follow_symlinks'],
			'max_bytes' => isset($batchDefaults['max_bytes']) ? (int)$batchDefaults['max_bytes'] : $global['max_bytes'],
			'allow_binary' => isset($batchDefaults['allow_binary']) ? (bool)$batchDefaults['allow_binary'] : $global['allow_binary'],
			'split_bytes' => isset($batchDefaults['split_bytes']) ? max(0, (int)$batchDefaults['split_bytes']) : $global['split_bytes'],
		];

		foreach ($batchJobs as $j) {
			if (!isset($j['source']) || empty($j['output'])) {
				fwrite(STDERR, "Error: Each job in batch must include 'source' and 'output'.\n");
				return 1;
			}
			[$sources, $sourceDirOrNull] = normalize_sources($j['source']);
			$jobs[] = [
				'sources' => $sources,
				'source_dir' => $sourceDirOrNull,
				'output' => normalize_path($j['output']),
				'ext' => isset($j['ext']) ? array_map('strtolower', $j['ext']) : $mergedDefaults['ext'],
				'exclude' => $j['exclude'] ?? $mergedDefaults['exclude'],
				'exclude_paths' => isset($j['exclude_paths']) ? normalize_paths_array($j['exclude_paths']) : $mergedDefaults['exclude_paths'],
				'follow_symlinks' => isset($j['follow_symlinks']) ? (bool)$j['follow_symlinks'] : $mergedDefaults['follow_symlinks'],
				'max_bytes' => isset($j['max_bytes']) ? (int)$j['max_bytes'] : $mergedDefaults['max_bytes'],
				'allow_binary' => isset($j['allow_binary']) ? (bool)$j['allow_binary'] : $mergedDefaults['allow_binary'],
				'split_bytes' => isset($j['split_bytes']) ? max(0, (int)$j['split_bytes']) : $mergedDefaults['split_bytes'],
			];
		}
	}

	/* Multi-job via --job */
	if (isset($args['job'])) {
		$raw = $args['job'];
		if (!is_array($raw)) $raw = [$raw];
		foreach ($raw as $jobStr) {
			$jobs[] = parse_job($jobStr, $global);
		}
	}

	/* Single-job fallback */
	if (!$jobs) {
		$source = $args['source'] ?? null;
		$output = $args['output'] ?? null;
		if (!$source || !$output) {
			fwrite(STDERR, "Error: Provide --batch, or --job, or --source/--output.\n");
			print_concat_help();
			return 1;
		}
		[$sources, $sourceDirOrNull] = normalize_sources($source);
		$jobs[] = [
			'sources' => $sources,
			'source_dir' => $sourceDirOrNull,
			'output' => normalize_path($output),
			'ext' => $global['ext'],
			'exclude' => $global['exclude'],
			'exclude_paths' => $global['exclude_paths'],
			'follow_symlinks' => $global['follow_symlinks'],
			'max_bytes' => $global['max_bytes'],
			'allow_binary' => $global['allow_binary'],
			'split_bytes' => $global['split_bytes'],
		];
	}

	/* Execute all jobs */
	$overallProcessed = 0;
	foreach ($jobs as $i => $job) {
		[$processed, $stats] = run_concat_job($job);
		$overallProcessed += $processed;

		$idx = $i + 1;
		fwrite(STDOUT, "[Job {$idx}] Wrote {$processed} files to {$job['output']}\n");
		if ($stats) {
			fwrite(STDOUT, "[Job {$idx}] Skipped: size={$stats['size']}, binary={$stats['binary']}, excluded_name={$stats['excluded_name']}, excluded_path={$stats['excluded_path']}, ext={$stats['ext']}\n");
		}
	}
	return ($overallProcessed > 0 ? 0 : 2);
}

function run_concat_job(array $job): array {
	$separator = str_repeat('-', 49);
	$startTime = date('c');

	$processed = 0;
	$skippedSize = 0;
	$skippedBinary = 0;
	$skippedExcludedDir = 0;
	$skippedExcludedPath = 0;
	$skippedNoExtMatch = 0;

	$nameExcludeSet = array_fill_keys($job['exclude'], true);

	/* Chunking setup */
	$chunkIdx = 1;
	$currentBytes = 0;
	$splitBytes = (int)$job['split_bytes'];
	$baseOut = $job['output'];

	$headerLines = [
		"Concatenated code export",
		!empty($job['sources'])
			? "Sources (files): " . implode(', ', $job['sources'])
			: "Source (dir): " . $job['source_dir'],
		"Excluded (names): " . implode(',', $job['exclude']),
		"Excluded (paths): " . implode(',', $job['exclude_paths']),
		"Generated: {$startTime}",
		"Extensions: " . implode(',', $job['ext']),
	];

	$outPath = chunk_filename($baseOut, $chunkIdx);
	$fp = open_output($outPath);
	if ($fp === false) return [0, null];
	write_chunk_header($fp, $headerLines, $chunkIdx, $splitBytes);
	$currentBytes = ftell($fp);

	$write_block = function(string $displayPath, string $contents) use (&$fp, &$currentBytes, $separator) {
		$block  = $separator . "\n";
		$block .= " " . $displayPath . "\n";
		$block .= $separator . "\n\n";
		$block .= $contents;
		if (!str_ends_with($contents, "\n")) $block .= "\n";
		$block .= "\n";
		$written = fwrite($fp, $block);
		$currentBytes += ($written !== false ? $written : 0);
	};

	/* Explicit files */
	if (!empty($job['sources'])) {
		foreach ($job['sources'] as $fullPath) {
			$fullPath = str_replace('\\', '/', $fullPath);
			if (!is_file($fullPath)) { fwrite(STDERR, "Warning: Missing file: {$fullPath}\n"); continue; }

			$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
			if ($ext === '' || !in_array($ext, $job['ext'], true)) { $skippedNoExtMatch++; continue; }

			$size = @filesize($fullPath);
			if ($size !== false && $size > $job['max_bytes']) { $skippedSize++; continue; }

			$contents = @file_get_contents($fullPath);
			if ($contents === false) { fwrite(STDERR, "Warning: Unable to read: {$fullPath}\n"); continue; }
			if (!$job['allow_binary'] && is_binary_string($contents)) { $skippedBinary++; continue; }

			$displayPath = display_path_for_header($fullPath);

			$blockLen = strlen($separator) + 1
				+ 1 + strlen($displayPath) + 1
				+ strlen($separator) + 2
				+ strlen($contents)
				+ (str_ends_with($contents, "\n") ? 1 : 2);

			if ($splitBytes > 0 && $currentBytes > 0 && ($currentBytes + $blockLen) > $splitBytes) {
				fclose($fp);
				$chunkIdx++;
				$outPath = chunk_filename($baseOut, $chunkIdx);
				$fp = open_output($outPath);
				if ($fp === false) { fwrite(STDERR, "Error: Failed to open next chunk: {$outPath}\n"); return [0, null]; }
				write_chunk_header($fp, $headerLines, $chunkIdx, $splitBytes);
				$currentBytes = ftell($fp);
			}

			if ($splitBytes > 0 && $blockLen > $splitBytes && $currentBytes === ftell($fp)) {
				fwrite(STDERR, "Warning: Single file block exceeds split-bytes ({$splitBytes}). Writing anyway: {$displayPath}\n");
			}

			$write_block($displayPath, $contents);
			$processed++;
		}
		fclose($fp);
		return [$processed, [
			'size' => $skippedSize, 'binary' => $skippedBinary,
			'excluded_name' => $skippedExcludedDir, 'excluded_path' => $skippedExcludedPath, 'ext' => $skippedNoExtMatch,
		]];
	}

	/* Directory source */
	$sourceDir = $job['source_dir'];
	if (!is_dir($sourceDir)) {
		fwrite(STDERR, "Error: Source directory not found: {$sourceDir}\n");
		fclose($fp);
		return [0, null];
	}

	$flags = \FilesystemIterator::SKIP_DOTS;
	$dirIt = new RecursiveDirectoryIterator($sourceDir, $flags);
	$it = new RecursiveIteratorIterator($dirIt, RecursiveIteratorIterator::SELF_FIRST);

	$sourceReal = realpath($sourceDir) ?: $sourceDir;
	$sourceReal = str_replace('\\', '/', $sourceReal);

	foreach ($it as $fileInfo) {
		if (!$fileInfo->isFile()) continue;
		if (is_link($fileInfo->getPathname()) && !$job['follow_symlinks']) continue;

		$fullPath = str_replace('\\', '/', $fileInfo->getPathname());
		$displayPath = display_path_for_header($fullPath);

		$relInsideSource = ltrim(str_replace($sourceReal, '', $fullPath), '/');
		if (is_path_excluded($relInsideSource, $job['exclude_paths'])) { $skippedExcludedPath++; continue; }

		$pathParts = explode('/', str_replace('\\', '/', $fileInfo->getPath()));
		$excludedHit = false;
		foreach ($pathParts as $part) {
			if ($part === '') continue;
			if (in_array($part, $job['exclude'], true)) { $excludedHit = true; break; }
		}
		if ($excludedHit) { $skippedExcludedDir++; continue; }

		$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
		if ($ext === '' || !in_array($ext, $job['ext'], true)) { $skippedNoExtMatch++; continue; }

		$size = $fileInfo->getSize();
		if ($size > $job['max_bytes']) { $skippedSize++; continue; }

		$contents = @file_get_contents($fullPath);
		if ($contents === false) { fwrite(STDERR, "Warning: Unable to read: {$fullPath}\n"); continue; }
		if (!$job['allow_binary'] && is_binary_string($contents)) { $skippedBinary++; continue; }

		$blockLen = strlen($separator) + 1
			+ 1 + strlen($displayPath) + 1
			+ strlen($separator) + 2
			+ strlen($contents)
			+ (str_ends_with($contents, "\n") ? 1 : 2);

		if ($splitBytes > 0 && $currentBytes > 0 && ($currentBytes + $blockLen) > $splitBytes) {
			fclose($fp);
			$chunkIdx++;
			$outPath = chunk_filename($baseOut, $chunkIdx);
			$fp = open_output($outPath);
			if ($fp === false) { fwrite(STDERR, "Error: Failed to open next chunk: {$outPath}\n"); return [0, null]; }
			write_chunk_header($fp, $headerLines, $chunkIdx, $splitBytes);
			$currentBytes = ftell($fp);
		}

		if ($splitBytes > 0 && $blockLen > $splitBytes && $currentBytes === ftell($fp)) {
			fwrite(STDERR, "Warning: Single file block exceeds split-bytes ({$splitBytes}). Writing anyway: {$displayPath}\n");
		}

		$write_block($displayPath, $contents);
		$processed++;
	}

	fclose($fp);
	return [$processed, [
		'size' => $skippedSize, 'binary' => $skippedBinary,
		'excluded_name' => $skippedExcludedDir, 'excluded_path' => $skippedExcludedPath, 'ext' => $skippedNoExtMatch,
	]];
}

/* =======================================================================
 * (3) Mode: TREE
 * ======================================================================= */

function run_tree_mode(array $DEFAULT_EXTS, array $DEFAULT_EXCLUDES): int {
	$args = getopt('', [
		'source:',
		'output:',
		'batch:',
		'ext::',
		'all-files::',
		'exclude::',
		'exclude-paths::',
		'follow-symlinks::',
		'dirs-only::',
		'max-depth::',
	], $optind);

	/* -------- Batch JSON mode -------- */
	if (!empty($args['batch'])) {
		$batchPath = normalize_path($args['batch']);
		if (!is_file($batchPath)) {
			fwrite(STDERR, "Error: Batch file not found: {$batchPath}\n");
			return 1;
		}

		[$batchDefaults, $batchJobs] = parse_batch_json($batchPath);

		// Start with safe defaults for every job
		$defaults = [
			'ext'             => isset($batchDefaults['ext']) ? array_map('strtolower', $batchDefaults['ext']) : $DEFAULT_EXTS,
			'exclude'         => $batchDefaults['exclude'] ?? $DEFAULT_EXCLUDES,
			'exclude_paths'   => isset($batchDefaults['exclude_paths']) ? normalize_paths_array($batchDefaults['exclude_paths']) : [],
			'follow_symlinks' => isset($batchDefaults['follow_symlinks']) ? (bool)$batchDefaults['follow_symlinks'] : false,
			'dirs_only'       => isset($batchDefaults['dirs_only']) ? (bool)$batchDefaults['dirs_only'] : false,
			'max_depth'       => isset($batchDefaults['max_depth']) ? max(0, (int)$batchDefaults['max_depth']) : 0,
			'all_files'       => isset($batchDefaults['all_files']) ? (bool)$batchDefaults['all_files'] : false,
		];

		$anyRan = false;

		foreach ($batchJobs as $job) {
			if (!isset($job['source']) || empty($job['output'])) {
				fwrite(STDERR, "Error: Each job in batch must include 'source' and 'output'.\n");
				return 1;
			}

			// Merge job into defaults (ADD-ONLY for excludes)
			$cfg = $defaults;
			if (isset($job['ext']))             $cfg['ext'] = array_map('strtolower', $job['ext']);
			if (isset($job['exclude']))         $cfg['exclude'] = merge_unique($cfg['exclude'], parse_csv_raw(join(',', $job['exclude'])));
			if (isset($job['exclude_paths']))   $cfg['exclude_paths'] = merge_unique($cfg['exclude_paths'], normalize_paths_array($job['exclude_paths']));
			if (isset($job['follow_symlinks'])) $cfg['follow_symlinks'] = (bool)$job['follow_symlinks'];
			if (isset($job['dirs_only']))       $cfg['dirs_only'] = (bool)$job['dirs_only'];
			if (isset($job['max_depth']))       $cfg['max_depth'] = max(0, (int)$job['max_depth']);
			if (isset($job['all_files']))       $cfg['all_files'] = (bool)$job['all_files'];

			// CLI overrides are also additive for excludes (if provided)
			if (isset($args['ext']))             $cfg['ext'] = parse_csv($args['ext']);
			if (isset($args['exclude']))         $cfg['exclude'] = merge_unique($cfg['exclude'], parse_csv_raw($args['exclude']));
			if (isset($args['exclude-paths']))   $cfg['exclude_paths'] = merge_unique($cfg['exclude_paths'], normalize_paths_array(parse_csv_raw($args['exclude-paths'])));
			if (array_key_exists('follow-symlinks', $args)) $cfg['follow_symlinks'] = filter_bool($args['follow-symlinks']);
			if (array_key_exists('dirs-only', $args))       $cfg['dirs_only'] = filter_bool($args['dirs-only']);
			if (isset($args['max-depth']))                  $cfg['max_depth'] = max(0, (int)$args['max-depth']);
			if (array_key_exists('all-files', $args))       $cfg['all_files'] = filter_bool($args['all-files']);

			// Normalize source(s)
			$sources = $job['source'];
			if (!is_array($sources)) $sources = [$sources];

			$output = normalize_path($job['output']);
			$fp = open_output($output);
			if ($fp === false) return 1;

			$separator = str_repeat('-', 72);

			// File header (job-level)
			$header = [
				"Directory tree export",
				"Output: " . $output,
				"Generated: " . date('c'),
				"Follow symlinks: " . ($cfg['follow_symlinks'] ? 'true' : 'false'),
				"Dirs only: " . ($cfg['dirs_only'] ? 'true' : 'false'),
				"Max depth: " . ($cfg['max_depth'] ?: 'unlimited'),
				"Excluded (names): " . implode(',', $cfg['exclude']),
				"Excluded (paths): " . implode(',', $cfg['exclude_paths']),
				"Extensions: " . ($cfg['all_files'] ? 'ALL' : implode(',', $cfg['ext'])),
			];
			foreach ($header as $h) fwrite($fp, $h . "\n");
			fwrite($fp, $separator . "\n\n");

			$grand = ['dirs' => 0, 'files' => 0, 'excluded_name' => 0, 'excluded_path' => 0, 'ext_filtered' => 0];

			foreach ($sources as $si => $src) {
				$srcNorm = normalize_path($src);
				if (!is_dir($srcNorm)) {
					fwrite(STDERR, "Warning: Source directory not found (skipping): {$srcNorm}\n");
					continue;
				}

				$cfgSource = $cfg;
				$cfgSource['source'] = $srcNorm;

				[$lines, $stats] = build_tree_lines($cfgSource);

				// Write tree lines (they already include root label)
				foreach ($lines as $line) fwrite($fp, $line . "\n");
				if ($si < count($sources) - 1) fwrite($fp, "\n"); // space between sources

				$grand['dirs'] += $stats['dirs'];
				$grand['files'] += $stats['files'];
				$grand['excluded_name'] += $stats['excluded_name'];
				$grand['excluded_path'] += $stats['excluded_path'];
				$grand['ext_filtered'] += $stats['ext_filtered'];
			}

			fwrite($fp, "\n{$separator}\n");
			fwrite($fp, "Stats (total): dirs={$grand['dirs']}, files={$grand['files']}, skipped_name={$grand['excluded_name']}, skipped_path={$grand['excluded_path']}, skipped_ext={$grand['ext_filtered']}\n");
			fclose($fp);

			fwrite(STDOUT, "Wrote tree to {$output}\n");
			$anyRan = true;
		}

		return $anyRan ? 0 : 2;
	}

	/* -------- Single job via CLI flags -------- */
	$source = $args['source'] ?? null;
	$output = $args['output'] ?? null;
	if (!$source || !$output) {
		fwrite(STDERR, "Error: --source and --output are required for --mode=tree.\n");
		print_tree_help();
		return 1;
	}

	// Start with defaults, then additive merges if flags provided
	$cfg = [
		'source'          => normalize_path($source),
		'output'          => normalize_path($output),
		'ext'             => isset($args['ext']) ? parse_csv($args['ext']) : $DEFAULT_EXTS,
		'all_files'       => filter_bool($args['all-files'] ?? false),
		'exclude'         => $DEFAULT_EXCLUDES, // defaults first
		'exclude_paths'   => [],                // start empty
		'follow_symlinks' => filter_bool($args['follow-symlinks'] ?? false),
		'dirs_only'       => filter_bool($args['dirs-only'] ?? false),
		'max_depth'       => isset($args['max-depth']) ? max(0, (int)$args['max-depth']) : 0,
	];
	if (isset($args['exclude']))       $cfg['exclude'] = merge_unique($cfg['exclude'], parse_csv_raw($args['exclude']));
	if (isset($args['exclude-paths'])) $cfg['exclude_paths'] = merge_unique($cfg['exclude_paths'], normalize_paths_array(parse_csv_raw($args['exclude-paths'])));

	if (!is_dir($cfg['source'])) {
		fwrite(STDERR, "Error: Source directory not found: {$cfg['source']}\n");
		return 1;
	}

	[$lines, $stats] = build_tree_lines($cfg);

	$fp = open_output($cfg['output']);
	if ($fp === false) return 1;

	$header = [
		"Directory tree export",
		"Source: " . display_path_for_header($cfg['source']),
		"Generated: " . date('c'),
		"Follow symlinks: " . ($cfg['follow_symlinks'] ? 'true' : 'false'),
		"Dirs only: " . ($cfg['dirs_only'] ? 'true' : 'false'),
		"Max depth: " . ($cfg['max_depth'] ?: 'unlimited'),
		"Excluded (names): " . implode(',', $cfg['exclude']),
		"Excluded (paths): " . implode(',', $cfg['exclude_paths']),
		"Extensions: " . ($cfg['all_files'] ? 'ALL' : implode(',', $cfg['ext'])),
	];
	$separator = str_repeat('-', 72);

	foreach ($header as $h) fwrite($fp, $h . "\n");
	fwrite($fp, $separator . "\n\n");
	foreach ($lines as $line) fwrite($fp, $line . "\n");
	fwrite($fp, "\n{$separator}\n");
	fwrite($fp, "Stats: dirs={$stats['dirs']}, files={$stats['files']}, skipped_name={$stats['excluded_name']}, skipped_path={$stats['excluded_path']}, skipped_ext={$stats['ext_filtered']}\n");
	fclose($fp);

	fwrite(STDOUT, "Wrote tree to {$cfg['output']}\n");
	return 0;
}

function build_tree_lines(array $cfg): array {
	$src = $cfg['source'];
	$rootLabel = basename($src);
	if ($rootLabel === '' || $rootLabel === '/' || $rootLabel === '.') $rootLabel = $src;

	$lines = [];
	$stats = ['dirs' => 0, 'files' => 0, 'excluded_name' => 0, 'excluded_path' => 0, 'ext_filtered' => 0];

	$nameExcludeSet = array_fill_keys($cfg['exclude'], true);
	$visited = []; // symlink loop protection

	$lines[] = $rootLabel . '/';

	$absSrc = realpath($src) ?: $src;
	$absSrc = str_replace('\\', '/', $absSrc);

	[$children, $localStats] = list_children($absSrc, '', $cfg, $nameExcludeSet, $visited, 1);
	$stats = add_stats($stats, $localStats);

	$lastIdx = count($children) - 1;
	foreach ($children as $i => $child) {
		$isLast = ($i === $lastIdx);
		render_node($lines, $child, '', $isLast);
	}
	return [$lines, $stats];
}

function list_children(string $absDir, string $relDirFromSource, array $cfg, array $nameExcludeSet, array &$visited, int $depth): array {
	$nodes = [];
	$stats = ['dirs' => 0, 'files' => 0, 'excluded_name' => 0, 'excluded_path' => 0, 'ext_filtered' => 0];

	$it = @scandir($absDir);
	if ($it === false) return [$nodes, $stats];

	$entries = array_values(array_filter($it, fn($e) => $e !== '.' && $e !== '..'));

	$dirs = [];
	$files = [];
	foreach ($entries as $name) {
		$full = "{$absDir}/{$name}";
		if (is_dir($full)) $dirs[] = $name; else $files[] = $name;
	}
	sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
	sort($files, SORT_NATURAL | SORT_FLAG_CASE);

	/* Directories */
	foreach ($dirs as $d) {
		if (isset($nameExcludeSet[$d])) { $stats['excluded_name']++; continue; }

		$full = "{$absDir}/{$d}";
		$rel  = ltrim($relDirFromSource . ($relDirFromSource === '' ? '' : '/') . $d, '/');

		if (is_path_excluded($rel, $cfg['exclude_paths'])) { $stats['excluded_path']++; continue; }

		if (is_link($full)) {
			if (!$cfg['follow_symlinks']) continue;
			$real = realpath($full);
			if ($real !== false) {
				$real = str_replace('\\', '/', $real);
				if (isset($visited[$real])) continue;
				$visited[$real] = true;
			}
		}

		$node = ['type' => 'dir', 'name' => $d, 'children' => []];
		$stats['dirs']++;

		$canRecurse = ($cfg['max_depth'] === 0 || $depth < $cfg['max_depth']);
		if ($canRecurse) {
			[$grandChildren, $childStats] = list_children($full, $rel, $cfg, $nameExcludeSet, $visited, $depth + 1);
			$node['children'] = $grandChildren;
			$stats = add_stats($stats, $childStats);
		}
		$nodes[] = $node;
	}

	/* Files */
	if (!$cfg['dirs_only']) {
		foreach ($files as $f) {
			$rel = ltrim($relDirFromSource . ($relDirFromSource === '' ? '' : '/') . $f, '/');
			if (is_path_excluded($rel, $cfg['exclude_paths'])) { $stats['excluded_path']++; continue; }

			if (!$cfg['all_files']) {
				$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
				if ($ext === '' || !in_array($ext, $cfg['ext'], true)) { $stats['ext_filtered']++; continue; }
			}

			$nodes[] = ['type' => 'file', 'name' => $f];
			$stats['files']++;
		}
	}
	return [$nodes, $stats];
}

function render_node(array &$lines, array $node, string $prefix, bool $isLast): void {
	$branch = $isLast ? '└── ' : '├── ';
	if ($node['type'] === 'dir') {
		$lines[] = "{$prefix}{$branch}{$node['name']}/";
		$childPrefix = $prefix . ($isLast ? '    ' : '│   ');
		$kids = $node['children'];
		$lastIdx = count($kids) - 1;
		foreach ($kids as $i => $child) {
			$kidLast = ($i === $lastIdx);
			render_node($lines, $child, $childPrefix, $kidLast);
		}
	} else {
		$lines[] = "{$prefix}{$branch}{$node['name']}";
	}
}

function add_stats(array $a, array $b): array {
	$a['dirs'] += $b['dirs'];
	$a['files'] += $b['files'];
	$a['excluded_name'] += $b['excluded_name'];
	$a['excluded_path'] += $b['excluded_path'];
	$a['ext_filtered'] += $b['ext_filtered'];
	return $a;
}

/* =======================================================================
 * (5) Shared Helpers (DRY)
 * ======================================================================= */

function parse_csv(string $s): array {
	return array_values(array_filter(array_map(fn($e) => strtolower(trim($e)), explode(',', $s))));
}
function parse_csv_raw(string $s): array {
	return array_values(array_filter(array_map(fn($e) => trim($e), explode(',', $s))));
}
function normalize_paths_array(array $paths): array {
	$out = [];
	foreach ($paths as $p) {
		$p = trim($p);
		$p = str_replace('\\', '/', $p);
		$p = ltrim($p, './');
		$p = ltrim($p, '/');
		$p = rtrim($p, '/');
		if ($p !== '') $out[] = $p;
	}
	return array_values(array_unique($out));
}
function merge_unique(array $base, array $add): array {
	return array_values(array_unique(array_merge($base, $add)));
}
function filter_bool($v): bool { return filter_var($v, FILTER_VALIDATE_BOOLEAN); }
function normalize_path(string $p): string {
	$p = str_replace('\\', '/', $p);
	$p = rtrim($p, '/');
	return $p === '' ? '.' : $p;
}

/* Display path relative to CWD */
function display_path_for_header(string $fullPath): string {
	$fullReal = realpath($fullPath);
	$full = str_replace('\\', '/', $fullReal !== false ? $fullReal : $fullPath);
	$cwdReal = realpath(getcwd());
	$cwd = str_replace('\\', '/', $cwdReal !== false ? $cwdReal : getcwd());
	return ltrim(str_replace($cwd, '', $full), '/');
}

/* Path exclusion: exact match or prefix "<xp>/" */
function is_path_excluded(string $relPath, array $excludePaths): bool {
	$rel = ltrim(str_replace('\\', '/', $relPath), '/');
	foreach ($excludePaths as $xp) {
		$xp = str_replace('\\', '/', $xp);
		$xp = ltrim($xp, '/');
		$xp = rtrim($xp, '/');
		if ($xp === '') continue;
		if ($rel === $xp) return true;
		if (strpos($rel, $xp . '/') === 0) return true;
	}
	return false;
}

/* Concat: source parsing */
function normalize_sources($src) : array {
	if (is_array($src)) {
		$files = [];
		foreach ($src as $p) {
			$p = normalize_path($p);
			if (!is_file($p)) {
				fwrite(STDERR, "Warning: Skipping non-file in array source: {$p}\n");
				continue;
			}
			$files[] = $p;
		}
		return [$files, null];
	}
	$src = normalize_path((string)$src);

	if (strpos($src, ',') !== false) {
		$list = array_map('trim', explode(',', $src));
		$files = [];
		foreach ($list as $p) {
			$p = normalize_path($p);
			if (!is_file($p)) {
				fwrite(STDERR, "Warning: Skipping non-file in list: {$p}\n");
				continue;
			}
			$files[] = $p;
		}
		return [$files, null];
	}

	if (is_dir($src)) return [[], $src];
	if (is_file($src)) return [[$src], null];

	fwrite(STDERR, "Error: Source not found: {$src}\n");
	exit(1);
}

function parse_job(string $jobStr, array $global): array {
	$parts = explode(';', $jobStr);
	$pair = array_shift($parts);
	[$src, $out] = array_map('trim', explode('=>', $pair) + ['', '']);
	if ($src === '' || $out === '') {
		fwrite(STDERR, "Error: Invalid --job format. Expected \"source=>output[;key=value...]\". Got: {$jobStr}\n");
		exit(1);
	}
	[$sources, $sourceDirOrNull] = normalize_sources($src);

	$job = [
		'sources' => $sources,
		'source_dir' => $sourceDirOrNull,
		'output' => normalize_path($out),
		'ext' => $global['ext'],
		'exclude' => $global['exclude'],
		'exclude_paths' => $global['exclude_paths'],
		'follow_symlinks' => $global['follow_symlinks'],
		'max_bytes' => $global['max_bytes'],
		'allow_binary' => $global['allow_binary'],
		'split_bytes' => $global['split_bytes'],
	];
	foreach ($parts as $kv) {
		if (strpos($kv, '=') === false) continue;
		[$k, $v] = array_map('trim', explode('=', $kv, 2));
		switch ($k) {
			case 'ext': $job['ext'] = parse_csv($v); break;
			case 'exclude': $job['exclude'] = array_map('trim', explode(',', $v)); break;
			case 'exclude-paths': $job['exclude_paths'] = normalize_paths_array(parse_csv_raw($v)); break;
			case 'max-bytes': $job['max_bytes'] = (int)$v; break;
			case 'allow-binary': $job['allow_binary'] = filter_bool($v); break;
			case 'follow-symlinks': $job['follow_symlinks'] = filter_bool($v); break;
			case 'split-bytes': $job['split_bytes'] = max(0, (int)$v); break;
		}
	}
	return $job;
}

function parse_batch_json(string $path): array {
	$json = file_get_contents($path);
	if ($json === false) {
		fwrite(STDERR, "Error: Unable to read batch file: {$path}\n");
		exit(1);
	}
	$data = json_decode($json, true);
	if (!is_array($data)) {
		fwrite(STDERR, "Error: Invalid JSON in batch file: {$path}\n");
		exit(1);
	}
	$defaults = $data['defaults'] ?? [];
	$jobs = $data['jobs'] ?? null;
	if (!is_array($jobs)) {
		fwrite(STDERR, "Error: Batch file must include a 'jobs' array.\n");
		exit(1);
	}
	return [$defaults, $jobs];
}

/* Output + chunk header helpers */
function open_output(string $path) {
	$dir = dirname($path);
	if ($dir && !is_dir($dir)) {
		if (!mkdir($dir, 0777, true)) {
			fwrite(STDERR, "Error: Unable to create output directory: {$dir}\n");
			return false;
		}
	}
	$fp = @fopen($path, 'wb');
	if (!$fp) {
		fwrite(STDERR, "Error: Cannot open output file for writing: {$path}\n");
		return false;
	}
	return $fp;
}

function chunk_filename(string $baseOutput, int $index): string {
	$baseOutput = normalize_path($baseOutput);
	$dot = strrpos($baseOutput, '.');
	if ($dot === false) return $baseOutput . $index;
	$name = substr($baseOutput, 0, $dot);
	$ext = substr($baseOutput, $dot);
	return $name . $index . $ext;
}

function write_chunk_header($fp, array $headerLines, int $chunkIndex, int $splitBytes): void {
	$separator = str_repeat('-', 49);
	foreach ($headerLines as $line) fwrite($fp, $line . "\n");
	fwrite($fp, "Chunk: {$chunkIndex}" . ($splitBytes > 0 ? " (limit {$splitBytes} bytes)" : "") . "\n");
	fwrite($fp, $separator . "\n\n");
}

/* Binary sniff */
function is_binary_string(string $s): bool {
	if (strpos($s, "\0") !== false) return true;
	$len = strlen($s);
	if ($len === 0) return false;
	$nonPrintable = 0;
	for ($i = 0; $i < min($len, 4096); $i++) {
		$ord = ord($s[$i]);
		if ($ord === 9 || $ord === 10 || $ord === 13) continue;
		if ($ord < 32 || $ord > 126) $nonPrintable++;
	}
	return ($nonPrintable > 128);
}
