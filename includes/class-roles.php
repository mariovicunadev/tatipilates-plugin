<?php
/**
 * Student role behavior.
 *
 * @package TatiPilates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the custom student role while the plugin is active.
 */
class TP_Roles {

    /**
     * Student role slug.
     *
     * @var string
     */
    const ROLE_ALUMNA = 'tp_alumna';

    /**
     * Pilates admin role slug.
     *
     * @var string
     */
    const ROLE_ADMIN_PILATES = 'tp_admin_pilates';

    /**
     * Capability required to manage the Pilates system.
     *
     * @var string
     */
    const CAP_MANAGE_PILATES = 'tp_manage_pilates';

    /**
     * Registers runtime hooks.
     */
    public function __construct() {
        add_action('init', array($this, 'registrar_rol'));
        add_action('admin_init', array($this, 'redirigir_alumnas_del_admin'));
        add_filter('login_redirect', array($this, 'redirigir_despues_del_login'), 10, 3);
        add_filter('show_admin_bar', array($this, 'ocultar_admin_bar_para_alumnas'));
    }

    /**
     * Ensures the student role exists.
     *
     * The activator creates it once, but this keeps local/staging installs resilient
     * if roles are reset while the plugin remains active.
     *
     * @return void
     */
    public function registrar_rol() {
        self::registrar_roles();
    }

    /**
     * Ensures custom roles and capabilities exist.
     *
     * @return void
     */
    public static function registrar_roles() {
        if (!get_role(self::ROLE_ALUMNA)) {
            add_role(
                self::ROLE_ALUMNA,
                'Estudiante de Pilates',
                array(
                    'read' => true,
                )
            );
        }

        if (!get_role(self::ROLE_ADMIN_PILATES)) {
            add_role(
                self::ROLE_ADMIN_PILATES,
                'Admin Pilates',
                array(
                    'read'                => true,
                    self::CAP_MANAGE_PILATES => true,
                )
            );
        }

        $admin_pilates = get_role(self::ROLE_ADMIN_PILATES);

        if ($admin_pilates && !$admin_pilates->has_cap(self::CAP_MANAGE_PILATES)) {
            $admin_pilates->add_cap(self::CAP_MANAGE_PILATES);
        }

        $administrator = get_role('administrator');

        if ($administrator && !$administrator->has_cap(self::CAP_MANAGE_PILATES)) {
            $administrator->add_cap(self::CAP_MANAGE_PILATES);
        }

        self::actualizar_nombre_visible_rol();
    }

    /**
     * Sends student users away from wp-admin.
     *
     * @return void
     */
    public function redirigir_alumnas_del_admin() {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        global $pagenow;

        if ('admin-post.php' === $pagenow) {
            return;
        }

        if (!self::usuario_actual_es_alumna()) {
            return;
        }

        wp_safe_redirect(self::portal_url());
        exit;
    }

    /**
     * Sends students to the portal after login.
     *
     * @param string           $redirect_to           Requested redirect URL.
     * @param string           $requested_redirect_to Original redirect URL.
     * @param WP_User|WP_Error $user                  Logged-in user or error.
     * @return string
     */
    public function redirigir_despues_del_login($redirect_to, $requested_redirect_to, $user) {
        if ($user instanceof WP_User && self::usuario_es_alumna($user)) {
            return self::portal_url();
        }

        return $redirect_to;
    }

    /**
     * Hides the WordPress admin bar for students on the frontend.
     *
     * @param bool $show Whether to show the admin bar.
     * @return bool
     */
    public function ocultar_admin_bar_para_alumnas($show) {
        if (self::usuario_actual_es_alumna()) {
            return false;
        }

        return $show;
    }

    /**
     * Checks whether the current logged-in user is a student.
     *
     * @return bool
     */
    public static function usuario_actual_es_alumna() {
        $user = wp_get_current_user();

        return self::usuario_es_alumna($user);
    }

    /**
     * Checks whether a given user has the student role.
     *
     * @param WP_User|null $user User object.
     * @return bool
     */
    public static function usuario_es_alumna($user) {
        if (!$user instanceof WP_User || empty($user->roles)) {
            return false;
        }

        return in_array(self::ROLE_ALUMNA, (array) $user->roles, true);
    }

    /**
     * Checks whether the current logged-in user has the Pilates admin role.
     *
     * @return bool
     */
    public static function usuario_actual_es_admin_pilates() {
        $user = wp_get_current_user();

        return self::usuario_es_admin_pilates($user);
    }

    /**
     * Checks whether a given user has the Pilates admin role.
     *
     * @param WP_User|null $user User object.
     * @return bool
     */
    public static function usuario_es_admin_pilates($user) {
        if (!$user instanceof WP_User || empty($user->roles)) {
            return false;
        }

        return in_array(self::ROLE_ADMIN_PILATES, (array) $user->roles, true);
    }

    /**
     * Returns the student portal URL.
     *
     * @return string
     */
    public static function portal_url() {
        $page_id = (int) get_option('tp_portal_page_id');

        if ($page_id) {
            $url = get_permalink($page_id);

            if ($url) {
                return $url;
            }
        }

        return home_url('/mi-pilates/');
    }

    /**
     * Updates the role label for installs where the old name already exists.
     *
     * @return void
     */
    private static function actualizar_nombre_visible_rol() {
        global $wp_roles;

        if (!isset($wp_roles) || !is_object($wp_roles)) {
            $wp_roles = wp_roles();
        }

        if (!isset($wp_roles->roles[self::ROLE_ALUMNA])) {
            return;
        }

        $wp_roles->roles[self::ROLE_ALUMNA]['name'] = 'Estudiante de Pilates';
        $wp_roles->role_names[self::ROLE_ALUMNA]    = 'Estudiante de Pilates';
        update_option($wp_roles->role_key, $wp_roles->roles);
    }
}
