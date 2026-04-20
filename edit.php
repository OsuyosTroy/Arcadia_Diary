<?php
include 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) redirect_to_home();

$stmt = $conn->prepare("SELECT * FROM GameCollectionDiary WHERE Entry_ID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) redirect_to_home();

$personalNotes = normalize_personal_notes($row['Personal_Notes'] ?? '');
$acqDate = $row['Acquisition_Date'] ?? '';
$daysSince = days_since($acqDate);
$currentImage = get_vault_image($row['Game_Title'], $row['Entry_ID']);
$flashMessages = get_flash_messages();

$pageTitle  = 'Edit: ' . $row['Game_Title'] . ' | Arcadia Vault';
$activePage = '';
include 'header.php';
?>
<main class="shell">
    <div class="page-hdr">
        <span class="page-kicker">Update selected vault</span>
        <h1><?= e(vault_upper($row['Game_Title'])) ?> <span>Vault</span></h1>
        <p>Update the selected game vault whenever the rank changes, hours grow, or you want to keep new notes.</p>
    </div>
    <?php if ($flashMessages): ?>
    <div class="page-notice-stack">
        <?php foreach ($flashMessages as $message): ?>
        <div class="page-notice page-notice-<?= e($message['type'] === 'success' ? 'success' : 'error') ?>" role="alert">
            <strong><?= $message['type'] === 'success' ? 'Update saved' : "We couldn't save that update yet" ?></strong>
            <p><?= e($message['message']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="form-layout">
        <aside class="form-sticky">
            <div class="fsb">
                <div class="fsb-label">Current snapshot</div>
                <h3>Live record</h3>
                <div class="fmeta"><span class="fmeta-k">Vault</span><span>#<?= e($row['Entry_ID']) ?></span></div>
                <div class="fmeta"><span class="fmeta-k">Game</span><span><?= e($row['Game_Title']) ?></span></div>
                <div class="fmeta"><span class="fmeta-k">Platform</span><span><?= e($row['Platform']) ?></span></div>
                <div class="fmeta"><span class="fmeta-k">Status</span><span><?= e(normalize_status($row['Progress_Status'])) ?></span></div>
                <div class="fmeta"><span class="fmeta-k">Rating</span><span class="gold"><?= e(render_stars($row['Rating'] ?? 3)) ?></span></div>
                <div class="fmeta"><span class="fmeta-k">Prime rank</span><span class="gold"><?= e($row['Prime_Rank']) ?></span></div>
                <div class="fmeta"><span class="fmeta-k">Hours</span><span><?= e($row['Hours_Played']) ?>h</span></div>
                <?php if ($acqDate && $acqDate !== '0000-00-00'): ?>
                <div class="fmeta"><span class="fmeta-k">Date added</span><span><?= e(format_date($acqDate)) ?></span></div>
                <?php if ($daysSince !== null): ?>
                <div class="fmeta"><span class="fmeta-k">Days in vault</span><span class="neon"><?= e($daysSince) ?></span></div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="fsb">
                <div class="fsb-label">Quick action</div>
                <a class="btn btn-ghost" style="width:100%;" href="view.php?id=<?= e($row['Entry_ID']) ?>">Back to vault</a>
            </div>
        </aside>
        <div class="form-card">
            <form method="POST" action="update.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="Entry_ID" value="<?= e($row['Entry_ID']) ?>">
                <div class="fsec">
                    <div class="fsec-label">Game identity</div>
                    <div class="fgrid">
                        <div class="field full">
                            <label for="Game_Title">Game title</label>
                            <input id="Game_Title" type="text" name="Game_Title" value="<?= e($row['Game_Title']) ?>" readonly required>
                        </div>
                        <div class="field">
                            <label for="Platform">Platform</label>
                            <input id="Platform" type="text" name="Platform" value="<?= e($row['Platform']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="Creator">Creator or studio</label>
                            <input id="Creator" type="text" name="Creator" value="<?= e($row['Creator']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="Acquisition_Date">Date added</label>
                            <input id="Acquisition_Date" type="date" name="Acquisition_Date" value="<?= e(($acqDate && $acqDate !== '0000-00-00') ? $acqDate : '') ?>">
                        </div>
                        <div class="field full">
                            <label for="Game_Image">Game picture or logo</label>
                            <input id="Game_Image" type="file" name="Game_Image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <span class="fhint"><?= $currentImage ? 'Uploading a new image will replace the current vault image.' : 'Optional. Upload a cover, logo, or artwork for this game vault.' ?></span>
                        </div>
                    </div>
                </div>

                <div class="fsec">
                    <div class="fsec-label">Competitive profile</div>
                    <div class="fgrid">
                        <div class="field">
                            <label for="Current_Rank">Current rank</label>
                            <input id="Current_Rank" type="text" name="Current_Rank" value="<?= e($row['Current_Rank']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="Prime_Rank">Prime rank</label>
                            <input id="Prime_Rank" type="text" name="Prime_Rank" value="<?= e($row['Prime_Rank']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="Rating">Personal rating</label>
                            <select id="Rating" name="Rating">
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                <option value="<?= $r ?>" <?= (int)($row['Rating'] ?? 3) === $r ? 'selected' : '' ?>><?= $r ?> star<?= $r > 1 ? 's' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="fsec">
                    <div class="fsec-label">Progress and notes</div>
                    <div class="fgrid">
                        <div class="field">
                            <label for="Hours_Played">Hours played</label>
                            <input id="Hours_Played" type="number" name="Hours_Played" min="0" value="<?= e($row['Hours_Played']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="Progress_Status">Status</label>
                            <select id="Progress_Status" name="Progress_Status">
                                <option value="Not Started" <?= $row['Progress_Status'] === 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                                <option value="In Progress" <?= $row['Progress_Status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed" <?= $row['Progress_Status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        <div class="field full">
                            <label for="Personal_Notes">Personal notes</label>
                            <textarea id="Personal_Notes" name="Personal_Notes" placeholder="Keep the details that make this game worth revisiting."><?= e($personalNotes) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-footer">
                    <button class="btn btn-neon" type="submit">Update vault</button>
                    <a class="btn btn-ghost" href="view.php?id=<?= e($row['Entry_ID']) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>
<?php include 'footer.php'; ?>
