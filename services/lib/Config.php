<?php

namespace Service;

define('APPLICATION_ENV', 'production');  // This should either be 'staging','production', or 'dev'
date_default_timezone_set('America/New_York');


/**
 * This file is responsible for configuring the runtime environment for the services present in this application*
 * This file parses each necessarry .ini file and defines sets constants.
 */
class Config
{
    public static function getConfig()
    {
        $globalConfig = parse_ini_file('config/config.global.ini', true);

        switch (APPLICATION_ENV) {
            case 'dev':
                $config = Config::mergeConfigs(parse_ini_file('config/config.dev.ini', true), $globalConfig);
                break;
            case 'staging':
                $config = Config::mergeConfigs(parse_ini_file('config/config.staging.ini', true), $globalConfig);
                break;
            case 'production':
                $config = Config::mergeConfigs(parse_ini_file('config/config.production.ini', true), $globalConfig);
                break;
            default:
                throw new \Exception (
                    "No environment variable set, or the variable was set incorrectly.
                    Must be production, staging, or dev");
        }

        return $config;
    }

    private static function mergeConfigs($local, $global)
    {
        $merged = array();
        // Loop through each local file and look for arrays to merge.
        foreach ($local AS $key => $value) {
            if (isset($global[$key])) {
                $merged[$key] = array_merge($global[$key], $local[$key]);
            } else {
                $merged[$key] = $local[$key];
            }
        }
        // Loop through each global file and make sure none are getting left behind.
        foreach ($global AS $key => $value) {
            if (!isset($merged[$key])) {
                $merged[$key] = $global[$key];
            }
        }

        return $merged;
    }

}