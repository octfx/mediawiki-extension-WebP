# WebP Extension
Still somewhat experimental.

Upon file upload this extensions creates a WebP version of the uploaded image.

If an WebP file exists, and the browser supports WebP images, the link for the current image is changed for the webp version.

Requires a usable version of imagick and `libwebp` installed.

## Converting already uploaded images
A maintenance script exists to convert already uploaded images:
```shell
php extensions/WebP/maintenance/ConvertImages.php

# To only convert non-thumbnails run
php extensions/WebP/maintenance/ConvertImages.php --no-thumbs

# To create thumbnails of custom sizes run
# This will create two thumbnails with size 1000px and 1250px
php extensions/WebP/maintenance/ConvertImages.php --thumb-sizes=1000,1250

# To only work on some images run
php extensions/WebP/maintenance/ConvertImages.php --titles=ImageA.jpg,ImageB.png

# To force the creation of already existing images run
php extensions/WebP/maintenance/ConvertImages.php --overwrite

# Only work on page titles matching a prefix
# Every page starting with prefix 'Example' will be selected
php extensions/WebP/maintenance/ConvertImages.php --title-prefix=Example

# Only work on page titles matching a file-type
# Every page starting with file-type 'png' will be selected
# Can be combined with 'title-prefix'
php extensions/WebP/maintenance/ConvertImages.php --file-type=png
```

## Installation
Inside LocalSettings.php do:
```php
wfLoadExtension( 'WebP' );

$wgWebPCompressionQuality = 50;
$wgWebPFilterStrength = 50;
$wgWebPAutoFilter = true;
$wgWebPConvertInJobQueue = true;
$wgWebPEnableConvertOnUpload = true;
$wgWebPEnableConvertOnTransform = true;
```

**Note: This extension registers itself as a local file repo!**  
`$wgLocalFileRepo['class'] = LocalWebPFileRepo::class;`

## De-Installation
Delete the folders `images/webp` and `images/thumbs/webp` and remove the extension.
