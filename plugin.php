<?php
/**
 * Plugin Name: Toolbox for ApisCP
 * Description: Helper toolbox to better integrate W3TC and other features of the ApisCP hosting platform.
 * Version: 1.0.2
 * Author: thundersquared
 * Author URI: https://thundersquared.com/
 * Text Domain: sqrd-apiscp-toolbox
 */

namespace ApisCP\Toolbox;

defined('ABSPATH') || exit;

class Plugin
{
    private const SECURE_ACCESS_TOKEN = 'SECURE_ACCESS_TOKEN';
    private static ?Plugin $instance = null;

    public static function getInstance(): Plugin
    {
        if (is_null(self::$instance))
        {
            self::$instance = new \ApisCP\Toolbox\Plugin();
        }

        return self::$instance;
    }

    public function __construct()
    {
        // Disable W3TC comment on response documents
        add_filter('w3tc_can_print_comment', '__return_false', 10, 1);
        // Enable secure access via environment-set token
        add_action('set_auth_cookie', array($this, 'set_secure_access_token'), 10, 5);
        // Enable secure access via environment-set token
        add_action('clear_auth_cookie', array($this, 'clear_secure_access_token'), 10);
        register_activation_hook(__FILE__, array($this, 'on_activation'));
    }

    public function on_activation()
    {
        if (!is_plugin_active('w3-total-cache/w3-total-cache.php'))
        {
            deactivate_plugins('sqrd-apiscp-toolbox/plugin.php');
        }
    }

    /**
     * Read secure access token from environment and provide a cookie for mod_evasive to bypass filtering
     *
     * @param $auth_cookie
     * @param $expire
     * @param $expiration
     * @param $user_id
     * @param $scheme
     */
    public function set_secure_access_token($auth_cookie, $expire, $expiration, $user_id, $scheme)
    {
        $cookie = $_ENV[self::SECURE_ACCESS_TOKEN] ?? null;
        $secure = apply_filters('secure_auth_cookie', is_ssl(), $user_id);

        if (!is_null($cookie))
        {
            setcookie(self::SECURE_ACCESS_TOKEN, $cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
            if (COOKIEPATH !== SITECOOKIEPATH)
            {
                setcookie(self::SECURE_ACCESS_TOKEN, $cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure, true);
            }
        }
    }

    /**
     * Clear previously set secure access tokens
     */
    public function clear_secure_access_token()
    {
        setcookie(self::SECURE_ACCESS_TOKEN, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        setcookie(self::SECURE_ACCESS_TOKEN, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN);
    }
}

Plugin::getInstance();

// Include CLI command when WP_CLI exists
if (defined('WP_CLI') && WP_CLI)
{
    require_once dirname(__FILE__) . '/cli.php';
}
