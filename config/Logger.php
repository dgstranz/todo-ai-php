<?php
/**
 * Clase para manejar el registro de eventos en la aplicación
 */
class Logger {
    private $logFile;
    private static $instance = null;

    /**
     * Constructor privado para implementar el patrón Singleton
     */
    private function __construct() {
        $this->logFile = dirname(__DIR__) . '/logs/app.log';
        
        // Crear el directorio de logs si no existe
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
    }

    /**
     * Obtener la instancia única del logger
     * @return Logger
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra un mensaje en el archivo de log
     * @param string $message Mensaje a registrar
     * @param string $level Nivel del mensaje (INFO, ERROR, WARNING)
     * @return bool
     */
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp][$level] $message" . PHP_EOL;
        
        return file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Registra un mensaje de información
     * @param string $message
     */
    public function info($message) {
        $this->log($message, 'INFO');
    }

    /**
     * Registra un mensaje de error
     * @param string $message
     */
    public function error($message) {
        $this->log($message, 'ERROR');
    }

    /**
     * Registra un mensaje de advertencia
     * @param string $message
     */
    public function warning($message) {
        $this->log($message, 'WARNING');
    }
} 