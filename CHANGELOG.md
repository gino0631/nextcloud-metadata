# Changelog

## [Unreleased]

## 0.15.0 – 2021-12-02
### Added
- Compatibility with Nextcloud 23.

### Changed
- Required PHP version changed from 5.6 to 7.3.

## 0.14.0 – 2021-07-10
### Added
- ZIP support (number of files, archive comment)
- Compatibility with Nextcloud 22.

### Changed
- Warnings produced by `exif_read_data` will be ignored
  [#78](https://github.com/gino0631/nextcloud-metadata/issues/78)

### Fixed
- HEIC metadata not being pulled
  [#76](https://github.com/gino0631/nextcloud-metadata/issues/76)

## 0.13.0 – 2021-02-27
### Added
- Support for ACDSee caption and categories metadata
  [#67](https://github.com/gino0631/nextcloud-metadata/issues/67)
- Support for PHP 8.0
- Compatibility with Nextcloud 21.

### Fixed
- PHP 7.4 errors fixed by updating GetID3 to 1.9.20
  [#62](https://github.com/gino0631/nextcloud-metadata/issues/62)
- Null parameter in array_merge()
  [#66](https://github.com/gino0631/nextcloud-metadata/issues/66)
- Error messages when parsing certain files
  [#68](https://github.com/gino0631/nextcloud-metadata/issues/68)

## 0.12.0 – 2020-07-03
### Added
- Support for HEIC image files
  [#41](https://github.com/gino0631/nextcloud-metadata/issues/41)
- Support for image Subject metadata written by Windows
  [#57](https://github.com/gino0631/nextcloud-metadata/issues/57)
- Support for ratings
  [#39](https://github.com/gino0631/nextcloud-metadata/issues/39)
- Translations to many languages from
  [Transifex](https://www.transifex.com/nextcloud/nextcloud/metadata/)

### Changed
- Display of exposure bias
  [#55](https://github.com/gino0631/nextcloud-metadata/issues/55)

### Fixed
- Trying to access array offset on value of type bool
  [#54](https://github.com/gino0631/nextcloud-metadata/issues/54)
- Unparenthesized `a ? b : c ? d : e` is deprecated
  [#53](https://github.com/gino0631/nextcloud-metadata/issues/53)
- Handling of GPS coordinates encoded as all zeros
  [#61](https://github.com/gino0631/nextcloud-metadata/issues/61)

## 0.11.1 – 2020-01-21
### Fixed
- Nextcloud crash since 0.11.0
  [#49](https://github.com/gino0631/nextcloud-metadata/issues/49)

## 0.11.0 – 2020-01-20
### Added
- Compatibility with Nextcloud 18.

### Fixed
- App sidebar not displayed in Nextcloud 18
  [#45](https://github.com/gino0631/nextcloud-metadata/issues/45)
- Exception when trying to show metadata of a certain MP3 file
  [#44](https://github.com/gino0631/nextcloud-metadata/issues/44)

## 0.10.0 – 2019-10-05
### Added
- Compatibility with Nextcloud 17.

### Fixed
- Handling of Unicode comments and missing IPTC metadata fixed
  [#36](https://github.com/gino0631/nextcloud-metadata/issues/36)

## 0.9.0 – 2019-01-27
### Added
- Support for PHP 7.3 (though some functionality may be affected
  by the bugs in PHP, see the discussion for details)
  [#29](https://github.com/gino0631/nextcloud-metadata/issues/29)
- Sidebar tab icon
  [#28](https://github.com/gino0631/nextcloud-metadata/issues/28)
- Support for IPTC metadata
  [#31](https://github.com/gino0631/nextcloud-metadata/issues/31)
- Support for XMP sidecar files
  [#21](https://github.com/gino0631/nextcloud-metadata/issues/21)

## 0.8.0 – 2018-11-18
### Added
- Support for PHP 7.2 (though some functionality may be affected
  by the bugs in PHP, see the discussion for details)
  [#19](https://github.com/gino0631/nextcloud-metadata/issues/19)
- Support for RAW image files
  [#22](https://github.com/gino0631/nextcloud-metadata/pull/22)
- Support for WEBM video files
  [#25](https://github.com/gino0631/nextcloud-metadata/pull/25)

### Changed
- Display of image metadata improved

## 0.7.0 – 2018-08-12
### Added
- Support for additional video metadata
  [#12](https://github.com/gino0631/nextcloud-metadata/issues/12)
- Support for keywords
  [#13](https://github.com/gino0631/nextcloud-metadata/issues/13)

### Changed
- Display of image metadata improved
- Error handling improved

### Fixed
- XMP parsing fixed
  [#11](https://github.com/gino0631/nextcloud-metadata/issues/11)
- GPS info parsing fixed
  [#15](https://github.com/gino0631/nextcloud-metadata/issues/15)

## 0.6.0 – 2017-10-22
### Added
- Support for EXIF UserComment and DigiKam TagsList
  [#7](https://github.com/gino0631/nextcloud-metadata/issues/7)

### Changed
- Display of some attributes improved
 
### Fixed
- Fail to initialize the main page when using this app
  [#8](https://github.com/gino0631/nextcloud-metadata/issues/8)

## 0.5.0 – 2017-08-21
### Added
- Support for XMP metadata in JPEG and TIFF files

### Changed
- Display of some attributes improved

## 0.4.0 – 2017-08-15
### Added
- MP3 (ID3v2), OGG, FLAC and WAVE audio support
- MPEG, MKV and FLV video support

## 0.3.0 – 2017-08-06
### Added
- MP3 support

### Changed
- Display of some EXIF attributes improved

## 0.2.0 – 2017-08-05
### Added
- Integration with OpenStreetMap

### Fixed
- Metadata not shown for non-admin users
  [#1](https://github.com/gino0631/nextcloud-metadata/issues/1)
