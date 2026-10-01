                <li
                    class="chat-item<?php echo !empty($active) ? ' active' : ''; ?>"
                    id="chat_session_<?php echo (int)$id; ?>">
                  <button
                        type="button"
                        class="chat-item-main"
                        onclick="changeChat(<?php echo $id ?>)">
                    <span class="chat-title">
                      <?php echo htmlspecialchars($session_name); ?>
                    </span>
                  </button>
                  <input
                      type="text"
                      class="chat-rename-input"
                      value="<?php echo htmlspecialchars($session_name); ?>"
                      data-chat-id="<?php echo (int)$id; ?>"
                      hidden>
                  <button
                        type="button"
                        class="chat-item-menu"
                        title="<?php echo LANG['OPERATIONS']; ?>"
                        aria-label="<?php echo LANG['CHAT_OPERATIONS']; ?>"
                        data-chat-menu>
                        ⋯
                  </button>
                  <div class="chat-item-dropdown" hidden>
                    <button type="button" data-chat-rename>
                      <?php echo LANG['RENAME']; ?>
                    </button>
                    <button
                        type="button"
                        class="delete"
                        data-chat-delete
                        data-delete-confirm="<?php echo htmlspecialchars(LANG['MSG_YOU_WANT_DELETE']); ?>">
                      <?php echo LANG['DELETE']; ?>
                    </button>
                  </div>
                </li>
