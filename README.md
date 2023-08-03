# WebP Extension
While this has been in active use on star-citizen.wiki for over a year, I still deem this somewhat experimental, as many edge-cases (may) remain.

Upon file upload this extension creates a WebP version of the uploaded image.

Requires a working job queue, [Extension:PictureHtmlSupport](https://github.com/StarCitizenWiki/mediawiki-extensions-PictureHtmlSupport), and a usable version of `imagick`, `libwebp (cwebp)`, or `gd` installed.


## How does this work?
After an upload or file transformation, a transform job is dispatched that creates a webp file version of the original file.  
The PictureHtmlSupport extension then exposes a hook when a thumbnail is output. Extension:WebP uses this hook to add a `<source>` element to the output, containing the webp file version.

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
| Key                                | Description                                                                                                                                                               | Example                                                                                                                    | Default                     |
|------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|-----------------------------|
| $wgWebPEnableConvertOnUpload       | Enables WebP creation after a new image was uploaded. Doesn't work for copy uploads.                                                                                      | true                                                                                                                       | false                       |
| $wgWebPEnableConvertOnTransform    | Enables WebP creation after a thumbnail was created. This isn't necessary if a thumbhandler is active.                                                                    | false                                                                                                                      | true                        |
| $wgWebPEnableResponsiveVersionJobs | Dispatch transform jobs for 1.5x and 2x file versions. Note: This runs for each thumbnail inclusion and may be disabled after all present thumbnails have been converted. | false                                                                                                                      | true                        |
| $wgWebPCheckAcceptHeader           | Check if the accept header contains webp. If not the original file will be served.                                                                                        | true                                                                                                                       | false                       |
| $wgWebPCompressionQuality          | Compression Quality. Lower means worse.                                                                                                                                   | 50                                                                                                                         | 80                          |
| $wgWebPFilterStrength              | Alpha compression strength. Sets imagick `webp:alpha-quality` and `cwebp -alpha_q`. Lossless is 100.                                                                      | 50                                                                                                                         | 80                          |
| $wgWebPAutoFilter                  | Enables the auto filter.  This algorithm will spend additional time optimizing the filtering strength to reach a well-balanced quality.                                   | false                                                                                                                      | true                        |
| $wgWebPThumbSizes                  | Thumbnail Sizes to create through the maintenance script                                                                                                                  | [2400]                                                                                                                     | [120, 320, 800, 1200, 1600] |
| $wgEnabledTransformers             | List of enabled image transformers                                                                                                                                        | [ "MediaWiki\\Extension\\WebP\\Transformer\\WebPTransformer", "MediaWiki\\Extension\\WebP\\Transformer\\AvifTransformer" ] | WebP Transformer            |

## De-Installation
Delete the folders `images/webp` and `images/thumbs/webp` and remove the extension.
