<?php
include 'db.php';

$result = $conn->query("SELECT Entry_ID, Game_Title, Platform, Creator, Current_Rank, Prime_Rank, Rating, Hours_Played, Progress_Status FROM GameCollectionDiary ORDER BY Entry_ID DESC");
$allVaults = [];
while ($row = $result->fetch_assoc()) {
    $allVaults[] = $row;
}

// xdkgnxdkgxdkgvnxlvx

$gameMap = [];
foreach ($allVaults as $row) {
    $gameMap[normalize_game_key($row['Game_Title'])] = $row;
}

$vaultGames = [
    ['title'=>'Mobile Legends','aliases'=>['mobilelegends','mobilelegend'],'image'=>'assets/games/mobile-legends.jpg'],
    ['title'=>'Call of Duty',  'aliases'=>['callofduty'],                  'image'=>'assets/games/call-of-duty.jpg'],
    ['title'=>'Crossfire',     'aliases'=>['crossfire'],                   'image'=>'assets/games/crossfire.jpg'],
    ['title'=>'Clash of Clans','aliases'=>['clashofclans'],                'image'=>'assets/games/clash-of-clans.jpg'],
    ['title'=>'Dota',          'aliases'=>['dota','dota2'],                'image'=>'assets/games/dota-2.jpg'],
];

$fixedKeys = [];
foreach ($vaultGames as $g) {
    foreach ($g['aliases'] as $a) $fixedKeys[$a] = true;
}

$customVaults = [];
foreach ($allVaults as $row) {
    if (!isset($fixedKeys[normalize_game_key($row['Game_Title'])])) {
        $customVaults[] = $row;
    }
}

/* Build flat card list for JS data */
$cards = [];
foreach ($vaultGames as $game) {
    $record = null;
    foreach ($game['aliases'] as $alias) {
        if (isset($gameMap[$alias])) { $record = $gameMap[$alias]; break; }
    }
    $image = $record ? (get_vault_image($record['Game_Title'], $record['Entry_ID']) ?: $game['image']) : $game['image'];
    $href  = $record ? 'view.php?id='.(int)$record['Entry_ID'] : 'create.php?game='.urlencode($game['title']);
    $cards[] = [
        'image'    => $image,
        'href'     => $href,
        'label'    => $game['title'],
        'add'      => false,
        'custom'   => false,
        'hasVault' => $record !== null,
        'platform' => $record['Platform'] ?? '',
        'creator'  => $record['Creator'] ?? '',
        'curRank'  => $record['Current_Rank'] ?? '',
        'primeRank'=> $record['Prime_Rank'] ?? '',
        'rating'   => $record ? (int)$record['Rating'] : 0,
        'hours'    => $record ? (int)$record['Hours_Played'] : 0,
        'status'   => $record ? normalize_status($record['Progress_Status']) : '',
    ];
}
foreach ($customVaults as $custom) {
    $img = get_vault_image($custom['Game_Title'], $custom['Entry_ID']);
    $cards[] = [
        'image'    => $img ?: '',
        'href'     => 'view.php?id='.(int)$custom['Entry_ID'],
        'label'    => $custom['Game_Title'],
        'add'      => false,
        'custom'   => true,
        'hasVault' => true,
        'platform' => $custom['Platform'] ?? '',
        'creator'  => $custom['Creator'] ?? '',
        'curRank'  => $custom['Current_Rank'] ?? '',
        'primeRank'=> $custom['Prime_Rank'] ?? '',
        'rating'   => (int)$custom['Rating'],
        'hours'    => (int)$custom['Hours_Played'],
        'status'   => normalize_status($custom['Progress_Status']),
    ];
}

$pageTitle  = 'Vaults | Arcadia Vault';
$activePage = 'collection';
include 'header.php';
$flashMessages = get_flash_messages();
?>
<style>
/* ── Two-column vault layout ─────────────────────── */
.vl-wrap {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 32px;
    align-items: start;
    padding-bottom: 80px;
}

/* ── LEFT: carousel stage ────────────────────────── */
.vl-left {
    min-width: 0;
    position: relative;
}
.vl-kicker {
    font-size: 0.68rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(0,255,224,0.65);
    margin-bottom: 18px;
    display: block;
}

/* Stage: fixed height, clips overflow so cards don't spill */
.cv-stage {
    position: relative;
    width: 100%;
    height: 520px;
    overflow: hidden;
    cursor: grab;
    user-select: none;
    border-radius: 28px;
    background:
        radial-gradient(circle at 50% 42%, rgba(16,206,255,0.18) 0%, rgba(16,206,255,0.05) 20%, transparent 44%),
        radial-gradient(circle at 18% 18%, rgba(255,209,102,0.08) 0%, transparent 24%),
        linear-gradient(180deg, rgba(6,11,24,0.96) 0%, rgba(4,8,20,0.99) 100%);
    border: 1px solid rgba(111,206,255,0.14);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.04), 0 26px 64px rgba(0,0,0,0.36);
}
.cv-stage:active { cursor: grabbing; }
.cv-stage::before,
.cv-stage::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 1;
}
.cv-stage::before {
    background:
        linear-gradient(90deg, rgba(3,6,15,0.92), transparent 14%, transparent 86%, rgba(3,6,15,0.92)),
        linear-gradient(180deg, rgba(255,255,255,0.04), transparent 16%, transparent 84%, rgba(255,255,255,0.03));
}
.cv-stage::after {
    inset: 16px;
    border-radius: 20px;
    border: 1px solid rgba(120,150,255,0.08);
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(circle at 50% 44%, black 30%, transparent 90%);
    opacity: 0.34;
}

/* Cards: absolutely positioned, JS drives transform */
.cv-item {
    position: absolute;
    top: 0;
    width: 180px;
    text-decoration: none;
    transform-origin: top center;
    will-change: transform, opacity, filter;
    outline: none;
    -webkit-tap-highlight-color: transparent;
    transition:
        transform 0.94s cubic-bezier(0.18, 0.9, 0.22, 1),
        opacity   0.74s cubic-bezier(0.18, 0.9, 0.22, 1),
        filter    0.74s cubic-bezier(0.18, 0.9, 0.22, 1);
    z-index: 2;
}
.cv-item:focus { outline: none; }
.cv-stage.is-dragging .cv-item {
    transition-duration: 0.12s;
    transition-timing-function: linear;
}
.cv-item:focus-visible .cv-face {
    border-color: rgba(0,255,224,0.92);
    box-shadow: 0 0 0 2px rgba(0,255,224,0.4);
}

.cv-face {
    width: 100%;
    height: 272px;
    border-radius: 18px;
    background-size: cover;
    background-position: center;
    background-color: #0a1220;
    border: 1px solid rgba(84,196,255,0.3);
    box-shadow: 0 18px 38px rgba(0,0,0,0.48);
    transition: border-color 0.52s ease, box-shadow 0.52s ease, transform 0.52s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}
.cv-face::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0) 22%, rgba(4,8,18,0.1) 62%, rgba(4,8,18,0.3) 100%);
    opacity: 0.85;
    pointer-events: none;
}
.cv-item.cv-active .cv-face {
    border-color: rgba(94,228,255,0.95);
    transform: translateY(-2px);
    box-shadow:
        0 0 0 1px rgba(201,249,255,0.2),
        0 0 28px rgba(0,208,255,0.34),
        0 0 62px rgba(0,120,255,0.18),
        0 22px 54px rgba(0,0,0,0.58);
}

/* reflection strip */
.cv-reflect {
    width: 100%;
    height: 116px;
    margin-top: 10px;
    background-size: cover;
    background-position: center bottom;
    transform: scaleY(-1);
    opacity: 0;
    border-radius: 0 0 18px 18px;
    pointer-events: none;
    transition: opacity 0.72s ease, transform 0.72s ease;
    filter: blur(4px);
    -webkit-mask-image: linear-gradient(to bottom, rgba(0,0,0,0.45) 0%, rgba(0,0,0,0.1) 38%, transparent 100%);
    mask-image:         linear-gradient(to bottom, rgba(0,0,0,0.45) 0%, rgba(0,0,0,0.1) 38%, transparent 100%);
}
.cv-item.cv-active .cv-reflect { opacity: 0.52; }
.cv-stage.is-dragging .cv-reflect { transition-duration: 0.18s; }

/* plain card (add / custom initials) */
.cv-face-plain {
    flex-direction: column;
    gap: 8px;
    background: linear-gradient(160deg, #0e1c2e 0%, #070c18 100%);
}
.cv-plain-mark {
    font-size: 2.8rem;
    font-weight: 900;
    line-height: 1;
    color: rgba(0,255,224,0.85);
    text-shadow: 0 0 24px rgba(0,255,224,0.4);
}
.cv-plain-name {
    font-size: 0.62rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.45);
    text-align: center;
    padding: 0 8px;
}

/* nav dots */
.cv-controls {
    position: absolute;
    inset-inline: 18px;
    top: 50%;
    display: flex;
    justify-content: space-between;
    pointer-events: none;
    z-index: 20;
    transform: translateY(-50%);
}
.cv-control {
    width: 46px;
    height: 46px;
    border-radius: 999px;
    border: 1px solid rgba(132,160,255,0.18);
    background: rgba(7,11,23,0.74);
    color: rgba(236,241,255,0.84);
    display: grid;
    place-items: center;
    font-size: 1.35rem;
    line-height: 1;
    cursor: pointer;
    pointer-events: auto;
    backdrop-filter: blur(18px);
    box-shadow: 0 16px 30px rgba(0,0,0,0.3);
    transition: transform 0.24s ease, border-color 0.24s ease, color 0.24s ease, background 0.24s ease;
    position: relative;
    z-index: 21;
}
.cv-control:hover,
.cv-control:focus-visible {
    transform: translateY(-2px);
    border-color: rgba(0,255,224,0.34);
    color: #fff;
    background: rgba(10,17,32,0.94);
}
.cv-control:focus-visible {
    outline: none;
}
.cv-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 18px;
}
.cv-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: rgba(255,255,255,0.18);
    border: none;
    padding: 0;
    cursor: pointer;
    transition: background 0.28s ease, transform 0.28s ease, width 0.28s ease;
}
.cv-dot.cv-dot-active {
    width: 24px;
    background: linear-gradient(90deg, rgba(0,255,224,0.94), rgba(130,255,236,0.72));
    transform: none;
}

/* ── RIGHT: info panel ───────────────────────────── */
.vl-right {
    position: sticky;
    top: 96px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.vl-inspector {
    min-height: 620px;
}

.vl-panel {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    background: linear-gradient(180deg, rgba(18,26,45,0.94) 0%, rgba(9,14,27,0.98) 100%),
                linear-gradient(160deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.01) 100%);
    border: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 22px 54px rgba(0,0,0,0.28);
    padding: 24px;
}
.vl-panel::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0,255,224,0.6), rgba(255,209,102,0.35), transparent);
    opacity: 0.72;
}

.vl-panel-label {
    font-size: 0.62rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(0,255,224,0.6);
    margin-bottom: 14px;
}

.vl-game-name {
    font-size: 1.68rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: 0.02em;
    line-height: 1.08;
    margin-bottom: 8px;
    min-height: 2.05rem;
    transition: opacity 0.24s ease, transform 0.24s ease;
}

.vl-game-sub {
    font-size: 0.73rem;
    color: rgba(230,238,255,0.42);
    letter-spacing: 0.14em;
    text-transform: uppercase;
    min-height: 1rem;
    transition: opacity 0.24s ease, transform 0.24s ease;
}

.vl-divider {
    height: 1px;
    background: linear-gradient(90deg, rgba(255,255,255,0.08), transparent 80%);
    margin: 18px 0;
}

.vl-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.vl-btn-primary {
    display: block;
    text-align: center;
    min-height: 48px;
    padding: 12px 18px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(9,63,82,0.92), rgba(12,101,133,0.9));
    border: 1px solid rgba(0,255,224,0.2);
    color: rgba(225,255,248,0.95);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    text-decoration: none;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.05), 0 14px 26px rgba(0,88,118,0.18);
    transition: transform 0.22s ease, background 0.22s ease, border-color 0.22s ease, color 0.22s ease, box-shadow 0.22s ease;
}
.vl-btn-primary:hover {
    transform: translateY(-1px);
    background: linear-gradient(135deg, rgba(11,88,116,0.98), rgba(15,124,162,0.94));
    border-color: rgba(93,255,234,0.38);
    box-shadow: 0 0 20px rgba(0,255,224,0.14), 0 16px 28px rgba(0,61,84,0.24);
    color: #fff;
}

.vl-btn-ghost {
    display: block;
    text-align: center;
    min-height: 48px;
    padding: 11px 18px;
    border-radius: 14px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(222,230,247,0.62);
    font-size: 0.76rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    text-decoration: none;
    transition: transform 0.22s ease, border-color 0.22s ease, color 0.22s ease;
}
.vl-btn-ghost:hover {
    transform: translateY(-1px);
    border-color: rgba(255,255,255,0.25);
    color: rgba(255,255,255,0.88);
}

.vl-stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 0.75rem;
}
.vl-stat-row:last-child { border-bottom: none; }
.vl-stat-k {
    color: rgba(210,219,238,0.34);
    letter-spacing: 0.12em;
    text-transform: uppercase;
    font-size: 0.61rem;
}
.vl-stat-v {
    color: rgba(244,247,255,0.92);
    font-weight: 700;
    letter-spacing: 0.04em;
    text-align: right;
}
.vl-stat-v.neon { color: rgba(0,255,224,0.9); }
.vl-stat-v.gold { color: rgba(255,209,102,0.9); }

/* ── Inline quick-create form ────────────────────── */
.vl-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 10px;
}
.vl-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 0;
}
.vl-label {
    font-size: 0.6rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(0,255,224,0.55);
}
.vl-input {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 8px 10px;
    color: #fff;
    font-size: 0.78rem;
    font-family: inherit;
    width: 100%;
    box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
    -webkit-appearance: none;
}
.vl-input:focus {
    border-color: rgba(0,255,224,0.5);
    box-shadow: 0 0 0 2px rgba(0,255,224,0.1);
}
.vl-input::placeholder { color: rgba(255,255,255,0.2); }
.vl-input option { background: #0d1628; color: #fff; }
.vl-textarea {
    resize: vertical;
    min-height: 62px;
}

/* counter badge */
.vl-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.68rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.35);
    margin-top: 4px;
}
.vl-count-num {
    font-size: 1.1rem;
    font-weight: 700;
    color: rgba(0,255,224,0.7);
    letter-spacing: 0;
}

/* responsive */
@media (max-width: 860px) {
    .vl-wrap {
        grid-template-columns: 1fr;
    }
    .vl-right {
        position: static;
        flex-direction: row;
        flex-wrap: wrap;
    }
    .vl-panel { flex: 1 1 240px; }
    .vl-inspector { min-height: auto; }
    .cv-stage { height: 430px; }
    .cv-item  { width: 140px; }
    .cv-face  { height: 220px; }
    .cv-controls {
        inset-inline: 12px;
    }
    .cv-control {
        width: 40px;
        height: 40px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cv-item,
    .cv-face,
    .cv-reflect,
    .cv-dot,
    .cv-control {
        transition-duration: 0.01ms !important;
        animation: none !important;
    }
}
</style>

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
    <section class="page-hdr">
        <span class="page-kicker">Choose a game</span>
        <h1>Game <span>Vaults</span></h1>
        <p>Browse your collection like a control room: spotlight an active vault, inspect its status, or create a clean new entry without leaving the carousel.</p>
    </section>

    <div class="vl-wrap">

        <!-- LEFT: Coverflow carousel -->
        <div class="vl-left">
            <span class="vl-kicker">Your game vaults</span>

            <div class="cv-stage" id="cvStage" tabindex="0" aria-label="Vault carousel">
                <?php foreach ($cards as $i => $card): ?>
                <a class="cv-item" href="<?= e($card['href']) ?>" id="cvItem<?= $i ?>" aria-label="<?= e($card['label']) ?>">
                    <?php if (!empty($card['image'])): ?>
                        <div class="cv-face" style="background-image:url('<?= e($card['image']) ?>')"></div>
                        <div class="cv-reflect" style="background-image:url('<?= e($card['image']) ?>')"></div>
                    <?php elseif ($card['add']): ?>
                        <div class="cv-face cv-face-plain">
                            <div class="cv-plain-mark">+</div>
                            <div class="cv-plain-name">Add New Game</div>
                        </div>
                        <div class="cv-reflect" style="background:#070c18"></div>
                    <?php else: ?>
                            <div class="cv-face cv-face-plain">
                                <div class="cv-plain-mark"><?= e(vault_upper(vault_substr($card['label'], 0, 2))) ?></div>
                            <div class="cv-plain-name"><?= e($card['label']) ?></div>
                        </div>
                        <div class="cv-reflect" style="background:#070c18"></div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <div class="cv-controls">
                <button class="cv-control" type="button" id="cvPrevBtn" aria-label="Previous vault">&#8249;</button>
                <button class="cv-control" type="button" id="cvNextBtn" aria-label="Next vault">&#8250;</button>
                </div>
            </div>

            <div class="cv-dots" id="cvDots"></div>
        </div>

        <!-- RIGHT: Info + actions panel -->
        <div class="vl-right">

            <!-- STATE A: existing vault info -->
            <div class="vl-panel vl-inspector" id="vlPanelView">
                <div class="vl-panel-label">Selected Vault</div>
                <div class="vl-game-name" id="vlGameName">&nbsp;</div>
                <div class="vl-game-sub" id="vlGameSub">Open to view vault details</div>
                <div class="vl-divider"></div>
                <div id="vlStats" style="margin-bottom:14px;">
                    <div class="vl-stat-row"><span class="vl-stat-k">Platform</span><span class="vl-stat-v" id="vlPlatform"></span></div>
                    <div class="vl-stat-row"><span class="vl-stat-k">Current Rank</span><span class="vl-stat-v neon" id="vlCurRank"></span></div>
                    <div class="vl-stat-row"><span class="vl-stat-k">Prime Rank</span><span class="vl-stat-v gold" id="vlPrimeRank"></span></div>
                    <div class="vl-stat-row"><span class="vl-stat-k">Hours Played</span><span class="vl-stat-v" id="vlHours"></span></div>
                    <div class="vl-stat-row"><span class="vl-stat-k">Status</span><span class="vl-stat-v" id="vlStatus"></span></div>
                    <div class="vl-stat-row"><span class="vl-stat-k">Rating</span><span class="vl-stat-v gold" id="vlRating"></span></div>
                </div>
                <div class="vl-divider"></div>
                <div class="vl-actions">
                    <a class="vl-btn-primary" href="#" id="vlOpenBtn">Open Vault</a>
                    <a class="vl-btn-ghost"   href="#" id="vlEditBtn">Edit Vault</a>
                </div>
            </div>

            <!-- STATE B: no vault yet — inline quick-create form -->
            <div class="vl-panel vl-inspector" id="vlPanelCreate" style="display:none;">
                <div class="vl-panel-label">Selected Vault</div>
                <div class="vl-game-name" id="vlCreateName">&nbsp;</div>
                <div class="vl-game-sub" style="margin-bottom:14px;">No vault yet — fill in the details below</div>
                <div class="vl-divider"></div>
                <form method="POST" action="create.php" id="vlQuickForm" style="margin-top:12px;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="submit" value="1">
                    <input type="hidden" name="Game_Title" id="vlQTitle">

                    <div class="vl-field">
                        <label class="vl-label" for="vlQPlatform">Platform</label>
                        <input class="vl-input" type="text" id="vlQPlatform" name="Platform" placeholder="PC, Mobile…" required>
                    </div>
                    <div class="vl-field">
                        <label class="vl-label" for="vlQCreator">Creator / Studio</label>
                        <input class="vl-input" type="text" id="vlQCreator" name="Creator" placeholder="Studio or publisher" required>
                    </div>
                    <div class="vl-field-row">
                        <div class="vl-field">
                            <label class="vl-label" for="vlQCurRank">Current Rank</label>
                            <input class="vl-input" type="text" id="vlQCurRank" name="Current_Rank" placeholder="e.g. Gold III" required>
                        </div>
                        <div class="vl-field">
                            <label class="vl-label" for="vlQPrimeRank">Prime Rank</label>
                            <input class="vl-input" type="text" id="vlQPrimeRank" name="Prime_Rank" placeholder="Best result" required>
                        </div>
                    </div>
                    <div class="vl-field-row">
                        <div class="vl-field">
                            <label class="vl-label" for="vlQHours">Hours Played</label>
                            <input class="vl-input" type="number" id="vlQHours" name="Hours_Played" min="0" value="0" required>
                        </div>
                        <div class="vl-field">
                            <label class="vl-label" for="vlQRating">Rating</label>
                            <select class="vl-input" id="vlQRating" name="Rating">
                                <option value="1">★☆☆☆☆</option>
                                <option value="2">★★☆☆☆</option>
                                <option value="3" selected>★★★☆☆</option>
                                <option value="4">★★★★☆</option>
                                <option value="5">★★★★★</option>
                            </select>
                        </div>
                    </div>
                    <div class="vl-field">
                        <label class="vl-label" for="vlQStatus">Status</label>
                        <select class="vl-input" id="vlQStatus" name="Progress_Status">
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="vl-field">
                        <label class="vl-label" for="vlQNotes">Notes <span style="opacity:.4">(optional)</span></label>
                        <textarea class="vl-input vl-textarea" id="vlQNotes" name="Personal_Notes" placeholder="Any notes for this vault…"></textarea>
                    </div>
                    <div class="vl-divider"></div>
                    <button class="vl-btn-primary" type="submit" style="width:100%;border:none;cursor:pointer;">Save Vault</button>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    var stage  = document.getElementById('cvStage');
    var dotsEl = document.getElementById('cvDots');
    var prevBtn = document.getElementById('cvPrevBtn');
    var nextBtn = document.getElementById('cvNextBtn');
    var nameEl     = document.getElementById('vlGameName');
    var subEl      = document.getElementById('vlGameSub');
    var openEl     = document.getElementById('vlOpenBtn');
    var editEl     = document.getElementById('vlEditBtn');
    var statsEl    = document.getElementById('vlStats');
    var platformEl = document.getElementById('vlPlatform');
    var curRankEl  = document.getElementById('vlCurRank');
    var primeEl    = document.getElementById('vlPrimeRank');
    var hoursEl    = document.getElementById('vlHours');
    var statusEl   = document.getElementById('vlStatus');
    var ratingEl   = document.getElementById('vlRating');

    var panelView   = document.getElementById('vlPanelView');
    var panelCreate = document.getElementById('vlPanelCreate');
    var createName  = document.getElementById('vlCreateName');
    var qTitle      = document.getElementById('vlQTitle');
    var qPlatform   = document.getElementById('vlQPlatform');
    var qCreator    = document.getElementById('vlQCreator');
    var qCurRank    = document.getElementById('vlQCurRank');
    var qPrimeRank  = document.getElementById('vlQPrimeRank');
    var qHours      = document.getElementById('vlQHours');
    var qRating     = document.getElementById('vlQRating');
    var qStatus     = document.getElementById('vlQStatus');
    var qNotes      = document.getElementById('vlQNotes');
    var items = Array.from(stage.querySelectorAll('.cv-item'));
    var total = items.length;
    if (!total) return;

    /* Card data passed from PHP */
    var CARDS = <?= json_encode(array_values($cards)) ?>;

    var active = 0;
    var lastPanelKey = '';
    var dragOffset = 0;
    var isDragging = false;
    var didDrag = false;
    var autoId = null;
    var resumeId = null;
    var reduceMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    /* SLOTS: [horizontal-distance, scale, opacity, vertical-drop] */
    var SLOTS = [
        [  0,   1.00,  1.00,   12 ],
        [188,   0.82,  0.92,   34 ],
        [332,   0.62,  0.64,   58 ],
        [448,   0.42,  0.34,   82 ],
        [548,   0.26,  0.14,   98 ],
    ];

    /* Build dots */
    items.forEach(function(_, i) {
        var d = document.createElement('button');
        d.className = 'cv-dot' + (i === 0 ? ' cv-dot-active' : '');
        d.setAttribute('aria-label', 'Vault ' + (i+1));
        d.addEventListener('click', function() { stopAuto(); go(i); scheduleResume(); });
        dotsEl.appendChild(d);
    });
    var dots = Array.from(dotsEl.children);

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function normalizeOffset(value) {
        if (value >  total / 2) value -= total;
        if (value < -total / 2) value += total;
        return value;
    }

    function getSlotMetrics(absOff) {
        var bounded = Math.min(absOff, SLOTS.length - 1);
        var baseIndex = Math.floor(bounded);
        var nextIndex = Math.min(baseIndex + 1, SLOTS.length - 1);
        var progress = bounded - baseIndex;
        var base = SLOTS[baseIndex];
        var next = SLOTS[nextIndex];

        return [
            base[0] + ((next[0] - base[0]) * progress),
            base[1] + ((next[1] - base[1]) * progress),
            base[2] + ((next[2] - base[2]) * progress),
            base[3] + ((next[3] - base[3]) * progress)
        ];
    }

    function setInteractiveState() {
        items.forEach(function(item, i) {
            var isActive = i === active;
            item.classList.toggle('cv-active', isActive);
            item.setAttribute('tabindex', isActive ? '0' : '-1');
            item.setAttribute('aria-current', isActive ? 'true' : 'false');
        });

        dots.forEach(function(d, i) {
            d.classList.toggle('cv-dot-active', i === active);
            d.setAttribute('aria-pressed', i === active ? 'true' : 'false');
        });
    }

    function layout() {
        var W  = stage.clientWidth;
        var cx = W / 2;
        var faceH = parseInt(getComputedStyle(stage.querySelector('.cv-face')).height) || 260;
        /* vertically center the active card in stage */
        var stageH   = stage.clientHeight;
        var cardH    = faceH + 60; /* face + reflect */
        var topBase  = ((stageH - cardH) / 2) + 18;
        var virtualActive = active - dragOffset;

        items.forEach(function(item, i) {
            var off = normalizeOffset(i - virtualActive);
            var absOff = Math.abs(off);
            var s      = getSlotMetrics(absOff);
            var dist   = s[0], sc = s[1], op = s[2], yDrop = s[3];
            var dir    = off === 0 ? 0 : (off < 0 ? -1 : 1);
            var blur   = absOff < 0.08 ? 0 : Math.min(absOff * 0.9, 2.8);
            var rotate = dir * Math.min(absOff * 6, 14);

            var tx = cx + dir * dist;   /* center of card */
            var ty = topBase + yDrop;

            item.style.zIndex    = String(Math.round(80 - absOff * 12));
            item.style.opacity   = op.toFixed(3);
            item.style.top       = ty + 'px';
            item.style.filter    = 'blur(' + blur.toFixed(2) + 'px) saturate(' + (absOff < 0.08 ? 1 : Math.max(0.56, 1 - absOff * 0.1)).toFixed(3) + ')';
            item.style.transform = 'translateX(' + tx.toFixed(2) + 'px) translateX(-50%) scale(' + sc.toFixed(3) + ') rotateY(' + rotate.toFixed(2) + 'deg)';
        });
        setInteractiveState();

        /* Update right panel — three states */
        var card = CARDS[active];
        if (!card) return;

        var panelKey = (card.hasVault ? 'view:' : 'create:') + (card.href || '') + ':' + (card.label || '');

        if (card.hasVault) {
            /* STATE A: vault already exists — show stats */
            panelCreate.style.display = 'none';
            panelView.style.display   = 'block';
            nameEl.textContent = card.label || '';
            subEl.textContent  = 'Open to view vault details';
            openEl.href = card.href;
            editEl.href = card.href.replace('view.php', 'edit.php');
            platformEl.textContent = card.platform  || '—';
            curRankEl.textContent  = card.curRank   || '—';
            primeEl.textContent    = card.primeRank || '—';
            hoursEl.textContent    = card.hours + 'h';
            statusEl.textContent   = card.status    || '—';
            ratingEl.textContent   = '★'.repeat(card.rating) + '☆'.repeat(5 - card.rating);
        } else {
            /* STATE B: no vault yet — inline quick-create form */
            panelView.style.display = 'none';
            panelCreate.style.display = 'block';
            createName.textContent = card.label || '';
            if (lastPanelKey !== panelKey) {
                qTitle.value    = card.label || '';
                /* Pre-fill platform/creator from preset; reset other fields only when selection changes */
                qPlatform.value = card.platform || '';
                qCreator.value  = card.creator  || '';
                qCurRank.value   = '';
                qPrimeRank.value = '';
                qHours.value     = '0';
                qRating.value    = '3';
                qStatus.value    = 'Not Started';
                qNotes.value     = '';
            }
        }

        lastPanelKey = panelKey;
    }

    function go(idx) {
        active = ((idx % total) + total) % total;
        dragOffset = 0;
        layout();
    }

    function prefersReducedMotion() {
        return !!(reduceMotionQuery && reduceMotionQuery.matches);
    }

    function startAuto() {
        if (autoId || total < 2 || prefersReducedMotion() || document.hidden) return;
        autoId = setInterval(function() { go(active + 1); }, 4800);
    }

    function stopAuto() {
        clearInterval(autoId);
        clearTimeout(resumeId);
        autoId = null;
        resumeId = null;
    }

    function scheduleResume() {
        clearTimeout(resumeId);
        resumeId = null;
        if (prefersReducedMotion() || document.hidden) return;
        resumeId = setTimeout(startAuto, 2800);
    }

    /* Drag — track distance to distinguish tap from swipe */
    var dragX = 0, dragDelta = 0;

    stage.addEventListener('pointerdown', function(e) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        isDragging = true;
        didDrag = false;
        dragX = e.clientX;
        dragDelta = 0;
        dragOffset = 0;
        stage.classList.add('is-dragging');
        stage.setPointerCapture(e.pointerId);
        stopAuto();
    });
    stage.addEventListener('pointermove', function(e) {
        if (!isDragging) return;
        dragDelta = e.clientX - dragX;
        if (Math.abs(dragDelta) > 8) didDrag = true;
        dragOffset = clamp(dragDelta / 220, -1.15, 1.15);
        layout();
    });
    stage.addEventListener('pointerup', function() {
        if (!isDragging) return;
        isDragging = false;
        stage.classList.remove('is-dragging');
        if (didDrag && Math.abs(dragDelta) > 46) {
            go(active + (dragDelta < 0 ? 1 : -1));
        } else {
            dragOffset = 0;
            layout();
        }
        scheduleResume();
    });
    stage.addEventListener('pointercancel', function() {
        isDragging = false;
        didDrag = false;
        dragOffset = 0;
        stage.classList.remove('is-dragging');
        layout();
        scheduleResume();
    });

    /* Click: always center the selected card first */
    items.forEach(function(item, i) {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            if (didDrag) { didDrag = false; return; }
            stopAuto();
            if (i !== active) {
                go(i);
            }
            scheduleResume();
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('pointerdown', function (e) {
            e.stopPropagation();
        });
        prevBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            stopAuto();
            go(active - 1);
            scheduleResume();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('pointerdown', function (e) {
            e.stopPropagation();
        });
        nextBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            stopAuto();
            go(active + 1);
            scheduleResume();
        });
    }

    /* Keyboard */
    stage.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft')  { e.preventDefault(); stopAuto(); go(active - 1); scheduleResume(); }
        if (e.key === 'ArrowRight') { e.preventDefault(); stopAuto(); go(active + 1); scheduleResume(); }
    });

    stage.addEventListener('mouseenter', stopAuto);
    stage.addEventListener('mouseleave', scheduleResume);
    stage.addEventListener('focusin', stopAuto);
    stage.addEventListener('focusout', scheduleResume);
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAuto();
        } else {
            scheduleResume();
        }
    });

    if (reduceMotionQuery && typeof reduceMotionQuery.addEventListener === 'function') {
        reduceMotionQuery.addEventListener('change', function() {
            stopAuto();
            dragOffset = 0;
            layout();
            startAuto();
        });
    }

    window.addEventListener('resize', layout);

    layout();
    startAuto();
})();
</script>

<?php include 'footer.php'; ?>
