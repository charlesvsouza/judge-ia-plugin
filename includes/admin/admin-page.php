<?php
if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| MENU PRINCIPAL
|--------------------------------------------------------------------------
*/
function judgeia_add_admin_menu() {
    add_menu_page(
        'Judge IA Plugin',
        'Judge IA',
        'manage_options',
        'judgeia-settings',
        'judgeia_render_admin_page',
        'dashicons-format-chat',
        25
    );
}
add_action('admin_menu', 'judgeia_add_admin_menu');


/*
|--------------------------------------------------------------------------
| ENQUEUE ADMIN SCRIPTS (FORMA PROFISSIONAL)
|--------------------------------------------------------------------------
*/
function judgeia_enqueue_admin_scripts($hook) {

    if ($hook !== 'toplevel_page_judgeia-settings') {
        return;
    }

    // Media uploader oficial WP
    wp_enqueue_media();

    // JS Admin isolado
    wp_enqueue_script(
        'judgeia-admin-js',
        JUDGEIA_PLUGIN_URL . 'assets/js/judgeia-admin.js',
        array('jquery'),
        JUDGEIA_PLUGIN_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'judgeia_enqueue_admin_scripts');


/*
|--------------------------------------------------------------------------
| RENDERIZAÇÃO
|--------------------------------------------------------------------------
*/
function judgeia_render_admin_page() {

    if (!current_user_can('manage_options')) return;

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'geral';
    ?>

    <div class="wrap">
        <h1>Judge IA Plugin</h1>

        <h2 class="nav-tab-wrapper">

            <a href="?page=judgeia-settings&tab=geral"
               class="nav-tab <?php echo ($active_tab === 'geral') ? 'nav-tab-active' : ''; ?>">
               Geral
            </a>

            <a href="?page=judgeia-settings&tab=provedores"
               class="nav-tab <?php echo ($active_tab === 'provedores') ? 'nav-tab-active' : ''; ?>">
               Provedores
            </a>

            <a href="?page=judgeia-settings&tab=aparencia"
               class="nav-tab <?php echo ($active_tab === 'aparencia') ? 'nav-tab-active' : ''; ?>">
               Aparência
            </a>

        </h2>

        <div style="background:#fff;padding:20px;margin-top:15px;border-radius:6px;">

            <?php
            switch ($active_tab) {

                case 'provedores':
                    judgeia_render_tab_provedores();
                    break;

                case 'aparencia':
                    judgeia_render_tab_aparencia();
                    break;

                case 'geral':
                default:
                    judgeia_render_tab_geral();
                    break;
            }
            ?>

        </div>

    </div>

    <?php
}