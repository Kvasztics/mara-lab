<?php

$metrics = $metrics ?? [];

?>

<div class="test-row">
    <span><?php echo LANG['MODEL_PROVIDER']; ?></span>
    <strong>
        <?php echo htmlspecialchars(
            ucfirst((string)($metrics['provider'] ?? ''))
        ); ?>
    </strong>
</div>

<div class="test-row">
    <span><?php echo LANG['MODEL_MODEL']; ?></span>
    <strong>
        <?php echo htmlspecialchars(
            (string)($metrics['model'] ?? '')
        ); ?>
    </strong>
</div>

<div class="test-row">
    <span><?php echo LANG['MODEL_CONTEXT']; ?></span>
    <strong>
        <?php echo (int)($metrics['context_used'] ?? 0); ?>
        /
        <?php echo (int)($metrics['context_size'] ?? 0); ?>
    </strong>
</div>

<div class="test-progress">
    <span style="width:<?php
        echo (float)($metrics['context_percent'] ?? 0);
    ?>%"></span>
</div>

<div class="test-row">
    <span><?php echo LANG['MODEL_PROMPT_TOKENS']; ?></span>
    <strong>
        <?php echo (int)($metrics['prompt_tokens'] ?? 0); ?>
    </strong>
</div>

<div class="test-row">
    <span><?php echo LANG['MODEL_OUTPUT_TOKENS']; ?></span>
    <strong>
        <?php echo (int)($metrics['output_tokens'] ?? 0); ?>
    </strong>
</div>

<div class="test-row">
    <span><?php echo LANG['MODEL_GENERATION']; ?></span>
    <strong>
        <?php echo number_format(
            (float)($metrics['generation'] ?? 0),
            2
        ); ?> s
    </strong>
</div>

<div class="test-row">
    <span><?php echo LANG['MODEL_SPEED']; ?></span>
    <strong>
        <?php echo number_format(
            (float)($metrics['speed'] ?? 0),
            1
        ); ?> tok/s
    </strong>
</div>