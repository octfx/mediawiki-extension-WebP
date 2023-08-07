# WebP Extension
Upon file upload this extension creates a WebP (and Avif if active) version of the uploaded image and its thumbs.

Requires a working job queue, [Extension:PictureHtmlSupport](https://github.com/StarCitizenWiki/mediawiki-extensions-PictureHtmlSupport), and a usable version of `imagick`, `libwebp (cwebp)`, or `gd` installed.

## How does this work?
After an upload or file transformation, a transform job is dispatched that creates a webp (and avif if active) file version of the original file.  

Additionally, the PictureHtmlSupport extension exposes a hook when a thumbnail is added to the page output.  
Extension:WebP utilizes this hook to add a `<source>` element for each active image transformer.

## Converting already uploaded images
A maintenance script exists to convert already uploaded images:
```shell
php extensions/WebP/maintenance/CreateFromLocalFiles.php

# To only convert non-thumbnails run
php extensions/WebP/maintenance/CreateFromLocalFiles.php --no-thumbs

# To create thumbnails of custom sizes run
# This will create two thumbnails with size 1000px and 1250px
php extensions/WebP/maintenance/CreateFromLocalFiles.php --thumb-sizes=1000,1250

# To only work on some images run
php extensions/WebP/maintenance/CreateFromLocalFiles.php --titles=ImageA.jpg,ImageB.png

# To force the creation of already existing images run
php extensions/WebP/maintenance/CreateFromLocalFiles.php --overwrite

# Only work on page titles matching a prefix
# Every page starting with prefix 'Example' will be selected
php extensions/WebP/maintenance/CreateFromLocalFiles.php --title-prefix=Example

# Only work on page titles matching a file-type
# Every page starting with file-type 'png' will be selected
# Can be combined with 'title-prefix'
php extensions/WebP/maintenance/CreateFromLocalFiles.php --file-type=png
```

## Installation

```php
wfLoadExtension( 'WebP' );
```


## Configuration
| Key                                | Description                                                                                                                                                               | Example                                                                                                                    | Default                     |
|------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|-----------------------------|
| $wgWebPEnableConvertOnUpload       | Enables file creation after a new image was uploaded. Doesn't work for copy uploads.                                                                                      | true                                                                                                                       | true                        |
| $wgWebPEnableConvertOnTransform    | Enables file creation after a thumbnail was created. This isn't necessary if a thumbhandler is active.                                                                    | false                                                                                                                      | true                        |
| $wgWebPEnableResponsiveVersionJobs | Dispatch transform jobs for 1.5x and 2x file versions. Note: This runs for each thumbnail inclusion and may be disabled after all present thumbnails have been converted. | false                                                                                                                      | true                        |
| $wgWebPCheckAcceptHeader           | Check if the accept header contains webp. If not the original file will be served.                                                                                        | true                                                                                                                       | false                       |
| $wgWebPCompressionQuality          | Compression Quality. Lower means worse.                                                                                                                                   | 50                                                                                                                         | 75                          |
| $wgWebPFilterStrength              | Alpha compression strength. Sets imagick `webp:alpha-quality` and `cwebp -alpha_q`. Lossless is 100.                                                                      | 50                                                                                                                         | 80                          |
| $wgWebPAutoFilter                  | Enables the auto filter.  This algorithm will spend additional time optimizing the filtering strength to reach a well-balanced quality.                                   | false                                                                                                                      | true                        |
| $wgWebPThumbSizes                  | Thumbnail Sizes to create through the maintenance script                                                                                                                  | [2400]                                                                                                                     | [120, 320, 800, 1200, 1600] |
| $wgEnabledTransformers             | List of enabled image transformers                                                                                                                                        | [ "MediaWiki\\Extension\\WebP\\Transformer\\WebPTransformer", "MediaWiki\\Extension\\WebP\\Transformer\\AvifTransformer" ] | WebP Transformer            |

## De-Installation
Delete the folders `images/webp` and `images/thumbs/webp` and remove the extension.  
If the Avif transformer is active remove `images/avif` and `images/thumbs/avif`.
