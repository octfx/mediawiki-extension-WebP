# WebP Extension
While this has been in active use on star-citizen.wiki for over a year, I still deem this somewhat experimental, as many edge-cases (may) remain.

Upon file upload this extension creates a WebP version of the uploaded image.

Requires a working job queue, [Extension:PictureHtmlSupport](https://github.com/StarCitizenWiki/mediawiki-extensions-PictureHtmlSupport), and a usable version of `imagick`, `libwebp (cwebp)`, or `gd` installed.


## How does this work?
The basic idea of this extension is to transparently change out all existing images of a wiki to webp versions without requiring a re-upload.  
This works by installing a new file repo that first checks `/webp` sub-folders for existing files and falling back to the original version if nothing was found.  

It works best when the thumb handler is active, as this will enable rendering webp thumbnails on the fly.

## Converting already uploaded images
A maintenance script exists to convert already uploaded images:
```shell
php extensions/WebP/maintenance/CreateWebPFilesFromLocalFiles.php

# To only convert non-thumbnails run
php extensions/WebP/maintenance/CreateWebPFilesFromLocalFiles.php --no-thumbs

# To create thumbnails of custom sizes run
# This will create two thumbnails with size 1000px and 1250px
php extensions/WebP/maintenance/CreateWebPFilesFromLocalFiles.php --thumb-sizes=1000,1250

# To only work on some images run
php extensions/WebP/maintenance/CreateWebPFilesFromLocalFiles.php --titles=ImageA.jpg,ImageB.png

# To force the creation of already existing images run
php extensions/WebP/maintenance/CreateWebPFilesFromLocalFiles.php --overwrite

# Only work on page titles matching a prefix
# Every page starting with prefix 'Example' will be selected
php extensions/WebP/maintenance/CreateWebPFilesFromLocalFiles.php --title-prefix=Example

# Only work on page titles matching a file-type
# Every page starting with file-type 'png' will be selected
# Can be combined with 'title-prefix'
php extensions/WebP/maintenance/CreateWebPFilesFromLocalFiles.php --file-type=png
```

## Installation
Inside LocalSettings.php do:
```php
wfLoadExtension( 'WebP' );

$wgWebPCompressionQuality = 50;
$wgWebPFilterStrength = 50;
$wgWebPAutoFilter = true;
$wgWebPEnableConvertOnUpload = true;
$wgWebPEnableConvertOnTransform = true;
```


## Configuration
| Key                             | Description                                                                                                                             | Example | Default                     |
|---------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------|---------|-----------------------------|
| $wgWebPEnableConvertOnUpload    | Enables WebP creation after a new image was uploaded. Doesn't work for copy uploads.                                                    | true    | false                       |
| $wgWebPEnableConvertOnTransform | Enables WebP creation after a thumbnail was created. This isn't necessary if a thumbhandler is active.                                  | false   | true                        |
| $wgWebPCheckAcceptHeader        | Check if the accept header contains webp. If not the original file will be served.                                                      | true    | false                       |
| $wgWebPCompressionQuality       | Compression Quality. Lower means worse.                                                                                                 | 50      | 80                          |
| $wgWebPFilterStrength           | Alpha compression strength. Sets imagick `webp:alpha-quality` and `cwebp -alpha_q`. Lossless is 100.                                    | 50      | 80                          |
| $wgWebPAutoFilter               | Enables the auto filter.  This algorithm will spend additional time optimizing the filtering strength to reach a well-balanced quality. | false   | true                        |
| $wgWebPThumbSizes               | Thumbnail Sizes to create through the maintenance script                                                                                | [2400]  | [120, 320, 800, 1200, 1600] |

## De-Installation
Delete the folders `images/webp` and `images/thumbs/webp` and remove the extension.


## Current state
Tested on a fresh local installation of MW 1.39.0-rc.1, in WSL, using PHP 7.4.30 and NGINX, installed as specified in this Readme.  
Files were uploaded using the standard MW upload form, i.e., Special:Upload.  
Test were conducted with and without thumbhandler active.  

| Tested                             | Works  | Notes                                             |
|------------------------------------|--------|---------------------------------------------------|
| Upload of jpg and png files        | Yes    | WebP Thumbs are created                           |
| Upload of unsupported files        | Yes    | Files are shown normally                          |
| Automated creation of thumbnails   | Yes    |                                                   |
| File moving                        | Yes    | Empty folder may remain                           |
| File deletion                      | Mostly | One file errored out, but not reproducible        |
| !! Uploading of new file versions  | ?      | Local tests worked, Reports say otherwise         |
| Maintenance scripts: Create Images | Mostly | Job complains that files exist, files are created |
| Maintenance scripts: Remove Images | Yes    | Empty folders remain                              |
