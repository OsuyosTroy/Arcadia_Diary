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

$status = normalize_status($row['Progress_Status']);
$rating = normalize_rating($row['Rating'] ?? 3);
$notes = normalize_personal_notes($row['Personal_Notes'] ?? '');
$acqDate = $row['Acquisition_Date'] ?? null;
$daysSince = days_since($acqDate);
$hoursPlayed = (int)$row['Hours_Played'];
$flashMessages = get_flash_messages();

$chipCls = ['Not Started' => 's-ns', 'In Progress' => 's-ip', 'Completed' => 's-cp'][$status];
$progressPct = ['Not Started' => 5, 'In Progress' => 55, 'Completed' => 100][$status];

$heroImage = get_vault_image($row['Game_Title'], $row['Entry_ID']) ?? '';

$pageTitle  = e($row['Game_Title']) . ' Vault | Arcadia Vault';
$activePage = 'collection';
include 'header.php';
?>
<main class="shell">
    <?php if ($flashMessages): ?>
    <div class="page-notice-stack">
        <?php foreach ($flashMessages as $message): ?>
        <div class="page-notice page-notice-<?= e($message['type'] === 'success' ? 'success' : 'error') ?>" role="alert">
            <strong><?= $message['type'] === 'success' ? 'Vault updated' : 'Vault notice' ?></strong>
            <p><?= e($message['message']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <section class="detail-hero">
        <div class="detail-hero-shell reveal<?= $heroImage !== '' ? ' detail-hero-has-image' : '' ?>"<?= $heroImage !== '' ? ' style="--vault-bg:url(&quot;' . e($heroImage) . '&quot;)"' : '' ?>>
            <a class="detail-back" href="collection.php">Back to game vaults</a>
            <div class="detail-head">
                <div>
                    <div class="detail-kicker">Selected game vault</div>
                    <div class="detail-eyebrow">
                        <span class="status-chip <?= e($chipCls) ?>"><?= e($status) ?></span>
                        <span class="tag tag-platform"><?= e($row['Platform']) ?></span>
                        <span class="tag tag-creator"><?= e($row['Creator']) ?></span>
                    </div>
                    <h1 class="detail-title"><?= e($row['Game_Title']) ?> <span>Vault</span></h1>
                    <p class="detail-acq">
                        <?php if ($acqDate && $acqDate !== '0000-00-00'): ?>
                            Tracked since <?= e(format_date($acqDate)) ?><?php if ($daysSince !== null): ?> &mdash; <span class="neon"><?= e($daysSince) ?></span> days in the vault<?php endif; ?>
                        <?php else: ?>
                            This vault holds the current data for this game.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="rank-display">
                    <div class="rank-box prime">
                        <div class="rank-box-label">Prime rank</div>
                        <div class="rank-box-val"><?= e($row['Prime_Rank']) ?></div>
                    </div>
                    <div class="rank-box current">
                        <div class="rank-box-label">Current rank</div>
                        <div class="rank-box-val"><?= e($row['Current_Rank']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="detail-body">
        <div class="detail-main">
            <div class="dcard reveal">
                <div class="dcard-title">Vault progress</div>
                <div class="progress-wrap">
                    <div class="progress-label">
                        <span><?= e($status) ?></span>
                        <span class="neon"><?= e($progressPct) ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:<?= e($progressPct) ?>%"></div>
                    </div>
                </div>
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="si-label">Hours played</div>
                        <div class="si-val neon" data-count="<?= e($hoursPlayed) ?>" data-suffix="h"><?= e($hoursPlayed) ?>h</div>
                    </div>
                    <div class="stat-item">
                        <div class="si-label">Progress status</div>
                        <div class="si-val" style="font-size:1.2rem;"><?= e($status) ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="si-label">Personal rating</div>
                        <div class="stars-big"><?= e(render_stars($rating)) ?></div>
                        <div class="rating-desc"><?= e($rating) ?> out of 5 stars</div>
                    </div>
                    <div class="stat-item">
                        <div class="si-label">Vault ID</div>
                        <div class="si-val" style="font-size:1.4rem; opacity:0.6;">#<?= e($row['Entry_ID']) ?></div>
                    </div>
                </div>
            </div>

            <div class="dcard reveal">
                <div class="dcard-title">Game notes</div>
                <?php if (has_personal_notes($row['Personal_Notes'] ?? '')): ?>
                    <div class="notes-block"><?= e($notes) ?></div>
                <?php else: ?>
                    <div class="notes-empty">No notes yet &mdash; use <a href="edit.php?id=<?= e($row['Entry_ID']) ?>" style="color:var(--accent);">update vault</a> to add personal notes about this game.</div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="detail-aside">
            <div class="dcard reveal">
                <div class="dcard-title">Game info</div>
                <div class="acq-stack">
                    <div class="acq-display">
                        <div class="acq-icon">GM</div>
                        <div>
                            <div class="acq-date-big"><?= e($row['Game_Title']) ?></div>
                            <div class="acq-sub">Game title</div>
                        </div>
                    </div>
                    <div class="acq-display">
                        <div class="acq-icon">PL</div>
                        <div>
                            <div class="acq-date-big"><?= e($row['Platform']) ?></div>
                            <div class="acq-sub">Platform</div>
                        </div>
                    </div>
                    <div class="acq-display">
                        <div class="acq-icon">CR</div>
                        <div>
                            <div class="acq-date-big"><?= e($row['Creator']) ?></div>
                            <div class="acq-sub">Creator / studio</div>
                        </div>
                    </div>
                    <?php if ($acqDate && $acqDate !== '0000-00-00'): ?>
                    <div class="acq-display">
                        <div class="acq-icon">DT</div>
                        <div>
                            <div class="acq-date-big"><?= e(format_date($acqDate)) ?></div>
                            <div class="acq-sub">Date tracked</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dcard reveal">
                <div class="dcard-title">Ranks at a glance</div>
                <div class="summary-list">
                    <div class="stat-item">
                        <div class="si-label">Prime rank</div>
                        <div class="si-val gold" style="font-size:1.5rem;"><?= e($row['Prime_Rank']) ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="si-label">Current rank</div>
                        <div class="si-val neon" style="font-size:1.5rem;"><?= e($row['Current_Rank']) ?></div>
                    </div>
                </div>
            </div>

            <div class="dcard reveal">
                <div class="dcard-title">Quick actions</div>
                <div class="quick-action-stack">
                    <a class="btn btn-neon" href="edit.php?id=<?= e($row['Entry_ID']) ?>">Update vault data</a>
                    <a class="btn btn-ghost" href="collection.php">Pick another game</a>
                    <form method="POST" action="delete.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= e($row['Entry_ID']) ?>">
                        <button class="btn btn-danger" type="submit">Delete this vault</button>
                    </form>
                </div>
            </div>
        </aside>
    </div>
</main>
<?php include 'footer.php'; ?>
