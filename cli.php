<?php

namespace ApisCP\Toolbox;

defined('ABSPATH') || exit;

use W3TC\BrowserCache_Environment;
use W3TC\Cdn_Environment;
use W3TC\DbCache_Environment;
use W3TC\Dispatcher;
use W3TC\Generic_Environment;
use W3TC\Minify_Environment;
use W3TC\ObjectCache_Environment;
use W3TC\PgCache_Environment;
use W3TC\Util_Environment;
use W3TC\Util_Environment_Exceptions;
use W3TC\Util_Rule;
use WP_CLI;
use WP_CLI_Command;

/**
 * The ApisCP plugin integration
 *
 * @package wp-cli
 * @subpackage commands/third-party
 */
class Command extends WP_CLI_Command
{
    // List of W3TC extracted handlers
    private function w3tc_get_handlers(): array
    {
        return array(
            new Generic_Environment(),
            new Minify_Environment(),
            new PgCache_Environment(),
            new BrowserCache_Environment(),
            new ObjectCache_Environment(),
            new DbCache_Environment(),
            new Cdn_Environment(),
        );
    }

    // Rules order that .htaccess should follow
    public static function w3tc_get_rules_order(): array
    {
        return array(
            W3TC_MARKER_BEGIN_BROWSERCACHE_NO404WP => 0,
            W3TC_MARKER_BEGIN_WORDPRESS => 0,
            W3TC_MARKER_END_MINIFY_CORE => strlen(W3TC_MARKER_END_MINIFY_CORE) + 1,
            W3TC_MARKER_END_BROWSERCACHE_CACHE => strlen(W3TC_MARKER_END_BROWSERCACHE_CACHE) + 1,
            W3TC_MARKER_END_PGCACHE_CACHE => strlen(W3TC_MARKER_END_PGCACHE_CACHE) + 1,
            W3TC_MARKER_END_MINIFY_CACHE => strlen(W3TC_MARKER_END_MINIFY_CACHE) + 1
        );
    }

    /**
     * Creates missing files, writes Apache rules.
     */
    function w3tc_fix_environment()
    {
        try
        {
            // Access W3TC config
            $config = Dispatcher::config();
            $exs = new Util_Environment_Exceptions();

            foreach ($this->w3tc_get_handlers() as $handler)
            {
                // Get handler rewrite rules
                $blocks = $handler->get_required_rules($config);

                // Some handlers do not require any rule, thus returning null
                if (!is_null($blocks))
                {
                    // A handler may need multiple configs in different paths
                    foreach ($blocks as $block)
                    {
                        // Find block delimiters
                        preg_match('/^(?<delimiter># BEGIN .*?\n)/mi', $block['content'], $start);
                        preg_match('/^(?<delimiter># END .*?\n)/mi', $block['content'], $end);

                        // Clean up delimiters and block contents
                        $start = trim($start['delimiter']);
                        $end = trim($end['delimiter']);
                        $content = preg_replace('/^\s*AddType\s+.*$[\r\n]?/mi', '', $block['content']);
                        $content = preg_replace('/<IfModule mod_mime\.c>\s+?<\/IfModule>\s+?/mi', '', $content);

                        // Write rules to disk, given delimiters and rules order
                        Util_Rule::add_rules(
                            $exs,
                            $block['filename'],
                            $content,
                            $start,
                            $end,
                            self::w3tc_get_rules_order(),
                            true
                        );
                    }
                }
            }
        } catch (Util_Environment_Exceptions $e)
        {
            WP_CLI::error(__('Environment adjustment failed with error', 'sqrd-apiscp-toolbox'),
                $e->getMessage());
        }

        WP_CLI::success(__('Environment adjusted.', 'sqrd-apiscp-toolbox'));
    }

    /**
     * Enables W3TC page cache.
     */
    function w3tc_enable_page_cache()
    {
        $this->w3tc_set_option('pgcache.enabled', true);
    }

    /**
     * Disables W3TC rewrite rules mismatch alert.
     */
    function w3tc_disable_rules_check()
    {
        $this->w3tc_set_option('config.check', false);
    }

    /**
     * Disables W3TC rewrite rules mismatch alert.
     */
    function w3tc_decline_tracking()
    {
        $this->w3tc_set_option('license.community_terms', 'decline');
        $this->w3tc_set_option('common.track_usage', false);
    }

    /**
     * Set value for W3TC config option.
     */
    protected function w3tc_set_option($name, $value)
    {
        try
        {
            // Access W3TC config
            $config = Dispatcher::config();

            $config->set($name, $value);
            $config->save();

            WP_CLI::success(__('Option updated successfully.', 'sqrd-apiscp-toolbox'));
        } catch (\Exception $e)
        {
            WP_CLI::error(__('Option value update failed.', 'sqrd-apiscp-toolbox'));
        }
    }
}

if (method_exists('\WP_CLI', 'add_command'))
{
    WP_CLI::add_command('apiscp', '\ApisCP\Toolbox\Command');
}
else
{
    // backward compatibility
    WP_CLI::addCommand('apiscp', '\ApisCP\Toolbox\Command');
}
