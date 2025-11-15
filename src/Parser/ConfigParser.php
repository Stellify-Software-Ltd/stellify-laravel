<?php

namespace Stellify\Laravel\Parser;

use Illuminate\Support\Str;

class ConfigParser
{
    /**
     * Parse Laravel config files and return data for settings table
     *
     * @param string $configPath
     * @return array
     */
    public function parseConfigFiles(string $configPath): array
    {
        $settings = [];
        
        if (!is_dir($configPath)) {
            return $settings;
        }

        $configFiles = glob($configPath . '/*.php');

        foreach ($configFiles as $configFile) {
            $configName = basename($configFile, '.php');
            
            try {
                $config = include $configFile;
                
                if (is_array($config)) {
                    $settings[] = [
                        'project_id' => null,
                        'name' => $configName,
                        'active_domain' => '',
                        'data' => json_encode($this->flattenConfig($config, $configName))
                    ];
                }
            } catch (\Exception $e) {
                // Skip invalid config files
                echo "Error parsing config {$configName}: {$e->getMessage()}\n";
            }
        }

        return $settings;
    }

    /**
     * Flatten nested config arrays with dot notation
     * e.g., ['database' => ['connections' => ['mysql' => [...]]]]
     * becomes ['database.connections.mysql' => [...]]
     */
    private function flattenConfig(array $config, string $prefix = ''): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                // Check if it's an associative array or sequential
                if ($this->isAssoc($value)) {
                    $result = array_merge($result, $this->flattenConfig($value, $newKey));
                } else {
                    // Keep sequential arrays as-is
                    $result[$newKey] = $value;
                }
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if array is associative
     */
    private function isAssoc(array $array): bool
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
