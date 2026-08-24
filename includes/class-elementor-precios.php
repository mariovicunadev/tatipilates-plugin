<?php
/**
 * Exposes plugin-owned pricing to Elementor via a custom Dynamic Tag.
 *
 * Loaded only when Elementor is active (see the 'elementor/loaded' hook in
 * tatipilates.php) so this file is never parsed, and TP_Elementor_Precios
 * is never autoloaded, on a site without Elementor.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

/**
 * Dynamic tag that renders the current price for a selected plan.
 */
class TP_Elementor_Precios extends Tag {

    /**
     * Registers the "Tati Pilates" tag group and this tag.
     *
     * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Elementor's dynamic tags manager.
     * @return void
     */
    public static function registrar($dynamic_tags_manager) {
        $dynamic_tags_manager->register_group(
            'tati-pilates',
            array(
                'title' => __('Tati Pilates', 'tatipilates'),
            )
        );

        $dynamic_tags_manager->register(new self());
    }

    /**
     * @return string
     */
    public function get_name() {
        return 'tp-precio';
    }

    /**
     * @return string
     */
    public function get_title() {
        return __('Precio (Tati Pilates)', 'tatipilates');
    }

    /**
     * @return string
     */
    public function get_group() {
        return 'tati-pilates';
    }

    /**
     * @return array<int,string>
     */
    public function get_categories() {
        return array(Module::TEXT_CATEGORY);
    }

    /**
     * Registers the "Plan" and "Tipo" controls: which class/plan, and
     * whether to render its reference price or its cash-USD price.
     *
     * @return void
     */
    protected function register_controls() {
        $opciones = class_exists('TP_Precios') ? TP_Precios::etiquetas() : array();

        $this->add_control(
            'plan',
            array(
                'label'   => __('Plan', 'tatipilates'),
                'type'    => Controls_Manager::SELECT,
                'options' => $opciones,
                'default' => 'plan_2x',
            )
        );

        $this->add_control(
            'tipo',
            array(
                'label'   => __('Tipo', 'tatipilates'),
                'type'    => Controls_Manager::SELECT,
                'options' => array(
                    'ref' => __('Referencia', 'tatipilates'),
                    'usd' => __('Efectivo USD', 'tatipilates'),
                ),
                'default' => 'ref',
            )
        );
    }

    /**
     * Outputs the formatted price for the selected plan and price type.
     *
     * @return void
     */
    public function render() {
        if (!class_exists('TP_Precios')) {
            return;
        }

        $plan = $this->get_settings('plan');
        $tipo = $this->get_settings('tipo');

        if ('usd' === $tipo) {
            $valor = TP_Precios::obtener(TP_Precios::clave_usd($plan));
            echo esc_html(TP_Precios::formatear_usd($valor));
            return;
        }

        $valor = TP_Precios::obtener($plan);
        echo esc_html(TP_Precios::formatear($valor));
    }
}
