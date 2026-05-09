<?php
/**
 * Plugin Name: Judge IA Plugin
 * Plugin URI: https://seudominio.com/judge-ia
 * Update URI: https://github.com/charlesvsouza/judge-ia-plugin/
 * Description: Assistente de Inteligência Artificial para WordPress com suporte a Gemini e OpenAI, controle de limite diário e interface moderna.
 * Version: 2.1.21
 * Author: Charles Vasconcelos de Souza
 * Author URI: https://seudominio.com
 * Text Domain: judge-ia-plugin
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| CONSTANTES
|--------------------------------------------------------------------------
*/

define('JUDGEIA_PLUGIN_VERSION', '2.1.21');
define('JUDGEIA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('JUDGEIA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Optional token for private repositories.
if (!defined('JUDGEIA_GITHUB_TOKEN')) {
    define('JUDGEIA_GITHUB_TOKEN', '');
}

// Set true in wp-config.php only if your repository is private and needs auth.
if (!defined('JUDGEIA_GITHUB_REPO_PRIVATE')) {
    define('JUDGEIA_GITHUB_REPO_PRIVATE', false);
}

function judgeia_is_placeholder_github_token($token) {
    $token = strtolower(trim((string)$token));

    if ($token === '') {
        return true;
    }

    $placeholders = [
        'your_github_token_here',
        'seu_token_aqui',
        'token_aqui',
        'github_token',
        'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'github_pat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    ];

    return in_array($token, $placeholders, true);
}

/*
|--------------------------------------------------------------------------
| SAFE REQUIRE
|--------------------------------------------------------------------------
*/

function judgeia_safe_require($file) {
    $path = JUDGEIA_PLUGIN_PATH . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

/*
|--------------------------------------------------------------------------
| AUTO UPDATER (GitHub)
|--------------------------------------------------------------------------
*/

$puc_path = JUDGEIA_PLUGIN_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';
if (file_exists($puc_path)) {
    require_once $puc_path;
}

if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
    $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/charlesvsouza/judge-ia-plugin/',
        __FILE__,
        'judge-ia-plugin'
    );

    // Keep updates tied to the production branch.
    $myUpdateChecker->setBranch('main');

    // In this project, version bumps are published on main first.
    $myUpdateChecker->addFilter('vcs_update_detection_strategies', function ($strategies) {
        if (isset($strategies['branch'])) {
            return ['branch' => $strategies['branch']];
        }

        return $strategies;
    });

    // Avoid sending invalid auth on public repositories, which can trigger "Unauthorized".
    if (
        defined('JUDGEIA_GITHUB_REPO_PRIVATE')
        && JUDGEIA_GITHUB_REPO_PRIVATE
        && defined('JUDGEIA_GITHUB_TOKEN')
        && !judgeia_is_placeholder_github_token(JUDGEIA_GITHUB_TOKEN)
    ) {
        $myUpdateChecker->setAuthentication(JUDGEIA_GITHUB_TOKEN);
    }
}

/*
|--------------------------------------------------------------------------
| CORE
|--------------------------------------------------------------------------
*/

judgeia_safe_require('includes/core/defaults.php');
judgeia_safe_require('includes/core/sanitizers.php');
judgeia_safe_require('includes/core/settings-geral.php');
judgeia_safe_require('includes/core/settings-provedores.php');
judgeia_safe_require('includes/core/settings-aparencia.php');
judgeia_safe_require('includes/core/frontend.php');
judgeia_safe_require('includes/core/ajax-handler.php');
judgeia_safe_require('includes/core/response-cleaner.php');
judgeia_safe_require('includes/core/database.php');

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

judgeia_safe_require('includes/admin/admin-page.php');

/*
|--------------------------------------------------------------------------
| PROVIDERS
|--------------------------------------------------------------------------
*/

judgeia_safe_require('includes/providers/provider-interface.php');
judgeia_safe_require('includes/providers/gemini.php');
judgeia_safe_require('includes/providers/openai.php');

/*
|--------------------------------------------------------------------------
| ATIVAÇÃO / ATUALIZAÇÃO
|--------------------------------------------------------------------------
*/

register_activation_hook(__FILE__, 'judgeia_activate_plugin');

function judgeia_activate_plugin() {
    judgeia_install_or_update_database();
    judgeia_initialize_defaults();
    update_option('judgeia_plugin_db_version', JUDGEIA_PLUGIN_VERSION);
}

function judgeia_maybe_upgrade_plugin() {
    $installed_version = get_option('judgeia_plugin_db_version');

    if ($installed_version === JUDGEIA_PLUGIN_VERSION) {
        return;
    }

    judgeia_install_or_update_database();
    judgeia_initialize_defaults();
    update_option('judgeia_plugin_db_version', JUDGEIA_PLUGIN_VERSION);
}

add_action('plugins_loaded', 'judgeia_maybe_upgrade_plugin');