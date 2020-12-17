# WebP Extension
This is currently WIP

Upon file upload this extensions creates a webp file next to the original image.

If an webp file exists, and the browser supports webp images, all image links are changed out.

Requires a usable version of imagick.

## Installation
Inside LocalSettings.php do:
```php
wfLoadExtension( 'WebP' );

$wgWebPCompressionQuality = 50;
$wgWebPFilterStrength = 50;
$wgWebPAutoFilter = true;
```
