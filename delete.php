<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to_home();
}

require_csrf_token($_POST['csrf_token'] ?? '');

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id > 0) {
    try {
        $stmt = $conn->prepare("DELETE FROM GameCollectionDiary WHERE Entry_ID = ?");
        $stmt->bind_param("i", $id);
        execute_or_fail($stmt, 'The vault could not be deleted right now.');
        $deletedRows = $stmt->affected_rows;
        $stmt->close();

        if ($deletedRows > 0) {
            delete_vault_uploaded_image($id);
            redirect_with_flash('collection.php', 'success', 'Vault deleted successfully.');
        }

        redirect_with_flash('collection.php', 'error', 'That vault could not be found anymore.');
    } catch (Throwable $exception) {
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        redirect_with_flash('collection.php', 'error', 'The vault could not be deleted right now.');
    }
}

redirect_to_home();
?>
