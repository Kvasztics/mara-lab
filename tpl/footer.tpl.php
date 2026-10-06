    </div><!-- /.app-body -->

</div><!-- /.mara-app -->


<?php require DIR_TPL . '/modals.tpl.php'; ?>

<script>
<?php if ($message) { ?>

_alert(<?= json_encode(
    $message,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
) ?>);

<?php } ?>

</script>
<script src="<?= DIR_JS ?>/chat.js"></script>
</body>
</html>