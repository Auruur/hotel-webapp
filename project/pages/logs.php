<?php
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Administrador') {
    page_redirect('home');
}

// número de logs visibles para la paginación
$logs_per_page = 10;
$current_page = isset($_SESSION['log_page']) ? $_SESSION['log_page'] : 1;

if (isset($_POST['prev'])) {
    $current_page = max(1, $current_page - 1);
} elseif (isset($_POST['next'])) {
    $total_logs = get_logs_count();
    $total_pages = ceil($total_logs / $logs_per_page);
    $current_page = min($total_pages, $current_page + 1);
}

$_SESSION['log_page'] = $current_page;

// cálculo para obtener los logs de la siguiente página
$offset = ($current_page - 1) * $logs_per_page;
$logs = get_logs($logs_per_page, $offset);
?>

<div class="pagination-container">
    <table>
        <thead>
            <tr>
                <th>Fecha y Hora</th>
                <th>Descripción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo $log['fecha']; ?></td>
                    <td><?php echo $log['descripcion']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination-controls">
        <form method="post" action="index.php?page=logs">
            <button type="submit" name="prev" <?php if ($current_page <= 1) echo 'disabled'; ?>>&laquo; Anterior</button>
            <span>Pagina <?php echo $current_page; ?> </span>
            <button type="submit" name="next" <?php if (count($logs) < $logs_per_page) echo 'disabled'; ?>>Siguiente &raquo;</button>
        </form>
    </div>
</div>
