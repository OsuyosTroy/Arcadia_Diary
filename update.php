<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to_home();
}

require_csrf_token($_POST['csrf_token'] ?? '');

$id = (int) ($_POST['Entry_ID'] ?? 0);
if ($id <= 0) redirect_to_home();
list($data, $errors) = validate_vault_payload($_POST);

if ($errors) {
    redirect_with_flash('edit.php?id=' . $id, 'error', implode(' ', $errors));
}

try {
    $acqDate = $data['Acquisition_Date'] !== '' ? $data['Acquisition_Date'] : null;

    $stmt = $conn->prepare("UPDATE GameCollectionDiary SET Game_Title=?, Platform=?, Creator=?, Acquisition_Date=?, Current_Rank=?, Prime_Rank=?, Rating=?, Hours_Played=?, Progress_Status=?, Personal_Notes=? WHERE Entry_ID=?");
    $stmt->bind_param("ssssssiissi", $data['Game_Title'], $data['Platform'], $data['Creator'], $acqDate, $data['Current_Rank'], $data['Prime_Rank'], $data['Rating'], $data['Hours_Played'], $data['Progress_Status'], $data['Personal_Notes'], $id);
    execute_or_fail($stmt, 'The vault update could not be saved right now.');
    $stmt->close();

    list(, $uploadError) = save_vault_image_upload($_FILES['Game_Image'] ?? null, $id);
    if ($uploadError) {
        redirect_with_flash('edit.php?id=' . $id, 'error', 'Vault updated, but the image was not saved. ' . $uploadError);
    }

    redirect_with_flash('view.php?id=' . $id, 'success', 'Vault updated successfully.');
} catch (Throwable $exception) {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    redirect_with_flash('edit.php?id=' . $id, 'error', 'The vault update could not be saved right now.');
}
?>
