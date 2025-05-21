<?php
/**
 * Configuración y funciones de la base de datos
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'todo_app');

/**
 * Establece la conexión con la base de datos
 * @return mysqli Objeto de conexión a la base de datos
 */
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    // Crear la base de datos si no existe
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if ($conn->query($sql) === FALSE) {
        die("Error al crear la base de datos: " . $conn->error);
    }
    
    // Seleccionar la base de datos
    if (!$conn->select_db(DB_NAME)) {
        die("Error al seleccionar la base de datos: " . $conn->error);
    }
    
    // Crear las tablas si no existen
    createTables($conn);
    
    return $conn;
}

/**
 * Crea las tablas necesarias en la base de datos
 * @param mysqli $conn Conexión a la base de datos
 */
function createTables($conn) {
    // Tabla de tareas
    $sql = "CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        due_date DATETIME,
        completed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";
    
    if ($conn->query($sql) === FALSE) {
        die("Error al crear la tabla tasks: " . $conn->error);
    }
    
    // Tabla de subtareas
    $sql = "CREATE TABLE IF NOT EXISTS subtasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        completed TINYINT(1) DEFAULT 0,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB";
    
    if ($conn->query($sql) === FALSE) {
        die("Error al crear la tabla subtasks: " . $conn->error);
    }
    
    // Tabla de etiquetas
    $sql = "CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB";
    
    if ($conn->query($sql) === FALSE) {
        die("Error al crear la tabla tags: " . $conn->error);
    }
    
    // Tabla de relación entre tareas y etiquetas
    $sql = "CREATE TABLE IF NOT EXISTS task_tags (
        task_id INT,
        tag_id INT,
        PRIMARY KEY (task_id, tag_id),
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB";
    
    if ($conn->query($sql) === FALSE) {
        die("Error al crear la tabla task_tags: " . $conn->error);
    }
}

/**
 * Trunca todas las tablas de la base de datos
 * @return bool True si se truncaron las tablas correctamente
 */
function truncateDatabase() {
    $conn = getConnection();
    
    // Desactivar temporalmente las restricciones de clave foránea
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    $tables = ['task_tags', 'tags', 'subtasks', 'tasks'];
    $success = true;
    
    foreach ($tables as $table) {
        if ($conn->query("TRUNCATE TABLE $table") === FALSE) {
            $success = false;
            break;
        }
    }
    
    // Reactivar las restricciones de clave foránea
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    $conn->close();
    return $success;
}
