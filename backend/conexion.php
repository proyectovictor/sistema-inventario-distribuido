<?php

/* Evitamos errores futuros por tipo de dato*/
declare(strict_types=1);

/* Configuraciones para la base de datos */
class DatabaseManager
{
    private array $config = [];
    private ?PDO $activeConnection = null;
    private ?string $activeServer = null;
    private ?string $lastError = null;
    private string $logFile;

    public function __construct(?string $logFilePath = null)
    {
        // Definir ruta del archivo de log (por defecto en directorio actual)
        $this->logFile = $logFilePath ?? __DIR__ . '/db_errors.log';
        
        // Cargar configuración desde variables de entorno
        $this->config = [
            'A' => [
                'host'     => getenv('DB_A_HOST') ?: 'aws-1-us-east-1.pooler.supabase.com',
                'port'     => getenv('DB_A_PORT') ?: '6543',
                'dbname'   => getenv('DB_A_DBNAME') ?: 'postgres',
                'user'     => getenv('DB_A_USER') ?: 'postgres.srxffhodlvfgerrmngmg',
                'password' => getenv('DB_A_PASSWORD') ?: 'Chettos123#',
                'sslmode'  => 'require'
            ],
            'B' => [
                'host'     => getenv('DB_B_HOST') ?: 'aws-1-us-east-1.pooler.supabase.com',
                'port'     => getenv('DB_B_PORT') ?: '6543',
                'dbname'   => getenv('DB_B_DBNAME') ?: 'postgres',
                'user'     => getenv('DB_B_USER') ?: 'postgres.wivjjmadhmsbzcytceyk',
                'password' => getenv('DB_B_PASSWORD') ?: 'Chettos456#',
                'sslmode'  => 'require'
            ],
            'local' => [  
                'host'     => getenv('DB_LOCAL_HOST') ?: 'localhost',
                'port'     => getenv('DB_LOCAL_PORT') ?: '5432',
                'dbname'   => getenv('DB_LOCAL_DBNAME') ?: 'inventario_db',
                'user'     => getenv('DB_LOCAL_USER') ?: 'postgres',
                'password' => getenv('DB_LOCAL_PASSWORD') ?: 'Chettos123',
                'sslmode'  => 'disable'
            ]
        ];
    }

    // Prueba rápidamente qué servidor está disponible sin abrir una conexión persistente.
    public function detectActiveServer(): ?string
    {
        $servers = ['A', 'B', 'local'];
        foreach ($servers as $server) {
            if ($this->testConnection($server)) {
                $this->activeServer = $server;
                return $server;
            }
        }
        return null;
    }

    //Prueba de conexión temporal a un servidor específico.
    private function testConnection(string $server): bool
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

    //Obtiene una conexión, con un respaldo automático
    public function getConnection(): ?PDO
    {
        // Reutilizar conexión activa si sigue viva
        if ($this->activeConnection !== null && $this->activeServer !== null) {
            try {
                $this->activeConnection->query('SELECT 1');
                return $this->activeConnection;
            } catch (PDOException $e) {
                // Conexión perdida, la cerramos y continuamos para crear una nueva
                $this->activeConnection = null;
                $this->activeServer = null;
                $this->logError("Conexión activa perdida en {$this->activeServer}: " . $e->getMessage());
            }
        }

        // Intentar conexión en orden: A, B, local
        $servers = ['A', 'B', 'local'];
        foreach ($servers as $server) {
            try {
                $cfg = $this->config[$server];
                $dsn = $this->buildDsn($cfg);
                $pdo = new PDO($dsn, $cfg['user'], $cfg['password']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5); // 5 segundos de timeout
                
                $this->activeConnection = $pdo;
                $this->activeServer = $server;
                $this->logError("Conectado exitosamente a {$server}");
                return $pdo;
            } catch (PDOException $e) {
                $this->lastError = $e->getMessage();
                $this->logError("Fallo conexión a {$server}: " . $e->getMessage());
                continue; // Probar siguiente servidor
            }
        }
        
        $this->logError("No se pudo conectar a ningún servidor (A, B, local)");
        return null;
    }

    //Consrtruye el Data Source Name para postgres
    private function buildDsn(array $cfg): string
    {
        return sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['dbname'],
            $cfg['sslmode']
        );
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

    //Registra errores en un archivo log
    private function logError(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        @file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}