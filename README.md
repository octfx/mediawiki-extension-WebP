# WebP Extension
Still somewhat experimental.

Upon file upload this extensions creates a WebP file next to the original image.

If an WebP file exists, and the browser supports WebP images, all image links are changed out.

Requires a usable version of imagick and `libwebp` installed.

## Converting already uploaded images
A maintenance script exists to convert already uploaded images:
```shell
php extensions/WebP/maintenance/convert_images.php

# To only convert non-thumbnails run
php extensions/WebP/maintenance/convert_images.php --no-thumbs

# To create thumbnails of custom sizes run
# This will create two thumbnails with size 1000px and 1250px
php extensions/WebP/maintenance/convert_images.php --thumb-sizes=1000,1250

# To only work on some images run
php extensions/WebP/maintenance/convert_images.php --titles=ImageA.jpg,ImageB.png
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
