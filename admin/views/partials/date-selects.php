<?php
/**
 * Date field rendered as Day / Month / Year selects.
 *
 * Expects $tp_date_field = array(
 *     'name'      => hidden input name,
 *     'label'     => visible label,
 *     'value'     => current Y-m-d value or empty string,
 *     'max_years' => how many years back the year list goes,
 * ).
 */

if (!defined('ABSPATH')) {
    exit;
}

$tp_ds_valor = isset($tp_date_field['value']) ? (string) $tp_date_field['value'] : '';
$tp_ds_partes = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tp_ds_valor, $tp_ds_match) ? array((int) $tp_ds_match[1], (int) $tp_ds_match[2], (int) $tp_ds_match[3]) : array(0, 0, 0);
$tp_ds_anio_actual = (int) gmdate('Y', current_time('timestamp'));
$tp_ds_anio_min = $tp_ds_anio_actual - (int) $tp_date_field['max_years'];
if ($tp_ds_partes[0] && $tp_ds_partes[0] < $tp_ds_anio_min) {
    $tp_ds_anio_min = $tp_ds_partes[0];
}
$tp_ds_anio_max = max($tp_ds_anio_actual, $tp_ds_partes[0]);
$tp_ds_meses = array(1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre');
?>
<div class="tp-field tp-field-date tp-field-birthdate" data-tp-birthdate>
    <span><?php echo esc_html($tp_date_field['label']); ?></span>
    <div class="tp-birthdate-selects">
        <select data-tp-bd="day" aria-label="<?php echo esc_attr(sprintf(__('%s: dia', 'tatipilates'), $tp_date_field['label'])); ?>">
            <option value=""><?php echo esc_html__('Dia', 'tatipilates'); ?></option>
            <?php for ($tp_ds_d = 1; $tp_ds_d <= 31; $tp_ds_d++) : ?>
                <option value="<?php echo esc_attr($tp_ds_d); ?>" <?php selected($tp_ds_partes[2], $tp_ds_d); ?>><?php echo esc_html($tp_ds_d); ?></option>
            <?php endfor; ?>
        </select>
        <select data-tp-bd="month" aria-label="<?php echo esc_attr(sprintf(__('%s: mes', 'tatipilates'), $tp_date_field['label'])); ?>">
            <option value=""><?php echo esc_html__('Mes', 'tatipilates'); ?></option>
            <?php foreach ($tp_ds_meses as $tp_ds_num => $tp_ds_nombre) : ?>
                <option value="<?php echo esc_attr($tp_ds_num); ?>" <?php selected($tp_ds_partes[1], $tp_ds_num); ?>><?php echo esc_html($tp_ds_nombre); ?></option>
            <?php endforeach; ?>
        </select>
        <select data-tp-bd="year" aria-label="<?php echo esc_attr(sprintf(__('%s: ano', 'tatipilates'), $tp_date_field['label'])); ?>">
            <option value=""><?php echo esc_html__('Ano', 'tatipilates'); ?></option>
            <?php for ($tp_ds_a = $tp_ds_anio_max; $tp_ds_a >= $tp_ds_anio_min; $tp_ds_a--) : ?>
                <option value="<?php echo esc_attr($tp_ds_a); ?>" <?php selected($tp_ds_partes[0], $tp_ds_a); ?>><?php echo esc_html($tp_ds_a); ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <input type="hidden" name="<?php echo esc_attr($tp_date_field['name']); ?>" value="<?php echo esc_attr($tp_ds_valor); ?>">
</div>
