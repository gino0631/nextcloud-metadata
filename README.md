# Metadata
[![Build Status](https://api.travis-ci.com/gino0631/nextcloud-metadata.svg?branch=master)](https://app.travis-ci.com/gino0631/nextcloud-metadata)
[![Last Commit](https://img.shields.io/github/last-commit/gino0631/nextcloud-metadata)](https://github.com/gino0631/nextcloud-metadata/commits/master)
[![Repo Size](https://img.shields.io/github/repo-size/gino0631/nextcloud-metadata)](https://github.com/gino0631/nextcloud-metadata)
[![Buy Me a Coffee](https://shields.io/badge/buy_me-a_coffee-ffdd00?logo=buymeacoffee)](https://www.buymeacoffee.com/gino0631)

A [Nextcloud](https://nextcloud.com/) plugin which displays file metadata in the file details sidebar. Currently, the supported file types include:
- application/pdf (Document information dictionary)
- application/zip (Comment, number of files)
- audio/flac (VorbisComment)
- audio/mp4 (QuickTime)
- audio/mpeg (ID3v1 and ID3v2)
- audio/ogg (VorbisComment)
- audio/wav (RIFF)
- image/heic (EXIF)
- image/jpeg (EXIF, IPTC, XMP-dc, XMP-photoshop, XMP-mwg-rs, XMP-digiKam TagsList)
- image/tiff (EXIF, IPTC, XMP-dc, XMP-photoshop, XMP-mwg-rs, XMP-digiKam TagsList)
- image/x-dcraw (EXIF, XMP sidecar files)
- video/mp4 (QuickTime)
- video/quicktime (QuickTime)
- video/webm (Matroska)
- video/x-matroska (Matroska)

Support for other formats may be implemented in future releases (feel free to make feature requests).

<br><kbd><img src="screenshots/jpg-metadata.png?raw=true"></kbd>

## Localization
The plugin makes use of [Transifex](https://www.transifex.com/), so you can contribute [here](https://www.transifex.com/nextcloud/nextcloud/metadata/).

## Requirements
* PHP 8.0 or later (tested successfully with 8.1, 8.2, and 8.3; other versions may or may not work)
* EXIF support for PHP (if `php --ri exif` returns `Extension 'exif' not present`, you might need to install an appropriate package for your system, e.g. by running `pkg install php81-exif` on FreeBSD/NAS4Free)
