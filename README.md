# WebP Extension
While this has been in active use on star-citizen.wiki for over a year, I still deem this somewhat experimental.

Upon file upload this extension creates a WebP version of the uploaded image.

If an WebP file exists, and the browser supports WebP images, the link for the current image is changed for the webp version.

Requires a usable version of `imagick` and `libwebp` installed.

This extension works best when thumbnail generation through [`thumb.php`](https://www.mediawiki.org/wiki/Manual:Thumb.php) is enabled.

## How does this work?
The basic idea of this extension is to transparently change out all existing images of a wiki to webp versions without requiring a re-upload.  
This works by installing a new file repo that first checks `/webp` sub-folders for existing files and falling back to the original version if nothing was found.  

It works best when the thumb handler is active, as this will enable rendering webp thumbnails on the fly.

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

## Configuration
| Key                             | Description                                                                                                                             | Example | Default                     |
|---------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------|---------|-----------------------------|
| $wgWebPEnableConvertOnUpload    | Enables WebP creation after a new image was uploaded. Doesn't work for copy uploads.                                                    | true    | false                       |
| $wgWebPEnableConvertOnTransform | Enables WebP creation after a thumbnail was created. This isn't necessary if a thumbhandler is active.                                  | false   | true                        |
| $wgWebPCheckAcceptHeader        | Check if the accept header contains webp. If not the original file will be served.                                                      | true    | false                       |
| $wgWebPCompressionQuality       | Compression Quality. Lower means worse.                                                                                                 | 50      | 80                          |
| $wgWebPFilterStrength           | Alpha compression strength. Sets imagick `webp:alpha-quality` and `cwebp -alpha_q`. Lossless is 100.                                    | 50      | 80                          |
| $wgWebPAutoFilter               | Enables the auto filter.  This algorithm will spend additional time optimizing the filtering strength to reach a well-balanced quality. | false   | true                        |
| $wgWebPConvertInJobQueue        | Converts files in the job queue after an image was uploaded.                                                                            | false   | true                        |
| $wgWebPThumbSizes               | Thumbnail Sizes to create through the maintenance script                                                                                | [2400]  | [120, 320, 800, 1200, 1600] |

## De-Installation
Delete the folders `images/webp` and `images/thumbs/webp` and remove the extension.

## Known Issues
MultiMediaViewer breaks when thumbhandler is disabled and the original sized image is displayed.  
This is due to Extension:WebP changing the url to the webp version, and MMV using the url to query the api for file info.  
As the url is containing `.webp` and no file page ending in `.webp` exists on the wiki, MMW will display that this file is missing.

There is currently no workaround other than enabling `thumb.php`. (? Help welcome)
