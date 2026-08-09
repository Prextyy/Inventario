<?php
// Panel de Administración - Gestión de Departamentos/Áreas
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$conn = getDBConnection();
$error = '';
$success = '';

// Crear departamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name)) {
        $error = 'El nombre del departamento es requerido';
    } else {
        // Verificar si ya existe
        $sql = "SELECT id FROM areas WHERE name = '" . mysqli_real_escape_string($conn, $name) . "'";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) > 0) {
            $error = 'El departamento ya existe';
        } else {
            // Crear departamento
            $sql = "INSERT INTO areas (name, description) VALUES (
                '" . mysqli_real_escape_string($conn, $name) . "',
                '" . mysqli_real_escape_string($conn, $description) . "'
            )";
            
            if (mysqli_query($conn, $sql)) {
                $success = "✓ Departamento '$name' creado exitosamente";
            } else {
                $error = 'Error al crear departamento: ' . mysqli_error($conn);
            }
        }
    }
}

// Editar departamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    $area_id = trim($_POST['area_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($area_id) || empty($name)) {
        $error = 'ID y nombre son requeridos';
    } else {
        $sql = "UPDATE areas SET name = '" . mysqli_real_escape_string($conn, $name) . "', 
                description = '" . mysqli_real_escape_string($conn, $description) . "' 
                WHERE id = " . intval($area_id);
        
        if (mysqli_query($conn, $sql)) {
            $success = "✓ Departamento actualizado exitosamente";
        } else {
            $error = 'Error al actualizar: ' . mysqli_error($conn);
        }
    }
}

// Eliminar departamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    $area_id = trim($_POST['area_id'] ?? '');
    
    if (empty($area_id)) {
        $error = 'ID es requerido';
    } else {
        // Verificar si hay usuarios con este departamento
        $sql = "SELECT COUNT(*) as count FROM users WHERE area_id = " . intval($area_id);
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        
        if ($row['count'] > 0) {
            $error = "No se puede eliminar. Hay $row[count] usuario(s) asignados a este departamento";
        } else {
            $sql = "DELETE FROM areas WHERE id = " . intval($area_id);
            
            if (mysqli_query($conn, $sql)) {
                $success = "✓ Departamento eliminado exitosamente";
            } else {
                $error = 'Error al eliminar: ' . mysqli_error($conn);
            }
        }
    }
}

// Obtener departamentos
$sql = "SELECT a.*, COUNT(u.id) as user_count FROM areas a LEFT JOIN users u ON a.id = u.area_id GROUP BY a.id ORDER BY a.name";
$result = mysqli_query($conn, $sql);
$areas = mysqli_fetch_all($result, MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Departamentos - <?php echo SITE_NAME; ?></title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
</head>
<body>
    <?php require_once 'includes/header.php'; ?>
    
    <div class="admin-panel">
        <div class="page-header">
            <h1>Gestion de Departamentos</h1>
            <p>Configura areas de trabajo y asignaciones para los usuarios.</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="msg error"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="msg success"><?php echo e($success); ?></div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats">
            <div class="stat-card">
                <h3>Total de Departamentos</h3>
                <div class="value"><?php echo count($areas); ?></div>
            </div>
            <div class="stat-card">
                <h3>Usuarios Asignados</h3>
                <div class="value"><?php echo array_sum(array_column($areas, 'user_count')); ?></div>
            </div>
        </div>
        
        <!-- Crear Departamento -->
        <div class="form-section">
            <h3>Crear Nuevo Departamento</h3>
            <form method="POST">
                <input type="hidden" name="action" value="crear">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nombre del Departamento *</label>
                        <input type="text" id="name" name="name" required placeholder="Ej: Administración">
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label for="description">Descripción (Opcional)</label>
                        <textarea id="description" name="description" placeholder="Descripción del departamento..."></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">+ Crear Departamento</button>
            </form>
        </div>
        
        <!-- Listado de Departamentos -->
        <div>
            <h3>Departamentos Actuales</h3>
            <?php if (count($areas) > 0): ?>
                <div class="areas-grid">
                    <?php foreach ($areas as $area): ?>
                        <div class="area-card">
                            <h3><?php echo htmlspecialchars($area['name']); ?></h3>
                            <p><?php echo htmlspecialchars($area['description'] ?? '(Sin descripción)'); ?></p>
                            
                            <div class="area-stats">
                                <span class="user-count"><?php echo $area['user_count']; ?> usuario(s)</span>
                            </div>
                            
                            <div class="area-actions">
                                <button class="btn btn-primary btn-sm" onclick="editArea(<?php echo $area['id']; ?>, '<?php echo htmlspecialchars($area['name']); ?>', '<?php echo htmlspecialchars($area['description'] ?? ''); ?>')">Editar</button>
                                <form method="POST" class="area-actions">
                                    <input type="hidden" name="action" value="eliminar">
                                    <input type="hidden" name="area_id" value="<?php echo $area['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este departamento?')">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No hay departamentos creados</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal para Editar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Editar Departamento</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" id="editAreaId" name="area_id">
                
                <div class="form-group">
                    <label for="editName">Nombre</label>
                    <input type="text" id="editName" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="editDescription">Descripción</label>
                    <textarea id="editDescription" name="description"></textarea>
                </div>
                
                <div class="area-actions section-top">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php require_once 'includes/footer.php'; ?>
    
    <script>
        function editArea(id, name, description) {
            document.getElementById('editAreaId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editDescription').value = description;
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>
