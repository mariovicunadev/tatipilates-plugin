<?php
/**
 * Lightweight JS/CSS/YAML lint checks without project dependencies.
 *
 * @package TatiPilates
 */

$root = dirname(__DIR__);

/**
 * Fails the lint process.
 *
 * @param string $message Failure message.
 * @return void
 */
function tp_static_lint_fail($message) {
    fwrite(STDERR, 'FAIL ' . $message . PHP_EOL);
    exit(1);
}

/**
 * Lists files by extension while ignoring generated release artifacts.
 *
 * @param string $root Root directory.
 * @param array<int,string> $extensions Extensions without dot.
 * @return array<int,string>
 */
function tp_static_lint_files($root, $extensions) {
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function ($current) {
                if (!$current->isDir()) {
                    return true;
                }

                return !in_array($current->getFilename(), array('.git', 'release', 'dev'), true);
            }
        )
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $extension = strtolower($file->getExtension());

        if (in_array($extension, $extensions, true)) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Checks common unresolved merge/debug markers.
 *
 * @param string $path File path.
 * @param string $contents File contents.
 * @return void
 */
function tp_static_lint_common($path, $contents) {
    if (preg_match('/^(<<<<<<<|=======|>>>>>>>)/m', $contents)) {
        tp_static_lint_fail($path . ' contiene marcadores de conflicto.');
    }
}

/**
 * Validates JavaScript syntax with Node when available.
 *
 * @param array<int,string> $files JS files.
 * @return void
 */
function tp_static_lint_js($files) {
    $node = trim((string) shell_exec('command -v node 2>/dev/null'));

    if ('' === $node) {
        tp_static_lint_fail('Node.js no esta disponible para validar JavaScript.');
    }

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        tp_static_lint_common($file, false === $contents ? '' : $contents);

        $command = escapeshellcmd($node) . ' --check ' . escapeshellarg($file) . ' 2>&1';
        exec($command, $output, $status);

        if (0 !== $status) {
            tp_static_lint_fail($file . ' no pasa node --check: ' . implode("\n", $output));
        }
    }
}

/**
 * Performs structural CSS checks.
 *
 * @param array<int,string> $files CSS files.
 * @return void
 */
function tp_static_lint_css($files) {
    foreach ($files as $file) {
        $contents = file_get_contents($file);

        if (false === $contents) {
            tp_static_lint_fail('No se pudo leer ' . $file);
        }

        tp_static_lint_common($file, $contents);

        if (substr_count($contents, '/*') !== substr_count($contents, '*/')) {
            tp_static_lint_fail($file . ' tiene comentarios CSS sin cerrar.');
        }

        $stripped = preg_replace('#/\*.*?\*/#s', '', $contents);
        $depth    = 0;
        $length   = strlen($stripped);

        for ($i = 0; $i < $length; $i++) {
            if ('{' === $stripped[$i]) {
                $depth++;
            } elseif ('}' === $stripped[$i]) {
                $depth--;
            }

            if ($depth < 0) {
                tp_static_lint_fail($file . ' tiene una llave CSS de cierre extra.');
            }
        }

        if (0 !== $depth) {
            tp_static_lint_fail($file . ' tiene llaves CSS sin balancear.');
        }
    }
}

/**
 * Validates YAML syntax using Ruby Psych, available on macOS and GitHub runners.
 *
 * @param array<int,string> $files YAML files.
 * @return void
 */
function tp_static_lint_yaml($files) {
    $ruby = trim((string) shell_exec('command -v ruby 2>/dev/null'));

    if ('' === $ruby) {
        tp_static_lint_fail('Ruby no esta disponible para validar YAML.');
    }

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        tp_static_lint_common($file, false === $contents ? '' : $contents);

        $command = escapeshellcmd($ruby) . ' -e ' . escapeshellarg('require "yaml"; YAML.load_file(ARGV[0])') . ' ' . escapeshellarg($file) . ' 2>&1';
        exec($command, $output, $status);

        if (0 !== $status) {
            tp_static_lint_fail($file . ' no pasa YAML.load_file: ' . implode("\n", $output));
        }
    }
}

$js_files   = tp_static_lint_files($root, array('js'));
$css_files  = tp_static_lint_files($root, array('css'));
$yaml_files = tp_static_lint_files($root, array('yml', 'yaml'));

tp_static_lint_js($js_files);
tp_static_lint_css($css_files);
tp_static_lint_yaml($yaml_files);

echo 'PASS static-assets-lint js=' . count($js_files) . ' css=' . count($css_files) . ' yaml=' . count($yaml_files) . PHP_EOL;
