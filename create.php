<?php
include 'db.php';

$preselectedGame = trim($_GET['game'] ?? '');
$gameKey = strtolower(preg_replace('/[^a-z0-9]+/', '', $preselectedGame));
$gamePresets = get_vault_game_presets();
$preset = $gamePresets[$gameKey] ?? null;
$errors = [];
$formData = [
    'Game_Title' => $preset['title'] ?? $preselectedGame,
    'Platform' => $preset['platform'] ?? '',
    'Creator' => $preset['creator'] ?? '',
    'Acquisition_Date' => '',
    'Current_Rank' => '',
    'Prime_Rank' => '',
    'Rating' => 3,
    'Hours_Played' => 0,
    'Progress_Status' => 'Not Started',
    'Personal_Notes' => '',
];

if (isset($_POST['submit'])) {
    require_csrf_token($_POST['csrf_token'] ?? '');
    list($formData, $errors) = validate_vault_payload($_POST, $preset['title'] ?? $preselectedGame);

    if (!$errors) {
        try {
            $acqDate = $formData['Acquisition_Date'] !== '' ? $formData['Acquisition_Date'] : null;

            $stmt = $conn->prepare("INSERT INTO GameCollectionDiary (Game_Title, Platform, Creator, Acquisition_Date, Current_Rank, Prime_Rank, Rating, Hours_Played, Progress_Status, Personal_Notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "ssssssiiss",
                $formData['Game_Title'],
                $formData['Platform'],
                $formData['Creator'],
                $acqDate,
                $formData['Current_Rank'],
                $formData['Prime_Rank'],
                $formData['Rating'],
                $formData['Hours_Played'],
                $formData['Progress_Status'],
                $formData['Personal_Notes']
            );
            execute_or_fail($stmt, 'The vault could not be created right now.');
            $newId = $conn->insert_id;
            $stmt->close();

            list(, $uploadError) = save_vault_image_upload($_FILES['Game_Image'] ?? null, $newId);
            if ($uploadError) {
                redirect_with_flash('edit.php?id=' . $newId, 'error', 'Vault created, but the image was not saved. ' . $uploadError);
            }

            redirect_with_flash('view.php?id=' . $newId, 'success', 'Vault created successfully.');
        } catch (Throwable $exception) {
            if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
            $errors[] = 'The vault could not be created right now.';
        }
    }
}

$pageTitle  = 'Create Vault | Arcadia Vault';
$activePage = '';
include 'header.php';
?>
<main class="shell">
    <div class="page-hdr">
        <span class="page-kicker">Create selected vault</span>
        <h1><?= $preselectedGame !== '' ? e($preset['title'] ?? $preselectedGame) . ' <span>Vault</span>' : 'Create <span>Vault</span>' ?></h1>
        <p><?= $preselectedGame !== '' ? 'You selected one of the fixed game vaults. Add the data for that game below.' : 'Create a vault profile for a selected game.' ?></p>
    </div>
    <?php if ($errors): ?>
    <div class="form-alert" role="alert">
        <strong>We still need a few details before saving this vault.</strong>
        <ul class="form-alert-list">
            <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <div class="form-layout">
        <aside class="form-sticky">
            <div class="fsb">
                <div class="fsb-label">Selected flow</div>
                <h3>Vault setup</h3>
                <div class="fstep"><div class="fstep-n">1</div><div><strong>Selected game</strong><span><?= $preselectedGame !== '' ? e($preset['title'] ?? $preselectedGame) : 'Choose the title for this vault.' ?></span></div></div>
                <div class="fstep"><div class="fstep-n">2</div><div><strong>Add the data</strong><span>Ranks, hours, status, rating, and notes.</span></div></div>
                <div class="fstep"><div class="fstep-n">3</div><div><strong>Open the vault</strong><span>Save and move into the game vault page.</span></div></div>
            </div>
        </aside>
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="fsec">
                    <div class="fsec-label">Game identity</div>
                    <div class="fgrid">
                        <div class="field full">
                            <label for="Game_Title">Game title</label>
                            <input id="Game_Title" type="text" name="Game_Title" value="<?= e($formData['Game_Title']) ?>" <?= $preselectedGame !== '' ? 'readonly' : '' ?> required>
                        </div>
                        <div class="field">
                            <label for="Platform">Platform</label>
                            <input id="Platform" type="text" name="Platform" value="<?= e($formData['Platform']) ?>" placeholder="PC, Mobile, Console" required>
                        </div>
                        <div class="field">
                            <label for="Creator">Creator or studio</label>
                            <input id="Creator" type="text" name="Creator" value="<?= e($formData['Creator']) ?>" placeholder="Studio or publisher" required>
                        </div>
                        <div class="field">
                            <label for="Acquisition_Date">Date added</label>
                            <input id="Acquisition_Date" type="date" name="Acquisition_Date" value="<?= e($formData['Acquisition_Date']) ?>">
                        </div>
                        <div class="field full">
                            <label for="Game_Image">Game picture or logo</label>
                            <input id="Game_Image" type="file" name="Game_Image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <span class="fhint">Optional. Upload a cover, logo, or artwork for this game vault.</span>
                        </div>
                    </div>
                </div>

                <div class="fsec">
                    <div class="fsec-label">Game data</div>
                    <div class="fgrid">
                        <div class="field">
                            <label for="Current_Rank">Current rank</label>
                            <input id="Current_Rank" type="text" name="Current_Rank" value="<?= e($formData['Current_Rank']) ?>" placeholder="Current standing" required>
                        </div>
                        <div class="field">
                            <label for="Prime_Rank">Prime rank</label>
                            <input id="Prime_Rank" type="text" name="Prime_Rank" value="<?= e($formData['Prime_Rank']) ?>" placeholder="Best result" required>
                        </div>
                        <div class="field">
                            <label for="Rating">Personal rating</label>
                            <select id="Rating" name="Rating">
                                <option value="1" <?= (int) $formData['Rating'] === 1 ? 'selected' : '' ?>>1 star</option>
                                <option value="2" <?= (int) $formData['Rating'] === 2 ? 'selected' : '' ?>>2 stars</option>
                                <option value="3" <?= (int) $formData['Rating'] === 3 ? 'selected' : '' ?>>3 stars</option>
                                <option value="4" <?= (int) $formData['Rating'] === 4 ? 'selected' : '' ?>>4 stars</option>
                                <option value="5" <?= (int) $formData['Rating'] === 5 ? 'selected' : '' ?>>5 stars</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="fsec">
                    <div class="fsec-label">Progress and notes</div>
                    <div class="fgrid">
                        <div class="field">
                            <label for="Hours_Played">Hours played</label>
                            <input id="Hours_Played" type="number" name="Hours_Played" min="0" value="<?= e($formData['Hours_Played']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="Progress_Status">Status</label>
                            <select id="Progress_Status" name="Progress_Status">
                                <option value="Not Started" <?= $formData['Progress_Status'] === 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                                <option value="In Progress" <?= $formData['Progress_Status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed" <?= $formData['Progress_Status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        <div class="field full">
                            <label for="Personal_Notes">Personal notes</label>
                            <textarea id="Personal_Notes" name="Personal_Notes" placeholder="Add any notes for this game vault."><?= e($formData['Personal_Notes']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-footer">
                    <button class="btn btn-neon" type="submit" name="submit">Save vault</button>
                    <a class="btn btn-ghost" href="collection.php">Back to vaults</a>
                </div>
            </form>
        </div>
    </div>
</main>
<?php include 'footer.php'; ?>
