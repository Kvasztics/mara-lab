    </div><!-- /.app-body -->

</div><!-- /.mara-app -->


<!-- =====================================================================
     COMMON MODALS
     ===================================================================== -->

<div id="modal_pconfirm" class="modal">

    <div class="modal-content">

        <a
            class="close"
            onclick="modalClose('modal_pconfirm');">
            &times;
        </a>

        <div class="content">

            <p id="pconfirm_title"></p>

            <div class="form-group row" style="margin:0;">

                <div class="cgrid-4"></div>

                <div class="cgrid-8">

                    <a
                        onclick="modalClose('modal_pconfirm');"
                        class="btn btn-primary">

                        <?php echo LANG['CANCEL']; ?>

                    </a>

                    <button
                        id="pconfirm_ok"
                        class="btn btn-primary">
                        OK
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>


<div id="modal_palert" class="modal">

    <div class="modal-content">

        <a
            class="close"
            onclick="modalClose('modal_palert');">
            &times;
        </a>

        <div class="content">

            <p id="palert_title"></p>

            <div class="form-group row" style="margin:0;">

                <div class="cgrid-4"></div>

                <div class="cgrid-8">

                    <a
                        onclick="modalClose('modal_palert');"
                        class="btn btn-primary">
                        OK
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>


<script>
function _confirm(msg, callback)
  {
    document.getElementById('pconfirm_title').innerHTML = msg;
    document.getElementById('pconfirm_ok').onclick = callback;

    document.getElementById('modal_pconfirm').style.visibility = 'visible';
    document.getElementById('modal_pconfirm').style.opacity = 1;

    return false;
  }

function _alert(msg)
  {
    document.getElementById('palert_title').innerHTML = msg;

    document.getElementById('modal_palert').style.visibility = 'visible';
    document.getElementById('modal_palert').style.opacity = 1;

    return false;
  }

<?php if ($message) { ?>

_alert('<?php echo $message; ?>');

<?php } ?>

</script>
<script src="<?= DIR_JS ?>/chat.js"></script>
</body>
</html>