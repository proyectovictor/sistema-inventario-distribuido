<?php

/* Evitamos errores futuros por tipo de dato*/
declare(strict_types=1);

date_default_timezone_set('America/Cancun');

/* Configuraciones para la base de datos */
class DatabaseManager
{
    private array $config = [];
    private ?PDO $activeConnection = null;
    private ?string $activeServer = null;
    private ?string $lastError = null;
    private string $logFile;
    private int $timeout;

    private array $serverConfig = [
        'A' => [
            'name' => 'Supabase A',
            'host' => 'aws-1-us-east-1.pooler.supabase.com',
            'port' => '6543',
            'dbname' => 'postgres',
            'user' => 'postgres.srxffhodlvfgerrmngmg',
            'password' => 'Chettos123#',
            'sslmode' => 'require'
        ],
        'B' => [
            'name' => 'Supabase B',
            'host' => 'aws-1-us-east-1.pooler.supabase.com',
            'port' => '6543',
            'dbname' => 'postgres',
            'user' => 'postgres.wivjjmadhmsbzcytceyk',
            'password' => 'Chettos456#',
            'sslmode' => 'require'
        ],
        'local' => [
            'name' => 'PostgreSQL Local',
            'host' => 'localhost',
            'port' => '5432',
            'dbname' => 'inventario_db',
            'user' => 'postgres',
            'password' => 'Chettos123',
            'sslmode' => 'disable'
        ]
    ];

    public function __construct(?string $logFilePath = null, int $timeout = 5)
    {
        $this->logFile = $logFilePath ?? __DIR__ . '/db_errors.log';
        $this->timeout = $timeout;
        $this->loadConfig();
    }

    // Carga la configuración de base de datos desde el archivo JSON
    private function loadConfig(): void
    {
        $this->config = $this->serverConfig;
        $this->logInfo("Configuración cargada exitosamente");
    }

    public function getServerConfig(string $server): ?array
    {
        return $this->config[$server] ?? null;
    }

    public function getConnection(string $server): ?PDO
    {
        if (!isset($this->config[$server])) {
            $this->lastError = "Servidor {$server} no configurado";
            $this->logError("getConnection falló en {$server}: " . $this->lastError);
            return null;
        }

        try {
            $cfg = $this->config[$server];
            $dsn = $this->buildDsn($cfg);

            $pdo = new PDO($dsn, $cfg['user'], $cfg['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_TIMEOUT, $this->timeout);
            
            $pdo->exec("SET TIME ZONE 'America/Cancun'"); // Ajusta según tu ubicación

            $this->activeConnection = $pdo;
            $this->activeServer = $server;
            $this->logInfo("Conectado a {$cfg['name']}");

            return $pdo;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $this->logError("getConnection falló en {$server}: " . $e->getMessage());
            return null;
        }
    }

    // Configura la conexión PDO
    // private function buildDsn(array $config): string
    // {
    //     return "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};sslmode={$config['sslmode']}";
    // }

    // Prueba rápidamente qué servidor está disponible sin abrir una conexión persistente.
    public function testConnection(string $server): bool
    {
        try {
            $cfg = $this->config[$server];
            $dsn = $this->buildDsn($cfg);
            $pdo = new PDO($dsn, $cfg['user'], $cfg['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->query('SELECT 1');
            
            return true;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $this->logError("TestConnection falló en {$server}: " . $e->getMessage());
            return false;
        }
    }
    
    public function diagnosticar(): array
    {
        $resultado = [];
        foreach ($this->config as $key => $cfg) {
            $inicio = microtime(true);
            $conecta = $this->testConnection($key);
            $tiempo = round((microtime(true) - $inicio) * 1000, 2);

            $resultado[$key] = [
                'nombre' => $cfg['name'],
                'host' => $cfg['host'],
                'conecta' => $conecta,
                'tiempo_ms' => $tiempo,
                'error' => $conecta ? null : $this->getLastError()
            ];
        }
        return $resultado;
    }

    //Devuelve la clave del servidor conectado
    public function getActiveServer(): ?string
    {
        return $this->activeServer;
    }

    //Devuelve ultimo error generado
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function buildDsn(array $cfg): string
    {
        return sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;connect_timeout=%d',
            $cfg['host'],
            $cfg['port'],
            $cfg['dbname'],
            $cfg['sslmode'],
            $this->timeout
        );
    }

    private function logInfo(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    //Registra errores en un archivo log
    private function logError(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf("[%s] [%s] %s", $timestamp, $level, $message) . PHP_EOL;

        try {
            $dir = dirname($this->logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log("DatabaseManager: " . $e->getMessage());
        }
    }

    public function closeConnection(): void
    {
        $this->activeConnection = null;
        $this->activeServer = null;
        $this->logInfo("Conexión cerrada");
    }
    
}
?>