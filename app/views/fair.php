<?php
/** @var list<array<string, mixed>> $recent */
?>
<section class="pagehead">
    <h2 class="pagehead-title"><?= icon('shield', 'pagehead-icon', 26) ?> How a draw is made</h2>
    <p class="muted">Nothing here needs you to trust the server.</p>
</section>

<section class="panel">
    <ol class="steps">
        <li>When a draw is scheduled, the site picks 32 random hex characters — the <em>nonce</em> — and stores it. The number it implies is computed immediately and thrown away.</li>
        <li>What gets published on the board is only <code>sha256(nonce|day|tier)</code>, the <em>commitment</em>. It is on the play screen before a single ticket exists.</li>
        <li>At draw time the result is <strong>derived</strong> from that same hash by rejection-sampling 32-bit blocks until one lands inside an exact multiple of 10, so all 10,000 numbers stay equally likely. No second roll, no re-draw, no operator discretion.</li>
        <li>The nonce is revealed with the result. Anyone can recompute the commitment and the number and confirm the site said the same thing before and after tickets closed.</li>
    </ol>
    <p class="fineprint">This is the same commitment scheme crypto casinos use, pointed at a game where nobody can lose money. The difference is on purpose: verifiability is worth having even when the stakes are fake.</p>
</section>

<section class="panel">
    <h3 class="panel-title"><?= icon('hash', 'panel-icon-svg', 16) ?> Revealed draws</h3>
    <?php if ($recent === []): ?>
        <p class="muted">Nothing settled yet.</p>
    <?php else: ?>
        <ul class="verifylist">
            <?php foreach ($recent as $row): ?>
                <li>
                    <div class="verify-head">
                        <span class="pill pill-<?= e(Config::tier($row['tier'])['accent']) ?>"><?= e(Config::tier($row['tier'])['label']) ?></span>
                        <span class="muted"><?= e($row['day']) ?></span>
                        <span class="result-num result-num-sm"><?= e($row['result']) ?></span>
                    </div>
                    <p class="kv"><b>nonce</b> <code><?= e($row['nonce']) ?></code></p>
                    <p class="kv"><b>commit</b> <code><?= e($row['commit_hash']) ?></code></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="panel-title"><?= icon('terminal', 'panel-icon-svg', 16) ?> Check one yourself</h3>
    <pre class="code">php -r '
$d="REPLACE-WITH-DAY"; $t="REPLACE-WITH-TIER"; $n="REPLACE-WITH-NONCE";
$h=hash("sha256","$n|$d|$t");
for($b=0;;$b++){ $x=hash("sha256","$n|$d|$t|$b"); $v=hexdec(substr($x,0,8)); if($v<4294960000) break; }
printf("commit %s\nnumber %04d\n", $h, $v%10000);'</pre>
    <p class="fineprint">If the commitment does not match what the board published, the result is worthless and you caught it. That is the entire point of publishing it early.</p>
</section>
