<?php
require_once 'config/database.php';
require_once 'config/Logger.php';

// Iniciar la sesión para mensajes de estado
session_start();

// Obtener la conexión a la base de datos
$conn = getConnection();
$logger = Logger::getInstance();

// Procesar la acción de truncar la base de datos
if (isset($_POST['truncate_db'])) {
    if (truncateDatabase()) {
        $_SESSION['message'] = "Base de datos reiniciada correctamente.";
        $logger->info("Base de datos reiniciada por el usuario");
    } else {
        $_SESSION['error'] = "Error al reiniciar la base de datos.";
        $logger->error("Error al intentar reiniciar la base de datos");
    }
    header('Location: index.php');
    exit;
}

// Procesar la acción de exportar a JSON
if (isset($_GET['export'])) {
    $logger->info("Iniciando exportación de tareas a JSON");
    
    $tasks = [];
    $result = $conn->query("SELECT * FROM tasks");
    
    if ($result === FALSE) {
        $logger->error("Error al obtener tareas para exportación: " . $conn->error);
        $_SESSION['error'] = "Error al exportar las tareas";
        header('Location: index.php');
        exit;
    }
    
    while ($task = $result->fetch_assoc()) {
        // Obtener subtareas
        $subtasks = [];
        $subtasksResult = $conn->query("SELECT * FROM subtasks WHERE task_id = " . $task['id']);
        if ($subtasksResult === FALSE) {
            $logger->error("Error al obtener subtareas para exportación de la tarea {$task['id']}: " . $conn->error);
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
            $logger->error("Error al obtener etiquetas para exportación de la tarea {$task['id']}: " . $conn->error);
            continue;
        }
        while ($tag = $tagsResult->fetch_assoc()) {
            $tags[] = $tag['name'];
        }
        $task['tags'] = $tags;
        
        $tasks[] = $task;
    }
    
    $logger->info("Exportación de tareas completada. Total de tareas exportadas: " . count($tasks));
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="tasks.json"');
    echo json_encode($tasks, JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tareas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .task-overdue {
            background-color: #ffe6e6 !important;
        }
        .task-due-soon {
            background-color: #fff3cd !important;
        }
        .completed {
            text-decoration: line-through;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Gestión de Tareas</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal">
                    <i class="fas fa-plus"></i> Nueva Tarea
                </button>
                <a href="index.php?export=1" class="btn btn-success">
                    <i class="fas fa-file-export"></i> Exportar JSON
                </a>
                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas reiniciar la base de datos? Esta acción no se puede deshacer.');">
                    <button type="submit" name="truncate_db" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Reiniciar BD
                    </button>
                </form>
            </div>
        </div>

        <!-- Filtro de etiquetas -->
        <div class="row mb-4">
            <div class="col">
                <div class="btn-group" role="group" aria-label="Filtros de etiquetas">
                    <button type="button" class="btn btn-outline-secondary active" onclick="filterByTag('all')">
                        Todas las tareas
                    </button>
                    <div id="tagFilters">
                        <!-- Los filtros de etiquetas se cargarán dinámicamente -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Aquí irá el contenido de las tareas -->
        <div id="taskList">
            <!-- El contenido se cargará dinámicamente con JavaScript -->
        </div>
    </div>

    <!-- Modal para crear/editar tareas -->
    <div class="modal fade" id="taskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Tarea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="taskForm">
                        <input type="hidden" id="taskId" name="taskId">
                        <div class="mb-3">
                            <label for="title" class="form-label">Título</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="dueDate" class="form-label">Fecha de vencimiento</label>
                            <input type="datetime-local" class="form-control" id="dueDate" name="dueDate">
                        </div>
                        <div class="mb-3">
                            <label for="tags" class="form-label">Etiquetas (separadas por comas)</label>
                            <input type="text" class="form-control" id="tags" name="tags">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subtareas</label>
                            <div id="subtasksList">
                                <!-- Las subtareas se agregarán aquí dinámicamente -->
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addSubtaskField()">
                                <i class="fas fa-plus"></i> Agregar Subtarea
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="saveTask()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentFilter = 'all';
        let allTasks = [];

        // Función para cargar las tareas
        function loadTasks() {
            fetch('api/tasks.php')
                .then(response => response.json())
                .then(tasks => {
                    allTasks = tasks; // Guardar todas las tareas
                    updateTagFilters(tasks); // Actualizar los filtros de etiquetas
                    displayTasks(tasks); // Mostrar las tareas según el filtro actual
                });
        }

        // Función para actualizar los filtros de etiquetas
        function updateTagFilters(tasks) {
            const tagFilters = document.getElementById('tagFilters');
            const uniqueTags = new Set();
            
            // Recopilar todas las etiquetas únicas
            tasks.forEach(task => {
                task.tags.forEach(tag => uniqueTags.add(tag));
            });

            // Crear los botones de filtro
            tagFilters.innerHTML = Array.from(uniqueTags)
                .map(tag => `
                    <button type="button" 
                            class="btn btn-outline-secondary ${currentFilter === tag ? 'active' : ''}"
                            onclick="filterByTag('${tag}')">
                        ${tag}
                    </button>
                `).join('');
        }

        // Función para filtrar por etiqueta
        function filterByTag(tag) {
            currentFilter = tag;
            
            // Actualizar estado activo de los botones
            document.querySelectorAll('.btn-group .btn').forEach(btn => {
                btn.classList.remove('active');
                if ((tag === 'all' && btn.textContent.trim() === 'Todas las tareas') ||
                    btn.textContent.trim() === tag) {
                    btn.classList.add('active');
                }
            });

            // Filtrar y mostrar las tareas
            const filteredTasks = tag === 'all' 
                ? allTasks 
                : allTasks.filter(task => task.tags.includes(tag));
            
            displayTasks(filteredTasks);
        }

        // Función para mostrar las tareas
        function displayTasks(tasks) {
            const taskList = document.getElementById('taskList');
            taskList.innerHTML = '';
            
            tasks.forEach(task => {
                const dueDate = new Date(task.due_date);
                const now = new Date();
                const timeDiff = dueDate - now;
                const hoursLeft = timeDiff / (1000 * 60 * 60);
                
                let taskClass = '';
                if (task.completed) {
                    taskClass = 'completed';
                } else if (timeDiff < 0) {
                    taskClass = 'task-overdue';
                } else if (hoursLeft <= 24) {
                    taskClass = 'task-due-soon';
                }
                
                const taskElement = document.createElement('div');
                taskElement.className = `card mb-3 ${taskClass}`;
                
                let subtasksHtml = '';
                if (task.subtasks.length > 0) {
                    subtasksHtml = `
                        <div class="mt-2">
                            <h6>Subtareas:</h6>
                            <ul class="list-unstyled">
                                ${task.subtasks.map(subtask => `
                                    <li>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   ${subtask.completed ? 'checked' : ''}
                                                   onchange="toggleSubtask(${subtask.id}, this.checked)">
                                            <label class="form-check-label ${subtask.completed ? 'completed' : ''}">
                                                ${subtask.title}
                                            </label>
                                        </div>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    `;
                }
                
                let tagsHtml = '';
                if (task.tags.length > 0) {
                    tagsHtml = `
                        <div class="mt-2">
                            ${task.tags.map(tag => `
                                <button type="button" 
                                        class="badge bg-secondary border-0 me-1" 
                                        onclick="filterByTag('${tag}')"
                                        style="cursor: pointer;">
                                    ${tag}
                                </button>
                            `).join('')}
                        </div>
                    `;
                }
                
                taskElement.innerHTML = `
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       ${task.completed ? 'checked' : ''}
                                       onchange="toggleTask(${task.id}, this.checked)">
                                <label class="form-check-label ${task.completed ? 'completed' : ''}">
                                    <h5 class="card-title mb-0">${task.title}</h5>
                                </label>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-primary me-1" 
                                        onclick="editTask(${task.id})"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Editar tarea"
                                        aria-label="Editar tarea">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="deleteTask(${task.id})"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Eliminar tarea"
                                        aria-label="Eliminar tarea">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        ${task.description ? `<p class="card-text mt-2">${task.description}</p>` : ''}
                        ${task.due_date ? `
                            <p class="card-text">
                                <small class="text-muted">
                                    Vence: ${new Date(task.due_date).toLocaleString()}
                                </small>
                            </p>
                        ` : ''}
                        ${subtasksHtml}
                        ${tagsHtml}
                    </div>
                `;
                
                taskList.appendChild(taskElement);
            });

            // Inicializar tooltips
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
        }

        // Función para agregar campo de subtarea
        function addSubtaskField() {
            const subtasksList = document.getElementById('subtasksList');
            const subtaskDiv = document.createElement('div');
            subtaskDiv.className = 'input-group mb-2';
            subtaskDiv.innerHTML = `
                <input type="text" class="form-control" placeholder="Título de la subtarea" required>
                <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            subtasksList.appendChild(subtaskDiv);
        }

        // Función para guardar una tarea
        function saveTask() {
            const form = document.getElementById('taskForm');
            const taskData = {
                title: form.title.value,
                description: form.description.value,
                dueDate: form.dueDate.value,
                tags: form.tags.value,
                subtasks: []
            };

            if (form.taskId.value) {
                taskData.id = parseInt(form.taskId.value);
            }

            // Recoger subtareas
            document.querySelectorAll('#subtasksList .input-group').forEach(subtaskElement => {
                const title = subtaskElement.querySelector('input').value;
                if (title.trim()) {
                    taskData.subtasks.push({ title });
                }
            });

            fetch('api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(taskData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('taskModal'));
                    modal.hide();
                    loadTasks();
                    form.reset();
                    document.getElementById('subtasksList').innerHTML = '';
                } else {
                    alert('Error al guardar la tarea');
                }
            });
        }

        // Función para editar una tarea
        function editTask(taskId) {
            fetch('api/tasks.php')
                .then(response => response.json())
                .then(tasks => {
                    const task = tasks.find(t => t.id === taskId);
                    if (task) {
                        const form = document.getElementById('taskForm');
                        form.taskId.value = task.id;
                        form.title.value = task.title;
                        form.description.value = task.description || '';
                        form.dueDate.value = task.due_date ? task.due_date.slice(0, 16) : '';
                        form.tags.value = task.tags.join(', ');

                        const subtasksList = document.getElementById('subtasksList');
                        subtasksList.innerHTML = '';
                        task.subtasks.forEach(subtask => {
                            const subtaskDiv = document.createElement('div');
                            subtaskDiv.className = 'input-group mb-2';
                            subtaskDiv.innerHTML = `
                                <input type="text" class="form-control" value="${subtask.title}" required>
                                <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()">
                                    <i class="fas fa-times"></i>
                                </button>
                            `;
                            subtasksList.appendChild(subtaskDiv);
                        });

                        const modal = new bootstrap.Modal(document.getElementById('taskModal'));
                        modal.show();
                    }
                });
        }

        // Función para eliminar una tarea
        function deleteTask(taskId) {
            if (confirm('¿Estás seguro de que deseas eliminar esta tarea?')) {
                fetch('api/tasks.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: taskId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadTasks();
                    } else {
                        alert('Error al eliminar la tarea');
                    }
                });
            }
        }

        // Función para marcar/desmarcar una tarea como completada
        function toggleTask(taskId, completed) {
            fetch('api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'toggle_task',
                    id: taskId,
                    completed: completed
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadTasks();
                }
            });
        }

        // Función para marcar/desmarcar una subtarea como completada
        function toggleSubtask(subtaskId, completed) {
            fetch('api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'toggle_subtask',
                    id: subtaskId,
                    completed: completed
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadTasks();
                }
            });
        }

        // Limpiar el formulario cuando se abre el modal para una nueva tarea
        document.getElementById('taskModal').addEventListener('show.bs.modal', function(event) {
            if (!event.relatedTarget.hasAttribute('data-task-id')) {
                const form = document.getElementById('taskForm');
                form.reset();
                form.taskId.value = '';
                document.getElementById('subtasksList').innerHTML = '';
            }
        });

        // Inicializar tooltips al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            loadTasks();
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
        });
    </script>
</body>
</html> 