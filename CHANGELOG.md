# Changelog for Safe Report Comments

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.0] - 2026-08-20

This release formalises the plugin's minimum requirements as WordPress 6.4 and PHP 7.4.

### Changed

- In automatic mode the report link is attached via the `comment_text` filter and positioned client-side, instead of via the `comment_reply_link` filter.
- Front-end flagging messages and the "Report comment" link text are now translatable; they were previously hard-coded in English (#33).

### Removed

- Internal `add_flagging_link()` method and the `safe_report_comments_comment_reply_link` filter, which the new placement mechanism no longer uses.

### Fixed

- Report link no longer disappears on themes that modify the comment reply-link markup, such as Twenty Twenty. It is now positioned in the browser next to the reply link rather than by parsing that markup server-side (#14).
- Report link now appears on comments at the maximum threading depth, where WordPress renders no reply link.
- Report link now appears in block themes that render comments with the Comment Content block, not only classic `wp_list_comments()` output.
- Reporting a comment already marked as spam or trashed no longer pushes it back into the moderation queue; only currently approved comments are moved to hold (#16).
- A visitor's own report is remembered again via the flag cookie, so the same comment can no longer be reported repeatedly by one person (#15).

### Security

- Flagging is now restricted to genuine, public comment types. Previously any numeric ID that shared the comments table — such as WooCommerce order notes — could be reported and forced into moderation once the threshold was reached, even though no report link was ever shown for it. A new `is_reportable_comment()` helper gates flagging, with filters to adjust the reportable types or veto a target.

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

[0.5.0]: https://github.com/Automattic/safe-report-comments/compare/0.4.1...0.5.0
[0.4.1]: https://github.com/Automattic/safe-report-comments/compare/0.4...0.4.1
[0.4]: https://github.com/Automattic/safe-report-comments/compare/0.3.2...0.4
[0.3.2]: https://github.com/Automattic/safe-report-comments/compare/0.3.1...0.3.2
[0.3.1]: https://github.com/Automattic/safe-report-comments/compare/0.3...0.3.1
