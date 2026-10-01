<div class="gallery-item-wrapper" id="<?php echo $image; ?>">
    <img src="<?php echo $image; ?>" alt="Model image" onclick="loadGalleryImage('<?php echo $image; ?>');">
    <div class="gallery-delete-btn" onclick="deleteGalleryImage('<?php echo $image; ?>', this)">×</div>
</div>