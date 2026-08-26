<?php
/**
 * Regenera las entradas recientes de CHANGELOG.md desde su fuente estructurada.
 *
 * Uso:
 * php scripts/generate-changelog.php
 * php scripts/generate-changelog.php --check
 *
 * @package TatiPilates
 */

$root_dir       = dirname(__DIR__);
$source_path    = $root_dir . '/docs/releases/changelog.json';
$changelog_path = $root_dir . '/CHANGELOG.md';
$args           = array_slice($argv, 1);
$check_only     = in_array('--check', $args, true);

if (array_diff($args, array('--check'))) {
    fwrite(STDERR, "Uso: php scripts/generate-changelog.php [--check]\n");
    exit(1);
}

try {
    $source = json_decode((string) file_get_contents($source_path), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, 'Fuente de changelog invalida: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

if (
    !is_array($source)
    || empty($source['preserve_from'])
    || !isset($source['unreleased'])
    || !is_array($source['unreleased'])
    || empty($source['releases'])
    || !is_array($source['releases'])
) {
    fwrite(STDERR, "La fuente de changelog no cumple el contrato esperado.\n");
    exit(1);
}

$section_names = array('Added', 'Changed', 'Fixed', 'Security', 'Deprecated', 'Removed');

/**
 * Renderiza las secciones de una version.
 *
 * @param array<string,string[]> $sections Secciones y entradas.
 * @param string[]               $allowed  Nombres permitidos.
 * @return string
 */
$render_sections = static function ($sections, $allowed) {
    if (!is_array($sections)) {
        throw new RuntimeException('Las secciones deben ser un objeto JSON.');
    }

    $output = '';

    foreach ($sections as $section => $entries) {
        if (!in_array($section, $allowed, true) || !is_array($entries) || empty($entries)) {
            throw new RuntimeException('Seccion de changelog invalida: ' . (string) $section);
        }

        $output .= '### ' . $section . "\n\n";

        foreach ($entries as $entry) {
            if (!is_string($entry) || '' === trim($entry)) {
                throw new RuntimeException('Cada entrada del changelog debe contener texto.');
            }

            $output .= '- ' . trim($entry) . "\n";
        }

        $output .= "\n";
    }

    return $output;
};

try {
    $generated = "<!-- Generado desde docs/releases/changelog.json mediante scripts/generate-changelog.php. -->\n\n";
    $generated .= "## [Unreleased]\n\n";

    if ($source['unreleased']) {
        $generated .= $render_sections($source['unreleased'], $section_names);
    } else {
        $generated .= "Sin cambios pendientes.\n\n";
    }

    foreach ($source['releases'] as $release) {
        if (
            !is_array($release)
            || empty($release['version'])
            || empty($release['date'])
            || !isset($release['sections'])
        ) {
            throw new RuntimeException('Una release del changelog esta incompleta.');
        }

        if (!preg_match('/^\d+\.\d+\.\d+$/', (string) $release['version'])) {
            throw new RuntimeException('Version de changelog invalida: ' . (string) $release['version']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $release['date'])) {
            throw new RuntimeException('Fecha de changelog invalida: ' . (string) $release['date']);
        }

        $generated .= sprintf("## [%s] - %s\n\n", $release['version'], $release['date']);
        $generated .= $render_sections($release['sections'], $section_names);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'No se pudo generar el changelog: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

$current = (string) file_get_contents($changelog_path);
$marker  = '<!-- Generado desde docs/releases/changelog.json mediante scripts/generate-changelog.php. -->';
$start   = strpos($current, $marker);

if (false === $start) {
    $start = strpos($current, '## [Unreleased]');
}

$anchor = '## [' . $source['preserve_from'] . ']';
$end    = strpos($current, $anchor);

if (false === $start || false === $end || $end <= $start) {
    fwrite(STDERR, "No se encontraron los limites regenerables de CHANGELOG.md.\n");
    exit(1);
}

$expected = rtrim(substr($current, 0, $start)) . "\n\n" . $generated . substr($current, $end);

if ($check_only) {
    if ($expected !== $current) {
        fwrite(STDERR, "CHANGELOG.md esta desactualizado. Ejecuta php scripts/generate-changelog.php.\n");
        exit(1);
    }

    echo "PASS changelog actualizado\n";
    exit(0);
}

if (false === file_put_contents($changelog_path, $expected, LOCK_EX)) {
    fwrite(STDERR, "No se pudo escribir CHANGELOG.md.\n");
    exit(1);
}

echo "CHANGELOG.md regenerado\n";
