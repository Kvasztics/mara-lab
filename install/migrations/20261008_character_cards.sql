-- Preserve imported Tavern character card data.
ALTER TABLE `models`
  ADD COLUMN `card_data` MEDIUMTEXT
  COLLATE utf8mb4_unicode_ci NULL AFTER `modelinfo`;
