# Changelog for Safe Report Comments

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.1] - 2014-07-23

### Fixed

- Typo fix (props @spencermorin).

## [0.4] - 2014-07-23

### Security

- Security fix (h/t vortfu).

## [0.3.2] - 2013-03-06

### Added

- New `safe_report_comments_allow_moderated_to_be_reflagged` filter allows comments to be reflagged after being moderated.

## [0.3.1] - 2012-11-21

### Fixed

- Use `home_url()` for generating the `ajaxurl` on mapped domains, but `admin_url()` where the domain isn't mapped.

## [0.3] - 2012-11-07

### Changed

- Coding standards and cleanup.

[0.4.1]: https://github.com/Automattic/safe-report-comments/compare/0.4...0.4.1
[0.4]: https://github.com/Automattic/safe-report-comments/compare/0.3.2...0.4
[0.3.2]: https://github.com/Automattic/safe-report-comments/compare/0.3.1...0.3.2
[0.3.1]: https://github.com/Automattic/safe-report-comments/compare/0.3...0.3.1
