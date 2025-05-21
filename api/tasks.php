<?php
require_once '../config/database.php';
require_once '../config/Logger.php';

header('Content-Type: application/json');

$conn = getConnection();
$logger = Logger::getInstance();

// Función para obtener todas las tareas
function getTasks() {
    global $conn, $logger;
    $tasks = [];
    
    $result = $conn->query("SELECT * FROM tasks ORDER BY created_at DESC");
    
    if ($result === FALSE) {
        $logger->error("Error al obtener las tareas: " . $conn->error);
        return [];
    }
    
    $logger->info("Consultando lista de tareas");
    
    while ($task = $result->fetch_assoc()) {
        // Obtener subtareas
        $subtasks = [];
        $subtasksResult = $conn->query("SELECT * FROM subtasks WHERE task_id = " . $task['id']);
        if ($subtasksResult === FALSE) {
            $logger->error("Error al obtener subtareas para la tarea {$task['id']}: " . $conn->error);
            continue;
        }
        while ($subtask = $subtasksResult->fetch_assoc()) {
            $subtasks[] = $subtask;
        }
        $task['subtasks'] = $subtasks;
        
        // Obtener etiquetas
        $tags = [];
        $tagsResult = $conn->query("SELECT tags.name FROM tags 
                                   JOIN task_tags ON tags.id = task_tags.tag_id 
                                   WHERE task_tags.task_id = " . $task['id']);
        if ($tagsResult === FALSE) {
            $logger->error("Error al obtener etiquetas para la tarea {$task['id']}: " . $conn->error);
            continue;
        }
        while ($tag = $tagsResult->fetch_assoc()) {
            $tags[] = $tag['name'];
        }
        $task['tags'] = $tags;
        
        $tasks[] = $task;
    }
    
    return $tasks;
}

// Función para crear o actualizar una tarea
function saveTask($data) {
    global $conn, $logger;
    
    $title = $conn->real_escape_string($data['title']);
    $description = $conn->real_escape_string($data['description'] ?? '');
    $dueDate = $data['dueDate'] ? "'" . $conn->real_escape_string($data['dueDate']) . "'" : "NULL";
    
    if (isset($data['id'])) {
        // Actualizar tarea existente
        $id = (int)$data['id'];
        $sql = "UPDATE tasks SET 
                title = '$title',
                description = '$description',
                due_date = $dueDate
                WHERE id = $id";
        
        if ($conn->query($sql)) {
            $logger->info("Tarea #$id actualizada: $title");
            
            // Eliminar subtareas existentes
            if ($conn->query("DELETE FROM subtasks WHERE task_id = $id") === FALSE) {
                $logger->error("Error al eliminar subtareas antiguas de la tarea #$id: " . $conn->error);
                return false;
            }
            // Eliminar relaciones de etiquetas existentes
            if ($conn->query("DELETE FROM task_tags WHERE task_id = $id") === FALSE) {
                $logger->error("Error al eliminar etiquetas antiguas de la tarea #$id: " . $conn->error);
                return false;
            }
        } else {
            $logger->error("Error al actualizar la tarea #$id: " . $conn->error);
            return false;
        }
        
        $taskId = $id;
    } else {
        // Crear nueva tarea
        $sql = "INSERT INTO tasks (title, description, due_date, completed) 
                VALUES ('$title', '$description', $dueDate, FALSE)";
        
        if ($conn->query($sql)) {
            $taskId = $conn->insert_id;
            $logger->info("Nueva tarea #$taskId creada: $title");
        } else {
            $logger->error("Error al crear nueva tarea: " . $conn->error);
            return false;
        }
    }
    
    // Guardar subtareas
    if (isset($data['subtasks'])) {
        foreach ($data['subtasks'] as $subtask) {
            $subtaskTitle = $conn->real_escape_string($subtask['title']);
            $subtaskDescription = $conn->real_escape_string($subtask['description'] ?? '');
            $sql = "INSERT INTO subtasks (task_id, title, description, completed) 
                    VALUES ($taskId, '$subtaskTitle', '$subtaskDescription', FALSE)";
            if ($conn->query($sql) === FALSE) {
                $logger->error("Error al crear subtarea '$subtaskTitle' para la tarea #$taskId: " . $conn->error);
            } else {
                $logger->info("Subtarea creada para la tarea #$taskId: $subtaskTitle");
            }
        }
    }
    
    // Guardar etiquetas
    if (isset($data['tags']) && !empty($data['tags'])) {
        $tags = array_map('trim', explode(',', $data['tags']));
        foreach ($tags as $tag) {
            $tagName = $conn->real_escape_string($tag);
            
            // Insertar etiqueta si no existe
            if ($conn->query("INSERT IGNORE INTO tags (name) VALUES ('$tagName')") === FALSE) {
                $logger->error("Error al crear etiqueta '$tagName': " . $conn->error);
                continue;
            }
            
            $tagId = $conn->insert_id ?: $conn->query("SELECT id FROM tags WHERE name = '$tagName'")->fetch_assoc()['id'];
            
            // Crear relación entre tarea y etiqueta
            if ($conn->query("INSERT IGNORE INTO task_tags (task_id, tag_id) VALUES ($taskId, $tagId)") === FALSE) {
                $logger->error("Error al asociar etiqueta '$tagName' con la tarea #$taskId: " . $conn->error);
            } else {
                $logger->info("Etiqueta '$tagName' asociada a la tarea #$taskId");
            }
        }
    }
    
    return true;
}

// Función para eliminar una tarea
function deleteTask($id) {
    global $conn, $logger;
    $id = (int)$id;
    
    if ($conn->query("DELETE FROM tasks WHERE id = $id")) {
        $logger->info("Tarea #$id eliminada");
        return true;
    } else {
        $logger->error("Error al eliminar la tarea #$id: " . $conn->error);
        return false;
    }
}

// Función para marcar una tarea como completada/incompleta
function toggleTaskComplete($id, $completed) {
    global $conn, $logger;
    $id = (int)$id;
    $completed = (int)$completed;
    
    if ($conn->query("UPDATE tasks SET completed = $completed WHERE id = $id")) {
        $status = $completed ? "completada" : "pendiente";
        $logger->info("Tarea #$id marcada como $status");
        return true;
    } else {
        $logger->error("Error al cambiar estado de la tarea #$id: " . $conn->error);
        return false;
    }
}

// Función para marcar una subtarea como completada/incompleta
function toggleSubtaskComplete($id, $completed) {
    global $conn, $logger;
    $id = (int)$id;
    $completed = (int)$completed;
    
    if ($conn->query("UPDATE subtasks SET completed = $completed WHERE id = $id")) {
        $status = $completed ? "completada" : "pendiente";
        $logger->info("Subtarea #$id marcada como $status");
        return true;
    } else {
        $logger->error("Error al cambiar estado de la subtarea #$id: " . $conn->error);
        return false;
    }
}

// Manejar las diferentes solicitudes HTTP
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            echo json_encode(getTasks());
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'toggle_task':
                        $success = toggleTaskComplete($data['id'], $data['completed']);
                        echo json_encode(['success' => $success]);
                        break;
                        
                    case 'toggle_subtask':
                        $success = toggleSubtaskComplete($data['id'], $data['completed']);
                        echo json_encode(['success' => $success]);
                        break;
                        
                    case 'delete':
                        $success = deleteTask($data['id']);
                        echo json_encode(['success' => $success]);
                        break;
                        
                    default:
                        $success = saveTask($data);
                        echo json_encode(['success' => $success]);
                }
            } else {
                $success = saveTask($data);
                echo json_encode(['success' => $success]);
            }
            break;
            
        default:
            $logger->warning("Método HTTP no permitido: $method");
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Exception $e) {
    $logger->error("Error en la aplicación: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close(); 