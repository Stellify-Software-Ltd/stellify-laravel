<?php

namespace Stellify\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Stellify\Laravel\Parser\PhpFileParser;
use Stellify\Laravel\Parser\RouteParser;
use Stellify\Laravel\Parser\ConfigParser;
use Stellify\Laravel\Parser\DirectoryParser;

class ExportCommand extends Command
{
    protected $signature = 'stellify:export 
                            {--only= : Only export specific types (routes,controllers,models,config)}
                            {--exclude= : Exclude specific paths (comma-separated)}
                            {--path= : Only export files from specific path}
                            {--connection=stellify : Database connection to use}';

    protected $description = 'Export Laravel project to Stellify database format';

    private $phpParser;
    private $routeParser;
    private $configParser;
    private $directoryParser;
    private $connection;

    public function __construct()
    {
        parent::__construct();
        
        $this->phpParser = new PhpFileParser();
        $this->routeParser = new RouteParser();
        $this->configParser = new ConfigParser();
        $this->directoryParser = new DirectoryParser();
    }

    public function handle()
    {
        $this->info('Starting Stellify export...');

        // Get options
        $only = $this->option('only') ? explode(',', $this->option('only')) : null;
        $exclude = $this->option('exclude') ? explode(',', $this->option('exclude')) : [];
        $path = $this->option('path');
        $this->connection = $this->option('connection');

        // Validate database connection
        if (!$this->validateConnection()) {
            return 1;
        }

        $basePath = base_path();

        // Determine what to export
        $exportRoutes = !$only || in_array('routes', $only);
        $exportControllers = !$only || in_array('controllers', $only);
        $exportModels = !$only || in_array('models', $only);
        $exportConfig = !$only || in_array('config', $only);

        // Build paths to scan
        $pathsToScan = [];
        if ($path) {
            $pathsToScan[] = $path;
        } else {
            if ($exportControllers) $pathsToScan[] = 'app/Http/Controllers';
            if ($exportModels) $pathsToScan[] = 'app/Models';
            if (!$only) {
                $pathsToScan = array_merge($pathsToScan, [
                    'app/Http/Middleware',
                    'app/Services',
                    'app/Providers',
                ]);
            }
        }

        // Export directories first
        $this->info('Parsing directories...');
        $directories = $this->directoryParser->parseDirectories($basePath, $pathsToScan);
        $this->exportDirectories($directories);
        $this->info('Exported ' . count($directories) . ' directories');

        // Export PHP files
        $totalFiles = 0;
        $totalMethods = 0;
        $totalStatements = 0;
        $totalClauses = 0;

        foreach ($pathsToScan as $scanPath) {
            $fullPath = $basePath . '/' . $scanPath;
            
            if (!is_dir($fullPath)) {
                $this->warn("Path not found: {$scanPath}");
                continue;
            }

            $this->info("Parsing {$scanPath}...");
            
            $result = $this->phpParser->parseDirectory($fullPath, $exclude);
            
            $this->exportFiles($result['files']);
            $this->exportMethods($result['methods']);
            $this->exportStatements($result['statements']);
            $this->exportClauses($result['clauses']);

            $totalFiles += count($result['files']);
            $totalMethods += count($result['methods']);
            $totalStatements += count($result['statements']);
            $totalClauses += count($result['clauses']);
        }

        $this->info("Exported {$totalFiles} files, {$totalMethods} methods, {$totalStatements} statements, {$totalClauses} clauses");

        // Export routes
        if ($exportRoutes) {
            $this->info('Parsing routes...');
            $routes = $this->routeParser->parseRoutes();
            $this->exportRoutes($routes);
            $this->info('Exported ' . count($routes) . ' routes');
        }

        // Export config
        if ($exportConfig) {
            $this->info('Parsing config files...');
            $settings = $this->configParser->parseConfigFiles($basePath . '/config');
            $this->exportSettings($settings);
            $this->info('Exported ' . count($settings) . ' config files');
        }

        $this->info('✅ Export completed successfully!');
        
        return 0;
    }

    private function validateConnection(): bool
    {
        try {
            DB::connection($this->connection)->getPdo();
            $this->info("Connected to database: {$this->connection}");
            return true;
        } catch (\Exception $e) {
            $this->error("Failed to connect to database '{$this->connection}': {$e->getMessage()}");
            $this->error("Make sure you have configured the connection in config/database.php");
            return false;
        }
    }

    private function exportDirectories(array $directories): void
    {
        if (empty($directories)) return;

        DB::connection($this->connection)
            ->table('directories')
            ->insert(array_map(function($dir) {
                return array_merge($dir, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }, $directories));
    }

    private function exportFiles(array $files): void
    {
        if (empty($files)) return;

        DB::connection($this->connection)
            ->table('files')
            ->insert(array_map(function($file) {
                return array_merge($file, [
                    'user_id' => null,
                    'project_id' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }, $files));
    }

    private function exportMethods(array $methods): void
    {
        if (empty($methods)) return;

        DB::connection($this->connection)
            ->table('methods')
            ->insert(array_map(function($method) {
                return [
                    'uuid' => $method['uuid'],
                    'user_id' => null,
                    'project_id' => null,
                    'type' => $method['type'] ?? 'method',
                    'name' => $method['name'],
                    'description' => null,
                    'data' => json_encode($method),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }, $methods));
    }

    private function exportStatements(array $statements): void
    {
        if (empty($statements)) return;

        DB::connection($this->connection)
            ->table('statements')
            ->insert(array_map(function($stmt) {
                return [
                    'uuid' => $stmt['uuid'],
                    'user_id' => null,
                    'project_id' => null,
                    'type' => $stmt['type'] ?? null,
                    'name' => null,
                    'data' => json_encode($stmt),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }, $statements));
    }

    private function exportClauses(array $clauses): void
    {
        if (empty($clauses)) return;

        DB::connection($this->connection)
            ->table('clauses')
            ->insert(array_map(function($clause) {
                return [
                    'uuid' => $clause['uuid'],
                    'user_id' => null,
                    'project_id' => null,
                    'type' => $clause['type'] ?? null,
                    'name' => $clause['name'] ?? null,
                    'description' => null,
                    'data' => json_encode($clause),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }, $clauses));
    }

    private function exportRoutes(array $routes): void
    {
        if (empty($routes)) return;

        DB::connection($this->connection)
            ->table('routes')
            ->insert(array_map(function($route) {
                return array_merge($route, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }, $routes));
    }

    private function exportSettings(array $settings): void
    {
        if (empty($settings)) return;

        DB::connection($this->connection)
            ->table('settings')
            ->insert(array_map(function($setting) {
                return array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }, $settings));
    }
}
