<?php

$rating = $rating ?? [];

$ratingFields = [
    'engagement'  => LANG['RATING_ENGAGEMENT'],
    'trust'       => LANG['RATING_TRUST'],
    'affinity'    => LANG['RATING_AFFINITY'],
    'curiosity'   => LANG['RATING_CURIOSITY'],
    'frustration' => LANG['RATING_FRUSTRATION'],
    'respect'     => LANG['RATING_RESPECT']
];

foreach ($ratingFields as $key => $label)
  {
    $value = isset($rating[$key])
        ? (int)$rating[$key]
        : 0;

    $value   = max(0, min(10, $value));
    $percent = $value * 10;
?>
    <div class="user-rating-row">

        <div class="user-rating-header">
            <span><?php echo htmlspecialchars($label); ?></span>
            <strong><?php echo $value; ?></strong>
        </div>

        <div class="user-rating-bar">
            <span style="width:<?php echo $percent; ?>%"></span>
        </div>

    </div>
<?php
  }

if (!empty($rating['note']))
  {
?>
    <div class="user-rating-note">
        <?php echo nl2br(
            htmlspecialchars((string)$rating['note'])
        ); ?>
    </div>
<?php
  }
?>